<?php
/**
 * 奖品辅助函数
 * 处理统一奖品表的查询和管理
 */

/**
 * 获取指定Lucky页面的所有可用奖品
 * @param PDO $db 数据库连接
 * @param string $luckyPage Lucky页面标识（如 'lucky1', 'lucky2'）
 * @param bool $activeOnly 是否只返回启用的奖品
 * @return array 奖品列表
 */
function getPrizesByLuckyPage($db, $luckyPage, $activeOnly = true) {
    // 移除.html后缀（如果有）
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.icon,
            p.image_url,
            p.value,
            COALESCE(plp.page_probability, p.probability) AS probability,
            p.rarity,
            p.quantity AS global_quantity,
            COALESCE(plp.page_quantity, p.quantity) AS quantity,
            p.original_probability,
            p.active AS global_active,
            plp.enabled AS page_enabled,
            plp.page_probability,
            plp.page_quantity,
            p.created_at,
            p.updated_at
        FROM prizes p
        INNER JOIN prize_lucky_pages plp ON p.id = plp.prize_id
        WHERE plp.lucky_page = ?
    ";
    
    if ($activeOnly) {
        $sql .= " AND p.active = 1 AND plp.enabled = 1";
    }
    
    $sql .= " ORDER BY p.probability DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$luckyPage]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 添加奖品到指定的Lucky页面
 * @param PDO $db 数据库连接
 * @param array $prizeData 奖品数据
 * @param array $luckyPages Lucky页面数组（如 ['lucky1', 'lucky2']）
 * @return int 新创建的奖品ID
 */
function addPrizeToPages($db, $prizeData, $luckyPages) {
    $db->beginTransaction();
    
    try {
        // 插入奖品
        $stmt = $db->prepare("
            INSERT INTO prizes (name, icon, image_url, value, probability, rarity, quantity, original_probability, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $prizeData['name'],
            $prizeData['icon'] ?? '?',
            $prizeData['image_url'] ?? null,
            $prizeData['value'],
            $prizeData['probability'],
            $prizeData['rarity'] ?? 'common',
            $prizeData['quantity'] ?? null,
            $prizeData['original_probability'] ?? null,
            $prizeData['active'] ?? 1
        ]);
        
        $prizeId = $db->lastInsertId();
        
        // 关联到指定的Lucky页面
        $stmt = $db->prepare("
            INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($luckyPages as $page) {
            $page = str_replace('.html', '', $page);
            $stmt->execute([
                $prizeId,
                $page,
                $prizeData['page_enabled'] ?? 1,
                $prizeData['page_probability'] ?? null
            ]);
        }
        
        $db->commit();
        return $prizeId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * 更新奖品信息
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param array $prizeData 奖品数据
 * @return bool 是否成功
 */
function updatePrize($db, $prizeId, $prizeData) {
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'icon', 'image_url', 'value', 'probability', 'rarity', 'quantity', 'original_probability', 'active'];
    
    foreach ($allowedFields as $field) {
        if (isset($prizeData[$field])) {
            $fields[] = "$field = ?";
            $values[] = $prizeData[$field];
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $prizeId;
    
    $sql = "UPDATE prizes SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * 更新奖品在特定页面的状态
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param string $luckyPage Lucky页面标识
 * @param array $pageData 页面相关数据（enabled, page_probability）
 * @return bool 是否成功
 */
function updatePrizePageStatus($db, $prizeId, $luckyPage, $pageData) {
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    $fields = [];
    $values = [];
    
    if (isset($pageData['enabled'])) {
        $fields[] = "enabled = ?";
        $values[] = $pageData['enabled'];
    }
    
    if (isset($pageData['page_probability'])) {
        $fields[] = "page_probability = ?";
        $values[] = $pageData['page_probability'];
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $prizeId;
    $values[] = $luckyPage;
    
    $sql = "UPDATE prize_lucky_pages SET " . implode(', ', $fields) . " WHERE prize_id = ? AND lucky_page = ?";
    $stmt = $db->prepare($sql);
    
    return $stmt->execute($values);
}

/**
 * 切换奖品在特定页面的启用状态
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param string $luckyPage Lucky页面标识
 * @return bool 新的启用状态
 */
function togglePrizePageStatus($db, $prizeId, $luckyPage) {
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    // 获取当前状态
    $stmt = $db->prepare("SELECT enabled FROM prize_lucky_pages WHERE prize_id = ? AND lucky_page = ?");
    $stmt->execute([$prizeId, $luckyPage]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        return false;
    }
    
    $newStatus = $current['enabled'] ? 0 : 1;
    
    // 更新状态
    $stmt = $db->prepare("UPDATE prize_lucky_pages SET enabled = ? WHERE prize_id = ? AND lucky_page = ?");
    $stmt->execute([$newStatus, $prizeId, $luckyPage]);
    
    return $newStatus;
}

/**
 * 删除奖品（会自动删除所有页面关联）
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @return bool 是否成功
 */
function deletePrize($db, $prizeId) {
    // 由于有外键约束，删除奖品会自动删除prize_lucky_pages中的关联记录
    $stmt = $db->prepare("DELETE FROM prizes WHERE id = ?");
    return $stmt->execute([$prizeId]);
}

/**
 * 将奖品添加到新的Lucky页面
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param string $luckyPage Lucky页面标识
 * @param bool $enabled 是否启用
 * @param float|null $pageProbability 页面特定概率
 * @return bool 是否成功
 */
function addPrizeToPage($db, $prizeId, $luckyPage, $enabled = true, $pageProbability = null) {
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), page_probability = VALUES(page_probability)
        ");
        
        return $stmt->execute([$prizeId, $luckyPage, $enabled ? 1 : 0, $pageProbability]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 从Lucky页面移除奖品关联
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param string $luckyPage Lucky页面标识
 * @return bool 是否成功
 */
function removePrizeFromPage($db, $prizeId, $luckyPage) {
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    $stmt = $db->prepare("DELETE FROM prize_lucky_pages WHERE prize_id = ? AND lucky_page = ?");
    return $stmt->execute([$prizeId, $luckyPage]);
}

/**
 * 获取奖品关联的所有Lucky页面
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @return array Lucky页面列表
 */
function getPrizePages($db, $prizeId) {
    $stmt = $db->prepare("
        SELECT lucky_page, enabled, page_probability
        FROM prize_lucky_pages
        WHERE prize_id = ?
        ORDER BY lucky_page
    ");
    
    $stmt->execute([$prizeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 批量更新奖品在多个页面的状态
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param array $pagesData 页面数据数组 [['page' => 'lucky1', 'enabled' => 1], ...]
 * @return bool 是否成功
 */
function batchUpdatePrizePages($db, $prizeId, $pagesData) {
    $db->beginTransaction();
    
    try {
        $stmt = $db->prepare("
            UPDATE prize_lucky_pages 
            SET enabled = ?, page_probability = ?
            WHERE prize_id = ? AND lucky_page = ?
        ");
        
        foreach ($pagesData as $pageData) {
            $page = str_replace('.html', '', $pageData['page']);
            $stmt->execute([
                $pageData['enabled'] ?? 1,
                $pageData['page_probability'] ?? null,
                $prizeId,
                $page
            ]);
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * 获取所有Lucky页面列表
 * @return array Lucky页面标识数组
 */
function getAllLuckyPages() {
    return [
        'lucky1', 'lucky2', 'lucky3', 'lucky4', 'lucky5', 'lucky6',
        'lucky7', 'lucky8', 'lucky9', 'lucky10', 'lucky11'
    ];
}

/**
 * 检查奖品是否在指定页面启用
 * @param PDO $db 数据库连接
 * @param int $prizeId 奖品ID
 * @param string $luckyPage Lucky页面标识
 * @return bool 是否启用
 */
function isPrizeEnabledOnPage($db, $prizeId, $luckyPage) {
    $luckyPage = str_replace('.html', '', $luckyPage);
    
    $stmt = $db->prepare("
        SELECT p.active, plp.enabled
        FROM prizes p
        INNER JOIN prize_lucky_pages plp ON p.id = plp.prize_id
        WHERE p.id = ? AND plp.lucky_page = ?
    ");
    
    $stmt->execute([$prizeId, $luckyPage]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return false;
    }
    
    return $result['active'] == 1 && $result['enabled'] == 1;
}
