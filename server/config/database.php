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
 * 数据库表结构：
 * 
 * 1. users - 用户表
 *    - id (INT, 主键, 自增)
 *    - username (VARCHAR(50), 唯一, 用户名)
 *    - password (VARCHAR(255), 密码哈希)
 *    - nickname (VARCHAR(100), 昵称)
 *    - email (VARCHAR(100), 邮箱)
 *    - balance (DECIMAL(10,2), 默认0.00, 账户余额)
 *    - avatar (TEXT, 头像数据，支持base64编码)
 *    - is_online (TINYINT(1), 默认0, 在线状态)
 *    - last_login (TIMESTAMP, 最后登录时间)
 *    - last_activity (TIMESTAMP, 最后活动时间)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 创建时间)
 * 
 * 2. prizes - 奖品表
 *    - id (INT, 主键, 自增)
 *    - name (VARCHAR(100), 奖品名称)
 *    - icon (VARCHAR(20), 奖品图标)
 *    - image_url (VARCHAR(255), 奖品图片URL)
 *    - value (DECIMAL(10,2), 奖品价值)
 *    - rarity (ENUM, 稀有度: common,rare,epic,legendary)
 *    - probability (DECIMAL(5,4), 概率)
 *    - is_active (TINYINT(1), 默认1, 是否启用)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 * 
 * 3. lucky1_prizes, lucky2_prizes, lucky3_prizes, lucky4_prizes - 各页面奖品表
 *    (结构同prizes表，用于独立管理各个抽奖页面的奖品)
 * 
 * 4. user_items - 用户物品表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id)
 *    - prize_id (INT, 外键关联prizes.id)
 *    - name (VARCHAR(100), 物品名称)
 *    - icon (VARCHAR(20), 物品图标)
 *    - image_url (VARCHAR(255), 物品图片URL)
 *    - value (DECIMAL(10,2), 物品价值)
 *    - rarity (ENUM, 稀有度: common,rare,epic,legendary)
 *    - obtained_at (TIMESTAMP, 默认CURRENT_TIMESTAMP, 获得时间)
 *    - decomposed (TINYINT(1), 默认0, 是否已分解)
 *    - decomposed_at (TIMESTAMP, 分解时间)
 * 
 * 5. draws - 抽奖记录表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id)
 *    - prize_id (INT, 外键关联prizes.id)
 *    - prize_name (VARCHAR(100), 奖品名称)
 *    - prize_value (DECIMAL(10,2), 奖品价值)
 *    - draw_type (VARCHAR(50), 抽奖类型)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 * 
 * 6. user_checkin - 用户签到表
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id)
 *    - checkin_date (DATE, 签到日期)
 *    - consecutive_days (INT, 默认1, 连续签到天数)
 *    - reward_amount (DECIMAL(10,2), 默认10.00, 奖励金额)
 *    - reward_type (ENUM, 默认'coins', 奖励类型: coins,item)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 *    - UNIQUE KEY: unique_user_date (user_id, checkin_date)
 * 
 * 7. super_admins - 超级管理员表
 *    - id (INT, 主键, 自增)
 *    - username (VARCHAR(50), 唯一, 管理员用户名)
 *    - password_hash (VARCHAR(255), 密码哈希)
 *    - secret_key (VARCHAR(100), 安全密钥)
 *    - ip_whitelist (TEXT, IP白名单)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 *    - last_login (TIMESTAMP, 最后登录时间)
 *    - status (ENUM, 默认'active', 状态: active,inactive)
 * 
 * 8. admin_security_log - 管理员安全日志表
 *    - id (INT, 主键, 自增)
 *    - admin_id (INT, 管理员ID)
 *    - username (VARCHAR(50), 用户名)
 *    - ip_address (VARCHAR(45), IP地址)
 *    - action (VARCHAR(50), 操作类型)
 *    - status (ENUM, 状态: success,failed)
 *    - reason (VARCHAR(100), 失败原因)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 *    - INDEX: idx_ip_time (ip_address, created_at)
 *    - INDEX: idx_username_time (username, created_at)
 * 
 * 9. transactions - 交易记录表 (如果存在)
 *    - id (INT, 主键, 自增)
 *    - user_id (INT, 外键关联users.id)
 *    - amount (DECIMAL(10,2), 交易金额)
 *    - description (VARCHAR(255), 交易描述)
 *    - type (ENUM, 交易类型: income,expense)
 *    - created_at (TIMESTAMP, 默认CURRENT_TIMESTAMP)
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'lucky_draw');
define('DB_USER', 'web_user');
define('DB_PASS', 'web_password');
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
