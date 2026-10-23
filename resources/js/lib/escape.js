const ESCAPE_MAP = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/**
 * Escapa contenido controlado por el usuario antes de insertarlo en una plantilla HTML.
 * Todo texto proveniente de la API (títulos, descripciones, nombres, comentarios, notas)
 * debe pasar por aquí: nunca se sabe qué escribió un vendedor/comprador en ese campo.
 */
export function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, (char) => ESCAPE_MAP[char]);
}
