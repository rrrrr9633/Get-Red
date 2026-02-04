<?php
// 只在直接访问时设置 headers 和处理请求
if (!defined('INCLUDED_FROM_PRIZES')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

require_once '../config/database.php';

// 如果 $database 和 $pdo 还未定义，则初始化
if (!isset($pdo)) {
    $database = new Database();
    $pdo = $database->getConnection();
}

// 函数定义（总是可用）
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

// 切换限时掉落状态
function toggleLimitedDrop($data) {
    global $pdo;
    
    try {
        $page = $data['page'] ?? 'luckytemp';
        $isActive = $data['is_active'] ?? 0;
        
        // 如果是开启限时掉落，清空上次的中奖记录
        if ($isActive) {
            clearWinners($page);
        }
        
        // 更新启用状态
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute(["limited_drop_enabled_{$page}", $isActive]);
        
        return [
            'success' => true,
            'message' => $isActive ? '限时掉落已开启' : '限时掉落已关闭'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '切换状态失败: ' . $e->getMessage()
        ];
    }
}

// 清空中奖记录
function clearWinners($page) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->execute(["limited_drop_winners_{$page}"]);
        return true;
    } catch (Exception $e) {
        error_log("清空中奖记录失败: " . $e->getMessage());
        return false;
    }
}

// 获取中奖用户列表
function getWinners($page) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(["limited_drop_winners_{$page}"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $winners = [];
        if ($result && $result['setting_value']) {
            $winners = json_decode($result['setting_value'], true) ?: [];
        }
        
        return [
            'success' => true,
            'winners' => $winners
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取中奖记录失败: ' . $e->getMessage()
        ];
    }
}

// 记录中奖用户（由draws.php调用）
function recordWinner($page, $userId, $username, $prizeName, $prizeValue) {
    global $pdo;
    
    try {
        // 获取现有记录
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(["limited_drop_winners_{$page}"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $winners = [];
        if ($result && $result['setting_value']) {
            $winners = json_decode($result['setting_value'], true) ?: [];
        }
        
        // 添加新记录
        $winners[] = [
            'user_id' => $userId,
            'username' => $username,
            'prize_name' => $prizeName,
            'prize_value' => $prizeValue,
            'win_time' => date('Y-m-d H:i:s')
        ];
        
        // 保存记录
        $winnersJson = json_encode($winners);
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute(["limited_drop_winners_{$page}", $winnersJson]);
        
        return true;
    } catch (Exception $e) {
        error_log("记录中奖用户失败: " . $e->getMessage());
        return false;
    }
}

// 只有在直接访问此文件时才执行请求处理逻辑
if (!defined('INCLUDED_FROM_PRIZES')) {
    $action = $_GET['action'] ?? '';

    // 处理POST请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'toggle_limited_drop':
                echo json_encode(toggleLimitedDrop($input));
                break;
            case 'update_config':
                echo json_encode(updateLimitedDropConfig($input));
                break;
            default:
                echo json_encode(['success' => false, 'message' => '无效的操作']);
                break;
        }
        exit;
    }

    switch ($action) {
        case 'get_config':
            echo json_encode(getLimitedDropConfig($_GET['page'] ?? 'luckytemp'));
            break;
        case 'get_legendary_prizes':
            echo json_encode(getLegendaryPrizes($_GET['page'] ?? 'luckytemp'));
            break;
        case 'get_display_data':
            echo json_encode(getDisplayData($_GET['page'] ?? 'luckytemp'));
            break;
        case 'get_winners':
            echo json_encode(getWinners($_GET['page'] ?? 'luckytemp'));
            break;
        default:
            echo json_encode(['success' => false, 'message' => '无效的操作']);
            break;
    }
}
?>
