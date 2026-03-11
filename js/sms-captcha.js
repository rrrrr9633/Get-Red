/**
 * 短信验证码管理脚本
 */

class SmsCaptchaManager {
    constructor(containerId = 'sms-captcha-container') {
        this.container = document.getElementById(containerId);
        this.countdown = 0;
        this.countdownInterval = null;
        this.phoneNumber = '';
        this.init();
    }
    
    /**
     * 初始化短信验证码组件
     */
    async init() {
        if (!this.container) return;
        
        // 创建短信验证码HTML结构
        this.container.innerHTML = `
            <div class="sms-captcha-wrapper">
                <div class="phone-input-group">
                    <input type="tel" id="phoneNumber" placeholder="请输入手机号" maxlength="11" class="phone-input">
                </div>
                <div class="captcha-input-group">
                    <input type="text" id="smsCaptchaInput" placeholder="请输入短信验证码" maxlength="6" class="sms-captcha-input">
                    <button type="button" id="sendSmsCaptcha" class="sms-captcha-btn" disabled>
                        <span id="sendBtnText">获取验证码</span>
                        <span id="countdownText" style="display: none;"></span>
                    </button>
                </div>
                <div class="captcha-tips">
                    <p>验证码将在5分钟内有效</p>
                </div>
            </div>
        `;
        
        // 绑定事件
        const phoneInput = document.getElementById('phoneNumber');
        const sendBtn = document.getElementById('sendSmsCaptcha');
        
        phoneInput.addEventListener('input', () => this.onPhoneInputChange());
        sendBtn.addEventListener('click', () => this.sendVerificationCode());
        
        // 初始检查
        this.onPhoneInputChange();
    }
    
    /**
     * 手机号输入变化处理
     */
    onPhoneInputChange() {
        const phoneInput = document.getElementById('phoneNumber');
        const sendBtn = document.getElementById('sendSmsCaptcha');
        
        this.phoneNumber = phoneInput.value.trim();
        
        // 验证手机号格式
        const isValid = this.validatePhoneNumber(this.phoneNumber);
        
        if (isValid && this.countdown === 0) {
            sendBtn.disabled = false;
            sendBtn.classList.remove('disabled');
        } else {
            sendBtn.disabled = true;
            sendBtn.classList.add('disabled');
        }
    }
    
    /**
     * 发送短信验证码
     */
    async sendVerificationCode() {
        if (!this.validatePhoneNumber(this.phoneNumber)) {
            this.showMessage('请输入正确的手机号', 'error');
            return;
        }
        
        const sendBtn = document.getElementById('sendSmsCaptcha');
        const sendBtnText = document.getElementById('sendBtnText');
        const countdownText = document.getElementById('countdownText');
        
        try {
            // 检查发送频率
            const checkResponse = await fetch(`/server/api/sms.php?action=check-rate&phone_number=${encodeURIComponent(this.phoneNumber)}`);
            const checkResult = await checkResponse.json();
            
            if (!checkResult.can_send) {
                if (checkResult.retry_after) {
                    this.startCountdown(checkResult.retry_after);
                }
                this.showMessage(checkResult.message, 'error');
                return;
            }
            
            // 显示加载状态
            sendBtn.disabled = true;
            sendBtn.classList.add('disabled');
            sendBtnText.textContent = '发送中...';
            
            // 发送验证码
            const formData = new FormData();
            formData.append('phone_number', this.phoneNumber);
            
            const response = await fetch('/server/api/sms.php?action=send', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('验证码发送成功', 'success');
                this.startCountdown(60); // 60秒倒计时
                
                // 自动聚焦到验证码输入框
                document.getElementById('smsCaptchaInput').focus();
            } else {
                this.showMessage(result.message, 'error');
                
                // 恢复按钮状态
                sendBtn.disabled = false;
                sendBtn.classList.remove('disabled');
                sendBtnText.textContent = '获取验证码';
            }
        } catch (error) {
            console.error('发送验证码失败:', error);
            this.showMessage('网络连接失败，请重试', 'error');
            
            // 恢复按钮状态
            sendBtn.disabled = false;
            sendBtn.classList.remove('disabled');
            sendBtnText.textContent = '获取验证码';
        }
    }
    
    /**
     * 开始倒计时
     */
    startCountdown(seconds) {
        this.countdown = seconds;
        const sendBtn = document.getElementById('sendSmsCaptcha');
        const sendBtnText = document.getElementById('sendBtnText');
        const countdownText = document.getElementById('countdownText');
        
        // 显示倒计时
        sendBtnText.style.display = 'none';
        countdownText.style.display = 'inline';
        countdownText.textContent = `${seconds}秒`;
        
        sendBtn.disabled = true;
        sendBtn.classList.add('disabled');
        
        // 清除之前的倒计时
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // 开始倒计时
        this.countdownInterval = setInterval(() => {
            this.countdown--;
            
            if (this.countdown <= 0) {
                this.stopCountdown();
            } else {
                countdownText.textContent = `${this.countdown}秒`;
            }
        }, 1000);
    }
    
    /**
     * 停止倒计时
     */
    stopCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        
        this.countdown = 0;
        
        const sendBtn = document.getElementById('sendSmsCaptcha');
        const sendBtnText = document.getElementById('sendBtnText');
        const countdownText = document.getElementById('countdownText');
        
        // 恢复按钮状态
        sendBtnText.style.display = 'inline';
        countdownText.style.display = 'none';
        
        // 重新检查手机号是否有效
        this.onPhoneInputChange();
    }
    
    /**
     * 获取验证码值
     */
    getValue() {
        return document.getElementById('smsCaptchaInput').value.trim();
    }
    
    /**
     * 获取手机号
     */
    getPhoneNumber() {
        return this.phoneNumber;
    }
    
    /**
     * 验证短信验证码
     */
    async verify() {
        const code = this.getValue();
        
        if (!code) {
            return { success: false, message: '请输入验证码' };
        }
        
        if (!this.phoneNumber) {
            return { success: false, message: '请输入手机号' };
        }
        
        try {
            const formData = new FormData();
            formData.append('phone_number', this.phoneNumber);
            formData.append('code', code);
            
            const response = await fetch('/server/api/sms.php?action=verify', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 验证成功，停止倒计时
                this.stopCountdown();
            }
            
            return result;
        } catch (error) {
            console.error('验证码验证失败:', error);
            return { success: false, message: '验证失败，请重试' };
        }
    }
    
    /**
     * 清空验证码
     */
    clear() {
        document.getElementById('smsCaptchaInput').value = '';
    }
    
    /**
     * 验证手机号格式
     */
    validatePhoneNumber(phoneNumber) {
        return /^1[3-9]\d{9}$/.test(phoneNumber);
    }
    
    /**
     * 显示消息提示
     */
    showMessage(text, type = 'success') {
        // 使用现有的消息提示系统
        if (typeof showMessage === 'function') {
            showMessage(text, type);
        } else {
            // 简单的alert作为备选
            alert(text);
        }
    }
    
    /**
     * 销毁组件
     */
    destroy() {
        this.stopCountdown();
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

// 导出到全局作用域
window.SmsCaptchaManager = SmsCaptchaManager;