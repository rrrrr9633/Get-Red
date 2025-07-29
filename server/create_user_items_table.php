<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        die("数据库连接失败");
    }
    
    // 创建用户物品表
    $sql = "CREATE TABLE IF NOT EXISTS user_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        prize_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        icon VARCHAR(20) NOT NULL,
        image_url VARCHAR(255),
        value DECIMAL(10,2) NOT NULL,
        rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
        obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        decomposed BOOLEAN DEFAULT FALSE,
        decomposed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "用户物品表创建成功！\n";
    
    // 验证表结构
    $stmt = $pdo->query("SHOW TABLES");
    echo "数据库中的表：\n";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
    
    // 检查用户物品表结构
    echo "\n用户物品表结构：\n";
    $stmt = $pdo->query("DESCRIBE user_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
