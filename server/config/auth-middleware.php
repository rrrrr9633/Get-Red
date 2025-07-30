<?php
// 超级管理员权限验证中间件
// 用于所有需要超级管理员权限的页面

function checkSuperAdminPermission() {
    // 检查是否通过POST请求或带有验证token
    $isDirectAccess = !isset($_SERVER['HTTP_REFERER']) || 
                     strpos($_SERVER['HTTP_REFERER'], 'admin.html') === false;
    
    // 如果是直接访问（通过URL），则拒绝
    if ($isDirectAccess && !isset($_SESSION['admin_verified'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => '禁止直接访问！请通过管理后台进入。'
        ]);
        exit();
    }
    
    // 验证超级管理员身份
    session_start();
    
    try {
        require_once '../../server/config/database.php';
        $db = (new Database())->getConnection();
        
        // 检查会话是否有效
        if (!isset($_SESSION['super_admin_id']) || !isset($_SESSION['super_admin_authenticated'])) {
            throw new Exception('未授权访问');
        }
        
        // 验证管理员是否仍然存在且激活
        $stmt = $db->prepare("SELECT id, username, is_active FROM super_admins WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['super_admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            // 清除无效会话
            session_destroy();
            throw new Exception('管理员账户不存在或已被禁用');
        }
        
        // 更新最后活动时间
        $stmt = $db->prepare("UPDATE super_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        return $admin;
        
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'redirect' => '../../super-admin.html'
        ]);
        exit();
    }
}

// 客服人员权限验证
function checkServicePermission() {
    session_start();
    
    try {
        require_once '../../server/config/database.php';
        $db = (new Database())->getConnection();
        
        // 检查是否为已认证的客服人员
        if (!isset($_SESSION['service_user_id']) || !isset($_SESSION['service_authenticated'])) {
            throw new Exception('客服权限验证失败');
        }
        
        // 验证客服人员身份
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ? AND role = 'service' AND is_active = 1");
        $stmt->execute([$_SESSION['service_user_id']]);
        $serviceUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$serviceUser) {
            session_destroy();
            throw new Exception('客服账户不存在或已被禁用');
        }
        
        return $serviceUser;
        
    } catch (Exception $e) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'redirect' => '../../auth/login.html'
        ]);
        exit();
    }
}

// 生成访问token（用于从admin页面跳转到其他管理页面）
function generateAccessToken($adminId) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['admin_access_token'] = $token;
    $_SESSION['admin_verified'] = time();
    return $token;
}

// 验证访问token
function verifyAccessToken($token) {
    return isset($_SESSION['admin_access_token']) && 
           $_SESSION['admin_access_token'] === $token &&
           isset($_SESSION['admin_verified']) &&
           (time() - $_SESSION['admin_verified']) < 300; // 5分钟有效期
}
?>
