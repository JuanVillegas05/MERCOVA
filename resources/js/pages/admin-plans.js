import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

let plans = [];

function row(plan) {
    const statusBadge = plan.is_active
        ? '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>'
        : '<span class="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 text-slate-500">Inactivo</span>';

    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${escapeHtml(plan.name)}</td>
            <td class="px-4 py-3 text-slate-600">${Number(plan.price) === 0 ? 'Gratis' : money(plan.price)}${Number(plan.price) > 0 ? ` / ${escapeHtml(plan.billing_period)}` : ''}</td>
            <td class="px-4 py-3 text-slate-600">${plan.max_listings ?? 'Ilimitado'}</td>
            <td class="px-4 py-3 text-slate-600">${plan.businesses_count ?? 0}</td>
            <td class="px-4 py-3">${statusBadge}</td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="edit" data-id="${plan.id}" class="text-xs font-medium text-indigo-600 hover:underline">Editar</button>
                <button data-action="delete" data-id="${plan.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `;
}

async function loadPlans() {
    const tbody = document.getElementById('plans-table-body');
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

    try {
        const { data } = await apiFetch('/admin/membership-plans');
        plans = data;
        tbody.innerHTML = plans.length
            ? plans.map(row).join('')
            : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin planes todavía.</td></tr>';
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
    }
}

function resetForm(form, title, cancelBtn) {
    form.reset();
    form.elements.id.value = '';
    form.elements.is_active.checked = true;
    title.textContent = 'Nuevo plan';
    cancelBtn.classList.add('hidden');
}

export async function initAdminPlans() {
    const tbody = document.getElementById('plans-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    const form = document.getElementById('plan-form');
    const title = document.getElementById('plan-form-title');
    const cancelBtn = document.getElementById('plan-form-cancel');

    cancelBtn.addEventListener('click', () => resetForm(form, title, cancelBtn));

    tbody.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;
        const { action, id } = btn.dataset;

        if (action === 'edit') {
            const plan = plans.find((p) => p.id === Number(id));
            if (!plan) return;
            form.elements.id.value = plan.id;
            form.elements.name.value = plan.name;
            form.elements.price.value = plan.price;
            form.elements.billing_period.value = plan.billing_period;
            form.elements.max_listings.value = plan.max_listings ?? '';
            form.elements.description.value = plan.description ?? '';
            form.elements.features.value = (plan.features || []).join('\n');
            form.elements.is_active.checked = Boolean(plan.is_active);
            title.textContent = `Editar "${plan.name}"`;
            cancelBtn.classList.remove('hidden');
            return;
        }

        if (action === 'delete') {
            if (!confirm('¿Eliminar este plan? Los emprendimientos que lo tengan quedarán sin plan.')) return;
            try {
                await apiFetch(`/admin/membership-plans/${id}`, { method: 'DELETE' });
                toast('Plan eliminado.', 'success');
                await loadPlans();
            } catch (error) {
                toast(error.message, 'error');
            }
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const id = formData.get('id');
        const maxListings = formData.get('max_listings');
        const features = formData.get('features')
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);

        const payload = {
            name: formData.get('name'),
            price: Number(formData.get('price')),
            billing_period: formData.get('billing_period'),
            max_listings: maxListings ? Number(maxListings) : null,
            description: formData.get('description') || null,
            features,
            is_active: form.elements.is_active.checked,
        };

        try {
            if (id) {
                await apiFetch(`/admin/membership-plans/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                toast('Plan actualizado.', 'success');
            } else {
                await apiFetch('/admin/membership-plans', { method: 'POST', body: JSON.stringify(payload) });
                toast('Plan creado.', 'success');
            }
            resetForm(form, title, cancelBtn);
            await loadPlans();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await loadPlans();
}
