-- 添加公告功能
-- 执行时间：2026-03-11

USE lucky_draw;

-- 1. 创建公告表
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL COMMENT '公告标题',
    content TEXT NOT NULL COMMENT '公告内容',
    created_by INT NOT NULL COMMENT '创建者用户ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    INDEX idx_created_at (created_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- 2. 创建用户公告阅读记录表
CREATE TABLE IF NOT EXISTS user_announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    announcement_id INT NOT NULL COMMENT '公告ID',
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '阅读时间',
    UNIQUE KEY uk_user_announcement (user_id, announcement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_announcement_id (announcement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户公告阅读记录表';

-- 3. 创建公告版本表（用于追踪最新公告）
CREATE TABLE IF NOT EXISTS announcement_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version INT NOT NULL DEFAULT 1 COMMENT '公告版本号',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告版本表';

-- 插入初始版本
INSERT INTO announcement_version (version) VALUES (1);

SELECT '公告功能表已创建！' AS message;
