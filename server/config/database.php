<?php
/**
 * 幸运降临 - 数据库配置文件
 * 
 * 当前数据库配置信息：
 * - 数据库名称: lucky_draw  
 * - 用户名: web_user
 * - 密码: web_password
 * - 主机: localhost
 * - 字符集: utf8mb4
 * 
 * 数据库表结构（共24个表）：
 * 
 * 1. users - 统一用户表（包含所有用户类型）
 *    - id (INT, 主键, 自增)
 *    - username (VARCHAR(50), 唯一, 用户名)
 *    - password (VARCHAR(255), 密码哈希)
 *    - nickname (VARCHAR(50), 昵称)
 *    - avatar (TEXT, 头像数据，支持base64编码)
 *    - balance (DECIMAL(10,2), 默认1000.00, 账户余额)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 *    - last_login (TIMESTAMP, 最后登录时间)
 *    - is_online (TINYINT(1), 默认0, 在线状态)
 *    - last_activity (TIMESTAMP, 最后活动时间, 索引)
 *    - email (VARCHAR(100), 邮箱)
 *    - user_type (ENUM('user','service','super_admin'), 默认'user', 用户类型)
 *    - secret_key (VARCHAR(100), 超级管理员身份码)
 *    - ip_whitelist (TEXT, IP白名单)
 *    - status (ENUM('active','inactive'), 默认'active', 用户状态)
 * 
 * 2. prizes - 奖品表
 *    - id (INT, 主键, 自增)
 *    - name (VARCHAR(100), 奖品名称)
 *    - icon (VARCHAR(20), 奖品图标)
 *    - image_url (VARCHAR(255), 奖品图片URL)
 *    - value (DECIMAL(10,2), 奖品价值)
 *    - rarity (ENUM('common','rare','epic','legendary'), 默认'common', 稀有度)
 *    - game_type (ENUM('lucky_drop','prize_draw','wheel'), 游戏类型)
 *    - probability (DECIMAL(5,2), 概率)
 *    - original_probability (DECIMAL(10,4), 原始概率)
 *    - active (TINYINT(1), 默认1, 是否启用)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - quantity (INT, 数量)
 * 
 * 3. user_items - 用户物品表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id, 索引)
 *    - prize_id (INT, 外键关联prizes.id, 索引)
 *    - name (VARCHAR(100), 物品名称)
 *    - icon (VARCHAR(20), 物品图标)
 *    - image_url (VARCHAR(255), 物品图片URL)
 *    - value (DECIMAL(10,2), 物品价值)
 *    - rarity (ENUM('common','rare','epic','legendary'), 默认'common', 稀有度)
 *    - obtained_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 获得时间)
 *    - decomposed (TINYINT(1), 默认0, 是否已分解)
 *    - decomposed_at (TIMESTAMP, 分解时间)
 * 
 * 4. draws - 抽奖记录表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id, 索引)
 *    - prize_id (INT, 奖品ID)
 *    - prize_name (VARCHAR(100), 奖品名称)
 *    - prize_value (DECIMAL(10,2), 奖品价值)
 *    - draw_type (VARCHAR(50), 默认'single', 抽奖类型)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 5. user_checkin - 用户签到表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id, 索引)
 *    - checkin_date (DATE, 签到日期)
 *    - consecutive_days (INT, 默认1, 连续签到天数)
 *    - reward_amount (DECIMAL(10,2), 默认10.00, 奖励金额)
 *    - reward_type (ENUM('coins','item'), 默认'coins', 奖励类型)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 6. security_logs - 统一安全日志表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 用户ID，关联users.id)
 *    - username (VARCHAR(50), 用户名)
 *    - user_type (ENUM('user','service','admin','super_admin'), 用户类型)
 *    - ip_address (VARCHAR(45), IP地址)
 *    - action (VARCHAR(100), 操作类型)
 *    - details (TEXT, 操作详情)
 *    - status (ENUM('success','failed'), 操作状态)
 *    - reason (VARCHAR(255), 失败原因)
 *    - user_agent (TEXT, 用户代理)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - INDEX: idx_user_id (user_id), idx_username (username), idx_user_type (user_type)
 *    - INDEX: idx_ip_address (ip_address), idx_action (action), idx_created_at (created_at)
 * 
 * 7. customer_service_config - 客服配置表
 *    - id (INT, 主键, 自增)
 *    - service_type (ENUM('online','qq','wechat'), 客服类型)
 *    - title (VARCHAR(100), 客服标题)
 *    - content (TEXT, 客服内容描述)
 *    - contact_info (VARCHAR(255), 联系方式)
 *    - qr_code_url (VARCHAR(500), 二维码图片URL)
 *    - is_enabled (TINYINT(1), 默认1, 是否启用)
 *    - sort_order (INT, 默认0, 排序)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 * 
 * 8. chat_sessions - 客服聊天会话表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 用户ID，关联users.id)
 *    - service_user_id (INT, 客服用户ID，关联users.id)
 *    - session_id (VARCHAR(100), 唯一, 会话ID)
 *    - status (ENUM('waiting','active','closed'), 默认'waiting', 会话状态)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 *    - closed_at (TIMESTAMP, 结束时间)
 *    - INDEX: idx_user_id (user_id), idx_service_user_id (service_user_id)
 * 
 * 9. chat_messages - 客服聊天消息表
 *    - id (INT, 主键, 自增)
 *    - session_id (VARCHAR(100), 会话ID，关联chat_sessions.session_id)
 *    - sender_id (INT, 发送者ID，关联users.id)
 *    - sender_type (ENUM('user','service'), 发送者类型)
 *    - message (TEXT, 消息内容)
 *    - message_type (ENUM('text','image','file'), 默认'text', 消息类型)
 *    - is_read (TINYINT(1), 默认0, 是否已读)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *    - INDEX: idx_session_id (session_id), idx_sender_id (sender_id)
 * 
 * 10. admin_security_log - 管理员安全日志表
 *     - id (INT, 主键, 自增)
 *     - admin_id (INT, 管理员ID，关联users.id)
 *     - username (VARCHAR(50), 用户名，索引)
 *     - ip_address (VARCHAR(45), IP地址，索引)
 *     - action (VARCHAR(50), 操作类型)
 *     - status (ENUM('success','failed'), 操作状态)
 *     - reason (VARCHAR(100), 失败原因)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *     - INDEX: idx_username (username), idx_ip_address (ip_address)
 * 
 * 11. service_user_assignments - 客服用户分配表
 *     - id (INT, 主键, 自增)
 *     - service_user_id (INT, 客服用户ID，关联users.id，索引)
 *     - regular_user_id (INT, 普通用户ID，关联users.id，唯一索引)
 *     - assigned_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 分配时间)
 *     - assigned_by (INT, 分配者ID，关联users.id，索引)
 *     - status (ENUM('active','inactive'), 默认'active', 分配状态)
 *     - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 *     - INDEX: idx_service_user_id (service_user_id), idx_assigned_by (assigned_by)
 *     - UNIQUE: unique_regular_user (regular_user_id)
 * 
 * 12. transactions - 交易记录表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - amount (DECIMAL(10,2), 交易金额)
 *     - description (VARCHAR(255), 交易描述)
 *     - type (ENUM('income','expense'), 交易类型)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 13. checkin_records - 签到记录表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - date (DATE, 签到日期)
 *     - reward (DECIMAL(10,2), 奖励金额)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 14. draw_history - 抽奖历史表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - draw_type (VARCHAR(50), 抽奖类型)
 *     - cost (DECIMAL(10,2), 消费金额)
 *     - results (TEXT, 抽奖结果JSON)
 *     - total_value (DECIMAL(10,2), 总价值)
 *     - draw_time (DATETIME, 默认CURRENT_TIMESTAMP, 抽奖时间)
 * 
 * 15. lottery_records - 彩票记录表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - game_type (ENUM('lucky_drop','prize_draw','wheel'), 游戏类型)
 *     - cost (DECIMAL(10,2), 消费金额)
 *     - reward (DECIMAL(10,2), 奖励金额)
 *     - result (TEXT, 抽奖结果)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 16. prize_draw_log - 奖品抽取日志表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - prize_id (INT, 奖品ID)
 *     - prize_name (VARCHAR(100), 奖品名称)
 *     - prize_table (VARCHAR(50), 奖品表名, 索引)
 *     - rarity (ENUM('common','rare','epic','legendary'), 稀有度, 索引)
 *     - original_quantity (INT, 原始数量)
 *     - remaining_quantity (INT, 剩余数量)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间, 索引)
 * 
 * 17. recharge_history - 充值历史表
 *     - id (INT, 主键, 自增)
 *     - user_id (INT, 外键关联users.id, 索引)
 *     - amount (DECIMAL(10,2), 充值金额)
 *     - coins_gained (INT, 获得金币)
 *     - payment_method (VARCHAR(50), 支付方式)
 *     - transaction_id (VARCHAR(255), 交易ID)
 *     - status (VARCHAR(50), 默认'pending', 状态)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *     - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 * 
 * 18. recharge_options - 充值选项表
 *     - id (INT, 主键, 自增)
 *     - amount (DECIMAL(10,2), 充值金额)
 *     - coins_reward (INT, 默认0, 金币奖励)
 *     - bonus_coins (INT, 默认0, 奖励金币)
 * 
 * 19. settings - 设置表
 *     - key (VARCHAR(255), 主键, 设置键)
 *     - value (TEXT, 设置值)
 * 
 * 20. system_settings - 系统设置表
 *     - setting_key (VARCHAR(50), 主键, 设置键)
 *     - setting_value (TEXT, 设置值)
 *     - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 * 
 * 21. super_admins - 超级管理员表（已废弃，数据已迁移到users表）
 *     - 保留表结构用于向后兼容
 * 
 * 22. sql_execution_log - SQL执行日志表（已废弃，数据已迁移到security_logs表）
 *     - 保留表结构用于向后兼容
 * 
 * 23. draw_prices - 抽奖价格表
 *     - id (INT, 主键, 自增)
 *     - page_name (VARCHAR(100), 页面名称, 如'lucky1.html')
 *     - price_type (ENUM('single','triple','quintuple'), 抽奖类型)
 *     - price_value (DECIMAL(10,2), 价格值)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *     - updated_at (TIMESTAMP, 默认CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 更新时间)
 *     - UNIQUE: unique_page_price (page_name, price_type)
 * 
 * 24. price_history - 价格历史表
 *     - id (INT, 主键, 自增)
 *     - page_name (VARCHAR(100), 页面名称)
 *     - price_type (ENUM('single','triple','quintuple'), 抽奖类型)
 *     - old_price (DECIMAL(10,2), 旧价格)
 *     - new_price (DECIMAL(10,2), 新价格)
 *     - changed_by (VARCHAR(100), 操作者)
 *     - change_reason (VARCHAR(255), 默认'manual', 变更原因)
 *     - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 *     - INDEX: idx_page_name (page_name), idx_created_at (created_at)
 * 
 * 总表数：24张表
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'lucky_draw');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 创建数据库连接
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("数据库连接失败: " . $exception->getMessage());
            throw new Exception("数据库连接失败");
        }
        return $this->conn;
    }
}

// CORS设置
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 处理OPTIONS请求
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}
?>
