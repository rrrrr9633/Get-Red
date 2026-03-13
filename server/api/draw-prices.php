<?php
/**
 * Draw Prices API - 抽奖价格配置接口
 * 
 * 功能：获取指定Lucky实例的抽奖价格配置
 * 需求: 4.1, 4.2, 4.3, 4.5, 10.2
 * 
 * 支持的查询参数：
 * - lucky_id: Lucky实例ID（必需）
 * 
 * 返回数据：
 * - single: 单抽价格
 * - triple: 三连抽价格
 * - quintuple: 五连抽价格
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

/**
 * 获取指定Lucky实例的抽奖价格配置
 * 
 * 验证需求:
 * - 4.1: 接受lucky_id查询参数
 * - 4.2: 查询该实例的价格配置
 * - 4.3: 支持单抽、三连抽、五连抽三种价格类型
 * - 4.5: 验证价格值为正数
 * - 10.2: 只返回指定Lucky实例的价格配置（数据隔离）
 * 
 * @param int $luckyId Lucky实例ID
 * @return array 包含成功状态和价格配置的数组
 */
function getDrawPricesByLuckyId($luckyId) {
    global $pdo;
    
    try {
        // 验证lucky_id为正整数（需求 4.1）
        if (!is_numeric($luckyId) || $luckyId <= 0) {
            return [
                'success' => false,
                'message' => 'Lucky实例ID必须为正整数'
            ];
        }
        
        // 使用预处理语句防止SQL注入，查询指定lucky_id的价格配置（需求 4.2, 10.2）
        $stmt = $pdo->prepare("
            SELECT price_type, price_value, button_name
            FROM draw_prices 
            WHERE lucky_id = ?
            ORDER BY 
                CASE price_type
                    WHEN 'single' THEN 1
                    WHEN 'triple' THEN 2
                    WHEN 'quintuple' THEN 3
                END
        ");
        $stmt->execute([$luckyId]);
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 构建价格配置对象（需求 4.3）
        $priceConfig = [
            'single' => null,
            'triple' => null,
            'quintuple' => null
        ];
        
        foreach ($prices as $price) {
            $priceType = $price['price_type'];
            $priceValue = floatval($price['price_value']);
            
            // 验证价格值为正数（需求 4.5）
            if ($priceValue <= 0) {
                return [
                    'success' => false,
                    'message' => "价格配置错误：{$priceType} 价格必须为正数"
                ];
            }
            
            $priceConfig[$priceType] = [
                'price' => $priceValue,
                'button_name' => $price['button_name']
            ];
        }
        
        return [
            'success' => true,
            'lucky_id' => (int)$luckyId,
            'prices' => $priceConfig,
            'count' => count($prices)
        ];
        
    } catch (Exception $e) {
        error_log("获取抽奖价格失败: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '获取抽奖价格失败: ' . $e->getMessage()
        ];
    }
}

// 处理GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $luckyId = $_GET['lucky_id'] ?? null;
    
    if ($luckyId === null) {
        echo json_encode([
            'success' => false,
            'message' => '缺少必需参数: lucky_id'
        ]);
        exit;
    }
    
    echo json_encode(getDrawPricesByLuckyId($luckyId));
} else {
    echo json_encode([
        'success' => false,
        'message' => '不支持的请求方法'
    ]);
}
?>
