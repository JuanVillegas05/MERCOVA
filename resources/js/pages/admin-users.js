import { apiFetch } from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { requireAdmin, roleLabel } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

function row(user) {
    const isAdmin = user.role === 'admin';
    const statusBadge = user.is_active
        ? '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>'
        : '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-700">Desactivado</span>';

    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${escapeHtml(user.name)}</td>
            <td class="px-4 py-3 text-slate-500">${escapeHtml(user.email)}</td>
            <td class="px-4 py-3 text-slate-600">${roleLabel(user.role)}</td>
            <td class="px-4 py-3 text-slate-600">${(user.products_count ?? 0) + (user.services_count ?? 0)}</td>
            <td class="px-4 py-3 text-slate-600">${user.orders_count ?? 0}</td>
            <td class="px-4 py-3">${statusBadge}</td>
            <td class="px-4 py-3">
                ${isAdmin
                    ? '<span class="text-xs text-slate-400">—</span>'
                    : `<button data-action="toggle" data-id="${user.id}" data-next="${user.is_active ? 'false' : 'true'}" class="text-xs font-medium ${user.is_active ? 'text-red-500' : 'text-emerald-600'} hover:underline">${user.is_active ? 'Desactivar' : 'Activar'}</button>`
                }
            </td>
        </tr>
    `;
}

export async function initAdminUsers() {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    const searchInput = document.getElementById('user-search');

    async function load(search = '') {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

        try {
            const params = search ? `?search=${encodeURIComponent(search)}` : '';
            const { data } = await apiFetch(`/admin/users${params}`);
            tbody.innerHTML = data.length
                ? data.map(row).join('')
                : '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin resultados.</td></tr>';
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
        }
    }

    let searchTimeout;
    searchInput?.addEventListener('input', (event) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => load(event.target.value), 350);
    });

    tbody.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action="toggle"]');
        if (!btn) return;

        try {
            await apiFetch(`/admin/users/${btn.dataset.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ is_active: btn.dataset.next === 'true' }),
            });
            toast('Estado del usuario actualizado.', 'success');
            await load(searchInput?.value || '');
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await load();
}
