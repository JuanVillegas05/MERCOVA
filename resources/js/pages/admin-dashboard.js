import { apiFetch, getToken } from '../lib/api.js';
import { money } from '../lib/money.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';
import { toast } from '../lib/toast.js';

const LIVE_POLL_MS = 15000;

const ICONS = {
    money: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
    users: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm8 4a4 4 0 00-3-3.87"/></svg>',
    basket: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 9l1.5-5h11L19 9M5 9h14l-1.2 9.6a2 2 0 01-2 1.4H8.2a2 2 0 01-2-1.4L5 9zm4 4v3m6-3v3"/></svg>',
    trend: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M21 7h-4m4 0v4"/></svg>',
};

function heroStatCard({ icon, label, value, caption, color }) {
    return `
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-start justify-between">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">${label}</p>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full ${color}">${icon}</span>
            </div>
            <p class="text-2xl font-extrabold text-slate-900 mt-3">${value}</p>
            <p class="text-xs text-slate-400 mt-1">${caption}</p>
        </div>
    `;
}

function miniStatTile(label, value) {
    return `
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
            <p class="text-xl font-extrabold text-slate-800">${value}</p>
            <p class="text-xs text-slate-500 mt-1">${label}</p>
        </div>
    `;
}

function orderRow(order) {
    return `
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0 text-sm">
            <div>
                <p class="font-medium text-slate-800">#${order.id} · ${escapeHtml(order.buyer?.name ?? '')}</p>
                <p class="text-xs text-slate-400">${new Date(order.created_at).toLocaleString('es-CO')}</p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-slate-800">${money(order.total)}</p>
                <p class="text-xs text-slate-400">${order.status}</p>
            </div>
        </div>
    `;
}

function liveOrderRow(order) {
    return `
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0 text-sm">
            <div>
                <p class="font-medium text-slate-800">#${order.id} · ${escapeHtml(order.buyer?.name ?? '—')}</p>
                <p class="text-xs text-slate-400">Vendedor: ${escapeHtml(order.seller?.name ?? '—')} · ${new Date(order.created_at).toLocaleString('es-CO')}</p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-slate-800">${money(order.total)}</p>
                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pendiente</span>
            </div>
        </div>
    `;
}

function activityRow(item) {
    return `
        <div class="py-3 border-b border-slate-100 last:border-0 text-sm">
            <p class="font-medium text-slate-800">${escapeHtml(item.title)}</p>
            <p class="text-xs text-slate-500">${escapeHtml(item.message || '')}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">${new Date(item.created_at).toLocaleString('es-CO')}</p>
        </div>
    `;
}

function renderStatusBreakdown(breakdown) {
    const total = breakdown.reduce((sum, item) => sum + item.count, 0);

    if (!total) {
        return '<p class="text-slate-400 text-sm py-4">Todavía no hay pedidos en este rango.</p>';
    }

    return breakdown.map((item) => {
        const pct = Math.round((item.count / total) * 100);

        return `
            <div>
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="flex items-center gap-2 font-medium text-slate-700">
                        <span class="h-2.5 w-2.5 rounded-full" style="background:${item.color}"></span>
                        ${escapeHtml(item.label)}
                    </span>
                    <span class="text-slate-500">${item.count} · ${pct}%</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full" style="width:${pct}%;background:${item.color}"></div>
                </div>
            </div>
        `;
    }).join('');
}

function renderOriginDonut(breakdown) {
    const total = breakdown.reduce((sum, item) => sum + item.count, 0);

    const legend = breakdown.map((item) => {
        const pct = total ? Math.round((item.count / total) * 100) : 0;

        return `
            <div class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2 text-slate-700">
                    <span class="h-2.5 w-2.5 rounded-full" style="background:${item.color}"></span>
                    ${escapeHtml(item.label)}
                </span>
                <span class="font-medium text-slate-500">${item.count} · ${pct}%</span>
            </div>
        `;
    }).join('');

    if (!total) {
        return `
            <div class="h-32 w-32 rounded-full bg-slate-100 shrink-0"></div>
            <div class="flex-1 space-y-2">${legend}</div>
        `;
    }

    let offset = 0;
    const stops = breakdown.map((item) => {
        const pct = (item.count / total) * 100;
        const stop = `${item.color} ${offset}% ${offset + pct}%`;
        offset += pct;
        return stop;
    }).join(', ');

    return `
        <div class="relative h-32 w-32 rounded-full shrink-0" style="background:conic-gradient(${stops})">
            <div class="absolute inset-3 rounded-full bg-white flex items-center justify-center">
                <span class="text-lg font-extrabold text-slate-800">${total}</span>
            </div>
        </div>
        <div class="flex-1 space-y-2">${legend}</div>
    `;
}

function renderGrowthChart(growth) {
    const max = Math.max(1, ...growth.flatMap((day) => [day.new_users, day.new_orders]));

    return growth.map((day) => {
        const usersPct = Math.round((day.new_users / max) * 100);
        const ordersPct = Math.round((day.new_orders / max) * 100);

        return `
            <div class="flex-1 flex flex-col items-center justify-end h-full gap-1" title="${day.label}: ${day.new_users} usuarios, ${day.new_orders} pedidos">
                <div class="w-full flex items-end justify-center gap-0.5 h-32">
                    <div class="w-2 rounded-t bg-emerald-500" style="height:${Math.max(usersPct, day.new_users ? 4 : 0)}%"></div>
                    <div class="w-2 rounded-t bg-indigo-400" style="height:${Math.max(ordersPct, day.new_orders ? 4 : 0)}%"></div>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap">${day.label}</span>
            </div>
        `;
    }).join('');
}

async function loadSummary(range) {
    const statsEl = document.getElementById('admin-stats');

    try {
        const { data } = await apiFetch(`/admin/dashboard?range=${range}`);
        const { stats, summary, order_status_breakdown: statusBreakdown, request_origin_breakdown: originBreakdown, growth, recent_orders: recentOrders, recent_activity: recentActivity } = data;

        statsEl.innerHTML = [
            heroStatCard({
                icon: ICONS.money,
                label: 'Dinero ingresado',
                value: money(summary.revenue),
                caption: 'Ventas registradas en la plataforma',
                color: 'bg-emerald-100 text-emerald-700',
            }),
            heroStatCard({
                icon: ICONS.users,
                label: 'Clientes registrados',
                value: summary.customers,
                caption: 'Usuarios con cuenta activa',
                color: 'bg-indigo-100 text-indigo-700',
            }),
            heroStatCard({
                icon: ICONS.basket,
                label: 'Cantidad de pedidos',
                value: summary.orders_count,
                caption: 'Sincronizados en tiempo real',
                color: 'bg-amber-100 text-amber-700',
            }),
            heroStatCard({
                icon: ICONS.trend,
                label: 'Promedio por pedido',
                value: money(summary.average_order_value),
                caption: 'Gasto estimado por cada compra',
                color: 'bg-rose-100 text-rose-700',
            }),
        ].join('');

        document.getElementById('status-breakdown').innerHTML = renderStatusBreakdown(statusBreakdown);
        document.getElementById('origin-chart').innerHTML = renderOriginDonut(originBreakdown);
        document.getElementById('growth-chart').innerHTML = renderGrowthChart(growth);

        document.getElementById('admin-recent-orders').innerHTML = recentOrders.length
            ? recentOrders.map(orderRow).join('')
            : '<p class="text-slate-400 text-sm py-4">Sin pedidos todavía.</p>';

        document.getElementById('admin-recent-activity').innerHTML = recentActivity.length
            ? recentActivity.map(activityRow).join('')
            : '<p class="text-slate-400 text-sm py-4">Sin actividad todavía.</p>';

        document.getElementById('live-orders-badge').textContent = stats.pending_orders;

        const extraRow = document.getElementById('admin-extra-stats');
        if (extraRow) {
            extraRow.innerHTML = [
                miniStatTile('Nuevos usuarios (24h)', stats.new_users),
                miniStatTile('Publicaciones activas', stats.products + stats.services),
                miniStatTile('Emprendimientos activos', stats.active_businesses),
                miniStatTile('Notificaciones sin leer', stats.unread_notifications),
            ].join('');
        }
    } catch (error) {
        statsEl.innerHTML = `<p class="col-span-full text-red-500 text-center py-8">${error.message}</p>`;
    }
}

async function loadLiveOrders() {
    const container = document.getElementById('live-orders-list');
    if (!container) return;

    try {
        const { data } = await apiFetch('/admin/orders?status=pending');
        container.innerHTML = data.length
            ? data.map(liveOrderRow).join('')
            : '<p class="text-slate-400 text-sm py-6 text-center">No hay pedidos pendientes por atender ahora mismo.</p>';
        document.getElementById('live-orders-badge').textContent = data.length;
    } catch (error) {
        container.innerHTML = `<p class="text-red-500 text-sm py-6 text-center">${error.message}</p>`;
    }
}

function bindTabs() {
    const tabs = document.querySelectorAll('.dashboard-tab');
    const panels = {
        summary: document.getElementById('dashboard-tab-summary'),
        live: document.getElementById('dashboard-tab-live'),
    };

    function setActive(name) {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.tab === name;
            tab.classList.toggle('bg-amber-400', isActive);
            tab.classList.toggle('text-slate-900', isActive);
            tab.classList.toggle('text-slate-500', !isActive);
            tab.classList.toggle('hover:text-slate-700', !isActive);
        });
        panels.summary.classList.toggle('hidden', name !== 'summary');
        panels.live.classList.toggle('hidden', name !== 'live');

        if (name === 'live') {
            loadLiveOrders();
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setActive(tab.dataset.tab));
    });

    setActive('summary');
}

function bindRangeSelector() {
    const select = document.getElementById('dashboard-range');
    select?.addEventListener('change', () => loadSummary(select.value));
    return select;
}

/**
 * Exporta exactamente lo que está en pantalla: usa el mismo rango
 * (hoy/semana/mes/todo) que #dashboard-range tiene seleccionado en ese
 * momento, no un rango aparte.
 */
function bindDashboardExportButtons(rangeSelect) {
    document.querySelectorAll('.dashboard-export-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) return;

            const format = button.dataset.dashboardExport;
            const range = rangeSelect?.value || 'today';

            setExportButtonLoading(button, true);
            try {
                await downloadExport(`/admin/dashboard/export/${format}?range=${range}`, `mercova-resumen.${format === 'excel' ? 'xlsx' : format}`);
                toast('Archivo descargado.', 'success');
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                setExportButtonLoading(button, false);
            }
        });
    });
}

/**
 * apiFetch() siempre intenta parsear JSON, así que no sirve para descargar
 * un archivo binario (xlsx/pdf): esta función hace un fetch aparte, igual de
 * autenticado, que lee la respuesta como blob y la "descarga" creando un
 * <a> temporal — el mismo truco de siempre para forzar una descarga desde
 * JS sin que el navegador solo la abra en una pestaña nueva.
 */
async function downloadExport(path, fallbackFilename) {
    const response = await fetch(`/api${path}`, {
        headers: {
            Accept: 'application/octet-stream',
            Authorization: `Bearer ${getToken()}`,
        },
    });

    if (!response.ok) {
        let message = 'No pudimos generar el archivo.';
        try {
            message = (await response.json())?.message || message;
        } catch (error) {
            // La respuesta de error no era JSON; nos quedamos con el mensaje genérico.
        }
        throw new Error(message);
    }

    const disposition = response.headers.get('Content-Disposition') || '';
    const filenameMatch = disposition.match(/filename="?([^"]+)"?/);
    const filename = filenameMatch ? filenameMatch[1] : fallbackFilename;

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function exportQueryParams() {
    const range = document.getElementById('export-range')?.value || 'this_month';
    const params = new URLSearchParams({ range });

    if (range === 'custom') {
        const from = document.getElementById('export-from')?.value;
        const to = document.getElementById('export-to')?.value;

        if (!from || !to) {
            throw new Error('Elige las dos fechas del rango personalizado.');
        }

        params.set('from', from);
        params.set('to', to);
    }

    return params;
}

function setExportButtonLoading(button, isLoading) {
    button.disabled = isLoading;
    button.querySelector('.export-spinner')?.classList.toggle('hidden', !isLoading);
}

function bindExportButtons() {
    const rangeSelect = document.getElementById('export-range');
    const customRange = document.getElementById('export-custom-range');

    rangeSelect?.addEventListener('change', () => {
        customRange.classList.toggle('hidden', rangeSelect.value !== 'custom');
    });

    document.querySelectorAll('.export-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) return;

            const format = button.dataset.exportFormat;

            setExportButtonLoading(button, true);
            try {
                const params = exportQueryParams();
                await downloadExport(`/admin/metrics/export/${format}?${params}`, `mercova-metricas.${format === 'excel' ? 'xlsx' : format}`);
                toast('Archivo descargado.', 'success');
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                setExportButtonLoading(button, false);
            }
        });
    });
}

export async function initAdminDashboard() {
    const statsEl = document.getElementById('admin-stats');
    if (!statsEl) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();
    bindTabs();
    bindExportButtons();
    const rangeSelect = bindRangeSelector();
    bindDashboardExportButtons(rangeSelect);

    await loadSummary(rangeSelect?.value || 'today');

    // "Tiempo real" sin WebSockets: refresco simple por polling cada 15s,
    // suficiente para un panel de administración de bajo volumen.
    setInterval(() => {
        if (!document.getElementById('dashboard-tab-live').classList.contains('hidden')) {
            loadLiveOrders();
        }
    }, LIVE_POLL_MS);
}
