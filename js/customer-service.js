// 客服弹窗组件
class CustomerServiceWidget {
    constructor() {
        this.isOpen = false;
        this.currentTab = 'online';
        this.chatSession = null;
        this.configs = {};
        this.messageInterval = null;
        this.lastMessageCount = 0; // 记录上次消息数量
        this.pollingTimeout = null; // 轮询超时定时器
        this.isWaitingForReply = false; // 是否正在等待回复
        
        this.init();
    }
    
    // 初始化组件
    init() {
        this.createWidget();
        this.loadConfigs();
        this.bindEvents();
    }
    
    // 创建组件HTML
    createWidget() {
        const widget = document.createElement('div');
        widget.className = 'customer-service-widget';
        widget.innerHTML = `
            <button class="service-trigger" onclick="customerService.toggle()">
                💬
            </button>
            
            <div class="service-modal" onclick="customerService.close(event)">
                <div class="service-content" onclick="event.stopPropagation()">
                    <div class="service-header">
                        <h3 class="service-title">联系客服</h3>
                        <button class="service-close" onclick="customerService.close()">&times;</button>
                    </div>
                    
                    <div class="service-tabs">
                        <button class="service-tab active" onclick="customerService.switchTab('online')">
                            在线客服
                        </button>
                        <button class="service-tab" onclick="customerService.switchTab('qq')">
                            QQ客服
                        </button>
                        <button class="service-tab" onclick="customerService.switchTab('wechat')">
                            微信客服
                        </button>
                    </div>
                    
                    <div class="service-body">
                        <!-- 在线客服面板 -->
                        <div class="service-panel active" id="online-panel">
                            <div class="online-service">
                                <div class="chat-messages" id="chatMessages">
                                    <div class="service-status">
                                        正在连接客服，请稍候...
                                    </div>
                                </div>
                                <div class="chat-input-area">
                                    <textarea class="chat-input" id="messageInput" 
                                              placeholder="请输入您的问题..." 
                                              maxlength="500"></textarea>
                                    <div style="display: flex; gap: 10px;">
                                        <button class="chat-send" onclick="customerService.sendMessage()" style="flex: 1;">
                                            发送
                                        </button>
                                        <button class="chat-refresh" onclick="customerService.manualRefresh()" 
                                                style="background: rgba(255, 255, 255, 0.1); color: #ffd700; padding: 10px 15px; border: 1px solid #ffd700; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;"
                                                onmouseover="this.style.background='rgba(255, 215, 0, 0.2)'"
                                                onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'">
                                            🔄
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- QQ客服面板 -->
                        <div class="service-panel" id="qq-panel">
                            <div class="contact-service">
                                <span class="contact-icon">🐧</span>
                                <h3 class="contact-title">QQ客服</h3>
                                <p class="contact-description">
                                    通过QQ联系我们的客服团队，获得快速响应和专业支持
                                </p>
                                <div class="contact-info">
                                    <div class="contact-number" id="qqNumber">
                                        加载中...
                                    </div>
                                    <button class="copy-button" onclick="customerService.copyContact('qq')">
                                        复制QQ号
                                    </button>
                                    <div class="contact-qr" id="qqQR">
                                        二维码加载中...
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 微信客服面板 -->
                        <div class="service-panel" id="wechat-panel">
                            <div class="contact-service">
                                <span class="contact-icon">💬</span>
                                <h3 class="contact-title">微信客服</h3>
                                <p class="contact-description">
                                    扫描二维码添加微信客服，享受一对一专属服务
                                </p>
                                <div class="contact-info">
                                    <div class="contact-number" id="wechatNumber">
                                        加载中...
                                    </div>
                                    <button class="copy-button" onclick="customerService.copyContact('wechat')">
                                        复制微信号
                                    </button>
                                    <div class="contact-qr" id="wechatQR">
                                        二维码加载中...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(widget);
    }
    
    // 绑定事件
    bindEvents() {
        // 回车发送消息
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
    }
    
    // 加载客服配置
    async loadConfigs() {
        try {
            // 这里应该调用API获取配置，暂时使用默认配置
            this.configs = {
                online: {
                    title: '在线客服',
                    content: '24小时在线客服为您服务',
                    is_enabled: 1
                },
                qq: {
                    title: 'QQ客服',
                    content: '官方QQ客服',
                    contact_info: '123456789',
                    qr_code_url: '',
                    is_enabled: 1
                },
                wechat: {
                    title: '微信客服',
                    content: '官方微信客服',
                    contact_info: 'lucky_service',
                    qr_code_url: '',
                    is_enabled: 1
                }
            };
            
            this.updateConfigs();
        } catch (error) {
            console.error('加载客服配置失败:', error);
        }
    }
    
    // 更新配置显示
    updateConfigs() {
        // 更新QQ客服信息
        const qqConfig = this.configs.qq;
        if (qqConfig && qqConfig.is_enabled) {
            document.getElementById('qqNumber').textContent = qqConfig.contact_info || '暂未配置';
            
            const qqQR = document.getElementById('qqQR');
            if (qqConfig.qr_code_url) {
                qqQR.innerHTML = `<img src="${qqConfig.qr_code_url}" alt="QQ二维码">`;
            } else {
                qqQR.textContent = '二维码暂未配置';
            }
        }
        
        // 更新微信客服信息
        const wechatConfig = this.configs.wechat;
        if (wechatConfig && wechatConfig.is_enabled) {
            document.getElementById('wechatNumber').textContent = wechatConfig.contact_info || '暂未配置';
            
            const wechatQR = document.getElementById('wechatQR');
            if (wechatConfig.qr_code_url) {
                wechatQR.innerHTML = `<img src="${wechatConfig.qr_code_url}" alt="微信二维码">`;
            } else {
                wechatQR.textContent = '二维码暂未配置';
            }
        }
    }
    
    // 切换显示/隐藏
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    // 打开弹窗
    open() {
        const modal = document.querySelector('.service-modal');
        modal.classList.add('show');
        this.isOpen = true;
        
        // 如果是在线客服且未创建会话，则创建会话
        if (this.currentTab === 'online' && !this.chatSession) {
            this.startChatSession();
        }
    }
    
    // 关闭弹窗
    close(event) {
        if (event && event.target !== event.currentTarget) {
            return;
        }
        
        const modal = document.querySelector('.service-modal');
        modal.classList.remove('show');
        this.isOpen = false;
        
        // 停止消息轮询
        this.stopMessagePolling();
        
        // 重置消息计数
        this.lastMessageCount = 0;
        this.isWaitingForReply = false;
    }
    
    // 切换标签
    switchTab(tab) {
        // 更新标签状态
        document.querySelectorAll('.service-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[onclick="customerService.switchTab('${tab}')"]`).classList.add('active');
        
        // 更新面板显示
        document.querySelectorAll('.service-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(`${tab}-panel`).classList.add('active');
        
        this.currentTab = tab;
        
        // 如果切换到在线客服且未创建会话，则创建会话
        if (tab === 'online' && !this.chatSession) {
            this.startChatSession();
        }
    }
    
    // 开始聊天会话
    async startChatSession() {
        try {
            // 检查是否已登录
            if (!window.api || !window.api.isLoggedIn()) {
                this.showChatStatus('请先登录后再使用在线客服');
                return;
            }
            
            this.showChatStatus('正在连接客服...');
            
            // 调用API创建或获取会话
            const response = await fetch('../../server/api/customer-service.php?action=start_session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.chatSession = data.session_id;
                
                if (data.has_service) {
                    this.showChatStatus('已连接到您的专属客服');
                } else {
                    this.showChatStatus('客服正在为您接入，请稍候...');
                }
                
                // 加载历史消息（不启动轮询）
                await this.loadChatHistory();
            } else {
                this.showChatStatus('连接客服失败: ' + data.error);
            }
            
        } catch (error) {
            console.error('创建聊天会话失败:', error);
            this.showChatStatus('连接客服失败，请稍后再试');
        }
    }
    
    // 显示聊天状态
    showChatStatus(message) {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML = `<div class="service-status">${message}</div>`;
    }
    
    // 加载聊天历史
    async loadChatHistory() {
        if (!this.chatSession) return;
        
        try {
            const response = await fetch(`../../server/api/customer-service.php?action=messages&session_id=${this.chatSession}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                const previousCount = this.lastMessageCount;
                this.lastMessageCount = data.messages.length;
                
                // 检查是否有新消息
                const hasNewMessages = data.messages.length > previousCount;
                
                // 如果收到新消息且正在等待回复，停止轮询
                if (hasNewMessages && this.isWaitingForReply && data.messages.length > 0) {
                    const lastMessage = data.messages[data.messages.length - 1];
                    // 如果最后一条消息是客服发的，说明收到回复了
                    if (lastMessage.sender_type === 'service') {
                        console.log('收到客服回复，停止轮询');
                        this.stopMessagePolling();
                        this.isWaitingForReply = false;
                    }
                }
                
                this.renderMessages(data.messages, hasNewMessages);
            } else {
                console.error('加载聊天历史失败:', data.error);
            }
        } catch (error) {
            console.error('加载聊天历史失败:', error);
        }
    }
    
    // 渲染消息
    renderMessages(messages, scrollToBottom = true) {
        const chatMessages = document.getElementById('chatMessages');
        
        // 保存当前滚动位置
        const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 50;
        
        chatMessages.innerHTML = '';
        
        if (messages.length === 0) {
            chatMessages.innerHTML = '<div class="service-status">暂无消息</div>';
            return;
        }
        
        messages.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${msg.sender_type}`;
            const time = new Date(msg.created_at).toLocaleTimeString('zh-CN', {
                hour: '2-digit',
                minute: '2-digit'
            });
            messageDiv.innerHTML = `
                <div>${this.escapeHtml(msg.message)}</div>
                <div class="message-time">${time}</div>
            `;
            chatMessages.appendChild(messageDiv);
        });
        
        // 只在有新消息或用户在底部时才自动滚动
        if (scrollToBottom || wasAtBottom) {
            setTimeout(() => {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 0);
        }
    }
    
    // HTML转义
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 发送消息
    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();
        
        if (!message || !this.chatSession) {
            return;
        }
        
        try {
            const response = await fetch('../../server/api/customer-service.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    session_id: this.chatSession,
                    message: message,
                    message_type: 'text'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // 清空输入框
                messageInput.value = '';
                
                // 立即刷新消息列表
                await this.loadChatHistory();
                
                // 发送消息后，启动智能轮询（等待客服回复）
                console.log('消息已发送，启动智能轮询等待回复');
                this.startSmartPolling();
            } else {
                this.showMessage('发送失败: ' + data.error, 'error');
            }
            
        } catch (error) {
            console.error('发送消息失败:', error);
            this.showMessage('发送消息失败，请稍后再试', 'error');
        }
    }
    
    // 添加客服消息（已废弃，改用loadChatHistory）
    addServiceMessage(content) {
        // 此方法已被loadChatHistory替代
        this.loadChatHistory();
    }
    
    // 开始消息轮询（旧方法，已废弃）
    startMessagePolling() {
        // 此方法已被 startSmartPolling 替代
        console.warn('startMessagePolling is deprecated, use startSmartPolling instead');
    }
    
    // 智能轮询：发送消息后启动，收到回复或超时后停止
    startSmartPolling() {
        // 先停止之前的轮询
        this.stopMessagePolling();
        
        console.log('启动智能轮询，15秒超时');
        this.isWaitingForReply = true;
        
        // 立即检查一次
        this.loadChatHistory();
        
        // 每2秒轮询一次
        this.messageInterval = setInterval(() => {
            if (this.chatSession && this.isOpen && this.isWaitingForReply) {
                this.loadChatHistory();
            }
        }, 2000);
        
        // 15秒超时自动停止
        this.pollingTimeout = setTimeout(() => {
            console.log('轮询超时（15秒），自动停止');
            this.stopMessagePolling();
            this.isWaitingForReply = false;
        }, 15000);
    }
    
    // 停止消息轮询
    stopMessagePolling() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
            this.messageInterval = null;
            console.log('轮询已停止');
        }
        
        if (this.pollingTimeout) {
            clearTimeout(this.pollingTimeout);
            this.pollingTimeout = null;
        }
    }
    
    // 手动刷新消息
    async manualRefresh() {
        console.log('手动刷新消息');
        await this.loadChatHistory();
        this.showMessage('消息已刷新', 'success');
    }
    
    // 复制联系方式
    copyContact(type) {
        const config = this.configs[type];
        if (!config || !config.contact_info) {
            this.showMessage('联系方式暂未配置', 'error');
            return;
        }
        
        // 复制到剪贴板
        if (navigator.clipboard) {
            navigator.clipboard.writeText(config.contact_info).then(() => {
                this.showMessage('已复制到剪贴板', 'success');
            }).catch(() => {
                this.fallbackCopy(config.contact_info);
            });
        } else {
            this.fallbackCopy(config.contact_info);
        }
    }
    
    // 备用复制方法
    fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            this.showMessage('已复制到剪贴板', 'success');
        } catch (err) {
            this.showMessage('复制失败，请手动复制', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    // 显示提示消息
    showMessage(text, type = 'success') {
        // 创建消息元素
        const message = document.createElement('div');
        message.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: #fff;
            font-weight: bold;
            z-index: 10001;
            animation: slideIn 0.3s ease;
            ${type === 'success' ? 'background: linear-gradient(45deg, #00ff00, #00aa00);' : 'background: linear-gradient(45deg, #ff6b6b, #ff8e8e);'}
        `;
        message.textContent = text;
        
        document.body.appendChild(message);
        
        // 3秒后移除消息
        setTimeout(() => {
            document.body.removeChild(message);
        }, 3000);
    }
}

// 全局实例
let customerService = null;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 检查是否为admin页面，如果是则不加载客服组件
    const path = window.location.pathname;
    const isAdminPage = path.includes('/admin/') || path.includes('super-admin') || path.includes('create-super-admin');
    
    if (!isAdminPage) {
        customerService = new CustomerServiceWidget();
    }
});
