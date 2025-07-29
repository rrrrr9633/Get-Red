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
        $stmt = $db->query("SELECT * FROM prizes ORDER BY probability DESC");
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'prizes' => $prizes]);
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
        $stmt = $db->prepare("INSERT INTO prizes (name, icon, image_url, value, probability, rarity, game_type, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['icon'],
            $input['image_url'] ?? null,
            $input['value'],
            $input['probability'],
            $input['rarity'] ?? 'common',
            'lucky_drop',
            $input['active'] ?? 1
        ]);
        
        echo json_encode(['success' => true, 'message' => '奖品添加成功']);
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
        $stmt = $db->prepare("UPDATE prizes SET name = ?, icon = ?, image_url = ?, value = ?, probability = ?, rarity = ?, active = ? WHERE id = ?");
        $stmt->execute([
            $input['name'],
            $input['icon'],
            $input['image_url'] ?? null,
            $input['value'],
            $input['probability'],
            $input['rarity'] ?? 'common',
            $input['active'] ?? 1,
            $input['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => '奖品更新成功']);
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
        $stmt = $db->prepare("UPDATE prizes SET active = ? WHERE id = ?");
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

function deletePrize() {
    global $db;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少奖品ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM prizes WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => '奖品删除成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '删除奖品失败: ' . $e->getMessage()]);
    }
}
?>
