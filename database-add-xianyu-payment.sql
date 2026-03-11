-- 添加咸鱼充值方式和支付方式禁用功能
-- 执行时间：2026-03-11

USE lucky_draw;

-- 1. 创建支付方式配置表（如果不存在）
CREATE TABLE IF NOT EXISTS payment_method_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_key VARCHAR(50) NOT NULL UNIQUE COMMENT '支付方式键名（alipay, wechat, xianyu）',
    method_name VARCHAR(100) NOT NULL COMMENT '支付方式显示名称',
    icon VARCHAR(50) DEFAULT '💰' COMMENT '图标',
    qr_code_url VARCHAR(500) COMMENT '二维码图片URL（咸鱼专用）',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_method_key (method_key),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付方式配置表';

-- 2. 插入默认支付方式配置
INSERT INTO payment_method_config (method_key, method_name, icon, is_enabled, sort_order) VALUES
('alipay', '支付宝', '💰', 1, 1),
('wechat', '微信支付', '💚', 1, 2),
('xianyu', '咸鱼账户充值', '🐟', 1, 3)
ON DUPLICATE KEY UPDATE method_key=method_key;

-- 3. 修复咸鱼充值二维码路径
-- 将 ../images/payment/ 改为 images/payment/
UPDATE payment_method_config 
SET qr_code_url = REPLACE(qr_code_url, '../images/', 'images/')
WHERE method_key = 'xianyu' AND qr_code_url LIKE '../images/%';

-- 4. 如果二维码URL为空，设置默认值
UPDATE payment_method_config 
SET qr_code_url = 'images/payment/xianyu_qrcode_1773242487.jpg'
WHERE method_key = 'xianyu' AND (qr_code_url IS NULL OR qr_code_url = '');

-- 5. 查看最终结果
SELECT method_key, method_name, qr_code_url, is_enabled 
FROM payment_method_config 
ORDER BY sort_order;

SELECT '咸鱼充值方式已添加并修复完成！' AS message;
