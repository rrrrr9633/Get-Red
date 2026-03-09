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
    balance DECIMAL(10,2) DEFAULT 10.00,
    bound_coins DECIMAL(10,2) DEFAULT 0.00 COMMENT '绑定金币（签到、分解普通/稀有/史诗物品获得）',
    unbound_coins DECIMAL(10,2) DEFAULT 0.00 COMMENT '非绑定金币（充值、分解传说物品获得）',
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
    session_token VARCHAR(64) COMMENT '当前登录会话token，用于单点登录控制',
    login_ip VARCHAR(45) COMMENT '最后登录IP地址',
    login_device VARCHAR(255) COMMENT '最后登录设备信息',
    has_recharged TINYINT(1) DEFAULT 0 COMMENT '是否充值过（0=未充值，1=已充值）',
    INDEX idx_last_activity (last_activity),
    INDEX idx_session_token (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. 统一奖品表（所有Lucky页面共享）
CREATE TABLE IF NOT EXISTS prizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '奖品名称',
    icon VARCHAR(20) DEFAULT '?' COMMENT '奖品图标',
    image_url VARCHAR(500) COMMENT '奖品图片URL',
    value DECIMAL(10,2) DEFAULT 0.00 COMMENT '奖品价值',
    probability DECIMAL(5,2) DEFAULT 0.00 COMMENT '默认中奖概率',
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common' COMMENT '稀有度',
    quantity INT COMMENT '数量限制（传说物品）',
    original_probability DECIMAL(10,4) COMMENT '原始概率（传说物品）',
    active TINYINT(1) DEFAULT 1 COMMENT '全局启用状态',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rarity (rarity),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一奖品表';

-- 2.1 奖品与Lucky页面关联表
CREATE TABLE IF NOT EXISTS prize_lucky_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prize_id INT NOT NULL COMMENT '奖品ID',
    lucky_page VARCHAR(50) NOT NULL COMMENT 'Lucky页面标识（如lucky1, lucky2）',
    enabled TINYINT(1) DEFAULT 1 COMMENT '在该页面是否启用',
    page_probability DECIMAL(5,2) DEFAULT NULL COMMENT '在该页面的特定概率（NULL表示使用奖品默认概率）',
    page_quantity INT DEFAULT NULL COMMENT '页面特定数量（传说物品，NULL表示使用全局数量）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_prize_page (prize_id, lucky_page),
    FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE,
    INDEX idx_lucky_page (lucky_page),
    INDEX idx_enabled (enabled),
    INDEX idx_prize_page_enabled (prize_id, lucky_page, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='奖品与Lucky页面关联表';

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
    INDEX idx_user_obtained (user_id, obtained_at),
    INDEX idx_user_rarity (user_id, rarity),
    INDEX idx_decomposed (decomposed),
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
    cost DECIMAL(10,2) DEFAULT 0.00 COMMENT '抽奖消耗的金币',
    coin_type ENUM('unbound') DEFAULT 'unbound' COMMENT '使用的金币类型',
    draw_type VARCHAR(50) DEFAULT 'single',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_draw_type (draw_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. 用户签到表
CREATE TABLE IF NOT EXISTS user_checkin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    checkin_date DATE,
    consecutive_days INT DEFAULT 1,
    reward_amount DECIMAL(10,2) DEFAULT 10.00,
    coin_type ENUM('bound','unbound') DEFAULT 'bound' COMMENT '奖励金币类型',
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
    INDEX idx_created_at (created_at),
    INDEX idx_user_action_time (user_id, action, created_at),
    INDEX idx_ip_created (ip_address, created_at)
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
    INDEX idx_session_created (session_id, created_at),
    INDEX idx_session_read (session_id, is_read),
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
    coin_type ENUM('bound','unbound','mixed') DEFAULT 'unbound' COMMENT '金币类型',
    bound_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '绑定金币数量',
    unbound_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '非绑定金币数量',
    description VARCHAR(255),
    type ENUM('income','expense'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_user_type (user_id, type),
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
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_game_type (game_type),
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
    coin_type ENUM('unbound') DEFAULT 'unbound' COMMENT '充值获得的金币类型',
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

INSERT INTO prizes (name, icon, value, rarity, probability, original_probability, quantity) VALUES
('金币+10', '💰', 10.00, 'common', 30.00, 30.0000, NULL),
('金币+50', '💰', 50.00, 'common', 20.00, 20.0000, NULL),
('金币+100', '💎', 100.00, 'rare', 15.00, 15.0000, NULL),
('金币+500', '💎', 500.00, 'epic', 5.00, 5.0000, NULL),
('金币+1000', '👑', 1000.00, 'legendary', 1.00, 1.0000, 50),
('神秘礼盒', '🎁', 200.00, 'rare', 10.00, 10.0000, NULL);

-- 为所有Lucky页面启用示例奖品
INSERT INTO prize_lucky_pages (prize_id, lucky_page, enabled, page_probability)
SELECT p.id, lp.page_name, 1, NULL
FROM prizes p
CROSS JOIN (
    SELECT 'lucky1' AS page_name UNION ALL
    SELECT 'lucky2' UNION ALL SELECT 'lucky3' UNION ALL
    SELECT 'lucky4' UNION ALL SELECT 'lucky5' UNION ALL
    SELECT 'lucky6' UNION ALL SELECT 'lucky7' UNION ALL
    SELECT 'lucky8' UNION ALL SELECT 'lucky9' UNION ALL
    SELECT 'lucky10' UNION ALL SELECT 'lucky11'
) lp;

-- 插入默认超级管理员（用户名: admin, 密码: password, 身份码: admin）
-- 注意：此账户在创建新超级管理员后会自动禁用
INSERT INTO users (username, password, nickname, user_type, secret_key, balance, bound_coins, unbound_coins, status, created_at) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '默认超级管理员', 'super_admin', 'admin', 9999999.00, 0.00, 9999999.00, 'active', NOW());

-- ========================================
-- 金币变更日志表
-- ========================================

-- 30. 金币变更日志表
CREATE TABLE IF NOT EXISTS coin_change_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    change_type ENUM('recharge','draw','decompose','shop_purchase','withdrawal','checkin','refund','admin_adjust') NOT NULL COMMENT '变更类型',
    coin_type ENUM('bound','unbound','mixed') NOT NULL COMMENT '金币类型',
    bound_change DECIMAL(10,2) DEFAULT 0.00 COMMENT '绑定金币变化量',
    unbound_change DECIMAL(10,2) DEFAULT 0.00 COMMENT '非绑定金币变化量',
    bound_balance_before DECIMAL(10,2) DEFAULT 0.00 COMMENT '变更前绑定金币余额',
    unbound_balance_before DECIMAL(10,2) DEFAULT 0.00 COMMENT '变更前非绑定金币余额',
    bound_balance_after DECIMAL(10,2) DEFAULT 0.00 COMMENT '变更后绑定金币余额',
    unbound_balance_after DECIMAL(10,2) DEFAULT 0.00 COMMENT '变更后非绑定金币余额',
    related_id INT COMMENT '关联记录ID（如交易ID、抽奖ID等）',
    description VARCHAR(255) COMMENT '变更描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_change_type (change_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='金币变更日志表';

-- ========================================
-- 用户总余额视图
-- ========================================

CREATE OR REPLACE VIEW user_total_balance AS
SELECT 
    id,
    username,
    nickname,
    bound_coins,
    unbound_coins,
    (bound_coins + unbound_coins) AS total_balance,
    balance AS old_balance
FROM users;

SELECT '数据库初始化完成！默认超级管理员已创建（用户名: admin, 密码: password, 身份码: admin）' AS message;


-- ========================================
-- 提现系统相关表
-- ========================================

-- 23. 跑刀提现申请表（待处理的提现请求）
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT '提现金币数量',
    coin_type ENUM('unbound') DEFAULT 'unbound' COMMENT '提现金币类型',
    buff_coins DECIMAL(10,2) NOT NULL COMMENT '转换后的哈夫币数量（汇率：60金币=10000000哈夫币）',
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
    coin_type ENUM('unbound') DEFAULT 'unbound' COMMENT '提现金币类型',
    buff_coins DECIMAL(10,2) NOT NULL COMMENT '转换后的哈夫币数量（汇率：60金币=10000000哈夫币）',
    status ENUM('completed', 'rejected') NOT NULL COMMENT '最终状态',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '处理时间',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跑刀提现配置表（汇率：60金币=10000000哈夫币）';

-- 插入跑刀提现配置
INSERT INTO withdrawal_config (config_key, config_value, description) VALUES
('exchange_rate', '166666.67', '兑换汇率（60金币=10000000哈夫币）'),
('min_amount', '60', '最小提现金币数'),
('max_amount', '600', '最大提现金币数'),
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
    bound_coins_used DECIMAL(10,2) DEFAULT 0.00 COMMENT '使用的绑定金币',
    unbound_coins_used DECIMAL(10,2) DEFAULT 0.00 COMMENT '使用的非绑定金币',
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
    INDEX idx_created_at (created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_status_created (status, created_at)
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


-- ============================================
-- Lucky页面合并功能相关表
-- ============================================

-- 创建合并组表
CREATE TABLE IF NOT EXISTS lucky_merge_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL COMMENT '合并组名称',
    group_icon VARCHAR(50) DEFAULT '🎰' COMMENT '合并组图标',
    group_thumb VARCHAR(255) DEFAULT NULL COMMENT '合并组缩略图路径',
    description TEXT COMMENT '合并组描述',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lucky页面合并组';

-- 创建页面元数据表
CREATE TABLE IF NOT EXISTS lucky_pages_meta (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_name VARCHAR(100) NOT NULL UNIQUE COMMENT '页面文件名',
    display_name VARCHAR(100) NOT NULL COMMENT '显示名称',
    description TEXT COMMENT '页面描述',
    thumb_image VARCHAR(255) DEFAULT NULL COMMENT '缩略图路径',
    merge_group_id INT DEFAULT NULL COMMENT '所属合并组ID',
    merge_order INT DEFAULT 0 COMMENT '在合并组中的排序',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merge_group_id) REFERENCES lucky_merge_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lucky页面元数据';

-- 创建索引
CREATE INDEX idx_merge_group ON lucky_pages_meta(merge_group_id);
CREATE INDEX idx_merge_order ON lucky_pages_meta(merge_order);

-- 初始化现有页面的元数据
INSERT IGNORE INTO lucky_pages_meta (file_name, display_name, description, merge_group_id, merge_order) VALUES
('lucky1.html', '零号大坝(普通)', '零号大坝危机四伏', 1, 1),
('lucky2.html', '零号大坝(机密)', '零号大坝危机四伏', 1, 2),
('lucky3.html', '零号大坝(永夜)', '零号大坝危机四伏', 1, 3),
('lucky4.html', '长弓溪谷(普通)', '长弓溪谷烽烟四起', 2, 1),
('lucky5.html', '长弓溪谷(机密)', '有一架飞机在长弓溪谷区域坠落！', 2, 2),
('lucky6.html', '巴克什(机密)', '博士，他们来了', 3, 1),
('lucky7.html', '巴克什(绝密)', '博士:"带我走~"', 3, 2),
('lucky8.html', '航天基地(机密)', '威龙，你看过烟花吗？', 4, 1),
('lucky9.html', '航天基地(绝密)', '我被敌机锁定了！', 4, 2),
('lucky10.html', '潮汐监狱(适应)', '潮汐监狱等待自由', 5, 1),
('lucky11.html', '潮汐监狱(绝密)', '潮汐监狱等待自由', 5, 2);


-- ========================================
-- 便捷视图：按页面查看奖品
-- ========================================
CREATE OR REPLACE VIEW v_prizes_by_page AS
SELECT 
    plp.lucky_page,
    p.id AS prize_id,
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
    (p.active = 1 AND plp.enabled = 1) AS is_available
FROM prizes p
JOIN prize_lucky_pages plp ON p.id = plp.prize_id
ORDER BY plp.lucky_page, p.probability DESC;

SELECT 'Lucky页面奖品表创建完成！' AS message;

