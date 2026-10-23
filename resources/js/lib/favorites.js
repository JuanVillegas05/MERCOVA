import { apiFetch } from './api.js';

export async function fetchFavorites() {
    const { data } = await apiFetch('/favorites');
    return data;
}

export async function addFavorite(productId) {
    return apiFetch('/favorites', {
        method: 'POST',
        body: JSON.stringify({ product_id: productId }),
    });
}

export async function removeFavorite(productId) {
    return apiFetch(`/favorites/${productId}`, { method: 'DELETE' });
}
