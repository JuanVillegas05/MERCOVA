import { apiFetch, getToken } from './api.js';

export async function fetchCart() {
    const { data } = await apiFetch('/cart');
    return data;
}

export async function addToCart(productId, quantity = 1) {
    return apiFetch('/cart/items', {
        method: 'POST',
        body: JSON.stringify({ product_id: productId, quantity }),
    });
}

export async function updateCartItem(cartItemId, quantity) {
    return apiFetch(`/cart/items/${cartItemId}`, {
        method: 'PUT',
        body: JSON.stringify({ quantity }),
    });
}

export async function removeCartItem(cartItemId) {
    return apiFetch(`/cart/items/${cartItemId}`, { method: 'DELETE' });
}

export async function refreshCartBadge() {
    const badges = document.querySelectorAll('[data-cart-count]');
    if (!badges.length) return;

    if (!getToken()) {
        badges.forEach((badge) => badge.classList.add('hidden'));
        return;
    }

    try {
        const cart = await fetchCart();
        const count = (cart.items || []).reduce((sum, item) => sum + item.quantity, 0);
        badges.forEach((badge) => {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        });
    } catch (error) {
        badges.forEach((badge) => badge.classList.add('hidden'));
    }
}
