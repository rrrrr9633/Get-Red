// 特效管理系统
class EffectsManager {
    constructor() {
        this.particles = [];
        this.animations = [];
        this.init();
    }

    init() {
        this.createBackgroundEffects();
        this.initInteractionEffects();
        this.startAnimationLoop();
    }

    createBackgroundEffects() {
        // 创建动态背景粒子
        this.createFloatingParticles();
        // 霓虹光线效果已禁用
        // this.createNeonRays();
        // 创建星空效果
        this.createStarField();
    }

    createFloatingParticles() {
        const particleContainer = document.createElement('div');
        particleContainer.className = 'floating-particles';
        particleContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        `;
        document.body.appendChild(particleContainer);

        // 创建浮动粒子
        for (let i = 0; i < 30; i++) {
            setTimeout(() => {
                this.createFloatingParticle(particleContainer);
            }, i * 200);
        }

        // 定期补充粒子
        setInterval(() => {
            if (Math.random() > 0.7) {
                this.createFloatingParticle(particleContainer);
            }
        }, 2000);
    }

    createFloatingParticle(container) {
        const particle = document.createElement('div');
        const colors = ['#ff00ff', '#00ffff', '#ffff00', '#ff0080', '#8a2be2'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        const size = 2 + Math.random() * 6;
        
        particle.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background: ${color};
            border-radius: 50%;
            left: ${Math.random() * 100}vw;
            top: 100vh;
            box-shadow: 0 0 ${size * 2}px ${color};
            animation: floatUpAndFade ${8 + Math.random() * 4}s linear forwards;
        `;
        
        container.appendChild(particle);
        
        // 清理粒子
        setTimeout(() => {
            if (particle.parentNode) {
                particle.parentNode.removeChild(particle);
            }
        }, 12000);
    }

    createNeonRays() {
        const rayContainer = document.createElement('div');
        rayContainer.className = 'neon-rays';
        rayContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        `;
        document.body.appendChild(rayContainer);

        // 创建霓虹光线
        for (let i = 0; i < 8; i++) {
            const ray = document.createElement('div');
            const colors = ['#ff00ff', '#00ffff', '#ffd700'];
            const color = colors[i % colors.length];
            
            ray.style.cssText = `
                position: absolute;
                width: 2px;
                height: 200px;
                background: linear-gradient(to bottom, transparent, ${color}, transparent);
                left: ${(i / 8) * 100}vw;
                top: -200px;
                transform-origin: center bottom;
                animation: rayMove ${6 + Math.random() * 3}s ease-in-out infinite;
                animation-delay: ${i * 0.5}s;
                opacity: 0.3;
            `;
            
            rayContainer.appendChild(ray);
        }
    }

    createStarField() {
        const starContainer = document.createElement('div');
        starContainer.className = 'star-field';
        starContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 1;
        `;
        document.body.appendChild(starContainer);

        // 创建星星
        for (let i = 0; i < 100; i++) {
            const star = document.createElement('div');
            const size = 1 + Math.random() * 3;
            
            star.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                background: #ffffff;
                border-radius: 50%;
                left: ${Math.random() * 100}vw;
                top: ${Math.random() * 100}vh;
                animation: starTwinkle ${2 + Math.random() * 3}s ease-in-out infinite;
                animation-delay: ${Math.random() * 2}s;
                box-shadow: 0 0 ${size * 2}px #ffffff;
            `;
            
            starContainer.appendChild(star);
        }
    }

    initInteractionEffects() {
        // 鼠标移动效果
        this.initMouseTrail();
        // 点击波纹效果已禁用
        // this.initClickRipples();
        // 元素悬停效果
        this.initHoverEffects();
    }

    initMouseTrail() {
        let mouseX = 0, mouseY = 0;
        const trail = [];
        const trailLength = 10;

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;

            // 创建轨迹点
            const trailPoint = {
                x: mouseX,
                y: mouseY,
                life: 1
            };

            trail.push(trailPoint);
            if (trail.length > trailLength) {
                trail.shift();
            }

            this.updateMouseTrail(trail);
        });
    }

    updateMouseTrail(trail) {
        // 清除旧的轨迹元素
        const oldTrails = document.querySelectorAll('.mouse-trail-point');
        oldTrails.forEach(el => el.remove());

        // 创建新的轨迹点
        trail.forEach((point, index) => {
            const trailEl = document.createElement('div');
            const opacity = (index / trail.length) * 0.5;
            const size = (index / trail.length) * 10;

            trailEl.className = 'mouse-trail-point';
            trailEl.style.cssText = `
                position: fixed;
                left: ${point.x - size/2}px;
                top: ${point.y - size/2}px;
                width: ${size}px;
                height: ${size}px;
                background: radial-gradient(circle, rgba(255, 0, 255, ${opacity}) 0%, transparent 70%);
                border-radius: 50%;
                pointer-events: none;
                z-index: 9999;
                transition: all 0.1s ease;
            `;

            document.body.appendChild(trailEl);

            // 自动清理
            setTimeout(() => {
                if (trailEl.parentNode) {
                    trailEl.remove();
                }
            }, 200);
        });
    }

    initClickRipples() {
        document.addEventListener('click', (e) => {
            this.createClickRipple(e.clientX, e.clientY);
        });
    }

    createClickRipple(x, y) {
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: fixed;
            left: ${x - 25}px;
            top: ${y - 25}px;
            width: 50px;
            height: 50px;
            border: 2px solid #ffd700;
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: clickRippleExpand 0.6s ease-out forwards;
        `;

        document.body.appendChild(ripple);

        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }

    initHoverEffects() {
        // 为所有按钮添加悬停粒子效果
        const buttons = document.querySelectorAll('.neon-btn, .module-btn, .auth-btn');
        
        buttons.forEach(button => {
            button.addEventListener('mouseenter', (e) => {
                this.createHoverParticles(e.target);
            });
        });
    }

    createHoverParticles(element) {
        const rect = element.getBoundingClientRect();
        const particleCount = 6;

        for (let i = 0; i < particleCount; i++) {
            setTimeout(() => {
                const particle = document.createElement('div');
                const x = rect.left + Math.random() * rect.width;
                const y = rect.top + Math.random() * rect.height;
                const size = 2 + Math.random() * 4;

                particle.style.cssText = `
                    position: fixed;
                    left: ${x}px;
                    top: ${y}px;
                    width: ${size}px;
                    height: ${size}px;
                    background: #ffd700;
                    border-radius: 50%;
                    pointer-events: none;
                    z-index: 9999;
                    animation: hoverParticleFloat 1s ease-out forwards;
                    box-shadow: 0 0 ${size * 2}px #ffd700;
                `;

                document.body.appendChild(particle);

                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, 1000);
            }, i * 50);
        }
    }

    startAnimationLoop() {
        // 启动动画循环
        const animate = () => {
            this.updateAnimations();
            requestAnimationFrame(animate);
        };
        animate();
    }

    updateAnimations() {
        // 更新所有动画
        this.animations = this.animations.filter(animation => {
            animation.update();
            return !animation.isComplete;
        });
    }

    // 创建烟花效果
    createFireworks(x, y) {
        const colors = ['#ff00ff', '#00ffff', '#ffff00', '#ff0080', '#8a2be2'];
        const particleCount = 20;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            const color = colors[Math.floor(Math.random() * colors.length)];
            const angle = (i / particleCount) * Math.PI * 2;
            const velocity = 50 + Math.random() * 100;
            const size = 3 + Math.random() * 5;

            particle.style.cssText = `
                position: fixed;
                left: ${x}px;
                top: ${y}px;
                width: ${size}px;
                height: ${size}px;
                background: ${color};
                border-radius: 50%;
                pointer-events: none;
                z-index: 10000;
                box-shadow: 0 0 ${size * 2}px ${color};
            `;

            document.body.appendChild(particle);

            // 动画粒子
            const endX = x + Math.cos(angle) * velocity;
            const endY = y + Math.sin(angle) * velocity;

            particle.animate([
                {
                    transform: 'translate(0, 0) scale(1)',
                    opacity: 1
                },
                {
                    transform: `translate(${endX - x}px, ${endY - y}px) scale(0)`,
                    opacity: 0
                }
            ], {
                duration: 1000 + Math.random() * 500,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            }).onfinish = () => {
                if (particle.parentNode) {
                    particle.parentNode.removeChild(particle);
                }
            };
        }
    }

    // 创建文字飞入效果
    createTextFlyIn(text, x, y, color = '#ffd700') {
        const textEl = document.createElement('div');
        textEl.textContent = text;
        textEl.style.cssText = `
            position: fixed;
            left: ${x}px;
            top: ${y}px;
            color: ${color};
            font-size: 24px;
            font-weight: bold;
            pointer-events: none;
            z-index: 10000;
            text-shadow: 0 0 10px ${color};
            transform: translateY(50px);
            opacity: 0;
        `;

        document.body.appendChild(textEl);

        textEl.animate([
            {
                transform: 'translateY(50px)',
                opacity: 0
            },
            {
                transform: 'translateY(0)',
                opacity: 1
            },
            {
                transform: 'translateY(-50px)',
                opacity: 0
            }
        ], {
            duration: 2000,
            easing: 'ease-out'
        }).onfinish = () => {
            if (textEl.parentNode) {
                textEl.parentNode.removeChild(textEl);
            }
        };
    }
}

// 添加特效相关的CSS动画
const effectsStyles = `
    @keyframes floatUpAndFade {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 0.8;
        }
        100% {
            transform: translateY(-100vh) rotate(360deg);
            opacity: 0;
        }
    }
    
    @keyframes rayMove {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 0;
        }
        50% {
            opacity: 0.6;
        }
        100% {
            transform: translateY(100vh) rotate(180deg);
            opacity: 0;
        }
    }
    
    @keyframes starTwinkle {
        0%, 100% {
            opacity: 0.3;
            transform: scale(1);
        }
        50% {
            opacity: 1;
            transform: scale(1.2);
        }
    }
    
    @keyframes clickRippleExpand {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        100% {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes hoverParticleFloat {
        0% {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        100% {
            transform: translateY(-30px) scale(0);
            opacity: 0;
        }
    }
    
    /* 页面加载动画 */
    .page-transition {
        animation: pageSlideIn 0.5s ease-out forwards;
    }
    
    @keyframes pageSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* 元素脉冲效果 */
    .pulse-effect {
        animation: pulseGlow 2s ease-in-out infinite;
    }
    
    @keyframes pulseGlow {
        0%, 100% {
            box-shadow: 0 0 5px currentColor;
        }
        50% {
            box-shadow: 0 0 20px currentColor, 0 0 30px currentColor;
        }
    }
`;

const effectsStyleSheet = document.createElement('style');
effectsStyleSheet.textContent = effectsStyles;
document.head.appendChild(effectsStyleSheet);

// 全局效果函数
window.createFireworks = (x, y) => {
    if (window.effectsManager) {
        window.effectsManager.createFireworks(x, y);
    }
};

window.createTextFlyIn = (text, x, y, color) => {
    if (window.effectsManager) {
        window.effectsManager.createTextFlyIn(text, x, y, color);
    }
};

// 初始化特效管理器
document.addEventListener('DOMContentLoaded', () => {
    window.effectsManager = new EffectsManager();
    
    // 添加页面切换动画
    const pages = document.querySelectorAll('.page');
    pages.forEach(page => {
        page.classList.add('page-transition');
    });
});
