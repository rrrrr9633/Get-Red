<?php
// 设置CORS头
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// 路由处理
switch($method) {
    case 'GET':
        handleGet($db, $action);
        break;
    case 'POST':
        handlePost($db, $action);
        break;
    case 'PUT':
        handlePut($db, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '方法不被允许']);
        break;
}

// 处理GET请求
function handleGet($db, $action) {
    switch($action) {
        case 'config':
            getWithdrawalConfig($db);
            break;
        case 'user_requests':
            getUserWithdrawalRequests($db);
            break;
        case 'user_history':
            getUserWithdrawalHistory($db);
            break;
        case 'admin_pending':
            getAdminPendingRequests($db);
            break;
        case 'admin_user_withdrawals':
            getAdminUserWithdrawals($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

// 处理POST请求
function handlePost($db, $action) {
    switch($action) {
        case 'submit':
            submitWithdrawalRequest($db);
            break;
        case 'process':
            processWithdrawalRequest($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

// 处理PUT请求
function handlePut($db, $action) {
    switch($action) {
        case 'update_config':
            updateWithdrawalConfig($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的操作']);
            break;
    }
}

// 获取提现配置
function getWithdrawalConfig($db) {
    try {
        // 获取全局配置
        $stmt = $db->prepare("SELECT config_key, config_value FROM withdrawal_config");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [
            'exchange_rate' => 10000, // 固定汇率
            'min_amount' => 100,
            'max_amount' => 10000,
            'is_enabled' => 1
        ];
        
        foreach ($configs as $item) {
            $config[$item['config_key']] = $item['config_value'];
        }
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取配置失败: ' . $e->getMessage()]);
    }
}

// 提交提现申请
function submitWithdrawalRequest($db) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['amount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $amount = floatval($input['amount']);
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 获取配置
        $stmt = $db->prepare("SELECT config_key, config_value FROM withdrawal_config");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $exchangeRate = floatval($configs['exchange_rate'] ?? 10000);
        $minAmount = floatval($configs['min_amount'] ?? 100);
        $maxAmount = floatval($configs['max_amount'] ?? 10000);
        $isEnabled = intval($configs['is_enabled'] ?? 1);
        
        if (!$isEnabled) {
            throw new Exception('提现功能暂时关闭');
        }
        
        // 验证金额范围
        if ($amount < $minAmount) {
            throw new Exception('提现金额不能少于 ' . $minAmount . ' 金币');
        }
        
        if ($amount > $maxAmount) {
            throw new Exception('提现金额不能超过 ' . $maxAmount . ' 金币');
        }
        
        // 检查用户余额
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['balance'] < $amount) {
            throw new Exception('余额不足');
        }
        
        // 计算哈夫币（固定汇率1:10000）
        $buffCoins = $amount * $exchangeRate;
        
        // 扣除用户余额
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        // 创建提现申请
        $stmt = $db->prepare("
            INSERT INTO withdrawal_requests (user_id, amount, buff_coins, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $amount, $buffCoins]);
        
        // 记录交易
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, amount, description, type)
            VALUES (?, ?, ?, 'expense')
        ");
        $stmt->execute([$userId, -$amount, "跑刀提现申请"]);
        
        // 查找负责该用户的客服并发送通知
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            SELECT service_user_id FROM service_user_assignments 
            WHERE regular_user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment && $assignment['service_user_id']) {
            // 查找或创建聊天会话
            $stmt = $db->prepare("
                SELECT session_id FROM chat_sessions 
                WHERE user_id = ? AND service_user_id = ? AND status != 'closed'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId, $assignment['service_user_id']]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                // 创建新会话
                $sessionId = 'session_' . $userId . '_' . $assignment['service_user_id'] . '_' . time();
                $stmt = $db->prepare("
                    INSERT INTO chat_sessions (user_id, service_user_id, session_id, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$userId, $assignment['service_user_id'], $sessionId]);
            } else {
                $sessionId = $session['session_id'];
            }
            
            // 发送系统消息给客服
            $message = "【系统通知】用户 {$userInfo['username']} 提交了跑刀提现申请，金额：{$amount} 金币（{$buffCoins} 哈夫币），请及时处理。";
            $stmt = $db->prepare("
                INSERT INTO chat_messages (session_id, sender_id, sender_type, message, message_type)
                VALUES (?, ?, 'user', ?, 'text')
            ");
            $stmt->execute([$sessionId, $userId, $message]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '提现申请已提交',
            'buff_coins' => $buffCoins
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取用户的提现申请
function getUserWithdrawalRequests($db) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM withdrawal_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'requests' => $requests
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取申请失败: ' . $e->getMessage()]);
    }
}

// 获取用户的提现历史
function getUserWithdrawalHistory($db) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM withdrawal_history 
            WHERE user_id = ? 
            ORDER BY processed_at DESC
            LIMIT 50
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取历史失败: ' . $e->getMessage()]);
    }
}

// 管理员：获取待处理的提现申请
function getAdminPendingRequests($db) {
    // 验证管理员权限（超级管理员或客服）
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT wr.*, u.username, u.nickname 
            FROM withdrawal_requests wr
            JOIN users u ON wr.user_id = u.id
            WHERE wr.status IN ('pending', 'processing')
            ORDER BY wr.created_at ASC
        ");
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'requests' => $requests
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取申请失败: ' . $e->getMessage()]);
    }
}

// 管理员：获取指定用户的提现信息
function getAdminUserWithdrawals($db) {
    // 验证管理员权限（超级管理员或客服）
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    $targetUserId = $_GET['user_id'] ?? null;
    if (!$targetUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少用户ID']);
        return;
    }
    
    try {
        // 首先验证用户是否存在（无论在线还是离线）
        $stmt = $db->prepare("SELECT id, username, nickname, is_online FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode([
                'success' => false,
                'error' => '用户不存在',
                'pending' => [],
                'history' => []
            ]);
            return;
        }
        
        // 获取待处理的申请（无论用户在线还是离线）
        $stmt = $db->prepare("
            SELECT * FROM withdrawal_requests 
            WHERE user_id = ? AND status IN ('pending', 'processing')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$targetUserId]);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取历史记录（无论用户在线还是离线）
        $stmt = $db->prepare("
            SELECT * FROM withdrawal_history 
            WHERE user_id = ? 
            ORDER BY processed_at DESC
            LIMIT 20
        ");
        $stmt->execute([$targetUserId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'pending' => $pending,
            'history' => $history,
            'debug' => [
                'user_id' => $targetUserId,
                'user_exists' => true,
                'is_online' => $user['is_online'],
                'pending_count' => count($pending),
                'history_count' => count($history)
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '获取信息失败: ' . $e->getMessage()]);
    }
}

// 管理员：处理提现申请
function processWithdrawalRequest($db) {
    // 验证管理员权限（超级管理员或客服）
    if (!isset($_SESSION['super_admin_verified']) && !isset($_SESSION['service_verified'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或无权限']);
        return;
    }
    
    // 获取管理员ID（用于记录处理人）
    $adminId = null;
    if (isset($_SESSION['super_admin_id'])) {
        $adminId = $_SESSION['super_admin_id'];
    } elseif (isset($_SESSION['service_user_id'])) {
        $adminId = $_SESSION['service_user_id'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['request_id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        return;
    }
    
    $requestId = $input['request_id'];
    $action = $input['action']; // 'approve' 或 'reject'
    $rejectReason = $input['reject_reason'] ?? null;
    
    try {
        $db->beginTransaction();
        
        // 获取申请信息
        $stmt = $db->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception('申请不存在');
        }
        
        if ($request['status'] !== 'pending' && $request['status'] !== 'processing') {
            throw new Exception('该申请已被处理');
        }
        
        if ($action === 'approve') {
            // 批准提现
            $status = 'completed';
            
            // 移动到历史记录
            $stmt = $db->prepare("
                INSERT INTO withdrawal_history 
                (user_id, amount, buff_coins, status, created_at, processed_at, processed_by)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $request['user_id'],
                $request['amount'],
                $request['buff_coins'],
                $status,
                $request['created_at'],
                $adminId
            ]);
            
        } else if ($action === 'reject') {
            // 拒绝提现，退还金币
            $status = 'rejected';
            
            // 退还用户余额
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$request['amount'], $request['user_id']]);
            
            // 记录交易
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, amount, description, type)
                VALUES (?, ?, ?, 'income')
            ");
            $stmt->execute([
                $request['user_id'],
                $request['amount'],
                "跑刀提现被拒绝，退还金币"
            ]);
            
            // 移动到历史记录
            $stmt = $db->prepare("
                INSERT INTO withdrawal_history 
                (user_id, amount, buff_coins, status, created_at, processed_at, processed_by, reject_reason)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $request['user_id'],
                $request['amount'],
                $request['buff_coins'],
                $status,
                $request['created_at'],
                $adminId,
                $rejectReason
            ]);
        } else {
            throw new Exception('无效的操作');
        }
        
        // 删除原申请
        $stmt = $db->prepare("DELETE FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $action === 'approve' ? '提现已批准' : '提现已拒绝'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 更新提现配置
function updateWithdrawalConfig($db) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // 更新配置（汇率固定为10000，不允许修改）
        if (isset($input['min_amount'])) {
            $stmt = $db->prepare("
                UPDATE withdrawal_config 
                SET config_value = ?
                WHERE config_key = 'min_amount'
            ");
            $stmt->execute([$input['min_amount']]);
        }
        
        if (isset($input['max_amount'])) {
            $stmt = $db->prepare("
                UPDATE withdrawal_config 
                SET config_value = ?
                WHERE config_key = 'max_amount'
            ");
            $stmt->execute([$input['max_amount']]);
        }
        
        if (isset($input['is_enabled'])) {
            $stmt = $db->prepare("
                UPDATE withdrawal_config 
                SET config_value = ?
                WHERE config_key = 'is_enabled'
            ");
            $stmt->execute([$input['is_enabled']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '配置已更新'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '更新失败: ' . $e->getMessage()]);
    }
}
?>
