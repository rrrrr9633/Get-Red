-- ========================================
-- 抽奖系统专用索引优化
-- 针对 draws.php 中的查询进行优化
-- ========================================

USE lucky_draw;

-- 设置分隔符
DELIMITER $

-- 创建添加索引的存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists$
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns VARCHAR(255)
)
BEGIN
    DECLARE index_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO index_exists
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
        AND table_name = p_table_name
        AND index_name = p_index_name;
    
    IF index_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table_name, ' ADD INDEX ', p_index_name, ' (', p_index_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('OK: Index ', p_index_name, ' created') AS result;
    ELSE
        SELECT CONCAT('SKIP: Index ', p_index_name, ' already exists') AS result;
    END IF;
END$

DELIMITER ;

-- ========================================
-- 核心优化：prize_lucky_pages 表的复合索引
-- 这是抽奖查询的关键索引，覆盖WHERE条件中的所有字段
-- ========================================
CALL add_index_if_not_exists(
    'prize_lucky_pages', 
    'idx_page_enabled_prob', 
    'lucky_page, enabled'
);

-- ========================================
-- prizes 表的复合索引
-- 优化 JOIN 和 WHERE 条件
-- ========================================
CALL add_index_if_not_exists(
    'prizes', 
    'idx_active_prob', 
    'active, probability'
);

-- ========================================
-- draw_prices 表的复合索引
-- 优化价格查询
-- ========================================
CALL add_index_if_not_exists(
    'draw_prices', 
    'idx_page_type', 
    'page_name, price_type'
);

-- ========================================
-- users 表的索引（如果不存在）
-- 优化用户查询和锁定
-- ========================================
CALL add_index_if_not_exists(
    'users', 
    'idx_id_coins', 
    'id, unbound_coins'
);

-- 清理存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ========================================
-- Complete
-- ========================================
SELECT '========================================' AS '';
SELECT 'Draw system index optimization completed!' AS '';
SELECT 'Added indexes:' AS '';
SELECT '1. prize_lucky_pages.idx_page_enabled_prob' AS '';
SELECT '2. prizes.idx_active_prob' AS '';
SELECT '3. draw_prices.idx_page_type' AS '';
SELECT '4. users.idx_id_coins' AS '';
SELECT '========================================' AS '';
