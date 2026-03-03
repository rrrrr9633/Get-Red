<?php
// 引入安全配置
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch($method) {
    case 'GET':
        handleGet($pdo, $action);
        break;
    case 'POST':
        handlePost($pdo, $action);
        break;
    case 'PUT':
        handlePut($pdo, $action);
        break;
    case 'DELETE':
        handleDelete($pdo, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '方法不被允许']);
        break;
}

function handleGet($pdo, $action) {
    switch($action) {
        case 'items':
            getShopItems($pdo);
            break;
        case 'user_purchases':
            getUserPurchases($pdo);
            break;
        case 'user_orders':
            getUserOrders($pdo);
            break;
        case 'admin_purchases':
            getAdminPurchases($pdo);
            break;
        case 'admin_items':
            getAdminItems($pdo);
            break;
        case 'my_legendary_items':
            getMyLegendaryItems($pdo);
            break;
        case 'legendary_exchange_items':
            getLegendaryExchangeItems($pdo);
            break;
        case 'legendary_exchange_config':
            getLegendaryExchangeConfig($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

function handlePost($pdo, $action) {
    switch($action) {
        case 'purchase':
            purchaseItem($pdo);
            break;
        case 'add_item':
            addShopItem($pdo);
            break;
        case 'process_purchase':
            processPurchase($pdo);
            break;
        case 'legendary_exchange':
            legendaryExchange($pdo);
            break;
        case 'save_legendary_exchange_config':
            saveLegendaryExchangeConfig($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

function handlePut($pdo, $action) {
    switch($action) {
        case 'update_item':
            updateShopItem($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

function handleDelete($pdo, $action) {
    switch($action) {
        case 'delete_item':
            deleteShopItem($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

function getShopItems($pdo) {
    try {
        $itemType = $_GET['type'] ?? null;
        
        if ($itemType && in_array($itemType, ['skin', 'escort'])) {
            $stmt = $pdo->prepare("
                SELECT * FROM shop_items 
                WHERE item_type = ? AND is_active = 1 
                ORDER BY sort_order ASC, created_at DESC
            ");
            $stmt->execute([$itemType]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM shop_items 
                WHERE is_active = 1 
                ORDER BY item_type, sort_order ASC, created_at DESC
            ");
            $stmt->execute();
        }
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取商品失败: ' . $e->getMessage()]);
    }
}

function purchaseItem($pdo) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['item_id']) || !isset($input['player_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $itemId = $input['item_id'];
    $playerId = $input['player_id'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM shop_items WHERE id = ? AND is_active = 1");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('商品不存在或已下架');
        }
        
        if ($item['stock'] != -1 && $item['stock'] <= 0) {
            throw new Exception('商品库存不足');
        }
        
        $stmt = $pdo->prepare("SELECT balance, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['balance'] < $item['price']) {
            throw new Exception('余额不足');
        }
        
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$item['price'], $userId]);
        
        if ($item['stock'] != -1) {
            $stmt = $pdo->prepare("UPDATE shop_items SET stock = stock - 1 WHERE id = ?");
            $stmt->execute([$itemId]);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO shop_purchase_history 
            (user_id, shop_item_id, item_name, item_type, price, player_id, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $userId,
            $itemId,
            $item['name'],
            $item['item_type'],
            $item['price'],
            $playerId
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, amount, description, type)
            VALUES (?, ?, ?, 'expense')
        ");
        $stmt->execute([
            $userId,
            -$item['price'],
            "购买商城物品: {$item['name']}"
        ]);
        
        // 查找负责该用户的客服
        $stmt = $pdo->prepare("
            SELECT service_user_id FROM service_user_assignments 
            WHERE regular_user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment && $assignment['service_user_id']) {
            // 查找或创建聊天会话
            $stmt = $pdo->prepare("
                SELECT session_id FROM chat_sessions 
                WHERE user_id = ? AND service_user_id = ? AND status != 'closed'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId, $assignment['service_user_id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                // 创建新会话
                $sessionId = 'session_' . $userId . '_' . $assignment['service_user_id'] . '_' . time();
                $stmt = $pdo->prepare("
                    INSERT INTO chat_sessions (user_id, service_user_id, session_id, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$userId, $assignment['service_user_id'], $sessionId]);
            } else {
                $sessionId = $session['session_id'];
            }
            
            // 发送系统消息给客服
            $message = "【系统通知】用户 {$user['username']} 购买了商城物品：{$item['name']}（{$item['item_type']}），价格：{$item['price']} 金币，玩家ID：{$playerId}，请及时处理订单。";
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (session_id, sender_id, sender_type, message, message_type)
                VALUES (?, ?, 'user', ?, 'text')
            ");
            $stmt->execute([$sessionId, $userId, $message]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '购买成功，请等待客服处理'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserPurchases($pdo) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shop_purchase_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'purchases' => $purchases
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取购买记录失败: ' . $e->getMessage()]);
    }
}

function getUserOrders($pdo) {
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少用户ID']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shop_purchase_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取订单失败: ' . $e->getMessage()]);
    }
}

function getAdminPurchases($pdo) {
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    try {
        $status = $_GET['status'] ?? null;
        
        if ($status && in_array($status, ['pending', 'processing', 'completed', 'cancelled'])) {
            $stmt = $pdo->prepare("
                SELECT sph.*, u.username, u.nickname 
                FROM shop_purchase_history sph
                JOIN users u ON sph.user_id = u.id
                WHERE sph.status = ?
                ORDER BY sph.created_at DESC
            ");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->prepare("
                SELECT sph.*, u.username, u.nickname 
                FROM shop_purchase_history sph
                JOIN users u ON sph.user_id = u.id
                ORDER BY sph.created_at DESC
                LIMIT 100
            ");
            $stmt->execute();
        }
        
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'purchases' => $purchases
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取购买记录失败: ' . $e->getMessage()]);
    }
}

function processPurchase($pdo) {
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $adminId = $_SESSION['super_admin_id'] ?? $_SESSION['service_user_id'] ?? null;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['purchase_id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    $purchaseId = $input['purchase_id'];
    $action = $input['action'];
    $notes = $input['notes'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM shop_purchase_history WHERE id = ?");
        $stmt->execute([$purchaseId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            throw new Exception('订单不存在');
        }
        
        if ($purchase['status'] === 'completed' || $purchase['status'] === 'cancelled') {
            throw new Exception('订单已处理');
        }
        
        if ($action === 'complete') {
            $stmt = $pdo->prepare("
                UPDATE shop_purchase_history 
                SET status = 'completed', processed_at = NOW(), processed_by = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$adminId, $notes, $purchaseId]);
            
            $message = '订单已完成';
            
        } else if ($action === 'cancel') {
            $stmt = $pdo->prepare("
                UPDATE shop_purchase_history 
                SET status = 'cancelled', processed_at = NOW(), processed_by = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$adminId, $notes, $purchaseId]);
            
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$purchase['price'], $purchase['user_id']]);
            
            $stmt = $pdo->prepare("
                UPDATE shop_items 
                SET stock = stock + 1 
                WHERE id = ? AND stock != -1
            ");
            $stmt->execute([$purchase['shop_item_id']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, amount, description, type)
                VALUES (?, ?, ?, 'income')
            ");
            $stmt->execute([
                $purchase['user_id'],
                $purchase['price'],
                "订单取消退款: {$purchase['item_name']}"
            ]);
            
            $message = '订单已取消并退款';
            
        } else {
            throw new Exception('无效的操作');
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAdminItems($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shop_items 
            ORDER BY item_type, sort_order ASC, created_at DESC
        ");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取商品失败: ' . $e->getMessage()]);
    }
}

function addShopItem($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || !isset($input['price']) || !isset($input['item_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO shop_items 
            (name, icon, image_url, description, price, item_type, rarity, stock, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['name'],
            $input['icon'] ?? '🎁',
            $input['image_url'] ?? '',
            $input['description'] ?? '',
            $input['price'],
            $input['item_type'],
            $input['rarity'] ?? 'common',
            $input['stock'] ?? -1,
            $input['is_active'] ?? 1,
            $input['sort_order'] ?? 0
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '商品添加成功',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '添加商品失败: ' . $e->getMessage()]);
    }
}

function updateShopItem($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少商品ID']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE shop_items 
            SET name = ?, icon = ?, image_url = ?, description = ?, 
                price = ?, item_type = ?, rarity = ?, stock = ?, 
                is_active = ?, sort_order = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $input['name'],
            $input['icon'] ?? '🎁',
            $input['image_url'] ?? '',
            $input['description'] ?? '',
            $input['price'],
            $input['item_type'],
            $input['rarity'] ?? 'common',
            $input['stock'] ?? -1,
            $input['is_active'] ?? 1,
            $input['sort_order'] ?? 0,
            $input['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '商品更新成功'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '更新商品失败: ' . $e->getMessage()]);
    }
}

function deleteShopItem($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $itemId = $_GET['id'] ?? null;
    
    if (!$itemId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少商品ID']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE shop_items SET is_active = 0 WHERE id = ?");
        $stmt->execute([$itemId]);
        
        echo json_encode([
            'success' => true,
            'message' => '商品已下架'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '删除商品失败: ' . $e->getMessage()]);
    }
}

// ========== 传说级兑换相关功能 ==========

function getMyLegendaryItems($pdo) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM user_items 
            WHERE user_id = ? AND rarity = 'legendary' AND decomposed = 0
            ORDER BY obtained_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取传说级物品失败: ' . $e->getMessage()]);
    }
}

function getLegendaryExchangeItems($pdo) {
    try {
        // 获取所有启用的传说级兑换配置
        $stmt = $pdo->prepare("
            SELECT lec.*, si.name, si.icon, si.image_url, si.description, si.item_type, si.rarity
            FROM legendary_exchange_config lec
            JOIN shop_items si ON lec.shop_item_id = si.id
            WHERE lec.is_active = 1 AND si.is_active = 1
            ORDER BY lec.sort_order ASC, lec.created_at DESC
        ");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析每个配置的所需物品
        $items = [];
        foreach ($configs as $config) {
            $requiredItems = json_decode($config['required_items'], true);
            
            // 获取每个所需物品的详细信息
            $requiredItemsDetails = [];
            foreach ($requiredItems as $reqItem) {
                $stmt = $pdo->prepare("SELECT name, icon, value FROM prizes WHERE id = ?");
                $stmt->execute([$reqItem['prize_id']]);
                $prizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($prizeInfo) {
                    $requiredItemsDetails[] = [
                        'prize_id' => $reqItem['prize_id'],
                        'name' => $prizeInfo['name'],
                        'icon' => $prizeInfo['icon'],
                        'value' => $prizeInfo['value'],
                        'quantity' => $reqItem['quantity']
                    ];
                }
            }
            
            $items[] = [
                'id' => $config['id'],
                'shop_item_id' => $config['shop_item_id'],
                'name' => $config['name'],
                'icon' => $config['icon'],
                'image_url' => $config['image_url'],
                'description' => $config['description'],
                'item_type' => $config['item_type'],
                'rarity' => $config['rarity'],
                'required_items' => $requiredItemsDetails
            ];
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取兑换商品失败: ' . $e->getMessage()]);
    }
}

function legendaryExchange($pdo) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['item_id']) || !isset($input['player_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $configId = $input['item_id'];
    $playerId = $input['player_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 获取兑换配置
        $stmt = $pdo->prepare("
            SELECT lec.*, si.name, si.item_type
            FROM legendary_exchange_config lec
            JOIN shop_items si ON lec.shop_item_id = si.id
            WHERE lec.id = ? AND lec.is_active = 1 AND si.is_active = 1
        ");
        $stmt->execute([$configId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception('兑换配置不存在或已禁用');
        }
        
        $requiredItems = json_decode($config['required_items'], true);
        
        // 获取用户的传说级物品
        $stmt = $pdo->prepare("
            SELECT * FROM user_items 
            WHERE user_id = ? AND rarity = 'legendary' AND decomposed = 0
        ");
        $stmt->execute([$userId]);
        $userItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 统计用户物品数量
        $userItemCounts = [];
        $userItemIds = [];
        foreach ($userItems as $item) {
            $key = $item['prize_id'];
            if (!isset($userItemCounts[$key])) {
                $userItemCounts[$key] = 0;
                $userItemIds[$key] = [];
            }
            $userItemCounts[$key]++;
            $userItemIds[$key][] = $item['id'];
        }
        
        // 检查是否满足兑换条件
        $itemsToConsume = [];
        foreach ($requiredItems as $reqItem) {
            $prizeId = $reqItem['prize_id'];
            $requiredQty = $reqItem['quantity'];
            $userQty = $userItemCounts[$prizeId] ?? 0;
            
            if ($userQty < $requiredQty) {
                throw new Exception('传说级物品数量不足');
            }
            
            // 记录要消耗的物品ID
            $itemsToConsume = array_merge($itemsToConsume, array_slice($userItemIds[$prizeId], 0, $requiredQty));
        }
        
        // 标记物品为已分解（消耗）
        foreach ($itemsToConsume as $itemId) {
            $stmt = $pdo->prepare("
                UPDATE user_items 
                SET decomposed = 1, decomposed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$itemId]);
        }
        
        // 获取用户信息
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 创建购买记录
        $usedItemsJson = json_encode($requiredItems);
        $stmt = $pdo->prepare("
            INSERT INTO shop_purchase_history 
            (user_id, shop_item_id, item_name, item_type, price, purchase_type, used_items, player_id, status)
            VALUES (?, ?, ?, ?, 0, 'legendary', ?, ?, 'pending')
        ");
        $stmt->execute([
            $userId,
            $config['shop_item_id'],
            $config['name'],
            $config['item_type'],
            $usedItemsJson,
            $playerId
        ]);
        
        // 查找负责该用户的客服并发送通知
        $stmt = $pdo->prepare("
            SELECT service_user_id FROM service_user_assignments 
            WHERE regular_user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment && $assignment['service_user_id']) {
            // 查找或创建聊天会话
            $stmt = $pdo->prepare("
                SELECT session_id FROM chat_sessions 
                WHERE user_id = ? AND service_user_id = ? AND status != 'closed'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId, $assignment['service_user_id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                $sessionId = 'session_' . $userId . '_' . $assignment['service_user_id'] . '_' . time();
                $stmt = $pdo->prepare("
                    INSERT INTO chat_sessions (user_id, service_user_id, session_id, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$userId, $assignment['service_user_id'], $sessionId]);
            } else {
                $sessionId = $session['session_id'];
            }
            
            // 构建消息
            $itemsList = '';
            foreach ($requiredItems as $reqItem) {
                $stmt = $pdo->prepare("SELECT name FROM prizes WHERE id = ?");
                $stmt->execute([$reqItem['prize_id']]);
                $prizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $itemsList .= "\n- {$prizeInfo['name']} x{$reqItem['quantity']}";
            }
            
            $message = "【传说级兑换通知】用户 {$user['username']} 使用传说级物品兑换了：{$config['name']}（{$config['item_type']}）\n使用的物品：{$itemsList}\n玩家ID：{$playerId}\n请及时处理订单。";
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (session_id, sender_id, sender_type, message, message_type)
                VALUES (?, ?, 'user', ?, 'text')
            ");
            $stmt->execute([$sessionId, $userId, $message]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '兑换成功，请等待客服处理'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getLegendaryExchangeConfig($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    try {
        $shopItemId = $_GET['shop_item_id'] ?? null;
        
        if ($shopItemId) {
            $stmt = $pdo->prepare("
                SELECT * FROM legendary_exchange_config 
                WHERE shop_item_id = ?
            ");
            $stmt->execute([$shopItemId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config) {
                $config['required_items'] = json_decode($config['required_items'], true);
            }
            
            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT lec.*, si.name as shop_item_name
                FROM legendary_exchange_config lec
                JOIN shop_items si ON lec.shop_item_id = si.id
                ORDER BY lec.sort_order ASC, lec.created_at DESC
            ");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($configs as &$config) {
                $config['required_items'] = json_decode($config['required_items'], true);
            }
            
            echo json_encode([
                'success' => true,
                'configs' => $configs
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取配置失败: ' . $e->getMessage()]);
    }
}

function saveLegendaryExchangeConfig($pdo) {
    if (!isset($_SESSION['super_admin_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['shop_item_id']) || !isset($input['required_items'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    try {
        $shopItemId = $input['shop_item_id'];
        $requiredItems = $input['required_items'];
        $isActive = $input['is_active'] ?? 1;
        $sortOrder = $input['sort_order'] ?? 0;
        
        // 验证商品是否存在
        $stmt = $pdo->prepare("SELECT id FROM shop_items WHERE id = ?");
        $stmt->execute([$shopItemId]);
        if (!$stmt->fetch()) {
            throw new Exception('商品不存在');
        }
        
        // 验证所需物品
        if (empty($requiredItems)) {
            throw new Exception('至少需要选择一个传说级物品');
        }
        
        $requiredItemsJson = json_encode($requiredItems);
        
        // 检查是否已存在配置
        $stmt = $pdo->prepare("SELECT id FROM legendary_exchange_config WHERE shop_item_id = ?");
        $stmt->execute([$shopItemId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 更新现有配置
            $stmt = $pdo->prepare("
                UPDATE legendary_exchange_config 
                SET required_items = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                WHERE shop_item_id = ?
            ");
            $stmt->execute([$requiredItemsJson, $isActive, $sortOrder, $shopItemId]);
        } else {
            // 创建新配置
            $stmt = $pdo->prepare("
                INSERT INTO legendary_exchange_config 
                (shop_item_id, required_items, is_active, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$shopItemId, $requiredItemsJson, $isActive, $sortOrder]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '传说级兑换配置保存成功'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
