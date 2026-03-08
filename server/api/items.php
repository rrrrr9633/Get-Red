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

$database = new Database();
$pdo = $database->getConnection();

// 获取用户物品列表
function getUserItems($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_items WHERE user_id = ? AND decomposed = 0 ORDER BY obtained_at DESC");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'items' => $items
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取用户物品失败: ' . $e->getMessage()
        ];
    }
}

// 分解物品
function decomposeItems($userId, $itemIds, $totalValue) {
    global $pdo;
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 验证物品是否属于该用户且未分解
        $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, name, value, rarity FROM user_items WHERE id IN ($placeholders) AND user_id = ? AND decomposed = 0");
        $stmt->execute(array_merge($itemIds, [$userId]));
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($items) !== count($itemIds)) {
            throw new Exception('部分物品不存在或已分解');
        }
        
        // 验证总价值
        $calculatedValue = array_sum(array_column($items, 'value'));
        if (abs($calculatedValue - $totalValue) > 0.01) {
            throw new Exception('物品价值计算错误');
        }
        
        // 按稀有度分类物品
        $legendaryValue = 0;  // 传说级 → 非绑定金币
        $normalValue = 0;     // 普通级 → 绑定金币
        
        foreach ($items as $item) {
            if ($item['rarity'] === 'legendary') {
                $legendaryValue += floatval($item['value']);
            } else {
                $normalValue += floatval($item['value']);
            }
        }
        
        // 标记物品为已分解
        $stmt = $pdo->prepare("UPDATE user_items SET decomposed = 1, decomposed_at = NOW() WHERE id IN ($placeholders) AND user_id = ?");
        $stmt->execute(array_merge($itemIds, [$userId]));
        
        // 根据稀有度增加对应类型的金币
        $itemNames = implode('、', array_column($items, 'name'));
        
        if ($legendaryValue > 0) {
            // 直接在当前事务中增加非绑定金币
            $stmt = $pdo->prepare("UPDATE users SET unbound_coins = unbound_coins + ?, balance = balance + ? WHERE id = ?");
            $stmt->execute([$legendaryValue, $legendaryValue, $userId]);
            
            // 记录日志
            $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userCoins = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                INSERT INTO coin_change_log 
                (user_id, change_type, coin_type, bound_change, unbound_change, 
                 bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
                 related_id, description)
                VALUES (?, 'decompose', 'unbound', 0, ?, ?, ?, ?, ?, NULL, ?)
            ");
            $stmt->execute([
                $userId, 
                $legendaryValue,
                $userCoins['bound_coins'],
                $userCoins['unbound_coins'] - $legendaryValue,
                $userCoins['bound_coins'],
                $userCoins['unbound_coins'],
                "分解传说级物品: {$itemNames}"
            ]);
        }
        
        if ($normalValue > 0) {
            // 直接在当前事务中增加绑定金币
            $stmt = $pdo->prepare("UPDATE users SET bound_coins = bound_coins + ?, balance = balance + ? WHERE id = ?");
            $stmt->execute([$normalValue, $normalValue, $userId]);
            
            // 记录日志
            $stmt = $pdo->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userCoins = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                INSERT INTO coin_change_log 
                (user_id, change_type, coin_type, bound_change, unbound_change, 
                 bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
                 related_id, description)
                VALUES (?, 'decompose', 'bound', ?, 0, ?, ?, ?, ?, NULL, ?)
            ");
            $stmt->execute([
                $userId, 
                $normalValue,
                $userCoins['bound_coins'] - $normalValue,
                $userCoins['unbound_coins'],
                $userCoins['bound_coins'],
                $userCoins['unbound_coins'],
                "分解普通物品: {$itemNames}"
            ]);
        }
        
        // 提交事务
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => '分解成功',
            'decomposed_value' => $totalValue,
            'legendary_coins' => $legendaryValue,
            'normal_coins' => $normalValue,
            'coin_breakdown' => [
                'unbound' => $legendaryValue,
                'bound' => $normalValue
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['user_id'])) {
            echo json_encode(getUserItems($_GET['user_id']));
        } else {
            echo json_encode(['success' => false, 'message' => '缺少用户ID']);
        }
        break;
        
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'decompose':
                    $userId = $input['user_id'] ?? null;
                    $itemIds = $input['item_ids'] ?? [];
                    $totalValue = $input['total_value'] ?? 0;
                    
                    if (!$userId || empty($itemIds)) {
                        echo json_encode(['success' => false, 'message' => '参数不完整']);
                        break;
                    }
                    
                    echo json_encode(decomposeItems($userId, $itemIds, $totalValue));
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
