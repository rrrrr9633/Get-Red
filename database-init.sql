-- 幸运降临数据库初始化脚本
-- 创建数据库
CREATE DATABASE IF NOT EXISTS lucky_draw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lucky_draw;

-- 1. 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50),
    avatar TEXT,
    balance DECIMAL(10,2) DEFAULT 1000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_online TINYINT(1) DEFAULT 0,
    last_activity TIMESTAMP NULL,
    email VARCHAR(100),
    user_type ENUM('user','service','super_admin') DEFAULT 'user',
    secret_key VARCHAR(100),
    ip_whitelist TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. 奖品表
CREATE TABLE IF NOT EXISTS prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(20),
    image_url VARCHAR(255),
    value DECIMAL(10,2),
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    game_type ENUM('lucky_drop','prize_draw','wheel'),
    probability DECIMAL(5,2),
    original_probability DECIMAL(10,4),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    quantity INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 用户物品表
CREATE TABLE IF NOT EXISTS user_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prize_id INT,
    name VARCHAR(100),
    icon VARCHAR(20),
    image_url VARCHAR(255),
    value DECIMAL(10,2),
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decomposed TINYINT(1) DEFAULT 0,
    decomposed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_prize_id (prize_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. 抽奖记录表
CREATE TABLE IF NOT EXISTS draws (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prize_id INT,
    prize_name VARCHAR(100),
    prize_value DECIMAL(10,2),
    draw_type VARCHAR(50) DEFAULT 'single',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. 用户签到表
CREATE TABLE IF NOT EXISTS user_checkin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    checkin_date DATE,
    consecutive_days INT DEFAULT 1,
    reward_amount DECIMAL(10,2) DEFAULT 10.00,
    reward_type ENUM('coins','item') DEFAULT 'coins',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. 安全日志表
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    user_type ENUM('user','service','admin','super_admin'),
    ip_address VARCHAR(45),
    action VARCHAR(100),
    details TEXT,
    status ENUM('success','failed'),
    reason VARCHAR(255),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_user_type (user_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. 客服配置表
CREATE TABLE IF NOT EXISTS customer_service_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type ENUM('online','qq','wechat'),
    title VARCHAR(100),
    content TEXT,
    contact_info VARCHAR(255),
    qr_code_url VARCHAR(500),
    is_enabled TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. 客服聊天会话表
CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    service_user_id INT,
    session_id VARCHAR(100) UNIQUE,
    status ENUM('waiting','active','closed') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_service_user_id (service_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. 客服聊天消息表
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    sender_id INT,
    sender_type ENUM('user','service'),
    message TEXT,
    message_type ENUM('text','image','file') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_sender_id (sender_id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. 管理员安全日志表
CREATE TABLE IF NOT EXISTS admin_security_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    action VARCHAR(50),
    status ENUM('success','failed'),
    reason VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. 客服用户分配表
CREATE TABLE IF NOT EXISTS service_user_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_user_id INT,
    regular_user_id INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    status ENUM('active','inactive') DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_service_user_id (service_user_id),
    INDEX idx_assigned_by (assigned_by),
    UNIQUE unique_regular_user (regular_user_id),
    FOREIGN KEY (service_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (regular_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. 交易记录表
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2),
    description VARCHAR(255),
    type ENUM('income','expense'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. 签到记录表
CREATE TABLE IF NOT EXISTS checkin_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE,
    reward DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. 抽奖历史表
CREATE TABLE IF NOT EXISTS draw_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    draw_type VARCHAR(50),
    cost DECIMAL(10,2),
    results TEXT,
    total_value DECIMAL(10,2),
    draw_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. 彩票记录表
CREATE TABLE IF NOT EXISTS lottery_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_type ENUM('lucky_drop','prize_draw','wheel'),
    cost DECIMAL(10,2),
    reward DECIMAL(10,2),
    result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. 奖品抽取日志表
CREATE TABLE IF NOT EXISTS prize_draw_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prize_id INT,
    prize_name VARCHAR(100),
    prize_table VARCHAR(50),
    rarity ENUM('common','rare','epic','legendary'),
    original_quantity INT,
    remaining_quantity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_prize_table (prize_table),
    INDEX idx_rarity (rarity),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. 充值历史表
CREATE TABLE IF NOT EXISTS recharge_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2),
    coins_gained INT,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. 充值选项表
CREATE TABLE IF NOT EXISTS recharge_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2),
    coins_reward INT DEFAULT 0,
    bonus_coins INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. 设置表
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(255) PRIMARY KEY,
    value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. 系统设置表
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. 抽奖价格表
CREATE TABLE IF NOT EXISTS draw_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(100),
    price_type ENUM('single','triple','quintuple'),
    price_value DECIMAL(10,2),
    button_name VARCHAR(50) DEFAULT NULL COMMENT '按钮显示名称',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE unique_page_price (page_name, price_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. 价格历史表
CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(100),
    price_type ENUM('single','triple','quintuple'),
    old_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    changed_by VARCHAR(100),
    change_reason VARCHAR(255) DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_page_name (page_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认充值选项
INSERT INTO recharge_options (amount, coins_reward, bonus_coins) VALUES
(10.00, 100, 0),
(50.00, 500, 50),
(100.00, 1000, 150),
(500.00, 5000, 1000);

-- 插入默认抽奖价格
INSERT INTO draw_prices (page_name, price_type, price_value) VALUES
('lucky1.html', 'single', 10.00),
('lucky1.html', 'triple', 28.00),
('lucky1.html', 'quintuple', 45.00);

-- 插入示例奖品数据
INSERT INTO prizes (name, icon, value, rarity, game_type, probability, original_probability, quantity) VALUES
('金币+10', '💰', 10.00, 'common', 'lucky_drop', 30.00, 30.0000, 1000),
('金币+50', '💰', 50.00, 'common', 'lucky_drop', 20.00, 20.0000, 500),
('金币+100', '💎', 100.00, 'rare', 'lucky_drop', 15.00, 15.0000, 300),
('金币+500', '💎', 500.00, 'epic', 'lucky_drop', 5.00, 5.0000, 100),
('金币+1000', '👑', 1000.00, 'legendary', 'lucky_drop', 1.00, 1.0000, 50),
('神秘礼盒', '🎁', 200.00, 'rare', 'lucky_drop', 10.00, 10.0000, 200);

-- 插入默认超级管理员（用户名: admin, 密码: password, 身份码: admin）
-- 注意：此账户在创建新超级管理员后会自动禁用
INSERT INTO users (username, password, nickname, user_type, secret_key, balance, status, created_at) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '默认超级管理员', 'super_admin', 'admin', 9999999.00, 'active', NOW());

SELECT '数据库初始化完成！默认超级管理员已创建（用户名: admin, 密码: password, 身份码: admin）' AS message;


-- ========================================
-- 提现系统相关表
-- ========================================

-- 23. 跑刀提现申请表（待处理的提现请求）
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT '提现金币数量',
    buff_coins DECIMAL(10,2) NOT NULL COMMENT '转换后的哈夫币数量（固定汇率1:10000）',
    status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending' COMMENT '状态：待处理、处理中、已完成、已拒绝',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
    processed_at TIMESTAMP NULL COMMENT '处理时间',
    processed_by INT NULL COMMENT '处理人ID',
    reject_reason VARCHAR(255) NULL COMMENT '拒绝原因',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跑刀提现申请表（只做跑刀提现，金币换哈夫币）';

-- 24. 跑刀提现历史记录表（已处理的提现记录）
CREATE TABLE IF NOT EXISTS withdrawal_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT '提现金币数量',
    buff_coins DECIMAL(10,2) NOT NULL COMMENT '转换后的哈夫币数量（固定汇率1:10000）',
    status ENUM('completed', 'rejected') NOT NULL COMMENT '最终状态',
    created_at TIMESTAMP NOT NULL COMMENT '申请时间',
    processed_at TIMESTAMP NOT NULL COMMENT '处理时间',
    processed_by INT NULL COMMENT '处理人ID',
    reject_reason VARCHAR(255) NULL COMMENT '拒绝原因',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跑刀提现历史记录表';

-- 25. 跑刀提现配置表
CREATE TABLE IF NOT EXISTS withdrawal_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) NOT NULL UNIQUE COMMENT '配置键',
    config_value VARCHAR(255) NOT NULL COMMENT '配置值',
    description VARCHAR(255) COMMENT '配置说明',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跑刀提现配置表（固定汇率1:10000）';

-- 插入跑刀提现配置
INSERT INTO withdrawal_config (config_key, config_value, description) VALUES
('exchange_rate', '10000', '兑换汇率（1金币=10000哈夫币，固定不变）'),
('min_amount', '100', '最小提现金币数'),
('max_amount', '10000', '最大提现金币数'),
('is_enabled', '1', '是否启用提现功能（1=启用，0=禁用）')
ON DUPLICATE KEY UPDATE config_key=config_key;

SELECT '提现系统表创建完成！' AS message;

-- ========================================
-- 商城系统相关表
-- ========================================

-- 26. 商城物品表（皮肤和护航）
CREATE TABLE IF NOT EXISTS shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '物品名称',
    icon VARCHAR(20) COMMENT '物品图标',
    image_url VARCHAR(500) COMMENT '物品图片URL',
    description TEXT COMMENT '物品描述',
    price DECIMAL(10,2) NOT NULL COMMENT '物品价格（金币）',
    item_type ENUM('skin', 'escort') NOT NULL COMMENT '物品类型：皮肤或护航',
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common' COMMENT '稀有度',
    stock INT DEFAULT -1 COMMENT '库存数量（-1表示无限）',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否上架',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_type (item_type),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城物品表（皮肤和护航）';

-- 27. 用户购买记录表
CREATE TABLE IF NOT EXISTS shop_purchase_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shop_item_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    item_type ENUM('skin', 'escort') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    purchase_type ENUM('coin', 'legendary') DEFAULT 'coin' COMMENT '购买方式：金币或传说级兑换',
    used_items TEXT COMMENT '使用的传说级物品JSON（仅传说级兑换）',
    player_id VARCHAR(100) COMMENT '玩家ID（提现账号）',
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending' COMMENT '订单状态',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL COMMENT '处理人ID',
    notes TEXT COMMENT '备注信息',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_purchase_type (purchase_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户购买记录表';

-- 插入示例商城物品
INSERT INTO shop_items (name, icon, image_url, description, price, item_type, rarity, stock, sort_order) VALUES
-- 皮肤类
('AK-47 | 火蛇', '🔫', '', '经典红色火蛇皮肤，稀有度高', 2500.00, 'skin', 'legendary', 10, 1),
('AWP | 龙狙', '🎯', '', '传说级龙狙皮肤', 3500.00, 'skin', 'legendary', 5, 2),
('M4A4 | 咆哮', '💥', '', '史诗级咆哮皮肤', 1800.00, 'skin', 'epic', 20, 3),
('沙漠之鹰 | 烈焰', '🔥', '', '稀有烈焰皮肤', 800.00, 'skin', 'rare', 50, 4),
('格洛克 | 水元素', '💧', '', '普通水元素皮肤', 300.00, 'skin', 'common', -1, 5),

-- 护航类
('金牌护航 - 至尊版', '👑', '', '最高级别护航服务，全程保障', 5000.00, 'escort', 'legendary', 3, 1),
('金牌护航 - 豪华版', '💎', '', '豪华护航服务，安全可靠', 3000.00, 'escort', 'epic', 10, 2),
('金牌护航 - 标准版', '🛡️', '', '标准护航服务', 1500.00, 'escort', 'rare', 30, 3),
('金牌护航 - 基础版', '🔰', '', '基础护航服务', 800.00, 'escort', 'common', -1, 4);

SELECT '商城系统表创建完成！' AS message;

-- ========================================
-- 传说级兑换系统相关表
-- ========================================

-- 28. 传说级兑换配置表（简化版）
CREATE TABLE IF NOT EXISTS legendary_exchange_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_item_id INT NOT NULL COMMENT '目标商城物品ID',
    required_items TEXT NOT NULL COMMENT '所需传说物品JSON数组 [{"prize_id":1,"name":"物品名","quantity":1}]',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_item_id) REFERENCES shop_items(id) ON DELETE CASCADE,
    INDEX idx_shop_item_id (shop_item_id),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='传说级兑换配置表';

SELECT '传说级兑换系统表创建完成！' AS message;

-- ========================================
-- 商店图标配置表
-- ========================================

-- 29. 商店图标配置表
CREATE TABLE IF NOT EXISTS shop_icon_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon_key VARCHAR(50) NOT NULL UNIQUE COMMENT '图标键名（gold-escort, knife-exchange, skin-exchange, legendary-exchange）',
    icon_name VARCHAR(100) NOT NULL COMMENT '图标显示名称',
    icon_url VARCHAR(500) COMMENT '图标图片URL',
    fallback_icon VARCHAR(20) DEFAULT '🎁' COMMENT '备用图标（Emoji）',
    description TEXT COMMENT '描述',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_icon_key (icon_key),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商店图标配置表';

-- 插入默认图标配置
INSERT INTO shop_icon_config (icon_key, icon_name, icon_url, fallback_icon, description, sort_order) VALUES
('gold-escort', '金牌护航', '../images/shop/gold-escort.png', '🛡️', '保障您的每一次抽奖体验', 1),
('knife-exchange', '1:1跑刀', '../images/shop/knife-exchange.png', '🔪', '公平公正的刀具兑换服务', 2),
('skin-exchange', '皮肤兑换', '../images/shop/skin-exchange.png', '🎨', '精美皮肤，随心兑换', 3),
('legendary-exchange', '传说级兑换', '../images/shop/legendary-exchange.png', '⭐', '顶级稀有物品，尊享兑换', 4)
ON DUPLICATE KEY UPDATE icon_key=icon_key;

SELECT '商店图标配置表创建完成！' AS message;
