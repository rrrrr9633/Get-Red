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

function drawPrizes($gameType, $count, $userId, $page = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 检查用户余额
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
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
        $stmt->execute([$page ?: 'lucky1.html', $priceType]);
        $priceValue = $stmt->fetchColumn();
        
        if ($priceValue === false) {
            // 如果没有找到价格配置，使用默认价格
            $defaultPrices = ['single' => 10, 'triple' => 30, 'quintuple' => 50];
            $priceValue = $defaultPrices[$priceType] ?? 10;
        }
        
        // 如果不是标准的1、3、5连抽，按单价计算
        if (!in_array($count, [1, 3, 5])) {
            $stmt = $pdo->prepare("SELECT price_value FROM draw_prices WHERE page_name = ? AND price_type = 'single'");
            $stmt->execute([$page ?: 'lucky1.html']);
            $singlePrice = $stmt->fetchColumn() ?: 10;
            $cost = $count * $singlePrice;
        } else {
            $cost = $priceValue;
        }
        
        if ($user['balance'] < $cost) {
            throw new Exception('余额不足');
        }
        
        // 确定奖品表名
        $tableName = 'prizes';
        if ($page) {
            $tableName = str_replace('.html', '_prizes', $page);
            $tableName = str_replace('-', '_', $tableName);
            
            // 检查表是否存在
            $checkTableSQL = "SHOW TABLES LIKE '{$tableName}'";
            $result = $pdo->query($checkTableSQL);
            if ($result->rowCount() == 0) {
                $tableName = 'prizes';
            }
        }
        
        // 获取可用奖品（概率大于0的奖品）
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}` WHERE active = 1 AND probability > 0 ORDER BY probability ASC");
        $stmt->execute();
        $availablePrizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($availablePrizes)) {
            throw new Exception('暂无可抽取的奖品');
        }
        
        $results = [];
        $totalValue = 0;
        
        // 执行抽奖 - 每次抽奖后立即更新数量和概率
        for ($i = 0; $i < $count; $i++) {
            // 重新获取当前可用奖品列表（因为可能有概率变化）
            $stmt = $pdo->prepare("SELECT * FROM `{$tableName}` WHERE active = 1 AND probability > 0 ORDER BY probability ASC");
            $stmt->execute();
            $currentPrizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($currentPrizes)) {
                throw new Exception('抽奖过程中奖品已耗尽');
            }
            
            // 进行抽奖
            $prize = selectPrizeByProbability($currentPrizes);
            $results[] = $prize;
            $totalValue += $prize['value'];
            
            // 如果是传说物品且有数量限制，扣减数量
            if ($prize['rarity'] === 'legendary' && isset($prize['quantity']) && $prize['quantity'] !== null) {
                $newQuantity = $prize['quantity'] - 1;
                
                // 更新数量
                $stmt = $pdo->prepare("UPDATE `{$tableName}` SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $prize['id']]);
                
                // 如果数量变为0，将概率设为0
                if ($newQuantity <= 0) {
                    $stmt = $pdo->prepare("UPDATE `{$tableName}` SET probability = 0 WHERE id = ?");
                    $stmt->execute([$prize['id']]);
                }
            }
            
            // 记录抽奖日志
            $stmt = $pdo->prepare("INSERT INTO prize_draw_log (user_id, prize_table, prize_id, prize_name, rarity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $tableName, $prize['id'], $prize['name'], $prize['rarity']]);
        }
        
        // 扣除费用
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$cost, $userId]);
        
        // 记录交易
        $pageInfo = $page ? " - {$page}" : '';
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type) VALUES (?, ?, ?, 'expense')");
        $stmt->execute([$userId, $cost, "抽奖消费({$gameType}{$pageInfo})x{$count}"]);
        
        // 记录抽奖结果
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, game_type, cost, reward, result) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $gameType, $cost, $totalValue, json_encode($results)]);
        
        // 将抽到的物品添加到用户仓库
        $stmt = $pdo->prepare("INSERT INTO user_items (user_id, prize_id, name, icon, image_url, value, rarity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($results as $prize) {
            // 允许prize_id为NULL，如果不存在或为null则保持null
            $prizeIdForStorage = isset($prize['id']) && $prize['id'] !== null ? $prize['id'] : null;
            
            $stmt->execute([
                $userId, 
                $prizeIdForStorage,
                $prize['name'],
                $prize['icon'] ?? '🎁',
                $prize['image_url'] ?? '',
                $prize['value'] ?? 0,
                $prize['rarity'] ?? 'common'
            ]);
        }
        
        // 提交事务
        $pdo->commit();
        
        return [
            'success' => true,
            'results' => $results,
            'prizes' => $results, // 兼容前端
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
                    
                    echo json_encode(drawPrizes($gameType, $count, $userId, $page));
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
