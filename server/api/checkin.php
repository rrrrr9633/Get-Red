<?php
session_start();
require_once '../config/database.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取数据库连接
$database = new Database();
$db = $database->getConnection();

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '用户未登录']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch($action) {
    case 'status':
        getCheckinStatus();
        break;
    case 'checkin':
        doCheckin();
        break;
    case 'calendar':
        getCheckinCalendar();
        break;
    case 'history':
        getCheckinHistory();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '无效的操作']);
        break;
}

// 获取签到状态
function getCheckinStatus() {
    global $db, $userId;
    
    try {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        // 检查今天是否已签到
        $stmt = $db->prepare("SELECT id FROM user_checkin WHERE user_id = ? AND checkin_date = ?");
        $stmt->execute([$userId, $today]);
        $todayCheckin = $stmt->fetch();
        
        // 获取连续签到天数
        $consecutiveDays = getConsecutiveDays($userId);
        
        // 获取本月签到天数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_checkin WHERE user_id = ? AND DATE_FORMAT(checkin_date, '%Y-%m') = ?");
        $stmt->execute([$userId, $thisMonth]);
        $monthlyCount = $stmt->fetch()['count'];
        
        // 获取累计签到天数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_checkin WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalCount = $stmt->fetch()['count'];
        
        // 计算今日奖励
        $todayReward = calculateReward($consecutiveDays + 1);
        
        echo json_encode([
            'success' => true,
            'hasCheckedIn' => $todayCheckin ? true : false,
            'consecutiveDays' => $consecutiveDays,
            'monthlyDays' => $monthlyCount,
            'totalDays' => $totalCount,
            'todayReward' => $todayReward
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取签到状态失败: ' . $e->getMessage()]);
    }
}

// 执行签到
function doCheckin() {
    global $db, $userId;
    
    try {
        $today = date('Y-m-d');
        
        // 检查今天是否已签到
        $stmt = $db->prepare("SELECT id FROM user_checkin WHERE user_id = ? AND checkin_date = ?");
        $stmt->execute([$userId, $today]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => '今天已经签到过了']);
            return;
        }
        
        // 获取连续签到天数
        $consecutiveDays = getConsecutiveDays($userId);
        $newConsecutiveDays = $consecutiveDays + 1;
        
        // 计算奖励
        $reward = calculateReward($newConsecutiveDays);
        
        // 开始事务
        $db->beginTransaction();
        
        // 插入签到记录
        $stmt = $db->prepare("INSERT INTO user_checkin (user_id, checkin_date, consecutive_days, reward_amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $today, $newConsecutiveDays, $reward]);
        
        // 更新用户余额
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$reward, $userId]);
        
        // 获取更新后的用户余额
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newBalance = $stmt->fetch()['balance'];
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '签到成功！',
            'reward' => $reward,
            'consecutiveDays' => $newConsecutiveDays,
            'newBalance' => $newBalance
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['error' => '签到失败: ' . $e->getMessage()]);
    }
}

// 获取签到日历
function getCheckinCalendar() {
    global $db, $userId;
    
    try {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $stmt = $db->prepare("
            SELECT DAY(checkin_date) as day, consecutive_days, reward_amount 
            FROM user_checkin 
            WHERE user_id = ? AND YEAR(checkin_date) = ? AND MONTH(checkin_date) = ?
            ORDER BY checkin_date
        ");
        $stmt->execute([$userId, $year, $month]);
        $checkinDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'checkinDays' => $checkinDays,
            'year' => $year,
            'month' => $month
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取签到日历失败: ' . $e->getMessage()]);
    }
}

// 获取连续签到天数
function getConsecutiveDays($userId) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT checkin_date 
        FROM user_checkin 
        WHERE user_id = ? 
        ORDER BY checkin_date DESC 
        LIMIT 30
    ");
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        return 0;
    }
    
    $consecutiveDays = 0;
    $currentDate = new DateTime();
    $yesterday = clone $currentDate;
    $yesterday->sub(new DateInterval('P1D'));
    
    foreach ($records as $record) {
        $checkinDate = new DateTime($record['checkin_date']);
        
        if ($consecutiveDays === 0) {
            // 检查最近的签到是否是昨天或今天
            if ($checkinDate->format('Y-m-d') === $currentDate->format('Y-m-d') || 
                $checkinDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                $consecutiveDays = 1;
                $currentDate = $checkinDate;
            } else {
                break;
            }
        } else {
            // 检查是否连续
            $expectedDate = clone $currentDate;
            $expectedDate->sub(new DateInterval('P1D'));
            
            if ($checkinDate->format('Y-m-d') === $expectedDate->format('Y-m-d')) {
                $consecutiveDays++;
                $currentDate = $checkinDate;
            } else {
                break;
            }
        }
    }
    
    return $consecutiveDays;
}

// 计算签到奖励
function calculateReward($consecutiveDays) {
    if ($consecutiveDays <= 0) return 10;
    
    $rewards = [
        1 => 10,
        2 => 15,
        3 => 20,
        4 => 25,
        5 => 30,
        6 => 40,
        7 => 50
    ];
    
    // 7天以上都是50金币
    if ($consecutiveDays >= 7) {
        return 50;
    }
    
    return $rewards[$consecutiveDays] ?? 10;
}

// 获取签到历史记录
function getCheckinHistory() {
    global $db, $userId;
    
    try {
        $stmt = $db->prepare("
            SELECT checkin_date, consecutive_days, reward_amount, created_at 
            FROM user_checkin 
            WHERE user_id = ? 
            ORDER BY checkin_date DESC 
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'records' => $records
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '获取签到历史失败: ' . $e->getMessage()]);
    }
}
?>
