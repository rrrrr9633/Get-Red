<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

// 获取数据库连接
try {
    $db = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

// 初始化表结构
initializeTables($db);

// 获取请求参数
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// 路由处理
switch ($action) {
    case 'get_coin_ratio':
        getCoinRatio($db);
        break;
    case 'save_coin_ratio':
        saveCoinRatio($db, $input);
        break;
    case 'get_recharge_options':
        getRechargeOptions($db);
        break;
    case 'add_recharge_option':
        addRechargeOption($db, $input);
        break;
    case 'delete_recharge_option':
        deleteRechargeOption($db, $input);
        break;
    case 'get_payment_settings':
        getPaymentSettings($db);
        break;
    case 'save_payment_settings':
        savePaymentSettings($db, $input);
        break;
    case 'get_recharge_history':
        getRechargeHistory($db);
        break;
    case 'get_user_recharge_history':
        getUserRechargeHistory($db, $input);
        break;
    case 'create_recharge_order':
        createRechargeOrder($db, $input);
        break;
    case 'get_user_recharge_options':
        getUserRechargeOptions($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => '无效的操作']);
        break;
}

// 初始化数据表
function initializeTables($db) {
    try {
        // 充值选项表
        $db->exec("
            CREATE TABLE IF NOT EXISTS recharge_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                amount DECIMAL(10,2) NOT NULL,
                bonus_coins INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 充值记录表
        $db->exec("
            CREATE TABLE IF NOT EXISTS recharge_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                coins_gained INT NOT NULL,
                payment_method VARCHAR(20) NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                order_no VARCHAR(50) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // 系统设置表
        $db->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // 插入默认充值选项（如果不存在）
        $stmt = $db->query("SELECT COUNT(*) FROM recharge_options");
        if ($stmt->fetchColumn() == 0) {
            $defaultOptions = [
                [6, 0], [30, 0], [68, 0], [128, 0], [324, 0], [648, 0]
            ];
            $stmt = $db->prepare("INSERT INTO recharge_options (amount, bonus_coins) VALUES (?, ?)");
            foreach ($defaultOptions as $option) {
                $stmt->execute($option);
            }
        }

        // 插入默认金币比例（如果不存在）
        $stmt = $db->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('coin_ratio', '10')");
        $stmt->execute();

    } catch (Exception $e) {
        error_log("初始化数据表失败: " . $e->getMessage());
    }
}

// 获取金币比例
function getCoinRatio($db) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio'");
        $stmt->execute();
        $ratio = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'ratio' => $ratio ? intval($ratio) : 10
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 保存金币比例
function saveCoinRatio($db, $input) {
    try {
        $ratio = $input['ratio'] ?? 10;
        
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('coin_ratio', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$ratio, $ratio]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取充值选项
function getRechargeOptions($db) {
    try {
        $stmt = $db->query("SELECT * FROM recharge_options ORDER BY amount ASC");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'options' => $options
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 添加充值选项
function addRechargeOption($db, $input) {
    try {
        $amount = $input['amount'] ?? 0;
        $bonusCoins = $input['bonus_coins'] ?? 0;
        
        if ($amount <= 0) {
            throw new Exception('充值金额必须大于0');
        }

        // 获取金币比例设置
        $ratioStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio'");
        $ratioStmt->execute();
        $ratio = $ratioStmt->fetch(PDO::FETCH_ASSOC);
        $coinRatio = ($ratio && $ratio['setting_value']) ? intval($ratio['setting_value']) : 10;
        
        // 计算基础金币奖励
        $coinsReward = $amount * $coinRatio;
        
        $stmt = $db->prepare("INSERT INTO recharge_options (amount, coins_reward, bonus_coins) VALUES (?, ?, ?)");
        $stmt->execute([$amount, $coinsReward, $bonusCoins]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 删除充值选项
function deleteRechargeOption($db, $input) {
    try {
        $id = $input['id'] ?? 0;
        
        if ($id <= 0) {
            throw new Exception('无效的选项ID');
        }
        
        $stmt = $db->prepare("DELETE FROM recharge_options WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取支付设置
function getPaymentSettings($db) {
    try {
        $settings = [];
        $paymentKeys = [
            'alipay_app_id', 'alipay_private_key', 'alipay_public_key',
            'wechat_app_id', 'wechat_mch_id', 'wechat_api_key'
        ];
        
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (" . 
                           str_repeat('?,', count($paymentKeys) - 1) . "?)");
        $stmt->execute($paymentKeys);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 保存支付设置
function savePaymentSettings($db, $input) {
    try {
        $settings = $input['settings'] ?? [];
        
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取充值记录
function getRechargeHistory($db) {
    try {
        global $input;
        $search = $input['search'] ?? '';
        
        $sql = "
            SELECT rh.*, u.username 
            FROM recharge_history rh 
            LEFT JOIN users u ON rh.user_id = u.id 
        ";
        
        $params = [];
        if (!empty($search)) {
            $sql .= " WHERE u.username LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY rh.created_at DESC LIMIT 100";
        
        if (empty($params)) {
            $stmt = $db->query($sql);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取用户充值记录
function getUserRechargeHistory($db, $input) {
    session_start();
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception('用户未登录');
        }
        
        $page = max(1, intval($input['page'] ?? 1));
        $limit = min(50, max(5, intval($input['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM recharge_history WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 获取充值记录
        $sql = "
            SELECT rh.*, u.username 
            FROM recharge_history rh 
            LEFT JOIN users u ON rh.user_id = u.id 
            WHERE rh.user_id = ?
            ORDER BY rh.created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'history' => $history,
            'total' => $total,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 创建充值订单
function createRechargeOrder($db, $input) {
    session_start();
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception('用户未登录');
        }
        
        $optionId = $input['option_id'] ?? 0;
        $paymentMethod = $input['payment_method'] ?? '';
        
        if (!in_array($paymentMethod, ['alipay', 'wechat'])) {
            throw new Exception('无效的支付方式');
        }
        
        // 获取充值选项
        $stmt = $db->prepare("SELECT * FROM recharge_options WHERE id = ?");
        $stmt->execute([$optionId]);
        $option = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$option) {
            throw new Exception('充值选项不存在');
        }
        
        // 获取金币比例
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio'");
        $stmt->execute();
        $coinRatio = intval($stmt->fetchColumn() ?: 10);
        
        // 计算获得的金币
        $coinsGained = ($option['amount'] * $coinRatio) + $option['bonus_coins'];
        
        // 生成订单号
        $orderNo = 'RCH' . date('YmdHis') . rand(1000, 9999);
        
        // 创建充值记录
        $stmt = $db->prepare("
            INSERT INTO recharge_history (user_id, amount, coins_gained, payment_method, transaction_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $option['amount'], $coinsGained, $paymentMethod, $orderNo]);
        
        // 返回支付信息（这里是模拟的，实际应该调用支付接口）
        echo json_encode([
            'success' => true,
            'transaction_id' => $orderNo,
            'amount' => $option['amount'],
            'coins_gained' => $coinsGained,
            'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=pay_{$orderNo}",
            'message' => "请使用{$paymentMethod}扫码支付 ¥{$option['amount']}"
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取用户充值选项（前端页面使用）
function getUserRechargeOptions($db) {
    try {
        // 获取充值选项
        $stmt = $db->query("SELECT * FROM recharge_options ORDER BY amount ASC");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取金币比例
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio'");
        $stmt->execute();
        $coinRatio = intval($stmt->fetchColumn() ?: 10);
        
        // 计算每个选项的总金币数
        foreach ($options as &$option) {
            $option['total_coins'] = ($option['amount'] * $coinRatio) + $option['bonus_coins'];
        }
        
        echo json_encode([
            'success' => true,
            'options' => $options,
            'coin_ratio' => $coinRatio
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
