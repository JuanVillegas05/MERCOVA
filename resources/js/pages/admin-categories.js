import { apiFetch } from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { requireAdmin } from '../lib/auth.js';
import { initNotificationBell } from '../lib/notifications.js';
import { escapeHtml } from '../lib/escape.js';

let categories = [];

function row(category) {
    const isChild = category.parent_id !== null;
    const listingsCount = (category.products_count ?? 0) + (category.services_count ?? 0);
    const name = isChild ? `<span class="text-slate-300">— </span>${escapeHtml(category.name)}` : `<strong>${escapeHtml(category.name)}</strong>`;

    return `
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 text-slate-800">${name}</td>
            <td class="px-4 py-3 text-slate-500">${category.parent ? escapeHtml(category.parent.name) : '<span class="text-slate-300">—</span>'}</td>
            <td class="px-4 py-3 text-slate-600">${listingsCount}</td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="edit" data-id="${category.id}" class="text-xs font-medium text-indigo-600 hover:underline">Editar</button>
                <button data-action="delete" data-id="${category.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `;
}

function sortedForDisplay(list) {
    const parents = list.filter((c) => c.parent_id === null);
    const rows = [];
    parents.forEach((parent) => {
        rows.push(parent);
        list.filter((c) => c.parent_id === parent.id).forEach((child) => rows.push(child));
    });
    return rows;
}

async function loadCategories() {
    const tbody = document.getElementById('categories-table-body');
    tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>';

    try {
        categories = await apiFetch('/categories');
        const ordered = sortedForDisplay(categories);
        tbody.innerHTML = ordered.length
            ? ordered.map(row).join('')
            : '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin categorías todavía.</td></tr>';

        const parentSelect = document.querySelector('select[name="parent_id"]');
        const topLevel = categories.filter((c) => c.parent_id === null);
        parentSelect.innerHTML = '<option value="">Ninguna (categoría principal)</option>'
            + topLevel.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
    }
}

function resetForm(form, title, cancelBtn) {
    form.reset();
    form.elements.id.value = '';
    title.textContent = 'Nueva categoría';
    cancelBtn.classList.add('hidden');
}

export async function initAdminCategories() {
    const tbody = document.getElementById('categories-table-body');
    if (!tbody) return;
    if (!(await requireAdmin())) return;

    initNotificationBell();

    const form = document.getElementById('category-form');
    const title = document.getElementById('category-form-title');
    const cancelBtn = document.getElementById('category-form-cancel');

    cancelBtn.addEventListener('click', () => resetForm(form, title, cancelBtn));

    tbody.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;
        const { action, id } = btn.dataset;

        if (action === 'edit') {
            const category = categories.find((c) => c.id === Number(id));
            if (!category) return;
            form.elements.id.value = category.id;
            form.elements.name.value = category.name;
            form.elements.parent_id.value = category.parent_id ?? '';
            form.elements.icon.value = category.icon ?? '';
            form.elements.description.value = category.description ?? '';
            title.textContent = `Editar "${category.name}"`;
            cancelBtn.classList.remove('hidden');
            return;
        }

        if (action === 'delete') {
            if (!confirm('¿Eliminar esta categoría?')) return;
            try {
                await apiFetch(`/admin/categories/${id}`, { method: 'DELETE' });
                toast('Categoría eliminada.', 'success');
                await loadCategories();
            } catch (error) {
                toast(error.message, 'error');
            }
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const id = formData.get('id');

        const payload = {
            name: formData.get('name'),
            parent_id: formData.get('parent_id') || null,
            icon: formData.get('icon') || null,
            description: formData.get('description') || null,
        };

        try {
            if (id) {
                await apiFetch(`/admin/categories/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                toast('Categoría actualizada.', 'success');
            } else {
                await apiFetch('/admin/categories', { method: 'POST', body: JSON.stringify(payload) });
                toast('Categoría creada.', 'success');
            }
            resetForm(form, title, cancelBtn);
            await loadCategories();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await loadCategories();
}
