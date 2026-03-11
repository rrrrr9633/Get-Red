<?php
// 引入安全配置
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();

// 启动Session
session_start();

// 客服系统API
$allowedOrigins = [
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    'http://192.168.1.11:8080'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: http://192.168.1.11:8080');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
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

// 检查用户权限
function checkPermission($db, $required_types = ['service', 'admin', 'super_admin']) {
    // 优先检查超级管理员session
    if (isset($_SESSION['super_admin_id']) && isset($_SESSION['super_admin_verified']) && $_SESSION['super_admin_verified'] === true) {
        $userId = $_SESSION['super_admin_id'];
    } 
    // 其次检查普通用户session
    elseif (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    // 最后检查客服专用session
    elseif (isset($_SESSION['service_user_id'])) {
        $userId = $_SESSION['service_user_id'];
    } else {
        return false;
    }
    
    $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user && in_array($user['user_type'], $required_types);
}

// 获取当前用户ID
function getCurrentUserId() {
    // 优先检查超级管理员session
    if (isset($_SESSION['super_admin_id']) && isset($_SESSION['super_admin_verified']) && $_SESSION['super_admin_verified'] === true) {
        return $_SESSION['super_admin_id'];
    } 
    // 其次检查普通用户session
    elseif (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    // 最后检查客服专用session
    elseif (isset($_SESSION['service_user_id'])) {
        return $_SESSION['service_user_id'];
    }
    return null;
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
                case 'get_user_session':
                    getUserSession();
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
                case 'create_admin_session':
                    createAdminSession();
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
    
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    try {
        // 获取用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(403);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        if ($user['user_type'] === 'service') {
            // 客服用户：获取分配给自己的会话
            $stmt = $db->prepare("
                SELECT cs.*, 
                       u.nickname as user_nickname, 
                       u.username as user_username,
                       u.is_online as user_is_online,
                       (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.session_id AND sender_type = 'user' AND is_read = 0) as unread_count
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.service_user_id = ? AND cs.status IN ('waiting', 'active')
                ORDER BY cs.updated_at DESC, cs.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id]);
        } elseif (in_array($user['user_type'], ['admin', 'super_admin'])) {
            // 管理员：获取所有会话
            $stmt = $db->prepare("
                SELECT cs.*, 
                       u.nickname as user_nickname, 
                       u.username as user_username,
                       u.is_online as user_is_online,
                       su.nickname as service_nickname, 
                       su.username as service_username,
                       (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.session_id AND sender_type = 'user' AND is_read = 0) as unread_count
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                LEFT JOIN users su ON cs.service_user_id = su.id
                WHERE cs.status IN ('waiting', 'active')
                ORDER BY cs.updated_at DESC, cs.created_at DESC
                LIMIT 100
            ");
            $stmt->execute();
        } else {
            // 普通用户：只能看自己的会话
            $stmt = $db->prepare("
                SELECT cs.*, 
                       u.nickname as user_nickname, 
                       u.username as user_username,
                       su.nickname as service_nickname, 
                       su.username as service_username
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                LEFT JOIN users su ON cs.service_user_id = su.id
                WHERE cs.user_id = ?
                ORDER BY cs.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
        }
        
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'get_chat_sessions', 'failed', null, $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取会话列表失败: ' . $e->getMessage()]);
    }
}

// 获取聊天消息
function getChatMessages() {
    global $db;
    
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    if (!isset($_GET['session_id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少会话ID']);
        return;
    }
    
    try {
        $session_id = $_GET['session_id'];
        
        // 获取用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(403);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 验证用户是否有权限查看此会话
        $stmt = $db->prepare("SELECT user_id, service_user_id FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => '会话不存在']);
            return;
        }
        
        // 检查权限：普通用户只能看自己的会话，客服只能看分配给自己的会话，管理员可以看所有会话
        $hasPermission = false;
        if ($user['user_type'] === 'user' && $session['user_id'] == $user_id) {
            $hasPermission = true;
        } elseif ($user['user_type'] === 'service' && $session['service_user_id'] == $user_id) {
            $hasPermission = true;
        } elseif (in_array($user['user_type'], ['admin', 'super_admin'])) {
            $hasPermission = true;
        }
        
        if (!$hasPermission) {
            http_response_code(403);
            echo json_encode(['error' => '无权限查看此会话']);
            return;
        }
        
        // 获取消息
        $stmt = $db->prepare("
            SELECT cm.*, u.nickname as sender_nickname, u.username as sender_username
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.session_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$session_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 如果是客服或管理员查看，标记用户发送的消息为已读
        if (in_array($user['user_type'], ['service', 'admin', 'super_admin'])) {
            $stmt = $db->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE session_id = ? AND sender_type = 'user' AND is_read = 0
            ");
            $stmt->execute([$session_id]);
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'get_chat_messages', 'failed', "session_id: " . ($session_id ?? 'unknown'), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取消息失败: ' . $e->getMessage()]);
    }
}

// 开始聊天会话
function startChatSession() {
    global $db, $input;
    
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    try {
        
        // 检查是否已有活跃会话
        $stmt = $db->prepare("
            SELECT session_id FROM chat_sessions 
            WHERE user_id = ? AND status IN ('waiting', 'active')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSession) {
            // 返回现有会话
            echo json_encode([
                'success' => true, 
                'session_id' => $existingSession['session_id'],
                'is_new' => false
            ]);
            return;
        }
        
        // 创建新会话
        $session_id = 'chat_' . $user_id . '_' . time() . '_' . rand(1000, 9999);
        
        // 查找分配的客服
        $stmt = $db->prepare("
            SELECT service_user_id 
            FROM service_user_assignments 
            WHERE regular_user_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $service_user_id = $assignment ? $assignment['service_user_id'] : null;
        
        $stmt = $db->prepare("
            INSERT INTO chat_sessions (user_id, service_user_id, session_id, status) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $service_user_id, 
            $session_id,
            $service_user_id ? 'active' : 'waiting'
        ]);
        
        // 发送欢迎消息
        if ($service_user_id) {
            $stmt = $db->prepare("
                INSERT INTO chat_messages (session_id, sender_id, sender_type, message, message_type) 
                VALUES (?, ?, 'service', ?, 'text')
            ");
            $stmt->execute([
                $session_id,
                $service_user_id,
                '您好！我是您的专属客服，很高兴为您服务。请问有什么可以帮助您的？'
            ]);
        }
        
        echo json_encode([
            'success' => true, 
            'session_id' => $session_id,
            'is_new' => true,
            'has_service' => $service_user_id ? true : false
        ]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'start_chat_session', 'failed', null, $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '创建会话失败: ' . $e->getMessage()]);
    }
}

// 发送聊天消息
function sendChatMessage() {
    global $db, $input;
    
    $user_id = getCurrentUserId();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    if (!isset($input['session_id']) || !isset($input['message'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        // 获取用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(403);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 验证用户是否有权限在此会话中发送消息
        $stmt = $db->prepare("SELECT user_id, service_user_id FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$input['session_id']]);
        $session = $stmt->fetch();
        
        if (!$session) {
            http_response_code(404);
            echo json_encode(['error' => '会话不存在']);
            return;
        }
        
        // 检查权限
        $hasPermission = false;
        if ($user['user_type'] === 'user' && $session['user_id'] == $user_id) {
            $hasPermission = true;
        } elseif ($user['user_type'] === 'service' && $session['service_user_id'] == $user_id) {
            $hasPermission = true;
        } elseif (in_array($user['user_type'], ['admin', 'super_admin'])) {
            $hasPermission = true;
        }
        
        if (!$hasPermission) {
            http_response_code(403);
            echo json_encode(['error' => '无权限在此会话中发送消息']);
            return;
        }
        
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
        
        // 更新会话的最后更新时间
        $stmt = $db->prepare("UPDATE chat_sessions SET updated_at = NOW() WHERE session_id = ?");
        $stmt->execute([$input['session_id']]);
        
        // 如果是客服发送消息，更新会话状态为active
        // 但如果是超级管理员，不更新 service_user_id（保持原客服分配）
        if ($sender_type === 'service') {
            if ($user['user_type'] === 'service') {
                // 普通客服：更新 service_user_id
                $stmt = $db->prepare("UPDATE chat_sessions SET status = 'active', service_user_id = ? WHERE session_id = ?");
                $stmt->execute([$user_id, $input['session_id']]);
            } else {
                // 超级管理员：只更新状态，不改变客服分配
                $stmt = $db->prepare("UPDATE chat_sessions SET status = 'active' WHERE session_id = ?");
                $stmt->execute([$input['session_id']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => '消息发送成功']);
    } catch (Exception $e) {
        logSecurityEvent($db, 'send_chat_message', 'failed', json_encode($input), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '发送消息失败: ' . $e->getMessage()]);
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

// 获取指定用户的会话（超级管理员专用）
function getUserSession() {
    global $db;
    
    $current_user_id = getCurrentUserId();
    
    // 调试信息
    error_log("getUserSession called");
    error_log("Session ID: " . session_id());
    error_log("Current user_id: " . ($current_user_id ?? 'NOT SET'));
    error_log("Request user_id: " . ($_GET['user_id'] ?? 'NOT SET'));
    
    if (!$current_user_id) {
        error_log("Current user_id not set, returning 401");
        http_response_code(401);
        echo json_encode(['error' => '未登录', 'debug' => 'user_id_not_set']);
        return;
    }
    
    if (!isset($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        $user_id = $_GET['user_id'];
        
        error_log("Current user ID: $current_user_id, Target user ID: $user_id");
        
        // 获取当前用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $current_user = $stmt->fetch();
        
        if (!$current_user) {
            error_log("Current user not found in database");
            http_response_code(403);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        error_log("Current user type: " . $current_user['user_type']);
        
        // 查询该用户的最新会话（不限制状态，包括已关闭的）
        if (in_array($current_user['user_type'], ['admin', 'super_admin'])) {
            // 超级管理员可以查看所有会话
            $stmt = $db->prepare("
                SELECT cs.*, 
                       u.nickname as user_nickname, 
                       u.username as user_username,
                       u.is_online as user_is_online
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.user_id = ?
                ORDER BY cs.updated_at DESC, cs.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
        } elseif ($current_user['user_type'] === 'service') {
            // 客服只能查看分配给自己的会话
            $stmt = $db->prepare("
                SELECT cs.*, 
                       u.nickname as user_nickname, 
                       u.username as user_username,
                       u.is_online as user_is_online
                FROM chat_sessions cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.user_id = ? AND cs.service_user_id = ?
                ORDER BY cs.updated_at DESC, cs.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id, $current_user_id]);
        } else {
            http_response_code(403);
            echo json_encode(['error' => '权限不足']);
            return;
        }
        
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Session found: " . ($session ? 'YES' : 'NO'));
        if ($session) {
            error_log("Session ID: " . $session['session_id']);
        }
        
        if ($session) {
            echo json_encode(['success' => true, 'session' => $session]);
        } else {
            echo json_encode(['success' => false, 'message' => '未找到会话']);
        }
    } catch (Exception $e) {
        error_log("getUserSession error: " . $e->getMessage());
        logSecurityEvent($db, 'get_user_session', 'failed', "user_id: " . ($user_id ?? 'unknown'), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取会话失败: ' . $e->getMessage()]);
    }
}

// 创建管理员会话（超级管理员主动发起）
function createAdminSession() {
    global $db, $input;
    
    $current_user_id = getCurrentUserId();
    if (!$current_user_id) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        $target_user_id = $input['user_id'];
        
        // 获取当前用户类型
        $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $current_user = $stmt->fetch();
        
        if (!$current_user || !in_array($current_user['user_type'], ['admin', 'super_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => '只有管理员可以创建会话']);
            return;
        }
        
        // 检查是否已有会话
        $stmt = $db->prepare("
            SELECT session_id FROM chat_sessions 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$target_user_id]);
        $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSession) {
            // 如果已有会话，重新激活它
            $stmt = $db->prepare("UPDATE chat_sessions SET status = 'active', updated_at = NOW() WHERE session_id = ?");
            $stmt->execute([$existingSession['session_id']]);
            
            echo json_encode([
                'success' => true, 
                'session_id' => $existingSession['session_id'],
                'is_new' => false
            ]);
            return;
        }
        
        // 创建新会话
        $session_id = 'chat_' . $target_user_id . '_' . time() . '_' . rand(1000, 9999);
        
        // 查找分配的客服（如果有）
        $stmt = $db->prepare("
            SELECT service_user_id 
            FROM service_user_assignments 
            WHERE regular_user_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$target_user_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $service_user_id = $assignment ? $assignment['service_user_id'] : null;
        
        $stmt = $db->prepare("
            INSERT INTO chat_sessions (user_id, service_user_id, session_id, status) 
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([
            $target_user_id, 
            $service_user_id, 
            $session_id
        ]);
        
        logSecurityEvent($db, 'create_admin_session', 'success', "session_id: $session_id, user_id: $target_user_id");
        
        echo json_encode([
            'success' => true, 
            'session_id' => $session_id,
            'is_new' => true
        ]);
    } catch (Exception $e) {
        logSecurityEvent($db, 'create_admin_session', 'failed', json_encode($input), $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '创建会话失败: ' . $e->getMessage()]);
    }
}

?>
