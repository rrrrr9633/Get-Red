<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_service_users':
        getServiceUsers();
        break;
    case 'get_regular_users':
        getRegularUsers();
        break;
    case 'get_assignment_status':
        getAssignmentStatus();
        break;
    case 'auto_assign':
        autoAssignUsers();
        break;
    case 'manual_assign':
        manualAssignUsers();
        break;
    case 'remove_assignment':
        removeAssignment();
        break;
    case 'search_user':
        searchUser();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
}

// 获取客服用户列表
function getServiceUsers() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.nickname, 
                   COUNT(sua.regular_user_id) as assigned_count
            FROM users u
            LEFT JOIN service_user_assignments sua ON u.id = sua.service_user_id AND sua.status = 'active'
            WHERE u.user_type = 'service' AND u.status = 'active'
            GROUP BY u.id, u.username, u.nickname
            ORDER BY u.username
        ");
        $stmt->execute();
        $serviceUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'service_users' => $serviceUsers
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取客服用户失败: ' . $e->getMessage()]);
    }
}

// 获取普通用户列表（未分配的）
function getRegularUsers() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.nickname, u.created_at,
                   CASE WHEN sua.regular_user_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
                   sv.username as service_username
            FROM users u
            LEFT JOIN service_user_assignments sua ON u.id = sua.regular_user_id AND sua.status = 'active'
            LEFT JOIN users sv ON sua.service_user_id = sv.id
            WHERE u.user_type = 'user' AND u.status = 'active'
            ORDER BY is_assigned ASC, u.created_at DESC
        ");
        $stmt->execute();
        $regularUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'regular_users' => $regularUsers
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取普通用户失败: ' . $e->getMessage()]);
    }
}

// 获取分配状态统计
function getAssignmentStatus() {
    global $db;
    
    try {
        // 获取客服统计
        $stmt = $db->prepare("
            SELECT 
                sv.id,
                sv.username as service_username,
                sv.nickname as service_nickname,
                COUNT(sua.regular_user_id) as assigned_count,
                GROUP_CONCAT(
                    CONCAT(ru.username, '(', ru.nickname, ')') 
                    ORDER BY sua.assigned_at DESC 
                    SEPARATOR ', '
                ) as assigned_users
            FROM users sv
            LEFT JOIN service_user_assignments sua ON sv.id = sua.service_user_id AND sua.status = 'active'
            LEFT JOIN users ru ON sua.regular_user_id = ru.id
            WHERE sv.user_type = 'service' AND sv.status = 'active'
            GROUP BY sv.id, sv.username, sv.nickname
            ORDER BY assigned_count DESC, sv.username
        ");
        $stmt->execute();
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取总体统计
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN user_type = 'user' THEN 1 END) as total_regular_users,
                COUNT(CASE WHEN user_type = 'service' THEN 1 END) as total_service_users,
                (SELECT COUNT(*) FROM service_user_assignments WHERE status = 'active') as total_assigned
            FROM users 
            WHERE status = 'active'
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['unassigned_users'] = $stats['total_regular_users'] - $stats['total_assigned'];
        
        echo json_encode([
            'success' => true,
            'assignments' => $assignments,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取分配状态失败: ' . $e->getMessage()]);
    }
}

// 自动平均分配
function autoAssignUsers() {
    global $db, $input;
    
    try {
        $db->beginTransaction();
        
        // 获取所有活跃的客服用户
        $stmt = $db->prepare("
            SELECT id, username 
            FROM users 
            WHERE user_type = 'service' AND status = 'active'
            ORDER BY id
        ");
        $stmt->execute();
        $serviceUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($serviceUsers)) {
            throw new Exception('没有可用的客服用户');
        }
        
        // 获取所有未分配的普通用户
        $stmt = $db->prepare("
            SELECT u.id 
            FROM users u
            LEFT JOIN service_user_assignments sua ON u.id = sua.regular_user_id AND sua.status = 'active'
            WHERE u.user_type = 'user' AND u.status = 'active' AND sua.regular_user_id IS NULL
            ORDER BY u.created_at ASC
        ");
        $stmt->execute();
        $unassignedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unassignedUsers)) {
            $db->rollBack();
            echo json_encode([
                'success' => true,
                'message' => '没有需要分配的用户',
                'assigned_count' => 0
            ]);
            return;
        }
        
        // 平均分配算法
        $serviceCount = count($serviceUsers);
        $assignedCount = 0;
        
        // 删除现有的分配（如果需要重新分配）
        if (isset($input['reassign']) && $input['reassign']) {
            $stmt = $db->prepare("UPDATE service_user_assignments SET status = 'inactive' WHERE status = 'active'");
            $stmt->execute();
            
            // 重新获取所有普通用户
            $stmt = $db->prepare("
                SELECT id 
                FROM users 
                WHERE user_type = 'user' AND status = 'active'
                ORDER BY created_at ASC
            ");
            $stmt->execute();
            $unassignedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // 准备插入语句
        $stmt = $db->prepare("
            INSERT INTO service_user_assignments (service_user_id, regular_user_id, assigned_by) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            service_user_id = VALUES(service_user_id),
            status = 'active',
            assigned_at = CURRENT_TIMESTAMP,
            assigned_by = VALUES(assigned_by)
        ");
        
        // 分配用户
        foreach ($unassignedUsers as $index => $user) {
            $serviceIndex = $index % $serviceCount;
            $serviceUserId = $serviceUsers[$serviceIndex]['id'];
            
            $stmt->execute([$serviceUserId, $user['id'], $input['admin_id'] ?? null]);
            $assignedCount++;
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "成功分配 {$assignedCount} 个用户给 {$serviceCount} 个客服",
            'assigned_count' => $assignedCount
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '自动分配失败: ' . $e->getMessage()]);
    }
}

// 手动分配用户
function manualAssignUsers() {
    global $db, $input;
    
    try {
        if (!isset($input['service_user_id']) || !isset($input['regular_user_ids'])) {
            throw new Exception('缺少必要参数');
        }
        
        $serviceUserId = $input['service_user_id'];
        $regularUserIds = $input['regular_user_ids'];
        
        if (!is_array($regularUserIds) || empty($regularUserIds)) {
            throw new Exception('请选择要分配的用户');
        }
        
        $db->beginTransaction();
        
        // 验证客服用户存在
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE id = ? AND user_type = 'service' AND status = 'active'
        ");
        $stmt->execute([$serviceUserId]);
        if (!$stmt->fetch()) {
            throw new Exception('选择的客服用户不存在或不可用');
        }
        
        // 准备插入语句
        $stmt = $db->prepare("
            INSERT INTO service_user_assignments (service_user_id, regular_user_id, assigned_by) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            service_user_id = VALUES(service_user_id),
            status = 'active',
            assigned_at = CURRENT_TIMESTAMP,
            assigned_by = VALUES(assigned_by)
        ");
        
        $assignedCount = 0;
        foreach ($regularUserIds as $regularUserId) {
            // 验证普通用户存在
            $checkStmt = $db->prepare("
                SELECT id FROM users 
                WHERE id = ? AND user_type = 'user' AND status = 'active'
            ");
            $checkStmt->execute([$regularUserId]);
            
            if ($checkStmt->fetch()) {
                $stmt->execute([$serviceUserId, $regularUserId, $input['admin_id'] ?? null]);
                $assignedCount++;
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "成功分配 {$assignedCount} 个用户",
            'assigned_count' => $assignedCount
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '手动分配失败: ' . $e->getMessage()]);
    }
}

// 移除分配
function removeAssignment() {
    global $db, $input;
    
    try {
        if (!isset($input['regular_user_id'])) {
            throw new Exception('缺少用户ID参数');
        }
        
        $stmt = $db->prepare("
            UPDATE service_user_assignments 
            SET status = 'inactive', updated_at = CURRENT_TIMESTAMP
            WHERE regular_user_id = ? AND status = 'active'
        ");
        $stmt->execute([$input['regular_user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => '成功移除分配'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '移除分配失败: ' . $e->getMessage()]);
    }
}

// 搜索用户
function searchUser() {
    global $db, $input;
    
    try {
        if (!isset($input['search_term']) || empty(trim($input['search_term']))) {
            throw new Exception('搜索关键词不能为空');
        }
        
        $searchTerm = trim($input['search_term']);
        
        $stmt = $db->prepare("
            SELECT 
                u.id, 
                u.username, 
                u.nickname, 
                u.created_at,
                CASE WHEN sua.regular_user_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned,
                sv.username as service_username,
                sv.nickname as service_nickname
            FROM users u
            LEFT JOIN service_user_assignments sua ON u.id = sua.regular_user_id AND sua.status = 'active'
            LEFT JOIN users sv ON sua.service_user_id = sv.id
            WHERE u.user_type = 'user' 
            AND u.status = 'active'
            AND (u.username LIKE ? OR u.nickname LIKE ?)
            ORDER BY is_assigned ASC, u.created_at DESC
            LIMIT 20
        ");
        
        $searchPattern = '%' . $searchTerm . '%';
        $stmt->execute([$searchPattern, $searchPattern]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'total_found' => count($users)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '搜索用户失败: ' . $e->getMessage()]);
    }
}
?>
