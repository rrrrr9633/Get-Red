<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

function getPrizes($gameType = null) {
    global $pdo;
    
    try {
        if ($gameType) {
            $stmt = $pdo->prepare("SELECT * FROM prizes WHERE game_type = ? AND active = 1 ORDER BY probability ASC");
            $stmt->execute([$gameType]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM prizes WHERE active = 1 ORDER BY game_type, probability ASC");
            $stmt->execute();
        }
        
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'prizes' => $prizes
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '获取奖品失败: ' . $e->getMessage()
        ];
    }
}

function addPrize($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO prizes (name, icon, image_url, value, rarity, game_type, probability) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['icon'],
            $data['image_url'] ?? null,
            $data['value'],
            $data['rarity'],
            $data['game_type'],
            $data['probability']
        ]);
        
        return [
            'success' => true,
            'message' => '奖品添加成功',
            'id' => $pdo->lastInsertId()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '添加奖品失败: ' . $e->getMessage()
        ];
    }
}

function updatePrize($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE prizes SET name = ?, icon = ?, image_url = ?, value = ?, rarity = ?, game_type = ?, probability = ? WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['icon'],
            $data['image_url'] ?? null,
            $data['value'],
            $data['rarity'],
            $data['game_type'],
            $data['probability'],
            $id
        ]);
        
        return [
            'success' => true,
            'message' => '奖品更新成功'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '更新奖品失败: ' . $e->getMessage()
        ];
    }
}

function deletePrize($id) {
    global $pdo;
    
    try {
        // 软删除，设置 active = 0
        $stmt = $pdo->prepare("UPDATE prizes SET active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        return [
            'success' => true,
            'message' => '奖品删除成功'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '删除奖品失败: ' . $e->getMessage()
        ];
    }
}

function performDraw($userId, $gameType, $count = 1, $page = null) {
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
            throw new Exception('余额不足');
        }
        
        // 根据页面参数确定表名
        $tableName = 'prizes'; // 默认表名
        if ($page) {
            // 从 lucky1.html -> lucky1_prizes
            $pageBase = str_replace('.html', '', $page);
            if (preg_match('/^lucky\d+$/', $pageBase)) {
                $tableName = $pageBase . '_prizes';
            }
        }
        
        // 获取奖品列表
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}` WHERE active = 1 ORDER BY probability ASC");
        $stmt->execute();
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prizes)) {
            // 如果指定表没有数据，回退到默认表
            if ($tableName !== 'prizes') {
                $stmt = $pdo->prepare("SELECT * FROM prizes WHERE game_type = ? AND active = 1 ORDER BY probability ASC");
                $stmt->execute([$gameType]);
                $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (empty($prizes)) {
                throw new Exception('暂无可抽取的奖品');
            }
        }
        
        $results = [];
        $totalValue = 0;
        
        // 执行抽奖
        for ($i = 0; $i < $count; $i++) {
            $prize = selectPrizeByProbability($prizes);
            $results[] = $prize;
            $totalValue += $prize['value'];
        }
        
        // 扣除费用
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$cost, $userId]);
        
        // 记录交易
        $pageInfo = $page ? " - {$page}" : '';
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'expense')");
        $stmt->execute([$userId, $cost, "抽奖消费({$gameType}{$pageInfo})x{$count}"]);
        
        // 记录抽奖结果 - 不在game_type中包含页面信息
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $gameType, $cost, $totalValue, json_encode($results)]);
        
        // 将抽到的物品添加到用户仓库
        $stmt = $pdo->prepare("INSERT INTO user_items (user_id, prize_id, name, icon, image_url, value, rarity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($results as $prize) {
            $stmt->execute([
                $userId, 
                $prize['id'], 
                $prize['name'], 
                $prize['icon'], 
                $prize['image_url'], 
                $prize['value'], 
                $prize['rarity']
            ]);
        }
        
        // 不再直接添加余额，物品需要通过分解才能获得余额
        
        // 提交事务
        $pdo->commit();
        
        return [
            'success' => true,
            'results' => $results,
            'total_value' => $totalValue,
            'cost' => $cost
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
    $totalProbability = array_sum(array_column($prizes, 'probability'));
    
    // 生成0到总概率之间的随机数
    $random = (mt_rand(0, $totalProbability * 10000) / 10000);
    
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

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['game_type'])) {
            echo json_encode(getPrizes($_GET['game_type']));
        } else {
            echo json_encode(getPrizes());
        }
        break;
        
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'add':
                    echo json_encode(addPrize($input));
                    break;
                case 'draw':
                    $userId = $input['user_id'] ?? null;
                    $gameType = $input['game_type'] ?? 'lucky_drop';
                    $count = $input['count'] ?? 1;
                    $page = $input['page'] ?? null;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
                        break;
                    }
                    
                    echo json_encode(performDraw($userId, $gameType, $count, $page));
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => '未知操作']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '缺少操作参数']);
        }
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            echo json_encode(updatePrize($_GET['id'], $input));
        } else {
            echo json_encode(['success' => false, 'message' => '缺少奖品ID']);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            echo json_encode(deletePrize($_GET['id']));
        } else {
            echo json_encode(['success' => false, 'message' => '缺少奖品ID']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
}
?>
