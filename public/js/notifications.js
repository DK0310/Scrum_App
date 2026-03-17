/**
 * Notifications Module - DriveNow
 * Handles: Real-time notifications, DB-backed, polling, marking as read, deletion
 */

let notificationsLoaded = false;
let notifPollInterval = null;

function toggleNotifications() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open') && !notificationsLoaded) {
        loadNotifications();
    }
}

async function loadNotifications() {
    try {
        const res = await fetch('/api/notifications.php?action=list&limit=20');
        const data = await res.json();
        if (data.success) {
            renderNotifications(data.notifications, data.unread_count);
            notificationsLoaded = true;
        }
    } catch (err) {
        console.error('Load notifications error:', err);
    }
}

function getNotifIcon(type) {
    const icons = { booking: '📋', payment: '💳', promo: '🎁', system: '⚙️', alert: '🔔' };
    return icons[type] || '🔔';
}

function renderNotifications(notifications, unreadCount) {
    const list = document.getElementById('notificationList');
    if (!list) return;

    updateNotifBadge(unreadCount);

    if (!notifications || notifications.length === 0) {
        list.innerHTML = '<li class="notification-item" style="justify-content:center;color:var(--gray-400);font-size:0.85rem;padding:24px;"><span>No notifications yet</span></li>';
        return;
    }

    list.innerHTML = notifications.map(n => `
        <li class="notification-item ${n.is_read === 't' || n.is_read === true ? '' : 'unread'}" data-id="${n.id}" onclick="markNotificationRead('${n.id}', this)">
            <div class="notification-icon ${n.type}">${getNotifIcon(n.type)}</div>
            <div class="notification-content">
                <div class="notification-title">${escapeHtml(n.title)}</div>
                <div class="notification-text">${escapeHtml(n.message)}</div>
                <div class="notification-time">${n.time_ago || ''}</div>
            </div>
            <button class="notif-delete-btn" onclick="event.stopPropagation();deleteNotification('${n.id}', this.closest('.notification-item'))" title="Delete" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:0.75rem;padding:4px;margin-left:4px;border-radius:4px;transition:var(--transition);" onmouseover="this.style.color='var(--danger)';this.style.background='var(--danger-50, #fef2f2)'" onmouseout="this.style.color='var(--gray-400)';this.style.background='none'">✕</button>
        </li>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function updateNotifBadge(count) {
    const badge = document.getElementById('notifCount');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = '';
    } else {
        badge.textContent = '0';
        badge.style.display = 'none';
    }
}

async function markNotificationRead(id, el) {
    if (el && !el.classList.contains('unread')) return;
    try {
        await fetch('/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark-read', notification_id: id })
        });
        if (el) el.classList.remove('unread');
        // Update badge
        const badge = document.getElementById('notifCount');
        if (badge) {
            const current = parseInt(badge.textContent || 0);
            updateNotifBadge(Math.max(0, current - 1));
        }
    } catch (err) {
        console.error('Mark read error:', err);
    }
}

async function markAllRead() {
    try {
        const res = await fetch('/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark-all-read' })
        });
        const data = await res.json();
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
            updateNotifBadge(0);
            showToast('All notifications marked as read.', 'success');
        }
    } catch (err) {
        console.error('Mark all read error:', err);
        showToast('Failed to mark notifications as read.', 'error');
    }
}

async function deleteNotification(id, el) {
    try {
        const res = await fetch('/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', notification_id: id })
        });
        const data = await res.json();
        if (data.success && el) {
            const wasUnread = el.classList.contains('unread');
            el.remove();
            if (wasUnread) {
                const badge = document.getElementById('notifCount');
                if (badge) updateNotifBadge(Math.max(0, parseInt(badge.textContent || 0) - 1));
            }
            // Check if list is now empty
            const list = document.getElementById('notificationList');
            if (list && list.children.length === 0) {
                list.innerHTML = '<li class="notification-item" style="justify-content:center;color:var(--gray-400);font-size:0.85rem;padding:24px;"><span>No notifications yet</span></li>';
            }
        }
    } catch (err) {
        console.error('Delete notification error:', err);
    }
}

async function clearAllNotifications() {
    if (!confirm('Clear all notifications?')) return;
    try {
        const res = await fetch('/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear-all' })
        });
        const data = await res.json();
        if (data.success) {
            const list = document.getElementById('notificationList');
            if (list) list.innerHTML = '<li class="notification-item" style="justify-content:center;color:var(--gray-400);font-size:0.85rem;padding:24px;"><span>No notifications yet</span></li>';
            updateNotifBadge(0);
            showToast('All notifications cleared.', 'success');
        }
    } catch (err) {
        console.error('Clear all error:', err);
    }
}

async function pollUnreadCount() {
    try {
        const res = await fetch('/api/notifications.php?action=unread-count');
        const data = await res.json();
        if (data.success) {
            const oldCount = parseInt(document.getElementById('notifCount')?.textContent || 0);
            updateNotifBadge(data.unread_count);
            // If new notifications arrived while panel is open, reload
            if (data.unread_count > oldCount && document.getElementById('notificationPanel')?.classList.contains('open')) {
                loadNotifications();
            }
        }
    } catch (err) {
        // Silent fail for polling
    }
}

// Add notification and refresh from DB
function addNotification(title, text, type = 'booking') {
    // Just reload from DB to stay in sync
    notificationsLoaded = false;
    pollUnreadCount();
    if (document.getElementById('notificationPanel')?.classList.contains('open')) {
        loadNotifications();
    }
    showToast(title + ': ' + text, 'info');
}

// Export for footer initialization
function initNotifications(isLoggedIn) {
    if (isLoggedIn) {
        document.addEventListener('DOMContentLoaded', () => {
            // Initial unread count
            pollUnreadCount();
            // Poll every 15 seconds
            notifPollInterval = setInterval(pollUnreadCount, 15000);
        });
    }

    document.addEventListener('click', (e) => {
        const panel = document.getElementById('notificationPanel');
        const btn = document.getElementById('notifBtn');
        if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('open');
        }
    });
}
