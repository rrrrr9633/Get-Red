<?php
/**
 * 安全配置文件
 * 统一管理Session、错误显示等安全设置
 */

// 检测是否为生产环境
$isProduction = ($_SERVER['SERVER_NAME'] !== 'localhost' && 
                 $_SERVER['SERVER_NAME'] !== '127.0.0.1' &&
                 !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']));

// 错误显示配置
if ($isProduction) {
    // 生产环境：关闭错误显示，记录到日志
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
} else {
    // 开发环境：显示所有错误
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Session安全配置（必须在session_start()之前调用）
function configureSecureSession() {
    global $isProduction;
    
    // HttpOnly: 防止JavaScript访问Cookie（防XSS窃取Session）
    ini_set('session.cookie_httponly', 1);
    
    // Secure: 仅通过HTTPS传输Cookie（生产环境启用）
    if ($isProduction) {
        ini_set('session.cookie_secure', 1);
    }
    
    // SameSite: 防止CSRF攻击
    ini_set('session.cookie_samesite', 'Strict');
    
    // Session有效期：1小时
    ini_set('session.gc_maxlifetime', 3600);
    
    // Session Cookie有效期：1小时
    ini_set('session.cookie_lifetime', 3600);
    
    // 使用严格的Session ID生成
    ini_set('session.use_strict_mode', 1);
    
    // 仅使用Cookie存储Session ID
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    
    // 禁止通过URL传递Session ID
    ini_set('session.use_trans_sid', 0);
}

// 验证CSRF Token
function verifyCsrfToken() {
    // 从请求头获取token
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    // 如果请求头没有，尝试从POST数据获取
    if (empty($token) && isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    
    // 如果还是没有，尝试从JSON body获取
    if (empty($token)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            $token = $input['csrf_token'];
        }
    }
    
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF验证失败：缺少token']);
        exit;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF验证失败：token无效']);
        exit;
    }
    
    return true;
}

// 生成CSRF Token
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 请求频率限制
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start_time' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // 时间窗口过期，重置计数器
    if (time() - $data['start_time'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    
    // 检查是否超限
    if ($data['count'] >= $maxAttempts) {
        $remainingTime = $timeWindow - (time() - $data['start_time']);
        http_response_code(429);
        echo json_encode([
            'error' => '请求过于频繁，请稍后再试',
            'retry_after' => $remainingTime
        ]);
        exit;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// 验证密码强度
function validatePasswordStrength($password) {
    if (strlen($password) < 6) {
        return '密码长度至少6位';
    }
    
    // 可选：更严格的密码要求（取消注释启用）
    /*
    if (!preg_match('/[A-Z]/', $password)) {
        return '密码必须包含大写字母';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return '密码必须包含小写字母';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return '密码必须包含数字';
    }
    */
    
    return null; // 验证通过
}

// 清理输出（防XSS）
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// IP验证（检查Session是否被劫持）
function validateSessionIP() {
    if (!isset($_SESSION['user_ip'])) {
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        return true;
    }
    
    // 可选：严格IP验证（可能影响移动网络用户）
    // if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    //     session_destroy();
    //     http_response_code(401);
    //     echo json_encode(['error' => 'Session验证失败，请重新登录']);
    //     exit;
    // }
    
    return true;
}

// 记录安全事件
function logSecurityEvent($db, $action, $status, $details = null, $reason = null) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 获取用户类型
        $user_type = 'user';
        if ($user_id) {
            $stmt = $db->prepare("SELECT user_type FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $user_type = $user['user_type'] ?? 'user';
        }
        
        $stmt = $db->prepare("
            INSERT INTO security_logs 
            (user_id, username, user_type, ip_address, action, details, status, reason, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $username, 
            $user_type, 
            $ip, 
            $action, 
            $details, 
            $status, 
            $reason, 
            $user_agent
        ]);
    } catch (Exception $e) {
        error_log("安全日志记录失败: " . $e->getMessage());
    }
}

?>
