<?php
/**
 * 公告API
 */

require_once '../config/database.php';
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 初始化数据库
$database = new Database();
$db = $database->getConnection();

// 获取请求参数
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 处理不同的请求
switch ($action) {
    case 'get_announcements':
        // 获取公告列表
        handleGetAnnouncements($db);
        break;
    
    case 'get_unread_count':
        // 获取未读公告数量
        handleGetUnreadCount($db);
        break;
    
    case 'mark_as_read':
        // 标记公告为已读
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleMarkAsRead($db, $data);
        }
        break;
    
    case 'add_announcement':
        // 添加公告（管理员/客服）
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleAddAnnouncement($db, $data);
        }
        break;
    
    case 'delete_announcement':
        // 删除公告（管理员/客服）
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleDeleteAnnouncement($db, $data);
        }
        break;
    
    case 'get_announcement_version':
        // 获取公告版本号（用于检测是否有新公告）
        handleGetAnnouncementVersion($db);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '未知的操作']);
        break;
}

/**
 * 获取公告列表
 */
function handleGetAnnouncements($db) {
    try {
        // 检查表是否存在
        $query = "SHOW TABLES LIKE 'announcements'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo json_encode([
                'success' => true,
                'announcements' => []
            ]);
            return;
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        
        $query = "SELECT a.*, 
                  (SELECT COUNT(*) FROM user_announcement_reads WHERE user_id = :user_id AND announcement_id = a.id) as is_read
                  FROM announcements a 
                  WHERE a.is_active = 1 
                  ORDER BY a.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $userId ?? 0]);
        $announcements = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'announcements' => $announcements
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => true,
            'announcements' => []
        ]);
    }
}

/**
 * 获取未读公告数量
 */
function handleGetUnreadCount($db) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            echo json_encode(['success' => true, 'unread_count' => 0]);
            return;
        }
        
        // 检查表是否存在
        $query = "SHOW TABLES LIKE 'announcements'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo json_encode(['success' => true, 'unread_count' => 0]);
            return;
        }
        
        $query = "SELECT COUNT(*) as unread_count 
                  FROM announcements a 
                  WHERE a.is_active = 1 
                  AND NOT EXISTS (
                      SELECT 1 FROM user_announcement_reads 
                      WHERE user_id = :user_id AND announcement_id = a.id
                  )";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'unread_count' => $result['unread_count']
        ]);
    } catch (Exception $e) {
        // 出错时返回0，不要让前端报错
        echo json_encode(['success' => true, 'unread_count' => 0]);
    }
}

/**
 * 标记公告为已读
 */
function handleMarkAsRead($db, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('请先登录');
        }
        
        $announcementId = $data['announcement_id'] ?? 0;
        
        if ($announcementId > 0) {
            // 标记单个公告为已读
            $query = "INSERT IGNORE INTO user_announcement_reads (user_id, announcement_id) 
                      VALUES (:user_id, :announcement_id)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':announcement_id' => $announcementId
            ]);
        } else {
            // 标记所有公告为已读
            $query = "INSERT IGNORE INTO user_announcement_reads (user_id, announcement_id)
                      SELECT :user_id, id FROM announcements WHERE is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $userId]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 添加公告
 */
function handleAddAnnouncement($db, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('请先登录');
        }
        
        // 检查权限（管理员或客服）
        $query = "SELECT user_type FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !in_array($user['user_type'], ['super_admin', 'admin', 'customer_service'])) {
            throw new Exception('没有权限');
        }
        
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        
        if (empty($title) || empty($content)) {
            throw new Exception('标题和内容不能为空');
        }
        
        // 插入公告
        $query = "INSERT INTO announcements (title, content, created_by) 
                  VALUES (:title, :content, :created_by)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':created_by' => $userId
        ]);
        
        // 更新公告版本号
        $query = "UPDATE announcement_version SET version = version + 1";
        $db->exec($query);
        
        echo json_encode(['success' => true, 'message' => '公告发布成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 删除公告
 */
function handleDeleteAnnouncement($db, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('请先登录');
        }
        
        // 检查权限
        $query = "SELECT user_type FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !in_array($user['user_type'], ['super_admin', 'admin', 'customer_service'])) {
            throw new Exception('没有权限');
        }
        
        $announcementId = $data['announcement_id'] ?? 0;
        
        if ($announcementId <= 0) {
            throw new Exception('无效的公告ID');
        }
        
        // 软删除（设置为不活跃）
        $query = "UPDATE announcements SET is_active = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $announcementId]);
        
        echo json_encode(['success' => true, 'message' => '公告删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取公告版本号
 */
function handleGetAnnouncementVersion($db) {
    try {
        // 检查表是否存在
        $query = "SHOW TABLES LIKE 'announcement_version'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            // 表不存在，返回默认版本
            echo json_encode([
                'success' => true,
                'version' => 1
            ]);
            return;
        }
        
        $query = "SELECT version FROM announcement_version LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'version' => $result['version'] ?? 1
        ]);
    } catch (Exception $e) {
        // 出错时返回默认版本，不要让前端报错
        echo json_encode([
            'success' => true,
            'version' => 1
        ]);
    }
}
?>
