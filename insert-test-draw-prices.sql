-- 插入测试抽奖价格数据
-- Task 4.1 测试数据

-- 为 lucky_id = 1 插入价格配置
INSERT INTO draw_prices (lucky_id, price_type, price_value, button_name) VALUES
(1, 'single', 10.00, '单抽'),
(1, 'triple', 28.00, '三连抽'),
(1, 'quintuple', 45.00, '五连抽');

-- 为 lucky_id = 2 插入价格配置（不同的价格）
INSERT INTO draw_prices (lucky_id, price_type, price_value, button_name) VALUES
(2, 'single', 15.00, '单抽'),
(2, 'triple', 40.00, '三连抽'),
(2, 'quintuple', 65.00, '五连抽');

-- 为 lucky_id = 3 插入价格配置
INSERT INTO draw_prices (lucky_id, price_type, price_value, button_name) VALUES
(3, 'single', 20.00, '单抽'),
(3, 'triple', 55.00, '三连抽'),
(3, 'quintuple', 90.00, '五连抽');

SELECT 'Test data inserted successfully!' AS message;
