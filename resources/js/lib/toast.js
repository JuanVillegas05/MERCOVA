export function toast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const colors = {
        info: 'bg-slate-800',
        success: 'bg-emerald-600',
        error: 'bg-red-600',
    };

    const el = document.createElement('div');
    el.className = `${colors[type] || colors.info} text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg transition-opacity duration-300`;
    el.textContent = message;
    container.appendChild(el);

    setTimeout(() => {
        el.classList.add('opacity-0');
        setTimeout(() => el.remove(), 300);
    }, 3500);
}
