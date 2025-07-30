<?php
session_start();
require_once '../config/database.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'verify':
        verifySuperAdmin();
        break;
    case 'verifySuperAdmin':
        verifySuperAdminNew();
        break;
    case 'verifyService':
        verifyService();
        break;
    case 'create':
        createSuperAdmin();
        break;
    case 'createUser':
        createUser();
        break;
    case 'changeSecretKey':
        changeSecretKey();
        break;
    case 'check':
        checkSuperAdminAccess();
        break;
    case 'logout':
        logoutSuperAdmin();
        break;
    case 'verify_identity':
        verifyIdentity();
        break;
    case 'verify_token':
        verifyAccessToken();
        break;
    case 'check_service':
        checkServiceAccess();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
        break;
}

// 验证超级管理员
function verifySuperAdmin() {
    global $db;
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $secretKey = $_POST['secretKey'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($username) || empty($password) || empty($secretKey)) {
        echo json_encode(['error' => '所有字段都是必填的']);
        return;
    }
    
    try {
        // 查找超级管理员（从users表）
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'super_admin' AND status = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            // 记录失败尝试
            logFailedAttempt($username, $clientIP, 'invalid_user');
            echo json_encode(['error' => '无效的超级管理员账户']);
            return;
        }
        
        // 验证密码
        if (!password_verify($password, $admin['password'])) {
            logFailedAttempt($username, $clientIP, 'invalid_password');
            echo json_encode(['error' => '密码错误']);
            return;
        }
        
        // 验证身份码
        if ($secretKey !== $admin['secret_key']) {
            logFailedAttempt($username, $clientIP, 'invalid_secret_key');
            echo json_encode(['error' => '身份码错误']);
            return;
        }
        
        // 验证成功，更新最后登录时间
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // 设置会话
        $_SESSION['super_admin_id'] = $admin['id'];
        $_SESSION['super_admin_username'] = $admin['username'];
        $_SESSION['super_admin_verified'] = true;
        
        // 记录成功登录
        logSuccessfulLogin($admin['id'], $clientIP);
        
        echo json_encode([
            'success' => true,
            'message' => '超级管理员验证成功',
            'admin' => [
                'id' => $admin['id'],
                'username' => $admin['username']
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '验证失败: ' . $e->getMessage()]);
    }
}

// 创建超级管理员（仅限本地主机调用）
function createSuperAdmin() {
    global $db;
    
    // 仅允许本地主机创建超级管理员
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($clientIP !== '127.0.0.1' && $clientIP !== '::1') {
        http_response_code(403);
        echo json_encode(['error' => '仅允许从本地主机创建超级管理员']);
        return;
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $secretKey = $_POST['secretKey'] ?? '';
    $ipWhitelist = $_POST['ipWhitelist'] ?? '';
    
    if (empty($username) || empty($password) || empty($secretKey)) {
        echo json_encode(['error' => '用户名、密码和安全密钥都是必填的']);
        return;
    }
    
    // 密码强度检查
    if (strlen($password) < 12) {
        echo json_encode(['error' => '密码长度至少需要12位']);
        return;
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        echo json_encode(['error' => '密码必须包含大小写字母、数字和特殊字符']);
        return;
    }
    
    try {
        // 检查用户名是否已存在
        $stmt = $db->prepare("SELECT id FROM super_admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => '用户名已存在']);
            return;
        }
        
        // 创建超级管理员
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO super_admins (username, password_hash, secret_key, ip_whitelist) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $passwordHash, $secretKey, $ipWhitelist]);
        
        echo json_encode([
            'success' => true,
            'message' => '超级管理员创建成功',
            'id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '创建失败: ' . $e->getMessage()]);
    }
}

// 检查超级管理员访问权限
function checkSuperAdminAccess() {
    // 检查会话是否存在且有效
    if (!isset($_SESSION['super_admin_verified']) || 
        $_SESSION['super_admin_verified'] !== true ||
        !isset($_SESSION['super_admin_id']) ||
        !isset($_SESSION['super_admin_username'])) {
        echo json_encode(['authenticated' => false]);
        return;
    }
    
    echo json_encode([
        'authenticated' => true,
        'admin' => [
            'id' => $_SESSION['super_admin_id'],
            'username' => $_SESSION['super_admin_username']
        ]
    ]);
}

// 登出超级管理员
function logoutSuperAdmin() {
    global $db;
    
    // 记录登出日志
    if (isset($_SESSION['super_admin_id'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO admin_security_log (admin_id, ip_address, action, status, created_at) 
                VALUES (?, ?, 'super_admin_logout', 'success', NOW())
            ");
            $stmt->execute([$_SESSION['super_admin_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {
            error_log('Failed to log logout event: ' . $e->getMessage());
        }
    }
    
    // 清除所有会话数据
    session_unset();
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => '已安全退出'
    ]);
}

// 创建用户
function createUser() {
    global $db;
    
    // 验证是否是超级管理员
    if (!isset($_SESSION['super_admin_verified']) || !$_SESSION['super_admin_verified']) {
        echo json_encode(['error' => '需要超级管理员权限']);
        return;
    }
    
    $userType = $_POST['userType'] ?? '';
    $username = $_POST['username'] ?? '';
    $nickname = $_POST['nickname'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $secretKey = $_POST['secretKey'] ?? '';
    
    // 验证必填字段
    if (empty($userType) || empty($username) || empty($nickname) || empty($password)) {
        echo json_encode(['error' => '请填写所有必填字段']);
        return;
    }
    
    // 验证用户类型
    $validTypes = ['user', 'service', 'super_admin'];
    if (!in_array($userType, $validTypes)) {
        echo json_encode(['error' => '无效的用户类型']);
        return;
    }
    
    // 如果创建超级管理员，需要验证身份码
    if ($userType === 'super_admin') {
        if (empty($secretKey)) {
            echo json_encode(['error' => '创建超级管理员需要身份码']);
            return;
        }
        
        // 验证当前用户的身份码
        $stmt = $db->prepare("SELECT secret_key FROM users WHERE id = ? AND user_type = 'super_admin'");
        $stmt->execute([$_SESSION['super_admin_id']]);
        $currentAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentAdmin || $secretKey !== $currentAdmin['secret_key']) {
            echo json_encode(['error' => '身份码验证失败']);
            return;
        }
    }
    
    try {
        // 检查用户名是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => '用户名已存在']);
            return;
        }
        
        // 创建用户
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, nickname, user_type, email, secret_key, balance, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $userSecretKey = ($userType === 'super_admin') ? 'admin' : null; // 新超级管理员默认身份码为admin
        $defaultBalance = ($userType === 'super_admin') ? 9999999.00 : 1000.00;
        
        $stmt->execute([
            $username,
            $hashedPassword,
            $nickname,
            $userType,
            $email ?: null,
            $userSecretKey,
            $defaultBalance
        ]);
        
        // 记录安全日志
        logSecurityAction($_SESSION['super_admin_id'], $username, 'create_user', 'success', "Created $userType user: $username");
        
        echo json_encode(['success' => true, 'message' => '用户创建成功']);
        
    } catch (Exception $e) {
        error_log('Create user error: ' . $e->getMessage());
        echo json_encode(['error' => '创建用户失败：' . $e->getMessage()]);
    }
}

// 更改超级管理员身份码
function changeSecretKey() {
    global $db;
    
    // 验证是否是超级管理员
    if (!isset($_SESSION['super_admin_verified']) || !$_SESSION['super_admin_verified']) {
        echo json_encode(['error' => '需要超级管理员权限']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $currentSecretKey = $input['currentSecretKey'] ?? '';
    $newSecretKey = $input['newSecretKey'] ?? '';
    $adminPassword = $input['adminPassword'] ?? '';
    
    if (empty($currentSecretKey) || empty($newSecretKey) || empty($adminPassword)) {
        echo json_encode(['error' => '所有字段都是必填的']);
        return;
    }
    
    if (strlen($newSecretKey) < 3) {
        echo json_encode(['error' => '新身份码长度至少3位']);
        return;
    }
    
    try {
        // 获取当前超级管理员信息
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'super_admin'");
        $stmt->execute([$_SESSION['super_admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode(['error' => '超级管理员账户不存在']);
            return;
        }
        
        // 验证当前身份码
        if ($currentSecretKey !== $admin['secret_key']) {
            echo json_encode(['error' => '当前身份码错误']);
            return;
        }
        
        // 验证管理员密码
        if (!password_verify($adminPassword, $admin['password'])) {
            echo json_encode(['error' => '管理员密码错误']);
            return;
        }
        
        // 更新身份码
        $stmt = $db->prepare("UPDATE users SET secret_key = ? WHERE id = ? AND user_type = 'super_admin'");
        $stmt->execute([$newSecretKey, $admin['id']]);
        
        // 记录安全日志
        logSecurityAction($admin['id'], $admin['username'], 'change_secret_key', 'success', "Changed secret key from admin interface");
        
        echo json_encode(['success' => true, 'message' => '身份码更改成功']);
        
    } catch (Exception $e) {
        error_log('Change secret key error: ' . $e->getMessage());
        echo json_encode(['error' => '更改身份码失败：' . $e->getMessage()]);
    }
}

// 记录安全操作
function logSecurityAction($userId, $username, $action, $status, $details = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO security_logs (user_id, username, user_type, ip_address, action, status, details, created_at) 
            VALUES (?, ?, 'super_admin', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $username,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $action,
            $status,
            $details
        ]);
    } catch (Exception $e) {
        error_log('Failed to log security action: ' . $e->getMessage());
    }
}

// 记录失败尝试
function logFailedAttempt($username, $ip, $reason) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO security_logs (username, user_type, ip_address, action, status, details, created_at) 
            VALUES (?, 'super_admin', ?, 'login_attempt', 'failed', ?, NOW())
        ");
        $stmt->execute([$username, $ip, $reason]);
    } catch (Exception $e) {
        error_log('Failed to log security event: ' . $e->getMessage());
    }
}

// 记录成功登录
function logSuccessfulLogin($adminId, $ip) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO security_logs (user_id, user_type, ip_address, action, status, details, created_at) 
            VALUES (?, 'super_admin', ?, 'login_attempt', 'success', 'Super admin login successful', NOW())
        ");
        $stmt->execute([$adminId, $ip]);
    } catch (Exception $e) {
        error_log('Failed to log security event: ' . $e->getMessage());
    }
}

// 新的超级管理员验证（使用account、password、identity_code）
function verifySuperAdminNew() {
    global $db;
    
    $account = $_POST['account'] ?? '';
    $password = $_POST['password'] ?? '';
    $identity_code = $_POST['identity_code'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($account) || empty($password) || empty($identity_code)) {
        echo json_encode(['success' => false, 'message' => '所有字段都是必填的']);
        return;
    }
    
    try {
        // 查找超级管理员
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'super_admin' AND status = 'active'");
        $stmt->execute([$account]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => '无效的超级管理员账户']);
            return;
        }
        
        // 验证密码
        if (!password_verify($password, $admin['password'])) {
            echo json_encode(['success' => false, 'message' => '密码错误']);
            return;
        }
        
        // 验证身份码
        if ($identity_code !== $admin['secret_key']) {
            echo json_encode(['success' => false, 'message' => '身份验证码错误']);
            return;
        }
        
        // 验证成功，设置会话
        $_SESSION['super_admin_id'] = $admin['id'];
        $_SESSION['super_admin_username'] = $admin['username'];
        $_SESSION['super_admin_verified'] = true;
        $_SESSION['user_type'] = 'super_admin';
        
        // 更新最后登录时间
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        echo json_encode(['success' => true, 'message' => '超级管理员验证成功']);
        
    } catch (Exception $e) {
        error_log('Super admin verification error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '验证过程中发生错误']);
    }
}

// 客服用户验证
function verifyService() {
    global $db;
    
    $account = $_POST['account'] ?? '';
    $password = $_POST['password'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($account) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '账户名和密码都是必填的']);
        return;
    }
    
    try {
        // 查找客服用户
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'service' AND status = 'active'");
        $stmt->execute([$account]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => '无效的客服账户']);
            return;
        }
        
        // 验证密码
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => '密码错误']);
            return;
        }
        
        // 验证成功，设置会话
        $_SESSION['service_user_id'] = $user['id'];
        $_SESSION['service_username'] = $user['username'];
        $_SESSION['service_verified'] = true;
        $_SESSION['user_type'] = 'service';
        
        // 更新最后登录时间
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        echo json_encode(['success' => true, 'message' => '客服用户登录成功']);
        
    } catch (Exception $e) {
        error_log('Service user verification error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '登录过程中发生错误']);
    }
}

// 验证超级管理员身份码（用于敏感操作）
function verifyIdentity() {
    session_start();
    global $db;
    
    // 检查是否为超级管理员会话
    if (!isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
        echo json_encode(['success' => false, 'message' => '未授权访问']);
        return;
    }
    
    $identity_code = $_POST['identity_code'] ?? '';
    
    if (empty($identity_code)) {
        echo json_encode(['success' => false, 'message' => '身份验证码不能为空']);
        return;
    }
    
    try {
        // 获取当前超级管理员的身份验证码
        $stmt = $db->prepare("SELECT secret_key FROM users WHERE id = ? AND user_type = 'super_admin'");
        $stmt->execute([$_SESSION['super_admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => '管理员信息不存在']);
            return;
        }
        
        // 验证身份码
        if ($identity_code !== $admin['secret_key']) {
            echo json_encode(['success' => false, 'message' => '身份验证码错误']);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => '身份验证成功']);
        
    } catch (Exception $e) {
        error_log('Identity verification error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '验证过程中发生错误']);
    }
}

// 验证访问token
function verifyAccessToken() {
    session_start(); // 确保session已启动
    
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    
    if (!$token) {
        echo json_encode(['success' => false, 'error' => '缺少token']);
        return;
    }
    
    // 验证token
    if (isset($_SESSION['admin_access_token']) && 
        $_SESSION['admin_access_token'] === $token &&
        isset($_SESSION['admin_verified']) &&
        (time() - $_SESSION['admin_verified']) < 300) { // 5分钟有效期
        
        // 验证超级管理员身份
        if (isset($_SESSION['super_admin_id']) && isset($_SESSION['super_admin_verified']) && $_SESSION['super_admin_verified'] === true) {
            echo json_encode([
                'success' => true,
                'authenticated' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'error' => '管理员身份验证失败'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'error' => 'Token无效或已过期'
        ]);
    }
}

// 检查客服人员权限
function checkServiceAccess() {
    session_start(); // 确保session已启动
    global $db;
    
    // 检查客服人员session
    if (!isset($_SESSION['service_user_id']) || !isset($_SESSION['service_verified']) || $_SESSION['service_verified'] !== true) {
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'error' => '客服权限验证失败'
        ]);
        return;
    }
    
    try {
        // 验证客服人员身份
        $stmt = $db->prepare("SELECT id, username, user_type FROM users WHERE id = ? AND user_type = 'service' AND status = 'active'");
        $stmt->execute([$_SESSION['service_user_id']]);
        $serviceUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$serviceUser) {
            session_destroy();
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'error' => '客服账户不存在或已被禁用'
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => $serviceUser
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'error' => '验证过程中发生错误'
        ]);
    }
}
?>
