-- ============================================
-- Lucky实例统一系统 - 数据库迁移测试脚本
-- 用途：测试迁移脚本的正确性
-- 需求：11.1
-- ============================================

USE lucky_draw;

SELECT '========================================' AS '';
SELECT '数据库迁移测试开始' AS '';
SELECT NOW() AS '测试时间';
SELECT '========================================' AS '';
SELECT '' AS '';

-- ============================================
-- 测试 1: 表创建测试
-- ============================================

SELECT '========== 测试 1: 表创建测试 ==========' AS '';

-- 测试 lucky_groups 表是否创建
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：lucky_groups 表已创建'
        ELSE '✗ 失败：lucky_groups 表未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_groups';

-- 测试 lucky_instances 表是否创建
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：lucky_instances 表已创建'
        ELSE '✗ 失败：lucky_instances 表未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances';

-- 验证表引擎和字符集
SELECT 
    CASE 
        WHEN ENGINE = 'InnoDB' AND TABLE_COLLATION LIKE 'utf8mb4%' 
        THEN '✓ 通过：lucky_groups 表引擎和字符集正确'
        ELSE '✗ 失败：lucky_groups 表引擎或字符集不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_groups';

SELECT 
    CASE 
        WHEN ENGINE = 'InnoDB' AND TABLE_COLLATION LIKE 'utf8mb4%' 
        THEN '✓ 通过：lucky_instances 表引擎和字符集正确'
        ELSE '✗ 失败：lucky_instances 表引擎或字符集不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances';

SELECT '' AS '';

-- ============================================
-- 测试 2: 字段扩展测试
-- ============================================

SELECT '========== 测试 2: 字段扩展测试 ==========' AS '';

-- 测试 prizes 表的 lucky_id 字段
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：prizes.lucky_id 字段已添加'
        ELSE '✗ 失败：prizes.lucky_id 字段未添加'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

-- 验证 prizes.lucky_id 字段类型
SELECT 
    CASE 
        WHEN COLUMN_TYPE = 'int' AND IS_NULLABLE = 'YES' 
        THEN '✓ 通过：prizes.lucky_id 字段类型正确（INT, NULL）'
        ELSE '✗ 失败：prizes.lucky_id 字段类型不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes' 
AND COLUMN_NAME = 'lucky_id';

-- 测试 draw_prices 表的 lucky_id 字段
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✓ 通过：draw_prices.lucky_id 字段已添加'
        ELSE '✗ 失败：draw_prices.lucky_id 字段未添加'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

-- 验证 draw_prices.lucky_id 字段类型
SELECT 
    CASE 
        WHEN COLUMN_TYPE = 'int' AND IS_NULLABLE = 'YES' 
        THEN '✓ 通过：draw_prices.lucky_id 字段类型正确（INT, NULL）'
        ELSE '✗ 失败：draw_prices.lucky_id 字段类型不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices' 
AND COLUMN_NAME = 'lucky_id';

SELECT '' AS '';

-- ============================================
-- 测试 3: 索引创建测试
-- ============================================

SELECT '========== 测试 3: 索引创建测试 ==========' AS '';

-- 测试 lucky_groups 表索引
SELECT 
    CASE 
        WHEN COUNT(*) = 2 THEN '✓ 通过：lucky_groups 表索引已创建（idx_sort_order, idx_is_active）'
        ELSE CONCAT('✗ 失败：lucky_groups 表索引不完整，当前数量：', COUNT(*))
    END AS '测试结果'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_groups'
AND INDEX_NAME IN ('idx_sort_order', 'idx_is_active');

-- 测试 lucky_instances 表索引
SELECT 
    CASE 
        WHEN COUNT(*) = 4 THEN '✓ 通过：lucky_instances 表索引已创建（idx_group_id, idx_is_active, idx_sort_order, idx_name）'
        ELSE CONCAT('✗ 失败：lucky_instances 表索引不完整，当前数量：', COUNT(*))
    END AS '测试结果'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND INDEX_NAME IN ('idx_group_id', 'idx_is_active', 'idx_sort_order', 'idx_name');

-- 测试 prizes 表 lucky_id 索引
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：prizes.lucky_id 索引已创建'
        ELSE '✗ 失败：prizes.lucky_id 索引未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes'
AND INDEX_NAME = 'idx_lucky_id';

-- 测试 draw_prices 表 lucky_id 索引
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：draw_prices.lucky_id 索引已创建'
        ELSE '✗ 失败：draw_prices.lucky_id 索引未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices'
AND INDEX_NAME = 'idx_lucky_id';

SELECT '' AS '';

-- ============================================
-- 测试 4: 外键约束测试
-- ============================================

SELECT '========== 测试 4: 外键约束测试 ==========' AS '';

-- 测试 lucky_instances.group_id 外键
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：lucky_instances.group_id 外键约束已创建'
        ELSE '✗ 失败：lucky_instances.group_id 外键约束未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME = 'group_id'
AND REFERENCED_TABLE_NAME = 'lucky_groups';

-- 测试 prizes.lucky_id 外键
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：prizes.lucky_id 外键约束已创建'
        ELSE '✗ 失败：prizes.lucky_id 外键约束未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes'
AND COLUMN_NAME = 'lucky_id'
AND REFERENCED_TABLE_NAME = 'lucky_instances';

-- 测试 draw_prices.lucky_id 外键
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：draw_prices.lucky_id 外键约束已创建'
        ELSE '✗ 失败：draw_prices.lucky_id 外键约束未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices'
AND COLUMN_NAME = 'lucky_id'
AND REFERENCED_TABLE_NAME = 'lucky_instances';

SELECT '' AS '';

-- ============================================
-- 测试 5: 外键删除行为测试
-- ============================================

SELECT '========== 测试 5: 外键删除行为测试 ==========' AS '';

-- 测试 lucky_instances.group_id 的 ON DELETE SET NULL
SELECT 
    CASE 
        WHEN DELETE_RULE = 'SET NULL' THEN '✓ 通过：lucky_instances.group_id 删除规则为 SET NULL'
        ELSE CONCAT('✗ 失败：lucky_instances.group_id 删除规则不正确，当前为：', DELETE_RULE)
    END AS '测试结果'
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND REFERENCED_TABLE_NAME = 'lucky_groups';

-- 测试 prizes.lucky_id 的 ON DELETE CASCADE
SELECT 
    CASE 
        WHEN DELETE_RULE = 'CASCADE' THEN '✓ 通过：prizes.lucky_id 删除规则为 CASCADE'
        ELSE CONCAT('✗ 失败：prizes.lucky_id 删除规则不正确，当前为：', DELETE_RULE)
    END AS '测试结果'
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'prizes'
AND REFERENCED_TABLE_NAME = 'lucky_instances';

-- 测试 draw_prices.lucky_id 的 ON DELETE CASCADE
SELECT 
    CASE 
        WHEN DELETE_RULE = 'CASCADE' THEN '✓ 通过：draw_prices.lucky_id 删除规则为 CASCADE'
        ELSE CONCAT('✗ 失败：draw_prices.lucky_id 删除规则不正确，当前为：', DELETE_RULE)
    END AS '测试结果'
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'draw_prices'
AND REFERENCED_TABLE_NAME = 'lucky_instances';

SELECT '' AS '';

-- ============================================
-- 测试 6: 唯一性约束测试
-- ============================================

SELECT '========== 测试 6: 唯一性约束测试 ==========' AS '';

-- 测试 lucky_instances.name 唯一性约束
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：lucky_instances.name 唯一性约束已创建'
        ELSE '✗ 失败：lucky_instances.name 唯一性约束未创建'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME = 'name'
AND NON_UNIQUE = 0;

SELECT '' AS '';

-- ============================================
-- 测试 7: 约束验证测试（插入测试数据）
-- ============================================

SELECT '========== 测试 7: 约束验证测试 ==========' AS '';

-- 清理测试数据（如果存在）
DELETE FROM lucky_instances WHERE name LIKE 'test_%';
DELETE FROM lucky_groups WHERE name LIKE 'test_%';

-- 测试 7.1: 插入测试分组
INSERT INTO lucky_groups (name, description, icon, sort_order) 
VALUES ('test_group', '测试分组', '🧪', 999);

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：成功插入测试分组'
        ELSE '✗ 失败：插入测试分组失败'
    END AS '测试结果'
FROM lucky_groups 
WHERE name = 'test_group';

-- 获取测试分组ID
SET @test_group_id = (SELECT id FROM lucky_groups WHERE name = 'test_group' LIMIT 1);

-- 测试 7.2: 插入测试实例
INSERT INTO lucky_instances (name, display_name, description, group_id, is_active) 
VALUES ('test_instance', '测试实例', '这是一个测试实例', @test_group_id, 1);

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 通过：成功插入测试实例'
        ELSE '✗ 失败：插入测试实例失败'
    END AS '测试结果'
FROM lucky_instances 
WHERE name = 'test_instance';

-- 获取测试实例ID
SET @test_instance_id = (SELECT id FROM lucky_instances WHERE name = 'test_instance' LIMIT 1);

-- 测试 7.3: 测试唯一性约束（尝试插入重复名称）
SET @duplicate_error = 0;
BEGIN
    DECLARE CONTINUE HANDLER FOR 1062 SET @duplicate_error = 1;
    INSERT INTO lucky_instances (name, display_name) 
    VALUES ('test_instance', '重复测试实例');
END;

SELECT 
    CASE 
        WHEN @duplicate_error = 1 THEN '✓ 通过：唯一性约束生效，阻止了重复名称'
        ELSE '✗ 失败：唯一性约束未生效'
    END AS '测试结果';

-- 测试 7.4: 测试外键约束（插入关联数据）
-- 注意：这里假设 prizes 和 draw_prices 表已存在
-- 如果表不存在，这些测试将被跳过

-- 检查 prizes 表是否存在
SET @prizes_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'lucky_draw' 
    AND TABLE_NAME = 'prizes'
);

-- 如果 prizes 表存在，测试插入关联奖品
SET @sql = IF(@prizes_exists > 0,
    CONCAT('INSERT INTO prizes (name, lucky_id, probability, active) VALUES (''测试奖品'', ', @test_instance_id, ', 100, 1)'),
    'SELECT ''跳过：prizes 表不存在'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 
    CASE 
        WHEN @prizes_exists = 0 THEN '⊘ 跳过：prizes 表不存在'
        WHEN (SELECT COUNT(*) FROM prizes WHERE name = '测试奖品' AND lucky_id = @test_instance_id) > 0 
        THEN '✓ 通过：成功插入关联奖品'
        ELSE '✗ 失败：插入关联奖品失败'
    END AS '测试结果';

-- 测试 7.5: 测试级联删除
-- 删除测试实例，验证关联奖品是否被级联删除
DELETE FROM lucky_instances WHERE id = @test_instance_id;

SELECT 
    CASE 
        WHEN @prizes_exists = 0 THEN '⊘ 跳过：prizes 表不存在'
        WHEN (SELECT COUNT(*) FROM prizes WHERE name = '测试奖品' AND lucky_id = @test_instance_id) = 0 
        THEN '✓ 通过：级联删除生效，关联奖品已删除'
        ELSE '✗ 失败：级联删除未生效'
    END AS '测试结果';

-- 测试 7.6: 测试 SET NULL 行为
-- 删除测试分组，验证实例的 group_id 是否被设为 NULL
-- 先重新插入测试实例
INSERT INTO lucky_instances (name, display_name, group_id) 
VALUES ('test_instance2', '测试实例2', @test_group_id);

SET @test_instance2_id = (SELECT id FROM lucky_instances WHERE name = 'test_instance2' LIMIT 1);

-- 删除测试分组
DELETE FROM lucky_groups WHERE id = @test_group_id;

SELECT 
    CASE 
        WHEN (SELECT group_id FROM lucky_instances WHERE id = @test_instance2_id) IS NULL 
        THEN '✓ 通过：SET NULL 生效，group_id 已设为 NULL'
        ELSE '✗ 失败：SET NULL 未生效'
    END AS '测试结果';

-- 清理测试数据
DELETE FROM lucky_instances WHERE name LIKE 'test_%';
DELETE FROM lucky_groups WHERE name LIKE 'test_%';
DELETE FROM prizes WHERE name = '测试奖品';

SELECT '' AS '';

-- ============================================
-- 测试 8: 表结构完整性测试
-- ============================================

SELECT '========== 测试 8: 表结构完整性测试 ==========' AS '';

-- 测试 lucky_groups 表必需字段
SELECT 
    CASE 
        WHEN COUNT(*) = 8 THEN '✓ 通过：lucky_groups 表包含所有必需字段'
        ELSE CONCAT('✗ 失败：lucky_groups 表字段不完整，当前数量：', COUNT(*))
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_groups'
AND COLUMN_NAME IN ('id', 'name', 'description', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at');

-- 测试 lucky_instances 表必需字段
SELECT 
    CASE 
        WHEN COUNT(*) = 11 THEN '✓ 通过：lucky_instances 表包含所有必需字段'
        ELSE CONCAT('✗ 失败：lucky_instances 表字段不完整，当前数量：', COUNT(*))
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME IN ('id', 'name', 'display_name', 'description', 'thumbnail_url', 'background_url', 'group_id', 'sort_order', 'is_active', 'created_at', 'updated_at');

-- 测试默认值设置
SELECT 
    CASE 
        WHEN COLUMN_DEFAULT = '1' THEN '✓ 通过：lucky_groups.is_active 默认值为 1'
        ELSE '✗ 失败：lucky_groups.is_active 默认值不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_groups'
AND COLUMN_NAME = 'is_active';

SELECT 
    CASE 
        WHEN COLUMN_DEFAULT = '1' THEN '✓ 通过：lucky_instances.is_active 默认值为 1'
        ELSE '✗ 失败：lucky_instances.is_active 默认值不正确'
    END AS '测试结果'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lucky_draw' 
AND TABLE_NAME = 'lucky_instances'
AND COLUMN_NAME = 'is_active';

SELECT '' AS '';

-- ============================================
-- 测试总结
-- ============================================

SELECT '========================================' AS '';
SELECT '测试总结' AS '';
SELECT '========================================' AS '';

-- 统计测试结果
SELECT 
    '表创建' AS '测试类别',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND TABLE_NAME IN ('lucky_groups', 'lucky_instances')) = 2 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '字段扩展' AS '测试类别',
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
    '索引创建' AS '测试类别',
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
    '外键约束' AS '测试类别',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND REFERENCED_TABLE_NAME IN ('lucky_groups', 'lucky_instances')
              AND TABLE_NAME IN ('lucky_instances', 'prizes', 'draw_prices')) >= 3 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '删除行为' AS '测试类别',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
              WHERE CONSTRAINT_SCHEMA = 'lucky_draw' 
              AND ((TABLE_NAME = 'lucky_instances' AND DELETE_RULE = 'SET NULL')
                   OR (TABLE_NAME IN ('prizes', 'draw_prices') AND DELETE_RULE = 'CASCADE'))) >= 3 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态'
UNION ALL
SELECT 
    '唯一性约束' AS '测试类别',
    CASE 
        WHEN (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
              WHERE TABLE_SCHEMA = 'lucky_draw' 
              AND TABLE_NAME = 'lucky_instances'
              AND COLUMN_NAME = 'name'
              AND NON_UNIQUE = 0) > 0 
        THEN '✓ 通过' 
        ELSE '✗ 失败' 
    END AS '状态';

SELECT '' AS '';
SELECT '========================================' AS '';
SELECT '数据库迁移测试完成' AS '';
SELECT NOW() AS '完成时间';
SELECT '========================================' AS '';
SELECT '如果所有测试都显示 ✓ 通过，则迁移脚本正确' AS '';
