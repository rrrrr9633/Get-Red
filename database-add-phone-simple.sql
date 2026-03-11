-- 为users表添加手机号字段（简化版）
USE lucky_draw;

-- 1. 添加手机号字段到users表
ALTER TABLE users 
ADD COLUMN phone_number VARCHAR(20) NULL AFTER email,
ADD UNIQUE INDEX idx_phone_number (phone_number);

-- 2. 添加注释说明
ALTER TABLE users 
MODIFY COLUMN phone_number VARCHAR(20) NULL COMMENT '手机号，用于短信验证';

-- 输出完成信息
SELECT '数据库表结构更新完成' AS message;