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
 * Escapa texto libre del usuario antes de insertarlo dentro de un ATRIBUTO HTML.
 *
 * `escapeHtml` no alcanza acá: se apoya en `textContent -> innerHTML`, que solo
 * convierte &, < y >. Las comillas pasan intactas, así que en un contexto como
 * `data-nombre="${valor}"` un valor con " cierra el atributo y permite inyectar
 * otros nuevos (onmouseover=...) sin necesidad de un solo `<`.
 *
 * Usar en todo `innerHTML` que interpole datos de usuario dentro de comillas;
 * `escapeHtml` sigue siendo el correcto para posiciones de texto.
 */
window.escapeAttr = (str) => window.escapeHtml(str)
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

/**
 * Mensaje legible para un código HTTP de error.
 */
window.httpStatusMessage = (status) => {
    if (status === 400) return 'La solicitud no es válida. Revisa los datos e intenta de nuevo.';
    if (status === 401) return 'Tu sesión expiró. Vuelve a iniciar sesión.';
    if (status === 403) return 'No tienes permiso para hacer esto, o el token de seguridad venció. Recarga la página.';
    if (status === 404) return 'El recurso solicitado no existe.';
    if (status === 413) return 'El archivo es demasiado grande para el servidor.';
    if (status === 429) return 'Demasiados intentos. Espera un momento e intenta de nuevo.';
    if (status >= 500) return 'El servidor tuvo un error interno (' + status + '). Intenta de nuevo en unos minutos.';
    return 'El servidor respondió con un error (' + status + ').';
};

/**
 * Reemplaza a `await response.json()` en TODO fetch que espere JSON.
 *
 * Problema que resuelve: cuando la API devuelve un 500 con cuerpo HTML (un
 * fatal de PHP, una página de error del hosting), `response.json()` explota con
 * "SyntaxError: Unexpected token '<'" — un error críptico que además no
 * distingue "falló la red" de "el servidor se cayó".
 *
 * Ojo con el orden: NO se puede cortar en `!response.ok` antes de leer el
 * cuerpo, porque la API sí manda JSON útil con status de error (429 rate limit,
 * 403 de CSRF, 500 del exception_handler, todos con un campo `error`). Se
 * intenta parsear primero y recién si no hay mensaje del servidor se cae al
 * texto genérico por código.
 *
 * Siempre lanza `Error` en el camino de fallo, así el `catch` que ya existe en
 * el llamador lo muestra en un modal sin cambios extra.
 */
window.parseJsonResponse = async (response, fallback = 'No se pudo procesar la respuesta del servidor.') => {
    let data = null;
    try {
        data = await response.json();
    } catch (_) {
        data = null;
    }

    if (!response.ok) {
        const serverMsg = data && (data.error || data.message);
        throw new Error(serverMsg || window.httpStatusMessage(response.status));
    }

    if (data === null || typeof data !== 'object') {
        throw new Error(fallback);
    }

    return data;
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
