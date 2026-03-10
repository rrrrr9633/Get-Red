-- ========================================
-- 数据库索引优化脚本（安全版本 - 已更新）
-- 可重复执行，不会因索引已存在而报错
-- 符合当前金币系统（bound_coins + unbound_coins）
-- 包含全系统索引优化和抽奖系统专用索引
-- ========================================

-- 设置分隔符，用于存储过程
DELIMITER $

-- 创建添加索引的存储过程（如果索引不存在才添加）
DROP PROCEDURE IF EXISTS add_index_if_not_exists$
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns VARCHAR(255)
)
BEGIN
    DECLARE index_exists INT DEFAULT 0;
    
    -- 检查索引是否存在
    SELECT COUNT(*) INTO index_exists
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
        AND table_name = p_table_name
        AND index_name = p_index_name;
    
    -- 如果索引不存在，则创建
    IF index_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table_name, ' ADD INDEX ', p_index_name, ' (', p_index_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✅ 索引 ', p_index_name, ' 已创建') AS result;
    ELSE
        SELECT CONCAT('⚠️  索引 ', p_index_name, ' 已存在，跳过') AS result;
    END IF;
END$

DELIMITER ;

-- ========================================
-- 1. draws 表索引优化
-- ========================================
CALL add_index_if_not_exists('draws', 'idx_user_created', 'user_id, created_at');
CALL add_index_if_not_exists('draws', 'idx_draw_type', 'draw_type');

-- ========================================
-- 2. user_items 表索引优化
-- ========================================
CALL add_index_if_not_exists('user_items', 'idx_user_obtained', 'user_id, obtained_at');
CALL add_index_if_not_exists('user_items', 'idx_user_rarity', 'user_id, rarity');
CALL add_index_if_not_exists('user_items', 'idx_decomposed', 'decomposed');

-- ========================================
-- 3. transactions 表索引优化
-- ========================================
CALL add_index_if_not_exists('transactions', 'idx_user_created', 'user_id, created_at');
CALL add_index_if_not_exists('transactions', 'idx_user_type', 'user_id, type');

-- ========================================
-- 4. lottery_records 表索引优化
-- ========================================
CALL add_index_if_not_exists('lottery_records', 'idx_user_created', 'user_id, created_at');
CALL add_index_if_not_exists('lottery_records', 'idx_game_type', 'game_type');

-- ========================================
-- 5. security_logs 表索引优化
-- ========================================
CALL add_index_if_not_exists('security_logs', 'idx_user_action_time', 'user_id, action, created_at');
CALL add_index_if_not_exists('security_logs', 'idx_ip_created', 'ip_address, created_at');

-- ========================================
-- 6. chat_messages 表索引优化
-- ========================================
CALL add_index_if_not_exists('chat_messages', 'idx_session_created', 'session_id, created_at');
CALL add_index_if_not_exists('chat_messages', 'idx_session_read', 'session_id, is_read');

-- ========================================
-- 7. prizes 表索引优化
-- ========================================
CALL add_index_if_not_exists('prizes', 'idx_game_active', 'game_type, active');
CALL add_index_if_not_exists('prizes', 'idx_rarity', 'rarity');

-- ========================================
-- 8. shop_purchase_history 表索引优化
-- ========================================
CALL add_index_if_not_exists('shop_purchase_history', 'idx_user_created', 'user_id, created_at');
CALL add_index_if_not_exists('shop_purchase_history', 'idx_status_created', 'status, created_at');

-- ========================================
-- 9. coin_change_log 表索引优化（新增）
-- ========================================
CALL add_index_if_not_exists('coin_change_log', 'idx_user_id', 'user_id');
CALL add_index_if_not_exists('coin_change_log', 'idx_change_type', 'change_type');
CALL add_index_if_not_exists('coin_change_log', 'idx_created_at', 'created_at');

-- ========================================
-- 10. withdrawal_requests 表索引优化（新增）
-- ========================================
CALL add_index_if_not_exists('withdrawal_requests', 'idx_user_id', 'user_id');
CALL add_index_if_not_exists('withdrawal_requests', 'idx_status', 'status');
CALL add_index_if_not_exists('withdrawal_requests', 'idx_created_at', 'created_at');

-- ========================================
-- 11. withdrawal_history 表索引优化（新增）
-- ========================================
CALL add_index_if_not_exists('withdrawal_history', 'idx_user_id', 'user_id');
CALL add_index_if_not_exists('withdrawal_history', 'idx_created_at', 'created_at');

-- ========================================
-- 12. recharge_history 表索引优化
-- ========================================
CALL add_index_if_not_exists('recharge_history', 'idx_user_id', 'user_id');

-- ========================================
-- 13. shop_items 表索引优化（新增）
-- ========================================
CALL add_index_if_not_exists('shop_items', 'idx_item_type', 'item_type');
CALL add_index_if_not_exists('shop_items', 'idx_is_active', 'is_active');
CALL add_index_if_not_exists('shop_items', 'idx_sort_order', 'sort_order');

-- 清理存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ========================================
-- 完成提示
-- ========================================
SELECT '========================================' AS '';
SELECT '✅ 索引优化完成！' AS '';
SELECT '已包含金币系统相关表的索引' AS '';
SELECT '========================================' AS '';
