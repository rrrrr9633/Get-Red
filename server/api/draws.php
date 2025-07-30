<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

function performLuckyDraw($userId, $count = 1) {
    global $pdo;
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 检查用户余额
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        $cost = $count * 10; // 每次抽奖10金币
        if ($user['balance'] < $cost) {
            throw new Exception('余额不足，请先充值！');
        }
        
        // 获取幸运掉落奖品列表
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
        
        // 扣除抽奖费用
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$cost, $userId]);
        
        // 记录抽奖消费
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'expense')");
        $stmt->execute([$userId, $cost, "幸运掉落抽奖x{$count}"]);
        
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

switch ($method) {
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'draw':
                    $userId = $input['user_id'] ?? null;
                    $count = $input['count'] ?? 1;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    if ($count < 1 || $count > 10) {
                        echo json_encode(['success' => false, 'message' => '抽奖次数必须在1-10之间']);
                        break;
                    }
                    
                    echo json_encode(performLuckyDraw($userId, $count));
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
