// 主界面功能
class MainPageManager {
    constructor() {
        this.currentModule = null;
        this.init();
    }

    init() {
        this.initModuleHoverEffects();
        this.initExpansionArea();
        this.addParticleEffects();
    }

    initModuleHoverEffects() {
        const modules = document.querySelectorAll('.module');
        
        modules.forEach(module => {
            module.addEventListener('mouseenter', (e) => {
                this.createRippleEffect(e);
                this.addHoverGlow(module);
            });
            
            module.addEventListener('mouseleave', () => {
                this.removeHoverGlow(module);
            });
            
            // 添加点击动画
            module.addEventListener('click', (e) => {
                this.createClickEffect(e);
            });
        });
    }

    createRippleEffect(e) {
        const module = e.currentTarget;
        const rect = module.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            width: 20px;
            height: 20px;
            left: ${x - 10}px;
            top: ${y - 10}px;
            pointer-events: none;
            animation: rippleExpand 0.6s ease-out forwards;
            z-index: 5;
        `;
        
        module.style.position = 'relative';
        module.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }

    createClickEffect(e) {
        const module = e.currentTarget;
        const rect = module.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        // 创建多个粒子效果
        for (let i = 0; i < 8; i++) {
            const particle = document.createElement('div');
            const angle = (i / 8) * Math.PI * 2;
            const velocity = 50 + Math.random() * 30;
            const size = 4 + Math.random() * 4;
            
            particle.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                background: #ffd700;
                border-radius: 50%;
                left: ${x}px;
                top: ${y}px;
                pointer-events: none;
                z-index: 10;
                box-shadow: 0 0 ${size * 2}px #ffd700;
            `;
            
            module.appendChild(particle);
            
            // 动画
            const endX = x + Math.cos(angle) * velocity;
            const endY = y + Math.sin(angle) * velocity;
            
            particle.animate([
                { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                { transform: `translate(${endX - x}px, ${endY - y}px) scale(0)`, opacity: 0 }
            ], {
                duration: 800,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            }).onfinish = () => {
                if (particle.parentNode) {
                    particle.parentNode.removeChild(particle);
                }
            };
        }
    }

    addHoverGlow(module) {
        module.style.boxShadow = `
            0 20px 40px rgba(0, 0, 0, 0.4),
            0 0 80px rgba(255, 215, 0, 0.3),
            0 0 120px rgba(255, 215, 0, 0.1)
        `;
    }

    removeHoverGlow(module) {
        module.style.boxShadow = `
            0 10px 30px rgba(0, 0, 0, 0.3),
            0 0 50px rgba(255, 215, 0, 0.1)
        `;
    }

    initExpansionArea() {
        const expansionArea = document.querySelector('.expansion-area');
        if (!expansionArea) return;
        
        // 添加动态背景效果
        const createFloatingElement = () => {
            const element = document.createElement('div');
            element.style.cssText = `
                position: absolute;
                width: ${10 + Math.random() * 20}px;
                height: ${10 + Math.random() * 20}px;
                background: rgba(255, 215, 0, ${0.1 + Math.random() * 0.2});
                border-radius: 50%;
                left: ${Math.random() * 100}%;
                top: 100%;
                pointer-events: none;
                animation: floatUp ${5 + Math.random() * 5}s linear infinite;
            `;
            
            expansionArea.appendChild(element);
            
            setTimeout(() => {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
            }, 10000);
        };
        
        // 定期创建浮动元素
        setInterval(createFloatingElement, 2000);
    }

    addParticleEffects() {
        // 为页面添加背景粒子效果
        const createBackgroundParticle = () => {
            const particle = document.createElement('div');
            const colors = ['#ff00ff', '#00ffff', '#ffff00', '#ff0080'];
            const color = colors[Math.floor(Math.random() * colors.length)];
            
            particle.style.cssText = `
                position: fixed;
                width: ${2 + Math.random() * 4}px;
                height: ${2 + Math.random() * 4}px;
                background: ${color};
                border-radius: 50%;
                left: ${Math.random() * 100}vw;
                top: -10px;
                pointer-events: none;
                z-index: 1;
                box-shadow: 0 0 ${4 + Math.random() * 8}px ${color};
                animation: particleFall ${8 + Math.random() * 4}s linear infinite;
            `;
            
            document.body.appendChild(particle);
            
            setTimeout(() => {
                if (particle.parentNode) {
                    particle.parentNode.removeChild(particle);
                }
            }, 12000);
        };
        
        // 每隔一段时间创建背景粒子
        setInterval(createBackgroundParticle, 3000);
    }

    openModule(moduleType) {
        this.currentModule = moduleType;
        
        // 根据不同模块类型执行不同操作
        switch (moduleType) {
            case 'lucky':
                this.openLuckyModule();
                break;
            case 'prize':
                this.openPrizeModule();
                break;
            case 'wheel':
                this.openWheelModule();
                break;
            case 'checkin':
                this.openCheckinModule();
                break;
            default:
                console.log('Unknown module:', moduleType);
        }
    }

    openLuckyModule() {
        // 切换到抽奖页面
        document.getElementById('main-page').classList.remove('active');
        document.getElementById('lottery-page').classList.add('active');
        
        // 如果老虎机管理器存在，重置状态
        if (window.slotMachine) {
            window.slotMachine.reset();
        }
    }

    openPrizeModule() {
        this.showModuleMessage('惊喜大奖模块即将开放，敬请期待！', 'info');
    }

    openWheelModule() {
        this.showModuleMessage('幸运转盘模块即将开放，敬请期待！', 'info');
    }

    openCheckinModule() {
        this.showModuleMessage('每日签到模块即将开放，敬请期待！', 'info');
    }

    showModuleMessage(message, type = 'info') {
        // 创建模块消息弹窗
        const modal = document.createElement('div');
        modal.className = 'module-modal';
        modal.innerHTML = `
            <div class="module-modal-content">
                <div class="module-modal-header">
                    <h3 class="neon-text gold">提示</h3>
                    <button class="modal-close" onclick="this.parentElement.parentElement.parentElement.remove()">×</button>
                </div>
                <div class="module-modal-body">
                    <p class="neon-text">${message}</p>
                </div>
                <div class="module-modal-footer">
                    <button class="modal-btn neon-btn" onclick="this.parentElement.parentElement.parentElement.remove()">
                        <span>确定</span>
                    </button>
                </div>
            </div>
        `;
        
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: modalFadeIn 0.3s ease;
        `;
        
        document.body.appendChild(modal);
        
        // 点击背景关闭
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // 自动关闭
        setTimeout(() => {
            if (modal.parentNode) {
                modal.remove();
            }
        }, 5000);
    }

    backToMain() {
        document.getElementById('lottery-page').classList.remove('active');
        document.getElementById('main-page').classList.add('active');
        this.currentModule = null;
    }
}

// 全局函数
function openModule(moduleType) {
    if (window.mainPageManager) {
        window.mainPageManager.openModule(moduleType);
    }
}

function backToMain() {
    if (window.mainPageManager) {
        window.mainPageManager.backToMain();
    }
}

// 添加必要的CSS动画
const additionalStyles = `
    @keyframes rippleExpand {
        to {
            width: 200px;
            height: 200px;
            opacity: 0;
        }
    }
    
    @keyframes particleFall {
        to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
    
    @keyframes floatUp {
        to {
            transform: translateY(-200px);
            opacity: 0;
        }
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.8); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .module-modal-content {
        background: rgba(20, 20, 40, 0.95);
        border-radius: 15px;
        padding: 30px;
        max-width: 400px;
        width: 90vw;
        border: 2px solid #ffd700;
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.3);
        backdrop-filter: blur(10px);
    }
    
    .module-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 215, 0, 0.3);
        padding-bottom: 10px;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: #ff6b6b;
        font-size: 24px;
        cursor: pointer;
        padding: 5px;
        transition: all 0.3s ease;
    }
    
    .modal-close:hover {
        color: #ff4757;
        transform: rotate(90deg);
    }
    
    .module-modal-body {
        margin: 20px 0;
        text-align: center;
    }
    
    .module-modal-footer {
        text-align: center;
        margin-top: 20px;
    }
    
    .modal-btn {
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 16px;
    }
`;

// 添加样式到页面
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// 初始化主界面管理器
document.addEventListener('DOMContentLoaded', () => {
    window.mainPageManager = new MainPageManager();
});
