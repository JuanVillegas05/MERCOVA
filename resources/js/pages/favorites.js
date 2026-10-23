import { toast } from '../lib/toast.js';
import { requireAuth } from '../lib/auth.js';
import { addToCart, refreshCartBadge } from '../lib/cart.js';
import { fetchFavorites, removeFavorite } from '../lib/favorites.js';
import { productCard, openOrderModal, openRentalModal } from './marketplace.js';

async function loadFavorites() {
    const grid = document.getElementById('favorites-grid');
    const empty = document.getElementById('favorites-empty');
    if (!grid) return;

    try {
        const products = await fetchFavorites();

        if (!products.length) {
            grid.innerHTML = '';
            empty?.classList.remove('hidden');
            return;
        }

        empty?.classList.add('hidden');
        grid.innerHTML = products.map((product) => productCard({ ...product, is_favorited: true })).join('');
    } catch (error) {
        grid.innerHTML = `<p class="col-span-full text-red-500 text-center py-10">${error.message}</p>`;
    }
}

export async function initFavorites() {
    const grid = document.getElementById('favorites-grid');
    if (!grid) return;
    if (!requireAuth()) return;

    grid.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        event.preventDefault();

        const { action, id, title } = button.dataset;

        try {
            if (action === 'toggle-favorite') {
                await removeFavorite(id);
                toast('Producto eliminado de favoritos.', 'success');
                await loadFavorites();
            }
            if (action === 'add-to-cart') {
                await addToCart(Number(id), 1);
                await refreshCartBadge();
                toast('Producto agregado al carrito.', 'success');
            }
            if (action === 'order') {
                openOrderModal(id, title);
            }
            if (action === 'rent') {
                openRentalModal(id, title);
            }
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    await loadFavorites();
}
