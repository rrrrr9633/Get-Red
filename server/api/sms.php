<?php
/**
 * 短信验证码API
 * 处理短信验证码的发送和验证
 */

// 启动Session（用于Redis不可用时的备选方案）
require_once __DIR__ . '/../config/security.php';
configureSecureSession();
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sms-service.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    $smsService = new SmsService();
    
    switch ($action) {
        case 'send':
            // 发送短信验证码
            $phoneNumber = $_POST['phone_number'] ?? '';
            
            if (empty($phoneNumber)) {
                echo json_encode([
                    'success' => false,
                    'message' => '请输入手机号'
                ]);
                break;
            }
            
            $result = $smsService->sendVerificationCode($phoneNumber);
            
            echo json_encode($result);
            break;
            
        case 'verify':
            // 验证短信验证码
            $phoneNumber = $_POST['phone_number'] ?? '';
            $code = $_POST['code'] ?? '';
            
            if (empty($phoneNumber) || empty($code)) {
                echo json_encode([
                    'success' => false,
                    'message' => '参数不完整'
                ]);
                break;
            }
            
            $result = $smsService->verifyCode($phoneNumber, $code);
            
            echo json_encode($result);
            break;
            
        case 'check-rate':
            // 检查发送频率（前端调用）
            $phoneNumber = $_GET['phone_number'] ?? '';
            
            if (empty($phoneNumber)) {
                echo json_encode([
                    'success' => false,
                    'message' => '请输入手机号'
                ]);
                break;
            }
            
            $result = $smsService->canSendVerificationCode($phoneNumber);
            
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '未知的操作'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('短信验证码API错误: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>