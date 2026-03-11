-- 添加传说级兑换类型到 coin_change_log 表
-- 执行时间：2026-03-11

USE lucky_draw;

-- 修改 coin_change_log 表的 change_type 字段，添加 'legendary_exchange' 选项
ALTER TABLE coin_change_log 
MODIFY COLUMN change_type ENUM(
    'recharge',
    'draw',
    'decompose',
    'shop_purchase',
    'withdrawal',
    'checkin',
    'refund',
    'admin_adjust',
    'legendary_exchange'
) NOT NULL COMMENT '变更类型';

-- 同时修改 coin_type 字段，添加 'none' 选项（用于不涉及金币变化的记录）
ALTER TABLE coin_change_log 
MODIFY COLUMN coin_type ENUM(
    'bound',
    'unbound',
    'mixed',
    'none'
) NOT NULL COMMENT '金币类型';

SELECT '传说级兑换类型已添加到 coin_change_log 表！' AS message;
