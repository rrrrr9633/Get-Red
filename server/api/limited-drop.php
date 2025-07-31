<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_config':
        echo json_encode(getLimitedDropConfig($_GET['page'] ?? 'luckytemp'));
        break;
    case 'update_config':
        $input = json_decode(file_get_contents('php://input'), true);
        echo json_encode(updateLimitedDropConfig($input));
        break;
    case 'get_legendary_prizes':
        echo json_encode(getLegendaryPrizes($_GET['page'] ?? 'luckytemp'));
        break;
    case 'get_display_data':
        echo json_encode(getDisplayData($_GET['page'] ?? 'luckytemp'));
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        break;
}

function getLimitedDropConfig($page) {
    global $pdo;
    
    try {
        // 获取页面特定配置
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE ?");
        $stmt->execute(["limited_drop_%_{$page}"]);
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [
            'page_name' => $page,
            'is_active' => 0,
            'window_title' => '限时掉落，爆率提升',
            'rate_boost_from' => 0.1,
            'rate_boost_to' => 2.5,
            'selected_prize_ids' => []
        ];
        
        foreach ($settings as $setting) {
            switch ($setting['setting_key']) {
                case "limited_drop_enabled_{$page}":
                    $config['is_active'] = (int)$setting['setting_value'];
                    break;
                case "limited_drop_title_{$page}":
                    $config['window_title'] = $setting['setting_value'];
                    break;
                case "limited_drop_rate_from_{$page}":
                    $config['rate_boost_from'] = (float)$setting['setting_value'];
                    break;
                case "limited_drop_rate_to_{$page}":
                    $config['rate_boost_to'] = (float)$setting['setting_value'];
                    break;
                case "limited_drop_selected_prizes_{$page}":
                    $config['selected_prize_ids'] = json_decode($setting['setting_value'], true) ?: [];
                    break;
            }
        }
        
        return [
            'success' => true,
            'config' => $config
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取配置失败: ' . $e->getMessage()
        ];
    }
}

function updateLimitedDropConfig($data) {
    global $pdo;
    
    try {
        $page = $data['page'] ?? 'luckytemp';
        $isActive = $data['is_active'] ?? 0;
        $selectedPrizeIds = $data['selected_prize_ids'] ?? [];
        $windowTitle = $data['window_title'] ?? '限时掉落，爆率提升';
        $rateBoostFrom = $data['rate_boost_from'] ?? 0.1;
        $rateBoostTo = $data['rate_boost_to'] ?? 2.5;
        
        $selectedPrizeIdsJson = json_encode($selectedPrizeIds);
        
        // 分别更新每个配置项
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        // 更新启用状态
        $stmt->execute(["limited_drop_enabled_{$page}", $isActive]);
        
        // 更新标题
        $stmt->execute(["limited_drop_title_{$page}", $windowTitle]);
        
        // 更新爆率显示范围
        $stmt->execute(["limited_drop_rate_from_{$page}", $rateBoostFrom]);
        $stmt->execute(["limited_drop_rate_to_{$page}", $rateBoostTo]);
        
        // 更新选中的奖品
        $stmt->execute(["limited_drop_selected_prizes_{$page}", $selectedPrizeIdsJson]);
        
        return [
            'success' => true,
            'message' => '配置更新成功'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '更新配置失败: ' . $e->getMessage()
        ];
    }
}

function getLegendaryPrizes($page) {
    global $pdo;
    
    try {
        // 动态确定奖品表名
        $tableName = 'prizes'; // 默认表名
        if ($page !== 'luckytemp') {
            // 对于非模板页面，使用页面名_prizes格式
            $tableName = $page . '_prizes';
        }
        
        // 首先检查表是否存在
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            return [
                'success' => false,
                'message' => "奖品表 {$tableName} 不存在，请先创建该页面"
            ];
        }
        
        // 获取传说级奖品
        $sql = "
            SELECT id, name, icon, image_url, value, quantity, rarity, probability 
            FROM `{$tableName}` 
            WHERE rarity = 'legendary' AND active = 1 
            ORDER BY name ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'table_name' => $tableName,
            'prizes' => $prizes
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取传说奖品失败: ' . $e->getMessage()
        ];
    }
}

function getDisplayData($page) {
    global $pdo;
    
    try {
        // 获取配置
        $configResult = getLimitedDropConfig($page);
        if (!$configResult['success']) {
            return $configResult;
        }
        
        $config = $configResult['config'];
        
        // 如果未启用，返回空数据
        if (!$config['is_active']) {
            return [
                'success' => true,
                'is_active' => false,
                'window_title' => $config['window_title'],
                'prizes' => []
            ];
        }
        
        // 获取选中的奖品详情
        $selectedPrizeIds = $config['selected_prize_ids'];
        if (empty($selectedPrizeIds)) {
            return [
                'success' => true,
                'is_active' => true,
                'window_title' => $config['window_title'],
                'prizes' => []
            ];
        }
        
        // 动态确定奖品表名
        $tableName = 'prizes'; // 默认表名
        if ($page !== 'luckytemp') {
            $tableName = $page . '_prizes';
        }
        
        // 检查表是否存在
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            return [
                'success' => false,
                'message' => "奖品表 {$tableName} 不存在"
            ];
        }
        
        // 构建查询
        $placeholders = str_repeat('?,', count($selectedPrizeIds) - 1) . '?';
        $orderPlaceholders = str_repeat('?,', count($selectedPrizeIds) - 1) . '?';
        
        $sql = "
            SELECT id, name, icon, image_url, value, quantity, rarity, probability 
            FROM `{$tableName}` 
            WHERE id IN ({$placeholders}) AND rarity = 'legendary' AND active = 1 
            ORDER BY FIELD(id, {$orderPlaceholders})
        ";
        
        $stmt = $pdo->prepare($sql);
        
        // 执行查询，参数需要重复一次用于ORDER BY
        $params = array_merge($selectedPrizeIds, $selectedPrizeIds);
        $stmt->execute($params);
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 为每个奖品添加虚假的爆率提升信息
        foreach ($prizes as &$prize) {
            // 使用配置的爆率显示值
            $prize['original_rate'] = number_format($config['rate_boost_from'], 3);
            $prize['boosted_rate'] = number_format($config['rate_boost_to'], 3);
            $prize['table_name'] = $tableName; // 添加表名信息用于调试
        }
        
        return [
            'success' => true,
            'is_active' => true,
            'window_title' => $config['window_title'],
            'table_name' => $tableName,
            'prizes' => $prizes
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取展示数据失败: ' . $e->getMessage()
        ];
    }
}
?>
