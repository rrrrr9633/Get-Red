<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'users':
        getUsers();
        break;
    case 'prizes':
        getPrizes();
        break;
    case 'draws':
        getDraws();
        break;
    case 'stats':
        getStats();
        break;
    case 'add_user':
        addUser();
        break;
    case 'update_user':
        updateUser();
        break;
    case 'user_items':
        getUserItems();
        break;
    case 'add_prize':
        addPrize();
        break;
    case 'get_prize':
        getPrize();
        break;
    case 'update_prize':
        updatePrize();
        break;
    case 'toggle_prize':
        togglePrize();
        break;
    case 'delete_user':
        deleteUser();
        break;
    case 'delete_prize':
        deletePrize();
        break;
    case 'list_lucky_pages':
        listLuckyPages();
        break;
    case 'create_lucky_page':
        createLuckyPage();
        break;
    case 'rename_lucky_page':
        renameLuckyPage();
        break;
    case 'delete_lucky_page':
        deleteLuckyPage();
        break;
    case 'delete_user_item':
        deleteUserItem();
        break;
    case 'user_details':
        getUserDetails();
        break;
    case 'user_draws':
        getUserDraws();
        break;
    case 'user_transactions':
        getUserTransactions();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
}

function getUsers() {
    global $db;
    
    try {
        // 首先清理超时的在线状态（超过5分钟无活动的用户标记为离线）
        $db->query("UPDATE users SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        
        // 获取用户列表，包含在线状态信息
        $stmt = $db->query("
            SELECT 
                id, 
                username, 
                nickname, 
                balance, 
                is_online,
                last_login,
                last_activity,
                created_at,
                updated_at
            FROM users 
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理显示格式
        foreach ($users as &$user) {
            $user['last_login_formatted'] = $user['last_login'] ? 
                date('Y-m-d H:i:s', strtotime($user['last_login'])) : '从未登录';
            $user['last_activity_formatted'] = $user['last_activity'] ? 
                date('Y-m-d H:i:s', strtotime($user['last_activity'])) : '无活动记录';
            $user['online_status'] = $user['is_online'] ? '在线' : '离线';
        }
        
        // 获取用户统计
        $totalUsers = count($users);
        
        // 获取在线用户数
        $stmt = $db->query("SELECT COUNT(*) as online_count FROM users WHERE is_online = 1");
        $onlineCount = $stmt->fetch(PDO::FETCH_ASSOC)['online_count'];
        
        // 获取今日新增用户
        $stmt = $db->query("SELECT COUNT(*) as today_new FROM users WHERE DATE(created_at) = CURDATE()");
        $todayNew = $stmt->fetch(PDO::FETCH_ASSOC)['today_new'];
        
        echo json_encode([
            'success' => true, 
            'users' => $users,
            'stats' => [
                'total' => $totalUsers,
                'online' => $onlineCount,
                'today_new' => $todayNew
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取用户列表失败: ' . $e->getMessage()]);
    }
}

function getPrizes() {
    global $db;
    
    try {
        // 获取page参数，决定查询哪个表
        $page = $_GET['page'] ?? 'lucky1.html';
        $tableName = str_replace('.html', '_prizes', $page);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        
        if ($result->rowCount() == 0) {
            // 如果表不存在，使用默认的prizes表
            $tableName = 'prizes';
        }
        
        $stmt = $db->query("SELECT * FROM `{$tableName}` ORDER BY probability DESC");
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'prizes' => $prizes, 'table' => $tableName]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取奖品列表失败: ' . $e->getMessage()]);
    }
}

function getDraws() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT l.*, u.username 
            FROM lottery_records l 
            JOIN users u ON l.user_id = u.id 
            ORDER BY l.created_at DESC 
            LIMIT 100
        ");
        $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'draws' => $draws]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取抽奖记录失败: ' . $e->getMessage()]);
    }
}

function getStats() {
    global $db;
    
    try {
        // 总用户数
        $stmt = $db->query("SELECT COUNT(*) as total_users FROM users");
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
        
        // 总抽奖次数
        $stmt = $db->query("SELECT COUNT(*) as total_draws FROM lottery_records");
        $totalDraws = $stmt->fetch(PDO::FETCH_ASSOC)['total_draws'];
        
        // 总收入
        $stmt = $db->query("SELECT SUM(cost) as total_income FROM lottery_records");
        $totalIncome = $stmt->fetch(PDO::FETCH_ASSOC)['total_income'] ?: 0;
        
        // 总支出
        $stmt = $db->query("SELECT SUM(reward) as total_payout FROM lottery_records");
        $totalPayout = $stmt->fetch(PDO::FETCH_ASSOC)['total_payout'] ?: 0;
        
        $stats = [
            'total_users' => $totalUsers,
            'total_draws' => $totalDraws,
            'total_income' => $totalIncome,
            'total_payout' => $totalPayout
        ];
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取统计数据失败: ' . $e->getMessage()]);
    }
}

function addUser() {
    global $db, $input;
    
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['nickname'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        // 检查用户名是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$input['username']]);
        
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '用户名已存在']);
            return;
        }
        
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        $balance = $input['balance'] ?? 1000;
        
        $stmt = $db->prepare("INSERT INTO users (username, password, nickname, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$input['username'], $hashedPassword, $input['nickname'], $balance]);
        
        echo json_encode(['success' => true, 'message' => '用户添加成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '添加用户失败: ' . $e->getMessage()]);
    }
}

function addPrize() {
    global $db, $input;
    
    if (!isset($input['name']) || !isset($input['icon']) || !isset($input['value']) || !isset($input['probability'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        // 获取page参数，决定操作哪个表
        $page = $_GET['page'] ?? 'lucky1.html';
        $tableName = str_replace('.html', '_prizes', $page);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        
        if ($result->rowCount() == 0) {
            // 如果表不存在，使用默认的prizes表
            $tableName = 'prizes';
        }
        
        // 处理数量字段
        $quantity = null;
        if (isset($input['quantity']) && $input['quantity'] !== '' && $input['quantity'] !== null) {
            $quantity = intval($input['quantity']);
        }
        
        // 如果是传说物品，保存原始概率
        $originalProbability = null;
        if (isset($input['rarity']) && $input['rarity'] === 'legendary') {
            $originalProbability = $input['probability'];
        }
        
        $stmt = $db->prepare("INSERT INTO `{$tableName}` (name, icon, image_url, value, probability, original_probability, rarity, quantity, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['icon'],
            $input['image_url'] ?? null,
            $input['value'],
            $input['probability'],
            $originalProbability,
            $input['rarity'] ?? 'common',
            $quantity,
            $input['active'] ?? 1
        ]);
        
        echo json_encode(['success' => true, 'message' => '奖品添加成功']);
        
        // 如果添加的是传说奖品，检查并更新概率状态
        if (isset($input['rarity']) && $input['rarity'] === 'legendary') {
            updateLegendaryProbabilities($tableName);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '添加奖品失败: ' . $e->getMessage()]);
    }
}

function getPrize() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少奖品ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM prizes WHERE id = ?");
        $stmt->execute([$id]);
        $prize = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prize) {
            echo json_encode(['success' => true, 'prize' => $prize]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '奖品不存在']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取奖品信息失败: ' . $e->getMessage()]);
    }
}

function updatePrize() {
    global $db, $input;
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少奖品ID']);
        return;
    }
    
    try {
        // 获取page参数，决定操作哪个表
        $page = $_GET['page'] ?? 'lucky1.html';
        $tableName = str_replace('.html', '_prizes', $page);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        
        if ($result->rowCount() == 0) {
            // 如果表不存在，使用默认的prizes表
            $tableName = 'prizes';
        }
        
        // 处理数量字段
        $quantity = null;
        if (isset($input['quantity']) && $input['quantity'] !== '' && $input['quantity'] !== null) {
            $quantity = intval($input['quantity']);
        }
        
        // 获取当前奖品的稀有度
        $stmt = $db->prepare("SELECT rarity, original_probability FROM `{$tableName}` WHERE id = ?");
        $stmt->execute([$input['id']]);
        $currentPrize = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentRarity = $currentPrize['rarity'];
        
        // 处理概率逻辑
        $originalProbability = null;
        if (isset($input['rarity']) && $input['rarity'] === 'legendary') {
            // 如果是传说奖品，始终使用用户输入的概率作为原始概率
            $originalProbability = $input['probability'];
        } else if ($currentRarity === 'legendary' && $input['rarity'] !== 'legendary') {
            // 从传说变为非传说，清除original_probability
            $originalProbability = null;
        } else if ($currentRarity !== 'legendary') {
            // 非传说物品，不设置original_probability
            $originalProbability = null;
        }
        
        $stmt = $db->prepare("UPDATE `{$tableName}` SET name = ?, icon = ?, image_url = ?, value = ?, probability = ?, original_probability = ?, rarity = ?, quantity = ?, active = ? WHERE id = ?");
        $stmt->execute([
            $input['name'],
            $input['icon'],
            $input['image_url'] ?? null,
            $input['value'],
            $input['probability'],
            $originalProbability,
            $input['rarity'] ?? 'common',
            $quantity,
            $input['active'] ?? 1,
            $input['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => '奖品更新成功']);
        
        // 如果修改了传说奖品的数量，检查并更新概率状态
        if ((isset($input['rarity']) && $input['rarity'] === 'legendary') || $currentRarity === 'legendary') {
            updateLegendaryProbabilities($tableName);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新奖品失败: ' . $e->getMessage()]);
    }
}

function togglePrize() {
    global $db, $input;
    
    if (!isset($input['id']) || !isset($input['active'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        // 获取page参数，决定操作哪个表
        $page = $_GET['page'] ?? 'lucky1.html';
        $tableName = str_replace('.html', '_prizes', $page);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        
        if ($result->rowCount() == 0) {
            // 如果表不存在，使用默认的prizes表
            $tableName = 'prizes';
        }
        
        $stmt = $db->prepare("UPDATE `{$tableName}` SET active = ? WHERE id = ?");
        $stmt->execute([$input['active'] ? 1 : 0, $input['id']]);
        
        echo json_encode(['success' => true, 'message' => '奖品状态更新成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新奖品状态失败: ' . $e->getMessage()]);
    }
}

function deleteUser() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => '用户删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除用户失败: ' . $e->getMessage()]);
    }
}

function updateUser() {
    global $db;
    
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $balance = $_POST['balance'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        // 构建动态SQL
        $fields = [];
        $values = [];
        
        if ($username !== null) {
            $fields[] = "username = ?";
            $values[] = $username;
        }
        
        if ($email !== null) {
            $fields[] = "email = ?";
            $values[] = $email;
        }
        
        if ($balance !== null && is_numeric($balance)) {
            $fields[] = "balance = ?";
            $values[] = floatval($balance);
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => '没有需要更新的字段']);
            return;
        }
        
        $values[] = $id; // 添加WHERE条件的值
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        echo json_encode(['success' => true, 'message' => '用户信息更新成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新用户失败: ' . $e->getMessage()]);
    }
}

function getUserItems() {
    global $db;
    
    $userId = $_GET['userId'] ?? null;
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT id, user_id, name as item_name, icon, value, rarity as item_type, obtained_at as created_at, 1 as quantity
            FROM user_items 
            WHERE user_id = ? AND decomposed = 0
            ORDER BY obtained_at DESC
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取用户物品失败: ' . $e->getMessage()]);
    }
}

function deletePrize() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少奖品ID']);
        return;
    }
    
    try {
        // 获取page参数，决定操作哪个表
        $page = $_GET['page'] ?? 'lucky1.html';
        $tableName = str_replace('.html', '_prizes', $page);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        
        if ($result->rowCount() == 0) {
            // 如果表不存在，使用默认的prizes表
            $tableName = 'prizes';
        }
        
        // 获取要删除的奖品信息
        $stmt = $db->prepare("SELECT rarity FROM `{$tableName}` WHERE id = ?");
        $stmt->execute([$id]);
        $prizeToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prizeToDelete) {
            http_response_code(404);
            echo json_encode(['error' => '奖品不存在']);
            return;
        }
        
        // 只有当要删除普通奖品且存在传说奖品时才进行限制
        if ($prizeToDelete['rarity'] !== 'legendary') {
            // 检查是否存在传说奖品
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM `{$tableName}` WHERE rarity = 'legendary' AND active = 1");
            $stmt->execute();
            $legendaryCount = $stmt->fetchColumn();
            
            if ($legendaryCount > 0) {
                // 检查删除后是否还有其他普通奖品
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM `{$tableName}` WHERE rarity != 'legendary' AND active = 1 AND id != ?");
                $stmt->execute([$id]);
                $remainingNormalCount = $stmt->fetchColumn();
                
                if ($remainingNormalCount == 0) {
                    http_response_code(400);
                    echo json_encode(['error' => '不能删除最后一个普通奖品！当存在传说奖品时，必须保留至少一个普通奖品以确保抽奖系统正常运行。']);
                    return;
                }
            }
        }
        
        $stmt = $db->prepare("DELETE FROM `{$tableName}` WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => '奖品删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除奖品失败: ' . $e->getMessage()]);
    }
}

// Lucky页面管理函数

function listLuckyPages() {
    try {
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $pages = [];
        
        if (is_dir($pagesDir)) {
            $files = glob($pagesDir . 'lucky*.html');
            foreach ($files as $file) {
                $fileName = basename($file);
                
                // 尝试读取页面标题
                $displayName = extractPageTitle($file);
                if (!$displayName) {
                    // 如果无法获取标题，使用文件名生成默认显示名
                    $baseName = str_replace('.html', '', $fileName);
                    $displayName = str_replace('lucky', '大红行动', $baseName);
                    if ($displayName === '大红行动') {
                        $displayName .= '1';
                    }
                }
                
                $pages[] = [
                    'fileName' => $fileName,
                    'displayName' => $displayName,
                    'icon' => '🍎'
                ];
            }
        }
        
        // 按文件名排序
        usort($pages, function($a, $b) {
            return strcmp($a['fileName'], $b['fileName']);
        });
        
        echo json_encode(['success' => true, 'pages' => $pages]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取Lucky页面列表失败: ' . $e->getMessage()]);
    }
}

function createLuckyPage() {
    global $input, $db;
    
    $fileName = $input['fileName'] ?? '';
    $displayName = $input['displayName'] ?? '';
    
    if (!$fileName || !$displayName) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    // 验证文件名格式
    if (!preg_match('/^lucky[a-zA-Z0-9_-]*\.html$/', $fileName)) {
        http_response_code(400);
        echo json_encode(['error' => '文件名格式不正确']);
        return;
    }
    
    try {
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $templateFile = dirname(__DIR__, 2) . '/luckytemp.html';
        $newFilePath = $pagesDir . $fileName;
        
        // 检查文件是否已存在
        if (file_exists($newFilePath)) {
            http_response_code(400);
            echo json_encode(['error' => '文件已存在']);
            return;
        }
        
        // 检查模板文件是否存在
        if (!file_exists($templateFile)) {
            http_response_code(500);
            echo json_encode(['error' => '模板文件不存在']);
            return;
        }
        
        // 读取模板文件内容
        $templateContent = file_get_contents($templateFile);
        
        // 替换模板中的标题
        $newContent = str_replace(
            '<title>幸运掉落 - 幸运降临</title>',
            '<title>' . $displayName . ' - 幸运降临</title>',
            $templateContent
        );
        
        // 调整CSS路径（模板在根目录，新文件在pages目录）
        $newContent = str_replace('../../css/', '../css/', $newContent);
        $newContent = str_replace('../../js/', '../js/', $newContent);
        
        // 写入新文件
        if (!file_put_contents($newFilePath, $newContent)) {
            http_response_code(500);
            echo json_encode(['error' => '创建文件失败']);
            return;
        }
        
        // 创建对应的奖品数据表
        $tableName = str_replace('.html', '_prizes', $fileName);
        $tableName = str_replace('-', '_', $tableName); // 替换连字符为下划线
        
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL COMMENT '奖品名称',
            `icon` varchar(10) DEFAULT '🎁' COMMENT '奖品图标',
            `image_url` varchar(500) DEFAULT NULL COMMENT '奖品图片URL',
            `value` decimal(10,2) DEFAULT 0.00 COMMENT '奖品价值',
            `probability` decimal(5,2) DEFAULT 0.00 COMMENT '中奖概率(%)',
            `rarity` enum('common','rare','epic','legendary') DEFAULT 'common' COMMENT '稀有度',
            `quantity` int(11) DEFAULT NULL COMMENT '奖品数量，NULL表示无限制',
            `original_probability` decimal(10,4) DEFAULT NULL COMMENT '原始概率，用于恢复',
            `active` tinyint(1) DEFAULT 1 COMMENT '是否启用',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='{$displayName}奖品表'";
        
        $db->exec($createTableSQL);
        
        // 插入默认奖品数据
        $defaultPrizes = [
            ['name' => '大红', 'icon' => '🍎', 'value' => 10.00, 'probability' => 30.00, 'rarity' => 'common', 'quantity' => null, 'original_probability' => 30.00],
            ['name' => '钻石', 'icon' => '💎', 'value' => 100.00, 'probability' => 5.00, 'rarity' => 'legendary', 'quantity' => 3, 'original_probability' => 5.00],
            ['name' => '金币', 'icon' => '🪙', 'value' => 1.00, 'probability' => 50.00, 'rarity' => 'common', 'quantity' => null, 'original_probability' => 50.00],
            ['name' => '空奖', 'icon' => '❌', 'value' => 0.00, 'probability' => 15.00, 'rarity' => 'common', 'quantity' => null, 'original_probability' => 15.00]
        ];
        
        $insertSQL = "INSERT INTO `{$tableName}` (name, icon, value, probability, rarity, quantity, original_probability) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($insertSQL);
        
        foreach ($defaultPrizes as $prize) {
            $stmt->execute([
                $prize['name'], 
                $prize['icon'], 
                $prize['value'], 
                $prize['probability'], 
                $prize['rarity'],
                $prize['quantity'],
                $prize['original_probability']
            ]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Lucky页面创建成功',
            'tableName' => $tableName
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '创建Lucky页面失败: ' . $e->getMessage()]);
    }
}

function renameLuckyPage() {
    global $input, $db;
    
    $oldFileName = $input['oldFileName'] ?? '';
    $newFileName = $input['newFileName'] ?? '';
    $newDisplayName = $input['newDisplayName'] ?? '';
    
    if (!$oldFileName || !$newFileName || !$newDisplayName) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    // 验证新文件名格式
    if (!preg_match('/^lucky[a-zA-Z0-9_-]*\.html$/', $newFileName)) {
        http_response_code(400);
        echo json_encode(['error' => '新文件名格式不正确']);
        return;
    }
    
    try {
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $oldFilePath = $pagesDir . $oldFileName;
        $newFilePath = $pagesDir . $newFileName;
        
        // 检查原文件是否存在
        if (!file_exists($oldFilePath)) {
            http_response_code(400);
            echo json_encode(['error' => '原文件不存在']);
            return;
        }
        
        // 检查新文件名是否已存在
        if (file_exists($newFilePath) && $oldFileName !== $newFileName) {
            http_response_code(400);
            echo json_encode(['error' => '新文件名已存在']);
            return;
        }
        
        // 如果只是更改显示名称，不需要重命名文件
        if ($oldFileName !== $newFileName) {
            // 重命名文件
            if (!rename($oldFilePath, $newFilePath)) {
                http_response_code(500);
                echo json_encode(['error' => '重命名文件失败']);
                return;
            }
            
            // 重命名对应的数据表
            $oldTableName = str_replace('.html', '_prizes', $oldFileName);
            $oldTableName = str_replace('-', '_', $oldTableName);
            $newTableName = str_replace('.html', '_prizes', $newFileName);
            $newTableName = str_replace('-', '_', $newTableName);
            
            if ($oldTableName !== $newTableName) {
                // 检查旧表是否存在
                $checkTableSQL = "SHOW TABLES LIKE '{$oldTableName}'";
                $result = $db->query($checkTableSQL);
                if ($result->rowCount() > 0) {
                    $renameTableSQL = "RENAME TABLE `{$oldTableName}` TO `{$newTableName}`";
                    $db->exec($renameTableSQL);
                }
            }
        }
        
        // 更新文件中的标题
        $filePath = ($oldFileName !== $newFileName) ? $newFilePath : $oldFilePath;
        $content = file_get_contents($filePath);
        
        // 更新title标签
        $content = preg_replace(
            '/<title>.*? - 幸运降临<\/title>/',
            '<title>' . $newDisplayName . ' - 幸运降临</title>',
            $content
        );
        
        file_put_contents($filePath, $content);
        
        echo json_encode(['success' => true, 'message' => 'Lucky页面重命名成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '重命名Lucky页面失败: ' . $e->getMessage()]);
    }
}

function deleteLuckyPage() {
    global $input, $db;
    
    $fileName = $input['fileName'] ?? '';
    
    if (!$fileName) {
        http_response_code(400);
        echo json_encode(['error' => '缺少文件名参数']);
        return;
    }
    
    try {
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $filePath = $pagesDir . $fileName;
        
        // 检查文件是否存在
        if (!file_exists($filePath)) {
            http_response_code(400);
            echo json_encode(['error' => '文件不存在']);
            return;
        }
        
        // 删除文件
        if (!unlink($filePath)) {
            http_response_code(500);
            echo json_encode(['error' => '删除文件失败']);
            return;
        }
        
        // 删除对应的数据表
        $tableName = str_replace('.html', '_prizes', $fileName);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        if ($result->rowCount() > 0) {
            $dropTableSQL = "DROP TABLE `{$tableName}`";
            $db->exec($dropTableSQL);
        }
        
        echo json_encode(['success' => true, 'message' => 'Lucky页面删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除Lucky页面失败: ' . $e->getMessage()]);
    }
}

// 辅助函数：从HTML文件中提取页面标题
function extractPageTitle($filePath) {
    try {
        $content = file_get_contents($filePath);
        if (preg_match('/<title>(.*?) - 幸运降临<\/title>/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// 删除用户物品
function deleteUserItem() {
    global $db, $input;
    
    // 获取并验证参数
    $userId = $input['user_id'] ?? null;
    $itemId = $input['item_id'] ?? null;
    
    // 参数验证
    if (!$userId || !$itemId) {
        error_log("删除用户物品失败: 缺少参数 - userId: " . var_export($userId, true) . ", itemId: " . var_export($itemId, true));
        error_log("接收到的完整输入: " . var_export($input, true));
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID或物品ID']);
        return;
    }
    
    // 确保参数是数字类型
    $userId = intval($userId);
    $itemId = intval($itemId);
    
    if ($userId <= 0 || $itemId <= 0) {
        error_log("删除用户物品失败: 无效的ID - userId: $userId, itemId: $itemId");
        http_response_code(400);
        echo json_encode(['error' => '无效的用户ID或物品ID']);
        return;
    }
    
    try {
        // 检查物品是否存在且属于指定用户
        $stmt = $db->prepare("SELECT id, user_id, name as item_name FROM user_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            error_log("删除用户物品失败: 物品不存在 - userId: $userId, itemId: $itemId");
            http_response_code(404);
            echo json_encode(['error' => '物品不存在或不属于指定用户']);
            return;
        }
        
        // 删除物品
        $stmt = $db->prepare("DELETE FROM user_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            // 记录操作日志
            try {
                $logStmt = $db->prepare("
                    INSERT INTO admin_security_log (admin_id, action, target_type, target_id, details, ip_address, created_at) 
                    VALUES (0, 'delete_user_item', 'user_item', ?, ?, ?, NOW())
                ");
                $logStmt->execute([
                    $itemId,
                    "删除用户ID {$userId} 的物品 '{$item['item_name']}' (ID: {$itemId})",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } catch (Exception $logError) {
                error_log("记录删除日志失败: " . $logError->getMessage());
                // 不影响主操作，继续执行
            }
            
            echo json_encode(['success' => true, 'message' => '物品删除成功']);
        }
    } catch (Exception $e) {
        error_log("删除用户物品异常: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '删除物品失败: ' . $e->getMessage()]);
    }
}

// 获取用户详细信息
function getUserDetails() {
    global $db;
    global $input;
    
    $userId = $input['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT 
                id,
                username,
                nickname,
                balance,
                is_online,
                last_login,
                last_activity,
                created_at,
                updated_at,
                (SELECT COUNT(*) FROM lottery_records WHERE user_id = ?) as total_draws,
                (SELECT COUNT(*) FROM lottery_records WHERE user_id = ? AND reward > 0) as win_count,
                (SELECT SUM(cost) FROM lottery_records WHERE user_id = ?) as total_spent,
                (SELECT COUNT(*) FROM transactions WHERE user_id = ?) as total_transactions
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 计算胜率
        $user['win_rate'] = $user['total_draws'] > 0 ? round(($user['win_count'] / $user['total_draws']) * 100, 2) : 0;
        
        echo json_encode(['success' => true, 'user' => $user]);
    } catch (Exception $e) {
        error_log("获取用户详情失败: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取用户详情失败']);
    }
}

// 获取用户抽奖记录
function getUserDraws() {
    global $db;
    global $input;
    
    $userId = $input['user_id'] ?? null;
    $page = max(1, intval($input['page'] ?? 1));
    $limit = min(50, max(10, intval($input['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM lottery_records WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 获取抽奖记录
        $stmt = $db->prepare("
            SELECT 
                id,
                user_id,
                game_type,
                cost,
                reward,
                result,
                created_at
            FROM lottery_records 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([$userId]);
        $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析结果JSON
        foreach ($draws as &$draw) {
            if ($draw['result']) {
                $draw['result'] = json_decode($draw['result'], true);
            }
        }
        
        echo json_encode([
            'success' => true,
            'draws' => $draws,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        error_log("获取用户抽奖记录失败: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取抽奖记录失败']);
    }
}

// 获取用户交易记录
function getUserTransactions() {
    global $db;
    global $input;
    
    $userId = $input['user_id'] ?? null;
    $page = max(1, intval($input['page'] ?? 1));
    $limit = min(50, max(10, intval($input['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户ID']);
        return;
    }
    
    try {
        // 获取总数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 获取交易记录
        $stmt = $db->prepare("
            SELECT 
                id,
                user_id,
                type,
                amount,
                description,
                created_at
            FROM transactions 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'limit' => $limit
            ]
        ]);
    } catch (Exception $e) {
        error_log("获取用户交易记录失败: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '获取交易记录失败']);
    }
}

// 更新传说奖品概率状态的函数
function updateLegendaryProbabilities($tableName) {
    global $db;
    
    try {
        // 获取所有传说奖品
        $stmt = $db->prepare("SELECT id, quantity, probability, original_probability FROM `{$tableName}` WHERE rarity = 'legendary' AND active = 1");
        $stmt->execute();
        $legendaryPrizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($legendaryPrizes as $prize) {
            // 如果original_probability为空，初始化为当前概率
            if ($prize['original_probability'] === null) {
                $stmt = $db->prepare("UPDATE `{$tableName}` SET original_probability = probability WHERE id = ?");
                $stmt->execute([$prize['id']]);
                $prize['original_probability'] = $prize['probability'];
            }
            
            // 根据数量状态调整概率
            if (isset($prize['quantity']) && $prize['quantity'] !== null) {
                if ($prize['quantity'] <= 0) {
                    // 数量为0，概率设为0
                    $stmt = $db->prepare("UPDATE `{$tableName}` SET probability = 0 WHERE id = ?");
                    $stmt->execute([$prize['id']]);
                } else {
                    // 数量大于0，恢复原始概率
                    $stmt = $db->prepare("UPDATE `{$tableName}` SET probability = original_probability WHERE id = ?");
                    $stmt->execute([$prize['id']]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("更新传说奖品概率失败: " . $e->getMessage());
    }
}
?>
