import { apiFetch, getToken } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { openModal } from '../lib/modal.js';
import { addToCart, refreshCartBadge } from '../lib/cart.js';
import { addFavorite, removeFavorite } from '../lib/favorites.js';
import { starRating } from '../lib/reviews.js';
import { escapeHtml } from '../lib/escape.js';

const state = {
    type: 'products',
    categoryId: '',
    transactionType: '',
    search: '',
};

function requireLogin() {
    if (!getToken()) {
        window.location.href = `/login?next=${encodeURIComponent('/marketplace')}`;
        return false;
    }
    return true;
}

export function productCard(product) {
    const isRent = product.transaction_type === 'rent';
    const badge = isRent ? 'Alquiler' : 'Venta';
    const favorited = Boolean(product.is_favorited);
    const priceLabel = isRent ? `${money(product.price)}/día` : money(product.price);
    const title = escapeHtml(product.title);
    const description = escapeHtml(product.description);
    const location = escapeHtml(product.location || '');
    const businessName = escapeHtml(product.business?.name || '');

    const actions = isRent
        ? `
            <button type="button" data-action="rent" data-id="${product.id}" data-title="${title}"
                class="w-full bg-indigo-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">
                Solicitar alquiler
            </button>
        `
        : `
            <div class="flex gap-2">
                <button type="button" data-action="add-to-cart" data-id="${product.id}"
                    class="flex-1 border border-emerald-600 text-emerald-700 text-sm font-semibold rounded-lg py-2 hover:bg-emerald-50 transition">
                    Agregar al carrito
                </button>
                <button type="button" data-action="order" data-id="${product.id}" data-title="${title}"
                    class="flex-1 bg-emerald-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">
                    Comprar
                </button>
            </div>
        `;

    return `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
            <a href="/producto/${product.slug}" class="aspect-[4/3] bg-slate-100 relative block">
                <img src="${product.image_url || 'https://placehold.co/600x450?text=Mercova'}" alt="${title}" class="w-full h-full object-cover">
                <button type="button" data-action="toggle-favorite" data-id="${product.id}" data-favorited="${favorited ? '1' : '0'}"
                    class="absolute top-2 right-2 h-8 w-8 rounded-full bg-white/90 flex items-center justify-center shadow hover:scale-105 transition">
                    <svg class="h-4 w-4 ${favorited ? 'fill-red-500 text-red-500' : 'fill-none text-slate-400'}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-9.5-8.5C.5 9 2 5 5.5 5c2 0 3.5 1.2 4.5 2.8C11 6.2 12.5 5 14.5 5 18 5 19.5 9 19.5 12.5 17 16.65 12 21 12 21z" />
                    </svg>
                </button>
            </a>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-emerald-600">${badge}</span>
                    ${businessName ? `<span class="text-xs text-indigo-500 font-medium">${businessName}</span>` : ''}
                </div>
                <h3 class="font-semibold text-slate-800"><a href="/producto/${product.slug}" class="hover:text-emerald-600">${title}</a></h3>
                <p class="text-sm text-slate-500 mt-1 line-clamp-2">${description}</p>
                <div class="mt-1">${starRating(Number(product.reviews_avg_rating) || 0, product.reviews_count || 0)}</div>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-emerald-700 font-bold">${priceLabel}</span>
                    <span class="text-xs text-slate-400">${location}</span>
                </div>
                <div class="mt-4">${actions}</div>
            </div>
        </div>
    `;
}

function serviceCard(service) {
    const title = escapeHtml(service.title);
    const description = escapeHtml(service.description);
    const location = escapeHtml(service.location || '');
    const businessName = escapeHtml(service.business?.name || '');
    const duration = escapeHtml(service.duration);

    return `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
            <div class="aspect-[4/3] bg-slate-100">
                <img src="${service.image_url || 'https://placehold.co/600x450?text=Mercova'}" alt="${title}" class="w-full h-full object-cover">
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Servicio · ${duration}</span>
                    ${businessName ? `<span class="text-xs text-indigo-500 font-medium">${businessName}</span>` : ''}
                </div>
                <h3 class="font-semibold text-slate-800">${title}</h3>
                <p class="text-sm text-slate-500 mt-1 line-clamp-2">${description}</p>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-indigo-700 font-bold">${money(service.price)}</span>
                    <span class="text-xs text-slate-400">${location}</span>
                </div>
                <button type="button" data-action="book" data-id="${service.id}" data-title="${title}" data-price="${service.price}"
                    class="mt-4 w-full bg-indigo-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">
                    Reservar
                </button>
            </div>
        </div>
    `;
}

async function loadCategories() {
    const select = document.getElementById('filter-category');
    if (!select) return;

    try {
        const categories = await apiFetch('/categories');
        select.innerHTML = '<option value="">Todas las categorías</option>' + categories.map((category) => (
            `<option value="${category.id}">${escapeHtml(category.name)}</option>`
        )).join('');

        const params = new URLSearchParams(window.location.search);
        if (params.get('category_id')) {
            select.value = params.get('category_id');
            state.categoryId = params.get('category_id');
        }
    } catch (error) {
        // filters degrade gracefully if categories fail to load
    }
}

async function loadListings() {
    const grid = document.getElementById('listing-grid');
    const emptyState = document.getElementById('listing-empty');
    if (!grid) return;

    grid.innerHTML = '<p class="col-span-full text-slate-400 text-center py-10">Cargando...</p>';
    emptyState?.classList.add('hidden');

    const params = new URLSearchParams();
    if (state.categoryId) params.set('category_id', state.categoryId);
    if (state.search) params.set('search', state.search);
    if (state.type === 'products' && state.transactionType) params.set('transaction_type', state.transactionType);

    try {
        const { data } = await apiFetch(`/${state.type}?${params.toString()}`);
        const items = data.data || [];

        if (!items.length) {
            grid.innerHTML = '';
            emptyState?.classList.remove('hidden');
            return;
        }

        grid.innerHTML = items.map(state.type === 'products' ? productCard : serviceCard).join('');
    } catch (error) {
        grid.innerHTML = `<p class="col-span-full text-red-500 text-center py-10">${error.message}</p>`;
    }
}

export async function openOrderModal(id, title) {
    const { data: paymentMethods } = await apiFetch('/payment-methods');
    const options = Object.entries(paymentMethods).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');

    const { body, close } = openModal(`Comprar "${title}"`, `
        <form id="order-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad</label>
                <input type="number" name="quantity" min="1" value="1" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago</label>
                <select name="payment_method" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    ${options}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">Confirmar compra</button>
        </form>
    `);

    body.querySelector('#order-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);

        try {
            const result = await apiFetch('/orders', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: id,
                    quantity: Number(formData.get('quantity')),
                    payment_method: formData.get('payment_method'),
                    notes: formData.get('notes') || null,
                }),
            });

            if (result.payment_url) {
                toast('Compra creada. Redirigiendo a la pasarela de pago...', 'success');
                close();
                window.location.href = result.payment_url;
                return;
            }

            toast('¡Compra realizada con éxito!', 'success');
            close();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

export async function openRentalModal(id, title) {
    const { data: paymentMethods } = await apiFetch('/payment-methods');
    const options = Object.entries(paymentMethods).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    const today = new Date().toISOString().slice(0, 10);

    const { body, close } = openModal(`Solicitar alquiler de "${title}"`, `
        <form id="rental-form" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Desde</label>
                    <input type="date" name="start_date" min="${today}" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Hasta</label>
                    <input type="date" name="end_date" min="${today}" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <p id="rental-availability" class="text-sm"></p>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago</label>
                <select name="payment_method" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    ${options}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
            <button type="submit" id="rental-submit" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2 hover:bg-indigo-700 transition" disabled>
                Enviar solicitud
            </button>
        </form>
    `);

    const form = body.querySelector('#rental-form');
    const availabilityEl = body.querySelector('#rental-availability');
    const submitBtn = body.querySelector('#rental-submit');

    async function checkAvailability() {
        const startDate = form.elements.start_date.value;
        const endDate = form.elements.end_date.value;

        if (!startDate || !endDate || endDate < startDate) {
            availabilityEl.textContent = '';
            submitBtn.disabled = true;
            return;
        }

        try {
            const { data } = await apiFetch(`/products/${id}/availability?start_date=${startDate}&end_date=${endDate}`);
            if (data.available) {
                availabilityEl.textContent = 'Disponible para esas fechas.';
                availabilityEl.className = 'text-sm text-emerald-600';
                submitBtn.disabled = false;
            } else {
                availabilityEl.textContent = 'Ya reservado en esas fechas. Elige otro rango.';
                availabilityEl.className = 'text-sm text-red-500';
                submitBtn.disabled = true;
            }
        } catch (error) {
            availabilityEl.textContent = '';
            submitBtn.disabled = true;
        }
    }

    form.elements.start_date.addEventListener('change', checkAvailability);
    form.elements.end_date.addEventListener('change', checkAvailability);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        try {
            await apiFetch('/rentals', {
                method: 'POST',
                body: JSON.stringify({
                    product_id: Number(id),
                    start_date: formData.get('start_date'),
                    end_date: formData.get('end_date'),
                    payment_method: formData.get('payment_method'),
                    notes: formData.get('notes') || null,
                }),
            });
            toast('Solicitud de alquiler enviada. El propietario debe confirmarla.', 'success');
            close();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function openBookingModal(id, title) {
    const { body, close } = openModal(`Reservar "${title}"`, `
        <form id="booking-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fecha y hora</label>
                <input type="datetime-local" name="scheduled_at" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad de personas</label>
                <input type="number" name="guest_count" min="1" value="1" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">Confirmar reserva</button>
        </form>
    `);

    body.querySelector('#booking-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);

        try {
            await apiFetch(`/services/${id}/book`, {
                method: 'POST',
                body: JSON.stringify({
                    scheduled_at: formData.get('scheduled_at'),
                    guest_count: Number(formData.get('guest_count')),
                    notes: formData.get('notes') || null,
                }),
            });
            toast('¡Reserva realizada con éxito!', 'success');
            close();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

export async function initMarketplace() {
    const grid = document.getElementById('listing-grid');
    if (!grid) return;

    const params = new URLSearchParams(window.location.search);
    if (params.get('type') === 'services') state.type = 'services';
    if (params.get('transaction_type')) state.transactionType = params.get('transaction_type');
    if (params.get('search')) state.search = params.get('search');

    const tabProducts = document.getElementById('tab-products');
    const tabServices = document.getElementById('tab-services');
    const transactionFilter = document.getElementById('filter-transaction-type');
    const searchInput = document.getElementById('filter-search');
    const categorySelect = document.getElementById('filter-category');

    const setActiveTab = () => {
        const isProducts = state.type === 'products';
        tabProducts.classList.toggle('bg-emerald-600', isProducts);
        tabProducts.classList.toggle('text-white', isProducts);
        tabProducts.classList.toggle('text-slate-600', !isProducts);
        tabServices.classList.toggle('bg-indigo-600', !isProducts);
        tabServices.classList.toggle('text-white', !isProducts);
        tabServices.classList.toggle('text-slate-600', isProducts);
        transactionFilter?.classList.toggle('hidden', !isProducts);
    };

    tabProducts?.addEventListener('click', () => { state.type = 'products'; setActiveTab(); loadListings(); });
    tabServices?.addEventListener('click', () => { state.type = 'services'; setActiveTab(); loadListings(); });

    categorySelect?.addEventListener('change', (event) => { state.categoryId = event.target.value; loadListings(); });
    transactionFilter?.addEventListener('change', (event) => { state.transactionType = event.target.value; loadListings(); });

    let searchTimeout;
    searchInput?.addEventListener('input', (event) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.search = event.target.value;
            loadListings();
        }, 350);
    });

    grid.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        event.preventDefault();
        if (!requireLogin()) return;

        const { action, id, title } = button.dataset;
        if (action === 'order') openOrderModal(id, title);
        if (action === 'book') openBookingModal(id, title);
        if (action === 'rent') openRentalModal(id, title);
        if (action === 'add-to-cart') {
            try {
                await addToCart(Number(id), 1);
                await refreshCartBadge();
                toast('Producto agregado al carrito.', 'success');
            } catch (error) {
                toast(error.message, 'error');
            }
        }
        if (action === 'toggle-favorite') {
            const favorited = button.dataset.favorited === '1';
            try {
                if (favorited) {
                    await removeFavorite(id);
                } else {
                    await addFavorite(id);
                }
                button.dataset.favorited = favorited ? '0' : '1';
                const svg = button.querySelector('svg');
                svg.classList.toggle('fill-red-500', !favorited);
                svg.classList.toggle('text-red-500', !favorited);
                svg.classList.toggle('fill-none', favorited);
                svg.classList.toggle('text-slate-400', favorited);
            } catch (error) {
                toast(error.message, 'error');
            }
        }
    });

    setActiveTab();
    if (searchInput) searchInput.value = state.search;
    await loadCategories();
    await loadListings();
}
