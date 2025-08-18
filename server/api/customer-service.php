<?php
// 客服系统API
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 记录安全日志
function logSecurityEvent($db, $action, $status, $details = null, $reason = null) {
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // 获取用户类型
    $user_type = null;
    if ($user_id) {
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $user_type = $user['user_type'] ?? 'user';
    }
    
    $stmt = $db->prepare("INSERT INTO security_logs (user_id, username, user_type, ip_address, action, details, status, reason, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $user_type, $ip_address, $action, $details, $status, $reason, $user_agent]);
}

// 检查用户权限
function checkPermission($db, $required_types = ['service', 'admin', 'super_admin']) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    return $user && in_array($user['user_type'], $required_types);
}

switch($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'config':
                    getServiceConfig();
                    break;
                case 'sessions':
                    getChatSessions();
                    break;
                case 'messages':
                    getChatMessages();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
        
    case 'POST':
        if (isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'save_config':
                    saveServiceConfig();
                    break;
                case 'start_session':
                    startChatSession();
                    break;
                case 'send_message':
                    sendChatMessage();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
        
    case 'PUT':
        if (isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'close_session':
                    closeChatSession();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不被允许']);
        break;
}

// 获取客服配置
function getServiceConfig() {
    global $db;
    
    if (!checkPermission($db, ['admin', 'super_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM customer_service_config ORDER BY sort_order");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logSecurityEvent($db, 'get_service_config', 'success');
        echo json_encode(['success' => true, 'configs' => $configs]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'get_service_config', 'failed', null, $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取配置失败']);
    }
}

// 保存客服配置
function saveServiceConfig() {
    global $db, $input;
    
    if (!checkPermission($db, ['admin', 'super_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    if (!isset($input['service_type'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO customer_service_config (service_type, title, content, contact_info, qr_code_url, is_enabled) 
            VALUES (?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            title = VALUES(title), 
            content = VALUES(content), 
            contact_info = VALUES(contact_info), 
            qr_code_url = VALUES(qr_code_url), 
            is_enabled = VALUES(is_enabled)
        ");
        
        $stmt->execute([
            $input['service_type'],
            $input['title'],
            $input['content'],
            $input['contact_info'],
            $input['qr_code_url'],
            $input['is_enabled']
        ]);
        
        logSecurityEvent($db, 'save_service_config', 'success', json_encode($input));
        echo json_encode(['success' => true, 'message' => '配置保存成功']);
    } catch (Exception $e) {
        logSecurityEvent($db, 'save_service_config', 'failed', json_encode($input), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '保存配置失败']);
    }
}

// 获取聊天会话列表
function getChatSessions() {
    global $db;
    
    if (!checkPermission($db)) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    try {
        session_start();
        $user_id = $_SESSION['user_id'];
        
        // 获取用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user['user_type'] === 'service') {
            // 客服用户：获取分配给自己的会话
            $stmt = $db->prepare("
                SELECT cs.*, u.nickname as user_nickname, u.username as user_username
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.service_user_id = ? OR cs.service_user_id IS NULL
                ORDER BY cs.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id]);
        } else {
            // 管理员：获取所有会话
            $stmt = $db->prepare("
                SELECT cs.*, u.nickname as user_nickname, u.username as user_username,
                       su.nickname as service_nickname, su.username as service_username
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                LEFT JOIN users su ON cs.service_user_id = su.id
                ORDER BY cs.created_at DESC
                LIMIT 100
            ");
            $stmt->execute();
        }
        
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'get_chat_sessions', 'failed', null, $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取会话列表失败']);
    }
}

// 获取聊天消息
function getChatMessages() {
    global $db;
    
    if (!checkPermission($db)) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    if (!isset($_GET['session_id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少会话ID']);
        return;
    }
    
    try {
        $session_id = $_GET['session_id'];
        
        $stmt = $db->prepare("
            SELECT cm.*, u.nickname as sender_nickname, u.username as sender_username
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.session_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$session_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'get_chat_messages', 'failed', "session_id: $session_id", $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取消息失败']);
    }
}

// 开始聊天会话
function startChatSession() {
    global $db, $input;
    
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        $session_id = 'chat_' . $user_id . '_' . time() . '_' . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO chat_sessions (user_id, session_id, status) 
            VALUES (?, ?, 'waiting')
        ");
        $stmt->execute([$user_id, $session_id]);
        
        echo json_encode(['success' => true, 'session_id' => $session_id]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'start_chat_session', 'failed', null, $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '创建会话失败']);
    }
}

// 发送聊天消息
function sendChatMessage() {
    global $db, $input;
    
    if (!checkPermission($db, ['user', 'service', 'admin', 'super_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    if (!isset($input['session_id']) || !isset($input['message'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        session_start();
        $user_id = $_SESSION['user_id'];
        
        // 获取用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        $sender_type = in_array($user['user_type'], ['service', 'admin', 'super_admin']) ? 'service' : 'user';
        
        $stmt = $db->prepare("
            INSERT INTO chat_messages (session_id, sender_id, sender_type, message, message_type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['session_id'],
            $user_id,
            $sender_type,
            $input['message'],
            $input['message_type'] ?? 'text'
        ]);
        
        // 如果是客服发送消息，更新会话状态为active
        if ($sender_type === 'service') {
            $stmt = $db->prepare("UPDATE chat_sessions SET status = 'active', service_user_id = ? WHERE session_id = ?");
            $stmt->execute([$user_id, $input['session_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => '消息发送成功']);
    } catch (Exception $e) {
        logSecurityEvent($db, 'send_chat_message', 'failed', json_encode($input), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '发送消息失败']);
    }
}

// 关闭聊天会话
function closeChatSession() {
    global $db, $input;
    
    if (!checkPermission($db)) {
        http_response_code(403);
        echo json_encode(['error' => '权限不足']);
        return;
    }
    
    if (!isset($input['session_id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少会话ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("UPDATE chat_sessions SET status = 'closed', closed_at = NOW() WHERE session_id = ?");
        $stmt->execute([$input['session_id']]);
        
        logSecurityEvent($db, 'close_chat_session', 'success', $input['session_id']);
        echo json_encode(['success' => true, 'message' => '会话已关闭']);
    } catch (Exception $e) {
        logSecurityEvent($db, 'close_chat_session', 'failed', $input['session_id'], $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '关闭会话失败']);
    }
}

?>
