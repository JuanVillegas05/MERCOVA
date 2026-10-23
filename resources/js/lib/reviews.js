import { apiFetch } from './api.js';

export async function submitReview(productId, rating, comment) {
    return apiFetch('/reviews', {
        method: 'POST',
        body: JSON.stringify({ product_id: productId, rating, comment: comment || null }),
    });
}

export function starRating(average, count) {
    if (!count) return '<span class="text-xs text-slate-400">Sin calificaciones todavía</span>';

    const rounded = Math.round(average);
    const stars = Array.from({ length: 5 }, (_, i) => (i < rounded ? '★' : '☆')).join('');

    return `<span class="text-amber-500 text-sm">${stars}</span> <span class="text-xs text-slate-400">${average.toFixed(1)} (${count})</span>`;
}
