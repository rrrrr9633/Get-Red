<?php
/**
 * 抽奖缓存管理类
 * 专门处理抽奖系统的缓存逻辑
 */

require_once __DIR__ . '/redis-cache.php';
require_once __DIR__ . '/database.php';

class DrawCache {
    private $redis;
    private $pdo;
    private $enabled;
    
    // 缓存键前缀
    const PRIZES_KEY = 'prizes:';           // 奖品列表: prizes:lucky1
    const STOCK_KEY = 'stock:';             // 库存: stock:123
    const USER_GOLD_KEY = 'user:gold:';     // 用户金币: user:gold:1
    const LOCK_KEY = 'lock:draw:';          // 抽奖锁: lock:draw:1
    const QUEUE_KEY = 'queue:draw';         // 异步队列
    
    // 缓存过期时间
    const PRIZES_TTL = 300;      // 奖品列表缓存5分钟
    const USER_GOLD_TTL = 3600;  // 用户金币缓存1小时
    const LOCK_TTL = 5;          // 抽奖锁5秒
    
    public function __construct() {
        $this->redis = RedisCache::getInstance();
        $this->enabled = $this->redis->isEnabled();
        
        if ($this->enabled) {
            error_log('DrawCache: Redis缓存已启用');
        } else {
            error_log('DrawCache: Redis缓存未启用，将直接查询数据库');
        }
    }
    
    /**
     * 检查缓存是否启用
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * 获取PDO连接
     */
    private function getPdo() {
        if (!$this->pdo) {
            $database = new Database();
            $this->pdo = $database->getConnection();
        }
        return $this->pdo;
    }
    
    /**
     * 获取奖品列表（带缓存）
     * @param string $luckyPage 页面标识
     * @return array 奖品列表
     */
    public function getPrizes($luckyPage) {
        $cacheKey = self::PRIZES_KEY . $luckyPage;
        
        // 尝试从缓存获取
        if ($this->enabled) {
            $cached = $this->redis->hGetAll($cacheKey);
            if ($cached !== null && !empty($cached)) {
                // 反序列化奖品数据
                $prizes = [];
                foreach ($cached as $prizeId => $prizeJson) {
                    $prizes[] = json_decode($prizeJson, true);
                }
                error_log("DrawCache: 从缓存获取奖品列表 (页面: $luckyPage, 数量: " . count($prizes) . ")");
                return $prizes;
            }
        }
        
        // 缓存未命中，从数据库查询
        $prizes = $this->loadPrizesFromDB($luckyPage);
        
        // 写入缓存
        if ($this->enabled && !empty($prizes)) {
            $cacheData = [];
            foreach ($prizes as $prize) {
                $cacheData[$prize['id']] = json_encode($prize);
            }
            $this->redis->hMSet($cacheKey, $cacheData);
            $this->redis->expire($cacheKey, self::PRIZES_TTL);
            error_log("DrawCache: 奖品列表已缓存 (页面: $luckyPage, 数量: " . count($prizes) . ")");
        }
        
        return $prizes;
    }
    
    /**
     * 从数据库加载奖品列表
     */
    private function loadPrizesFromDB($luckyPage) {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.icon,
                p.image_url,
                p.value,
                COALESCE(plp.page_probability, p.probability) AS probability,
                p.rarity,
                p.quantity AS global_quantity,
                COALESCE(plp.page_quantity, p.quantity) AS quantity,
                p.original_probability
            FROM prizes p
            INNER JOIN prize_lucky_pages plp ON p.id = plp.prize_id
            WHERE plp.lucky_page = ? 
              AND p.active = 1 
              AND plp.enabled = 1
              AND COALESCE(plp.page_probability, p.probability) > 0
            ORDER BY COALESCE(plp.page_probability, p.probability) ASC
        ");
        $stmt->execute([$luckyPage]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 刷新奖品缓存
     * @param string $luckyPage 页面标识，null表示刷新所有
     */
    public function refreshPrizes($luckyPage = null) {
        if (!$this->enabled) {
            return;
        }
        
        if ($luckyPage) {
            // 刷新单个页面
            $this->redis->delete(self::PRIZES_KEY . $luckyPage);
            error_log("DrawCache: 已刷新奖品缓存 (页面: $luckyPage)");
        } else {
            // 刷新所有页面
            $this->redis->deletePattern(self::PRIZES_KEY . '*');
            error_log("DrawCache: 已刷新所有奖品缓存");
        }
    }
    
    /**
     * 原子减少库存
     * @param int $prizeId 奖品ID
     * @return int|false 剩余库存，失败返回false
     */
    public function decrementStock($prizeId) {
        if (!$this->enabled) {
            return false; // 未启用缓存，返回false表示需要走数据库逻辑
        }
        
        $cacheKey = self::STOCK_KEY . $prizeId;
        $stock = $this->redis->decrement($cacheKey, 1);
        
        if ($stock === false) {
            error_log("DrawCache: 库存减少失败 (奖品ID: $prizeId)");
            return false;
        }
        
        error_log("DrawCache: 库存已减少 (奖品ID: $prizeId, 剩余: $stock)");
        return $stock;
    }
    
    /**
     * 回滚库存（抽奖失败时）
     * @param int $prizeId 奖品ID
     */
    public function rollbackStock($prizeId) {
        if (!$this->enabled) {
            return;
        }
        
        $cacheKey = self::STOCK_KEY . $prizeId;
        $this->redis->increment($cacheKey, 1);
        error_log("DrawCache: 库存已回滚 (奖品ID: $prizeId)");
    }
    
    /**
     * 初始化库存缓存
     * @param string $luckyPage 页面标识
     */
    public function initStockCache($luckyPage) {
        if (!$this->enabled) {
            return;
        }
        
        $prizes = $this->loadPrizesFromDB($luckyPage);
        foreach ($prizes as $prize) {
            if ($prize['rarity'] === 'legendary' && isset($prize['quantity']) && $prize['quantity'] !== null) {
                $cacheKey = self::STOCK_KEY . $prize['id'];
                $this->redis->set($cacheKey, $prize['quantity'], 86400); // 缓存24小时
            }
        }
        error_log("DrawCache: 库存缓存已初始化 (页面: $luckyPage)");
    }
    
    /**
     * 获取用户金币（带缓存）
     * @param int $userId 用户ID
     * @return array ['bound_coins' => 0, 'unbound_coins' => 0]
     */
    public function getUserGold($userId) {
        $cacheKey = self::USER_GOLD_KEY . $userId;
        
        // 尝试从缓存获取
        if ($this->enabled) {
            $cached = $this->redis->get($cacheKey);
            if ($cached !== null) {
                return json_decode($cached, true);
            }
        }
        
        // 缓存未命中，从数据库查询
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $gold = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gold) {
            return ['bound_coins' => 0, 'unbound_coins' => 0];
        }
        
        // 写入缓存
        if ($this->enabled) {
            $this->redis->set($cacheKey, json_encode($gold), self::USER_GOLD_TTL);
        }
        
        return $gold;
    }
    
    /**
     * 刷新用户金币缓存
     * @param int $userId 用户ID
     */
    public function refreshUserGold($userId) {
        if (!$this->enabled) {
            return;
        }
        
        $cacheKey = self::USER_GOLD_KEY . $userId;
        $this->redis->delete($cacheKey);
    }
    
    /**
     * 尝试获取抽奖锁
     * @param int $userId 用户ID
     * @return bool 是否成功获取锁
     */
    public function acquireLock($userId) {
        if (!$this->enabled) {
            return true; // 未启用缓存，直接返回true
        }
        
        $cacheKey = self::LOCK_KEY . $userId;
        $locked = $this->redis->lock($cacheKey, self::LOCK_TTL);
        
        if ($locked) {
            error_log("DrawCache: 抽奖锁已获取 (用户ID: $userId)");
        } else {
            error_log("DrawCache: 抽奖锁获取失败，用户正在抽奖中 (用户ID: $userId)");
        }
        
        return $locked;
    }
    
    /**
     * 释放抽奖锁
     * @param int $userId 用户ID
     */
    public function releaseLock($userId) {
        if (!$this->enabled) {
            return;
        }
        
        $cacheKey = self::LOCK_KEY . $userId;
        $this->redis->unlock($cacheKey);
        error_log("DrawCache: 抽奖锁已释放 (用户ID: $userId)");
    }
    
    /**
     * 将抽奖结果推入异步队列
     * @param array $data 抽奖数据
     */
    public function pushToQueue($data) {
        if (!$this->enabled) {
            return false;
        }
        
        return $this->redis->lpush(self::QUEUE_KEY, json_encode($data));
    }
    
    /**
     * 从异步队列弹出数据
     * @param int $timeout 超时时间（秒）
     * @return array|null 抽奖数据
     */
    public function popFromQueue($timeout = 0) {
        if (!$this->enabled) {
            return null;
        }
        
        $result = $this->redis->brpop(self::QUEUE_KEY, $timeout);
        if ($result) {
            return json_decode($result[1], true);
        }
        return null;
    }
    
    /**
     * 获取队列长度
     * @return int 队列长度
     */
    public function getQueueLength() {
        if (!$this->enabled) {
            return 0;
        }
        
        return $this->redis->llen(self::QUEUE_KEY);
    }
}
?>
