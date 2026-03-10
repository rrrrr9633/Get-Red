<?php
// 引入安全配置
require_once '../config/security.php';

// 配置安全Session（在任何session_start()之前）
configureSecureSession();

// 启动Session
session_start();

// 设置CORS头 - 限制允许的源
$allowedOrigins = [
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    'http://192.168.1.11:8080'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // 如果没有 Origin 头或不在白名单中，允许同源访问
    header('Access-Control-Allow-Origin: http://192.168.1.11:8080');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/coin-helper.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch($method) {
    case 'POST':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'register':
                    register();
                    break;
                case 'login':
                    login();
                    break;
                case 'logout':
                    logout();
                    break;
                case 'heartbeat':
                    handleHeartbeat();
                    break;
                case 'online':
                    handleOnline();
                    break;
                case 'offline':
                    handleOffline();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
    case 'GET':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'profile':
                    getProfile();
                    break;
                case 'balance':
                    getBalance();
                    break;
                case 'transactions':
                    getTransactions();
                    break;
                case 'get_warehouse_count':
                    getWarehouseCount();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
    case 'PUT':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'profile':
                    updateProfile();
                    break;
                case 'password':
                    changePassword();
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

function register() {
    global $db, $input;
    
    // 频率限制：30秒内最多3次注册尝试
    checkRateLimit('register', 3, 30);
    
    if(!isset($input['username']) || !isset($input['password']) || !isset($input['nickname'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        logSecurityEvent($db, 'register_attempt', 'failed', null, '缺少必要参数');
        return;
    }
    
    // 验证密码强度
    $passwordError = validatePasswordStrength($input['password']);
    if ($passwordError) {
        http_response_code(400);
        echo json_encode(['error' => $passwordError]);
        logSecurityEvent($db, 'register_attempt', 'failed', null, $passwordError);
        return;
    }
    
    // 检查用户名是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    if($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => '用户名已存在']);
        logSecurityEvent($db, 'register_attempt', 'failed', $input['username'], '用户名已存在');
        return;
    }
    
    // 创建用户（不包含手机号）
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    $avatar = isset($input['avatar']) ? $input['avatar'] : 'images/default-avatar.gif';
    
    // 插入用户数据（初始赠送10非绑定金币）
    $stmt = $db->prepare("INSERT INTO users (username, password, nickname, avatar, balance, bound_coins, unbound_coins) VALUES (?, ?, ?, ?, 10.00, 0.00, 10.00)");
    
    if ($stmt->execute([$input['username'], $hashedPassword, $input['nickname'], $avatar])) {
        $userId = $db->lastInsertId();
        
        // 记录注册奖励（10非绑定金币）
        $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, 10.00, '注册奖励（非绑定金币）', 'income')");
        $stmt->execute([$userId]);
        
        // 记录金币变动日志
        $stmt = $db->prepare("
            INSERT INTO coin_change_log 
            (user_id, change_type, coin_type, bound_change, unbound_change, 
             bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
             related_id, description)
            VALUES (?, 'register', 'unbound', 0, 10.00, 0, 0, 0, 10.00, NULL, '注册奖励')
        ");
        $stmt->execute([$userId]);
        
        // 自动分配客服（分配给用户数最少的客服）
        try {
            $stmt = $db->prepare("
                SELECT u.id, COUNT(sua.regular_user_id) as user_count
                FROM users u
                LEFT JOIN service_user_assignments sua ON u.id = sua.service_user_id AND sua.status = 'active'
                WHERE u.user_type = 'service' AND u.status = 'active'
                GROUP BY u.id
                ORDER BY user_count ASC, u.id ASC
                LIMIT 1
            ");
            $stmt->execute();
            $serviceUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($serviceUser) {
                // 分配给用户数最少的客服
                $stmt = $db->prepare("
                    INSERT INTO service_user_assignments (service_user_id, regular_user_id, assigned_by) 
                    VALUES (?, ?, NULL)
                ");
                $stmt->execute([$serviceUser['id'], $userId]);
            }
        } catch (Exception $e) {
            // 自动分配失败不影响注册流程
            error_log('自动分配客服失败: ' . $e->getMessage());
        }
        
        // 记录成功注册日志
        logSecurityEvent($db, 'register', 'success', $input['username']);
        
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '注册失败']);
        logSecurityEvent($db, 'register', 'failed', $input['username'], '数据库执行失败');
    }
}

function login() {
    global $db, $input;
    
    // 频率限制：30秒内最多5次登录尝试
    checkRateLimit('login', 5, 30);
    
    if(!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户名或密码']);
        logSecurityEvent($db, 'login_attempt', 'failed', null, '缺少用户名或密码');
        return;
    }
    
    // 只通过用户名查找用户
    $stmt = $db->prepare("SELECT id, username, password, nickname, avatar, balance, bound_coins, unbound_coins, has_recharged, session_token FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user && password_verify($input['password'], $user['password'])) {
        // 生成新的会话token
        $newSessionToken = bin2hex(random_bytes(32));
        
        // 获取客户端IP和设备信息
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // 更新用户在线状态、最后登录时间和会话token
        $updateStmt = $db->prepare("
            UPDATE users 
            SET is_online = 1, 
                last_login = NOW(), 
                last_activity = NOW(),
                session_token = ?,
                login_ip = ?,
                login_device = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$newSessionToken, $clientIp, $userAgent, $user['id']]);
        
        unset($user['password']);
        unset($user['session_token']); // 不返回token给客户端
        
        // Session已在文件开头启动，直接使用
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['session_token'] = $newSessionToken; // 存储在服务器端session中
        
        // 强制刷新Session Cookie
        refreshSessionCookie();
        
        // 生成CSRF Token
        $csrfToken = generateCsrfToken();
        
        // 记录成功登录日志
        logSecurityEvent($db, 'login', 'success', $input['username']);
        
        echo json_encode([
            'success' => true, 
            'user' => $user,
            'csrf_token' => $csrfToken,
            'message' => '登录成功'
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => '用户名或密码错误']);
        logSecurityEvent($db, 'login_attempt', 'failed', $input['username'], '用户名或密码错误');
    }
}

function logout() {
    // Session已在文件开头启动
    
    // 如果有用户登录，更新离线状态并清除session_token
    if(isset($_SESSION['user_id'])) {
        global $db;
        $username = $_SESSION['username'] ?? 'unknown';
        
        $stmt = $db->prepare("UPDATE users SET is_online = 0, session_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // 记录登出日志
        logSecurityEvent($db, 'logout', 'success', $username);
    }
    
    session_destroy();
    echo json_encode(['success' => true, 'message' => '退出成功']);
}

function getProfile() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录', 'forceLogout' => true]);
        return;
    }
    
    global $db;
    
    // 验证会话token是否匹配（单点登录检查）
    $stmt = $db->prepare("SELECT id, username, nickname, email, avatar, balance, bound_coins, unbound_coins, has_recharged, created_at, last_login, user_type, status, session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        http_response_code(404);
        echo json_encode(['error' => '用户不存在', 'forceLogout' => true]);
        return;
    }
    
    // 检查session_token是否匹配，如果不匹配说明在其他地方登录了
    if(isset($_SESSION['session_token']) && $user['session_token'] !== $_SESSION['session_token']) {
        // 清除当前会话
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error' => '您的账号已在其他设备登录',
            'forceLogout' => true,
            'reason' => 'kicked'
        ]);
        return;
    }
    
    // 更新用户活动时间
    $updateStmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $updateStmt->execute([$_SESSION['user_id']]);
    
    unset($user['session_token']); // 不返回token
    echo json_encode(['success' => true, 'user' => $user]);
}

function getBalance() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    $coins = getUserCoins($db, $_SESSION['user_id']);
    
    if (!$coins) {
        http_response_code(500);
        echo json_encode(['error' => '获取余额失败']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $coins['total_coins'],
        'bound_coins' => $coins['bound_coins'],
        'unbound_coins' => $coins['unbound_coins']
    ]);
    
    if($result) {
        echo json_encode(['success' => true, 'balance' => $result['balance']]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '用户不存在']);
    }
}

function updateProfile() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    // CSRF验证
    verifyCsrfToken();
    
    global $db, $input;
    $updateFields = [];
    $params = [];
    
    if(isset($input['nickname'])) {
        $updateFields[] = "nickname = ?";
        $params[] = $input['nickname'];
    }
    
    if(isset($input['email'])) {
        $updateFields[] = "email = ?";
        $params[] = $input['email'];
    }
    
    if(isset($input['avatar'])) {
        $updateFields[] = "avatar = ?";
        $params[] = $input['avatar'];
    }
    
    if(isset($input['balance'])) {
        $updateFields[] = "balance = ?";
        $params[] = $input['balance'];
    }
    
    if(empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => '没有要更新的字段']);
        return;
    }
    
    $params[] = $_SESSION['user_id'];
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if($stmt->execute($params)) {
        logSecurityEvent($db, 'profile_update', 'success', $_SESSION['username']);
        echo json_encode(['success' => true, 'message' => '更新成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '更新失败']);
        logSecurityEvent($db, 'profile_update', 'failed', $_SESSION['username'], '数据库执行失败');
    }
}

function changePassword() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    // CSRF验证
    verifyCsrfToken();
    
    // 频率限制：5分钟内最多3次修改密码尝试
    checkRateLimit('change_password', 3, 300);
    
    global $db, $input;
    
    if(!isset($input['current_password']) || !isset($input['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    // 验证新密码强度
    $passwordError = validatePasswordStrength($input['new_password']);
    if ($passwordError) {
        http_response_code(400);
        echo json_encode(['error' => $passwordError]);
        return;
    }
    
    // 验证当前密码
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user || !password_verify($input['current_password'], $user['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '当前密码错误']);
        return;
    }
    
    // 更新密码
    $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    if($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
        logSecurityEvent($db, 'password_change', 'success', $_SESSION['username']);
        echo json_encode(['success' => true, 'message' => '密码修改成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '密码修改失败']);
        logSecurityEvent($db, 'password_change', 'failed', $_SESSION['username'], '数据库执行失败');
    }
}

// 心跳检测处理
function handleHeartbeat() {
    // Session已在文件开头启动
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录', 'forceLogout' => true]);
        return;
    }
    
    // 验证会话token是否匹配
    $stmt = $db->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        http_response_code(401);
        echo json_encode(['error' => '用户不存在', 'forceLogout' => true]);
        return;
    }
    
    // 检查是否被其他设备挤掉
    if(isset($_SESSION['session_token']) && $user['session_token'] !== $_SESSION['session_token']) {
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error' => '您的账号已在其他设备登录',
            'forceLogout' => true,
            'reason' => 'kicked'
        ]);
        return;
    }
    
    // 更新最后活动时间
    $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '心跳检测成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '心跳检测失败']);
    }
}

// 设置在线状态
function handleOnline() {
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_online = 1, last_activity = NOW() WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '设置在线状态成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '设置在线状态失败']);
    }
}

// 设置离线状态
function handleOffline() {
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '设置离线状态成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '设置离线状态失败']);
    }
}

// 获取交易记录
function getTransactions() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    
    $userId = $_SESSION['user_id'];
    $type = $_GET['type'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(50, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    try {
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        
        // 根据类型过滤
        switch($type) {
            case 'draws':
                // 抽奖相关记录
                $whereClause .= " AND change_type = 'draw'";
                break;
            case 'decompose':
                // 分解相关记录
                $whereClause .= " AND change_type = 'decompose'";
                break;
            case 'financial':
                // 资金流水（排除抽奖和分解）
                $whereClause .= " AND change_type NOT IN ('draw', 'decompose')";
                break;
            case 'all':
            default:
                // 全部记录，不添加额外条件
                break;
        }
        
        // 获取总记录数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM coin_change_log $whereClause");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // 获取分页记录 - 使用coin_change_log表
        $stmt = $db->prepare("
            SELECT 
                id, 
                change_type as type,
                coin_type,
                bound_change,
                unbound_change,
                (bound_change + unbound_change) as amount,
                bound_balance_after,
                unbound_balance_after,
                description, 
                created_at 
            FROM coin_change_log 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
                'limit' => $limit
            ],
            'totalPages' => $totalPages // 保持兼容性
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取交易记录失败: ' . $e->getMessage()]);
    }
}

// 获取用户仓库物品数量
function getWarehouseCount() {
    // Session已在文件开头启动
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    
    try {
        // 统计用户仓库中的物品总数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_items WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'count' => intval($result['count'])
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取仓库物品数量失败: ' . $e->getMessage()]);
    }
}
?>
