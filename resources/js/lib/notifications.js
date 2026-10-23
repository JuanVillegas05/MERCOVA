import { apiFetch } from './api.js';
import { escapeHtml } from './escape.js';

const POLL_INTERVAL = 30000;

function timeAgo(dateString) {
    const seconds = Math.floor((Date.now() - new Date(dateString).getTime()) / 1000);
    if (seconds < 60) return 'hace un momento';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;
    const days = Math.floor(hours / 24);
    return `hace ${days} d`;
}

function notificationItem(notification) {
    const unreadDot = notification.read_at ? '' : '<span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>';
    return `
        <button type="button" data-notification-id="${notification.id}"
            class="w-full text-left px-4 py-3 flex gap-2 hover:bg-slate-50 border-b border-slate-100 last:border-0 ${notification.read_at ? 'opacity-60' : ''}">
            ${unreadDot}
            <span class="flex-1 min-w-0">
                <span class="block text-sm font-semibold text-slate-800">${escapeHtml(notification.title)}</span>
                <span class="block text-xs text-slate-500 truncate">${escapeHtml(notification.message || '')}</span>
                <span class="block text-[11px] text-slate-400 mt-0.5">${timeAgo(notification.created_at)}</span>
            </span>
        </button>
    `;
}

export function initNotificationBell(endpoint = '/admin/notifications') {
    const bellBtn = document.getElementById('notif-bell-btn');
    const badge = document.getElementById('notif-badge');
    const dropdown = document.getElementById('notif-dropdown');
    const list = document.getElementById('notif-list');
    const markAllBtn = document.getElementById('notif-mark-all');

    if (!bellBtn || !dropdown || !list) return;

    async function refresh() {
        try {
            const { data, unread_count: unreadCount } = await apiFetch(endpoint);
            const notifications = data.data || [];

            if (unreadCount > 0) {
                badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }

            list.innerHTML = notifications.length
                ? notifications.map(notificationItem).join('')
                : '<p class="text-sm text-slate-400 text-center py-6">Sin notificaciones todavía.</p>';
        } catch (error) {
            // silently ignore polling failures
        }
    }

    bellBtn.addEventListener('click', () => {
        dropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (event) => {
        if (!dropdown.contains(event.target) && !bellBtn.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    list.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-notification-id]');
        if (!btn) return;

        try {
            await apiFetch(`${endpoint}/${btn.dataset.notificationId}/read`, { method: 'POST' });
            await refresh();
        } catch (error) {
            // ignore
        }
    });

    markAllBtn?.addEventListener('click', async () => {
        try {
            await apiFetch(`${endpoint}/read-all`, { method: 'POST' });
            await refresh();
        } catch (error) {
            // ignore
        }
    });

    refresh();
    setInterval(refresh, POLL_INTERVAL);
}
