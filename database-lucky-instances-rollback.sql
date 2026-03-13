-- ============================================
-- Lucky实例统一系统 - 数据库回滚脚本
-- 用途：回滚 database-lucky-instances-migration.sql 的所有更改
-- 警告：此操作将删除所有Lucky实例相关数据！
-- ============================================

USE lucky_draw;

-- ============================================
-- 警告提示
-- ============================================
SELECT '========== 警告 ==========' AS message;
SELECT '此脚本将删除以下内容：' AS warning;
SELECT '1. lucky_instances 表及其所有数据' AS warning;
SELECT '2. lucky_groups 表及其所有数据' AS warning;
SELECT '3. prizes 表的 lucky_id 字段' AS warning;
SELECT '4. draw_prices 表的 lucky_id 字段' AS warning;
SELECT '5. 所有相关的外键约束和索引' AS warning;
SELECT '' AS blank;
SELECT '如果您不确定是否要继续，请立即停止执行！' AS warning;
SELECT '建议：在执行回滚前先备份数据库' AS recommendation;
SELECT '' AS blank;

-- ============================================
-- 1. 删除 prizes 表的外键约束和字段
-- ============================================

-- 检查外键是否存在
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND CONSTRAINT_NAME = 'fk_prizes_lucky_id'
);

-- 删除外键约束
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE prizes DROP FOREIGN KEY fk_prizes_lucky_id',
    'SELECT ''Foreign key fk_prizes_lucky_id does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查索引是否存在
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND INDEX_NAME = 'idx_lucky_id'
);

-- 删除索引
SET @sql = IF(@index_exists > 0,
    'ALTER TABLE prizes DROP INDEX idx_lucky_id',
    'SELECT ''Index idx_lucky_id does not exist in prizes table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查字段是否存在
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND COLUMN_NAME = 'lucky_id'
);

-- 删除字段
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE prizes DROP COLUMN lucky_id',
    'SELECT ''Column lucky_id does not exist in prizes table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '✓ prizes 表的 lucky_id 字段、索引和外键已删除' AS status;

-- ============================================
-- 2. 删除 draw_prices 表的外键约束和字段
-- ============================================

-- 检查外键是否存在
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND CONSTRAINT_NAME = 'fk_draw_prices_lucky_id'
);

-- 删除外键约束
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE draw_prices DROP FOREIGN KEY fk_draw_prices_lucky_id',
    'SELECT ''Foreign key fk_draw_prices_lucky_id does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查索引是否存在
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND INDEX_NAME = 'idx_lucky_id'
);

-- 删除索引
SET @sql = IF(@index_exists > 0,
    'ALTER TABLE draw_prices DROP INDEX idx_lucky_id',
    'SELECT ''Index idx_lucky_id does not exist in draw_prices table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查字段是否存在
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND COLUMN_NAME = 'lucky_id'
);

-- 删除字段
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE draw_prices DROP COLUMN lucky_id',
    'SELECT ''Column lucky_id does not exist in draw_prices table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '✓ draw_prices 表的 lucky_id 字段、索引和外键已删除' AS status;

-- ============================================
-- 3. 删除 lucky_instances 表
-- ============================================

-- 检查表是否存在
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'lucky_instances'
);

-- 删除表
SET @sql = IF(@table_exists > 0,
    'DROP TABLE IF EXISTS lucky_instances',
    'SELECT ''Table lucky_instances does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '✓ lucky_instances 表已删除' AS status;

-- ============================================
-- 4. 删除 lucky_groups 表
-- ============================================

-- 检查表是否存在
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'lucky_groups'
);

-- 删除表
SET @sql = IF(@table_exists > 0,
    'DROP TABLE IF EXISTS lucky_groups',
    'SELECT ''Table lucky_groups does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT '✓ lucky_groups 表已删除' AS status;

-- ============================================
-- 5. 验证回滚结果
-- ============================================

SELECT '========== 回滚验证 ==========' AS message;

-- 验证表是否已删除
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ lucky_groups 和 lucky_instances 表已成功删除'
        ELSE '✗ 表删除失败，仍有表存在'
    END AS table_deletion_status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME IN ('lucky_groups', 'lucky_instances');

-- 验证 prizes 表的 lucky_id 字段是否已删除
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ prizes 表的 lucky_id 字段已删除'
        ELSE '✗ prizes 表的 lucky_id 字段仍然存在'
    END AS prizes_column_status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

-- 验证 draw_prices 表的 lucky_id 字段是否已删除
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ draw_prices 表的 lucky_id 字段已删除'
        ELSE '✗ draw_prices 表的 lucky_id 字段仍然存在'
    END AS draw_prices_column_status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

-- 验证索引是否已删除
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ lucky_id 索引已删除'
        ELSE '✗ lucky_id 索引仍然存在'
    END AS index_status
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND INDEX_NAME = 'idx_lucky_id'
AND TABLE_NAME IN ('prizes', 'draw_prices');

-- 验证外键约束是否已删除
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✓ 外键约束已删除'
        ELSE '✗ 外键约束仍然存在'
    END AS foreign_key_status
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND CONSTRAINT_NAME IN ('fk_prizes_lucky_id', 'fk_draw_prices_lucky_id');

SELECT '========== 回滚完成 ==========' AS message;
SELECT '所有Lucky实例相关的表结构已回滚到迁移前的状态' AS message;
SELECT '如果您有数据库备份，现在可以恢复备份数据' AS recommendation;
