<?php
// 开启错误显示（调试用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 先启动 session，再设置 header
session_start();

// 设置CORS头
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 检查登录状态
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '方法不被允许']);
    exit;
}

// 检查是否有文件上传
if (!isset($_FILES['avatar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '没有上传文件', 'debug' => $_FILES]);
    exit;
}

if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '文件大小超过 php.ini 中的限制',
        UPLOAD_ERR_FORM_SIZE => '文件大小超过表单中的限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => 'PHP 扩展阻止了文件上传'
    ];
    
    $errorMsg = isset($errorMessages[$_FILES['avatar']['error']]) 
        ? $errorMessages[$_FILES['avatar']['error']] 
        : '未知错误: ' . $_FILES['avatar']['error'];
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '上传失败: ' . $errorMsg]);
    exit;
}

$file = $_FILES['avatar'];

// 验证文件类型 - 使用多种方法
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// 获取文件扩展名
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// 检查扩展名
if (!in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '不支持的文件扩展名: ' . $extension . '，仅支持 jpg、png、gif、webp']);
    exit;
}

// 检查 MIME 类型（如果可用）
if (function_exists('mime_content_type')) {
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '不支持的文件类型: ' . $fileType]);
        exit;
    }
} else {
    // 如果 mime_content_type 不可用，只检查扩展名
    // 已经在上面检查过了
}

// 验证文件大小（最大2MB）
$maxSize = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '文件大小超过限制（最大2MB）']);
    exit;
}

// 生成唯一文件名
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;

// 确保上传目录存在
$uploadDir = __DIR__ . '/../../uploads/avatars/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '无法创建上传目录']);
        exit;
    }
}

$targetPath = $uploadDir . $fileName;

// 移动上传的文件
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '文件保存失败，请检查目录权限']);
    exit;
}

// 更新数据库中的头像路径
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $avatarUrl = 'uploads/avatars/' . $fileName;
    
    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$avatarUrl, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => '头像上传成功',
        'avatar_url' => $avatarUrl
    ]);
} catch (Exception $e) {
    // 如果数据库更新失败，删除已上传的文件
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '数据库更新失败: ' . $e->getMessage()]);
}
?>
