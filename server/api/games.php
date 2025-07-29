<?php
require_once '../config/database.php';

session_start();
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch($method) {
    case 'POST':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'lucky_drop':
                    luckyDrop();
                    break;
                case 'prize_draw':
                    prizeDraw();
                    break;
                case 'wheel':
                    wheelSpin();
                    break;
                case 'checkin':
                    dailyCheckin();
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
                case 'prizes':
                    getPrizes();
                    break;
                case 'checkin_status':
                    getCheckinStatus();
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
    default:
        http_response_code(405);
        echo json_encode(['error' => '方法不被允许']);
        break;
}

function getPrizes() {
    global $db;
    $gameType = isset($_GET['game_type']) ? $_GET['game_type'] : '';
    
    if(empty($gameType)) {
        http_response_code(400);
        echo json_encode(['error' => '缺少游戏类型参数']);
        return;
    }
    
    $stmt = $db->prepare("SELECT id, name, icon, image_url, value, rarity FROM prizes WHERE game_type = ? AND active = 1 ORDER BY rarity DESC, value DESC");
    $stmt->execute([$gameType]);
    $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'prizes' => $prizes]);
}

function luckyDrop() {
    global $db, $input;
    $userId = $_SESSION['user_id'];
    $cost = 10;
    
    // 检查余额
    if(!checkBalance($userId, $cost)) {
        http_response_code(400);
        echo json_encode(['error' => '余额不足']);
        return;
    }
    
    // 扣除费用
    deductBalance($userId, $cost, '幸运掉落抽奖');
    
    // 获取奖品列表
    $stmt = $db->prepare("SELECT * FROM prizes WHERE game_type = 'lucky_drop' AND active = 1");
    $stmt->execute();
    $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 随机抽奖逻辑
    $selectedPrizes = [];
    for($i = 0; $i < 3; $i++) {
        $selectedPrizes[] = selectPrizeByProbability($prizes);
    }
    
    // 计算奖励
    $totalReward = 0;
    $prizeNames = [];
    $isWin = false;
    
    // 检查是否三个相同
    if($selectedPrizes[0]['id'] == $selectedPrizes[1]['id'] && 
       $selectedPrizes[1]['id'] == $selectedPrizes[2]['id']) {
        $isWin = true;
        $totalReward = $selectedPrizes[0]['value'];
        $prizeNames[] = $selectedPrizes[0]['name'];
    } else {
        // 安慰奖
        $totalReward = 5;
        $prizeNames[] = '安慰奖';
    }
    
    // 添加奖励
    if($totalReward > 0) {
        addBalance($userId, $totalReward, '幸运掉落中奖: ' . implode(', ', $prizeNames));
    }
    
    // 记录抽奖记录
    $result = json_encode([
        'prizes' => array_map(function($p) { return ['name' => $p['name'], 'icon' => $p['icon']]; }, $selectedPrizes),
        'reward' => $totalReward,
        'is_win' => $isWin
    ]);
    
    recordLottery($userId, 'lucky_drop', $cost, $totalReward, $result);
    
    echo json_encode([
        'success' => true,
        'prizes' => array_map(function($p) { return ['name' => $p['name'], 'icon' => $p['icon'], 'image_url' => $p['image_url']]; }, $selectedPrizes),
        'reward' => $totalReward,
        'prize_name' => implode(', ', $prizeNames),
        'is_win' => $isWin
    ]);
}

function prizeDraw() {
    global $db, $input;
    $userId = $_SESSION['user_id'];
    
    $drawType = isset($input['draw_type']) ? $input['draw_type'] : 'single';
    $costs = ['single' => 20, 'triple' => 50, 'premium' => 100];
    $cost = $costs[$drawType];
    
    // 检查余额
    if(!checkBalance($userId, $cost)) {
        http_response_code(400);
        echo json_encode(['error' => '余额不足']);
        return;
    }
    
    // 扣除费用
    deductBalance($userId, $cost, "奖品抽取-{$drawType}");
    
    // 获取奖品列表
    $stmt = $db->prepare("SELECT * FROM prizes WHERE game_type = 'prize_draw' AND active = 1");
    $stmt->execute();
    $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 根据抽取类型调整概率
    if($drawType == 'premium') {
        // 高级抽取提高稀有度概率
        foreach($prizes as &$prize) {
            if($prize['rarity'] == 'legendary') $prize['probability'] *= 3;
            if($prize['rarity'] == 'epic') $prize['probability'] *= 2;
        }
    }
    
    // 执行抽奖
    $drawCount = ($drawType == 'triple') ? 3 : 1;
    $results = [];
    $totalValue = 0;
    
    for($i = 0; $i < $drawCount; $i++) {
        $prize = selectPrizeByProbability($prizes);
        $results[] = $prize;
        $totalValue += $prize['value'];
    }
    
    // 添加奖励（如果是实物奖品则不加余额，如果是金币奖品则加余额）
    foreach($results as $prize) {
        if(strpos($prize['name'], '金币') !== false || strpos($prize['name'], '现金') !== false) {
            addBalance($userId, $prize['value'], "奖品价值-{$prize['name']}");
        }
    }
    
    // 记录抽奖记录
    $result = json_encode(['prizes' => $results, 'total_value' => $totalValue]);
    recordLottery($userId, 'prize_draw', $cost, $totalValue, $result);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total_value' => $totalValue
    ]);
}

function wheelSpin() {
    global $db;
    $userId = $_SESSION['user_id'];
    $cost = 30;
    
    // 检查余额
    if(!checkBalance($userId, $cost)) {
        http_response_code(400);
        echo json_encode(['error' => '余额不足']);
        return;
    }
    
    // 扣除费用
    deductBalance($userId, $cost, '幸运转盘游戏');
    
    // 获取转盘奖品
    $stmt = $db->prepare("SELECT * FROM prizes WHERE game_type = 'wheel' AND active = 1");
    $stmt->execute();
    $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 随机选择奖品
    $selectedPrize = selectPrizeByProbability($prizes);
    
    // 添加奖励
    addBalance($userId, $selectedPrize['value'], "幸运转盘中奖: {$selectedPrize['name']}");
    
    // 记录抽奖记录
    $result = json_encode(['prize' => $selectedPrize]);
    recordLottery($userId, 'wheel', $cost, $selectedPrize['value'], $result);
    
    echo json_encode([
        'success' => true,
        'prize' => $selectedPrize
    ]);
}

function dailyCheckin() {
    global $db;
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // 检查今天是否已签到
    $stmt = $db->prepare("SELECT id FROM checkin_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
    if($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => '今天已经签到过了']);
        return;
    }
    
    // 计算连续签到天数
    $consecutiveDays = getConsecutiveCheckinDays($userId);
    
    // 计算奖励
    $rewards = [10, 15, 20, 25, 30, 40, 50];
    $reward = $consecutiveDays < 7 ? $rewards[$consecutiveDays] : 50;
    
    // 添加签到记录
    $stmt = $db->prepare("INSERT INTO checkin_records (user_id, date, reward) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $today, $reward]);
    
    // 添加奖励
    addBalance($userId, $reward, '每日签到奖励');
    
    echo json_encode([
        'success' => true,
        'reward' => $reward,
        'consecutive_days' => $consecutiveDays + 1
    ]);
}

function getCheckinStatus() {
    global $db;
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // 检查今天是否已签到
    $stmt = $db->prepare("SELECT reward FROM checkin_records WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $today]);
    $todayRecord = $stmt->fetch();
    
    // 获取签到统计
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(reward) as total_reward FROM checkin_records WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取连续签到天数
    $consecutiveDays = getConsecutiveCheckinDays($userId);
    
    // 获取本月签到天数
    $thisMonth = date('Y-m');
    $stmt = $db->prepare("SELECT COUNT(*) as monthly FROM checkin_records WHERE user_id = ? AND date LIKE ?");
    $stmt->execute([$userId, $thisMonth . '%']);
    $monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'checked_today' => $todayRecord ? true : false,
        'today_reward' => $todayRecord ? $todayRecord['reward'] : 0,
        'consecutive_days' => $consecutiveDays,
        'total_days' => $stats['total'],
        'monthly_days' => $monthlyStats['monthly'],
        'total_reward' => $stats['total_reward']
    ]);
}

function getTransactions() {
    global $db;
    $userId = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $stmt = $db->prepare("SELECT amount, description, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$userId, $limit, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
}

// 辅助函数
function checkBalance($userId, $amount) {
    global $db;
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user && $user['balance'] >= $amount;
}

function deductBalance($userId, $amount, $description) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);
    
    $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'expense')");
    $stmt->execute([$userId, -$amount, $description]);
}

function addBalance($userId, $amount, $description) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);
    
    $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'income')");
    $stmt->execute([$userId, $amount, $description]);
}

function selectPrizeByProbability($prizes) {
    $totalProbability = array_sum(array_column($prizes, 'probability'));
    $random = mt_rand(1, $totalProbability * 100) / 100;
    
    $currentProbability = 0;
    foreach($prizes as $prize) {
        $currentProbability += $prize['probability'];
        if($random <= $currentProbability) {
            return $prize;
        }
    }
    
    return $prizes[array_rand($prizes)]; // 备选方案
}

function recordLottery($userId, $gameType, $cost, $reward, $result) {
    global $db;
    $stmt = $db->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $gameType, $cost, $reward, $result]);
}

function getConsecutiveCheckinDays($userId) {
    global $db;
    $stmt = $db->prepare("SELECT date FROM checkin_records WHERE user_id = ? ORDER BY date DESC");
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($records)) return 0;
    
    $consecutiveDays = 0;
    $currentDate = new DateTime();
    $currentDate->setTime(0, 0, 0);
    
    foreach($records as $record) {
        $recordDate = new DateTime($record['date']);
        $diff = $currentDate->diff($recordDate)->days;
        
        if($diff == $consecutiveDays) {
            $consecutiveDays++;
            $currentDate->modify('-1 day');
        } else {
            break;
        }
    }
    
    return $consecutiveDays;
}
?>
