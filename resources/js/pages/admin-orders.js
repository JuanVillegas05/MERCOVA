import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

const STATUS_LABELS = {
    pending: 'Pendiente',
    processing: 'En proceso',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

const STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-700',
    processing: 'bg-indigo-100 text-indigo-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
};

const PAYMENT_STATUS_LABELS = {
    pending: 'Pago pendiente',
    approved: 'Pago confirmado',
    declined: 'Pago rechazado',
    voided: 'Pago anulado',
    error: 'Error de pago',
};

const PAYMENT_STATUS_COLORS = {
    pending: 'bg-slate-100 text-slate-500',
    approved: 'bg-emerald-100 text-emerald-700',
    declined: 'bg-red-100 text-red-700',
    voided: 'bg-red-100 text-red-700',
    error: 'bg-red-100 text-red-700',
};

function paymentBadge(order) {
    if (!order.payment_reference) return '';
    const status = order.payment_status || 'pending';
    return `<span class="block mt-1 text-[11px] font-medium px-1.5 py-0.5 rounded ${PAYMENT_STATUS_COLORS[status] || ''}">${PAYMENT_STATUS_LABELS[status] || status}</span>`;
}

const state = { type: 'orders', status: '', onlyNew: false };

function statusSelect(id, current) {
    return `
        <select data-status-select="${id}" class="text-xs rounded-md border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
            ${Object.entries(STATUS_LABELS).map(([value, label]) => (
                `<option value="${value}" ${value === current ? 'selected' : ''}>${label}</option>`
            )).join('')}
        </select>
    `;
}

function orderRow(order) {
    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3">#${order.id}</td>
            <td class="px-4 py-3">${escapeHtml(order.buyer?.name ?? '—')}</td>
            <td class="px-4 py-3">${escapeHtml(order.seller?.name ?? '—')}</td>
            <td class="px-4 py-3 font-medium">${money(order.total)}${paymentBadge(order)}</td>
            <td class="px-4 py-3 text-slate-500">${new Date(order.created_at).toLocaleDateString('es-CO')}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${STATUS_COLORS[order.status] || ''}">${STATUS_LABELS[order.status] || order.status}</span></td>
            <td class="px-4 py-3">${statusSelect(order.id, order.status)}</td>
        </tr>
    `;
}

function bookingRow(booking) {
    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3">#${booking.id}</td>
            <td class="px-4 py-3">${escapeHtml(booking.buyer?.name ?? '—')}</td>
            <td class="px-4 py-3">${escapeHtml(booking.service?.title ?? '—')}</td>
            <td class="px-4 py-3 font-medium">${money(booking.total)}</td>
            <td class="px-4 py-3 text-slate-500">${new Date(booking.scheduled_at).toLocaleDateString('es-CO')}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${STATUS_COLORS[booking.status] || ''}">${STATUS_LABELS[booking.status] || booking.status}</span></td>
            <td class="px-4 py-3">${statusSelect(booking.id, booking.status)}</td>
        </tr>
    `;
}

async function loadTable() {
    const tbody = document.getElementById('orders-table-body');
    const colExtra = document.getElementById('col-extra');
    colExtra.textContent = state.type === 'orders' ? 'Vendedor' : 'Servicio';

    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

    const params = new URLSearchParams();
    if (state.status) params.set('status', state.status);
    if (state.onlyNew) params.set('window', 'new');

    try {
        const { data } = await apiFetch(`/admin/${state.type}?${params.toString()}`);
        if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin resultados.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map(state.type === 'orders' ? orderRow : bookingRow).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
    }
}

export async function initAdminOrders() {
    const tbody = document.getElementById('orders-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    const tabOrders = document.getElementById('tab-orders');
    const tabBookings = document.getElementById('tab-bookings');
    const statusFilter = document.getElementById('filter-status');
    const newFilter = document.getElementById('filter-new');

    const setActiveTab = () => {
        const isOrders = state.type === 'orders';
        tabOrders.classList.toggle('bg-emerald-600', isOrders);
        tabOrders.classList.toggle('text-white', isOrders);
        tabOrders.classList.toggle('text-slate-600', !isOrders);
        tabBookings.classList.toggle('bg-indigo-600', !isOrders);
        tabBookings.classList.toggle('text-white', !isOrders);
        tabBookings.classList.toggle('text-slate-600', isOrders);
    };

    tabOrders.addEventListener('click', () => { state.type = 'orders'; setActiveTab(); loadTable(); });
    tabBookings.addEventListener('click', () => { state.type = 'bookings'; setActiveTab(); loadTable(); });
    statusFilter.addEventListener('change', (e) => { state.status = e.target.value; loadTable(); });
    newFilter.addEventListener('change', (e) => { state.onlyNew = e.target.checked; loadTable(); });

    tbody.addEventListener('change', async (event) => {
        const select = event.target.closest('[data-status-select]');
        if (!select) return;

        const id = select.dataset.statusSelect;
        const endpoint = state.type === 'orders' ? `/admin/orders/${id}/status` : `/admin/bookings/${id}/status`;

        try {
            await apiFetch(endpoint, { method: 'PATCH', body: JSON.stringify({ status: select.value }) });
            toast('Estado actualizado.', 'success');
            await loadTable();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    setActiveTab();
    await loadTable();
}
