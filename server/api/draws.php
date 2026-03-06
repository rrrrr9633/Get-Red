<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

// 初始化数据库连接
$database = new Database();
$pdo = $database->getConnection();

function performLuckyDraw($userId, $count = 1, $page = 'lucky1.html') {
    global $pdo;
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 🔒 使用 FOR UPDATE 锁定用户记录，防止并发问题
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        // 获取动态价格
        $priceType = '';
        if ($count == 1) {
            $priceType = 'single';
        } elseif ($count == 3) {
            $priceType = 'triple';
        } elseif ($count == 5) {
            $priceType = 'quintuple';
        } else {
            // 对于其他数量，使用单抽价格乘以数量
            $priceType = 'single';
        }
        
        // 从数据库获取价格
        $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = ?");
        $stmt->execute([$page, $priceType]);
        $priceValue = $stmt->fetchColumn();
        
        if ($priceValue === false) {
            // 如果没有找到价格配置，使用默认价格
            $defaultPrices = ['single' => 10, 'triple' => 30, 'quintuple' => 50];
            $priceValue = $defaultPrices[$priceType] ?? 10;
        }
        
        // 如果不是标准的1、3、5连抽，按单价计算
        if (!in_array($count, [1, 3, 5])) {
            $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = 'single'");
            $stmt->execute([$page]);
            $singlePrice = $stmt->fetchColumn() ?: 10;
            $cost = $count * $singlePrice;
        } else {
            $cost = $priceValue;
        }
        
        // ✅ 检查余额（在锁定状态下）
        if ($user['balance'] < $cost) {
            throw new Exception('余额不足，请先充值！');
        }
        
        // 获取指定页面的奖品列表
        $stmt = $pdo->prepare("SELECT * FROM prizes WHERE game_type = 'lucky_drop' AND active = 1");
        $stmt->execute();
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prizes)) {
            throw new Exception('暂无可抽取的奖品，请联系管理员');
        }
        
        $results = [];
        $totalValue = 0;
        
        // 执行抽奖
        for ($i = 0; $i < $count; $i++) {
            $prize = selectPrizeByProbability($prizes);
            $results[] = $prize;
            $totalValue += floatval($prize['value']);
        }
        
        // ✅ 扣除抽奖费用（使用 WHERE 条件二次确认，防止余额负数）
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
        $stmt->execute([$cost, $userId, $cost]);
        
        // 检查是否真的扣除成功
        if ($stmt->rowCount() === 0) {
            throw new Exception('余额扣除失败，请重试');
        }
        
        // 记录抽奖消费
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'expense')");
        $stmt->execute([$userId, $cost, "幸运掉落抽奖x{$count}({$page})"]);
        
        // 将奖品价值作为余额奖励给用户
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$totalValue, $userId]);
        
        // 记录奖励交易
        $prizeNames = implode('、', array_column($results, 'name'));
        $description = "抽奖奖励: {$prizeNames}";
        
        // 限制描述长度，避免超过数据库字段限制(varchar(255))
        // 使用字节长度检查，确保兼容性
        if (strlen($description) > 250) {
            // 安全截断，避免截断多字节字符
            $description = substr($description, 0, 230) . '...(共' . count($results) . '件)';
        }
        
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'income')");
        $stmt->execute([$userId, $totalValue, $description]);
        
        // 记录抽奖历史
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, 'lucky_drop', ?, ?, ?)");
        $stmt->execute([$userId, $cost, $totalValue, json_encode($results, JSON_UNESCAPED_UNICODE)]);
        
        // 检查是否抽中传说物品，如果是则记录到限时掉落中奖列表
        define('INCLUDED_FROM_PRIZES', true);
        require_once 'limited-drop.php';
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn() ?: '未知用户';
        
        foreach ($results as $prize) {
            if (isset($prize['rarity']) && $prize['rarity'] === 'legendary') {
                recordWinner($page, $userId, $username, $prize['name'], $prize['value']);
            }
        }
        
        // 提交事务
        $pdo->commit();
        
        return [
            'success' => true,
            'results' => $results,
            'total_value' => $totalValue,
            'cost' => $cost,
            'message' => '抽奖成功！'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function selectPrizeByProbability($prizes) {
    // 计算总概率
    $totalProbability = 0;
    foreach ($prizes as $prize) {
        $totalProbability += floatval($prize['probability']);
    }
    
    // 生成随机数 (0到总概率之间)
    $random = mt_rand(0, $totalProbability * 100) / 100;
    
    // 根据概率选择奖品
    $accumulator = 0;
    foreach ($prizes as $prize) {
        $accumulator += floatval($prize['probability']);
        if ($random <= $accumulator) {
            return $prize;
        }
    }
    
    // 备用返回最后一个奖品
    return end($prizes);
}

function getLotteryHistory($userId, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM lottery_records WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析结果JSON
        foreach ($records as &$record) {
            $record['result'] = json_decode($record['result'], true);
        }
        
        return [
            'success' => true,
            'records' => $records
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取历史记录失败: ' . $e->getMessage()
        ];
    }
}

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 调试日志
error_log("draws.php - Method: $method, Input: " . json_encode($input));

switch ($method) {
    case 'POST':
        error_log("draws.php - POST request, action: " . ($input['action'] ?? 'NOT SET'));
        if (isset($input['action'])) {
            error_log("draws.php - Action is set: " . $input['action']);
            switch ($input['action']) {
                case 'draw':
                    error_log("draws.php - Entering draw case");
                    $userId = $input['user_id'] ?? null;
                    $count = $input['count'] ?? 1;
                    $page = $input['page'] ?? 'lucky1.html';
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    if ($count < 1 || $count > 10) {
                        echo json_encode(['success' => false, 'message' => '抽奖次数必须在1-10之间']);
                        break;
                    }
                    
                    error_log("draws.php - Calling performLuckyDraw");
                    echo json_encode(performLuckyDraw($userId, $count, $page));
                    break;
                    
                case 'history':
                    $userId = $input['user_id'] ?? null;
                    $limit = $input['limit'] ?? 10;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    echo json_encode(getLotteryHistory($userId, $limit));
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => '未知操作']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '缺少操作参数']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
}
?>
