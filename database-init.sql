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
