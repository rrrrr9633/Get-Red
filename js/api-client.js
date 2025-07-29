// API客户端类
class APIClient {
    constructor() {
        this.baseURL = window.location.origin + '/server/api';
        this.currentUser = null;
        this.activityTimer = null;
        this.heartbeatInterval = null;
        
        // 如果用户已登录，启动心跳检测
        if (this.isLoggedIn()) {
            this.startHeartbeat();
            this.setupActivityMonitoring();
        }
    }

    // 发送HTTP请求
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include' // 包含cookies以维持session
        };

        const finalOptions = { ...defaultOptions, ...options };
        
        if (finalOptions.body && typeof finalOptions.body === 'object') {
            finalOptions.body = JSON.stringify(finalOptions.body);
        }

        try {
            const response = await fetch(url, finalOptions);
            const data = await response.json();
            
            if (!response.ok) {
                // 对于HTTP错误状态码，返回包含错误信息的数据而不是抛出异常
                return { 
                    success: false, 
                    error: data.error || `HTTP error! status: ${response.status}`,
                    message: data.error || `HTTP error! status: ${response.status}`
                };
            }
            
            return data;
        } catch (error) {
            console.error('API请求失败:', error);
            // 只有在网络错误或JSON解析失败时才抛出异常
            return { 
                success: false, 
                error: '网络连接失败，请检查网络连接', 
                message: '网络连接失败，请检查网络连接'
            };
        }
    }

    // 用户注册
    async register(userData) {
        const result = await this.request('/users.php?action=register', {
            method: 'POST',
            body: userData
        });
        return result;
    }

    // 用户登录
    async login(username, password) {
        const result = await this.request('/users.php?action=login', {
            method: 'POST',
            body: { username, password }
        });
        
        if (result.success) {
            this.currentUser = result.user;
            localStorage.setItem('currentUser', JSON.stringify(result.user));
            localStorage.setItem('isLoggedIn', 'true');
        }
        
        return result;
    }

    // 用户退出
    async logout() {
        const result = await this.request('/users.php?action=logout', {
            method: 'POST'
        });
        
        this.currentUser = null;
        localStorage.removeItem('currentUser');
        localStorage.removeItem('isLoggedIn');
        
        return result;
    }

    // 获取用户资料
    async getUserProfile() {
        const result = await this.request('/users.php?action=profile');
        if (result.success) {
            this.currentUser = result.user;
            localStorage.setItem('currentUser', JSON.stringify(result.user));
        }
        return result;
    }

    // 更新用户资料
    async updateProfile(updateData) {
        const result = await this.request('/users.php?action=profile', {
            method: 'PUT',
            body: updateData
        });
        
        if (result.success) {
            // 刷新用户资料
            await this.getUserProfile();
        }
        
        return result;
    }

    // 修改密码
    async changePassword(currentPassword, newPassword) {
        return await this.request('/users.php?action=password', {
            method: 'PUT',
            body: {
                current_password: currentPassword,
                new_password: newPassword
            }
        });
    }

    // 获取余额
    async getBalance() {
        return await this.request('/users.php?action=balance');
    }

    // 获取奖品列表
    async getPrizes(gameType) {
        return await this.request(`/games.php?action=prizes&game_type=${gameType}`);
    }

    // 幸运掉落抽奖
    async luckyDrop() {
        return await this.request('/games.php?action=lucky_drop', {
            method: 'POST'
        });
    }

    // 奖品抽取
    async prizeDraw(drawType) {
        return await this.request('/games.php?action=prize_draw', {
            method: 'POST',
            body: { draw_type: drawType }
        });
    }

    // 幸运转盘
    async wheelSpin() {
        return await this.request('/games.php?action=wheel', {
            method: 'POST'
        });
    }

    // 每日签到
    async dailyCheckin() {
        return await this.request('/games.php?action=checkin', {
            method: 'POST'
        });
    }

    // 获取签到状态
    async getCheckinStatus() {
        return await this.request('/games.php?action=checkin_status');
    }

    // 获取交易记录
    async getTransactions(limit = 20, offset = 0) {
        return await this.request(`/games.php?action=transactions&limit=${limit}&offset=${offset}`);
    }

    // 检查登录状态 - 修改为更严格的检查
    isLoggedIn() {
        // 不再依赖localStorage的isLoggedIn标记
        // 只检查是否有有效的用户数据
        const currentUser = localStorage.getItem('currentUser');
        
        if (!currentUser) {
            return false;
        }
        
        try {
            const user = JSON.parse(currentUser);
            // 检查用户数据是否有效（有ID和用户名）
            if (user && user.id && user.username) {
                this.currentUser = user;
                return true;
            }
        } catch (e) {
            console.error('用户数据解析失败:', e);
            // 清除无效数据
            localStorage.removeItem('currentUser');
            localStorage.removeItem('isLoggedIn');
        }
        
        return false;
    }

    // 获取当前用户
    getCurrentUser() {
        if (!this.currentUser) {
            const userData = localStorage.getItem('currentUser');
            if (userData) {
                this.currentUser = JSON.parse(userData);
            }
        }
        return this.currentUser;
    }

    // 重定向到登录页面
    redirectToLogin() {
        const currentPath = window.location.pathname;
        if (!currentPath.includes('login.html')) {
            // 根据当前路径确定登录页面的相对路径
            let loginPath = 'pages/auth/login.html';
            if (currentPath.includes('/pages/')) {
                if (currentPath.includes('/pages/auth/')) {
                    loginPath = 'login.html';
                } else if (currentPath.includes('/pages/modules/') || currentPath.includes('/pages/user/')) {
                    loginPath = '../auth/login.html';
                } else {
                    loginPath = 'auth/login.html';
                }
            }
            window.location.href = loginPath;
        }
    }

    // 检查并确保用户已登录
    async ensureLoggedIn() {
        if (!this.isLoggedIn()) {
            alert('请先登录！');
            this.redirectToLogin();
            return false;
        }
        
        try {
            // 验证服务器端的登录状态
            await this.getUserProfile();
            return true;
        } catch (error) {
            console.error('用户身份验证失败:', error);
            alert('登录状态已过期，请重新登录！');
            this.redirectToLogin();
            return false;
        }
    }

    // 抽奖相关方法
    async performLuckyDraw(count = 1) {
        if (!this.currentUser) {
            return { success: false, message: '请先登录' };
        }
        
        return await this.request('/draws.php', {
            method: 'POST',
            body: {
                action: 'draw',
                user_id: this.currentUser.id,
                count: count
            }
        });
    }

    async getLotteryHistory(limit = 10) {
        if (!this.currentUser) {
            return { success: false, message: '请先登录' };
        }
        
        return await this.request('/draws.php', {
            method: 'POST',
            body: {
                action: 'history',
                user_id: this.currentUser.id,
                limit: limit
            }
        });
    }

    // 新增抽奖方法
    async drawPrizes(gameType, count = 1) {
        // 直接从localStorage获取用户信息
        const currentUserData = localStorage.getItem('currentUser');
        if (!currentUserData) {
            return { success: false, message: '请先登录' };
        }
        
        let user;
        try {
            user = JSON.parse(currentUserData);
        } catch (e) {
            return { success: false, message: '用户信息异常' };
        }
        
        if (!user.id) {
            return { success: false, message: '用户ID无效' };
        }
        
        return await this.request('/prizes.php', {
            method: 'POST',
            body: {
                action: 'draw',
                game_type: gameType,
                count: count,
                user_id: user.id
            }
        });
    }

    // 奖品管理方法
    async getPrizes(gameType = null) {
        const endpoint = gameType ? `/prizes.php?game_type=${gameType}` : '/prizes.php';
        return await this.request(endpoint);
    }

    async addPrize(prizeData) {
        return await this.request('/prizes.php', {
            method: 'POST',
            body: {
                action: 'add',
                ...prizeData
            }
        });
    }

    async updatePrize(id, prizeData) {
        return await this.request(`/prizes.php?id=${id}`, {
            method: 'PUT',
            body: prizeData
        });
    }

    async deletePrize(id) {
        return await this.request(`/prizes.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    // 用户物品管理方法
    async getUserItems() {
        const currentUserData = localStorage.getItem('currentUser');
        console.log('localStorage中的用户数据:', currentUserData);
        
        if (!currentUserData) {
            return { success: false, message: '请先登录' };
        }
        
        let user;
        try {
            user = JSON.parse(currentUserData);
            console.log('解析后的用户数据:', user);
        } catch (e) {
            console.error('用户数据解析失败:', e);
            return { success: false, message: '用户信息异常' };
        }
        
        const url = `/items.php?user_id=${user.id}`;
        console.log('调用API URL:', this.baseURL + url);
        
        const result = await this.request(url);
        console.log('API响应:', result);
        return result;
    }

    async decomposeItems(itemIds, totalValue) {
        const currentUserData = localStorage.getItem('currentUser');
        console.log('分解API调用，用户数据:', currentUserData);
        
        if (!currentUserData) {
            return { success: false, message: '请先登录' };
        }
        
        let user;
        try {
            user = JSON.parse(currentUserData);
            console.log('解析后的用户数据:', user);
        } catch (e) {
            console.error('用户数据解析失败:', e);
            return { success: false, message: '用户信息异常' };
        }
        
        const requestData = {
            action: 'decompose',
            user_id: user.id,
            item_ids: itemIds,
            total_value: totalValue
        };
        
        console.log('分解请求数据:', requestData);
        
        const result = await this.request('/items.php', {
            method: 'POST',
            body: requestData
        });
        
        console.log('分解API响应:', result);
        return result;
    }

    // 活动监测和心跳检测
    startHeartbeat() {
        // 每30秒发送一次心跳
        this.heartbeatInterval = setInterval(async () => {
            if (this.isLoggedIn() && !this.isAdminPage() && !this.isLoginPage()) {
                try {
                    await this.request('/users.php?action=heartbeat', {
                        method: 'POST'
                    });
                } catch (error) {
                    console.error('心跳检测失败:', error);
                }
            }
        }, 30000);
    }

    setupActivityMonitoring() {
        // 页面可见性变化监听
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // 页面隐藏时设置离线
                this.setOfflineStatus();
            } else if (this.isLoggedIn() && !this.isAdminPage() && !this.isLoginPage()) {
                // 页面可见时设置在线
                this.setOnlineStatus();
            }
        });

        // 页面卸载时设置离线
        window.addEventListener('beforeunload', () => {
            if (this.isLoggedIn()) {
                navigator.sendBeacon(
                    `${this.baseURL}/users.php?action=offline`,
                    JSON.stringify({ user_id: this.getCurrentUser()?.id })
                );
            }
        });

        // 鼠标和键盘活动监听
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
            document.addEventListener(event, () => {
                if (this.isLoggedIn() && !this.isAdminPage() && !this.isLoginPage()) {
                    this.updateActivity();
                }
            }, { passive: true });
        });
    }

    async setOnlineStatus() {
        if (this.isLoggedIn()) {
            try {
                await this.request('/users.php?action=online', {
                    method: 'POST'
                });
            } catch (error) {
                console.error('设置在线状态失败:', error);
            }
        }
    }

    async setOfflineStatus() {
        if (this.isLoggedIn()) {
            try {
                await this.request('/users.php?action=offline', {
                    method: 'POST'
                });
            } catch (error) {
                console.error('设置离线状态失败:', error);
            }
        }
    }

    updateActivity() {
        // 清除之前的定时器
        if (this.activityTimer) {
            clearTimeout(this.activityTimer);
        }

        // 5分钟无活动后不更新状态
        this.activityTimer = setTimeout(() => {
            // 可以在这里添加无活动处理逻辑
        }, 5 * 60 * 1000);
    }

    isLoginPage() {
        return window.location.pathname.includes('login.html') || 
               window.location.pathname.includes('register.html');
    }

    isAdminPage() {
        return window.location.pathname.includes('admin');
    }

    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        if (this.activityTimer) {
            clearTimeout(this.activityTimer);
            this.activityTimer = null;
        }
    }
}

// 创建全局API客户端实例
window.api = new APIClient();
