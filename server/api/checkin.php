<?php
// 引入安全配置
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();
session_start();

require_once '../config/database.php';
require_once '../config/coin-helper.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取网络时间函数
function getNetworkTime() {
    try {
        // 使用世界时间API获取北京时间
        $timeApiUrl = 'https://worldtimeapi.org/api/timezone/Asia/Shanghai';
        $context = stream_context_create([
            'http' => [
                'timeout' => 5, // 5秒超时
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($timeApiUrl, false, $context);
        if ($response !== false) {
            $timeData = json_decode($response, true);
            if (isset($timeData['datetime'])) {
                // 返回格式：2024-01-01T12:30:45+08:00
                $datetime = new DateTime($timeData['datetime']);
                return $datetime->format('Y-m-d H:i:s');
            }
        }
    } catch (Exception $e) {
        error_log('网络时间获取失败: ' . $e->getMessage());
    }
    
    // 备用方案：使用服务器时间，但设置为中国时区
    try {
        $timezone = new DateTimeZone('Asia/Shanghai');
        $datetime = new DateTime('now', $timezone);
        return $datetime->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // 最后备用方案：使用PHP默认时间
        return date('Y-m-d H:i:s');
    }
}

// 获取当前日期（网络时间）
function getCurrentDate() {
    $networkTime = getNetworkTime();
    return substr($networkTime, 0, 10); // 只取日期部分 Y-m-d
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
        $today = getCurrentDate(); // 使用网络时间
        $thisMonth = substr($today, 0, 7); // Y-m 格式
        
        // 检查今天是否已签到
        $stmt = $db->prepare("SELECT id FROM user_checkin WHERE user_id = ? AND checkin_date = ?");
        $stmt->execute([$userId, $today]);
        $todayCheckin = $stmt->fetch();
        
        // 获取本月签到天数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_checkin WHERE user_id = ? AND DATE_FORMAT(checkin_date, '%Y-%m') = ?");
        $stmt->execute([$userId, $thisMonth]);
        $monthlyCount = $stmt->fetch()['count'];
        
        // 获取累计签到天数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_checkin WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalCount = $stmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'hasCheckedIn' => $todayCheckin ? true : false,
            'monthlyDays' => $monthlyCount,
            'maxMonthlyDays' => 7,
            'remainingCheckins' => max(0, 7 - $monthlyCount),
            'totalDays' => $totalCount,
            'rewardRange' => '1-5金币（随机）',
            'rewardProbability' => [
                '5金币' => '1%',
                '4金币' => '5%',
                '3金币' => '9%',
                '2金币' => '15%',
                '1金币' => '70%'
            ],
            'currentTime' => getNetworkTime(),
            'currentDate' => $today
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
        $today = getCurrentDate(); // 使用网络时间
        $thisMonth = substr($today, 0, 7); // Y-m 格式
        
        // 检查今天是否已签到
        $stmt = $db->prepare("SELECT id FROM user_checkin WHERE user_id = ? AND checkin_date = ?");
        $stmt->execute([$userId, $today]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => '今天已经签到过了']);
            return;
        }
        
        // 检查本月签到次数（限制每月最多7次）
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_checkin WHERE user_id = ? AND DATE_FORMAT(checkin_date, '%Y-%m') = ?");
        $stmt->execute([$userId, $thisMonth]);
        $monthlyCount = $stmt->fetch()['count'];
        
        if ($monthlyCount >= 7) {
            echo json_encode(['error' => '本月签到次数已达上限（7次）']);
            return;
        }
        
        // 随机奖励：1-5绑定金币
        $reward = calculateReward();
        
        // 开始事务
        $db->beginTransaction();
        
        // 插入签到记录，使用网络时间
        $networkTime = getNetworkTime();
        $stmt = $db->prepare("INSERT INTO user_checkin (user_id, checkin_date, consecutive_days, reward_amount, coin_type, created_at) VALUES (?, ?, 0, ?, 'bound', ?)");
        $stmt->execute([$userId, $today, $reward, $networkTime]);
        
        // 直接在当前事务中增加绑定金币（签到奖励是绑定金币）
        $stmt = $db->prepare("UPDATE users SET bound_coins = bound_coins + ?, balance = balance + ? WHERE id = ?");
        $stmt->execute([$reward, $reward, $userId]);
        
        // 记录日志
        $stmt = $db->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userCoins = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("
            INSERT INTO coin_change_log 
            (user_id, change_type, coin_type, bound_change, unbound_change, 
             bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
             related_id, description)
            VALUES (?, 'checkin', 'bound', ?, 0, ?, ?, ?, ?, NULL, ?)
        ");
        $stmt->execute([
            $userId, 
            $reward,
            $userCoins['bound_coins'] - $reward,
            $userCoins['unbound_coins'],
            $userCoins['bound_coins'],
            $userCoins['unbound_coins'],
            "每日签到奖励（本月第" . ($monthlyCount + 1) . "次）"
        ]);
        
        // 获取更新后的用户余额
        $coins = getUserCoins($db, $userId);
        if (!$coins) {
            throw new Exception('获取用户余额失败');
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "签到成功！获得{$reward}绑定金币",
            'reward' => $reward,
            'coin_type' => 'bound',
            'monthlyCount' => $monthlyCount + 1,
            'remainingCheckins' => 7 - ($monthlyCount + 1),
            'bound_coins' => $coins['bound_coins'],
            'unbound_coins' => $coins['unbound_coins'],
            'total_coins' => $coins['total_coins'],
            'checkinTime' => $networkTime
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => '签到失败: ' . $e->getMessage()]);
    }
}

// 获取签到日历
function getCheckinCalendar() {
    global $db, $userId;
    
    try {
        // 使用网络时间作为默认年月
        $networkTime = getNetworkTime();
        $currentDateTime = new DateTime($networkTime);
        
        $year = $_GET['year'] ?? $currentDateTime->format('Y');
        $month = $_GET['month'] ?? $currentDateTime->format('m');
        
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
            'month' => $month,
            'currentNetworkTime' => $networkTime // 添加网络时间用于前端显示
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
    $currentDate = new DateTime(getCurrentDate()); // 使用网络时间
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

// 计算签到奖励（随机1-5金币）
function calculateReward() {
    // 生成0-100的随机数
    $random = mt_rand(1, 10000) / 100; // 精确到小数点后两位
    
    // 概率分布：
    // 5金币: 1%   (0-1)
    // 4金币: 5%   (1-6)
    // 3金币: 9%   (6-15)
    // 2金币: 15%  (15-30)
    // 1金币: 70%  (30-100)
    
    if ($random <= 1) {
        return 5;
    } elseif ($random <= 6) {
        return 4;
    } elseif ($random <= 15) {
        return 3;
    } elseif ($random <= 30) {
        return 2;
    } else {
        return 1;
    }
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
