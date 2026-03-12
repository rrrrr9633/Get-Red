<?php
/**
 * Redis 连接管理类
 * 使用单例模式确保全局只有一个 Redis 连接
 */

class RedisConnection {
    private static $instance = null;
    private $redis;
    
    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {
        try {
            $this->redis = new Redis();
            
            // 连接 Redis 服务器
            $host = '127.0.0.1';
            $port = 6379;
            $timeout = 2.5; // 连接超时时间（秒）
            
            $connected = $this->redis->connect($host, $port, $timeout);
            
            if (!$connected) {
                throw new Exception("无法连接到 Redis 服务器");
            }
            
            // 如果 Redis 设置了密码，取消下面的注释并设置密码
            // $password = 'your_redis_password';
            // $this->redis->auth($password);
            
            // 选择数据库（0-15）
            $this->redis->select(0);
            
            // 设置序列化方式
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            
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
