import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { requireAuth, roleLabel } from '../lib/auth.js';
import { openModal } from '../lib/modal.js';
import { submitReview } from '../lib/reviews.js';
import { escapeHtml } from '../lib/escape.js';

let me = null;
let myBusiness = null;
let categories = [];

function statusBadge(status) {
    const colors = {
        active: 'bg-emerald-100 text-emerald-700',
        draft: 'bg-slate-100 text-slate-600',
        archived: 'bg-amber-100 text-amber-700',
    };
    return `<span class="text-xs font-semibold px-2 py-1 rounded-full ${colors[status] || colors.draft}">${status}</span>`;
}

function listingRow(item, type) {
    const nextStatus = item.status === 'active' ? 'archived' : 'active';
    const isRent = type === 'products' && item.transaction_type === 'rent';
    const priceLabel = isRent ? `${money(item.price)}/día` : money(item.price);
    const netHint = item.net_amount != null
        ? ` · recibes ${money(item.net_amount)}${isRent ? '/día' : ''}`
        : '';
    return `
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${escapeHtml(item.title)}</p>
                <p class="text-xs text-slate-400">${priceLabel}${netHint} · ${statusBadge(item.status)}</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button data-action="toggle-status" data-type="${type}" data-id="${item.id}" data-next="${nextStatus}"
                    class="text-xs font-medium text-indigo-600 hover:underline">${nextStatus === 'active' ? 'Activar' : 'Archivar'}</button>
                <button data-action="delete" data-type="${type}" data-id="${item.id}"
                    class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </div>
        </div>
    `;
}

function rentalStatusBadge(status) {
    const colors = {
        pending: 'bg-amber-100 text-amber-700',
        confirmed: 'bg-blue-100 text-blue-700',
        active: 'bg-emerald-100 text-emerald-700',
        finished: 'bg-slate-100 text-slate-600',
        cancelled: 'bg-red-100 text-red-600',
    };
    const labels = {
        pending: 'Pendiente',
        confirmed: 'Confirmado',
        active: 'Activo',
        finished: 'Finalizado',
        cancelled: 'Cancelado',
    };
    return `<span class="text-xs font-semibold px-2 py-1 rounded-full ${colors[status] || colors.pending}">${labels[status] || status}</span>`;
}

function rentalRow(rental) {
    const title = escapeHtml(rental.product?.title ?? 'Producto');
    return `
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${title}</p>
                <p class="text-xs text-slate-400">${rental.start_date} → ${rental.end_date} · ${money(rental.total)}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                ${rentalStatusBadge(rental.status)}
                ${rental.status === 'pending'
                    ? `<button data-action="cancel-rental" data-id="${rental.id}" class="text-xs font-medium text-red-500 hover:underline">Cancelar</button>`
                    : ''}
                ${rental.status === 'finished' && rental.product
                    ? `<button data-action="review" data-product-id="${rental.product.id}" data-title="${title}" class="text-xs font-medium text-amber-600 hover:underline">Calificar</button>`
                    : ''}
            </div>
        </div>
    `;
}

function ownedRentalRow(rental) {
    const actionsByStatus = {
        pending: [['confirmed', 'Confirmar', 'text-emerald-600'], ['cancelled', 'Rechazar', 'text-red-500']],
        confirmed: [['active', 'Marcar activo', 'text-emerald-600'], ['cancelled', 'Cancelar', 'text-red-500']],
        active: [['finished', 'Finalizar', 'text-emerald-600']],
    };
    const buttons = (actionsByStatus[rental.status] || []).map(([status, label, cls]) => `
        <button data-action="update-rental" data-id="${rental.id}" data-status="${status}" class="text-xs font-medium ${cls} hover:underline">${label}</button>
    `).join('');

    return `
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${escapeHtml(rental.product?.title ?? 'Producto')}</p>
                <p class="text-xs text-slate-400">${escapeHtml(rental.renter?.name ?? '')} · ${rental.start_date} → ${rental.end_date} · ${money(rental.total)} · recibes ${money(rental.net_amount)}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                ${rentalStatusBadge(rental.status)}
                ${buttons}
            </div>
        </div>
    `;
}

function orderRow(order) {
    const reviewButtons = order.status === 'completed'
        ? (order.items || [])
            .filter((item) => item.product)
            .map((item) => {
                const title = escapeHtml(item.product.title);
                return `
                    <button data-action="review" data-product-id="${item.product.id}" data-title="${title}"
                        class="text-xs font-medium text-amber-600 hover:underline">Calificar "${title}"</button>
                `;
            }).join('')
        : '';

    return `
        <div class="py-3 border-b border-slate-100 last:border-0">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-800">Pedido #${order.id}</p>
                    <p class="text-xs text-slate-400">${new Date(order.created_at).toLocaleDateString('es-CO')} · ${money(order.total)}</p>
                </div>
                ${statusBadge(order.status)}
            </div>
            ${reviewButtons ? `<div class="mt-2 flex flex-wrap gap-3">${reviewButtons}</div>` : ''}
        </div>
    `;
}

function bookingRow(booking) {
    return `
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
            <div>
                <p class="font-medium text-slate-800">Reserva #${booking.id}</p>
                <p class="text-xs text-slate-400">${new Date(booking.scheduled_at).toLocaleString('es-CO')} · ${money(booking.total)}</p>
            </div>
            ${statusBadge(booking.status)}
        </div>
    `;
}

function renderProfile() {
    const el = document.getElementById('dashboard-profile');
    if (!el) return;
    el.innerHTML = `
        <p class="text-xl font-bold text-slate-800">${escapeHtml(me.name)}</p>
        <p class="text-sm text-slate-500">${escapeHtml(me.email)}</p>
        <span class="inline-block mt-2 text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">${roleLabel(me.role)}</span>
    `;
}

function renderListings() {
    const el = document.getElementById('dashboard-listings');
    if (!el) return;

    const products = (me.products || []).map((p) => listingRow(p, 'products'));
    const services = (me.services || []).map((s) => listingRow(s, 'services'));
    const rows = [...products, ...services];

    el.innerHTML = rows.length
        ? rows.join('')
        : '<p class="text-slate-400 text-sm py-4">Todavía no tienes publicaciones. Crea una abajo.</p>';
}

function renderOrders() {
    const el = document.getElementById('dashboard-orders');
    if (!el) return;
    const orders = me.orders || [];
    el.innerHTML = orders.length ? orders.map(orderRow).join('') : '<p class="text-slate-400 text-sm py-4">Aún no has realizado compras.</p>';
}

function renderBookings() {
    const el = document.getElementById('dashboard-bookings');
    if (!el) return;
    const bookings = me.bookings || [];
    el.innerHTML = bookings.length ? bookings.map(bookingRow).join('') : '<p class="text-slate-400 text-sm py-4">Aún no tienes reservas.</p>';
}

async function loadRentals() {
    const el = document.getElementById('dashboard-rentals');
    if (!el) return;

    try {
        const { data } = await apiFetch('/rentals');
        el.innerHTML = data.length
            ? data.map(rentalRow).join('')
            : '<p class="text-slate-400 text-sm py-4">Aún no has solicitado ningún alquiler.</p>';
    } catch (error) {
        el.innerHTML = '<p class="text-red-500 text-sm py-4">No pudimos cargar tus alquileres.</p>';
    }
}

async function loadOwnedRentals() {
    const section = document.getElementById('owned-rentals-section');
    const el = document.getElementById('dashboard-owned-rentals');
    if (!el || !section) return;

    if (!['seller', 'admin'].includes(me.role)) {
        section.classList.add('hidden');
        return;
    }

    try {
        const { data } = await apiFetch('/rentals/owned');
        section.classList.remove('hidden');
        el.innerHTML = data.length
            ? data.map(ownedRentalRow).join('')
            : '<p class="text-slate-400 text-sm py-4">No tienes solicitudes de alquiler recibidas.</p>';
    } catch (error) {
        section.classList.add('hidden');
    }
}

function openReviewModal(productId, title) {
    const { body, close } = openModal(`Calificar "${title}"`, `
        <form id="review-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Tu calificación</label>
                <div id="star-picker" class="flex gap-1 text-2xl text-slate-300">
                    ${[1, 2, 3, 4, 5].map((n) => `<button type="button" data-star="${n}" class="hover:text-amber-400 transition">★</button>`).join('')}
                </div>
                <input type="hidden" name="rating" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Comentario (opcional)</label>
                <textarea name="comment" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-amber-500 text-white font-semibold rounded-lg py-2 hover:bg-amber-600 transition">Enviar calificación</button>
        </form>
    `);

    const form = body.querySelector('#review-form');
    const ratingInput = form.elements.rating;
    const stars = body.querySelectorAll('[data-star]');

    stars.forEach((star) => {
        star.addEventListener('click', () => {
            const value = Number(star.dataset.star);
            ratingInput.value = value;
            stars.forEach((s) => s.classList.toggle('text-amber-400', Number(s.dataset.star) <= value));
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!ratingInput.value) {
            toast('Selecciona una calificación de 1 a 5 estrellas.', 'error');
            return;
        }

        try {
            await submitReview(productId, Number(ratingInput.value), new FormData(form).get('comment'));
            toast('Gracias por tu calificación.', 'success');
            close();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function bindReviewActions() {
    ['dashboard-orders', 'dashboard-rentals'].forEach((containerId) => {
        document.getElementById(containerId)?.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-action="review"]');
            if (!btn) return;
            openReviewModal(btn.dataset.productId, btn.dataset.title);
        });
    });
}

function bindRentalActions() {
    document.getElementById('dashboard-rentals')?.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action="cancel-rental"]');
        if (!btn) return;
        if (!confirm('¿Cancelar esta solicitud de alquiler?')) return;

        try {
            await apiFetch(`/rentals/${btn.dataset.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: 'cancelled' }),
            });
            toast('Solicitud cancelada.', 'success');
            await loadRentals();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    document.getElementById('dashboard-owned-rentals')?.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action="update-rental"]');
        if (!btn) return;

        try {
            await apiFetch(`/rentals/${btn.dataset.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status: btn.dataset.status }),
            });
            toast('Estado del alquiler actualizado.', 'success');
            await loadOwnedRentals();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function fillProfileForm() {
    const form = document.getElementById('profile-form');
    if (!form) return;
    form.elements.name.value = me.name || '';
    form.elements.phone.value = me.phone || '';
    form.elements.bio.value = me.bio || '';
    form.elements.avatar_url.value = me.avatar_url || '';
}

function bindProfileForm() {
    const form = document.getElementById('profile-form');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        try {
            await apiFetch('/me', {
                method: 'PUT',
                body: JSON.stringify({
                    name: formData.get('name'),
                    phone: formData.get('phone') || null,
                    bio: formData.get('bio') || null,
                    avatar_url: formData.get('avatar_url') || null,
                }),
            });
            toast('Perfil actualizado.', 'success');
            await reloadMe();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function bindPasswordForm() {
    const form = document.getElementById('password-form');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        try {
            await apiFetch('/me/password', {
                method: 'PUT',
                body: JSON.stringify({
                    current_password: formData.get('current_password'),
                    password: formData.get('password'),
                    password_confirmation: formData.get('password_confirmation'),
                }),
            });
            toast('Contraseña actualizada.', 'success');
            form.reset();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function bindListingActions() {
    const el = document.getElementById('dashboard-listings');
    el?.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;

        const { action, type, id, next } = btn.dataset;

        try {
            if (action === 'delete') {
                if (!confirm('¿Eliminar esta publicación?')) return;
                await apiFetch(`/${type}/${id}`, { method: 'DELETE' });
                toast('Publicación eliminada.', 'success');
            }
            if (action === 'toggle-status') {
                await apiFetch(`/${type}/${id}`, { method: 'PUT', body: JSON.stringify({ status: next }) });
                toast('Estado actualizado.', 'success');
            }
            await reloadMe();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

async function loadCategories() {
    const select = document.getElementById('listing-category');
    if (!select) return;
    categories = await apiFetch('/categories');
    select.innerHTML = categories.map((category) => `<option value="${category.id}">${escapeHtml(category.name)}</option>`).join('');
}

function setupCreateForm() {
    const section = document.getElementById('create-listing-section');
    if (!section) return;

    if (!['seller', 'admin'].includes(me.role)) {
        section.classList.add('hidden');
        return;
    }
    section.classList.remove('hidden');

    const businessSelect = document.getElementById('listing-business');
    if (businessSelect) {
        if (myBusiness && myBusiness.status === 'active') {
            businessSelect.innerHTML = `
                <option value="">Publicación personal</option>
                <option value="${myBusiness.id}">${escapeHtml(myBusiness.name)} (emprendimiento)</option>
            `;
        } else {
            businessSelect.innerHTML = '<option value="">Publicación personal</option>';
            if (myBusiness) {
                const hint = document.getElementById('business-hint');
                if (hint) hint.textContent = 'Tu emprendimiento necesita una membresía activa para publicar a su nombre. Revisa la sección Emprendimientos.';
            }
        }
    }

    const typeTabs = document.querySelectorAll('[data-listing-type]');
    const productFields = document.getElementById('product-only-fields');
    const serviceFields = document.getElementById('service-only-fields');
    let currentType = 'products';

    const priceModeTabs = document.querySelectorAll('[data-price-mode]');
    const priceModeToggle = document.getElementById('price-mode-toggle');
    const priceInput = document.getElementById('listing-price-input');
    const breakdownBox = document.getElementById('commission-breakdown');
    const breakdownGrossLabel = document.getElementById('breakdown-gross-label');
    const salePriceSection = document.getElementById('sale-price-section');
    const rentPriceSection = document.getElementById('rent-price-section');
    const rentPriceInput = document.getElementById('rent-price-input');
    const transactionTypeSelect = document.getElementById('listing-transaction-type');
    const stockField = document.getElementById('stock-field');
    let priceMode = 'price';

    const priceLabels = {
        price: 'Ej: 50000 (lo que pagará el comprador)',
        desired_net_price: 'Ej: 50000 (lo que quieres recibir tú, neto)',
    };

    const isRentMode = () => currentType === 'products' && transactionTypeSelect.value === 'rent';

    function applyPricingVisibility() {
        if (currentType === 'services') {
            salePriceSection.classList.remove('hidden');
            rentPriceSection.classList.add('hidden');
            priceModeToggle.classList.remove('hidden');
            priceInput.required = true;
            return;
        }

        const rentMode = isRentMode();
        salePriceSection.classList.toggle('hidden', rentMode);
        rentPriceSection.classList.toggle('hidden', !rentMode);
        priceModeToggle.classList.toggle('hidden', rentMode);
        priceInput.required = !rentMode;
        stockField.classList.toggle('hidden', rentMode);
    }

    async function refreshBreakdown() {
        const rentMode = isRentMode();
        const value = Number(rentMode ? rentPriceInput.value : priceInput.value);
        if (!value || value <= 0) {
            breakdownBox.classList.add('hidden');
            return;
        }

        try {
            const { data } = await apiFetch('/products/commission-preview', {
                method: 'POST',
                body: JSON.stringify(rentMode ? { price: value } : { [priceMode]: value }),
            });
            breakdownGrossLabel.textContent = rentMode ? 'Precio por día' : 'Valor publicado (lo que paga el comprador)';
            document.getElementById('breakdown-gross').textContent = money(data.gross_price);
            document.getElementById('breakdown-rate').textContent = (data.commission_rate * 100).toFixed(0);
            document.getElementById('breakdown-commission').textContent = `-${money(data.commission_amount)}`;
            document.getElementById('breakdown-net').textContent = money(data.net_amount);
            breakdownBox.classList.remove('hidden');
        } catch (error) {
            breakdownBox.classList.add('hidden');
        }
    }

    priceModeTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            priceMode = tab.dataset.priceMode;
            priceModeTabs.forEach((t) => t.classList.toggle('bg-white', t === tab));
            priceInput.placeholder = priceLabels[priceMode];
            refreshBreakdown();
        });
    });
    priceInput?.addEventListener('input', refreshBreakdown);
    rentPriceInput?.addEventListener('input', refreshBreakdown);
    transactionTypeSelect?.addEventListener('change', () => {
        applyPricingVisibility();
        refreshBreakdown();
    });

    typeTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            currentType = tab.dataset.listingType;
            typeTabs.forEach((t) => t.classList.toggle('bg-white', t === tab));
            productFields.classList.toggle('hidden', currentType !== 'products');
            serviceFields.classList.toggle('hidden', currentType !== 'services');
            priceMode = 'price';
            priceModeTabs.forEach((t) => t.classList.toggle('bg-white', t.dataset.priceMode === 'price'));
            priceInput.placeholder = priceLabels.price;
            applyPricingVisibility();
            refreshBreakdown();
        });
    });

    applyPricingVisibility();

    const form = document.getElementById('create-listing-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        const payload = {
            category_id: Number(formData.get('category_id')),
            business_id: formData.get('business_id') || null,
            title: formData.get('title'),
            description: formData.get('description'),
            location: formData.get('location') || null,
            image_url: formData.get('image_url') || null,
        };

        if (currentType === 'products') {
            payload.transaction_type = formData.get('transaction_type');
            payload.condition = formData.get('condition') || null;

            if (isRentMode()) {
                payload.price_per_day = Number(formData.get('price_per_day'));
                const deposit = formData.get('deposit_amount');
                payload.deposit_amount = deposit ? Number(deposit) : null;
            } else {
                payload[priceMode] = Number(formData.get('price'));
                payload.stock = Number(formData.get('stock') || 1);
            }
        } else {
            payload[priceMode] = Number(formData.get('price'));
            payload.duration = formData.get('duration') || '1 hora';
        }

        try {
            await apiFetch(`/${currentType}`, { method: 'POST', body: JSON.stringify(payload) });
            toast('Publicación creada con éxito.', 'success');
            form.reset();
            breakdownBox.classList.add('hidden');
            applyPricingVisibility();
            await reloadMe();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

async function reloadMe() {
    const { user } = await apiFetch('/me');
    me = user;
    renderProfile();
    renderListings();
    renderOrders();
    renderBookings();
    fillProfileForm();
}

export async function initDashboard() {
    if (!document.getElementById('dashboard-profile')) return;
    if (!requireAuth()) return;

    try {
        await reloadMe();
    } catch (error) {
        toast('No pudimos cargar tu panel. Inicia sesión nuevamente.', 'error');
        window.location.href = '/login';
        return;
    }

    try {
        const { data } = await apiFetch('/my-business');
        myBusiness = data;
    } catch (error) {
        myBusiness = null;
    }

    await loadCategories();
    setupCreateForm();
    bindListingActions();
    bindRentalActions();
    bindReviewActions();
    bindProfileForm();
    bindPasswordForm();

    await loadRentals();
    await loadOwnedRentals();
}
