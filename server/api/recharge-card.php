<?php
/**
 * 充值卡密 API
 * 支持卡密生成、兑换、查询和删除功能
 */

require_once '../config/database.php';
require_once '../config/redis.php';
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 初始化数据库
$database = new Database();
$db = $database->getConnection();

// 初始化 Redis
try {
    $redis = RedisConnection::getInstance();
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Redis 服务不可用']);
    exit;
}

// 获取请求参数
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 处理不同的请求
switch ($action) {
    // ==================== 管理端API ====================
    
    case 'generate_card':
        // 生成卡密
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleGenerateCard($db, $redis, $data);
        }
        break;
    
    case 'get_card_list':
        // 获取卡密列表
        if ($method === 'GET') {
            handleGetCardList($db, $redis);
        }
        break;
    
    case 'delete_card':
        // 删除卡密
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleDeleteCard($db, $redis, $data);
        }
        break;
    
    // ==================== 用户端API ====================
    
    case 'redeem_card':
        // 兑换卡密
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleRedeemCard($db, $redis, $data);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '未知的操作']);
        break;
}

// ==================== 处理函数 ====================

/**
 * 生成16位随机卡密
 * 排除易混淆字符：0/O, 1/I/L
 */
function generateCardCode($redis) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 排除 0,O,1,I,L
    $maxAttempts = 10; // 最多尝试10次
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 16; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // 检查 Redis 中是否已存在
        if (!$redis->exists("recharge_card:{$code}")) {
            return $code;
        }
    }
    
    throw new Exception("生成卡密失败，请重试");
}

/**
 * 验证超级管理员权限
 */
function checkSuperAdminPermission() {
    // 检查是否是超级管理员session
    if (!isset($_SESSION['super_admin_id']) || !isset($_SESSION['super_admin_verified']) || $_SESSION['super_admin_verified'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '需要超级管理员权限']);
        exit;
    }
    
    return $_SESSION['super_admin_id'];
}

/**
 * 获取充值比例
 */
function getCoinRatio($db) {
    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? intval($result['setting_value']) : 10;
    } catch (Exception $e) {
        return 10;
    }
}

/**
 * 生成卡密
 */
function handleGenerateCard($db, $redis, $data) {
    try {
        // 验证管理员权限
        $adminId = checkSuperAdminPermission();
        
        $amount = floatval($data['amount'] ?? 0);
        $optionId = intval($data['option_id'] ?? 0);
        
        if ($amount <= 0) {
            throw new Exception("金额无效");
        }
        
        // 查询充值选项
        $query = "SELECT * FROM recharge_options WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $optionId]);
        $option = $stmt->fetch();
        
        if (!$option) {
            throw new Exception("充值选项不存在");
        }
        
        // 获取充值比例
        $coinRatio = getCoinRatio($db);
        
        // 计算金币数量
        $coins = ($amount * $coinRatio) + ($option['bonus_coins'] ?? 0);
        
        // 生成卡密
        $cardCode = generateCardCode($redis);
        
        // 存储到 Redis
        $redis->hMSet("recharge_card:{$cardCode}", [
            'amount' => $amount,
            'coins' => $coins,
            'generated_at' => time(),
            'admin_id' => $adminId
        ]);
        $redis->expire("recharge_card:{$cardCode}", 300); // 5分钟过期
        
        // 添加到管理员卡密列表
        $redis->zAdd("admin_cards:{$adminId}", time(), $cardCode);
        $redis->expire("admin_cards:{$adminId}", 600); // 10分钟过期
        
        // 计算过期时间
        $expiresAt = date('Y-m-d H:i:s', time() + 300);
        
        echo json_encode([
            'success' => true,
            'card_code' => $cardCode,
            'amount' => $amount,
            'coins' => $coins,
            'expires_at' => $expiresAt,
            'expires_in_seconds' => 300
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 兑换卡密
 */
function handleRedeemCard($db, $redis, $data) {
    try {
        // 验证用户登录
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("请先登录");
        }
        
        $userId = $_SESSION['user_id'];
        $cardCode = strtoupper(trim($data['card_code'] ?? ''));
        
        if (strlen($cardCode) !== 16) {
            throw new Exception("卡密格式错误");
        }
        
        // 防暴力破解：检查尝试次数
        $attemptsKey = "card_redeem_attempts:{$userId}";
        $attempts = $redis->incr($attemptsKey);
        if ($attempts === 1) {
            $redis->expire($attemptsKey, 60); // 1分钟过期
        }
        if ($attempts > 5) {
            throw new Exception("尝试次数过多，请1分钟后再试");
        }
        
        // 从 Redis 查询卡密
        $cardInfo = $redis->hGetAll("recharge_card:{$cardCode}");
        
        if (empty($cardInfo)) {
            throw new Exception("卡密不存在或已过期");
        }
        
        $amount = floatval($cardInfo['amount']);
        $coins = intval($cardInfo['coins']);
        
        // 开启数据库事务
        $db->beginTransaction();
        
        try {
            // 1. 更新用户金币（非绑定金币）
            $query = "UPDATE users SET unbound_coins = unbound_coins + :coins, balance = balance + :coins WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':coins' => $coins,
                ':user_id' => $userId
            ]);
            
            // 2. 检查并激活首充状态
            $query = "SELECT has_recharged FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch();
            
            $isFirstRecharge = false;
            if ($user && !$user['has_recharged']) {
                $query = "UPDATE users SET has_recharged = 1 WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute([':user_id' => $userId]);
                $isFirstRecharge = true;
            }
            
            // 3. 创建充值订单记录
            $orderNo = 'CARD_' . time() . rand(1000, 9999);
            $query = "INSERT INTO recharge_history (
                user_id, amount, coins_gained, coin_type,
                payment_method, transaction_id, status, created_at, updated_at
            ) VALUES (
                :user_id, :amount, :coins, 'unbound',
                'card_redeem', :order_no, 'completed', NOW(), NOW()
            )";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':coins' => $coins,
                ':order_no' => $orderNo
            ]);
            
            // 4. 从 Redis 删除卡密（防止重复使用）
            $redis->del("recharge_card:{$cardCode}");
            
            // 提交事务
            $db->commit();
            
            // 获取新余额
            $query = "SELECT unbound_coins, balance FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            $userBalance = $stmt->fetch();
            $newBalance = $userBalance['balance'];
            $newUnboundCoins = $userBalance['unbound_coins'];
            
            // 重置尝试次数
            $redis->del($attemptsKey);
            
            echo json_encode([
                'success' => true,
                'message' => '充值成功',
                'amount' => $amount,
                'coins' => $coins,
                'coins_gained' => $coins,
                'new_balance' => $newBalance,
                'new_unbound_coins' => $newUnboundCoins,
                'first_recharge_activated' => $isFirstRecharge
            ]);
            
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取卡密列表
 */
function handleGetCardList($db, $redis) {
    try {
        // 验证管理员权限
        $adminId = checkSuperAdminPermission();
        
        // 从 Redis 获取管理员的卡密列表
        $cardCodes = $redis->zRevRange("admin_cards:{$adminId}", 0, -1);
        
        $cards = [];
        foreach ($cardCodes as $cardCode) {
            $info = $redis->hGetAll("recharge_card:{$cardCode}");
            
            if (!empty($info)) {
                $ttl = $redis->ttl("recharge_card:{$cardCode}");
                
                if ($ttl > 0) {
                    $generatedAt = date('Y-m-d H:i:s', intval($info['generated_at']));
                    $expiresAt = date('Y-m-d H:i:s', intval($info['generated_at']) + 300);
                    
                    $cards[] = [
                        'card_code' => $cardCode,
                        'amount' => floatval($info['amount']),
                        'coins' => intval($info['coins']),
                        'generated_at' => $generatedAt,
                        'expires_at' => $expiresAt,
                        'remaining_seconds' => $ttl
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'cards' => $cards,
            'total' => count($cards)
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 删除卡密
 */
function handleDeleteCard($db, $redis, $data) {
    try {
        // 验证管理员权限
        $adminId = checkSuperAdminPermission();
        
        $cardCode = strtoupper(trim($data['card_code'] ?? ''));
        
        if (empty($cardCode)) {
            throw new Exception("卡密不能为空");
        }
        
        // 从 Redis 删除卡密
        $deleted = $redis->del("recharge_card:{$cardCode}");
        
        if ($deleted === 0) {
            throw new Exception("卡密不存在或已过期");
        }
        
        // 从管理员卡密列表中移除
        $redis->zRem("admin_cards:{$adminId}", $cardCode);
        
        echo json_encode([
            'success' => true,
            'message' => '卡密已删除'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
