<?php
/**
 * Redis 缓存类
 * 用于缓存热点数据，减少数据库查询
 */

// 加载环境变量
if (file_exists(__DIR__ . '/../../.env.sms')) {
    $lines = file(__DIR__ . '/../../.env.sms', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // 跳过注释和空行
        if (empty($line) || strpos($line, '#') === 0) continue;
        // 跳过包含注释的行（但保留值中的#）
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // 如果值后面有注释，去掉注释部分
        if (strpos($value, ' #') !== false) {
            $value = trim(substr($value, 0, strpos($value, ' #')));
        }
        
        putenv($name . '=' . $value);
    }
}

class RedisCache {
    private static $instance = null;
    private $redis;
    private $enabled = false;
    
    private function __construct() {
        try {
            // 检查 Redis 是否启用
            if (getenv('REDIS_ENABLED') !== 'true') {
                error_log('Redis 缓存已禁用');
                return;
            }
            
            // 检查 Redis 扩展是否安装
            if (!class_exists('Redis')) {
                error_log('Redis 扩展未安装，缓存功能已禁用');
                return;
            }
            
            $this->redis = new Redis();
            
            // 从环境变量读取配置
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('REDIS_PORT') ?: 6379;
            $password = getenv('REDIS_PASSWORD');
            $db = getenv('REDIS_DB') ?: 0;
            
            // 连接 Redis
            $connected = $this->redis->connect($host, $port, 2); // 2秒超时
            
            if (!$connected) {
                error_log('Redis 连接失败，缓存功能已禁用');
                return;
            }
            
            // 如果有密码，进行认证
            if ($password) {
                $this->redis->auth($password);
            }
            
            // 选择数据库
            $this->redis->select($db);
            
            // 设置序列化方式为 JSON
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            
            // 设置键前缀，避免冲突
            $this->redis->setOption(Redis::OPT_PREFIX, 'lucky_draw:');
            
            $this->enabled = true;
            error_log('Redis 连接成功 (Host: ' . $host . ':' . $port . ', DB: ' . $db . ')');
            
        } catch (Exception $e) {
            error_log('Redis 初始化失败: ' . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 检查 Redis 是否可用
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在返回 null
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $value = $this->redis->get($key);
            return $value === false ? null : $value;
        } catch (Exception $e) {
            error_log('Redis GET 失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），默认 300 秒（5分钟）
     * @return bool 是否成功
     */
    public function set($key, $value, $ttl = 300) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log('Redis SET 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public function delete($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log('Redis DELETE 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查缓存是否存在
     * @param string $key 缓存键
     * @return bool 是否存在
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log('Redis EXISTS 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量删除缓存（支持通配符）
     * @param string $pattern 匹配模式，如 "prizes:*"
     * @return int 删除的键数量
     */
    public function deletePattern($pattern) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            return $this->redis->del($keys);
        } catch (Exception $e) {
            error_log('Redis DELETE PATTERN 失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 增加计数器
     * @param string $key 缓存键
     * @param int $value 增加的值，默认 1
     * @return int|false 增加后的值，失败返回 false
     */
    public function increment($key, $value = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($key, $value);
        } catch (Exception $e) {
            error_log('Redis INCREMENT 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 设置过期时间
     * @param string $key 缓存键
     * @param int $ttl 过期时间（秒）
     * @return bool 是否成功
     */
    public function expire($key, $ttl) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log('Redis EXPIRE 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 减少计数器（原子操作）
     * @param string $key 缓存键
     * @param int $value 减少的值，默认 1
     * @return int|false 减少后的值，失败返回 false
     */
    public function decrement($key, $value = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->decrBy($key, $value);
        } catch (Exception $e) {
            error_log('Redis DECREMENT 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取哈希表的所有字段和值
     * @param string $key 哈希表键
     * @return array|null 哈希表数据，失败返回 null
     */
    public function hGetAll($key) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $data = $this->redis->hGetAll($key);
            return $data === false ? null : $data;
        } catch (Exception $e) {
            error_log('Redis HGETALL 失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 设置哈希表的字段值
     * @param string $key 哈希表键
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @return bool 是否成功
     */
    public function hSet($key, $field, $value) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->hSet($key, $field, $value) !== false;
        } catch (Exception $e) {
            error_log('Redis HSET 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量设置哈希表的字段值
     * @param string $key 哈希表键
     * @param array $data 字段值数组
     * @return bool 是否成功
     */
    public function hMSet($key, $data) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->hMSet($key, $data);
        } catch (Exception $e) {
            error_log('Redis HMSET 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取哈希表的字段值
     * @param string $key 哈希表键
     * @param string $field 字段名
     * @return mixed|null 字段值，不存在返回 null
     */
    public function hGet($key, $field) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $value = $this->redis->hGet($key, $field);
            return $value === false ? null : $value;
        } catch (Exception $e) {
            error_log('Redis HGET 失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 尝试获取分布式锁
     * @param string $key 锁的键
     * @param int $ttl 锁的过期时间（秒），默认 5 秒
     * @return bool 是否成功获取锁
     */
    public function lock($key, $ttl = 5) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            // 使用 SET NX EX 实现分布式锁
            return $this->redis->set($key, 1, ['NX', 'EX' => $ttl]);
        } catch (Exception $e) {
            error_log('Redis LOCK 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 释放分布式锁
     * @param string $key 锁的键
     * @return bool 是否成功释放
     */
    public function unlock($key) {
        return $this->delete($key);
    }
    
    /**
     * 将列表推入队列（左侧）
     * @param string $key 队列键
     * @param mixed $value 值
     * @return int|false 队列长度，失败返回 false
     */
    public function lpush($key, $value) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->lPush($key, $value);
        } catch (Exception $e) {
            error_log('Redis LPUSH 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 从队列弹出元素（右侧，阻塞）
     * @param string $key 队列键
     * @param int $timeout 超时时间（秒），0 表示永久阻塞
     * @return array|null [队列名, 值]，超时返回 null
     */
    public function brpop($key, $timeout = 0) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $result = $this->redis->brPop($key, $timeout);
            return $result === false ? null : $result;
        } catch (Exception $e) {
            error_log('Redis BRPOP 失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取队列长度
     * @param string $key 队列键
     * @return int 队列长度
     */
    public function llen($key) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            return $this->redis->lLen($key);
        } catch (Exception $e) {
            error_log('Redis LLEN 失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取 Redis 原生对象（用于高级操作）
     * @return Redis|null
     */
    public function getRedis() {
        return $this->enabled ? $this->redis : null;
    }
}
?>
