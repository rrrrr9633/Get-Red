/**
 * Lucky Loader - 动态加载Lucky实例配置
 * 统一Lucky页面系统 - 动态加载模块
 * 
 * 功能:
 * - 解析URL参数获取Lucky实例ID
 * - 调用API获取配置、奖品和价格
 * - 动态更新页面内容
 * 
 * 需求: 1.1, 1.2, 2.2, 2.3, 3.2, 3.3, 4.2
 */

class LuckyLoader {
    constructor() {
        this.luckyId = null;
        this.config = null;
        this.prizes = [];
        this.prices = {};
    }

    /**
     * 获取Lucky实例ID
     * 从URL参数中获取id，如果没有则默认为1
     * 需求: 1.1, 1.2
     */
    getLuckyInstanceId() {
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');
        
        // 如果没有id参数，使用默认值1
        if (!id) {
            return 1;
        }
        
        // 验证id是否为正整数
        const parsedId = parseInt(id, 10);
        if (isNaN(parsedId) || parsedId <= 0) {
            console.warn('无效的Lucky实例ID:', id, '使用默认值1');
            return 1;
        }
        
        return parsedId;
    }

    /**
     * 加载Lucky实例配置
     * 需求: 2.1, 2.2
     */
    async loadConfig() {
        try {
            const response = await fetch(`../server/api/lucky-config.php?id=${this.luckyId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load Lucky configuration');
            }
            
            // API返回的是 instance 字段
            this.config = data.instance;
            console.log('Lucky配置加载成功:', this.config);
            return this.config;
        } catch (error) {
            console.error('加载Lucky配置失败:', error);
            throw error;
        }
    }

    /**
     * 加载奖品列表
     * 需求: 3.1, 3.2, 3.5
     */
    async loadPrizes() {
        try {
            const response = await fetch(`../server/api/prizes.php?lucky_id=${this.luckyId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load prizes');
            }
            
            this.prizes = data.prizes || [];
            console.log('奖品列表加载成功:', this.prizes.length, '个奖品');
            return this.prizes;
        } catch (error) {
            console.error('加载奖品列表失败:', error);
            throw error;
        }
    }

    /**
     * 加载抽奖价格配置
     * 需求: 4.1, 4.2
     */
    async loadDrawPrices() {
        try {
            const response = await fetch(`../server/api/draw-prices.php?lucky_id=${this.luckyId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load draw prices');
            }
            
            // API返回的prices格式: { single: {price, button_name}, triple: {...}, quintuple: {...} }
            const pricesData = data.prices || {};
            this.prices = {
                single: pricesData.single?.price || 10,   // 默认值
                triple: pricesData.triple?.price || 30,   // 默认值
                quintuple: pricesData.quintuple?.price || 50 // 默认值
            };
            
            // 保存按钮名称（如果需要）
            this.buttonNames = {
                single: pricesData.single?.button_name || '单抽',
                triple: pricesData.triple?.button_name || '三连抽',
                quintuple: pricesData.quintuple?.button_name || '五连抽'
            };
            
            console.log('抽奖价格加载成功:', this.prices);
            return this.prices;
        } catch (error) {
            console.error('加载抽奖价格失败:', error);
            // 使用默认价格
            console.warn('使用默认价格配置');
            return this.prices;
        }
    }

    /**
     * 应用背景图
     * 需求: 2.2
     */
    applyBackgroundImage() {
        if (this.config && this.config.background_url) {
            const backgroundUrl = this.config.background_url.startsWith('../') 
                ? this.config.background_url 
                : `../${this.config.background_url}`;
            
            document.body.style.backgroundImage = `url('${backgroundUrl}')`;
            console.log('背景图已应用:', backgroundUrl);
        }
    }

    /**
     * 应用显示名称和描述
     * 需求: 2.2
     */
    applyDisplayName() {
        if (this.config) {
            // 更新页面标题
            const headerTitle = document.querySelector('.game-header h2');
            if (headerTitle && this.config.display_name) {
                headerTitle.textContent = this.config.display_name;
            }
            
            // 更新页面描述
            const headerDesc = document.querySelector('.game-header p');
            if (headerDesc && this.config.description) {
                headerDesc.textContent = this.config.description;
            }
            
            console.log('显示名称已应用:', this.config.display_name);
        }
    }

    /**
     * 渲染奖品列表
     * 需求: 3.2, 3.3
     */
    renderPrizes() {
        const prizeGrid = document.getElementById('prizeGrid');
        if (!prizeGrid) {
            console.warn('找不到奖品网格元素');
            return;
        }
        
        if (this.prizes.length === 0) {
            prizeGrid.innerHTML = '<p class="neon-text">暂无奖品配置</p>';
            return;
        }
        
        prizeGrid.innerHTML = this.prizes.map(prize => {
            const icon = prize.icon || '🎁';
            const name = prize.name || '未知奖品';
            const value = prize.value || 0;
            const rarity = prize.rarity || 'common';
            
            // 如果有图片URL，使用图片；否则使用图标
            const displayContent = prize.image_url 
                ? `<img src="../${prize.image_url}" alt="${name}" class="prize-image">`
                : `<div class="prize-icon">${icon}</div>`;
            
            return `
                <div class="prize-item ${rarity}">
                    <span class="prize-rarity">${rarity}</span>
                    <div class="prize-display">
                        ${displayContent}
                    </div>
                    <div class="prize-name">${name}</div>
                    <div class="prize-value">${value.toFixed(2)}</div>
                </div>
            `;
        }).join('');
        
        console.log('奖品列表已渲染:', this.prizes.length, '个奖品');
    }

    /**
     * 更新抽奖按钮价格
     * 需求: 4.2
     */
    updateDrawButtons() {
        // 更新按钮显示的价格
        const btnCost = document.getElementById('btnCost');
        if (btnCost) {
            // 默认显示单抽价格
            btnCost.textContent = `(消耗: ${this.prices.single})`;
        }
        
        // 如果页面有全局变量drawPrices，也更新它
        if (typeof window.drawPrices !== 'undefined') {
            window.drawPrices = this.prices;
        }
        
        console.log('抽奖按钮价格已更新');
    }

    /**
     * 初始化页面
     * 主入口函数
     */
    async initialize() {
        try {
            // 1. 获取Lucky实例ID
            this.luckyId = this.getLuckyInstanceId();
            console.log('当前Lucky实例ID:', this.luckyId);
            
            // 将ID暴露给全局作用域，供其他脚本使用
            window.LUCKY_INSTANCE_ID = this.luckyId;
            
            // 2. 并行加载所有数据
            const [config, prizes, prices] = await Promise.all([
                this.loadConfig(),
                this.loadPrizes(),
                this.loadDrawPrices()
            ]);
            
            // 3. 应用配置到页面
            this.applyBackgroundImage();
            this.applyDisplayName();
            
            // 4. 渲染奖品列表
            this.renderPrizes();
            
            // 5. 更新抽奖按钮
            this.updateDrawButtons();
            
            // 6. 将数据暴露给全局作用域，供其他脚本使用
            window.luckyConfig = this.config;
            window.luckyPrizes = this.prizes;
            window.luckyPrices = this.prices;
            
            console.log('Lucky页面初始化完成');
            
            // 触发自定义事件，通知其他模块数据已加载
            window.dispatchEvent(new CustomEvent('luckyDataLoaded', {
                detail: {
                    luckyId: this.luckyId,
                    config: this.config,
                    prizes: this.prizes,
                    prices: this.prices
                }
            }));
            
            return {
                success: true,
                luckyId: this.luckyId,
                config: this.config,
                prizes: this.prizes,
                prices: this.prices
            };
            
        } catch (error) {
            console.error('Lucky页面初始化失败:', error);
            
            // 显示错误消息
            this.showError(error.message || '加载失败，请刷新页面重试');
            
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * 显示错误消息
     */
    showError(message) {
        const gameHeader = document.querySelector('.game-header');
        if (gameHeader) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.cssText = `
                background: rgba(239, 68, 68, 0.2);
                border: 2px solid #ef4444;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
                color: #fff;
                text-align: center;
            `;
            errorDiv.innerHTML = `
                <h3 style="color: #ef4444; margin-bottom: 10px;">⚠️ 加载失败</h3>
                <p>${message}</p>
                <button onclick="location.reload()" style="
                    margin-top: 15px;
                    padding: 10px 20px;
                    background: #ef4444;
                    border: none;
                    border-radius: 5px;
                    color: #fff;
                    cursor: pointer;
                    font-weight: bold;
                ">刷新页面</button>
            `;
            gameHeader.appendChild(errorDiv);
        }
    }
}

// 创建全局实例
window.luckyLoader = new LuckyLoader();

// 导出供其他模块使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LuckyLoader;
}
