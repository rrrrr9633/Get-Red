<?php
/**
 * 阿里云短信服务配置
 * 需要配置以下信息：
 * 1. AccessKey ID 和 AccessKey Secret
 * 2. 短信签名
 * 3. 短信模板CODE
 * 4. 短信发送频率限制
 */

class SmsService {
    private $accessKeyId;
    private $accessKeySecret;
    private $signName;
    private $templateCode;
    private $redis;
    
    // 短信发送频率限制（秒）
    const SEND_INTERVAL = 60; // 60秒内只能发送一次
    const MAX_DAILY_SENDS = 10; // 每天最多发送10次
    const VERIFY_CODE_TTL = 300; // 验证码有效期5分钟
    
    public function __construct() {
        // 从环境变量或配置文件中读取配置
        $this->accessKeyId = getenv('ALIYUN_SMS_ACCESS_KEY_ID') ?: '';
        $this->accessKeySecret = getenv('ALIYUN_SMS_ACCESS_KEY_SECRET') ?: '';
        $this->signName = getenv('ALIYUN_SMS_SIGN_NAME') ?: '无敌俱乐部';
        $this->templateCode = getenv('ALIYUN_SMS_TEMPLATE_CODE') ?: 'SMS_123456789';
        
        // 初始化Redis
        require_once __DIR__ . '/redis-cache.php';
        $this->redis = RedisCache::getInstance();
        
        // 验证配置（生产环境需要验证，开发环境可以跳过）
        $appEnv = getenv('APP_ENV') ?: 'development';
        if ($appEnv === 'production' && (empty($this->accessKeyId) || empty($this->accessKeySecret))) {
            throw new Exception('阿里云短信服务配置不完整，请设置AccessKey ID和Secret');
        }
    }
    
    /**
     * 发送短信验证码
     * @param string $phoneNumber 手机号
     * @param string $code 验证码（可选，不传则自动生成）
     * @return array ['success' => bool, 'message' => string, 'code' => string]
     */
    public function sendVerificationCode($phoneNumber, $code = null) {
        // 验证手机号格式
        if (!$this->validatePhoneNumber($phoneNumber)) {
            return ['success' => false, 'message' => '手机号格式不正确'];
        }
        
        // 检查发送频率限制
        $rateLimitResult = $this->checkRateLimit($phoneNumber);
        if (!$rateLimitResult['success']) {
            return $rateLimitResult;
        }
        
        // 生成验证码（如果未提供）
        if (empty($code)) {
            $code = $this->generateVerificationCode();
        }
        
        // 生成会话ID
        $sessionId = bin2hex(random_bytes(16));
        
        // 发送短信
        $sendResult = $this->sendSms($phoneNumber, $code);
        
        if ($sendResult['success']) {
            // 保存验证码到Redis
            $this->saveVerificationCode($phoneNumber, $code, $sessionId);
            
            // 更新发送记录
            $this->updateSendRecord($phoneNumber);
            
            return [
                'success' => true,
                'message' => '验证码发送成功',
                'session_id' => $sessionId,
                'code' => $code // 开发环境返回验证码，生产环境应移除
            ];
        }
        
        return $sendResult;
    }
    
    /**
     * 验证短信验证码
     * @param string $phoneNumber 手机号
     * @param string $code 用户输入的验证码
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyCode($phoneNumber, $code) {
        if (empty($phoneNumber) || empty($code)) {
            return ['success' => false, 'message' => '参数不完整'];
        }
        
        // 从Redis获取验证码
        $storedCode = $this->getVerificationCode($phoneNumber);
        
        if (!$storedCode) {
            return ['success' => false, 'message' => '验证码已过期或不存在'];
        }
        
        // 验证验证码
        if ($storedCode === $code) {
            // 验证成功后删除验证码
            $this->deleteVerificationCode($phoneNumber);
            return ['success' => true, 'message' => '验证码正确'];
        } else {
            return ['success' => false, 'message' => '验证码错误'];
        }
    }
    
    /**
     * 检查是否可以发送验证码
     * @param string $phoneNumber 手机号
     * @return array ['success' => bool, 'can_send' => bool, 'message' => string, 'retry_after' => int|null]
     */
    public function canSendVerificationCode($phoneNumber) {
        $rateLimitResult = $this->checkRateLimit($phoneNumber);
        
        if ($rateLimitResult['success']) {
            return [
                'success' => true,
                'can_send' => true,
                'message' => '可以发送验证码'
            ];
        } else {
            return [
                'success' => false,
                'can_send' => false,
                'message' => $rateLimitResult['message'],
                'retry_after' => $rateLimitResult['retry_after'] ?? null
            ];
        }
    }
    
    /**
     * 发送短信
     * @param string $phoneNumber 手机号
     * @param string $code 验证码
     * @return array ['success' => bool, 'message' => string]
     */
    private function sendSms($phoneNumber, $code) {
        try {
            // 阿里云短信服务SDK调用
            // 这里使用阿里云官方SDK的调用方式
            // 需要安装阿里云SDK: composer require alibabacloud/sdk
            
            $params = [
                "PhoneNumbers" => $phoneNumber,
                "SignName" => $this->signName,
                "TemplateCode" => $this->templateCode,
                "TemplateParam" => json_encode(["code" => $code], JSON_UNESCAPED_UNICODE)
            ];
            
            // 实际调用阿里云API
            // $result = $this->callAliyunSmsApi($params);
            
            // 模拟发送成功（开发环境）
            if ($this->isDevelopment()) {
                error_log("开发环境：发送短信到 {$phoneNumber}，验证码：{$code}");
                return ['success' => true, 'message' => '开发环境：短信发送成功'];
            }
            
            // 生产环境需要实际调用阿里云API
            // 这里返回模拟成功，实际使用时需要实现阿里云API调用
            return ['success' => true, 'message' => '短信发送成功'];
            
        } catch (Exception $e) {
            error_log("短信发送失败: " . $e->getMessage());
            return ['success' => false, 'message' => '短信发送失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 检查发送频率限制
     */
    private function checkRateLimit($phoneNumber) {
        if (!$this->redis->isEnabled()) {
            return ['success' => true]; // Redis不可用时跳过频率限制
        }
        
        $now = time();
        
        // 检查60秒内是否已发送
        $lastSendKey = "sms:last_send:{$phoneNumber}";
        $lastSendTime = $this->redis->get($lastSendKey);
        
        if ($lastSendTime && ($now - $lastSendTime) < self::SEND_INTERVAL) {
            $waitSeconds = self::SEND_INTERVAL - ($now - $lastSendTime);
            return [
                'success' => false,
                'message' => "请求过于频繁，请{$waitSeconds}秒后再试",
                'retry_after' => $waitSeconds
            ];
        }
        
        // 检查当天发送次数
        $dateKey = date('Ymd');
        $dailyCountKey = "sms:daily_count:{$phoneNumber}:{$dateKey}";
        $dailyCount = $this->redis->get($dailyCountKey) ?: 0;
        
        if ($dailyCount >= self::MAX_DAILY_SENDS) {
            return [
                'success' => false,
                'message' => "今日发送次数已达上限，请明天再试"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * 更新发送记录
     */
    private function updateSendRecord($phoneNumber) {
        if (!$this->redis->isEnabled()) return;
        
        $now = time();
        $dateKey = date('Ymd');
        
        // 记录最后一次发送时间
        $lastSendKey = "sms:last_send:{$phoneNumber}";
        $this->redis->set($lastSendKey, $now, self::SEND_INTERVAL);
        
        // 增加当天发送计数
        $dailyCountKey = "sms:daily_count:{$phoneNumber}:{$dateKey}";
        $dailyCount = $this->redis->get($dailyCountKey) ?: 0;
        $this->redis->set($dailyCountKey, $dailyCount + 1, 86400); // 24小时过期
    }
    

    
    /**
     * 生成6位数字验证码
     */
    private function generateVerificationCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * 验证手机号格式
     */
    private function validatePhoneNumber($phoneNumber) {
        // 简单的手机号格式验证
        return preg_match('/^1[3-9]\d{9}$/', $phoneNumber);
    }
    
    /**
     * 判断是否为开发环境
     */
    private function isDevelopment() {
        return getenv('APP_ENV') === 'development' || 
               getenv('APP_ENV') === 'dev' || 
               php_sapi_name() === 'cli-server';
    }
    
    /**
     * 实际调用阿里云短信API（需要安装阿里云SDK）
     */
    private function callAliyunSmsApi($params) {
        // 这里需要实现阿里云SDK的实际调用
        // 示例代码：
        /*
        AlibabaCloud::accessKeyClient($this->accessKeyId, $this->accessKeySecret)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        
        try {
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')
                ->options([
                    'query' => $params,
                ])
                ->request();
            
            $result = $result->toArray();
            
            if ($result['Code'] === 'OK') {
                return ['success' => true, 'message' => '短信发送成功'];
            } else {
                return ['success' => false, 'message' => $result['Message']];
            }
        } catch (ClientException $e) {
            return ['success' => false, 'message' => $e->getErrorMessage()];
        } catch (ServerException $e) {
            return ['success' => false, 'message' => $e->getErrorMessage()];
        }
        */
        
        // 返回模拟结果
        return ['success' => true, 'message' => '短信发送成功'];
    }
    
    /**
     * 保存验证码到Redis
     */
    private function saveVerificationCode($phoneNumber, $code, $sessionId) {
        if (!$this->redis->isEnabled()) {
            // Redis不可用时，使用Session作为备选   
            $_SESSION["sms_verify_code_{$phoneNumber}"] = $code;
            $_SESSION["sms_session_id_{$phoneNumber}"] = $sessionId;
            return;
        }
        
        $key = "sms:verify_code:{$phoneNumber}";
        $this->redis->set($key, $code, self::VERIFY_CODE_TTL);
        
        // 同时保存session_id
        $sessionKey = "sms:session_id:{$phoneNumber}";
        $this->redis->set($sessionKey, $sessionId, self::VERIFY_CODE_TTL);
    }
    
    /**
     * 获取验证码
     */
    private function getVerificationCode($phoneNumber) {
        if (!$this->redis->isEnabled()) {
            // Redis不可用时，从Session获取
            return $_SESSION["sms_verify_code_{$phoneNumber}"] ?? null;
        }
        
        $key = "sms:verify_code:{$phoneNumber}";
        return $this->redis->get($key);
    }
    
    /**
     * 删除验证码
     */
    private function deleteVerificationCode($phoneNumber) {
        if (!$this->redis->isEnabled()) {
            // Redis不可用时，从Session删除
            unset($_SESSION["sms_verify_code_{$phoneNumber}"]);
            unset($_SESSION["sms_session_id_{$phoneNumber}"]);
            return;
        }
        
        $key = "sms:verify_code:{$phoneNumber}";
        $this->redis->delete($key);
        
        $sessionKey = "sms:session_id:{$phoneNumber}";
        $this->redis->delete($sessionKey);
    }
}
?>