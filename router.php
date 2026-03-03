<?php
/**
 * PHP内置服务器路由文件
 * 用于保护上传目录，防止PHP文件被执行
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// 定义受保护的目录
$protectedDirs = [
    '/uploads/',
    '/images/',
];

// 检查请求是否访问受保护目录中的PHP文件
foreach ($protectedDirs as $dir) {
    if (strpos($requestPath, $dir) === 0) {
        // 检查是否是PHP文件
        if (preg_match('/\.(php|phtml|php3|php4|php5|phar)$/i', $requestPath)) {
            // 禁止执行，返回403
            http_response_code(403);
            header('Content-Type: text/plain');
            echo "403 Forbidden - PHP execution is not allowed in this directory for security reasons.";
            exit;
        }
        
        // 检查是否是图片文件
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $requestPath)) {
            // 允许访问图片
            return false; // 让PHP内置服务器处理
        }
        
        // 其他文件类型也禁止访问
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "403 Forbidden - Only image files are allowed in this directory.";
        exit;
    }
}

// 对于其他请求，让PHP内置服务器正常处理
return false;
?>
