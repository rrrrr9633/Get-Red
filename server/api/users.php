<?php
// 设置CORS头
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch($method) {
    case 'POST':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'register':
                    register();
                    break;
                case 'login':
                    login();
                    break;
                case 'logout':
                    logout();
                    break;
                case 'heartbeat':
                    handleHeartbeat();
                    break;
                case 'online':
                    handleOnline();
                    break;
                case 'offline':
                    handleOffline();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
    case 'GET':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'profile':
                    getProfile();
                    break;
                case 'balance':
                    getBalance();
                    break;
                case 'transactions':
                    getTransactions();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
    case 'PUT':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'profile':
                    updateProfile();
                    break;
                case 'password':
                    changePassword();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['error' => '无效的操作']);
                    break;
            }
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不被允许']);
        break;
}

function register() {
    global $db, $input;
    
    if(!isset($input['username']) || !isset($input['password']) || !isset($input['nickname'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    // 检查用户名是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    if($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => '用户名已存在']);
        return;
    }
    
    // 创建用户（不包含手机号）
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    $avatar = isset($input['avatar']) ? $input['avatar'] : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiByeD0iNTAiIGZpbGw9IiNmZmQ3MDAiLz4KPHN2ZyB4PSIyNSIgeT0iMjAiIHdpZHRoPSI1MCIgaGVpZ2h0PSI2MCI+CjxjaXJjbGUgY3g9IjI1IiBjeT0iMjAiIHI9IjE1IiBmaWxsPSIjMTExIi8+CjxlbGxpcHNlIGN4PSIyNSIgY3k9IjUwIiByeD0iMjAiIHJ5PSIxNSIgZmlsbD0iIzExMSIvPgo8L3N2Zz4KPC9zdmc+';
    
    // 插入用户数据（不包含phone字段）
    $stmt = $db->prepare("INSERT INTO users (username, password, nickname, avatar) VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$input['username'], $hashedPassword, $input['nickname'], $avatar])) {
        $userId = $db->lastInsertId();
        
        // 记录注册奖励
        $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, 1000.00, '注册奖励', 'income')");
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '注册失败']);
    }
}

function login() {
    global $db, $input;
    
    if(!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少用户名或密码']);
        return;
    }
    
    // 只通过用户名查找用户
    $stmt = $db->prepare("SELECT id, username, password, nickname, avatar, balance FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user && password_verify($input['password'], $user['password'])) {
        // 更新用户在线状态和最后登录时间
        $updateStmt = $db->prepare("UPDATE users SET is_online = 1, last_login = NOW(), last_activity = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        unset($user['password']);
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => '用户名或密码错误']);
    }
}

function logout() {
    session_start();
    
    // 如果有用户登录，更新离线状态
    if(isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    session_destroy();
    echo json_encode(['success' => true, 'message' => '退出成功']);
}

function getProfile() {
    session_start();
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    
    // 更新用户活动时间
    $updateStmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $updateStmt->execute([$_SESSION['user_id']]);
    
    $stmt = $db->prepare("SELECT id, username, nickname, email, avatar, balance, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '用户不存在']);
    }
}

function getBalance() {
    session_start();
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result) {
        echo json_encode(['success' => true, 'balance' => $result['balance']]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => '用户不存在']);
    }
}

function updateProfile() {
    session_start();
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db, $input;
    $updateFields = [];
    $params = [];
    
    if(isset($input['nickname'])) {
        $updateFields[] = "nickname = ?";
        $params[] = $input['nickname'];
    }
    
    if(isset($input['email'])) {
        $updateFields[] = "email = ?";
        $params[] = $input['email'];
    }
    
    if(isset($input['avatar'])) {
        $updateFields[] = "avatar = ?";
        $params[] = $input['avatar'];
    }
    
    if(isset($input['balance'])) {
        $updateFields[] = "balance = ?";
        $params[] = $input['balance'];
    }
    
    if(empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => '没有要更新的字段']);
        return;
    }
    
    $params[] = $_SESSION['user_id'];
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => '更新成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '更新失败']);
    }
}

function changePassword() {
    session_start();
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db, $input;
    
    if(!isset($input['current_password']) || !isset($input['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少必要参数']);
        return;
    }
    
    // 验证当前密码
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user || !password_verify($input['current_password'], $user['password'])) {
        http_response_code(400);
        echo json_encode(['error' => '当前密码错误']);
        return;
    }
    
    // 更新密码
    $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    if($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '密码修改成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '密码修改失败']);
    }
}

// 心跳检测处理
function handleHeartbeat() {
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    // 更新最后活动时间
    $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '心跳检测成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '心跳检测失败']);
    }
}

// 设置在线状态
function handleOnline() {
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_online = 1, last_activity = NOW() WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '设置在线状态成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '设置在线状态失败']);
    }
}

// 设置离线状态
function handleOffline() {
    global $db;
    
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    if($stmt->execute([$_SESSION['user_id']])) {
        echo json_encode(['success' => true, 'message' => '设置离线状态成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '设置离线状态失败']);
    }
}

// 获取交易记录
function getTransactions() {
    session_start();
    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登录']);
        return;
    }
    
    global $db;
    
    $userId = $_SESSION['user_id'];
    $type = $_GET['type'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(50, intval($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    try {
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        
        // 根据类型过滤
        switch($type) {
            case 'draws':
                // 抽奖相关记录
                $whereClause .= " AND (description LIKE '%抽奖%' OR description LIKE '%奖励%')";
                break;
            case 'decompose':
                // 分解相关记录
                $whereClause .= " AND description LIKE '%分解%'";
                break;
            case 'financial':
                // 资金流水（排除抽奖和分解）
                $whereClause .= " AND description NOT LIKE '%抽奖%' AND description NOT LIKE '%分解%'";
                break;
            case 'all':
            default:
                // 全部记录，不添加额外条件
                break;
        }
        
        // 获取总记录数
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM transactions $whereClause");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // 获取分页记录
        $stmt = $db->prepare("
            SELECT id, amount, description, type, created_at 
            FROM transactions 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
                'limit' => $limit
            ],
            'totalPages' => $totalPages // 保持兼容性
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取交易记录失败: ' . $e->getMessage()]);
    }
}
?>
