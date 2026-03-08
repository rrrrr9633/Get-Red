<?php
/**
 * 金币系统辅助函数
 * 处理绑定金币和非绑定金币的操作
 */

/**
 * 记录金币变动日志
 */
function logCoinChange($db, $userId, $changeType, $coinType, $boundChange, $unboundChange, $relatedId = null, $description = '') {
    try {
        // 获取变动前的余额
        $stmt = $db->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        $boundBefore = $user['bound_coins'];
        $unboundBefore = $user['unbound_coins'];
        $boundAfter = $boundBefore + $boundChange;
        $unboundAfter = $unboundBefore + $unboundChange;
        
        // 插入日志
        $stmt = $db->prepare("
            INSERT INTO coin_change_log 
            (user_id, change_type, coin_type, bound_change, unbound_change, 
             bound_balance_before, unbound_balance_before, bound_balance_after, unbound_balance_after,
             related_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId, $changeType, $coinType, $boundChange, $unboundChange,
            $boundBefore, $unboundBefore, $boundAfter, $unboundAfter,
            $relatedId, $description
        ]);
    } catch (Exception $e) {
        error_log("Log coin change failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 增加非绑定金币（充值、传说级物品分解）
 */
function addUnboundCoins($db, $userId, $amount, $changeType, $relatedId = null, $description = '') {
    try {
        $db->beginTransaction();
        
        // 更新用户非绑定金币
        $stmt = $db->prepare("UPDATE users SET unbound_coins = unbound_coins + ?, balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $amount, $userId]);
        
        // 记录日志
        logCoinChange($db, $userId, $changeType, 'unbound', 0, $amount, $relatedId, $description);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Add unbound coins failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 增加绑定金币（普通级物品分解、签到）
 */
function addBoundCoins($db, $userId, $amount, $changeType, $relatedId = null, $description = '') {
    try {
        $db->beginTransaction();
        
        // 更新用户绑定金币
        $stmt = $db->prepare("UPDATE users SET bound_coins = bound_coins + ?, balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $amount, $userId]);
        
        // 记录日志
        logCoinChange($db, $userId, $changeType, 'bound', $amount, 0, $relatedId, $description);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Add bound coins failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 扣除非绑定金币（抽奖、提现）
 */
function deductUnboundCoins($db, $userId, $amount, $changeType, $relatedId = null, $description = '') {
    try {
        $db->beginTransaction();
        
        // 检查余额
        $stmt = $db->prepare("SELECT unbound_coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['unbound_coins'] < $amount) {
            $db->rollBack();
            return false;
        }
        
        // 扣除非绑定金币
        $stmt = $db->prepare("UPDATE users SET unbound_coins = unbound_coins - ?, balance = balance - ? WHERE id = ? AND unbound_coins >= ?");
        $stmt->execute([$amount, $amount, $userId, $amount]);
        
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            return false;
        }
        
        // 记录日志
        logCoinChange($db, $userId, $changeType, 'unbound', 0, -$amount, $relatedId, $description);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Deduct unbound coins failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 混合扣除金币（商城兑换）
 * 优先使用绑定金币，不足时使用非绑定金币
 */
function deductMixedCoins($db, $userId, $totalAmount, $useBound = true, $changeType = 'shop_purchase', $relatedId = null, $description = '') {
    try {
        $db->beginTransaction();
        
        // 锁定用户记录
        $stmt = $db->prepare("SELECT bound_coins, unbound_coins FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $db->rollBack();
            return ['success' => false, 'error' => 'User not found'];
        }
        
        $boundCoins = $user['bound_coins'];
        $unboundCoins = $user['unbound_coins'];
        $totalCoins = $boundCoins + $unboundCoins;
        
        // 检查总余额
        if ($totalCoins < $totalAmount) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Insufficient balance'];
        }
        
        $boundUsed = 0;
        $unboundUsed = 0;
        
        if ($useBound) {
            // 优先使用绑定金币
            $boundUsed = min($boundCoins, $totalAmount);
            $unboundUsed = $totalAmount - $boundUsed;
        } else {
            // 只使用非绑定金币
            if ($unboundCoins < $totalAmount) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Insufficient unbound coins'];
            }
            $unboundUsed = $totalAmount;
        }
        
        // 扣除金币
        $stmt = $db->prepare("
            UPDATE users 
            SET bound_coins = bound_coins - ?, 
                unbound_coins = unbound_coins - ?,
                balance = balance - ?
            WHERE id = ?
        ");
        $stmt->execute([$boundUsed, $unboundUsed, $totalAmount, $userId]);
        
        // 记录日志
        logCoinChange($db, $userId, $changeType, 'mixed', -$boundUsed, -$unboundUsed, $relatedId, $description);
        
        $db->commit();
        return [
            'success' => true,
            'bound_used' => $boundUsed,
            'unbound_used' => $unboundUsed
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Deduct mixed coins failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 获取用户金币余额
 */
function getUserCoins($db, $userId) {
    try {
        $stmt = $db->prepare("SELECT bound_coins, unbound_coins, (bound_coins + unbound_coins) as total_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get user coins failed: " . $e->getMessage());
        return null;
    }
}

/**
 * 退还金币（取消订单、拒绝提现等）
 */
function refundCoins($db, $userId, $boundAmount, $unboundAmount, $relatedId = null, $description = '') {
    try {
        $db->beginTransaction();
        
        $totalAmount = $boundAmount + $unboundAmount;
        
        // 退还金币
        $stmt = $db->prepare("
            UPDATE users 
            SET bound_coins = bound_coins + ?, 
                unbound_coins = unbound_coins + ?,
                balance = balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$boundAmount, $unboundAmount, $totalAmount, $userId]);
        
        // 记录日志
        $coinType = ($boundAmount > 0 && $unboundAmount > 0) ? 'mixed' : ($boundAmount > 0 ? 'bound' : 'unbound');
        logCoinChange($db, $userId, 'refund', $coinType, $boundAmount, $unboundAmount, $relatedId, $description);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Refund coins failed: " . $e->getMessage());
        return false;
    }
}
?>
