/**
 * Helpers de DOM/errores compartidos por todas las páginas.
 *
 * Se carga desde footer.php en el bloque fijo (junto a modalUtils.js), antes
 * de cualquier script de página, así que está disponible en todos lados sin
 * depender del orden de $pageScripts.
 */

/**
 * Escapa texto libre del usuario antes de insertarlo como HTML.
 *
 * Necesario en cualquier `innerHTML` que interpole datos que vinieron de un
 * formulario o de la BD: sin esto, un nombre de beneficiario tipo
 * "<img src=x onerror=...>" ejecuta JS en la sesión de quien lo vea (XSS
 * almacenado, encontrado en la auditoría del 2026-08-21).
 */
window.escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
};

/**
 * Convierte el error crudo de un fetch en un mensaje que el usuario entienda.
 *
 * "Failed to fetch" es lo que tira el navegador cuando la request no llega a
 * completarse (sin conexión, timeout, archivo muy pesado) — mostrarlo tal cual
 * no le dice nada a nadie. Este chequeo estaba copiado a mano en varios
 * archivos; acá queda en un solo lugar.
 */
window.formatNetworkError = (error, fallback = 'Ocurrió un error inesperado. Intenta de nuevo.') => {
    const msg = (error && error.message) ? error.message : '';
    if (!msg) return fallback;
    if (msg.includes('Failed to fetch') || msg.includes('NetworkError') || msg.includes('Load failed')) {
        return 'No se pudo conectar con el servidor. Verifica tu conexión a internet e intenta de nuevo.';
    }
    return msg;
};
