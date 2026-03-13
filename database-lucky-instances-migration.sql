-- ============================================
-- Lucky实例统一系统 - 数据库迁移脚本
-- 创建时间：2024
-- 需求：11.1, 18.1, 18.2, 18.3, 18.4
-- ============================================

USE lucky_draw;

-- ============================================
-- 1. 创建 lucky_groups 表（分组管理）
-- ============================================

CREATE TABLE IF NOT EXISTS lucky_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '分组名称',
    description TEXT COMMENT '分组描述',
    icon VARCHAR(50) DEFAULT '🎰' COMMENT '分组图标',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sort_order (sort_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lucky分组表';

-- ============================================
-- 2. 创建 lucky_instances 表（Lucky实例配置）
-- ============================================

CREATE TABLE IF NOT EXISTS lucky_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Lucky实例名称（唯一标识）',
    display_name VARCHAR(100) NOT NULL COMMENT '显示名称',
    description TEXT COMMENT '描述',
    thumbnail_url VARCHAR(500) COMMENT '缩略图URL',
    background_url VARCHAR(500) COMMENT '背景图URL',
    group_id INT DEFAULT NULL COMMENT '所属分组ID',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES lucky_groups(id) ON DELETE SET NULL,
    INDEX idx_group_id (group_id),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lucky实例配置表';

-- ============================================
-- 3. 扩展 prizes 表（添加 lucky_id 字段）
-- ============================================

-- 检查 lucky_id 字段是否已存在，如果不存在则添加
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND COLUMN_NAME = 'lucky_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE prizes ADD COLUMN lucky_id INT DEFAULT NULL COMMENT ''Lucky实例ID'' AFTER id',
    'SELECT ''Column lucky_id already exists in prizes table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引（如果不存在）
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND INDEX_NAME = 'idx_lucky_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE prizes ADD INDEX idx_lucky_id (lucky_id)',
    'SELECT ''Index idx_lucky_id already exists in prizes table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加外键约束（如果不存在）
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes' 
    AND CONSTRAINT_NAME = 'fk_prizes_lucky_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE prizes ADD CONSTRAINT fk_prizes_lucky_id FOREIGN KEY (lucky_id) REFERENCES lucky_instances(id) ON DELETE CASCADE',
    'SELECT ''Foreign key fk_prizes_lucky_id already exists in prizes table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. 扩展 draw_prices 表（添加 lucky_id 字段）
-- ============================================

-- 检查 lucky_id 字段是否已存在，如果不存在则添加
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND COLUMN_NAME = 'lucky_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE draw_prices ADD COLUMN lucky_id INT DEFAULT NULL COMMENT ''Lucky实例ID'' AFTER id',
    'SELECT ''Column lucky_id already exists in draw_prices table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加索引（如果不存在）
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND INDEX_NAME = 'idx_lucky_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE draw_prices ADD INDEX idx_lucky_id (lucky_id)',
    'SELECT ''Index idx_lucky_id already exists in draw_prices table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 添加外键约束（如果不存在）
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'draw_prices' 
    AND CONSTRAINT_NAME = 'fk_draw_prices_lucky_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE draw_prices ADD CONSTRAINT fk_draw_prices_lucky_id FOREIGN KEY (lucky_id) REFERENCES lucky_instances(id) ON DELETE CASCADE',
    'SELECT ''Foreign key fk_draw_prices_lucky_id already exists in draw_prices table'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 5. 验证迁移结果
-- ============================================

-- 验证表是否创建成功
SELECT 
    CASE 
        WHEN COUNT(*) = 2 THEN '✓ lucky_groups 和 lucky_instances 表创建成功'
        ELSE '✗ 表创建失败'
    END AS table_creation_status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME IN ('lucky_groups', 'lucky_instances');

-- 验证 prizes 表的 lucky_id 字段
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ prizes 表的 lucky_id 字段已添加'
        ELSE '✗ prizes 表的 lucky_id 字段添加失败'
    END AS prizes_column_status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

-- 验证 draw_prices 表的 lucky_id 字段
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ draw_prices 表的 lucky_id 字段已添加'
        ELSE '✗ draw_prices 表的 lucky_id 字段添加失败'
    END AS draw_prices_column_status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

-- 验证索引
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN '✓ lucky_id 索引已创建'
        ELSE '✗ lucky_id 索引创建失败'
    END AS index_status
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND INDEX_NAME = 'idx_lucky_id'
AND TABLE_NAME IN ('prizes', 'draw_prices');

-- 验证外键约束
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN '✓ 外键约束已创建'
        ELSE '✗ 外键约束创建失败'
    END AS foreign_key_status
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND CONSTRAINT_NAME IN ('fk_prizes_lucky_id', 'fk_draw_prices_lucky_id');

-- 显示表结构
SELECT '========== lucky_groups 表结构 ==========' AS info;
DESCRIBE lucky_groups;

SELECT '========== lucky_instances 表结构 ==========' AS info;
DESCRIBE lucky_instances;

SELECT '========== prizes 表新增字段 ==========' AS info;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

SELECT '========== draw_prices 表新增字段 ==========' AS info;
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

SELECT '========== 迁移完成 ==========' AS message;
SELECT 'Lucky实例统一系统数据库表结构已创建完成！' AS message;
SELECT '下一步：运行数据迁移脚本，将现有的11个Lucky页面数据迁移到新表结构' AS next_step;
