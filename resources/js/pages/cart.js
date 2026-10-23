import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { requireAuth } from '../lib/auth.js';
import { fetchCart, updateCartItem, removeCartItem, refreshCartBadge } from '../lib/cart.js';
import { escapeHtml } from '../lib/escape.js';

function itemRow(item) {
    const product = item.product;
    if (!product) return '';
    const title = escapeHtml(product.title);

    return `
        <div class="flex items-center gap-4 p-4" data-cart-row="${item.id}">
            <img src="${product.image_url || 'https://placehold.co/100x100?text=Mercova'}" alt="${title}" class="w-16 h-16 rounded-lg object-cover bg-slate-100">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-slate-800 truncate">${title}</p>
                <p class="text-xs text-slate-400">${money(product.price)} c/u · ${product.transaction_type === 'rent' ? 'Alquiler' : 'Venta'}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-action="decrement" data-id="${item.id}" data-qty="${item.quantity}" class="w-7 h-7 rounded border border-slate-200 text-slate-500 hover:bg-slate-50">-</button>
                <span class="w-8 text-center text-sm font-semibold">${item.quantity}</span>
                <button type="button" data-action="increment" data-id="${item.id}" data-qty="${item.quantity}" data-stock="${item.available_stock ?? ''}" class="w-7 h-7 rounded border border-slate-200 text-slate-500 hover:bg-slate-50">+</button>
            </div>
            <div class="w-28 text-right font-semibold text-slate-800">${money(item.line_total)}</div>
            <button type="button" data-action="remove" data-id="${item.id}" class="text-red-400 hover:text-red-600 text-sm">Eliminar</button>
        </div>
    `;
}

function render(cart) {
    const container = document.getElementById('cart-items');
    const checkoutSection = document.getElementById('checkout-section');
    const items = cart.items || [];

    container.innerHTML = items.length
        ? items.map(itemRow).join('')
        : '<p class="p-10 text-slate-400 text-center">Tu carrito está vacío. <a href="/marketplace" class="text-emerald-600 font-medium hover:underline">Explora el marketplace</a>.</p>';

    document.getElementById('cart-subtotal').textContent = money(cart.subtotal);
    document.getElementById('cart-total').textContent = money(cart.total);
    checkoutSection.classList.toggle('hidden', items.length === 0);
}

async function reload() {
    const cart = await fetchCart();
    render(cart);
    await refreshCartBadge();
    return cart;
}

async function loadPaymentMethods() {
    const select = document.getElementById('checkout-payment-method');
    if (!select) return;
    const { data } = await apiFetch('/payment-methods');
    select.innerHTML = Object.entries(data).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
}

function bindCartActions() {
    const container = document.getElementById('cart-items');
    container.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action]');
        if (!btn) return;

        const { action, id, qty, stock } = btn.dataset;

        try {
            if (action === 'remove') {
                await removeCartItem(id);
            }
            if (action === 'increment') {
                const next = Number(qty) + 1;
                if (stock && next > Number(stock)) {
                    toast(`Solo quedan ${stock} unidades disponibles.`, 'error');
                    return;
                }
                await updateCartItem(id, next);
            }
            if (action === 'decrement') {
                const next = Number(qty) - 1;
                if (next < 1) {
                    await removeCartItem(id);
                } else {
                    await updateCartItem(id, next);
                }
            }
            await reload();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function bindCheckoutForm() {
    const form = document.getElementById('checkout-form');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        try {
            const result = await apiFetch('/checkout', {
                method: 'POST',
                body: JSON.stringify({
                    recipient_name: formData.get('recipient_name'),
                    recipient_phone: formData.get('recipient_phone'),
                    address: formData.get('address'),
                    city: formData.get('city'),
                    department: formData.get('department'),
                    payment_method: formData.get('payment_method'),
                    notes: formData.get('notes') || null,
                }),
            });
            form.reset();
            await reload();

            if (result.payment_url) {
                toast('Pedido creado. Redirigiendo a la pasarela de pago...', 'success');
                window.location.href = result.payment_url;
                return;
            }

            toast('¡Pedido realizado con éxito!', 'success');
            window.location.href = '/panel';
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

export async function initCart() {
    if (!document.getElementById('cart-items')) return;
    if (!requireAuth()) return;

    bindCartActions();
    bindCheckoutForm();
    await loadPaymentMethods();

    try {
        await reload();
    } catch (error) {
        toast('No pudimos cargar tu carrito.', 'error');
    }
}
