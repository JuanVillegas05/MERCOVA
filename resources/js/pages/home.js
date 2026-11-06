import { apiFetch } from '../lib/api.js';
import { money } from '../lib/money.js';
import { escapeHtml } from '../lib/escape.js';

function productCard(product) {
    const title = escapeHtml(product.title);
    return `
        <a href="/producto/${product.slug}" class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition group">
            <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
                <img src="${product.image_url || 'https://placehold.co/600x450?text=Mercova'}" alt="${title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
            </div>
            <div class="p-4">
                <span class="inline-block text-xs font-semibold uppercase tracking-wide text-emerald-600 mb-1">${product.transaction_type === 'rent' ? 'Alquiler' : 'Venta'}</span>
                <h3 class="font-semibold text-slate-800 truncate">${title}</h3>
                <p class="text-emerald-700 font-bold mt-1">${money(product.price)}</p>
            </div>
        </a>
    `;
}

function planCard(plan, { highlight } = {}) {
    const features = (plan.features || []).map((feature) => `<li class="flex gap-2 items-start"><span class="text-emerald-500">✓</span><span>${escapeHtml(feature)}</span></li>`).join('');
    const isFree = Number(plan.price) === 0;

    return `
        <div class="rounded-2xl p-6 flex flex-col bg-white ${highlight ? 'ring-2 ring-emerald-500 shadow-xl sm:-translate-y-2' : 'shadow-sm'}">
            ${highlight ? '<span class="self-start mb-2 text-xs font-semibold uppercase tracking-wide bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full">Más elegido</span>' : ''}
            <h3 class="text-lg font-bold text-slate-800">${escapeHtml(plan.name)}</h3>
            <p class="text-slate-500 text-sm mt-1 mb-4">${escapeHtml(plan.description || '')}</p>
            <p class="text-3xl font-extrabold text-slate-900 mb-1">
                ${isFree ? 'Gratis' : money(plan.price)}
                ${!isFree ? `<span class="text-sm font-medium text-slate-400">/ ${escapeHtml(plan.billing_period)}</span>` : ''}
            </p>
            <ul class="text-sm text-slate-600 space-y-2 my-4 flex-1">${features}</ul>
            <a href="/emprendimientos" class="w-full text-center rounded-lg py-2.5 font-semibold transition ${highlight ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}">
                ${isFree ? 'Empezar gratis' : 'Elegir plan'}
            </a>
        </div>
    `;
}

function serviceCard(service) {
    const title = escapeHtml(service.title);
    return `
        <a href="/marketplace?type=services" class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition group">
            <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
                <img src="${service.image_url || 'https://placehold.co/600x450?text=Mercova'}" alt="${title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
            </div>
            <div class="p-4">
                <span class="inline-block text-xs font-semibold uppercase tracking-wide text-indigo-600 mb-1">Servicio · ${escapeHtml(service.duration)}</span>
                <h3 class="font-semibold text-slate-800 truncate">${title}</h3>
                <p class="text-indigo-700 font-bold mt-1">${money(service.price)}</p>
            </div>
        </a>
    `;
}

export async function initHome() {
    const categoriesEl = document.getElementById('home-categories');
    const productsEl = document.getElementById('home-featured-products');
    const servicesEl = document.getElementById('home-featured-services');
    const plansEl = document.getElementById('home-plans');

    try {
        const categories = await apiFetch('/categories');
        if (categoriesEl) {
            categoriesEl.innerHTML = categories.map((category) => `
                <a href="/marketplace?category_id=${category.id}" class="px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-medium text-slate-600 hover:border-emerald-500 hover:text-emerald-600 transition whitespace-nowrap">
                    ${escapeHtml(category.name)}
                </a>
            `).join('');
        }
    } catch (error) {
        // categories are a progressive enhancement, safe to ignore failures
    }

    try {
        const { data } = await apiFetch('/products?per_page=4');
        const featured = (data.data || []).slice(0, 4);
        if (productsEl) {
            productsEl.innerHTML = featured.length
                ? featured.map(productCard).join('')
                : '<p class="text-slate-500 col-span-full">Todavía no hay productos publicados.</p>';
        }
    } catch (error) {
        if (productsEl) productsEl.innerHTML = '<p class="text-slate-500 col-span-full">No pudimos cargar los productos.</p>';
    }

    try {
        const { data } = await apiFetch('/services?per_page=3');
        const featured = (data.data || []).slice(0, 3);
        if (servicesEl) {
            servicesEl.innerHTML = featured.length
                ? featured.map(serviceCard).join('')
                : '<p class="text-slate-500 col-span-full">Todavía no hay servicios publicados.</p>';
        }
    } catch (error) {
        if (servicesEl) servicesEl.innerHTML = '<p class="text-slate-500 col-span-full">No pudimos cargar los servicios.</p>';
    }

    try {
        const { data: plans } = await apiFetch('/membership-plans');
        if (plansEl) {
            plansEl.innerHTML = plans.length
                ? plans.map((plan, index) => planCard(plan, { highlight: index === 1 })).join('')
                : '<p class="text-slate-400 col-span-full">Todavía no hay planes disponibles.</p>';
        }
    } catch (error) {
        if (plansEl) plansEl.innerHTML = '<p class="text-slate-400 col-span-full">No pudimos cargar los planes.</p>';
    }
}
