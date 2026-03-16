<?php
/**
 * Redis 连接管理类
 * 使用单例模式确保全局只有一个 Redis 连接
 */

// 加载环境变量
function loadEnvFile($path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释和空行
            if (empty($line) || strpos($line, '#') === 0) continue;
            // 跳过包含注释的行（但保留值中的#）
            if (strpos($line, '=') === false) continue;
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // 如果值后面有注释，去掉注释部分
            if (strpos($value, ' #') !== false) {
                $value = trim(substr($value, 0, strpos($value, ' #')));
            }
            
            putenv($name . '=' . $value);
        }
        return true;
    }
    return false;
}

// 尝试从多个位置加载环境变量
$envPaths = [
    '/etc/lucky-box/.env', // 云端环境变量位置
    __DIR__ . '/../../.env.sms',
    __DIR__ . '/../../.env',
    __DIR__ . '/../.env.sms',
    __DIR__ . '/../.env',
    __DIR__ . '/.env.sms',
    __DIR__ . '/.env'
];

$envLoaded = false;
foreach ($envPaths as $path) {
    if (loadEnvFile($path)) {
        error_log('环境变量已从 ' . $path . ' 加载');
        $envLoaded = true;
        break;
    }
}

if (!$envLoaded) {
    error_log('未找到环境变量文件，将使用系统环境变量');
}

class RedisConnection {
    private static $instance = null;
    private $redis;
    
    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {
        try {
            $this->redis = new Redis();
            
            // 从环境变量读取配置
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('REDIS_PORT') ?: 6379;
            $password = getenv('REDIS_PASSWORD');
            $db = getenv('REDIS_DB') ?: 0;
            $timeout = 2.5; // 连接超时时间（秒）
            
            $connected = $this->redis->connect($host, $port, $timeout);
            
            if (!$connected) {
                throw new Exception("无法连接到 Redis 服务器: $host:$port");
            }
            
            // 如果 Redis 设置了密码，进行认证
            if ($password) {
                $authResult = $this->redis->auth($password);
                if (!$authResult) {
                    throw new Exception("Redis 认证失败");
                }
            }
            
            // 选择数据库（0-15）
            $this->redis->select($db);
            
            // 设置序列化方式
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            
            error_log("Redis 连接成功: $host:$port, DB: $db");
            
        } catch (Exception $e) {
            error_log("Redis 连接失败: " . $e->getMessage());
            throw new Exception("Redis 服务不可用");
        }
    }
    
    /**
     * 获取 Redis 实例（单例模式）
     * 
     * @return Redis Redis 连接实例
     * @throws Exception 如果连接失败
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->redis;
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
