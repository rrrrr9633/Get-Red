<?php
// 数据库安装脚本 - 使用socket连接
echo "<h2>数据库安装向导 (Socket连接版)</h2>";

try {
    // 尝试socket连接
    $dsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=lucky_draw;charset=utf8mb4";
    $db = new PDO($dsn, 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ 数据库连接成功 (Socket方式)</p>";
    
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
    
    // 检查表是否创建成功
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h4>已创建的表：</h4>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>{$table}</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 安装失败: " . $e->getMessage() . "</p>";
    echo "<h3>解决方案：</h3>";
    echo "<ol>";
    echo "<li>确保MySQL服务已启动：sudo systemctl start mysql</li>";
    echo "<li>确保数据库已创建：sudo mysql -e \"CREATE DATABASE lucky_draw;\"</li>";
    echo "<li>确保PHP有访问socket的权限</li>";
    echo "<li>检查socket文件是否存在：ls -la /var/run/mysqld/mysqld.sock</li>";
    echo "</ol>";
    
    // 尝试使用TCP连接作为备选方案
    echo "<h4>尝试TCP连接...</h4>";
    try {
        $dsn_tcp = "mysql:host=localhost;dbname=lucky_draw;charset=utf8mb4";
        $db_tcp = new PDO($dsn_tcp, 'root', '27797chenge');
        echo "<p style='color: green;'>✓ TCP连接成功！请使用原配置文件</p>";
    } catch (PDOException $tcp_e) {
        echo "<p style='color: red;'>✗ TCP连接也失败: " . $tcp_e->getMessage() . "</p>";
    }
}
?>
