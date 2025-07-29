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

$action = $_GET['action'] ?? '';

switch($action) {
    case 'verify':
        verifySuperAdmin();
        break;
    case 'create':
        createSuperAdmin();
        break;
    case 'check':
        checkSuperAdminAccess();
        break;
    case 'logout':
        logoutSuperAdmin();
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
        // 查找超级管理员
        $stmt = $db->prepare("SELECT * FROM super_admins WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            // 记录失败尝试
            logFailedAttempt($username, $clientIP, 'invalid_user');
            echo json_encode(['error' => '无效的超级管理员账户']);
            return;
        }
        
        // 验证密码
        if (!password_verify($password, $admin['password_hash'])) {
            logFailedAttempt($username, $clientIP, 'invalid_password');
            echo json_encode(['error' => '密码错误']);
            return;
        }
        
        // 验证安全密钥
        if ($secretKey !== $admin['secret_key']) {
            logFailedAttempt($username, $clientIP, 'invalid_secret_key');
            echo json_encode(['error' => '安全密钥错误']);
            return;
        }
        
        // 检查IP白名单（如果设置了）
        if (!empty($admin['ip_whitelist'])) {
            $allowedIPs = explode(',', $admin['ip_whitelist']);
            $allowedIPs = array_map('trim', $allowedIPs);
            
            if (!in_array($clientIP, $allowedIPs) && !in_array('*', $allowedIPs)) {
                logFailedAttempt($username, $clientIP, 'ip_not_allowed');
                echo json_encode(['error' => '您的IP地址未授权访问']);
                return;
            }
        }
        
        // 验证成功，更新最后登录时间
        $stmt = $db->prepare("UPDATE super_admins SET last_login = NOW() WHERE id = ?");
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

// 记录失败尝试
function logFailedAttempt($username, $ip, $reason) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO admin_security_log (username, ip_address, action, status, reason, created_at) 
            VALUES (?, ?, 'super_admin_login', 'failed', ?, NOW())
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
            INSERT INTO admin_security_log (admin_id, ip_address, action, status, created_at) 
            VALUES (?, ?, 'super_admin_login', 'success', NOW())
        ");
        $stmt->execute([$adminId, $ip]);
    } catch (Exception $e) {
        error_log('Failed to log security event: ' . $e->getMessage());
    }
}
?>
