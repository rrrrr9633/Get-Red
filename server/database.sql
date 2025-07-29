-- 创建数据库
CREATE DATABASE IF NOT EXISTS lucky_draw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lucky_draw;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(50) NOT NULL,
    avatar TEXT,
    balance DECIMAL(10,2) DEFAULT 1000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 交易记录表
CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 签到记录表
CREATE TABLE IF NOT EXISTS checkin_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    reward DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 抽奖记录表
CREATE TABLE IF NOT EXISTS lottery_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    game_type ENUM('lucky_drop', 'prize_draw', 'wheel') NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    reward DECIMAL(10,2) NOT NULL,
    result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 奖品配置表
CREATE TABLE IF NOT EXISTS prizes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(20) NOT NULL,
    image_url VARCHAR(255),
    value DECIMAL(10,2) NOT NULL,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    game_type ENUM('lucky_drop', 'prize_draw', 'wheel') NOT NULL,
    probability DECIMAL(5,2) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入默认奖品数据
INSERT INTO prizes (name, icon, image_url, value, rarity, game_type, probability) VALUES
-- 幸运掉落奖品
('iPhone 15 Pro', '📱', '/uploads/prizes/iphone15.jpg', 8000, 'legendary', 'lucky_drop', 0.1),
('MacBook Air', '💻', '/uploads/prizes/macbook.jpg', 7000, 'legendary', 'lucky_drop', 0.2),
('AirPods Pro', '🎧', '/uploads/prizes/airpods.jpg', 1500, 'epic', 'lucky_drop', 1.0),
('iPad', '📱', '/uploads/prizes/ipad.jpg', 3000, 'epic', 'lucky_drop', 0.5),
('Apple Watch', '⌚', '/uploads/prizes/watch.jpg', 2500, 'rare', 'lucky_drop', 2.0),
('蓝牙音响', '🔊', '/uploads/prizes/speaker.jpg', 500, 'rare', 'lucky_drop', 5.0),
('充电宝', '🔋', '/uploads/prizes/powerbank.jpg', 100, 'common', 'lucky_drop', 15.0),
('数据线', '🔌', '/uploads/prizes/cable.jpg', 50, 'common', 'lucky_drop', 25.0),
('手机壳', '📱', '/uploads/prizes/case.jpg', 30, 'common', 'lucky_drop', 45.2),
('手机支架', '📱', '/uploads/prizes/stand.jpg', 25, 'common', 'lucky_drop', 5.0);

-- 用户物品表
CREATE TABLE IF NOT EXISTS user_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    prize_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(20) NOT NULL,
    image_url VARCHAR(255),
    value DECIMAL(10,2) NOT NULL,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decomposed BOOLEAN DEFAULT FALSE,
    decomposed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prize_id) REFERENCES prizes(id) ON DELETE CASCADE
);

-- 注意：如果需要创建默认管理员用户，请手动执行以下SQL：
-- INSERT INTO users (username, password, nickname, avatar, balance) VALUES
-- ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiByeD0iNTAiIGZpbGw9IiNmZmQ3MDAiLz4KPHN2ZyB4PSIyNSIgeT0iMjAiIHdpZHRoPSI1MCIgaGVpZ2h0PSI2MCI+CjxjaXJjbGUgY3g9IjI1IiBjeT0iMjAiIHI9IjE1IiBmaWxsPSIjMTExIi8+CjxlbGxpcHNlIGN4PSIyNSIgY3k9IjUwIiByeD0iMjAiIHJ5PSIxNSIgZmlsbD0iIzExMSIvPgo8L3N2Zz4KPC9zdmc+', 10000.00);