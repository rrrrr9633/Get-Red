-- ============================================
-- 插入测试数据用于验证 lucky-config.php API
-- ============================================

USE lucky_draw;

-- 插入测试分组
INSERT INTO lucky_groups (id, name, description, icon, sort_order, is_active) 
VALUES 
(1, '零号大坝', '零号大坝系列', '🏔️', 1, 1),
(2, '长弓溪谷', '长弓溪谷系列', '🏞️', 2, 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    description = VALUES(description),
    icon = VALUES(icon);

-- 插入测试Lucky实例
INSERT INTO lucky_instances (id, name, display_name, description, thumbnail_url, background_url, group_id, sort_order, is_active) 
VALUES 
(1, 'lucky1', '零号大坝(普通)', '零号大坝危机四伏', 'images/thumbs/lucky1.png', 'images/shop/lucky1.png', 1, 1, 1),
(2, 'lucky2', '零号大坝(机密)', '零号大坝危机四伏', 'images/thumbs/lucky2.png', 'images/shop/lucky2.png', 1, 2, 1),
(3, 'lucky3', '长弓溪谷(普通)', '长弓溪谷等待探索', 'images/thumbs/lucky3.png', 'images/shop/lucky3.png', 2, 1, 1),
(999, 'test_inactive', '未激活测试实例', '这是一个未激活的测试实例', 'images/test.png', 'images/test_bg.png', NULL, 999, 0)
ON DUPLICATE KEY UPDATE 
    display_name = VALUES(display_name),
    description = VALUES(description),
    thumbnail_url = VALUES(thumbnail_url),
    background_url = VALUES(background_url),
    group_id = VALUES(group_id),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

-- 验证插入结果
SELECT '========== 测试数据插入完成 ==========' AS message;

SELECT 'Lucky分组数据:' AS info;
SELECT * FROM lucky_groups WHERE id IN (1, 2);

SELECT 'Lucky实例数据:' AS info;
SELECT * FROM lucky_instances WHERE id IN (1, 2, 3, 999);

SELECT '========== 可以开始测试API了 ==========' AS message;
