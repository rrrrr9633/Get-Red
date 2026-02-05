<?php
session_start(); // 启动会话支持
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
$action = $input['action'] ?? $_GET['action'] ?? '';

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
    case 'check_auth':
        checkAuth();
        break;
    case 'generate_access_token':
        generateAccessToken();
        break;
    case 'get_theme_settings':
        getThemeSettings();
        break;
    case 'update_theme_settings':
        updateThemeSettings();
        break;
    case 'update_lucky_page_thumb':
        updateLuckyPageThumb();
        break;
    case 'get_draw_prices':
        getDrawPrices();
        break;
    case 'update_draw_price':
        updateDrawPrice();
        break;
    case 'batch_update_draw_prices':
        batchUpdateDrawPrices();
        break;
    case 'reset_draw_prices':
        resetDrawPrices();
        break;
    case 'get_price_history':
        getPriceHistory();
        break;
    case 'get_shop_icons':
        getShopIcons();
        break;
    case 'update_shop_icon':
        updateShopIcon();
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
        
        // 获取用户列表，包含在线状态信息和待处理提现数量
        $stmt = $db->query("
            SELECT 
                u.id, 
                u.username, 
                u.nickname, 
                u.balance, 
                u.is_online,
                u.last_login,
                u.last_activity,
                u.created_at,
                u.updated_at,
                u.user_type,
                u.status,
                COUNT(DISTINCT wr.id) as pending_withdrawals,
                COUNT(DISTINCT sph.id) as pending_orders
            FROM users u
            LEFT JOIN withdrawal_requests wr ON u.id = wr.user_id AND wr.status IN ('pending', 'processing')
            LEFT JOIN shop_purchase_history sph ON u.id = sph.user_id AND sph.status IN ('pending', 'processing')
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理显示格式
        foreach ($users as &$user) {
            $user['last_login_formatted'] = $user['last_login'] ? 
                date('Y-m-d H:i:s', strtotime($user['last_login'])) : '从未登录';
            $user['last_activity_formatted'] = $user['last_activity'] ? 
                date('Y-m-d H:i:s', strtotime($user['last_activity'])) : '无活动记录';
            $user['online_status'] = $user['is_online'] ? '在线' : '离线';
            $user['pending_withdrawals'] = intval($user['pending_withdrawals']); // 确保是整数
            $user['pending_orders'] = intval($user['pending_orders']); // 确保是整数
        }
        
        // 获取用户统计
        $totalUsers = count($users);
        
        // 获取在线用户数
        $stmt = $db->query("SELECT COUNT(*) as online_count FROM users WHERE is_online = 1");
        $onlineCount = $stmt->fetch(PDO::FETCH_ASSOC)['online_count'];
        
        // 获取今日新增用户
        $stmt = $db->query("SELECT COUNT(*) as today_new FROM users WHERE DATE(created_at) = CURDATE()");
        $todayNew = $stmt->fetch(PDO::FETCH_ASSOC)['today_new'];
        
        // 获取待处理提现总数
        $stmt = $db->query("SELECT COUNT(*) as pending_withdrawals FROM withdrawal_requests WHERE status IN ('pending', 'processing')");
        $pendingWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['pending_withdrawals'];
        
        // 获取待处理订单总数
        $stmt = $db->query("SELECT COUNT(*) as pending_orders FROM shop_purchase_history WHERE status IN ('pending', 'processing')");
        $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['pending_orders'];
        
        echo json_encode([
            'success' => true, 
            'users' => $users,
            'stats' => [
                'total' => $totalUsers,
                'online' => $onlineCount,
                'today_new' => $todayNew,
                'pending_withdrawals' => $pendingWithdrawals + $pendingOrders
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
        // 开始事务
        $db->beginTransaction();
        
        // 删除用户相关数据（按外键依赖顺序）
        
        // 1. 删除充值历史记录
        $stmt = $db->prepare("DELETE FROM recharge_history WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 2. 删除用户物品
        $stmt = $db->prepare("DELETE FROM user_items WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 3. 删除抽奖记录
        $stmt = $db->prepare("DELETE FROM draws WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 4. 删除签到记录
        $stmt = $db->prepare("DELETE FROM user_checkin WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 5. 删除签到历史记录
        $stmt = $db->prepare("DELETE FROM checkin_records WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 6. 删除抽奖历史
        $stmt = $db->prepare("DELETE FROM draw_history WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 7. 删除彩票记录
        $stmt = $db->prepare("DELETE FROM lottery_records WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 8. 删除奖品抽取日志
        $stmt = $db->prepare("DELETE FROM prize_draw_log WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 9. 删除交易记录
        $stmt = $db->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // 10. 最后删除用户本身
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        // 检查是否删除的是超级管理员
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'super_admin' AND status = 'active'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果没有活跃的超级管理员了，重新激活默认admin账户
        if ($result['count'] == 0) {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE username = 'admin' AND user_type = 'super_admin' AND nickname = '默认超级管理员'");
            $stmt->execute();
        }
        
        // 提交事务
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => '用户及相关数据删除成功']);
    } catch (Exception $e) {
        // 回滚事务
        $db->rollback();
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
                
                // 检查是否有小图片
                $thumbImage = getPageThumbImage($fileName);
                
                $pages[] = [
                    'fileName' => $fileName,
                    'displayName' => $displayName,
                    'icon' => '🍎',
                    'thumbImage' => $thumbImage
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
    global $db;
    
    // 处理文件上传，使用$_POST和$_FILES而不是JSON输入
    $fileName = $_POST['fileName'] ?? '';
    $displayName = $_POST['displayName'] ?? '';
    $description = $_POST['description'] ?? '';
    
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
        $imagesDir = dirname(__DIR__, 2) . '/images/';
        
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
        
        // 处理图片上传
        $imageFileName = null;
        if (isset($_FILES['gameImage']) && $_FILES['gameImage']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['gameImage'];
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($uploadedFile['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => '不支持的图片格式']);
                return;
            }
            
            // 验证文件大小（最大2MB）
            if ($uploadedFile['size'] > 2 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => '图片文件过大，请控制在2MB以内']);
                return;
            }
            
            // 生成唯一文件名
            $ext = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $imageFileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . time() . '.' . $ext;
            
            // 确保images目录存在
            if (!is_dir($imagesDir)) {
                mkdir($imagesDir, 0755, true);
            }
            
            // 移动上传的文件
            if (!move_uploaded_file($uploadedFile['tmp_name'], $imagesDir . $imageFileName)) {
                http_response_code(500);
                echo json_encode(['error' => '图片上传失败']);
                return;
            }
        }
        
        // 读取模板文件内容
        $templateContent = file_get_contents($templateFile);
        
        // 替换模板中的标题
        $newContent = str_replace(
            '<title>幸运掉落 - 幸运降临</title>',
            '<title>' . $displayName . ' - 幸运降临</title>',
            $templateContent
        );
        
        // 替换页面标题
        $newContent = str_replace(
            '<h2 class="neon-text rainbow">幸运掉落</h2>',
            '<h2 class="neon-text rainbow">' . $displayName . '</h2>',
            $newContent
        );
        
        // 如果有描述，替换说明文字
        if ($description) {
            $newContent = str_replace(
                '<p class="neon-text">神秘礼品等你来抽，运气决定一切！</p>',
                '<p class="neon-text">' . htmlspecialchars($description) . '</p>',
                $newContent
            );
        }
        
        // 如果有图片，修改中心展示图片
        if ($imageFileName) {
            // 创建图片HTML，使用适合展示区的样式
            $showcaseImageHtml = '<img src="../images/' . $imageFileName . '" alt="' . htmlspecialchars($displayName) . '" style="max-width: 180px; max-height: 180px; object-fit: contain; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.5));">';
            
            // 替换展示区的emoji图标为图片
            $newContent = str_replace(
                '<div class="showcase-icon">🎁</div>',
                '<div class="showcase-icon">' . $showcaseImageHtml . '</div>',
                $newContent
            );
            
            // 同时调整showcase-icon的CSS以适应图片
            $imageStyle = "<style>\n";
            $imageStyle .= ".showcase-icon img {\n";
            $imageStyle .= "    animation: float 3s ease-in-out infinite;\n";
            $imageStyle .= "}\n";
            $imageStyle .= "</style>\n";
            
            // 在</head>前插入样式
            $newContent = str_replace('</head>', $imageStyle . '</head>', $newContent);
        }
        
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

// 辅助函数：获取页面小图片
function getPageThumbImage($fileName) {
    $imagesDir = dirname(__DIR__, 2) . '/images/thumbs/';
    $pageBaseName = pathinfo($fileName, PATHINFO_FILENAME);
    
    // 查找对应的小图片文件
    $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($extensions as $ext) {
        $thumbPath = $imagesDir . $pageBaseName . '.' . $ext;
        if (file_exists($thumbPath)) {
            return 'images/thumbs/' . $pageBaseName . '.' . $ext;
        }
    }
    
    return null;
}

// 更新Lucky页面小图片
function updateLuckyPageThumb() {
    if (!isset($_POST['fileName'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少文件名参数']);
        return;
    }
    
    $fileName = $_POST['fileName'];
    $pageBaseName = pathinfo($fileName, PATHINFO_FILENAME);
    
    // 验证文件名
    if (!preg_match('/^lucky[a-zA-Z0-9_-]*$/', $pageBaseName)) {
        http_response_code(400);
        echo json_encode(['error' => '无效的文件名']);
        return;
    }
    
    try {
        $thumbsDir = dirname(__DIR__, 2) . '/images/thumbs/';
        
        // 确保thumbs目录存在
        if (!is_dir($thumbsDir)) {
            mkdir($thumbsDir, 0755, true);
        }
        
        // 处理图片上传
        if (isset($_FILES['thumbImage']) && $_FILES['thumbImage']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['thumbImage'];
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($uploadedFile['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => '不支持的图片格式']);
                return;
            }
            
            // 验证文件大小（最大1MB）
            if ($uploadedFile['size'] > 1 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => '图片文件过大，请控制在1MB以内']);
                return;
            }
            
            // 删除旧的小图片
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            foreach ($extensions as $ext) {
                $oldPath = $thumbsDir . $pageBaseName . '.' . $ext;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            // 生成新文件名
            $ext = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $newFileName = $pageBaseName . '.' . $ext;
            $newFilePath = $thumbsDir . $newFileName;
            
            // 移动上传的文件
            if (!move_uploaded_file($uploadedFile['tmp_name'], $newFilePath)) {
                http_response_code(500);
                echo json_encode(['error' => '图片上传失败']);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => '小图片上传成功',
                'thumbImage' => 'images/thumbs/' . $newFileName
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => '没有上传图片或上传失败']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新小图片失败: ' . $e->getMessage()]);
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

// 检查用户权限
function checkAuth() {
    session_start();
    
    // 检查超级管理员权限
    if (isset($_SESSION['super_admin_verified']) && $_SESSION['super_admin_verified'] === true) {
        echo json_encode([
            'success' => true,
            'user_type' => 'super_admin',
            'username' => $_SESSION['super_admin_username'] ?? ''
        ]);
        return;
    }
    
    // 检查客服用户权限
    if (isset($_SESSION['service_verified']) && $_SESSION['service_verified'] === true) {
        echo json_encode([
            'success' => true,
            'user_type' => 'service',
            'username' => $_SESSION['service_username'] ?? ''
        ]);
        return;
    }
    
    // 未授权
    echo json_encode([
        'success' => false,
        'message' => '未授权访问'
    ]);
}

function generateAccessToken() {
    session_start();
    
    // 验证超级管理员身份
    if (!isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '未授权访问']);
        return;
    }
    
    // 生成访问token
    $token = bin2hex(random_bytes(32));
    $_SESSION['admin_access_token'] = $token;
    $_SESSION['admin_verified'] = time();
    
    echo json_encode([
        'success' => true,
        'token' => $token
    ]);
}

// 获取主题设置
function getThemeSettings() {
    global $db;
    
    session_start();
    
    // 验证超级管理员身份
    if (!isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '未授权访问']);
        return;
    }
    
    try {
        // 从system_settings表中获取主题设置
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'theme_name'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $themeName = $result ? $result['setting_value'] : '幸运降临';
        
        echo json_encode([
            'success' => true,
            'theme' => [
                'name' => $themeName
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 更新主题设置
function updateThemeSettings() {
    global $input, $db;
    
    session_start();
    
    // 验证超级管理员身份
    if (!isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '未授权访问']);
        return;
    }
    
    $themeName = $input['themeName'] ?? '';
    
    if (!$themeName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少主题名称']);
        return;
    }
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 更新数据库中的主题设置
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('theme_name', ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$themeName]);
        
        // 获取项目根目录
        $projectRoot = dirname(__DIR__, 2);
        $updatedFiles = 0;
        
        // 需要更新的文件模式
        $filesToUpdate = [
            // 主页和根目录文件
            $projectRoot . '/index.html',
            $projectRoot . '/super-admin.html',
            $projectRoot . '/create-super-admin.html',
            $projectRoot . '/luckytemp.html',
            
            // 用户相关页面
            $projectRoot . '/pages/main.html',
            $projectRoot . '/pages/auth/login.html',
            $projectRoot . '/pages/auth/register.html',
            $projectRoot . '/pages/user/profile.html',
            $projectRoot . '/pages/user/recharge.html',
            $projectRoot . '/pages/modules/checkin.html',
            $projectRoot . '/pages/modules/container.html',
        ];
        
        // 管理员页面
        $adminPages = glob($projectRoot . '/pages/admin/*.html');
        $filesToUpdate = array_merge($filesToUpdate, $adminPages);
        
        // Lucky页面
        $luckyPages = glob($projectRoot . '/pages/lucky*.html');
        $filesToUpdate = array_merge($filesToUpdate, $luckyPages);
        
        // 更新每个文件
        foreach ($filesToUpdate as $filePath) {
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                
                // 替换title标签中的主题名称
                $patterns = [
                    '/<title>([^<]*?)幸运降临([^<]*?)<\/title>/i',
                    '/<title>([^<]*?)大红行动([^<]*?)<\/title>/i',
                    '/<title>([^<]*?)幸运大抽奖([^<]*?)<\/title>/i',
                    '/<title>([^<]*?)幸运转盘([^<]*?)<\/title>/i',
                ];
                
                $replaced = false;
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '<title>$1' . $themeName . '$2</title>', $content);
                        $replaced = true;
                        break;
                    }
                }
                
                // 如果没有找到匹配的模式，尝试替换包含"降临"的标题
                if (!$replaced) {
                    $content = preg_replace('/<title>([^<]*?)<\/title>/i', '<title>$1</title>', $content);
                    $content = preg_replace('/<title>([^<]*?)([^<]*?)<\/title>/i', '<title>' . $themeName . '</title>', $content);
                }
                
                // 替换导航栏中的品牌名称
                $brandPatterns = [
                    '/(<h1[^>]*?>)([^<]*?)幸运降临([^<]*?)(<\/h1>)/i',
                    '/(<h1[^>]*?>)([^<]*?)大红行动([^<]*?)(<\/h1>)/i',
                    '/(<div[^>]*?nav-brand[^>]*?>.*?<h1[^>]*?>)([^<]*?)幸运降临([^<]*?)(<\/h1>)/is',
                ];
                
                foreach ($brandPatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, '$1$2' . $themeName . '$3$4', $content);
                        break;
                    }
                }
                
                // 替换页面标题中的主题名称
                $headerPatterns = [
                    '/(<h[1-6][^>]*?>)([^<]*?)幸运降临([^<]*?)(<\/h[1-6]>)/i',
                    '/(<h[1-6][^>]*?>)([^<]*?)大红行动([^<]*?)(<\/h[1-6]>)/i',
                ];
                
                foreach ($headerPatterns as $pattern) {
                    $content = preg_replace($pattern, '$1$2' . $themeName . '$3$4', $content);
                }
                
                // 保存文件
                if (file_put_contents($filePath, $content) !== false) {
                    $updatedFiles++;
                }
            }
        }
        
        // 提交事务
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '主题设置更新成功',
            'updated_files' => $updatedFiles
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ======== 抽奖价格控制功能 ========

// 获取抽奖价格设置
function getDrawPrices() {
    global $db;
    
    try {
        $page = $_GET['page'] ?? 'lucky1.html';
        
        // 查询当前页面的价格设置
        $stmt = $db->prepare("
            SELECT price_type, price_value, button_name 
            FROM draw_prices 
            WHERE page_name = ?
        ");
        $stmt->execute([$page]);
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 转换为关联数组
        $priceData = [
            'single' => 10,    // 默认值
            'triple' => 30,
            'quintuple' => 50
        ];
        
        $nameData = [
            'single' => '',
            'triple' => '',
            'quintuple' => ''
        ];
        
        foreach ($prices as $price) {
            $priceData[$price['price_type']] = (int)$price['price_value'];
            $nameData[$price['price_type']] = $price['button_name'] ?? '';
        }
        
        echo json_encode([
            'success' => true,
            'prices' => $priceData,
            'names' => $nameData
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 更新单个抽奖价格
function updateDrawPrice() {
    global $db, $input;
    
    try {
        $page = $input['page'] ?? '';
        $type = $input['type'] ?? '';
        $price = $input['price'] ?? 0;
        $buttonName = $input['button_name'] ?? null;
        
        if (empty($page) || empty($type) || $price <= 0) {
            throw new Exception('参数不完整或价格无效');
        }
        
        if (!in_array($type, ['single', 'triple', 'quintuple'])) {
            throw new Exception('无效的抽奖类型');
        }
        
        $db->beginTransaction();
        
        // 获取旧价格用于历史记录
        $stmt = $db->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = ?");
        $stmt->execute([$page, $type]);
        $oldPrice = $stmt->fetchColumn() ?: 0;
        
        // 更新或插入价格和按钮名称
        $stmt = $db->prepare("
            INSERT INTO draw_prices (page_name, price_type, price_value, button_name, updated_at) 
            VALUES (?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            price_value = VALUES(price_value),
            button_name = VALUES(button_name),
            updated_at = VALUES(updated_at)
        ");
        $stmt->execute([$page, $type, $price, $buttonName]);
        
        // 记录价格变更历史
        $stmt = $db->prepare("
            INSERT INTO price_history (page_name, price_type, old_price, new_price, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$page, $type, $oldPrice, $price]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '价格更新成功'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 批量更新抽奖价格
function batchUpdateDrawPrices() {
    global $db, $input;
    
    try {
        $page = $input['page'] ?? '';
        $prices = $input['prices'] ?? [];
        
        if (empty($page) || empty($prices)) {
            throw new Exception('参数不完整');
        }
        
        $db->beginTransaction();
        
        foreach ($prices as $type => $price) {
            if (!in_array($type, ['single', 'triple', 'quintuple']) || $price <= 0) {
                continue;
            }
            
            // 获取旧价格
            $stmt = $db->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = ?");
            $stmt->execute([$page, $type]);
            $oldPrice = $stmt->fetchColumn() ?: 0;
            
            // 更新价格
            $stmt = $db->prepare("
                INSERT INTO draw_prices (page_name, price_type, price_value, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                price_value = VALUES(price_value), 
                updated_at = VALUES(updated_at)
            ");
            $stmt->execute([$page, $type, $price]);
            
            // 记录历史
            $stmt = $db->prepare("
                INSERT INTO price_history (page_name, price_type, old_price, new_price, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$page, $type, $oldPrice, $price]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '批量价格更新成功'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 重置为默认价格
function resetDrawPrices() {
    global $db, $input;
    
    try {
        $page = $input['page'] ?? '';
        
        if (empty($page)) {
            throw new Exception('页面参数不能为空');
        }
        
        $defaultPrices = [
            'single' => 10,
            'triple' => 30,
            'quintuple' => 50
        ];
        
        $db->beginTransaction();
        
        foreach ($defaultPrices as $type => $price) {
            // 获取旧价格
            $stmt = $db->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = ?");
            $stmt->execute([$page, $type]);
            $oldPrice = $stmt->fetchColumn() ?: 0;
            
            // 更新为默认价格
            $stmt = $db->prepare("
                INSERT INTO draw_prices (page_name, price_type, price_value, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                price_value = VALUES(price_value), 
                updated_at = VALUES(updated_at)
            ");
            $stmt->execute([$page, $type, $price]);
            
            // 记录历史
            $stmt = $db->prepare("
                INSERT INTO price_history (page_name, price_type, old_price, new_price, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$page, 'reset', $oldPrice, $price]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '价格已重置为默认值'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 获取价格变更历史
function getPriceHistory() {
    global $db;
    
    try {
        $page = $_GET['page'] ?? 'lucky1.html';
        
        $stmt = $db->prepare("
            SELECT price_type, old_price, new_price, created_at 
            FROM price_history 
            WHERE page_name = ? 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$page]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ========== 商店图标管理功能 ==========

function getShopIcons() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT * FROM shop_icon_config 
            ORDER BY sort_order ASC, id ASC
        ");
        $icons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'icons' => $icons
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '获取图标配置失败: ' . $e->getMessage()
        ]);
    }
}

function updateShopIcon() {
    global $db, $input;
    
    if (!isset($input['id']) || !isset($input['icon_key'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE shop_icon_config 
            SET icon_url = ?, 
                fallback_icon = ?, 
                description = ?,
                updated_at = NOW()
            WHERE id = ? AND icon_key = ?
        ");
        
        $stmt->execute([
            $input['icon_url'] ?? '',
            $input['fallback_icon'] ?? '🎁',
            $input['description'] ?? '',
            $input['id'],
            $input['icon_key']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '图标更新成功'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '更新图标失败: ' . $e->getMessage()
        ]);
    }
}
?>
