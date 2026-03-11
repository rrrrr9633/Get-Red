<?php
/**
 * 充值API v2 - 支持商户模式和个人模式
 * 支持虎皮椒支付网关
 * 支持真正的支付监测功能
 */

require_once '../config/database.php';
require_once '../config/payment-gateway.php';
require_once '../config/security.php';

// 配置安全Session
configureSecureSession();

// 启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 初始化数据库
$database = new Database();
$db = $database->getConnection();

// 初始化支付网关
$paymentGateway = new PaymentGateway($db);

// 获取请求参数
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 处理不同的请求
switch ($action) {
    // ==================== 用户端API ====================
    
    case 'get_recharge_options':
        // 获取充值选项
        handleGetRechargeOptions($db, $_GET['mode'] ?? 'merchant');
        break;
    
    case 'create_order':
        // 创建支付订单
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleCreateOrder($db, $paymentGateway, $data);
        }
        break;
    
    case 'check_order_status':
        // 检查订单状态
        handleCheckOrderStatus($db, $_GET['order_no'] ?? '');
        break;
    
    case 'get_payment_status':
        // 轮询获取支付状态（用于前端实时监测）
        handleGetPaymentStatus($db, $_GET['order_no'] ?? '');
        break;
    
    // ==================== 回调处理 ====================
    
    case 'hupijiao_callback':
        // 虎皮椒支付回调
        handleHuPiJiaoCallback($db, $paymentGateway);
        break;
    
    // ==================== 管理端API ====================
    
    case 'get_payment_config':
        // 获取支付配置
        if ($method === 'GET') {
            handleGetPaymentConfig($db, $_GET['mode'] ?? 'merchant');
        }
        break;
    
    case 'save_payment_config':
        // 保存支付配置
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleSavePaymentConfig($db, $paymentGateway, $data);
        }
        break;
    
    case 'get_payment_orders':
        // 获取支付订单列表
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleGetPaymentOrders($db, $data);
        }
        break;
    
    case 'get_payment_stats':
        // 获取支付统计
        handleGetPaymentStats($db);
        break;
    
    case 'toggle_payment_mode_status':
        // 切换支付模式状态（启用/禁用整个模式）
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleTogglePaymentModeStatus($db, $data);
        }
        break;
    
    case 'get_payment_mode_status':
        // 获取支付模式状态
        handleGetPaymentModeStatus($db);
        break;
    
    case 'get_payment_methods':
        // 获取启用的支付方式列表
        handleGetPaymentMethods($db);
        break;
    
    case 'save_payment_method':
        // 保存支付方式配置（管理端）
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleSavePaymentMethod($db, $data);
        }
        break;
    
    case 'toggle_payment_method':
        // 切换支付方式启用状态（管理端）
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            handleTogglePaymentMethod($db, $data);
        }
        break;
    
    case 'upload_payment_qrcode':
        // 上传支付方式二维码图片（管理端）
        if ($method === 'POST') {
            handleUploadPaymentQrCode($db);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '未知的操作']);
        break;
}

// ==================== 处理函数 ====================

/**
 * 获取充值选项
 */
function handleGetRechargeOptions($db, $mode) {
    try {
        $query = "SELECT * FROM recharge_options ORDER BY amount ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $options = $stmt->fetchAll();
        
        // 获取充值比例
        $coinRatio = getCoinRatio($db);
        
        $optionList = [];
        foreach ($options as $option) {
            $totalCoins = ($option['amount'] * $coinRatio) + ($option['bonus_coins'] ?? 0);
            $optionList[] = [
                'id' => $option['id'],
                'amount' => $option['amount'],
                'bonus_coins' => $option['bonus_coins'] ?? 0,
                'total_coins' => $totalCoins,
                'coin_ratio' => $coinRatio
            ];
        }
        
        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'options' => $optionList,
            'coin_ratio' => $coinRatio
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 创建支付订单
 */
function handleCreateOrder($db, $paymentGateway, $data) {
    try {
        // 验证用户登录
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("请先登录");
        }
        
        $userId = $_SESSION['user_id'];
        $mode = $data['mode'] ?? 'merchant';
        $paymentMethod = $data['payment_method'] ?? 'alipay';
        $amount = floatval($data['amount'] ?? 0);
        
        if ($amount <= 0) {
            throw new Exception("金额无效");
        }
        
        // 获取充值选项
        $query = "SELECT * FROM recharge_options WHERE amount = :amount LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':amount' => $amount]);
        $option = $stmt->fetch();
        
        if (!$option) {
            throw new Exception("充值选项不存在");
        }
        
        // 根据模式创建订单
        if ($mode === 'personal') {
            // 个人模式 - 使用虎皮椒API
            $returnUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
                        dirname($_SERVER['REQUEST_URI']) . '/recharge-v2.php';
            
            $result = $paymentGateway->createHuPiJiaoOrder($userId, $amount, $paymentMethod, $returnUrl);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'order_no' => $result['order_no'],
                    'qr_code' => $result['qr_code'],
                    'pay_url' => $result['pay_url'],
                    'amount' => $amount,
                    'mode' => 'personal',
                    'payment_method' => $paymentMethod
                ]);
            } else {
                throw new Exception($result['error']);
            }
        } else {
            // 商户模式 - 使用现有的支付宝/微信API
            throw new Exception("商户模式暂未实现");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 检查订单状态
 */
function handleCheckOrderStatus($db, $orderNo) {
    try {
        if (empty($orderNo)) {
            throw new Exception("订单号不能为空");
        }
        
        $query = "SELECT * FROM payment_orders WHERE order_no = :order_no LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':order_no' => $orderNo]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("订单不存在");
        }
        
        echo json_encode([
            'success' => true,
            'order' => [
                'order_no' => $order['order_no'],
                'status' => $order['status'],
                'amount' => $order['amount'],
                'created_at' => $order['created_at'],
                'completed_at' => $order['completed_at']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取支付状态（用于前端轮询）
 */
function handleGetPaymentStatus($db, $orderNo) {
    try {
        if (empty($orderNo)) {
            throw new Exception("订单号不能为空");
        }
        
        $query = "SELECT id, status, amount, payment_method FROM payment_orders WHERE order_no = :order_no LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':order_no' => $orderNo]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("订单不存在");
        }
        
        $statusMap = [
            'pending' => '待支付',
            'completed' => '已完成',
            'failed' => '支付失败',
            'cancelled' => '已取消'
        ];
        
        echo json_encode([
            'success' => true,
            'status' => $order['status'],
            'status_text' => $statusMap[$order['status']] ?? $order['status'],
            'amount' => $order['amount'],
            'payment_method' => $order['payment_method'],
            'is_completed' => $order['status'] === 'completed'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 处理虎皮椒支付回调
 */
function handleHuPiJiaoCallback($db, $paymentGateway) {
    try {
        // 获取回调数据
        $callbackData = $_POST;
        
        // 记录回调日志
        $query = "INSERT INTO payment_callbacks (order_no, gateway, callback_type, raw_data, created_at)
                 VALUES (:order_no, :gateway, :callback_type, :raw_data, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':order_no' => $callbackData['order_no'] ?? '',
            ':gateway' => 'hupijiao',
            ':callback_type' => 'payment_notify',
            ':raw_data' => json_encode($callbackData)
        ]);
        
        // 获取支付配置
        $config = $paymentGateway->getPaymentConfig('personal');
        if (!$config || !$config['settings']) {
            throw new Exception("支付配置未找到");
        }
        
        $settings = $config['settings'];
        $apiKey = $settings['hupijiao_api_key'] ?? '';
        
        // 处理回调
        $result = $paymentGateway->handleHuPiJiaoCallback($callbackData, $apiKey);
        
        if ($result['success']) {
            // 返回成功响应
            echo "success";
        } else {
            // 返回失败响应
            echo "fail";
        }
    } catch (Exception $e) {
        error_log("处理虎皮椒回调异常: " . $e->getMessage());
        echo "fail";
    }
}

/**
 * 获取支付配置
 */
function handleGetPaymentConfig($db, $mode) {
    try {
        $query = "SELECT * FROM payment_config WHERE mode = :mode";
        $stmt = $db->prepare($query);
        $stmt->execute([':mode' => $mode]);
        $configs = $stmt->fetchAll();
        
        $configList = [];
        foreach ($configs as $config) {
            $configList[] = [
                'payment_method' => $config['payment_method'],
                'settings' => json_decode($config['settings'], true)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'configs' => $configList
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 保存支付配置
 */
function handleSavePaymentConfig($db, $paymentGateway, $data) {
    try {
        $mode = $data['mode'] ?? 'merchant';
        $paymentMethod = $data['payment_method'] ?? 'alipay';
        $settings = $data['settings'] ?? [];
        
        $paymentGateway->savePaymentConfig($mode, $paymentMethod, $settings);
        
        echo json_encode(['success' => true, 'message' => '配置保存成功']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取支付订单列表
 */
function handleGetPaymentOrders($db, $data) {
    try {
        $mode = $data['mode'] ?? '';
        $status = $data['status'] ?? '';
        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);
        
        $query = "SELECT * FROM payment_orders WHERE 1=1";
        $params = [];
        
        if (!empty($mode)) {
            $query .= " AND mode = :mode";
            $params[':mode'] = $mode;
        }
        
        if (!empty($status)) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // 获取总数
        $countQuery = "SELECT COUNT(*) as total FROM payment_orders WHERE 1=1";
        if (!empty($mode)) {
            $countQuery .= " AND mode = :mode";
        }
        if (!empty($status)) {
            $countQuery .= " AND status = :status";
        }
        
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'total' => $totalCount
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取支付统计
 */
function handleGetPaymentStats($db) {
    try {
        // 总收入
        $query = "SELECT SUM(amount) as total FROM payment_orders WHERE status = 'completed'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $totalResult = $stmt->fetch();
        $totalRevenue = $totalResult['total'] ?? 0;
        
        // 今日收入
        $query = "SELECT SUM(amount) as total FROM payment_orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $todayResult = $stmt->fetch();
        $todayRevenue = $todayResult['total'] ?? 0;
        
        // 订单统计
        $query = "SELECT status, COUNT(*) as count FROM payment_orders GROUP BY status";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $statusStats = $stmt->fetchAll();
        
        $stats = [
            'total_revenue' => $totalRevenue,
            'today_revenue' => $todayRevenue,
            'status_stats' => $statusStats
        ];
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 切换支付模式状态（启用/禁用整个模式）
 */
function handleTogglePaymentModeStatus($db, $data) {
    try {
        $mode = $data['mode'] ?? '';
        
        if (empty($mode) || !in_array($mode, ['merchant', 'personal'])) {
            throw new Exception("模式参数无效");
        }
        
        // 获取当前状态
        $query = "SELECT is_enabled FROM payment_mode_status WHERE mode = :mode LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':mode' => $mode]);
        $status = $stmt->fetch();
        
        if (!$status) {
            // 如果不存在，创建一条记录
            $query = "INSERT INTO payment_mode_status (mode, is_enabled) VALUES (:mode, 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([':mode' => $mode]);
            $newStatus = 1;
        } else {
            // 切换状态
            $newStatus = $status['is_enabled'] ? 0 : 1;
            $query = "UPDATE payment_mode_status SET is_enabled = :is_enabled WHERE mode = :mode";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':is_enabled' => $newStatus,
                ':mode' => $mode
            ]);
        }
        
        $statusText = $newStatus ? '已启用' : '已禁用';
        $modeText = $mode === 'merchant' ? '商户模式' : '个人模式';
        echo json_encode([
            'success' => true,
            'message' => "{$modeText}已{$statusText}",
            'new_status' => $newStatus
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取支付模式状态
 */
function handleGetPaymentModeStatus($db) {
    try {
        $query = "SELECT mode, is_enabled FROM payment_mode_status";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $statuses = $stmt->fetchAll();
        
        $statusMap = [];
        foreach ($statuses as $status) {
            $statusMap[$status['mode']] = $status['is_enabled'];
        }
        
        echo json_encode([
            'success' => true,
            'statuses' => $statusMap
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 获取充值比例
 */
function getCoinRatio($db) {
    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? intval($result['setting_value']) : 10;
    } catch (Exception $e) {
        return 10;
    }
}

/**
 * 获取启用的支付方式列表
 */
function handleGetPaymentMethods($db) {
    try {
        // 检查是否是管理员请求（获取所有支付方式）
        $isAdmin = isset($_GET['admin']) && $_GET['admin'] === '1';
        
        if ($isAdmin) {
            // 管理员获取所有支付方式
            $query = "SELECT method_key, method_name, icon, qr_code_url, is_enabled, sort_order 
                      FROM payment_method_config 
                      ORDER BY sort_order ASC";
        } else {
            // 普通用户只获取启用的支付方式
            $query = "SELECT method_key, method_name, icon, qr_code_url, sort_order 
                      FROM payment_method_config 
                      WHERE is_enabled = 1 
                      ORDER BY sort_order ASC";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $methods = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'methods' => $methods
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 保存支付方式配置（管理端）
 */
function handleSavePaymentMethod($db, $data) {
    try {
        $methodKey = $data['method_key'] ?? '';
        $qrCodeUrl = $data['qr_code_url'] ?? '';
        
        if (empty($methodKey)) {
            throw new Exception('支付方式键名不能为空');
        }
        
        // 更新二维码URL
        $query = "UPDATE payment_method_config SET qr_code_url = :qr_code_url WHERE method_key = :method_key";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':qr_code_url' => $qrCodeUrl,
            ':method_key' => $methodKey
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '保存成功'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 切换支付方式启用状态（管理端）
 */
function handleTogglePaymentMethod($db, $data) {
    try {
        $methodKey = $data['method_key'] ?? '';
        
        if (empty($methodKey)) {
            throw new Exception('支付方式键名不能为空');
        }
        
        // 获取当前状态
        $query = "SELECT is_enabled FROM payment_method_config WHERE method_key = :method_key LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':method_key' => $methodKey]);
        $status = $stmt->fetch();
        
        if (!$status) {
            throw new Exception('支付方式不存在');
        }
        
        // 切换状态
        $newStatus = $status['is_enabled'] ? 0 : 1;
        $query = "UPDATE payment_method_config SET is_enabled = :is_enabled WHERE method_key = :method_key";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':is_enabled' => $newStatus,
            ':method_key' => $methodKey
        ]);
        
        echo json_encode([
            'success' => true,
            'is_enabled' => $newStatus,
            'message' => $newStatus ? '已启用' : '已禁用'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * 上传支付方式二维码图片（管理端）
 */
function handleUploadPaymentQrCode($db) {
    try {
        $methodKey = $_POST['method_key'] ?? '';
        
        if (empty($methodKey)) {
            throw new Exception('支付方式键名不能为空');
        }
        
        // 检查文件是否上传
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败');
        }
        
        $file = $_FILES['file'];
        
        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('只支持 JPG、PNG、GIF、WEBP 格式的图片');
        }
        
        // 验证文件大小（最大5MB）
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('图片文件不能超过5MB');
        }
        
        // 生成文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'xianyu_qrcode_' . time() . '.' . $extension;
        
        // 确保上传目录存在
        $uploadDir = '../../images/payment/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . $fileName;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('文件保存失败');
        }
        
        // 生成相对URL（从项目根目录开始）
        $qrCodeUrl = 'images/payment/' . $fileName;
        
        // 更新数据库
        $query = "UPDATE payment_method_config SET qr_code_url = :qr_code_url WHERE method_key = :method_key";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':qr_code_url' => $qrCodeUrl,
            ':method_key' => $methodKey
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '上传成功',
            'qr_code_url' => $qrCodeUrl
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
