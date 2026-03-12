<?php
/**
 * 客服配置调试脚本
 * 用于检查数据库中的客服配置数据
 */

require_once 'server/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>客服配置调试</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
    th { background: #4CAF50; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #f44336; font-weight: bold; }
    .warning { color: #ff9800; font-weight: bold; }
    img { max-width: 200px; border: 2px solid #ddd; border-radius: 4px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<div class='section'>";
    echo "<h2>1. 数据库连接</h2>";
    echo "<p class='success'>✓ 数据库连接成功</p>";
    echo "</div>";
    
    // 检查表是否存在
    echo "<div class='section'>";
    echo "<h2>2. 检查表结构</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'customer_service_config'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p class='success'>✓ customer_service_config 表存在</p>";
        
        // 显示表结构
        echo "<h3>表结构：</h3>";
        $stmt = $db->query("DESCRIBE customer_service_config");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ customer_service_config 表不存在</p>";
    }
    echo "</div>";
    
    // 查询配置数据
    echo "<div class='section'>";
    echo "<h2>3. 配置数据</h2>";
    
    $stmt = $db->query("SELECT * FROM customer_service_config ORDER BY sort_order");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($configs) > 0) {
        echo "<p class='success'>✓ 找到 " . count($configs) . " 条配置记录</p>";
        
        echo "<table>";
        echo "<tr><th>ID</th><th>类型</th><th>标题</th><th>联系方式</th><th>二维码URL</th><th>启用状态</th><th>排序</th></tr>";
        foreach ($configs as $config) {
            echo "<tr>";
            echo "<td>{$config['id']}</td>";
            echo "<td>{$config['service_type']}</td>";
            echo "<td>{$config['title']}</td>";
            echo "<td>" . ($config['contact_info'] ?: '<span class="warning">未设置</span>') . "</td>";
            echo "<td>" . ($config['qr_code_url'] ?: '<span class="warning">未设置</span>') . "</td>";
            echo "<td>" . ($config['is_enabled'] ? '<span class="success">启用</span>' : '<span class="error">禁用</span>') . "</td>";
            echo "<td>{$config['sort_order']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 显示详细信息
        echo "<h3>详细配置：</h3>";
        foreach ($configs as $config) {
            echo "<div style='margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #4CAF50;'>";
            echo "<h4>{$config['service_type']} - {$config['title']}</h4>";
            echo "<p><strong>描述：</strong>" . ($config['content'] ?: '无') . "</p>";
            echo "<p><strong>联系方式：</strong>" . ($config['contact_info'] ?: '未设置') . "</p>";
            echo "<p><strong>二维码URL：</strong>" . ($config['qr_code_url'] ?: '未设置') . "</p>";
            
            if ($config['qr_code_url']) {
                echo "<p><strong>二维码预览：</strong></p>";
                $qrPath = $config['qr_code_url'];
                // 如果是相对路径，尝试显示
                if (!preg_match('/^https?:\/\//', $qrPath)) {
                    echo "<img src='{$qrPath}' alt='二维码' onerror='this.style.display=\"none\"; this.nextElementSibling.style.display=\"block\"'>";
                    echo "<p style='display:none; color: #f44336;'>图片加载失败，路径可能不正确</p>";
                    echo "<p><small>完整路径：" . realpath($qrPath) . "</small></p>";
                } else {
                    echo "<img src='{$qrPath}' alt='二维码'>";
                }
            }
            
            echo "<p><strong>创建时间：</strong>{$config['created_at']}</p>";
            echo "<p><strong>更新时间：</strong>{$config['updated_at']}</p>";
            echo "</div>";
        }
        
        // JSON格式输出
        echo "<h3>JSON格式（API返回格式）：</h3>";
        echo "<pre>" . json_encode(['success' => true, 'configs' => $configs], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
    } else {
        echo "<p class='warning'>⚠ 没有找到配置记录</p>";
        echo "<p>请在管理后台的配置页面添加QQ和微信客服配置。</p>";
    }
    echo "</div>";
    
    // 测试API调用
    echo "<div class='section'>";
    echo "<h2>4. 测试API调用</h2>";
    echo "<p>在浏览器控制台中运行以下代码测试：</p>";
    echo "<pre>
fetch('./server/api/customer-service.php?action=config')
    .then(res => res.json())
    .then(data => console.log('API响应:', data))
    .catch(err => console.error('API错误:', err));
</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'>✗ 错误: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>5. 解决方案</h2>";
echo "<p>如果配置数据为空或不正确，请按以下步骤操作：</p>";
echo "<ol>";
echo "<li>登录超级管理员账号</li>";
echo "<li>进入管理后台 → 系统配置</li>";
echo "<li>配置QQ客服和微信客服信息</li>";
echo "<li>上传二维码图片</li>";
echo "<li>确保配置状态为"启用"</li>";
echo "<li>刷新此页面验证配置是否保存成功</li>";
echo "</ol>";
echo "</div>";
?>
