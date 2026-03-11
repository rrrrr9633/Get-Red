<?php
// 引入安全配置
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();
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
require_once '../config/coin-helper.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

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
    case 'monitor_data':
        getMonitorData();
        break;
    case 'get_merge_groups':
        getMergeGroups();
        break;
    case 'create_merge_group':
        createMergeGroup();
        break;
    case 'update_merge_group':
        updateMergeGroup();
        break;
    case 'delete_merge_group':
        deleteMergeGroup();
        break;
    case 'update_page_merge':
        updatePageMerge();
        break;
    case 'list_lucky_pages_merged':
        listLuckyPagesMerged();
        break;
    case 'get_page_merge_info':
        getPageMergeInfo();
        break;
    case 'get_group_pages':
        getGroupPages();
        break;
    case 'sync_page_titles':
        syncPageTitles();
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
        
        // 获取用户列表，包含在线状态信息、待处理提现数量和未读消息数量
        $stmt = $db->query("
            SELECT 
                u.id, 
                u.username, 
                u.nickname, 
                u.balance,
                u.bound_coins,
                u.unbound_coins,
                u.is_online,
                u.last_login,
                u.last_activity,
                u.created_at,
                u.updated_at,
                u.user_type,
                u.status,
                COUNT(DISTINCT wr.id) as pending_withdrawals,
                COUNT(DISTINCT sph.id) as pending_orders,
                COALESCE((
                    SELECT COUNT(*) 
                    FROM chat_messages cm
                    JOIN chat_sessions cs ON cm.session_id = cs.session_id
                    WHERE cs.user_id = u.id 
                    AND cm.sender_type = 'user' 
                    AND cm.is_read = 0
                ), 0) as unread_messages
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
            $user['unread_messages'] = intval($user['unread_messages']); // 确保是整数
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
        // 获取page参数，决定查询哪个页面的奖品
        $page = $_GET['page'] ?? 'lucky1.html';
        $luckyPage = str_replace('.html', '', $page);
        
        // 查询该页面的所有奖品（包含页面特定的启用状态和概率）
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.name,
                p.icon,
                p.image_url,
                p.value,
                p.probability AS default_probability,
                COALESCE(plp.page_probability, p.probability) AS probability,
                p.rarity,
                p.quantity AS global_quantity,
                COALESCE(plp.page_quantity, p.quantity) AS quantity,
                p.original_probability,
                p.active AS global_active,
                plp.enabled AS active,
                plp.page_probability,
                plp.page_quantity,
                p.created_at,
                p.updated_at
            FROM prizes p
            LEFT JOIN prize_lucky_pages plp ON p.id = plp.prize_id AND plp.lucky_page = ?
            ORDER BY COALESCE(plp.page_probability, p.probability) DESC
        ");
        $stmt->execute([$luckyPage]);
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 标记哪些奖品在当前页面已关联
        foreach ($prizes as &$prize) {
            $prize['is_linked'] = !is_null($prize['active']);
            // 如果未关联，设置默认值
            if (!$prize['is_linked']) {
                $prize['active'] = 0;
                $prize['page_probability'] = null;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'prizes' => $prizes, 
            'lucky_page' => $luckyPage,
            'table' => 'prizes (unified)'
        ]);
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
        $balance = $input['balance'] ?? 10;
        
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
    
    // 检查是否是文件上传
    $isFileUpload = isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK;
    
    // 如果是文件上传，从$_POST获取数据；否则从$input获取
    if ($isFileUpload) {
        $data = $_POST;
    } else {
        $data = $input;
    }
    
    if (!isset($data['name']) || !isset($data['icon']) || !isset($data['value']) || !isset($data['probability'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    try {
        // 获取page参数，决定关联到哪个页面
        $page = $_GET['page'] ?? 'lucky1.html';
        $luckyPage = str_replace('.html', '', $page);
        
        $db->beginTransaction();
        
        // 处理图片上传
        $imageUrl = null;
        if ($isFileUpload) {
            $file = $_FILES['image_file'];
            $uploadDir = dirname(__DIR__, 2) . '/images/prizes/';
            
            // 确保上传目录存在
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'prize_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // 移动上传的文件
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $imageUrl = 'images/prizes/' . $fileName;
            } else {
                throw new Exception('文件上传失败');
            }
        } else {
            $imageUrl = $data['image_url'] ?? null;
        }
        
        // 处理数量字段
        $quantity = null;
        if (isset($data['quantity']) && $data['quantity'] !== '' && $data['quantity'] !== null) {
            $quantity = intval($data['quantity']);
        }
        
        // 如果是传说物品，保存原始概率
        $originalProbability = null;
        if (isset($data['rarity']) && $data['rarity'] === 'legendary') {
            $originalProbability = $data['probability'];
        }
        
        // 1. 插入到统一的prizes表
        $stmt = $db->prepare("
            INSERT INTO prizes (name, icon, image_url, value, probability, original_probability, rarity, quantity, active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['icon'],
            $imageUrl,
            $data['value'],
            $data['probability'],
            $originalProbability,
            $data['rarity'] ?? 'common',
            $quantity,
            1  // 全局默认启用
        ]);
        
        $prizeId = $db->lastInsertId();
        
        // 2. 关联到当前Lucky页面
        $pageEnabled = $data['active'] ?? 1;  // 页面级别的启用状态
        $pageProbability = isset($data['page_probability']) && $data['page_probability'] !== '' 
            ? $data['page_probability'] 
            : null;  // 页面特定概率（NULL表示使用默认概率）
        $pageQuantity = isset($data['page_quantity']) && $data['page_quantity'] !== '' 
            ? intval($data['page_quantity']) 
            : null;  // 页面特定数量（NULL表示使用默认数量）
        
        $stmt = $db->prepare("
            INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability, page_quantity)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$prizeId, $luckyPage, $pageEnabled, $pageProbability, $pageQuantity]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => '奖品添加成功',
            'prize_id' => $prizeId
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
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
    
    // 检查是否是文件上传
    $isFileUpload = isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK;
    
    // 如果是文件上传，从$_POST获取数据；否则从$input获取
    if ($isFileUpload) {
        $data = $_POST;
    } else {
        $data = $input;
    }
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少奖品ID']);
        return;
    }
    
    try {
        // 获取page参数，决定更新哪个页面的配置
        $page = $_GET['page'] ?? 'lucky1.html';
        $luckyPage = str_replace('.html', '', $page);
        
        $db->beginTransaction();
        
        // 获取当前奖品信息
        $stmt = $db->prepare("SELECT rarity, original_probability, image_url FROM prizes WHERE id = ?");
        $stmt->execute([$data['id']]);
        $currentPrize = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentPrize) {
            throw new Exception('奖品不存在');
        }
        
        $currentRarity = $currentPrize['rarity'];
        $currentImageUrl = $currentPrize['image_url'];
        
        // 处理图片上传
        $imageUrl = $currentImageUrl; // 默认保持原有图片
        if ($isFileUpload) {
            $file = $_FILES['image_file'];
            $uploadDir = dirname(__DIR__, 2) . '/images/prizes/';
            
            // 确保上传目录存在
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'prize_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // 移动上传的文件
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $imageUrl = 'images/prizes/' . $fileName;
                
                // 删除旧图片（如果存在且是本地文件）
                if ($currentImageUrl && strpos($currentImageUrl, 'images/prizes/') === 0) {
                    $oldFilePath = dirname(__DIR__, 2) . '/' . $currentImageUrl;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
            } else {
                throw new Exception('文件上传失败');
            }
        } else if (isset($data['image_url'])) {
            $imageUrl = $data['image_url'];
        }
        
        // 处理数量字段
        $quantity = null;
        if (isset($data['quantity']) && $data['quantity'] !== '' && $data['quantity'] !== null) {
            $quantity = intval($data['quantity']);
        }
        
        // 处理概率逻辑
        $originalProbability = null;
        if (isset($data['rarity']) && $data['rarity'] === 'legendary') {
            // 如果是传说奖品，始终使用用户输入的概率作为原始概率
            $originalProbability = $data['probability'];
        }
        
        // 1. 更新prizes表（全局信息）
        $stmt = $db->prepare("
            UPDATE prizes 
            SET name = ?, icon = ?, image_url = ?, value = ?, probability = ?, 
                original_probability = ?, rarity = ?, quantity = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['icon'],
            $imageUrl,
            $data['value'],
            $data['probability'],
            $originalProbability,
            $data['rarity'] ?? 'common',
            $quantity,
            $data['id']
        ]);
        
        // 2. 更新或插入prize_lucky_pages表（页面特定配置）
        // 检查是否已存在关联
        $stmt = $db->prepare("
            SELECT id FROM prize_lucky_pages 
            WHERE prize_id = ? AND lucky_page = ?
        ");
        $stmt->execute([$data['id'], $luckyPage]);
        $exists = $stmt->fetch();
        
        $pageEnabled = $data['active'] ?? 1;
        $pageProbability = isset($data['page_probability']) && $data['page_probability'] !== '' 
            ? $data['page_probability'] 
            : null;
        $pageQuantity = isset($data['page_quantity']) && $data['page_quantity'] !== '' 
            ? intval($data['page_quantity']) 
            : null;
        
        if ($exists) {
            // 更新现有关联
            $stmt = $db->prepare("
                UPDATE prize_lucky_pages 
                SET enabled = ?, page_probability = ?, page_quantity = ?
                WHERE prize_id = ? AND lucky_page = ?
            ");
            $stmt->execute([$pageEnabled, $pageProbability, $pageQuantity, $data['id'], $luckyPage]);
        } else {
            // 创建新关联
            $stmt = $db->prepare("
                INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability, page_quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$data['id'], $luckyPage, $pageEnabled, $pageProbability, $pageQuantity]);
        }
        
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => '奖品更新成功']);
        
    } catch (Exception $e) {
        $db->rollBack();
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
        // 获取page参数，决定切换哪个页面的启用状态
        $page = $_GET['page'] ?? 'lucky1.html';
        $luckyPage = str_replace('.html', '', $page);
        
        // 检查是否已存在关联
        $stmt = $db->prepare("
            SELECT id FROM prize_lucky_pages 
            WHERE prize_id = ? AND lucky_page = ?
        ");
        $stmt->execute([$input['id'], $luckyPage]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // 更新现有关联的启用状态
            $stmt = $db->prepare("
                UPDATE prize_lucky_pages 
                SET enabled = ?
                WHERE prize_id = ? AND lucky_page = ?
            ");
            $stmt->execute([$input['active'] ? 1 : 0, $input['id'], $luckyPage]);
        } else {
            // 创建新关联（使用默认概率）
            $stmt = $db->prepare("
                INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability)
                VALUES (?, ?, ?, NULL)
            ");
            $stmt->execute([$input['id'], $luckyPage, $input['active'] ? 1 : 0]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => '奖品状态更新成功',
            'note' => '此操作仅影响当前页面(' . $luckyPage . ')的启用状态'
        ]);
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
        
        // 获取用户信息用于日志记录
        $stmt = $db->prepare("SELECT username, user_type FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => '用户不存在']);
            return;
        }
        
        // 防止删除最后一个超级管理员
        if ($user['user_type'] === 'super_admin') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'super_admin' AND status = 'active' AND id != ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode(['error' => '不能删除最后一个超级管理员']);
                return;
            }
        }
        
        // 删除用户（数据库会自动级联删除以下相关数据）：
        // - user_inventory (用户背包)
        // - draw_history (抽奖历史)
        // - user_coins (用户金币)
        // - customer_service_sessions (客服会话)
        // - customer_service_messages (客服消息)
        // - service_user_assignments (客服分配)
        // - recharge_records (充值记录)
        // - user_checkin (签到记录)
        // - user_draw_history (用户抽奖历史)
        // - user_game_history (游戏历史)
        // - decompose_history (分解历史)
        // - user_settings (用户设置)
        // - coin_change_log (金币变动日志)
        // - withdrawal_requests (提现请求)
        // - recharge_requests (充值请求)
        // - shop_orders (商城订单)
        // - payment_orders (支付订单)
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        // 提交事务
        $db->commit();
        
        // 记录删除日志
        error_log("管理员删除用户: ID={$id}, Username={$user['username']}, Type={$user['user_type']}");
        
        echo json_encode([
            'success' => true,
            'message' => '用户及其所有相关数据已删除'
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        $db->rollBack();
        error_log("删除用户失败: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '删除用户失败: ' . $e->getMessage()]);
    }
}

function updateUser() {
    global $db;
    
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $boundCoins = $_POST['bound_coins'] ?? null;
    $unboundCoins = $_POST['unbound_coins'] ?? null;
    
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
        
        // 更新绑定和非绑定金币，同时自动计算总余额
        if ($boundCoins !== null && is_numeric($boundCoins)) {
            $fields[] = "bound_coins = ?";
            $values[] = floatval($boundCoins);
        }
        
        if ($unboundCoins !== null && is_numeric($unboundCoins)) {
            $fields[] = "unbound_coins = ?";
            $values[] = floatval($unboundCoins);
        }
        
        // 如果更新了任一金币字段，重新计算总余额
        if ($boundCoins !== null || $unboundCoins !== null) {
            // 获取当前值
            $stmt = $db->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $newBound = $boundCoins !== null ? floatval($boundCoins) : floatval($current['bound_coins']);
            $newUnbound = $unboundCoins !== null ? floatval($unboundCoins) : floatval($current['unbound_coins']);
            $newBalance = $newBound + $newUnbound;
            
            $fields[] = "balance = ?";
            $values[] = $newBalance;
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
        // 获取page参数
        $page = $_GET['page'] ?? 'lucky1.html';
        $luckyPage = str_replace('.html', '', $page);
        
        // 获取要删除的奖品信息（包括图片路径）
        $stmt = $db->prepare("SELECT id, name, rarity, image_url FROM prizes WHERE id = ?");
        $stmt->execute([$id]);
        $prizeToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prizeToDelete) {
            http_response_code(404);
            echo json_encode(['error' => '奖品不存在']);
            return;
        }
        
        // 检查是否只是从当前页面移除，还是完全删除奖品
        $removeFromPage = $_GET['remove_from_page'] ?? false;
        
        if ($removeFromPage) {
            // 只从当前页面移除关联
            $stmt = $db->prepare("DELETE FROM prize_lucky_pages WHERE prize_id = ? AND lucky_page = ?");
            $stmt->execute([$id, $luckyPage]);
            
            echo json_encode([
                'success' => true, 
                'message' => '已从当前页面移除奖品',
                'note' => '奖品仍存在于其他页面'
            ]);
        } else {
            // 完全删除奖品（会自动删除所有页面关联，因为有外键约束）
            
            // 先删除图片文件（如果存在且是本地文件）
            if (!empty($prizeToDelete['image_url'])) {
                $imageUrl = $prizeToDelete['image_url'];
                
                // 检查是否是本地图片（在images/prizes/目录下）
                if (strpos($imageUrl, 'images/prizes/') === 0) {
                    $imagePath = dirname(__DIR__, 2) . '/' . $imageUrl;
                    
                    // 删除图片文件
                    if (file_exists($imagePath)) {
                        if (unlink($imagePath)) {
                            error_log("成功删除图片: {$imagePath}");
                        } else {
                            error_log("删除图片失败: {$imagePath}");
                        }
                    }
                }
            }
            
            // 删除数据库记录
            $stmt = $db->prepare("DELETE FROM prizes WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => '奖品已完全删除',
                'note' => '已从所有页面移除，图片文件已删除'
            ]);
        }
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
                
                // 读取页面描述
                $description = extractPageDescription($file);
                if (!$description) {
                    $description = '抽取心爱的大红';
                }
                
                // 提取中心展示图片（优先）
                $showcaseImage = extractShowcaseImage($file);
                
                // 如果没有中心展示图片，尝试获取缩略图
                $thumbImage = $showcaseImage ?: getPageThumbImage($fileName);
                
                $pages[] = [
                    'fileName' => $fileName,
                    'displayName' => $displayName,
                    'description' => $description,
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
        // 确保没有活跃的事务（清理可能存在的遗留事务）
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $filePath = $pagesDir . $fileName;
        
        // 检查文件是否存在
        $fileExists = file_exists($filePath);
        
        // 开始事务
        $db->beginTransaction();
        
        // 1. 删除对应的独立奖品表（如 lucky1_prizes）
        $tableName = str_replace('.html', '_prizes', $fileName);
        $tableName = str_replace('-', '_', $tableName);
        
        // 检查表是否存在
        $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
        $result = $db->query($checkTableSQL);
        if ($result->rowCount() > 0) {
            $dropTableSQL = "DROP TABLE `{$tableName}`";
            $db->exec($dropTableSQL);
        }
        
        // 2. 检查limited_drops表是否存在page_name字段，如果存在则删除
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'limited_drops' AND COLUMN_NAME = 'page_name'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $stmt = $db->prepare("DELETE FROM limited_drops WHERE page_name = ?");
            $stmt->execute([$fileName]);
        }
        
        // 3. 删除该页面的抽奖价格配置
        $stmt = $db->prepare("DELETE FROM draw_prices WHERE page_name = ?");
        $stmt->execute([$fileName]);
        
        // 4. 删除该页面的价格历史
        $stmt = $db->prepare("DELETE FROM price_history WHERE page_name = ?");
        $stmt->execute([$fileName]);
        
        // 提交事务（在删除文件之前）
        $db->commit();
        
        // 5. 删除HTML文件（在事务外执行，避免文件操作失败影响数据库）
        if ($fileExists) {
            @unlink($filePath);
        }
        
        // 6. 删除对应的缩略图（如果存在）
        $imagesDir = dirname(__DIR__, 2) . '/images/thumbs/';
        $pageBaseName = pathinfo($fileName, PATHINFO_FILENAME);
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach ($extensions as $ext) {
            $thumbPath = $imagesDir . $pageBaseName . '.' . $ext;
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
        }
        
        // 7. 删除对应的大图（如果存在）
        $imagesDir = dirname(__DIR__, 2) . '/images/';
        foreach ($extensions as $ext) {
            // 查找所有以页面名开头的图片文件
            $pattern = $imagesDir . $pageBaseName . '_*.' . $ext;
            $files = glob($pattern);
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Lucky页面及相关数据删除成功']);
    } catch (Exception $e) {
        // 回滚事务（只在事务活跃时）
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(200); // 改为200，让前端认为是"成功"的响应
        echo json_encode([
            'success' => false, 
            'error' => '⚠️ 为防止误删，请再次点击删除按钮确认删除此页面',
            'needConfirm' => true
        ]);
    }
}

// 辅助函数：从HTML文件中提取页面标题
function extractPageTitle($filePath) {
    try {
        $content = file_get_contents($filePath);
        
        // 优先从页面内的 <h2 class="neon-text rainbow"> 标签提取标题
        if (preg_match('/<h2[^>]*class="[^"]*neon-text[^"]*rainbow[^"]*"[^>]*>(.*?)<\/h2>/s', $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        // 备用方案：从 <title> 标签提取
        if (preg_match('/<title>(.*?) - 幸运降临<\/title>/', $content, $matches)) {
            return $matches[1];
        }
        
        // 再备用：从任意 <title> 标签提取
        if (preg_match('/<title>(.*?)<\/title>/', $content, $matches)) {
            $title = $matches[1];
            // 移除常见的后缀
            $title = preg_replace('/ - (幸运降临|幸运掉落|大红行动)$/', '', $title);
            return trim($title);
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// 辅助函数：从HTML文件中提取页面描述
function extractPageDescription($filePath) {
    try {
        $content = file_get_contents($filePath);
        
        // 从 game-header 区域的 <p class="neon-text"> 标签提取描述
        if (preg_match('/<div class="game-header">.*?<p class="neon-text">(.*?)<\/p>/s', $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// 辅助函数：从HTML文件中提取中心展示图片
function extractShowcaseImage($filePath) {
    try {
        $content = file_get_contents($filePath);
        
        // 从 showcase-icon 中提取图片 src
        if (preg_match('/<div class="showcase-icon">.*?<img[^>]+src="([^"]+)"[^>]*>/s', $content, $matches)) {
            $imageSrc = $matches[1];
            
            // 如果包含占位符（如 ${...}），则忽略
            if (strpos($imageSrc, '${') !== false || strpos($imageSrc, '$') !== false) {
                return null;
            }
            
            // 保持 ../ 前缀，因为 main.html 在 pages 目录下
            return $imageSrc;
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
            // 返回相对于 pages 目录的路径（需要 ../ 前缀）
            return '../images/thumbs/' . $pageBaseName . '.' . $ext;
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
                'thumbImage' => '../images/thumbs/' . $newFileName
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
        
        // 获取抽奖记录，关联coin_change_log获取金币类型信息
        $stmt = $db->prepare("
            SELECT 
                lr.id,
                lr.user_id,
                lr.game_type,
                lr.cost,
                lr.reward,
                lr.result,
                lr.created_at,
                ccl.coin_type,
                ccl.bound_change,
                ccl.unbound_change
            FROM lottery_records lr
            LEFT JOIN coin_change_log ccl ON ccl.related_id = lr.id AND ccl.change_type = 'draw' AND ccl.user_id = lr.user_id
            WHERE lr.user_id = ?
            ORDER BY lr.created_at DESC
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
        // 获取总数 - 排除抽奖记录
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM coin_change_log WHERE user_id = ? AND change_type != 'draw'");
        $countStmt->execute([$userId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 获取交易记录 - 使用coin_change_log表获取详细信息，排除抽奖记录
        $stmt = $db->prepare("
            SELECT 
                id,
                user_id,
                change_type as type,
                coin_type,
                bound_change,
                unbound_change,
                (bound_change + unbound_change) as amount,
                description,
                created_at
            FROM coin_change_log 
            WHERE user_id = ? AND change_type != 'draw'
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
    // Session已在文件开头启动，不需要再次启动
    
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
    // Session已在文件开头启动，不需要再次启动
    
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
    
    // Session已在文件开头启动，不需要再次启动
    
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
    
    // Session已在文件开头启动，不需要再次启动
    
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
        
        // 商店页面
        $shopPages = glob($projectRoot . '/pages/shop/*.html');
        $filesToUpdate = array_merge($filesToUpdate, $shopPages);
        
        // 更新每个文件
        foreach ($filesToUpdate as $filePath) {
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $originalContent = $content;
                
                // 1. 替换title标签中的主题名称（动态匹配任何内容）
                // 匹配 <title>XXX - 任何主题名</title> 或 <title>任何主题名 - XXX</title> 或 <title>任何主题名</title>
                $content = preg_replace_callback('/<title>([^<]+)<\/title>/i', function($matches) use ($themeName) {
                    $titleContent = $matches[1];
                    
                    // 如果标题包含 " - "，保留前缀或后缀
                    if (strpos($titleContent, ' - ') !== false) {
                        $parts = explode(' - ', $titleContent, 2);
                        // 判断主题名在前还是在后
                        // 如果第一部分是常见的页面名称，主题名在后面
                        $pageNames = ['登录', '注册', '个人中心', '充值', '签到', '仓库', '管理', '配置', '用户', '奖品', '抽奖', '客服', '商店', '皮肤兑换', '传说级兑换', '1:1跑刀', '金牌护航'];
                        $isPageNameFirst = false;
                        foreach ($pageNames as $pageName) {
                            if (strpos($parts[0], $pageName) !== false) {
                                $isPageNameFirst = true;
                                break;
                            }
                        }
                        
                        if ($isPageNameFirst) {
                            // 页面名 - 主题名
                            return '<title>' . $parts[0] . ' - ' . $themeName . '</title>';
                        } else {
                            // 主题名 - 页面名
                            return '<title>' . $themeName . ' - ' . $parts[1] . '</title>';
                        }
                    } else {
                        // 没有分隔符，直接替换为主题名
                        return '<title>' . $themeName . '</title>';
                    }
                }, $content);
                
                // 2. 替换导航栏中的品牌名称（h1标签）
                // 匹配 <h1 ...>任何内容</h1>，只替换nav-brand内的h1
                $content = preg_replace_callback('/(<div[^>]*?nav-brand[^>]*?>.*?<h1[^>]*?>)([^<]+)(<\/h1>)/is', function($matches) use ($themeName) {
                    return $matches[1] . $themeName . $matches[3];
                }, $content);
                
                // 如果上面没匹配到，尝试直接匹配h1（针对简单结构）
                if ($content === $originalContent) {
                    $content = preg_replace('/(<h1[^>]*?class="[^"]*neon-text[^"]*"[^>]*?>)([^<]+)(<\/h1>)/i', '$1' . $themeName . '$3', $content);
                }
                
                // 4. 特殊处理index.html中的内联样式h1标签（XX俱乐部）
                // 匹配 <h1 class="neon-text gold" style="...">XX俱乐部</h1>
                $content = preg_replace('/(<h1[^>]*?class="[^"]*neon-text[^"]*"[^>]*?style="[^"]*"[^>]*?>)[^<]+俱乐部(<\/h1>)/i', '$1' . $themeName . '$2', $content);
                
                // 5. 替换主页中心的欢迎标题（id="welcomeTitle"）
                // 匹配 <h2 id="welcomeTitle" ...>欢迎来到XXX</h2>
                $content = preg_replace_callback('/(<h2[^>]*?id="welcomeTitle"[^>]*?>)欢迎来到[^<]+(<\/h2>)/i', function($matches) use ($themeName) {
                    return $matches[1] . '欢迎来到' . $themeName . $matches[2];
                }, $content);
                
                // 保存文件
                if ($content !== $originalContent && file_put_contents($filePath, $content) !== false) {
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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
    
    // 检查是否是文件上传请求
    $isFileUpload = isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] === UPLOAD_ERR_OK;
    
    if ($isFileUpload) {
        // 处理文件上传
        $id = $_POST['id'] ?? null;
        $iconKey = $_POST['icon_key'] ?? null;
        
        if (!$id || !$iconKey) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '缺少必要参数'
            ]);
            return;
        }
        
        // 验证文件类型
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        $fileType = $_FILES['icon_image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '不支持的图片格式，仅支持 PNG、JPG、GIF、WebP'
            ]);
            return;
        }
        
        // 检查文件大小（最大2MB）
        if ($_FILES['icon_image']['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '图片文件过大，最大支持2MB'
            ]);
            return;
        }
        
        // 生成文件名
        $extension = pathinfo($_FILES['icon_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'icon_' . $iconKey . '_' . time() . '.' . $extension;
        
        // 确保目录存在
        $uploadDir = dirname(__DIR__, 2) . '/images/shop/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . $fileName;
        
        // 移动上传的文件
        if (!move_uploaded_file($_FILES['icon_image']['tmp_name'], $uploadPath)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => '文件上传失败'
            ]);
            return;
        }
        
        // 更新数据库，使用相对路径
        $iconUrl = 'images/shop/' . $fileName;
        
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
                $iconUrl,
                $_POST['fallback_icon'] ?? '🎁',
                $_POST['description'] ?? '',
                $id,
                $iconKey
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => '图标更新成功',
                'icon_url' => $iconUrl
            ]);
        } catch (Exception $e) {
            // 如果数据库更新失败，删除已上传的文件
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => '更新图标失败: ' . $e->getMessage()
            ]);
        }
    } else {
        // 处理URL/相对路径输入
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
}

// ========== 实时监控功能 ==========

function getMonitorData() {
    global $db;
    
    try {
        // 1. 在线用户数量（5分钟内有活动的用户）
        $db->query("
            UPDATE users 
            SET is_online = 0 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE is_online = 1
        ");
        $onlineUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // 2. 今日订单统计（充值、提现、兑换）
        $stmt = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM recharge_history WHERE DATE(created_at) = CURDATE()) as recharge_count,
                (SELECT COUNT(*) FROM withdrawal_requests WHERE DATE(created_at) = CURDATE()) as withdrawal_count,
                (SELECT COUNT(*) FROM shop_purchase_history WHERE DATE(created_at) = CURDATE()) as purchase_count
        ");
        $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $todayOrders = ($orderStats['recharge_count'] ?? 0) + 
                       ($orderStats['withdrawal_count'] ?? 0) + 
                       ($orderStats['purchase_count'] ?? 0);
        
        // 3. 待处理订单数量
        $stmt = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM withdrawal_requests WHERE status IN ('pending', 'processing')) as pending_withdrawal,
                (SELECT COUNT(*) FROM shop_purchase_history WHERE status IN ('pending', 'processing')) as pending_purchase
        ");
        $pendingStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $pendingOrders = ($pendingStats['pending_withdrawal'] ?? 0) + 
                        ($pendingStats['pending_purchase'] ?? 0);
        
        // 4. 今日收支分析
        // 收入：充值总额（coins_gained字段）
        $stmt = $db->query("
            SELECT COALESCE(SUM(coins_gained), 0) as total_income 
            FROM recharge_history 
            WHERE DATE(created_at) = CURDATE() AND status = 'completed'
        ");
        $todayIncome = $stmt->fetch(PDO::FETCH_ASSOC)['total_income'];
        
        // 支出：提现金币 + 商品兑换价值
        $stmt = $db->query("
            SELECT 
                COALESCE((SELECT SUM(amount) FROM withdrawal_requests 
                         WHERE DATE(processed_at) = CURDATE() AND status = 'completed'), 0) as withdrawal_expense,
                COALESCE((SELECT SUM(price) FROM shop_purchase_history 
                         WHERE DATE(processed_at) = CURDATE() AND status = 'completed'), 0) as purchase_expense
        ");
        $expenseStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $todayExpense = ($expenseStats['withdrawal_expense'] ?? 0) + 
                       ($expenseStats['purchase_expense'] ?? 0);
        
        // 5. 客服用户监测（包含待处理订单数量）
        $stmt = $db->query("
            SELECT 
                u.id,
                u.username,
                u.nickname,
                u.is_online,
                u.last_activity,
                (
                    SELECT COUNT(*) 
                    FROM withdrawal_requests wr 
                    WHERE wr.processed_by = u.id AND wr.status IN ('pending', 'processing')
                ) + (
                    SELECT COUNT(*) 
                    FROM shop_purchase_history sph 
                    WHERE sph.processed_by = u.id AND sph.status IN ('pending', 'processing')
                ) as pending_count
            FROM users u
            WHERE u.user_type = 'service'
            ORDER BY u.is_online DESC, pending_count DESC
        ");
        $serviceUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 6. 待处理订单（只显示pending和processing状态）
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $recentOrders = [];
        
        // 获取待处理提现订单
        $stmt = $db->query("
            SELECT 
                'withdrawal' as type,
                u.username,
                wr.amount,
                wr.status,
                wr.created_at
            FROM withdrawal_requests wr
            JOIN users u ON wr.user_id = u.id
            WHERE wr.status IN ('pending', 'processing')
            ORDER BY wr.created_at DESC
        ");
        $withdrawalOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取待处理商城购买订单
        $stmt = $db->query("
            SELECT 
                'shop_purchase' as type,
                u.username,
                sph.price as amount,
                sph.status,
                sph.created_at
            FROM shop_purchase_history sph
            JOIN users u ON sph.user_id = u.id
            WHERE sph.status IN ('pending', 'processing')
            ORDER BY sph.created_at DESC
        ");
        $purchaseOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 合并并排序所有待处理订单
        $allOrders = array_merge($withdrawalOrders, $purchaseOrders);
        usort($allOrders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // 计算总数和总页数
        $totalOrders = count($allOrders);
        $totalPages = ceil($totalOrders / $perPage);
        
        // 获取当前页的订单
        $recentOrders = array_slice($allOrders, $offset, $perPage);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'onlineUsers' => intval($onlineUsers),
                'todayOrders' => intval($todayOrders),
                'pendingOrders' => intval($pendingOrders),
                'todayIncome' => floatval($todayIncome),
                'todayExpense' => floatval($todayExpense),
                'serviceUsers' => $serviceUsers,
                'recentOrders' => $recentOrders,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalOrders' => $totalOrders,
                    'perPage' => $perPage
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => '获取监控数据失败: ' . $e->getMessage()
        ]);
    }
}

// ========== Lucky页面合并管理函数 ==========

// 获取合并组列表
function getMergeGroups() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT g.*, 
                   COUNT(p.id) as page_count
            FROM lucky_merge_groups g
            LEFT JOIN lucky_pages_meta p ON g.id = p.merge_group_id
            WHERE g.is_active = 1
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'groups' => $groups]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取合并组列表失败: ' . $e->getMessage()]);
    }
}

// 创建合并组
function createMergeGroup() {
    global $db;
    
    // 处理FormData上传，使用$_POST和$_FILES
    $groupName = $_POST['groupName'] ?? '';
    $groupIcon = $_POST['groupIcon'] ?? '🎰';
    $description = $_POST['description'] ?? '';
    
    if (!$groupName) {
        http_response_code(400);
        echo json_encode(['error' => '合并组名称不能为空']);
        return;
    }
    
    try {
        $groupThumb = null;
        
        // 处理封面图片上传
        if (isset($_FILES['groupThumb']) && $_FILES['groupThumb']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['groupThumb'];
            
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
            $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $newFileName = 'group_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadDir = dirname(__DIR__, 2) . '/images/thumbs/';
            
            // 确保目录存在
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $targetPath = $uploadDir . $newFileName;
            
            // 移动上传的文件
            if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                $groupThumb = 'images/thumbs/' . $newFileName;
            } else {
                http_response_code(500);
                echo json_encode(['error' => '图片上传失败']);
                return;
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO lucky_merge_groups (group_name, group_icon, group_thumb, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$groupName, $groupIcon, $groupThumb, $description]);
        
        $groupId = $db->lastInsertId();
        echo json_encode(['success' => true, 'groupId' => $groupId, 'message' => '合并组创建成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '创建合并组失败: ' . $e->getMessage()]);
    }
}

// 更新合并组
function updateMergeGroup() {
    global $db, $input;
    
    $groupId = $input['groupId'] ?? 0;
    $groupName = $input['groupName'] ?? '';
    $groupIcon = $input['groupIcon'] ?? '🎰';
    $description = $input['description'] ?? '';
    
    if (!$groupId || !$groupName) {
        http_response_code(400);
        echo json_encode(['error' => '参数不完整']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE lucky_merge_groups 
            SET group_name = ?, group_icon = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$groupName, $groupIcon, $description, $groupId]);
        
        echo json_encode(['success' => true, 'message' => '合并组更新成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '更新合并组失败: ' . $e->getMessage()]);
    }
}

// 删除合并组
function deleteMergeGroup() {
    global $db, $input;
    
    $groupId = $input['groupId'] ?? 0;
    
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['error' => '合并组ID不能为空']);
        return;
    }
    
    try {
        // 先将该组内的页面设置为独立显示
        $stmt = $db->prepare("
            UPDATE lucky_pages_meta 
            SET merge_group_id = NULL, merge_order = 0
            WHERE merge_group_id = ?
        ");
        $stmt->execute([$groupId]);
        
        // 删除合并组
        $stmt = $db->prepare("DELETE FROM lucky_merge_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        
        echo json_encode(['success' => true, 'message' => '合并组删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除合并组失败: ' . $e->getMessage()]);
    }
}

// 更新页面合并关系
function updatePageMerge() {
    global $db, $input;
    
    $pages = $input['pages'] ?? [];
    $targetGroupId = $input['targetGroupId'] ?? null;
    
    if (empty($pages)) {
        http_response_code(400);
        echo json_encode(['error' => '页面列表不能为空']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // 如果指定了目标合并组，先将该组原有的页面设置为独立显示
        if ($targetGroupId) {
            $stmt = $db->prepare("
                UPDATE lucky_pages_meta 
                SET merge_group_id = NULL, merge_order = 0
                WHERE merge_group_id = ?
            ");
            $stmt->execute([$targetGroupId]);
        }
        
        foreach ($pages as $page) {
            $fileName = $page['fileName'] ?? '';
            $mergeGroupId = $page['mergeGroupId'] ?? null;
            $mergeOrder = $page['mergeOrder'] ?? 0;
            
            if (!$fileName) continue;
            
            // 从HTML文件中提取实际的页面标题
            $filePath = dirname(__DIR__, 2) . '/pages/' . $fileName;
            $displayName = extractPageTitle($filePath);
            if (!$displayName) {
                // 如果无法提取标题，使用文件名
                $displayName = str_replace('.html', '', $fileName);
            }
            
            // 检查页面元数据是否存在
            $stmt = $db->prepare("SELECT id FROM lucky_pages_meta WHERE file_name = ?");
            $stmt->execute([$fileName]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // 更新（同时更新display_name）
                $stmt = $db->prepare("
                    UPDATE lucky_pages_meta 
                    SET display_name = ?, merge_group_id = ?, merge_order = ?
                    WHERE file_name = ?
                ");
                $stmt->execute([$displayName, $mergeGroupId, $mergeOrder, $fileName]);
            } else {
                // 插入
                $stmt = $db->prepare("
                    INSERT INTO lucky_pages_meta (file_name, display_name, merge_group_id, merge_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$fileName, $displayName, $mergeGroupId, $mergeOrder]);
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => '页面合并关系更新成功']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => '更新页面合并关系失败: ' . $e->getMessage()]);
    }
}

// 获取合并后的页面列表（用于main.html）
function listLuckyPagesMerged() {
    global $db;
    try {
        $items = [];
        
        // 1. 获取所有合并组
        $stmt = $db->query("
            SELECT id, group_name, group_icon, group_thumb, description
            FROM lucky_merge_groups
            WHERE is_active = 1
            ORDER BY id ASC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($groups as $group) {
            // 获取该组的第一个页面作为入口
            $stmt = $db->prepare("
                SELECT file_name, display_name, thumb_image
                FROM lucky_pages_meta
                WHERE merge_group_id = ? AND is_active = 1
                ORDER BY merge_order ASC
                LIMIT 1
            ");
            $stmt->execute([$group['id']]);
            $firstPage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($firstPage) {
                // 自动选择modle图片：按照合并组ID选择 modle1.png, modle2.png 等
                $modleImage = "images/modle/modle{$group['id']}.png";
                $modleImagePath = dirname(__DIR__, 2) . '/' . $modleImage;
                
                // 如果modle图片不存在，使用默认的modle1.png
                if (!file_exists($modleImagePath)) {
                    $modleImage = "images/modle/modle1.png";
                }
                
                $items[] = [
                    'type' => 'group',
                    'groupId' => $group['id'],
                    'fileName' => $firstPage['file_name'],
                    'displayName' => $group['group_name'],
                    'description' => $group['description'],
                    'icon' => $group['group_icon'],
                    'thumbImage' => $modleImage  // 使用modle图片作为封面
                ];
            }
        }
        
        // 2. 获取所有独立页面（未合并的）
        $stmt = $db->query("
            SELECT file_name, display_name, description, thumb_image
            FROM lucky_pages_meta
            WHERE (merge_group_id IS NULL OR merge_group_id = 0) AND is_active = 1
            ORDER BY file_name ASC
        ");
        $independentPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($independentPages as $page) {
            $items[] = [
                'type' => 'page',
                'fileName' => $page['file_name'],
                'displayName' => $page['display_name'],
                'description' => $page['description'] ?: '抽取心爱的大红',
                'icon' => '🍎',
                'thumbImage' => $page['thumb_image']
            ];
        }
        
        // 3. 获取数据库中不存在的页面（兼容旧系统）
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        if (is_dir($pagesDir)) {
            $files = glob($pagesDir . 'lucky*.html');
            $existingFiles = array_column($independentPages, 'file_name');
            
            // 获取合并组中的文件
            foreach ($groups as $group) {
                $stmt = $db->prepare("SELECT file_name FROM lucky_pages_meta WHERE merge_group_id = ?");
                $stmt->execute([$group['id']]);
                $groupFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $existingFiles = array_merge($existingFiles, $groupFiles);
            }
            
            foreach ($files as $file) {
                $fileName = basename($file);
                if (!in_array($fileName, $existingFiles)) {
                    $displayName = extractPageTitle($file) ?: str_replace('.html', '', $fileName);
                    $items[] = [
                        'type' => 'page',
                        'fileName' => $fileName,
                        'displayName' => $displayName,
                        'description' => '抽取心爱的大红',
                        'icon' => '🍎',
                        'thumbImage' => null
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取页面列表失败: ' . $e->getMessage()]);
    }
}

// 获取页面合并信息（用于lucky页面判断是否显示选项卡）
function getPageMergeInfo() {
    global $db;

    $fileName = $_GET['page'] ?? '';

    if (!$fileName) {
        http_response_code(400);
        echo json_encode(['error' => '页面文件名不能为空']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT merge_group_id, merge_order
            FROM lucky_pages_meta
            WHERE file_name = ? AND is_active = 1
        ");
        $stmt->execute([$fileName]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        // 根据文件名生成thumbs图片路径（lucky1.html -> images/thumbs/lucky1.png）
        $thumbImage = null;
        if (preg_match('/^(lucky\d+)\.html$/', $fileName, $matches)) {
            $baseName = $matches[1];
            $thumbPath = "images/thumbs/{$baseName}.png";
            $fullPath = dirname(__DIR__, 2) . '/' . $thumbPath;

            // 检查文件是否存在
            if (file_exists($fullPath)) {
                $thumbImage = $thumbPath;
            }
        }

        if ($page && $page['merge_group_id']) {
            echo json_encode([
                'success' => true,
                'mergeGroupId' => $page['merge_group_id'],
                'mergeOrder' => $page['merge_order'],
                'thumbImage' => $thumbImage
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'mergeGroupId' => null,
                'thumbImage' => $thumbImage
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取页面合并信息失败: ' . $e->getMessage()]);
    }
}


// 获取合并组内的页面列表（用于lucky页面选项卡）
function getGroupPages() {
    global $db;
    
    $groupId = $_GET['groupId'] ?? 0;
    
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['error' => '合并组ID不能为空']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT file_name, display_name, thumb_image
            FROM lucky_pages_meta
            WHERE merge_group_id = ? AND is_active = 1
            ORDER BY merge_order ASC
        ");
        $stmt->execute([$groupId]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'pages' => $pages]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取合并组页面列表失败: ' . $e->getMessage()]);
    }
}

// 同步所有页面的标题（从HTML文件中提取）
function syncPageTitles() {
    global $db;
    
    try {
        $pagesDir = dirname(__DIR__, 2) . '/pages/';
        $updated = 0;
        
        // 获取所有lucky页面的元数据
        $stmt = $db->query("SELECT file_name FROM lucky_pages_meta");
        $pages = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($pages as $fileName) {
            $filePath = $pagesDir . $fileName;
            
            if (file_exists($filePath)) {
                // 从HTML文件中提取标题
                $displayName = extractPageTitle($filePath);
                
                if ($displayName) {
                    // 更新数据库
                    $stmt = $db->prepare("UPDATE lucky_pages_meta SET display_name = ? WHERE file_name = ?");
                    $stmt->execute([$displayName, $fileName]);
                    $updated++;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "成功同步 {$updated} 个页面的标题",
            'updated' => $updated
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '同步页面标题失败: ' . $e->getMessage()]);
    }
}
?>
