// 认证相关功能
class AuthSystem {
    constructor() {
        this.isLoggedIn = false;
        this.currentUser = null;
        this.smsTimer = null;
        this.smsCountdown = 0;
        this.init();
    }

    init() {
        // 初始化标签页切换
        this.initTabs();
        // 生成验证码
        this.generateCaptcha();
        // 检查本地存储的登录状态
        this.checkLoginStatus();
        // 绑定事件
        this.bindEvents();
    }

    initTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const authForms = document.querySelectorAll('.auth-form');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;
                
                // 更新按钮状态
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // 更新表单显示
                authForms.forEach(form => {
                    form.classList.remove('active');
                    if (form.id === `${targetTab}-form`) {
                        form.classList.add('active');
                    }
                });
            });
        });
    }

    bindEvents() {
        // 输入框焦点效果
        const inputs = document.querySelectorAll('.input-group input');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', () => {
                if (!input.value) {
                    input.parentElement.classList.remove('focused');
                }
            });
        });

        // 验证码画布点击重新生成
        const captchaCanvas = document.getElementById('captcha-canvas');
        if (captchaCanvas) {
            captchaCanvas.addEventListener('click', () => {
                this.generateCaptcha();
            });
        }
    }

    generateCaptcha() {
        const canvas = document.getElementById('captcha-canvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        // 清空画布
        ctx.clearRect(0, 0, width, height);

        // 设置背景
        ctx.fillStyle = 'rgba(20, 20, 40, 0.8)';
        ctx.fillRect(0, 0, width, height);

        // 生成随机4位数字
        const captchaText = Math.floor(1000 + Math.random() * 9000).toString();
        this.currentCaptcha = captchaText;

        // 绘制验证码文字
        ctx.font = 'bold 20px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // 为每个字符设置不同颜色和位置
        const colors = ['#ff00ff', '#00ffff', '#ffff00', '#ff0080'];
        for (let i = 0; i < captchaText.length; i++) {
            ctx.fillStyle = colors[i];
            ctx.save();
            ctx.translate(15 + i * 20, height / 2);
            ctx.rotate((Math.random() - 0.5) * 0.4);
            ctx.fillText(captchaText[i], 0, 0);
            ctx.restore();
        }

        // 添加干扰线
        for (let i = 0; i < 5; i++) {
            ctx.strokeStyle = `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.3)`;
            ctx.beginPath();
            ctx.moveTo(Math.random() * width, Math.random() * height);
            ctx.lineTo(Math.random() * width, Math.random() * height);
            ctx.stroke();
        }
    }

    async sendSMSCode() {
        const phoneInput = document.getElementById('reg-phone');
        const verifyBtn = document.querySelector('.verify-btn');
        
        if (!phoneInput.value) {
            this.showMessage('请输入手机号码', 'error');
            return;
        }

        if (!/^1[3-9]\d{9}$/.test(phoneInput.value)) {
            this.showMessage('请输入正确的手机号码', 'error');
            return;
        }

        if (this.smsCountdown > 0) {
            return;
        }

        // 模拟发送短信验证码
        try {
            verifyBtn.disabled = true;
            this.smsCountdown = 60;
            
            const updateCountdown = () => {
                if (this.smsCountdown > 0) {
                    verifyBtn.textContent = `${this.smsCountdown}s后重发`;
                    this.smsCountdown--;
                    this.smsTimer = setTimeout(updateCountdown, 1000);
                } else {
                    verifyBtn.textContent = '获取验证码';
                    verifyBtn.disabled = false;
                }
            };
            
            updateCountdown();
            this.showMessage('验证码已发送，请查收', 'success');
            
            // 模拟验证码（实际项目中应该由后端发送）
            console.log('模拟验证码: 1234');
            
        } catch (error) {
            this.showMessage('发送失败，请稍后重试', 'error');
            verifyBtn.disabled = false;
            clearTimeout(this.smsTimer);
        }
    }

    validatePhone(phone) {
        return /^1[3-9]\d{9}$/.test(phone);
    }

    validatePassword(password) {
        return password.length >= 6;
    }

    async handleLogin() {
        const phone = document.getElementById('login-phone').value;
        const password = document.getElementById('login-password').value;

        if (!phone || !password) {
            this.showMessage('请填写完整信息', 'error');
            return;
        }

        if (!this.validatePhone(phone)) {
            this.showMessage('请输入正确的手机号码', 'error');
            return;
        }

        try {
            // 使用真实的API登录
            const result = await api.login(phone, password);
            
            if (result.success) {
                // 登录成功 - 保存账号密码供下次使用
                localStorage.setItem('savedUsername', phone);
                localStorage.setItem('savedPassword', password);
                
                this.currentUser = result.user;
                this.isLoggedIn = true;
                
                this.showMessage('登录成功！', 'success');
                setTimeout(() => {
                    this.switchToMainPage();
                }, 1000);
            } else {
                this.showMessage(result.message || '登录失败，请检查账号密码', 'error');
            }
            
        } catch (error) {
            this.showMessage('登录失败，请检查账号密码', 'error');
        }
    }

    async handleRegister() {
        const phone = document.getElementById('reg-phone').value;
        const smsCode = document.getElementById('sms-code').value;
        const captcha = document.getElementById('captcha').value;
        const password = document.getElementById('reg-password').value;

        if (!phone || !smsCode || !captcha || !password) {
            this.showMessage('请填写完整信息', 'error');
            return;
        }

        if (!this.validatePhone(phone)) {
            this.showMessage('请输入正确的手机号码', 'error');
            return;
        }

        if (!this.validatePassword(password)) {
            this.showMessage('密码长度至少6位', 'error');
            return;
        }

        // 验证图形验证码
        if (captcha !== this.currentCaptcha) {
            this.showMessage('图形验证码错误', 'error');
            this.generateCaptcha();
            return;
        }

        // 验证短信验证码（模拟）
        if (smsCode !== '1234') {
            this.showMessage('短信验证码错误', 'error');
            return;
        }

        try {
            // 模拟注册请求
            await this.simulateApiCall();
            
            // 注册成功
            this.currentUser = { phone };
            this.isLoggedIn = true;
            localStorage.setItem('userInfo', JSON.stringify(this.currentUser));
            localStorage.setItem('isLoggedIn', 'true');
            
            this.showMessage('注册成功！', 'success');
            setTimeout(() => {
                this.switchToMainPage();
            }, 1000);
            
        } catch (error) {
            this.showMessage('注册失败，请稍后重试', 'error');
        }
    }

    checkLoginStatus() {
        // 只检查保存的账号密码，不自动登录
        const savedUsername = localStorage.getItem('savedUsername');
        const savedPassword = localStorage.getItem('savedPassword');
        
        if (savedUsername && savedPassword) {
            // 填充登录表单但不自动登录
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            if (usernameInput && passwordInput) {
                usernameInput.value = savedUsername;
                passwordInput.value = savedPassword;
            }
        }
        
        // 清除自动登录状态，用户需要手动登录
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userInfo');
    }

    switchToMainPage() {
        // 跳转到主界面页面
        window.location.href = '../main.html';
    }

    async logout() {
        try {
            // 调用真实的API退出登录
            await api.logout();
        } catch (error) {
            console.error('退出登录API调用失败:', error);
        }
        
        this.isLoggedIn = false;
        this.currentUser = null;
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('userInfo');
        localStorage.removeItem('currentUser');
        
        // 跳转到登录页面
        window.location.href = 'login.html';
    }

    showMessage(message, type = 'info') {
        // 创建消息提示
        const messageEl = document.createElement('div');
        messageEl.className = `message-toast ${type}`;
        messageEl.innerHTML = `
            <div class="message-content">
                <span class="message-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
                <span class="message-text">${message}</span>
            </div>
        `;
        
        // 添加样式
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'rgba(0, 255, 0, 0.9)' : type === 'error' ? 'rgba(255, 0, 0, 0.9)' : 'rgba(0, 123, 255, 0.9)'};
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-size: 14px;
            max-width: 300px;
        `;
        
        document.body.appendChild(messageEl);
        
        // 显示动画
        setTimeout(() => {
            messageEl.style.transform = 'translateX(0)';
        }, 100);
        
        // 自动隐藏
        setTimeout(() => {
            messageEl.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(messageEl);
            }, 300);
        }, 3000);
    }

    simulateApiCall() {
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // 90% 成功率
                if (Math.random() > 0.1) {
                    resolve();
                } else {
                    reject(new Error('Network error'));
                }
            }, 1000 + Math.random() * 1000);
        });
    }
}

// 全局函数，供HTML调用
function handleLogin() {
    window.authSystem.handleLogin();
}

function handleRegister() {
    window.authSystem.handleRegister();
}

function sendSMSCode() {
    window.authSystem.sendSMSCode();
}

function logout() {
    window.authSystem.logout();
}

// 初始化认证系统
document.addEventListener('DOMContentLoaded', () => {
    window.authSystem = new AuthSystem();
});
