// 老虎机抽奖系统
class SlotMachine {
    constructor() {
        this.isSpinning = false;
        this.reels = [];
        this.symbols = ['🍎', '🍊', '🍋', '🍇', '🍓', '💎', '⭐', '🎁'];
        this.winningCombinations = {
            '💎💎💎': { prize: '超级大奖', probability: 0.01 },
            '⭐⭐⭐': { prize: '特等奖', probability: 0.02 },
            '🎁🎁🎁': { prize: '一等奖', probability: 0.05 },
            '🍓🍓🍓': { prize: '二等奖', probability: 0.08 },
            '🍇🍇🍇': { prize: '三等奖', probability: 0.12 }
        };
        this.init();
    }

    init() {
        // 初始化转轮
        this.initReels();
        // 绑定事件
        this.bindEvents();
        // 初始化结果显示
        this.updateResultDisplay('准备开始你的幸运之旅吧！');
    }

    initReels() {
        for (let i = 1; i <= 3; i++) {
            const reel = document.getElementById(`reel${i}`);
            if (reel) {
                this.reels.push(reel);
                this.populateReel(reel);
            }
        }
    }

    populateReel(reel) {
        // 清空现有内容
        reel.innerHTML = '';
        
        // 创建足够多的符号项目以支持平滑滚动
        const totalItems = this.symbols.length * 3; // 每个符号重复3次
        
        for (let i = 0; i < totalItems; i++) {
            const item = document.createElement('div');
            item.className = 'slot-item';
            item.textContent = this.symbols[i % this.symbols.length];
            reel.appendChild(item);
        }
    }

    bindEvents() {
        // 监听窗口大小变化，调整动画
        window.addEventListener('resize', () => {
            this.adjustAnimationForScreenSize();
        });
    }

    adjustAnimationForScreenSize() {
        const isMobile = window.innerWidth <= 768;
        const itemHeight = isMobile ? 80 : 120;
        
        // 更新CSS变量（如果使用CSS变量的话）
        document.documentElement.style.setProperty('--slot-item-height', `${itemHeight}px`);
    }

    async startSpin() {
        if (this.isSpinning) return;
        
        this.isSpinning = true;
        const spinBtn = document.getElementById('spin-btn');
        spinBtn.disabled = true;
        spinBtn.querySelector('span').textContent = '抽奖中...';
        
        // 清除之前的中奖状态
        this.clearWinningState();
        
        // 更新结果显示
        this.updateResultDisplay('抽奖进行中，请稍等...');
        
        try {
            // 开始所有转轮的旋转
            await this.spinAllReels();
            
            // 确定最终结果
            const result = this.determineResult();
            
            // 停止转轮并显示结果
            await this.stopReelsWithResult(result.symbols);
            
            // 显示结果
            this.showResult(result);
            
        } catch (error) {
            console.error('抽奖过程中出错:', error);
            this.updateResultDisplay('抽奖出现问题，请重试');
        } finally {
            this.isSpinning = false;
            spinBtn.disabled = false;
            spinBtn.querySelector('span').textContent = '开始抽奖';
        }
    }

    async spinAllReels() {
        const spinPromises = this.reels.map((reel, index) => {
            return this.startReelSpin(reel, index);
        });
        
        await Promise.all(spinPromises);
    }

    startReelSpin(reel, index) {
        return new Promise((resolve) => {
            // 添加旋转类和不同的速度
            reel.classList.add('spinning', `speed-${(index % 3) + 1}`);
            
            // 添加音效（如果需要）
            this.playSpinSound();
            
            // 基础旋转时间 + 随机延迟
            const spinDuration = 1000 + (index * 300) + Math.random() * 500;
            
            setTimeout(() => {
                resolve();
            }, spinDuration);
        });
    }

    determineResult() {
        // 根据概率确定是否中奖
        const random = Math.random();
        let cumulativeProbability = 0;
        
        for (const [combination, data] of Object.entries(this.winningCombinations)) {
            cumulativeProbability += data.probability;
            if (random < cumulativeProbability) {
                return {
                    symbols: combination.split(''),
                    prize: data.prize,
                    isWin: true
                };
            }
        }
        
        // 未中奖，随机生成不匹配的组合
        return {
            symbols: this.generateRandomCombination(),
            prize: null,
            isWin: false
        };
    }

    generateRandomCombination() {
        const symbols = [];
        // 确保不是中奖组合
        do {
            symbols.length = 0;
            for (let i = 0; i < 3; i++) {
                symbols.push(this.symbols[Math.floor(Math.random() * this.symbols.length)]);
            }
        } while (this.isWinningCombination(symbols));
        
        return symbols;
    }

    isWinningCombination(symbols) {
        const combination = symbols.join('');
        return this.winningCombinations.hasOwnProperty(combination);
    }

    async stopReelsWithResult(targetSymbols) {
        const stopPromises = this.reels.map((reel, index) => {
            return this.stopReel(reel, targetSymbols[index], index);
        });
        
        await Promise.all(stopPromises);
    }

    stopReel(reel, targetSymbol, index) {
        return new Promise((resolve) => {
            // 移除旋转类
            reel.classList.remove('spinning', `speed-${(index % 3) + 1}`);
            
            // 找到目标符号的位置
            const items = reel.querySelectorAll('.slot-item');
            let targetIndex = -1;
            
            // 检查items是否存在且有元素
            if (!items || items.length === 0) {
                console.warn('No items found in reel');
                resolve();
                return;
            }
            
            for (let i = 0; i < items.length; i++) {
                if (items[i].textContent === targetSymbol) {
                    targetIndex = i;
                    break;
                }
            }
            
            if (targetIndex !== -1) {
                // 计算需要滚动的距离
                const itemHeight = items[0].offsetHeight;
                const scrollDistance = targetIndex * itemHeight;
                
                // 应用变换以显示目标符号
                reel.style.transform = `translateY(-${scrollDistance}px)`;
                
                // 添加停止动画
                reel.classList.add('stopping');
                
                setTimeout(() => {
                    reel.classList.remove('stopping');
                    resolve();
                }, 500);
            } else {
                resolve();
            }
        });
    }

    showResult(result) {
        if (result.isWin) {
            this.showWinResult(result);
        } else {
            this.showLoseResult();
        }
    }

    showWinResult(result) {
        // 添加中奖动画效果
        this.reels.forEach(reel => {
            reel.classList.add('winning');
        });
        
        // 更新结果显示
        this.updateResultDisplay(`🎉 恭喜中奖！获得${result.prize}！ 🎉`, 'win');
        
        // 播放中奖音效
        this.playWinSound();
        
        // 创建庆祝效果
        this.createCelebrationEffect();
        
        // 显示成功模态框
        setTimeout(() => {
            this.showSuccessModal(`恭喜您获得${result.prize}！`);
        }, 1000);
    }

    showLoseResult() {
        this.updateResultDisplay('很遗憾，这次没有中奖，再试一次吧！', 'lose');
        
        // 播放失败音效
        this.playLoseSound();
    }

    updateResultDisplay(message, type = 'normal') {
        const resultDisplay = document.getElementById('result-display');
        if (resultDisplay) {
            resultDisplay.innerHTML = `<div class="neon-text">${message}</div>`;
            resultDisplay.className = `result-display ${type}`;
        }
    }

    createCelebrationEffect() {
        // 创建彩花效果
        const colors = ['#ffd700', '#ff00ff', '#00ffff', '#ff0080', '#ffff00'];
        
        for (let i = 0; i < 50; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.cssText = `
                    position: fixed;
                    left: ${Math.random() * 100}vw;
                    top: -10px;
                    width: ${4 + Math.random() * 8}px;
                    height: ${4 + Math.random() * 8}px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    z-index: 10000;
                    pointer-events: none;
                    animation: confettiFall ${2 + Math.random() * 2}s linear forwards;
                `;
                
                document.body.appendChild(confetti);
                
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 4000);
            }, i * 50);
        }
    }

    showSuccessModal(message) {
        const modal = document.getElementById('success-modal');
        if (modal) {
            modal.querySelector('.modal-message').textContent = message;
            modal.style.display = 'flex';
            modal.style.animation = 'modalFadeIn 0.5s ease forwards';
            
            setTimeout(() => {
                modal.style.animation = 'modalFadeOut 0.5s ease forwards';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 500);
            }, 3000);
        }
    }

    clearWinningState() {
        this.reels.forEach(reel => {
            reel.classList.remove('winning');
            reel.style.transform = '';
        });
    }

    reset() {
        if (this.isSpinning) return;
        
        this.clearWinningState();
        this.updateResultDisplay('准备开始你的幸运之旅吧！');
        
        // 重新初始化转轮位置
        this.reels.forEach(reel => {
            reel.style.transform = '';
        });
    }

    // 音效方法（可选实现）
    playSpinSound() {
        // 可以添加音效播放逻辑
        // const audio = new Audio('sounds/spin.mp3');
        // audio.play().catch(e => console.log('音效播放失败'));
    }

    playWinSound() {
        // 可以添加中奖音效
        // const audio = new Audio('sounds/win.mp3');
        // audio.play().catch(e => console.log('音效播放失败'));
    }

    playLoseSound() {
        // 可以添加失败音效
        // const audio = new Audio('sounds/lose.mp3');
        // audio.play().catch(e => console.log('音效播放失败'));
    }
}

// 全局函数
function startSpin() {
    if (window.slotMachine) {
        window.slotMachine.startSpin();
    }
}

// 添加额外的样式
const slotStyles = `
    @keyframes modalFadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.8); }
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        justify-content: center;
        align-items: center;
        z-index: 10000;
    }
    
    .modal-content {
        background: rgba(20, 20, 40, 0.95);
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        border: 3px solid #ffd700;
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.5);
        backdrop-filter: blur(15px);
    }
    
    .success-icon {
        font-size: 48px;
        color: #00ff00;
        margin-bottom: 20px;
        text-shadow: 0 0 20px #00ff00;
        animation: successPulse 1s ease-in-out infinite;
    }
    
    @keyframes successPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .modal-message {
        font-size: 24px;
        margin: 0;
    }
    
    /* 改进的转轮滚动效果 */
    .slot-reel.spinning .slot-item {
        animation: slotSpinSmooth 0.1s linear infinite;
    }
    
    @keyframes slotSpinSmooth {
        0% { transform: translateY(0); }
        100% { transform: translateY(-120px); }
    }
`;

const slotStyleSheet = document.createElement('style');
slotStyleSheet.textContent = slotStyles;
document.head.appendChild(slotStyleSheet);

// 初始化老虎机
document.addEventListener('DOMContentLoaded', () => {
    window.slotMachine = new SlotMachine();
});
