<?php
/**
 * Lucky实例配置API
 * 
 * 功能：获取指定Lucky实例的配置信息
 * 
 * GET参数：
 * - id: Lucky实例ID（默认值：1）
 * 
 * 返回格式：
 * {
 *   "success": true,
 *   "instance": {
 *     "id": 1,
 *     "name": "lucky1",
 *     "display_name": "零号大坝(普通)",
 *     "description": "零号大坝危机四伏",
 *     "thumbnail_url": "images/thumbs/lucky1.png",
 *     "background_url": "images/shop/lucky1.png",
 *     "group_id": 1,
 *     "sort_order": 1,
 *     "is_active": 1,
 *     "created_at": "2024-01-01 00:00:00",
 *     "updated_at": "2024-01-01 00:00:00"
 *   }
 * }
 * 
 * 错误响应：
 * - 400: 无效的ID参数
 * - 404: Lucky实例不存在
 * - 403: Lucky实例未激活
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

/**
 * 获取Lucky实例配置
 * 
 * @param PDO $pdo 数据库连接
 * @param int $id Lucky实例ID
 * @return array 响应数组
 */
function getLuckyInstance($pdo, $id) {
    try {
        // 验证ID为正整数
        if (!is_numeric($id) || $id <= 0 || $id != intval($id)) {
            return [
                'success' => false,
                'message' => '无效的Lucky实例ID，ID必须为正整数',
                'error_code' => 'INVALID_ID'
            ];
        }
        
        $id = intval($id);
        
        // 查询Lucky实例配置
        $stmt = $pdo->prepare("
            SELECT 
                id,
                name,
                display_name,
                description,
                thumbnail_url,
                background_url,
                group_id,
                sort_order,
                is_active,
                created_at,
                updated_at
            FROM lucky_instances
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 检查实例是否存在
        if (!$instance) {
            return [
                'success' => false,
                'message' => '该Lucky实例不存在',
                'error_code' => 'INSTANCE_NOT_FOUND'
            ];
        }
        
        // 检查实例是否激活
        if ($instance['is_active'] != 1) {
            return [
                'success' => false,
                'message' => '该Lucky实例未激活，无法访问',
                'error_code' => 'INSTANCE_INACTIVE'
            ];
        }
        
        // 返回成功响应
        return [
            'success' => true,
            'instance' => $instance
        ];
        
    } catch (Exception $e) {
        error_log("获取Lucky实例配置失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '获取Lucky实例配置失败，请稍后重试',
            'error_code' => 'SERVER_ERROR'
        ];
    }
}

// 处理GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 获取ID参数，默认值为1
    $id = isset($_GET['id']) ? $_GET['id'] : 1;
    
    // 调用函数并返回结果
    $response = getLuckyInstance($pdo, $id);
    
    // 设置HTTP状态码
    if (!$response['success']) {
        if (isset($response['error_code'])) {
            switch ($response['error_code']) {
                case 'INVALID_ID':
                    http_response_code(400); // Bad Request
                    break;
                case 'INSTANCE_NOT_FOUND':
                    http_response_code(404); // Not Found
                    break;
                case 'INSTANCE_INACTIVE':
                    http_response_code(403); // Forbidden
                    break;
                default:
                    http_response_code(500); // Internal Server Error
            }
        } else {
            http_response_code(500);
        }
    }
    
    echo json_encode($response);
} else {
    // 不支持的请求方法
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '不支持的请求方法',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
}
?>
