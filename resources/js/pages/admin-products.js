import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

const STATUS_COLORS = {
    active: 'bg-emerald-100 text-emerald-700',
    draft: 'bg-slate-100 text-slate-600',
    archived: 'bg-amber-100 text-amber-700',
};

function row(item, type) {
    const typeLabel = type === 'products'
        ? (item.transaction_type === 'rent' ? 'Alquiler' : 'Venta')
        : 'Servicio';
    const nextStatus = item.status === 'active' ? 'archived' : 'active';

    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${escapeHtml(item.title)}</td>
            <td class="px-4 py-3 text-slate-600">${escapeHtml(item.seller?.name ?? '—')}${item.business ? ` <span class="text-xs text-indigo-500">(${escapeHtml(item.business.name)})</span>` : ''}</td>
            <td class="px-4 py-3 text-slate-500">${typeLabel}</td>
            <td class="px-4 py-3 font-medium">${money(item.price)}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${STATUS_COLORS[item.status] || ''}">${item.status}</span></td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="toggle" data-type="${type}" data-id="${item.id}" data-next="${nextStatus}" class="text-xs font-medium text-indigo-600 hover:underline">
                    ${nextStatus === 'active' ? 'Activar' : 'Archivar'}
                </button>
                <button data-action="delete" data-type="${type}" data-id="${item.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `;
}

export async function initAdminProducts() {
    const tbody = document.getElementById('listings-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    async function load() {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

        try {
            const [products, services] = await Promise.all([
                apiFetch('/admin/products'),
                apiFetch('/admin/services'),
            ]);

            const rows = [
                ...products.data.map((item) => ({ item, type: 'products' })),
                ...services.data.map((item) => ({ item, type: 'services' })),
            ].sort((a, b) => new Date(b.item.created_at) - new Date(a.item.created_at));

            tbody.innerHTML = rows.length
                ? rows.map(({ item, type }) => row(item, type)).join('')
                : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin publicaciones todavía.</td></tr>';
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
        }
    }

    tbody.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;

        const { action, type, id, next } = btn.dataset;

        try {
            if (action === 'delete') {
                if (!confirm('¿Eliminar esta publicación?')) return;
                await apiFetch(`/${type}/${id}`, { method: 'DELETE' });
                toast('Publicación eliminada.', 'success');
            }
            if (action === 'toggle') {
                await apiFetch(`/${type}/${id}`, { method: 'PUT', body: JSON.stringify({ status: next }) });
                toast('Estado actualizado.', 'success');
            }
            await load();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await load();
}
