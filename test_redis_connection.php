<?php
/**
 * Redis连接测试脚本
 * 用于测试recharge-card.php使用的Redis连接是否正常
 */

echo "=== Redis连接测试 (recharge-card.php 使用的配置) ===\n";

// 加载Redis连接类
require_once __DIR__ . '/server/config/redis.php';

try {
    // 尝试获取Redis实例
    $redis = RedisConnection::getInstance();
    echo "✓ RedisConnection 实例创建成功\n";
    
    // 测试基本操作
    $testKey = 'test:recharge:card';
    $testValue = 'Redis connection test ' . time();
    
    // 设置值
    $setResult = $redis->set($testKey, $testValue);
    echo "✓ 设置测试值: " . ($setResult ? '成功' : '失败') . "\n";
    
    // 获取值
    $getValue = $redis->get($testKey);
    echo "✓ 获取测试值: " . ($getValue ? '成功' : '失败') . "\n";
    if ($getValue) {
        echo "✓ 测试值内容: $getValue\n";
    }
    
    // 删除值
    $deleteResult = $redis->del($testKey);
    echo "✓ 删除测试值: " . ($deleteResult > 0 ? '成功' : '失败') . "\n";
    
    // 测试哈希操作
    $hashKey = 'test:hash';
    $hashData = [
        'name' => '测试卡密',
        'amount' => 100,
        'coins' => 1000
    ];
    
    foreach ($hashData as $field => $value) {
        $redis->hSet($hashKey, $field, $value);
    }
    
    $hashResult = $redis->hGetAll($hashKey);
    echo "✓ 哈希操作测试: " . (!empty($hashResult) ? '成功' : '失败') . "\n";
    if (!empty($hashResult)) {
        echo "✓ 哈希数据: " . print_r($hashResult, true) . "\n";
    }
    
    $redis->del($hashKey);
    
    echo "\n=== 测试完成 ===\n";
    echo "✓ Redis连接正常，可以正常生成和管理卡密\n";
    
} catch (Exception $e) {
    echo "✗ Redis连接失败: " . $e->getMessage() . "\n";
    echo "\n=== 测试失败 ===\n";
}
?>