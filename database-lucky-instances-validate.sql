-- ============================================
-- Lucky实例统一系统 - 数据库验证脚本
-- 用途：验证迁移是否成功完成
-- ============================================

USE lucky_draw;

SELECT '========== 数据库迁移验证报告 ==========' AS title;
SELECT NOW() AS validation_time;
SELECT '' AS blank;

-- ============================================
-- 1. 验证表是否存在
-- ============================================

SELECT '========== 1. 表存在性检查 ==========' AS section;

SELECT 
    TABLE_NAME AS '表名',
    ENGINE AS '引擎',
    TABLE_COLLATION AS '字符集',
    CREATE_TIME AS '创建时间',
    TABLE_COMMENT AS '表注释'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME IN ('lucky_groups', 'lucky_instances')
ORDER BY TABLE_NAME;

SELECT 
    CASE 
        WHEN COUNT(*) = 2 THEN '✓ 通过：lucky_groups 和 lucky_instances 表已创建'
        WHEN COUNT(*) = 1 THEN '✗ 失败：只创建了部分表'
        ELSE '✗ 失败：表未创建'
    END AS result
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME IN ('lucky_groups', 'lucky_instances');

SELECT '' AS blank;

-- ============================================
-- 2. 验证表结构
-- ============================================

SELECT '========== 2. lucky_groups 表结构 ==========' AS section;
DESCRIBE lucky_groups;

SELECT '' AS blank;

SELECT '========== 3. lucky_instances 表结构 ==========' AS section;
DESCRIBE lucky_instances;

SELECT '' AS blank;

-- ============================================
-- 4. 验证字段扩展
-- ============================================

SELECT '========== 4. 字段扩展检查 ==========' AS section;

-- prizes 表的 lucky_id 字段
SELECT 
    TABLE_NAME AS '表名',
    COLUMN_NAME AS '字段名',
    COLUMN_TYPE AS '字段类型',
    IS_NULLABLE AS '可为空',
    COLUMN_DEFAULT AS '默认值',
    COLUMN_COMMENT AS '注释'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：prizes 表的 lucky_id 字段已添加'
        ELSE '✗ 失败：prizes 表的 lucky_id 字段未添加'
    END AS result
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

-- draw_prices 表的 lucky_id 字段
SELECT 
    TABLE_NAME AS '表名',
    COLUMN_NAME AS '字段名',
    COLUMN_TYPE AS '字段类型',
    IS_NULLABLE AS '可为空',
    COLUMN_DEFAULT AS '默认值',
    COLUMN_COMMENT AS '注释'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：draw_prices 表的 lucky_id 字段已添加'
        ELSE '✗ 失败：draw_prices 表的 lucky_id 字段未添加'
    END AS result
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

SELECT '' AS blank;

-- ============================================
-- 5. 验证索引
-- ============================================

SELECT '========== 5. 索引检查 ==========' AS section;

SELECT 
    TABLE_NAME AS '表名',
    INDEX_NAME AS '索引名',
    COLUMN_NAME AS '字段名',
    NON_UNIQUE AS '非唯一',
    INDEX_TYPE AS '索引类型'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND (
    (TABLE_NAME = 'lucky_groups' AND INDEX_NAME IN ('idx_sort_order', 'idx_is_active'))
    OR (TABLE_NAME = 'lucky_instances' AND INDEX_NAME IN ('idx_group_id', 'idx_is_active', 'idx_sort_order', 'idx_name'))
    OR (TABLE_NAME = 'prizes' AND INDEX_NAME = 'idx_lucky_id')
    OR (TABLE_NAME = 'draw_prices' AND INDEX_NAME = 'idx_lucky_id')
)
ORDER BY TABLE_NAME, INDEX_NAME;

SELECT 
    CASE 
        WHEN COUNT(*) >= 8 THEN '✓ 通过：所有必需索引已创建'
        ELSE CONCAT('✗ 失败：只创建了 ', COUNT(*), ' 个索引，应该至少有 8 个')
    END AS result
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND (
    (TABLE_NAME = 'lucky_groups' AND INDEX_NAME IN ('idx_sort_order', 'idx_is_active'))
    OR (TABLE_NAME = 'lucky_instances' AND INDEX_NAME IN ('idx_group_id', 'idx_is_active', 'idx_sort_order', 'idx_name'))
    OR (TABLE_NAME = 'prizes' AND INDEX_NAME = 'idx_lucky_id')
    OR (TABLE_NAME = 'draw_prices' AND INDEX_NAME = 'idx_lucky_id')
);

SELECT '' AS blank;

-- ============================================
-- 6. 验证外键约束
-- ============================================

SELECT '========== 6. 外键约束检查 ==========' AS section;

SELECT 
    TABLE_NAME AS '表名',
    COLUMN_NAME AS '字段名',
    CONSTRAINT_NAME AS '约束名',
    REFERENCED_TABLE_NAME AS '引用表',
    REFERENCED_COLUMN_NAME AS '引用字段'
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND CONSTRAINT_NAME IN (
    'lucky_instances_ibfk_1',
    'fk_prizes_lucky_id', 
    'fk_draw_prices_lucky_id'
)
ORDER BY TABLE_NAME;

SELECT 
    CASE 
        WHEN COUNT(*) >= 3 THEN '✓ 通过：所有外键约束已创建'
        ELSE CONCAT('✗ 失败：只创建了 ', COUNT(*), ' 个外键约束，应该至少有 3 个')
    END AS result
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND (
    CONSTRAINT_NAME LIKE 'lucky_instances_ibfk_%'
    OR CONSTRAINT_NAME IN ('fk_prizes_lucky_id', 'fk_draw_prices_lucky_id')
);

SELECT '' AS blank;

-- ============================================
-- 7. 验证外键删除行为
-- ============================================

SELECT '========== 7. 外键删除行为检查 ==========' AS section;

SELECT 
    TABLE_NAME AS '表名',
    CONSTRAINT_NAME AS '约束名',
    REFERENCED_TABLE_NAME AS '引用表',
    DELETE_RULE AS '删除规则',
    UPDATE_RULE AS '更新规则'
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'lucky_draw' 
AND (
    CONSTRAINT_NAME LIKE 'lucky_instances_ibfk_%'
    OR CONSTRAINT_NAME IN ('fk_prizes_lucky_id', 'fk_draw_prices_lucky_id')
)
ORDER BY TABLE_NAME;

SELECT '' AS blank;

-- ============================================
-- 8. 数据统计
-- ============================================

SELECT '========== 8. 数据统计 ==========' AS section;

SELECT 
    'lucky_groups' AS '表名',
    COUNT(*) AS '记录数'
FROM lucky_groups
UNION ALL
SELECT 
    'lucky_instances' AS '表名',
    COUNT(*) AS '记录数'
FROM lucky_instances
UNION ALL
SELECT 
    'prizes (with lucky_id)' AS '表名',
    COUNT(*) AS '记录数'
FROM prizes
WHERE lucky_id IS NOT NULL
UNION ALL
SELECT 
    'draw_prices (with lucky_id)' AS '表名',
    COUNT(*) AS '记录数'
FROM draw_prices
WHERE lucky_id IS NOT NULL;

SELECT '' AS blank;

-- ============================================
-- 9. 唯一性约束检查
-- ============================================

SELECT '========== 9. 唯一性约束检查 ==========' AS section;

SELECT 
    TABLE_NAME AS '表名',
    COLUMN_NAME AS '字段名',
    INDEX_NAME AS '索引名',
    NON_UNIQUE AS '非唯一'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME = 'name'
AND NON_UNIQUE = 0;

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：lucky_instances.name 字段有唯一性约束'
        ELSE '✗ 失败：lucky_instances.name 字段缺少唯一性约束'
    END AS result
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME = 'name'
AND NON_UNIQUE = 0;

SELECT '' AS blank;

-- ============================================
-- 10. 总结
-- ============================================

SELECT '========== 验证总结 ==========' AS section;

SELECT 
    '表创建' AS '检查项',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND TABLE_NAME IN ('lucky_groups', 'lucky_instances')) = 2 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '字段扩展' AS '检查项',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND COLUMN_NAME = 'lucky_id'
              AND TABLE_NAME IN ('prizes', 'draw_prices')) = 2 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '索引创建' AS '检查项',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND INDEX_NAME = 'idx_lucky_id'
              AND TABLE_NAME IN ('prizes', 'draw_prices')) >= 2 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '外键约束' AS '检查项',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND (CONSTRAINT_NAME LIKE 'lucky_instances_ibfk_%'
                   OR CONSTRAINT_NAME IN ('fk_prizes_lucky_id', 'fk_draw_prices_lucky_id'))) >= 3 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '唯一性约束' AS '检查项',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND TABLE_NAME = 'lucky_instances'
              AND COLUMN_NAME = 'name'
              AND NON_UNIQUE = 0) > 0 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态';

SELECT '' AS blank;
SELECT '========== 验证完成 ==========' AS message;
SELECT '如果所有检查项都显示 ✓ 通过，则迁移成功完成' AS message;
