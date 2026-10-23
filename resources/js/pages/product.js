import { apiFetch, getToken } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { addToCart, refreshCartBadge } from '../lib/cart.js';
import { addFavorite, removeFavorite } from '../lib/favorites.js';
import { starRating } from '../lib/reviews.js';
import { openOrderModal, openRentalModal } from './marketplace.js';
import { escapeHtml } from '../lib/escape.js';

function requireLogin() {
    if (!getToken()) {
        window.location.href = `/login?next=${encodeURIComponent(window.location.pathname)}`;
        return false;
    }
    return true;
}

function slugFromUrl() {
    const parts = window.location.pathname.split('/').filter(Boolean);
    return parts[parts.length - 1];
}

function reviewItem(review) {
    const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
    return `
        <div class="py-4 border-b border-slate-100 last:border-0">
            <div class="flex items-center justify-between">
                <p class="font-medium text-slate-800">${escapeHtml(review.reviewer?.name ?? 'Usuario')}</p>
                <span class="text-amber-500 text-sm">${stars}</span>
            </div>
            ${review.comment ? `<p class="text-sm text-slate-500 mt-1">${escapeHtml(review.comment)}</p>` : ''}
            <p class="text-xs text-slate-400 mt-1">${new Date(review.created_at).toLocaleDateString('es-CO')}</p>
        </div>
    `;
}

async function renderReviews(productId) {
    const el = document.getElementById('product-reviews');
    if (!el) return;

    try {
        const { data, meta } = await apiFetch(`/products/${productId}/reviews`);

        el.innerHTML = `
            <h2 class="text-xl font-bold text-slate-900 mb-1">Reseñas</h2>
            <p class="text-sm text-slate-500 mb-4">${starRating(meta.average || 0, meta.count)}</p>
            <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 px-5">
                ${data.length ? data.map(reviewItem).join('') : '<p class="text-slate-400 text-sm py-6">Todavía no hay reseñas para este producto.</p>'}
            </div>
        `;
    } catch (error) {
        el.innerHTML = '';
    }
}

function renderProduct(product) {
    const el = document.getElementById('product-detail');
    if (!el) return;

    const isRent = product.transaction_type === 'rent';
    const badge = isRent ? 'Alquiler' : 'Venta';
    const priceLabel = isRent ? `${money(product.price_per_day)}/día` : money(product.price);
    const favorited = Boolean(product.is_favorited);
    const title = escapeHtml(product.title);
    const description = escapeHtml(product.description);
    const condition = escapeHtml(product.condition || '');
    const location = escapeHtml(product.location || '');
    const sellerName = escapeHtml(product.business?.name ?? product.seller?.name ?? '');

    const actions = isRent
        ? `
            <button type="button" data-action="rent" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2.5 hover:bg-indigo-700 transition">
                Solicitar alquiler
            </button>
            ${product.deposit_amount ? `<p class="text-xs text-slate-400 mt-2">Incluye depósito/garantía de ${money(product.deposit_amount)}.</p>` : ''}
        `
        : `
            <div class="flex gap-2">
                <button type="button" data-action="add-to-cart" class="flex-1 border border-emerald-600 text-emerald-700 font-semibold rounded-lg py-2.5 hover:bg-emerald-50 transition">
                    Agregar al carrito
                </button>
                <button type="button" data-action="order" class="flex-1 bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">
                    Comprar
                </button>
            </div>
            ${product.stock != null ? `<p class="text-xs text-slate-400 mt-2">${product.stock} unidades disponibles.</p>` : ''}
        `;

    el.innerHTML = `
        <div class="aspect-square bg-slate-100 rounded-xl overflow-hidden">
            <img src="${product.image_url || 'https://placehold.co/800x800?text=Mercova'}" alt="${title}" class="w-full h-full object-cover">
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-600">${badge}</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-action="share" class="text-sm text-slate-400 hover:text-emerald-600">Compartir</button>
                    <button type="button" data-action="toggle-favorite" data-favorited="${favorited ? '1' : '0'}" class="text-xl ${favorited ? 'text-red-500' : 'text-slate-300'} hover:text-red-500 transition">♥</button>
                </div>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900">${title}</h1>
            <div class="mt-1">${starRating(Number(product.reviews_avg_rating) || 0, product.reviews_count || 0)}</div>
            <p class="text-3xl font-bold text-emerald-700 mt-3">${priceLabel}</p>
            <p class="text-sm text-slate-500 mt-4 whitespace-pre-line">${description}</p>

            <div class="mt-4 text-sm text-slate-500 space-y-1">
                ${condition ? `<p><span class="font-medium text-slate-700">Condición:</span> ${condition}</p>` : ''}
                ${location ? `<p><span class="font-medium text-slate-700">Ubicación:</span> ${location}</p>` : ''}
                <p><span class="font-medium text-slate-700">Vendido por:</span> ${sellerName}</p>
            </div>

            <div class="mt-6">${actions}</div>
        </div>
    `;

    el.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const { action } = button.dataset;

        if (action === 'share') {
            const url = window.location.href;
            if (navigator.share) {
                navigator.share({ title: product.title, url }).catch(() => {});
            } else {
                await navigator.clipboard.writeText(url);
                toast('Enlace copiado al portapapeles.', 'success');
            }
            return;
        }

        if (!requireLogin()) return;

        if (action === 'order') openOrderModal(product.id, product.title);
        if (action === 'rent') openRentalModal(product.id, product.title);
        if (action === 'add-to-cart') {
            try {
                await addToCart(product.id, 1);
                await refreshCartBadge();
                toast('Producto agregado al carrito.', 'success');
            } catch (error) {
                toast(error.message, 'error');
            }
        }
        if (action === 'toggle-favorite') {
            const wasFavorited = button.dataset.favorited === '1';
            try {
                if (wasFavorited) {
                    await removeFavorite(product.id);
                } else {
                    await addFavorite(product.id);
                }
                button.dataset.favorited = wasFavorited ? '0' : '1';
                button.classList.toggle('text-red-500', !wasFavorited);
                button.classList.toggle('text-slate-300', wasFavorited);
            } catch (error) {
                toast(error.message, 'error');
            }
        }
    });
}

export async function initProduct() {
    const el = document.getElementById('product-detail');
    if (!el) return;

    const slug = slugFromUrl();

    try {
        const { data } = await apiFetch(`/products/${slug}`);
        document.title = `${data.title} · Mercova`;
        renderProduct(data);
        await renderReviews(data.id);
    } catch (error) {
        el.innerHTML = `<p class="col-span-full text-red-500 text-center py-20">${error.message}</p>`;
    }
}
