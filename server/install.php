<?php
// 数据库安装脚本
require_once 'config/database.php';

echo "<h2>数据库安装向导</h2>";

try {
    // 尝试连接数据库
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p style='color: green;'>✓ 数据库连接成功</p>";
        
        // 读取SQL文件并执行
        $sql = file_get_contents('database.sql');
        $statements = explode(';', $sql);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $db->exec($statement);
                    $success_count++;
                } catch (PDOException $e) {
                    $error_count++;
                    echo "<p style='color: orange;'>警告: " . $e->getMessage() . "</p>";
                }
            }
        }
        
        echo "<p style='color: green;'>✓ 数据库安装完成</p>";
        echo "<p>成功执行: {$success_count} 条语句</p>";
        if ($error_count > 0) {
            echo "<p>警告: {$error_count} 条语句执行时有警告（可能是因为表已存在）</p>";
        }
        
        echo "<h3>安装完成！</h3>";
        echo "<p><a href='../index.html'>返回首页</a></p>";
        
    } else {
        echo "<p style='color: red;'>✗ 数据库连接失败</p>";
        echo "<p>请检查 config/database.php 中的数据库配置</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 安装失败: " . $e->getMessage() . "</p>";
    echo "<h3>解决方案：</h3>";
    echo "<ol>";
    echo "<li>确保MySQL服务已启动</li>";
    echo "<li>检查数据库连接配置（config/database.php）</li>";
    echo "<li>确保数据库用户有足够的权限</li>";
    echo "<li>手动创建数据库：CREATE DATABASE lucky_draw;</li>";
    echo "</ol>";
}
?>
