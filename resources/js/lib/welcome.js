import { escapeHtml } from './escape.js';

const STORAGE_KEY = 'mercova:welcome';

/**
 * Guarda el letrero de bienvenida para mostrarlo en la página a la que se
 * redirige después del registro (el redirect borraría cualquier cosa pintada
 * en la página de registro).
 */
export function queueWelcome(name, role) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ name, role }));
    } catch {
        // Sin sessionStorage (modo privado estricto) simplemente no se muestra.
    }
}

function takeQueuedWelcome() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        sessionStorage.removeItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

const content = {
    customer: {
        subtitle: 'Ya puedes explorar, comprar y alquilar productos y servicios de emprendedores locales.',
        steps: [
            ['🛍️', 'Explora el marketplace', 'Encuentra productos para comprar o alquilar.'],
            ['❤️', 'Guarda tus favoritos', 'Marca lo que te gusta para verlo después.'],
            ['📅', 'Reserva servicios', 'Agenda en el horario que mejor te quede.'],
        ],
        cta: { label: 'Empezar a explorar', href: '/marketplace' },
    },
    seller: {
        subtitle: 'Tu cuenta de vendedor está lista. Empieza a publicar y haz crecer tu negocio.',
        steps: [
            ['📦', 'Publica tu primer producto', 'Véndelo o alquílalo en minutos.'],
            ['🏪', 'Registra tu emprendimiento', 'Elige un plan y publica con tu marca.'],
            ['🔔', 'Recibe notificaciones', 'Te avisamos de cada compra o reserva.'],
        ],
        cta: { label: 'Ir a mi panel', href: '/panel' },
    },
};

/**
 * Si hay un letrero pendiente (recién registrado), lo muestra una sola vez.
 */
export function showPendingWelcome() {
    const welcome = takeQueuedWelcome();
    if (!welcome) return;

    const { subtitle, steps, cta } = content[welcome.role] || content.customer;
    const firstName = String(welcome.name || '').trim().split(/\s+/)[0];

    const overlay = document.createElement('div');
    overlay.id = 'mercova-welcome';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'mercova-welcome-title');
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 opacity-0 transition-opacity duration-300';
    overlay.innerHTML = `
        <div data-welcome-card class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl scale-95 transition-transform duration-300">
            <button type="button" data-welcome-close class="absolute top-3 right-3 z-10 rounded-full p-1 text-white/80 hover:bg-white/20 hover:text-white text-2xl leading-none" aria-label="Cerrar">&times;</button>

            <div class="relative bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 px-6 pt-10 pb-8 text-center text-white">
                <div class="absolute -top-10 -left-10 h-32 w-32 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-12 -right-8 h-40 w-40 rounded-full bg-white/10"></div>

                <span class="relative mx-auto mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-3xl font-extrabold text-emerald-600 shadow-lg">M</span>
                <p class="relative text-sm font-semibold uppercase tracking-widest text-emerald-100">¡Registro exitoso! 🎉</p>
                <h2 id="mercova-welcome-title" class="relative mt-1 text-3xl font-extrabold">
                    Bienvenido a Mercova${firstName ? `, ${escapeHtml(firstName)}` : ''}
                </h2>
                <p class="relative mx-auto mt-3 max-w-sm text-sm text-emerald-50">${subtitle}</p>
            </div>

            <div class="px-6 py-6">
                <ul class="space-y-3">
                    ${steps.map(([icon, title, text]) => `
                        <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <span class="text-2xl leading-none">${icon}</span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">${title}</span>
                                <span class="block text-xs text-slate-500">${text}</span>
                            </span>
                        </li>
                    `).join('')}
                </ul>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-welcome-close class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Más tarde</button>
                    <a href="${cta.href}" class="rounded-lg bg-emerald-600 px-5 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-700 transition">${cta.label}</a>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => {
        overlay.classList.remove('opacity-0');
        overlay.querySelector('[data-welcome-card]').classList.remove('scale-95');
    });

    const close = () => {
        overlay.classList.add('opacity-0');
        document.removeEventListener('keydown', onKeydown);
        setTimeout(() => overlay.remove(), 300);
    };
    const onKeydown = (event) => {
        if (event.key === 'Escape') close();
    };

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close();
    });
    overlay.querySelectorAll('[data-welcome-close]').forEach((btn) => btn.addEventListener('click', close));
    document.addEventListener('keydown', onKeydown);
}
