<?php
require_once 'config/database.php';

echo "=== 数据库连接测试 ===\n";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        die("数据库连接失败\n");
    }
    
    echo "✅ 数据库连接成功\n\n";
    
    // 测试用户表
    echo "=== 用户表测试 ===\n";
    $stmt = $pdo->query("SELECT id, username, nickname, balance FROM users LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "用户ID: {$row['id']}, 用户名: {$row['username']}, 昵称: {$row['nickname']}, 余额: {$row['balance']}\n";
    }
    
    // 测试奖品表
    echo "\n=== 奖品表测试 ===\n";
    $stmt = $pdo->query("SELECT id, name, value, rarity FROM prizes WHERE game_type = 'lucky_drop' LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "奖品ID: {$row['id']}, 名称: {$row['name']}, 价值: {$row['value']}, 稀有度: {$row['rarity']}\n";
    }
    
    // 测试用户物品表
    echo "\n=== 用户物品表测试 ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_items");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "用户物品总数: {$count['count']}\n";
    
    $stmt = $pdo->query("SELECT * FROM user_items ORDER BY obtained_at DESC LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "物品ID: {$row['id']}, 用户ID: {$row['user_id']}, 物品: {$row['name']}, 价值: {$row['value']}, 已分解: " . ($row['decomposed'] ? '是' : '否') . "\n";
    }
    
    // 测试抽奖记录
    echo "\n=== 抽奖记录测试 ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lottery_records");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "抽奖记录总数: {$count['count']}\n";
    
    $stmt = $pdo->query("SELECT * FROM lottery_records ORDER BY created_at DESC LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "记录ID: {$row['id']}, 用户ID: {$row['user_id']}, 游戏类型: {$row['game_type']}, 花费: {$row['cost']}, 奖励: {$row['reward']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}
?>
