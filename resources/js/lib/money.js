export function money(value) {
    const amount = Number(value || 0);
    const formatted = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(amount);

    return `${formatted} COP`;
}
