import { apiFetch, getToken } from '../lib/api.js';
import { money } from '../lib/money.js';
import { toast } from '../lib/toast.js';
import { fetchCurrentUser } from '../lib/auth.js';
import { escapeHtml } from '../lib/escape.js';

function planCard(plan, { activePlanId, canSubscribe } = {}) {
    const isCurrent = activePlanId === plan.id;
    const features = (plan.features || []).map((feature) => `<li class="flex gap-2 items-start"><span class="text-emerald-500">✓</span><span>${escapeHtml(feature)}</span></li>`).join('');

    return `
        <div class="bg-white rounded-2xl border ${isCurrent ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200'} p-6 flex flex-col">
            ${isCurrent ? '<span class="self-start mb-2 text-xs font-semibold uppercase tracking-wide bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full">Plan actual</span>' : ''}
            <h3 class="text-lg font-bold text-slate-800">${escapeHtml(plan.name)}</h3>
            <p class="text-slate-500 text-sm mt-1 mb-4">${escapeHtml(plan.description || '')}</p>
            <p class="text-3xl font-extrabold text-slate-900 mb-1">
                ${Number(plan.price) === 0 ? 'Gratis' : money(plan.price)}
                ${Number(plan.price) > 0 ? `<span class="text-sm font-medium text-slate-400">/ ${plan.billing_period}</span>` : ''}
            </p>
            <ul class="text-sm text-slate-600 space-y-2 my-4 flex-1">${features}</ul>
            <button type="button" data-action="subscribe" data-plan-id="${plan.id}"
                class="w-full rounded-lg py-2 font-semibold transition ${isCurrent ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-emerald-600 text-white hover:bg-emerald-700'}"
                ${isCurrent || !canSubscribe ? 'disabled' : ''}>
                ${isCurrent ? 'Plan activo' : 'Elegir plan'}
            </button>
        </div>
    `;
}

function businessCard(business) {
    const name = escapeHtml(business.name);
    return `
        <a href="/marketplace" class="block bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4 hover:shadow-md transition">
            <img src="${business.logo_url || 'https://placehold.co/80x80?text=Biz'}" alt="${name}" class="w-14 h-14 rounded-lg object-cover bg-slate-100">
            <div>
                <p class="font-semibold text-slate-800">${name}</p>
                <p class="text-xs text-slate-500">${escapeHtml(business.category || 'Emprendimiento')}</p>
            </div>
        </a>
    `;
}

async function loadPlans(context) {
    const container = document.getElementById('plans-grid');
    if (!container) return;

    try {
        const { data: plans } = await apiFetch('/membership-plans');
        container.innerHTML = plans.map((plan) => planCard(plan, context)).join('');

        container.querySelectorAll('[data-action="subscribe"]').forEach((btn) => {
            btn.addEventListener('click', () => context.onSubscribe(btn.dataset.planId));
        });
    } catch (error) {
        container.innerHTML = `<p class="text-red-500 col-span-full">${error.message}</p>`;
    }
}

async function loadDirectory() {
    const container = document.getElementById('business-directory');
    if (!container) return;

    try {
        const { data: businesses } = await apiFetch('/businesses');
        container.innerHTML = businesses.length
            ? businesses.map(businessCard).join('')
            : '<p class="text-slate-400 col-span-full">Todavía no hay emprendimientos activos. ¡Sé el primero!</p>';
    } catch (error) {
        container.innerHTML = `<p class="text-red-500 col-span-full">${error.message}</p>`;
    }
}

function renderBusinessPanel(business) {
    const panel = document.getElementById('business-panel');
    const form = document.getElementById('business-form-wrapper');
    if (!panel || !form) return;

    if (!business) {
        panel.classList.add('hidden');
        form.classList.remove('hidden');
        return;
    }

    form.classList.add('hidden');
    panel.classList.remove('hidden');

    const isActive = business.status === 'active';
    const expiry = business.membership_expires_at
        ? new Date(business.membership_expires_at).toLocaleDateString('es-CO')
        : null;

    const name = escapeHtml(business.name);
    panel.innerHTML = `
        <div class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
            <div class="flex items-center gap-4">
                <img src="${business.logo_url || 'https://placehold.co/64x64?text=Biz'}" class="w-16 h-16 rounded-lg object-cover bg-slate-100" alt="${name}">
                <div>
                    <p class="font-bold text-lg text-slate-800">${name}</p>
                    <p class="text-sm text-slate-500">${escapeHtml(business.category || '')}</p>
                    <span class="inline-block mt-1 text-xs font-semibold px-2 py-1 rounded-full ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">
                        ${isActive ? `Membresía activa${expiry ? ` · vence ${expiry}` : ''}` : 'Sin membresía activa'}
                    </span>
                </div>
            </div>
            <a href="/panel" class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition text-center">
                Publicar productos y servicios
            </a>
        </div>
    `;
}

export async function initEmprendimientos() {
    const grid = document.getElementById('plans-grid');
    if (!grid) return;

    const user = await fetchCurrentUser();
    const businessSection = document.getElementById('business-section');
    const guestCta = document.getElementById('emprendimientos-guest-cta');

    let myBusiness = null;

    if (user) {
        guestCta?.classList.add('hidden');
        businessSection?.classList.remove('hidden');

        try {
            const { data } = await apiFetch('/my-business');
            myBusiness = data;
        } catch (error) {
            myBusiness = null;
        }

        renderBusinessPanel(myBusiness);

        const businessForm = document.getElementById('business-form');
        businessForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(businessForm);

            try {
                const { data } = await apiFetch('/businesses', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: formData.get('name'),
                        description: formData.get('description') || null,
                        category: formData.get('category') || null,
                        phone: formData.get('phone') || null,
                        address: formData.get('address') || null,
                    }),
                });
                toast('Emprendimiento registrado. Elige tu membresía para publicar.', 'success');
                myBusiness = data;
                renderBusinessPanel(myBusiness);
                await loadPlans({ activePlanId: null, canSubscribe: true, onSubscribe: (planId) => subscribe(planId) });
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    } else {
        businessSection?.classList.add('hidden');
        guestCta?.classList.remove('hidden');
    }

    async function subscribe(planId) {
        if (!myBusiness) {
            toast('Registra tu emprendimiento antes de elegir una membresía.', 'error');
            return;
        }

        try {
            const { data } = await apiFetch(`/businesses/${myBusiness.id}/subscribe`, {
                method: 'POST',
                body: JSON.stringify({ membership_plan_id: Number(planId) }),
            });
            toast('¡Membresía activada con éxito!', 'success');
            myBusiness = data;
            renderBusinessPanel(myBusiness);
            await loadPlans({
                activePlanId: myBusiness.membership_plan_id,
                canSubscribe: Boolean(getToken()),
                onSubscribe: subscribe,
            });
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    await loadPlans({
        activePlanId: myBusiness?.membership_plan_id ?? null,
        canSubscribe: Boolean(user),
        onSubscribe: (planId) => {
            if (!getToken()) {
                window.location.href = `/login?next=${encodeURIComponent('/emprendimientos')}`;
                return;
            }
            subscribe(planId);
        },
    });

    await loadDirectory();
}
