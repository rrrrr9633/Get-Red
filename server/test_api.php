<?php
// 测试API端点

// 模拟用户ID为2的用户获取物品
echo "=== 测试获取用户物品API ===\n";

$url = 'http://localhost:8001/server/api/items.php?user_id=2';
$response = file_get_contents($url);

if ($response === false) {
    echo "❌ API调用失败\n";
} else {
    echo "✅ API响应: $response\n";
}

echo "\n=== 测试抽奖API ===\n";

// 准备抽奖请求数据
$data = json_encode([
    'action' => 'draw',
    'game_type' => 'lucky_drop',
    'count' => 1,
    'user_id' => 2
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $data
    ]
]);

$url = 'http://localhost:8001/server/api/prizes.php';
$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ 抽奖API调用失败\n";
} else {
    echo "✅ 抽奖API响应: $response\n";
}
?>
