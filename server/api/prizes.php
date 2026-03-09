<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/coin-helper.php';
require_once '../config/prize-helper.php';

$database = new Database();
$pdo = $database->getConnection();

function getPrizes($luckyPage = null) {
    global $pdo;
    
    try {
        if ($luckyPage) {
            // 获取指定Lucky页面的奖品
            $luckyPage = str_replace('.html', '', $luckyPage);
            $prizes = getPrizesByLuckyPage($pdo, $luckyPage, true);
        } else {
            // 获取所有启用的奖品
            $stmt = $pdo->prepare("SELECT * FROM prizes WHERE active = 1 ORDER BY probability ASC");
            $stmt->execute();
            $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'success' => true,
            'prizes' => $prizes,
            'lucky_page' => $luckyPage
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取奖品失败: ' . $e->getMessage()
        ];
    }
}

function drawPrizes($gameType, $count, $userId, $page = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 检查用户非绑定金币余额
        $coins = getUserCoins($pdo, $userId);
        if (!$coins) {
            throw new Exception('获取用户余额失败');
        }
        
        // 获取用户信息
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
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
        $stmt->execute([$page ?: 'lucky1.html', $priceType]);
        $priceValue = $stmt->fetchColumn();
        
        if ($priceValue === false) {
            // 如果没有找到价格配置，使用默认价格
            $defaultPrices = ['single' => 10, 'triple' => 30, 'quintuple' => 50];
            $priceValue = $defaultPrices[$priceType] ?? 10;
        }
        
        // 如果不是标准的1、3、5连抽，按单价计算
        if (!in_array($count, [1, 3, 5])) {
            $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = 'single'");
            $stmt->execute([$page ?: 'lucky1.html']);
            $singlePrice = $stmt->fetchColumn() ?: 10;
            $cost = $count * $singlePrice;
        } else {
            $cost = $priceValue;
        }
        
        if ($user['unbound_coins'] < $cost) {
            throw new Exception('余额不足');
        }
        
        // 获取Lucky页面标识
        $luckyPage = $page ? str_replace('.html', '', $page) : 'lucky1';
        
        // 使用统一的prize表，通过prize_lucky_pages关联获取该页面的奖品
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
        $availablePrizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($availablePrizes)) {
            throw new Exception('暂无可抽取的奖品');
        }
        
        $results = [];
        $totalValue = 0;
        
        // 执行抽奖 - 每次抽奖后立即更新数量和概率
        for ($i = 0; $i < $count; $i++) {
            // 重新获取当前可用奖品列表（因为可能有概率变化）
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
            $currentPrizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($currentPrizes)) {
                throw new Exception('抽奖过程中奖品已耗尽');
            }
            
            // 进行抽奖
            $prize = selectPrizeByProbability($currentPrizes);
            $results[] = $prize;
            $totalValue += $prize['value'];
            
            // 如果是传说物品且有数量限制，扣减页面特定数量
            if ($prize['rarity'] === 'legendary' && isset($prize['quantity']) && $prize['quantity'] !== null) {
                $newQuantity = $prize['quantity'] - 1;
                
                // 更新页面特定数量（不影响全局数量和其他页面）
                $stmt = $pdo->prepare("
                    UPDATE prize_lucky_pages 
                    SET page_quantity = ? 
                    WHERE prize_id = ? AND lucky_page = ?
                ");
                $stmt->execute([$newQuantity, $prize['id'], $luckyPage]);
                
                // 如果页面数量变为0，将该页面的概率设为0（只影响当前页面）
                if ($newQuantity <= 0) {
                    $stmt = $pdo->prepare("
                        UPDATE prize_lucky_pages 
                        SET page_probability = 0 
                        WHERE prize_id = ? AND lucky_page = ?
                    ");
                    $stmt->execute([$prize['id'], $luckyPage]);
                }
            }
            
            // 记录抽奖日志
            $stmt = $pdo->prepare("
                INSERT INTO prize_draw_log (user_id, prize_table, prize_id, prize_name, rarity, original_quantity, remaining_quantity) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, 
                'prizes (unified)', 
                $prize['id'], 
                $prize['name'], 
                $prize['rarity'],
                $prize['quantity'] ?? null,
                isset($prize['quantity']) ? ($prize['quantity'] - 1) : null
            ]);
        }
        
        // 直接在当前事务中扣除非绑定金币
        $stmt = $pdo->prepare("UPDATE users SET unbound_coins = unbound_coins - ?, balance = balance - ? WHERE id = ? AND unbound_coins >= ?");
        $stmt->execute([$cost, $cost, $userId, $cost]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('扣除金币失败');
        }
        
        // 记录金币变动日志
        $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userAfter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pageInfo = $page ? " - {$page}" : '';
        
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
            "抽奖消费({$gameType}{$pageInfo})x{$count}"
        ]);
        
        // 记录抽奖结果
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $gameType, $cost, $totalValue, json_encode($results)]);
        
        // 将抽到的物品添加到用户仓库
        $stmt = $pdo->prepare("INSERT INTO user_items (user_id, prize_id, name, icon, image_url, value, rarity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($results as $prize) {
            $prizeIdForStorage = isset($prize['id']) && $prize['id'] !== null ? $prize['id'] : null;
            
            $stmt->execute([
                $userId, 
                $prizeIdForStorage,
                $prize['name'],
                $prize['icon'] ?? '🎁',
                $prize['image_url'] ?? '',
                $prize['value'] ?? 0,
                $prize['rarity'] ?? 'common'
            ]);
        }
        
        // 检查是否抽中传说物品，如果是则记录到限时掉落中奖列表
        if ($page) {
            // 设置标志，防止 limited-drop.php 执行请求处理逻辑
            define('INCLUDED_FROM_PRIZES', true);
            require_once 'limited-drop.php';
            
            // 获取用户名
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $username = $stmt->fetchColumn() ?: '未知用户';
            
            // 处理页面名称格式（去掉.html后缀）
            $pageName = str_replace('.html', '', $page);
            
            // 记录所有传说物品的中奖信息
            foreach ($results as $prize) {
                if (isset($prize['rarity']) && $prize['rarity'] === 'legendary') {
                    recordWinner($pageName, $userId, $username, $prize['name'], $prize['value']);
                }
            }
        }
        
        // 提交事务
        $pdo->commit();
        
        return [
            'success' => true,
            'results' => $results,
            'prizes' => $results, // 兼容前端
            'total_value' => $totalValue,
            'cost' => $cost,
            'lucky_page' => $luckyPage
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function selectPrizeByProbability($prizes) {
    $totalProbability = array_sum(array_column($prizes, 'probability'));
    
    // 生成0到总概率之间的随机数
    $random = (mt_rand(0, $totalProbability * 10000) / 10000);
    
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

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $luckyPage = $_GET['page'] ?? $_GET['lucky_page'] ?? null;
        echo json_encode(getPrizes($luckyPage));
        break;
        
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'draw':
                    $userId = $input['user_id'] ?? null;
                    $gameType = $input['game_type'] ?? 'lucky_drop';
                    $count = $input['count'] ?? 1;
                    $page = $input['page'] ?? null;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    echo json_encode(drawPrizes($gameType, $count, $userId, $page));
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
