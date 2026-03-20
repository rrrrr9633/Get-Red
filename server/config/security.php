<?php
/**
 * 安全配置文件
 * 统一管理Session、错误显示等安全设置
 */

// 环境配置：
// 优先根据 APP_ENV 环境变量判断，其次在本地（localhost/IP）自动启用开发模式
$appEnv = getenv('APP_ENV');
// getenv() 在未设置时可能返回 false；这里统一转成空字符串，避免默认直接走 production
if ($appEnv === false) {
    $appEnv = '';
}
$appEnvLower = strtolower(trim($appEnv));

// 本地访问：默认按开发模式（除非显式设置为 production/prod）
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalHost = in_array($serverName, ['localhost', '127.0.0.1']) ||
               in_array($remoteAddr, ['127.0.0.1', '::1']);

if ($isLocalHost) {
    $isProduction = in_array($appEnvLower, ['production', 'prod']);
} else {
    // 非本地：根据 APP_ENV 判断（local/dev/development/test 为开发，其余为生产）
    $isProduction = !in_array($appEnvLower, ['local', 'dev', 'development', 'test']);
}

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
    } else {
        // 开发环境：关闭Secure，允许HTTP访问
        ini_set('session.cookie_secure', 0);
    }
    
    // SameSite: 开发环境设置为None以支持跨域（IP访问）
    // 注意：PHP 7.3+ 才支持 session.cookie_samesite
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        if ($isProduction) {
            ini_set('session.cookie_samesite', 'Lax');
        } else {
            // 开发环境：设置为None以支持IP访问
            // 但由于Secure=0，某些浏览器可能会忽略SameSite=None
            // 所以我们设置为空字符串，让浏览器使用默认行为
            ini_set('session.cookie_samesite', '');
        }
    }
    
    // Cookie域名：不设置domain，让其自动使用当前域名/IP
    // 这样无论是localhost还是IP访问都能正常工作
    ini_set('session.cookie_domain', '');
    
    // Cookie路径：设置为根路径
    ini_set('session.cookie_path', '/');
    
    // Session有效期：8小时
    ini_set('session.gc_maxlifetime', 28800);
    
    // Session Cookie有效期：8小时
    ini_set('session.cookie_lifetime', 28800);
    
    // 使用严格的Session ID生成
    ini_set('session.use_strict_mode', 1);
    
    // 仅使用Cookie存储Session ID
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    
    // 禁止通过URL传递Session ID
    ini_set('session.use_trans_sid', 0);
    
    // 调试日志（仅开发环境）
    if (!$isProduction) {
        error_log("Session配置: cookie_httponly=" . ini_get('session.cookie_httponly') . 
                  ", cookie_secure=" . ini_get('session.cookie_secure') . 
                  ", cookie_samesite=" . ini_get('session.cookie_samesite') . 
                  ", cookie_domain=" . ini_get('session.cookie_domain') . 
                  ", cookie_path=" . ini_get('session.cookie_path'));
    }
}

// 强制刷新Session Cookie（在登录后调用）
function refreshSessionCookie() {
    global $isProduction;
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        // 保存当前 Session 数据
        $sessionData = $_SESSION;
        
        // 重新生成Session ID（安全措施）
        session_regenerate_id(true);
        
        // 恢复 Session 数据
        $_SESSION = $sessionData;
        
        // 显式设置Cookie参数
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + 28800, // 8小时
                'path' => '/',
                'domain' => '',
                'secure' => $isProduction ? true : false,
                'httponly' => true,
                'samesite' => $isProduction ? 'Lax' : ''
            ]
        );
    }
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
        echo json_encode([
            'error' => 'CSRF验证失败：缺少token'
        ]);
        exit;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'CSRF验证失败：token无效'
        ]);
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = sha1($action . '|' . $ip);
    
    // 使用临时文件进行基于 IP 的限流，避免仅依赖单个 Session
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    
    $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
    $now  = time();
    
    // 通过 flock 对“读-改-写”整个过程加锁，避免并发竞态条件
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        // 打不开文件时，直接放行请求，避免影响正常功能
        return true;
    }
    
    // 独占锁，阻塞直到获得锁
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return true;
    }
    
    // 从文件读取当前计数
    $data = [
        'count'      => 0,
        'start_time' => $now
    ];
    
    // 将文件指针移到开头再读
    clearstatcache(true, $file);
    $filesize = filesize($file);
    if ($filesize > 0) {
        // 确保文件指针在开头位置再读取
        rewind($fh);
        $content = fread($fh, $filesize);
        if ($content !== false) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start_time'])) {
                $data = $decoded;
            }
        }
    }
    
    // 时间窗口过期，重置计数器
    if ($now - $data['start_time'] > $timeWindow) {
        $data = [
            'count'      => 1,
            'start_time' => $now
        ];
    } else {
        // 检查是否超限
        if ($data['count'] >= $maxAttempts) {
            $remainingTime = $timeWindow - ($now - $data['start_time']);
            if ($remainingTime < 0) {
                $remainingTime = 0;
            }
            
            // 更新文件中的数据后再返回（记录当前被拒绝的状态）
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
            
            http_response_code(429);
            echo json_encode([
                'error'       => '请求过于频繁，请稍后再试',
                'retry_after' => $remainingTime
            ]);
            exit;
        }
        
        // 尚未超限，增加计数
        $data['count']++;
    }
    
    // 将更新后的数据写回文件（覆盖原内容）
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    
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
