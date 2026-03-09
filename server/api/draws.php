<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/coin-helper.php';
require_once '../config/draw-cache.php';

// 初始化数据库连接
$database = new Database();
$pdo = $database->getConnection();

// 初始化缓存
$drawCache = new DrawCache();

function performLuckyDraw($userId, $count = 1, $page = 'lucky1.html') {
    global $pdo, $drawCache;
    
    try {
        // ✅ 优化：分布式锁，防止用户重复提交
        if ($drawCache->isEnabled() && !$drawCache->acquireLock($userId)) {
            return [
                'success' => false,
                'message' => '请勿重复抽奖，请稍后再试'
            ];
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 🔒 使用 FOR UPDATE 锁定用户记录，防止并发问题
        $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        // 获取动态价格
        $priceType = '';
        if ($count == 1) {
            $priceType = 'single';
        } elseif ($count == 3) {
            $priceType = 'triple';
        } elseif ($count == 5) {
            $priceType = 'quintuple';
        } else {
            // 对于其他数量，使用单抽价格乘以数量
            $priceType = 'single';
        }
        
        // 从数据库获取价格
        $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = ?");
        $stmt->execute([$page, $priceType]);
        $priceValue = $stmt->fetchColumn();
        
        if ($priceValue === false) {
            // 如果没有找到价格配置，使用默认价格
            $defaultPrices = ['single' => 10, 'triple' => 30, 'quintuple' => 50];
            $priceValue = $defaultPrices[$priceType] ?? 10;
        }
        
        // 如果不是标准的1、3、5连抽，按单价计算
        if (!in_array($count, [1, 3, 5])) {
            $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = 'single'");
            $stmt->execute([$page]);
            $singlePrice = $stmt->fetchColumn() ?: 10;
            $cost = $count * $singlePrice;
        } else {
            $cost = $priceValue;
        }
        
        // ✅ 检查非绑定金币余额（抽奖只能使用非绑定金币）
        if ($user['unbound_coins'] < $cost) {
            throw new Exception('非绑定金币不足，请先充值！当前非绑定金币：' . $user['unbound_coins']);
        }
        
        // 获取Lucky页面标识
        $luckyPage = $page ? str_replace('.html', '', $page) : 'lucky1';
        
        // ✅ 优化：优先从缓存获取奖品列表
        $prizes = $drawCache->isEnabled() 
            ? $drawCache->getPrizes($luckyPage)
            : [];
        
        // 缓存未命中或未启用，从数据库查询
        if (empty($prizes)) {
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
            $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (empty($prizes)) {
            throw new Exception('暂无可抽取的奖品，请联系管理员');
        }
        
        $results = [];
        $totalValue = 0;
        $legendaryUpdates = []; // 记录传说物品的数量变化
        
        // ✅ 优化：在内存中执行抽奖循环，不再重复查询数据库
        for ($i = 0; $i < $count; $i++) {
            // 过滤掉概率为0的奖品（数量已耗尽）
            $availablePrizes = array_filter($prizes, function($p) {
                return floatval($p['probability']) > 0;
            });
            

            
            // 从内存中的奖品列表选择
            $prize = selectPrizeByProbability($availablePrizes);
            $results[] = $prize;
            $totalValue += floatval($prize['value']);
            
            // 如果是传说物品且有数量限制，在内存中更新数量
            if ($prize['rarity'] === 'legendary' && isset($prize['quantity']) && $prize['quantity'] !== null) {
                // 记录需要更新到数据库的数量变化
                if (!isset($legendaryUpdates[$prize['id']])) {
                    $legendaryUpdates[$prize['id']] = 0;
                }
                $legendaryUpdates[$prize['id']]++;
                
                // 在内存中更新数量，避免重复抽中
                foreach ($prizes as &$p) {
                    if ($p['id'] == $prize['id']) {
                        $p['quantity']--;
                        // 如果数量为0，将概率设为0，下次循环不会再抽中
                        if ($p['quantity'] <= 0) {
                            $p['probability'] = 0;
                        }
                        break;
                    }
                }
                unset($p); // 解除引用
            }
        }
        
        // ✅ 优化：批量更新传说物品数量（一次性处理所有更新）
        if (!empty($legendaryUpdates)) {
            foreach ($legendaryUpdates as $prizeId => $decreaseCount) {
                // 更新页面特定数量
                $stmt = $pdo->prepare("
                    UPDATE prize_lucky_pages 
                    SET page_quantity = page_quantity - ? 
                    WHERE prize_id = ? AND lucky_page = ?
                ");
                $stmt->execute([$decreaseCount, $prizeId, $luckyPage]);
                
                // 如果数量变为0，将概率设为0
                $stmt = $pdo->prepare("
                    UPDATE prize_lucky_pages 
                    SET page_probability = 0 
                    WHERE prize_id = ? AND lucky_page = ? AND page_quantity <= 0
                ");
                $stmt->execute([$prizeId, $luckyPage]);
            }
        }
        
        // ✅ 优化：批量插入用户物品（一次SQL代替N次）
        if (!empty($results)) {
            $values = [];
            $params = [];
            foreach ($results as $prize) {
                $values[] = "(?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [
                    $userId,
                    $prize['id'] ?? null,
                    $prize['name'],
                    $prize['icon'] ?? '🎁',
                    $prize['image_url'] ?? '',
                    $prize['value'],
                    $prize['rarity']
                ]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO user_items (user_id, prize_id, name, icon, image_url, value, rarity)
                VALUES " . implode(', ', $values)
            );
            $stmt->execute($params);
        }
        
        // ✅ 扣除非绑定金币（直接在当前事务中执行）
        $stmt = $pdo->prepare("UPDATE users SET unbound_coins = unbound_coins - ?, balance = balance - ? WHERE id = ? AND unbound_coins >= ?");
        $stmt->execute([$cost, $cost, $userId, $cost]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('扣除金币失败，请重试');
        }
        
        // 记录金币变动日志
        $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userAfter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            INSERT INTO coin_change_log 
            (user_id, change_type, coin_type, bound_change, unbound_change, 
             bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
             related_id, description)
            VALUES (?, 'draw', 'unbound', 0, ?, ?, ?, ?, ?, NULL, ?)
        ");
        $stmt->execute([
            $userId, 
            -$cost, 
            $user['bound_coins'] ?? 0,
            $user['unbound_coins'], 
            $userAfter['bound_coins'],
            $userAfter['unbound_coins'],
            "幸运掉落抽奖x{$count}({$page})"
        ]);
        
        // ✅ 优化：批量记录抽奖到 draws 表（一次SQL代替N次）
        if (!empty($results)) {
            $drawType = $count == 1 ? 'single' : ($count == 3 ? 'triple' : ($count == 5 ? 'quintuple' : 'multiple'));
            $costPerDraw = $cost / $count;
            
            $values = [];
            $params = [];
            foreach ($results as $prize) {
                $values[] = "(?, ?, ?, ?, ?, ?, 'unbound')";
                $params = array_merge($params, [
                    $userId,
                    $prize['id'] ?? null,
                    $prize['name'],
                    $prize['value'],
                    $drawType,
                    $costPerDraw
                ]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO draws (user_id, prize_id, prize_name, prize_value, draw_type, cost, coin_type)
                VALUES " . implode(', ', $values)
            );
            $stmt->execute($params);
        }
        
        // 记录抽奖历史
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, 'lucky_drop', ?, ?, ?)");
        $stmt->execute([$userId, $cost, $totalValue, json_encode($results, JSON_UNESCAPED_UNICODE)]);
        
        // 检查是否抽中传说物品，如果是则记录到限时掉落中奖列表
        define('INCLUDED_FROM_PRIZES', true);
        require_once 'limited-drop.php';
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn() ?: '未知用户';
        
        // 处理页面名称格式（去掉.html后缀）
        $pageName = str_replace('.html', '', $page);
        
        foreach ($results as $prize) {
            if (isset($prize['rarity']) && $prize['rarity'] === 'legendary') {
                recordWinner($pageName, $userId, $username, $prize['name'], $prize['value']);
            }
        }
        
        // 提交事务
        $pdo->commit();
        
        // ✅ 优化：释放分布式锁
        if ($drawCache->isEnabled()) {
            $drawCache->releaseLock($userId);
            // 刷新用户金币缓存
            $drawCache->refreshUserGold($userId);
        }
        
        return [
            'success' => true,
            'results' => $results,
            'total_value' => $totalValue,
            'cost' => $cost,
            'message' => '抽奖成功！'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        // ✅ 优化：异常时也要释放锁
        if (isset($drawCache) && $drawCache->isEnabled()) {
            $drawCache->releaseLock($userId);
        }
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function selectPrizeByProbability($prizes) {
    // 计算总概率
    $totalProbability = 0;
    foreach ($prizes as $prize) {
        $totalProbability += floatval($prize['probability']);
    }
    
    // 生成随机数 (0到总概率之间)
    $random = mt_rand(0, $totalProbability * 100) / 100;
    
    // 根据概率选择奖品
    $accumulator = 0;
    foreach ($prizes as $prize) {
        $accumulator += floatval($prize['probability']);
        if ($random <= $accumulator) {
            return $prize;
        }
    }
    
    // 备用返回最后一个奖品
    return end($prizes);
}

function getLotteryHistory($userId, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM lottery_records WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析结果JSON
        foreach ($records as &$record) {
            $record['result'] = json_decode($record['result'], true);
        }
        
        return [
            'success' => true,
            'records' => $records
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取历史记录失败: ' . $e->getMessage()
        ];
    }
}

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 调试日志
error_log("draws.php - Method: $method, Input: " . json_encode($input));

switch ($method) {
    case 'POST':
        error_log("draws.php - POST request, action: " . ($input['action'] ?? 'NOT SET'));
        if (isset($input['action'])) {
            error_log("draws.php - Action is set: " . $input['action']);
            switch ($input['action']) {
                case 'draw':
                    error_log("draws.php - Entering draw case");
                    $userId = $input['user_id'] ?? null;
                    $count = $input['count'] ?? 1;
                    $page = $input['page'] ?? 'lucky1.html';
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    if ($count < 1 || $count > 10) {
                        echo json_encode(['success' => false, 'message' => '抽奖次数必须在1-10之间']);
                        break;
                    }
                    
                    error_log("draws.php - Calling performLuckyDraw");
                    echo json_encode(performLuckyDraw($userId, $count, $page));
                    break;
                    
                case 'history':
                    $userId = $input['user_id'] ?? null;
                    $limit = $input['limit'] ?? 10;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    echo json_encode(getLotteryHistory($userId, $limit));
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => '未知操作']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '缺少操作参数']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
}
?>
