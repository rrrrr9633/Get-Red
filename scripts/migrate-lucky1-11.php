<?php
/**
 * Lucky1-11数据迁移脚本
 * 将旧的lucky1-11页面数据迁移到统一Lucky页面系统
 */

require_once __DIR__ . '/../server/config/database.php';

echo "========== Lucky1-11数据迁移 ==========\n\n";

$database = new Database();
$pdo = $database->getConnection();

// 定义lucky1-11的配置
$luckyPages = [
    1 => ['name' => 'lucky1', 'display_name' => '大红行动', 'description' => '抽取心爱的大红'],
    2 => ['name' => 'lucky2', 'display_name' => '幸运掉落2', 'description' => '神秘礼品等你来抽'],
    3 => ['name' => 'lucky3', 'display_name' => '幸运掉落3', 'description' => '运气决定一切'],
    4 => ['name' => 'lucky4', 'display_name' => '幸运掉落4', 'description' => '抽取心爱的大红'],
    5 => ['name' => 'lucky5', 'display_name' => '幸运掉落5', 'description' => '神秘礼品等你来抽'],
    6 => ['name' => 'lucky6', 'display_name' => '幸运掉落6', 'description' => '运气决定一切'],
    7 => ['name' => 'lucky7', 'display_name' => '幸运掉落7', 'description' => '抽取心爱的大红'],
    8 => ['name' => 'lucky8', 'display_name' => '幸运掉落8', 'description' => '神秘礼品等你来抽'],
    9 => ['name' => 'lucky9', 'display_name' => '幸运掉落9', 'description' => '运气决定一切'],
    10 => ['name' => 'lucky10', 'display_name' => '幸运掉落10', 'description' => '抽取心爱的大红'],
    11 => ['name' => 'lucky11', 'display_name' => '幸运掉落11', 'description' => '神秘礼品等你来抽']
];

try {
    $pdo->beginTransaction();
    
    foreach ($luckyPages as $id => $config) {
        echo "迁移 {$config['name']} ({$config['display_name']})...\n";
        
        // 1. 检查实例是否已存在
        $stmt = $pdo->prepare("SELECT id FROM lucky_instances WHERE name = ?");
        $stmt->execute([$config['name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "  ✓ 实例已存在，ID: {$existing['id']}\n";
            $instanceId = $existing['id'];
        } else {
            // 2. 创建Lucky实例（不指定ID，让数据库自动分配）
            $thumbnailUrl = "images/thumbs/{$config['name']}.png";
            $backgroundUrl = "images/shop/{$config['name']}.png";
            
            $stmt = $pdo->prepare("
                INSERT INTO lucky_instances 
                (name, display_name, description, thumbnail_url, background_url, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $config['name'],
                $config['display_name'],
                $config['description'],
                $thumbnailUrl,
                $backgroundUrl,
                $id
            ]);
            
            $instanceId = $pdo->lastInsertId();
            echo "  ✓ 实例创建成功，ID: $instanceId\n";
        }
        
        // 3. 迁移奖品数据
        $oldTableName = $config['name'] . '_prizes';
        
        // 检查旧表是否存在
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$oldTableName]);
        
        if ($stmt->fetch()) {
            // 检查是否已经迁移过
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM prizes WHERE lucky_id = ?");
            $stmt->execute([$instanceId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                echo "  ✓ 奖品已迁移 ({$result['count']} 个)\n";
            } else {
                // 迁移奖品数据
                $stmt = $pdo->prepare("
                    INSERT INTO prizes 
                    (lucky_id, name, icon, image_url, value, probability, rarity, quantity, active)
                    SELECT 
                        ? as lucky_id,
                        name,
                        icon,
                        image_url,
                        value,
                        probability,
                        rarity,
                        quantity,
                        active
                    FROM $oldTableName
                ");
                $stmt->execute([$instanceId]);
                $prizeCount = $stmt->rowCount();
                echo "  ✓ 迁移奖品: $prizeCount 个\n";
            }
        } else {
            echo "  ⚠ 旧奖品表不存在: $oldTableName\n";
        }
        
        // 4. 迁移价格配置
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM draw_prices WHERE lucky_id = ?");
        $stmt->execute([$instanceId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo "  ✓ 价格配置已存在 ({$result['count']} 个)\n";
        } else {
            // 从旧的draw_prices表迁移（使用page_name字段）
            $pageName = $config['name'] . '.html';
            
            $stmt = $pdo->prepare("
                SELECT price_type, price_value, button_name
                FROM draw_prices
                WHERE page_name = ?
            ");
            $stmt->execute([$pageName]);
            $oldPrices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($oldPrices) > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO draw_prices (lucky_id, price_type, price_value, button_name)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($oldPrices as $price) {
                    $stmt->execute([
                        $instanceId,
                        $price['price_type'],
                        $price['price_value'],
                        $price['button_name']
                    ]);
                }
                
                echo "  ✓ 迁移价格配置: " . count($oldPrices) . " 个\n";
            } else {
                // 使用默认价格
                $defaultPrices = [
                    ['price_type' => 'single', 'price_value' => 10.00, 'button_name' => '单抽'],
                    ['price_type' => 'triple', 'price_value' => 30.00, 'button_name' => '三连抽'],
                    ['price_type' => 'quintuple', 'price_value' => 50.00, 'button_name' => '五连抽']
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO draw_prices (lucky_id, price_type, price_value, button_name)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($defaultPrices as $price) {
                    $stmt->execute([
                        $instanceId,
                        $price['price_type'],
                        $price['price_value'],
                        $price['button_name']
                    ]);
                }
                
                echo "  ✓ 创建默认价格配置\n";
            }
        }
        
        echo "\n";
    }
    
    $pdo->commit();
    
    echo "========================================\n";
    echo "迁移完成！\n\n";
    
    // 验证迁移结果
    echo "验证迁移结果：\n";
    echo "-----------------------------------\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lucky_instances WHERE id BETWEEN 1 AND 11");
    $result = $stmt->fetch();
    echo "Lucky实例数量: {$result['count']}/11\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM prizes WHERE lucky_id BETWEEN 1 AND 11");
    $result = $stmt->fetch();
    echo "奖品总数: {$result['count']}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM draw_prices WHERE lucky_id BETWEEN 1 AND 11");
    $result = $stmt->fetch();
    echo "价格配置总数: {$result['count']}\n";
    
    echo "\n访问示例：\n";
    echo "  lucky1: pages/lucky.html?id=1\n";
    echo "  lucky2: pages/lucky.html?id=2\n";
    echo "  ...\n";
    echo "  lucky11: pages/lucky.html?id=11\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
?>
