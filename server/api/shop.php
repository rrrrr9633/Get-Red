<?php
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
?>
