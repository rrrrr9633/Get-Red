<?php
/**
 * Redis 缓存类
 * 用于缓存热点数据，减少数据库查询
 */

class RedisCache {
    private static $instance = null;
    private $redis;
    private $enabled = false;
    
    private function __construct() {
        try {
            // 检查 Redis 扩展是否安装
            if (!class_exists('Redis')) {
                error_log('Redis 扩展未安装，缓存功能已禁用');
                return;
            }
            
            $this->redis = new Redis();
            
            // 连接 Redis（默认配置）
            $connected = $this->redis->connect('127.0.0.1', 6379, 2); // 2秒超时
            
            if (!$connected) {
                error_log('Redis 连接失败，缓存功能已禁用');
                return;
            }
            
            // 设置序列化方式为 JSON
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            
            // 设置键前缀，避免冲突
            $this->redis->setOption(Redis::OPT_PREFIX, 'lucky_draw:');
            
            $this->enabled = true;
            
        } catch (Exception $e) {
            error_log('Redis 初始化失败: ' . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 检查 Redis 是否可用
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed|null 缓存值，不存在返回 null
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $value = $this->redis->get($key);
            return $value === false ? null : $value;
        } catch (Exception $e) {
            error_log('Redis GET 失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），默认 300 秒（5分钟）
     * @return bool 是否成功
     */
    public function set($key, $value, $ttl = 300) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log('Redis SET 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public function delete($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log('Redis DELETE 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查缓存是否存在
     * @param string $key 缓存键
     * @return bool 是否存在
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log('Redis EXISTS 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量删除缓存（支持通配符）
     * @param string $pattern 匹配模式，如 "prizes:*"
     * @return int 删除的键数量
     */
    public function deletePattern($pattern) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            if (empty($keys)) {
                return 0;
            }
            return $this->redis->del($keys);
        } catch (Exception $e) {
            error_log('Redis DELETE PATTERN 失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 增加计数器
     * @param string $key 缓存键
     * @param int $value 增加的值，默认 1
     * @return int|false 增加后的值，失败返回 false
     */
    public function increment($key, $value = 1) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($key, $value);
        } catch (Exception $e) {
            error_log('Redis INCREMENT 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 设置过期时间
     * @param string $key 缓存键
     * @param int $ttl 过期时间（秒）
     * @return bool 是否成功
     */
    public function expire($key, $ttl) {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log('Redis EXPIRE 失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取 Redis 原生对象（用于高级操作）
     * @return Redis|null
     */
    public function getRedis() {
        return $this->enabled ? $this->redis : null;
    }
}
?>
