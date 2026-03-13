<?php
/**
 * Admin Lucky Instance Management API
 * 
 * 功能：管理Lucky实例的创建、更新、删除操作
 * 需求: 5.1, 5.2, 5.3, 6.1, 6.2, 7.1, 7.2, 13.1, 13.2, 14.2, 14.3
 * 
 * 支持的操作：
 * - create: 创建新的Lucky实例
 * - update: 更新Lucky实例配置
 * - delete: 删除Lucky实例（级联删除关联数据）
 * 
 * 权限要求：所有操作需要 super_admin 权限
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

/**
 * 验证用户是否为超级管理员
 * 需求: 13.1, 13.2
 * 
 * @param PDO $pdo 数据库连接
 * @param string $token JWT Token或用户ID（简化版本）
 * @return array 包含验证结果和用户信息
 */
function verifySuperAdmin($pdo, $token) {
    try {
        // 简化版本：直接使用用户ID验证
        // 生产环境应该使用JWT Token验证
        if (!$token || !is_numeric($token)) {
            return [
                'success' => false,
                'message' => '无效的认证令牌',
                'error_code' => 'INVALID_TOKEN'
            ];
        }
        
        $userId = intval($token);
        
        // 查询用户信息
        $stmt = $pdo->prepare("
            SELECT id, username, user_type 
            FROM users 
            WHERE id = ? AND user_type = 'super_admin' AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => '权限不足，需要超级管理员权限',
                'error_code' => 'PERMISSION_DENIED'
            ];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
        
    } catch (Exception $e) {
        error_log("权限验证失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '权限验证失败',
            'error_code' => 'AUTH_ERROR'
        ];
    }
}

/**
 * 验证实例名称
 * 需求: 14.2
 * 
 * @param string $name 实例名称
 * @return array 验证结果
 */
function validateInstanceName($name) {
    if (empty($name)) {
        return [
            'valid' => false,
            'message' => '实例名称不能为空'
        ];
    }
    
    if (strlen($name) < 1 || strlen($name) > 100) {
        return [
            'valid' => false,
            'message' => '实例名称长度必须在1到100字符之间'
        ];
    }
    
    return ['valid' => true];
}

/**
 * 验证URL格式
 * 需求: 14.3
 * 
 * @param string $url URL地址
 * @param bool $required 是否必需
 * @return array 验证结果
 */
function validateUrl($url, $required = false) {
    if (empty($url)) {
        if ($required) {
            return [
                'valid' => false,
                'message' => 'URL不能为空'
            ];
        }
        return ['valid' => true];
    }
    
    // 简单的URL格式验证
    if (!preg_match('/^(https?:\/\/|\/|\.\/|\.\.\/|[a-zA-Z0-9_\-\/\.]+)/', $url)) {
        return [
            'valid' => false,
            'message' => 'URL格式无效'
        ];
    }
    
    return ['valid' => true];
}

/**
 * 创建Lucky实例
 * 需求: 5.1, 5.2, 5.3
 * 
 * @param PDO $pdo 数据库连接
 * @param array $data 实例数据
 * @param int $userId 操作用户ID
 * @return array 响应数组
 */
function createLuckyInstance($pdo, $data, $userId) {
    try {
        // 验证必需字段
        if (!isset($data['name']) || !isset($data['display_name'])) {
            return [
                'success' => false,
                'message' => '缺少必需字段：name 和 display_name'
            ];
        }
        
        // 验证实例名称
        $nameValidation = validateInstanceName($data['name']);
        if (!$nameValidation['valid']) {
            return [
                'success' => false,
                'message' => $nameValidation['message']
            ];
        }
        
        $displayNameValidation = validateInstanceName($data['display_name']);
        if (!$displayNameValidation['valid']) {
            return [
                'success' => false,
                'message' => $displayNameValidation['message']
            ];
        }
        
        // 验证URL格式（如果提供）
        if (isset($data['thumbnail_url'])) {
            $urlValidation = validateUrl($data['thumbnail_url']);
            if (!$urlValidation['valid']) {
                return [
                    'success' => false,
                    'message' => '缩略图URL格式无效'
                ];
            }
        }
        
        if (isset($data['background_url'])) {
            $urlValidation = validateUrl($data['background_url']);
            if (!$urlValidation['valid']) {
                return [
                    'success' => false,
                    'message' => '背景图URL格式无效'
                ];
            }
        }
        
        // 检查名称唯一性
        $stmt = $pdo->prepare("SELECT id FROM lucky_instances WHERE name = ?");
        $stmt->execute([$data['name']]);
        if ($stmt->fetch()) {
            return [
                'success' => false,
                'message' => '实例名称已存在，请使用其他名称'
            ];
        }
        
        // 验证分组ID（如果提供）
        if (isset($data['group_id']) && $data['group_id'] !== null) {
            $stmt = $pdo->prepare("SELECT id FROM lucky_groups WHERE id = ?");
            $stmt->execute([$data['group_id']]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => '指定的分组不存在'
                ];
            }
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 插入新实例
        $stmt = $pdo->prepare("
            INSERT INTO lucky_instances 
            (name, display_name, description, thumbnail_url, background_url, group_id, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['display_name'],
            $data['description'] ?? null,
            $data['thumbnail_url'] ?? null,
            $data['background_url'] ?? null,
            $data['group_id'] ?? null,
            $data['sort_order'] ?? 0,
            isset($data['is_active']) ? $data['is_active'] : 1
        ]);
        
        $newInstanceId = $pdo->lastInsertId();
        
        // 获取新创建的实例
        $stmt = $pdo->prepare("SELECT * FROM lucky_instances WHERE id = ?");
        $stmt->execute([$newInstanceId]);
        $newInstance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 记录审计日志
        $stmt = $pdo->prepare("
            INSERT INTO security_logs 
            (user_id, username, user_type, ip_address, action, details, status)
            VALUES (?, ?, 'super_admin', ?, 'create_lucky_instance', ?, 'success')
        ");
        $stmt->execute([
            $userId,
            'admin',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            json_encode([
                'instance_id' => $newInstanceId,
                'name' => $data['name'],
                'display_name' => $data['display_name']
            ])
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Lucky实例创建成功',
            'instance' => $newInstance
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("创建Lucky实例失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '创建Lucky实例失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 更新Lucky实例
 * 需求: 6.1, 6.2
 * 
 * @param PDO $pdo 数据库连接
 * @param int $instanceId 实例ID
 * @param array $data 更新数据
 * @param int $userId 操作用户ID
 * @return array 响应数组
 */
function updateLuckyInstance($pdo, $instanceId, $data, $userId) {
    try {
        // 验证实例ID
        if (!is_numeric($instanceId) || $instanceId <= 0) {
            return [
                'success' => false,
                'message' => '无效的实例ID'
            ];
        }
        
        // 检查实例是否存在
        $stmt = $pdo->prepare("SELECT * FROM lucky_instances WHERE id = ?");
        $stmt->execute([$instanceId]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$instance) {
            return [
                'success' => false,
                'message' => 'Lucky实例不存在'
            ];
        }
        
        // 构建更新字段
        $updateFields = [];
        $updateValues = [];
        
        // 验证并添加可更新的字段
        if (isset($data['display_name'])) {
            $validation = validateInstanceName($data['display_name']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            $updateFields[] = 'display_name = ?';
            $updateValues[] = $data['display_name'];
        }
        
        if (isset($data['description'])) {
            $updateFields[] = 'description = ?';
            $updateValues[] = $data['description'];
        }
        
        if (isset($data['thumbnail_url'])) {
            $validation = validateUrl($data['thumbnail_url']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => '缩略图URL格式无效'
                ];
            }
            $updateFields[] = 'thumbnail_url = ?';
            $updateValues[] = $data['thumbnail_url'];
        }
        
        if (isset($data['background_url'])) {
            $validation = validateUrl($data['background_url']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => '背景图URL格式无效'
                ];
            }
            $updateFields[] = 'background_url = ?';
            $updateValues[] = $data['background_url'];
        }
        
        if (isset($data['group_id'])) {
            if ($data['group_id'] !== null) {
                // 验证分组是否存在
                $stmt = $pdo->prepare("SELECT id FROM lucky_groups WHERE id = ?");
                $stmt->execute([$data['group_id']]);
                if (!$stmt->fetch()) {
                    return [
                        'success' => false,
                        'message' => '指定的分组不存在'
                    ];
                }
            }
            $updateFields[] = 'group_id = ?';
            $updateValues[] = $data['group_id'];
        }
        
        if (isset($data['sort_order'])) {
            $updateFields[] = 'sort_order = ?';
            $updateValues[] = $data['sort_order'];
        }
        
        if (isset($data['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $updateValues[] = $data['is_active'];
        }
        
        // 如果没有要更新的字段
        if (empty($updateFields)) {
            return [
                'success' => false,
                'message' => '没有要更新的字段'
            ];
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 执行更新
        $updateValues[] = $instanceId;
        $sql = "UPDATE lucky_instances SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // 获取更新后的实例
        $stmt = $pdo->prepare("SELECT * FROM lucky_instances WHERE id = ?");
        $stmt->execute([$instanceId]);
        $updatedInstance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 记录审计日志
        $stmt = $pdo->prepare("
            INSERT INTO security_logs 
            (user_id, username, user_type, ip_address, action, details, status)
            VALUES (?, ?, 'super_admin', ?, 'update_lucky_instance', ?, 'success')
        ");
        $stmt->execute([
            $userId,
            'admin',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            json_encode([
                'instance_id' => $instanceId,
                'updated_fields' => array_keys($data)
            ])
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Lucky实例更新成功',
            'instance' => $updatedInstance
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("更新Lucky实例失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '更新Lucky实例失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 删除Lucky实例
 * 需求: 7.1, 7.2
 * 
 * @param PDO $pdo 数据库连接
 * @param int $instanceId 实例ID
 * @param int $userId 操作用户ID
 * @return array 响应数组
 */
function deleteLuckyInstance($pdo, $instanceId, $userId) {
    try {
        // 验证实例ID
        if (!is_numeric($instanceId) || $instanceId <= 0) {
            return [
                'success' => false,
                'message' => '无效的实例ID'
            ];
        }
        
        // 检查实例是否存在
        $stmt = $pdo->prepare("SELECT * FROM lucky_instances WHERE id = ?");
        $stmt->execute([$instanceId]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$instance) {
            return [
                'success' => false,
                'message' => 'Lucky实例不存在'
            ];
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 删除关联的奖品（如果有lucky_id字段）
        $stmt = $pdo->prepare("DELETE FROM prizes WHERE lucky_id = ?");
        $stmt->execute([$instanceId]);
        $deletedPrizes = $stmt->rowCount();
        
        // 删除关联的价格配置（如果有lucky_id字段）
        $stmt = $pdo->prepare("DELETE FROM draw_prices WHERE lucky_id = ?");
        $stmt->execute([$instanceId]);
        $deletedPrices = $stmt->rowCount();
        
        // 删除Lucky实例
        $stmt = $pdo->prepare("DELETE FROM lucky_instances WHERE id = ?");
        $stmt->execute([$instanceId]);
        
        // 记录审计日志
        $stmt = $pdo->prepare("
            INSERT INTO security_logs 
            (user_id, username, user_type, ip_address, action, details, status)
            VALUES (?, ?, 'super_admin', ?, 'delete_lucky_instance', ?, 'success')
        ");
        $stmt->execute([
            $userId,
            'admin',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            json_encode([
                'instance_id' => $instanceId,
                'instance_name' => $instance['name'],
                'deleted_prizes' => $deletedPrizes,
                'deleted_prices' => $deletedPrices
            ])
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Lucky实例删除成功',
            'deleted_prizes' => $deletedPrizes,
            'deleted_prices' => $deletedPrices
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("删除Lucky实例失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '删除Lucky实例失败: ' . $e->getMessage()
        ];
    }
}

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 验证权限
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
if (!$authHeader && isset($input['user_id'])) {
    $authHeader = $input['user_id'];
}

$authResult = verifySuperAdmin($pdo, $authHeader);
if (!$authResult['success']) {
    http_response_code(403);
    echo json_encode($authResult);
    exit;
}

$userId = $authResult['user']['id'];

// 路由处理
switch ($method) {
    case 'POST':
        // 创建Lucky实例
        if (!isset($input['action']) || $input['action'] !== 'create') {
            echo json_encode([
                'success' => false,
                'message' => '缺少操作参数或操作类型无效'
            ]);
            break;
        }
        
        echo json_encode(createLuckyInstance($pdo, $input, $userId));
        break;
        
    case 'PUT':
        // 更新Lucky实例
        if (!isset($input['id'])) {
            echo json_encode([
                'success' => false,
                'message' => '缺少实例ID'
            ]);
            break;
        }
        
        echo json_encode(updateLuckyInstance($pdo, $input['id'], $input, $userId));
        break;
        
    case 'DELETE':
        // 删除Lucky实例
        if (!isset($input['id'])) {
            echo json_encode([
                'success' => false,
                'message' => '缺少实例ID'
            ]);
            break;
        }
        
        echo json_encode(deleteLuckyInstance($pdo, $input['id'], $userId));
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => '不支持的请求方法'
        ]);
}
?>
