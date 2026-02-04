<?php
// 开启错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'php_version' => phpversion(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'file_uploads' => ini_get('file_uploads'),
    'temp_dir' => sys_get_temp_dir(),
    'current_dir' => __DIR__,
    'uploads_dir' => __DIR__ . '/../../uploads/avatars/',
    'uploads_exists' => file_exists(__DIR__ . '/../../uploads/avatars/'),
    'uploads_writable' => is_writable(__DIR__ . '/../../uploads/'),
    'database_file' => file_exists(__DIR__ . '/../config/database.php')
]);
?>
