<?php
/**
 * 支付网关配置 - 支持商户模式和个人模式
 * 支持虎皮椒API集成
 */

class PaymentGateway {
    private $db;
    private $mode; // 'merchant' 或 'personal'
    
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * 获取支付配置
     */
    public function getPaymentConfig($mode = 'merchant') {
        try {
            $query = "SELECT * FROM payment_config WHERE mode = :mode LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':mode' => $mode]);
            $config = $stmt->fetch();
            
            if ($config) {
                $config['settings'] = json_decode($config['settings'], true);
            }
            
            return $config;
        } catch (Exception $e) {
            error_log("获取支付配置失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 保存支付配置
     */
    public function savePaymentConfig($mode, $paymentMethod, $settings) {
        try {
            $query = "INSERT INTO payment_config (mode, payment_method, settings, updated_at) 
                     VALUES (:mode, :payment_method, :settings, NOW())
                     ON DUPLICATE KEY UPDATE settings = :settings, updated_at = NOW()";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':mode' => $mode,
                ':payment_method' => $paymentMethod,
                ':settings' => json_encode($settings)
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("保存支付配置失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建支付订单 - 虎皮椒个人模式
     */
    public function createHuPiJiaoOrder($userId, $amount, $paymentMethod, $returnUrl) {
        try {
            $config = $this->getPaymentConfig('personal');
            
            if (!$config || !$config['settings']) {
                throw new Exception("个人支付配置未设置");
            }
            
            $settings = $config['settings'];
            
            // 验证必要的配置
            if (empty($settings['hupijiao_api_key']) || empty($settings['hupijiao_merchant_id'])) {
                throw new Exception("虎皮椒API配置不完整");
            }
            
            // 生成订单号
            $orderNo = 'HJ' . date('YmdHis') . mt_rand(1000, 9999);
            
            // 准备虎皮椒API请求参数
            $apiParams = [
                'merchant_id' => $settings['hupijiao_merchant_id'],
                'order_no' => $orderNo,
                'amount' => $amount,
                'payment_method' => $paymentMethod, // 'alipay' 或 'wechat'
                'notify_url' => $returnUrl . '?action=hupijiao_callback',
                'return_url' => $returnUrl,
                'timestamp' => time()
            ];
            
            // 生成签名
            $sign = $this->generateHuPiJiaoSign($apiParams, $settings['hupijiao_api_key']);
            $apiParams['sign'] = $sign;
            
            // 调用虎皮椒API
            $response = $this->callHuPiJiaoAPI($apiParams, $settings);
            
            if ($response && isset($response['code']) && $response['code'] == 0) {
                // 保存订单到数据库
                $this->savePaymentOrder([
                    'user_id' => $userId,
                    'order_no' => $orderNo,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'mode' => 'personal',
                    'gateway' => 'hupijiao',
                    'status' => 'pending',
                    'qr_code' => $response['qr_code'] ?? '',
                    'pay_url' => $response['pay_url'] ?? '',
                    'response_data' => json_encode($response)
                ]);
                
                return [
                    'success' => true,
                    'order_no' => $orderNo,
                    'qr_code' => $response['qr_code'] ?? '',
                    'pay_url' => $response['pay_url'] ?? '',
                    'amount' => $amount
                ];
            } else {
                throw new Exception($response['msg'] ?? "虎皮椒API调用失败");
            }
        } catch (Exception $e) {
            error_log("创建虎皮椒订单失败: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成虎皮椒签名
     */
    private function generateHuPiJiaoSign($params, $apiKey) {
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $value) {
            if ($key != 'sign') {
                $signStr .= $key . '=' . $value . '&';
            }
        }
        $signStr .= 'key=' . $apiKey;
        return md5($signStr);
    }

    /**
     * 调用虎皮椒API
     */
    private function callHuPiJiaoAPI($params, $settings) {
        try {
            $apiUrl = $settings['hupijiao_api_url'] ?? 'https://api.hupijiao.com/v1/pay';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                return json_decode($response, true);
            } else {
                throw new Exception("API返回错误代码: " . $httpCode);
            }
        } catch (Exception $e) {
            error_log("调用虎皮椒API异常: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 保存支付订单
     */
    private function savePaymentOrder($orderData) {
        try {
            $query = "INSERT INTO payment_orders 
                     (user_id, order_no, amount, payment_method, mode, gateway, status, qr_code, pay_url, response_data, created_at)
                     VALUES (:user_id, :order_no, :amount, :payment_method, :mode, :gateway, :status, :qr_code, :pay_url, :response_data, NOW())";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($orderData);
        } catch (Exception $e) {
            error_log("保存支付订单失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理虎皮椒支付回调
     */
    public function handleHuPiJiaoCallback($data, $apiKey) {
        try {
            // 验证签名
            $sign = $data['sign'];
            unset($data['sign']);
            
            $calculatedSign = $this->generateHuPiJiaoSign($data, $apiKey);
            
            if ($sign !== $calculatedSign) {
                throw new Exception("签名验证失败");
            }
            
            // 查找订单
            $query = "SELECT * FROM payment_orders WHERE order_no = :order_no LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':order_no' => $data['order_no']]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception("订单不存在");
            }
            
            // 验证金额
            if ($order['amount'] != $data['amount']) {
                throw new Exception("金额不匹配");
            }
            
            // 更新订单状态
            if ($data['status'] == 'success') {
                $this->updateOrderStatus($order['id'], 'completed');
                
                // 给用户增加金币
                $this->addUserCoins($order['user_id'], $order['amount']);
                
                return [
                    'success' => true,
                    'message' => '支付成功'
                ];
            } else {
                $this->updateOrderStatus($order['id'], 'failed');
                return [
                    'success' => false,
                    'message' => '支付失败'
                ];
            }
        } catch (Exception $e) {
            error_log("处理虎皮椒回调失败: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 更新订单状态
     */
    private function updateOrderStatus($orderId, $status) {
        try {
            $query = "UPDATE payment_orders SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':status' => $status, ':id' => $orderId]);
        } catch (Exception $e) {
            error_log("更新订单状态失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 给用户增加金币
     */
    private function addUserCoins($userId, $amount) {
        try {
            // 根据充值比例计算金币
            $coinRatio = $this->getCoinRatio();
            $coins = $amount * $coinRatio;
            
            $query = "UPDATE users SET balance = balance + :coins WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':coins' => $coins, ':user_id' => $userId]);
            
            // 记录交易
            if ($result) {
                $this->recordTransaction($userId, $coins, 'recharge', '充值');
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("增加用户金币失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取充值比例
     */
    private function getCoinRatio() {
        try {
            $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'coin_ratio' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? intval($result['setting_value']) : 10;
        } catch (Exception $e) {
            return 10; // 默认比例
        }
    }

    /**
     * 记录交易
     */
    private function recordTransaction($userId, $amount, $type, $description) {
        try {
            $query = "INSERT INTO transactions (user_id, amount, type, description, created_at)
                     VALUES (:user_id, :amount, :type, :description, NOW())";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':type' => $type,
                ':description' => $description
            ]);
        } catch (Exception $e) {
            error_log("记录交易失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 查询订单状态
     */
    public function getOrderStatus($orderNo) {
        try {
            $query = "SELECT * FROM payment_orders WHERE order_no = :order_no LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':order_no' => $orderNo]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("查询订单状态失败: " . $e->getMessage());
            return null;
        }
    }
}
?>
