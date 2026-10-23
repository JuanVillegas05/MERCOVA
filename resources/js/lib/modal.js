import { escapeHtml } from './escape.js';

export function openModal(title, bodyHtml) {
    const existing = document.getElementById('mercova-modal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'mercova-modal';
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4';
    overlay.innerHTML = `
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
            <button type="button" data-modal-close class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            <h3 class="text-lg font-bold text-slate-800 mb-4">${escapeHtml(title)}</h3>
            <div data-modal-body>${bodyHtml}</div>
        </div>
    `;

    document.body.appendChild(overlay);

    const close = () => overlay.remove();
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) close();
    });
    overlay.querySelector('[data-modal-close]').addEventListener('click', close);

    return { overlay, close, body: overlay.querySelector('[data-modal-body]') };
}
