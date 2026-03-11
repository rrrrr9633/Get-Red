<?php
/**
 * 阿里云短信服务配置
 * 需要配置以下信息：
 * 1. AccessKey ID 和 AccessKey Secret
 * 2. 短信签名
 * 3. 短信模板CODE
 * 4. 短信发送频率限制
 */

// 加载环境变量
if (file_exists(__DIR__ . '/../../.env.sms')) {
    $lines = file(__DIR__ . '/../../.env.sms', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
}

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
        // 从环境变量读取配置
        $this->accessKeyId = getenv('ALIYUN_SMS_ACCESS_KEY_ID');
        $this->accessKeySecret = getenv('ALIYUN_SMS_ACCESS_KEY_SECRET');
        $this->signName = getenv('ALIYUN_SMS_SIGN_NAME');
        $this->templateCode = getenv('ALIYUN_SMS_TEMPLATE_CODE');
        
        // 验证必需配置
        if (empty($this->accessKeyId) || empty($this->accessKeySecret)) {
            throw new Exception('短信服务配置不完整');
        }
        
        if (empty($this->signName) || empty($this->templateCode)) {
            throw new Exception('短信服务配置不完整');
        }
        
        // 初始化Redis
        require_once __DIR__ . '/redis-cache.php';
        $this->redis = RedisCache::getInstance();
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
            $params = [
                "PhoneNumbers" => $phoneNumber,
                "SignName" => $this->signName,
                "TemplateCode" => $this->templateCode,
                "TemplateParam" => json_encode(["code" => $code, "min" => "5"], JSON_UNESCAPED_UNICODE)
            ];
            
            // 开发环境：模拟发送
            if ($this->isDevelopment()) {
                error_log("开发环境：发送短信到 {$phoneNumber}，验证码：{$code}");
                return ['success' => true, 'message' => '开发环境：短信发送成功'];
            }
            
            // 生产环境：实际调用阿里云API
            $result = $this->callAliyunSmsApi($params);
            return $result;
            
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
        $appEnv = getenv('APP_ENV');
        return $appEnv === 'development' || $appEnv === 'dev';
    }
    
    /**
     * 实际调用阿里云短信API（使用云通信号码认证服务）
     */
    private function callAliyunSmsApi($params) {
        try {
            // 阿里云云通信号码认证服务API配置
            $apiUrl = 'https://dypnsapi.aliyuncs.com/';
            $method = 'GET';
            
            // 公共参数
            $publicParams = [
                'Format' => 'JSON',
                'Version' => '2017-05-25',
                'AccessKeyId' => $this->accessKeyId,
                'SignatureMethod' => 'HMAC-SHA1',
                'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'SignatureVersion' => '1.0',
                'SignatureNonce' => uniqid(),
                'Action' => 'SendSmsVerifyCode',  // 使用号码认证服务的接口
                'RegionId' => 'cn-hangzhou',
            ];
            
            // 业务参数（号码认证服务的参数格式）
            $businessParams = [
                'SchemeName' => '短信验证码',  // 赠送模板所属的方案名
                'SignName' => $this->signName,
                'TemplateCode' => $this->templateCode,
                'TemplateParam' => $params['TemplateParam'],
                'CountryCode' => '86',
                'PhoneNumber' => $params['PhoneNumbers'],
            ];
            
            // 合并参数
            $allParams = array_merge($publicParams, $businessParams);
            
            // 生成签名
            $signature = $this->generateSignature($allParams, $method);
            $allParams['Signature'] = $signature;
            
            // 手动构建查询字符串（不使用http_build_query，避免编码不一致）
            $queryString = '';
            foreach ($allParams as $key => $value) {
                $queryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
            }
            $queryString = substr($queryString, 1); // 去掉第一个&
            
            $url = $apiUrl . '?' . $queryString;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log("阿里云短信API HTTP错误: {$httpCode}, Response: {$response}");
                
                // 尝试解析错误信息
                $errorResult = json_decode($response, true);
                if ($errorResult && isset($errorResult['Message'])) {
                    return ['success' => false, 'message' => "短信发送失败: {$errorResult['Message']}"];
                }
                
                return ['success' => false, 'message' => "HTTP请求失败: {$httpCode}"];
            }
            
            $result = json_decode($response, true);
            
            if (isset($result['Code']) && $result['Code'] === 'OK') {
                error_log("短信发送成功: {$params['PhoneNumbers']}");
                return ['success' => true, 'message' => '短信发送成功'];
            } else {
                $errorMsg = $result['Message'] ?? '未知错误';
                error_log("阿里云短信发送失败: {$errorMsg}, Response: {$response}");
                return ['success' => false, 'message' => "短信发送失败: {$errorMsg}"];
            }
            
        } catch (Exception $e) {
            error_log("阿里云短信API调用异常: " . $e->getMessage());
            return ['success' => false, 'message' => '短信发送异常: ' . $e->getMessage()];
        }
    }
    
    /**
     * 生成阿里云API签名
     */
    private function generateSignature($params, $method = 'GET') {
        // 1. 排序参数
        ksort($params);
        
        // 2. 构造待签名字符串
        $canonicalizedQueryString = '';
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $canonicalizedQueryString = substr($canonicalizedQueryString, 1);
        
        // 3. 构造签名字符串
        $stringToSign = $method . '&' . $this->percentEncode('/') . '&' . $this->percentEncode($canonicalizedQueryString);
        
        // 4. 计算签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
        
        return $signature;
    }
    
    /**
     * URL编码（符合阿里云规范）
     * 阿里云要求：
     * 1. 使用UTF-8字符集进行URL编码
     * 2. 编码后的字符串中，加号（+）替换为%20、星号（*）替换为%2A、%7E替换为波浪号（~）
     */
    private function percentEncode($str) {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
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