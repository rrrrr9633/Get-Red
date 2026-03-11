/**
 * 公告功能
 */

let announcementVersion = 0;
let checkInterval = null;

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function() {
    checkUnreadAnnouncements();
    startVersionCheck();
});

/**
 * 检查未读公告数量
 */
async function checkUnreadAnnouncements() {
    try {
        const response = await fetch('../server/api/announcement.php?action=get_unread_count');
        const result = await response.json();
        
        if (result.success) {
            const count = result.unread_count;
            const badge = document.getElementById('announcementBadge');
            const btn = document.getElementById('announcementBtn');
            
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
                btn.classList.add('has-unread');
            } else {
                badge.style.display = 'none';
                btn.classList.remove('has-unread');
            }
        }
    } catch (error) {
        console.error('检查未读公告失败:', error);
    }
}

/**
 * 开始检查公告版本（每30秒检查一次）
 */
function startVersionCheck() {
    // 先获取当前版本
    getCurrentVersion();
    
    // 每30秒检查一次
    checkInterval = setInterval(async () => {
        try {
            const response = await fetch('../server/api/announcement.php?action=get_announcement_version');
            const result = await response.json();
            
            if (result.success && result.version > announcementVersion) {
                // 有新公告，更新版本号并检查未读数量
                announcementVersion = result.version;
                checkUnreadAnnouncements();
            }
        } catch (error) {
            console.error('检查公告版本失败:', error);
        }
    }, 30000);
}

/**
 * 获取当前公告版本
 */
async function getCurrentVersion() {
    try {
        const response = await fetch('../server/api/announcement.php?action=get_announcement_version');
        const result = await response.json();
        
        if (result.success) {
            announcementVersion = result.version;
        }
    } catch (error) {
        console.error('获取公告版本失败:', error);
    }
}

/**
 * 打开公告弹窗
 */
async function openAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.style.display = 'flex';
    
    // 加载公告列表
    await loadAnnouncements();
    
    // 标记所有公告为已读
    await markAllAsRead();
}

/**
 * 关闭公告弹窗
 */
function closeAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.style.display = 'none';
}

/**
 * 加载公告列表
 */
async function loadAnnouncements() {
    const listContainer = document.getElementById('announcementList');
    listContainer.innerHTML = '<div class="loading">加载中...</div>';
    
    try {
        const response = await fetch('../server/api/announcement.php?action=get_announcements');
        const result = await response.json();
        
        if (result.success) {
            const announcements = result.announcements;
            
            if (announcements.length === 0) {
                listContainer.innerHTML = '<div class="no-announcements">暂无公告</div>';
                return;
            }
            
            listContainer.innerHTML = announcements.map(announcement => `
                <div class="announcement-item ${announcement.is_read ? 'read' : 'unread'}">
                    <div class="announcement-header">
                        <h3 class="announcement-title">${escapeHtml(announcement.title)}</h3>
                        <span class="announcement-time">${formatTime(announcement.created_at)}</span>
                    </div>
                    <div class="announcement-content">${escapeHtml(announcement.content).replace(/\n/g, '<br>')}</div>
                </div>
            `).join('');
        } else {
            listContainer.innerHTML = '<div class="error">加载失败，请稍后再试</div>';
        }
    } catch (error) {
        console.error('加载公告失败:', error);
        listContainer.innerHTML = '<div class="error">加载失败，请稍后再试</div>';
    }
}

/**
 * 标记所有公告为已读
 */
async function markAllAsRead() {
    try {
        await fetch('../server/api/announcement.php?action=mark_as_read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                announcement_id: 0 // 0表示标记所有
            })
        });
        
        // 更新未读数量
        checkUnreadAnnouncements();
    } catch (error) {
        console.error('标记已读失败:', error);
    }
}

/**
 * 格式化时间
 */
function formatTime(timeString) {
    const date = new Date(timeString);
    const now = new Date();
    const diff = now - date;
    
    // 小于1分钟
    if (diff < 60000) {
        return '刚刚';
    }
    
    // 小于1小时
    if (diff < 3600000) {
        return Math.floor(diff / 60000) + '分钟前';
    }
    
    // 小于1天
    if (diff < 86400000) {
        return Math.floor(diff / 3600000) + '小时前';
    }
    
    // 小于7天
    if (diff < 604800000) {
        return Math.floor(diff / 86400000) + '天前';
    }
    
    // 超过7天显示具体日期
    return date.toLocaleDateString('zh-CN');
}

/**
 * HTML转义
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 页面卸载时清除定时器
window.addEventListener('beforeunload', function() {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
});
