import { apiFetch } from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

const STATUS_LABELS = {
    active: 'Activo',
    pending: 'Pendiente de membresía',
    suspended: 'Suspendido',
};

const STATUS_COLORS = {
    active: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-slate-100 text-slate-600',
    suspended: 'bg-red-100 text-red-700',
};

function toggleAction(business) {
    if (business.status === 'active') {
        return `<button data-action="toggle" data-id="${business.id}" data-next="suspended" class="text-xs font-medium text-amber-600 hover:underline">Suspender</button>`;
    }
    if (business.status === 'suspended') {
        return `<button data-action="toggle" data-id="${business.id}" data-next="active" class="text-xs font-medium text-indigo-600 hover:underline">Reactivar</button>`;
    }
    return '';
}

function row(business) {
    const listingsCount = (business.products_count ?? 0) + (business.services_count ?? 0);

    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">
                ${escapeHtml(business.name)}
                <span class="block text-xs text-slate-400">${escapeHtml(business.category ?? '')}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">
                ${escapeHtml(business.owner?.name ?? '—')}
                <span class="block text-xs text-slate-400">${escapeHtml(business.owner?.email ?? '')}</span>
            </td>
            <td class="px-4 py-3 text-slate-600">${escapeHtml(business.plan?.name ?? 'Sin plan')}</td>
            <td class="px-4 py-3 text-slate-600">${listingsCount}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${STATUS_COLORS[business.status] || ''}">${STATUS_LABELS[business.status] || business.status}</span></td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                ${toggleAction(business)}
                <button data-action="delete" data-id="${business.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `;
}

export async function initAdminBusinesses() {
    const tbody = document.getElementById('businesses-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    async function load() {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

        try {
            const { data } = await apiFetch('/admin/businesses');
            tbody.innerHTML = data.length
                ? data.map(row).join('')
                : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin emprendimientos registrados todavía.</td></tr>';
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
        }
    }

    tbody.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;

        const { action, id, next } = btn.dataset;

        try {
            if (action === 'delete') {
                if (!confirm('¿Eliminar este emprendimiento? Sus publicaciones se archivarán y dejarán de verse en el marketplace.')) return;
                await apiFetch(`/admin/businesses/${id}`, { method: 'DELETE' });
                toast('Emprendimiento eliminado.', 'success');
            }
            if (action === 'toggle') {
                await apiFetch(`/admin/businesses/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status: next }) });
                toast('Estado actualizado.', 'success');
            }
            await load();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await load();
}
