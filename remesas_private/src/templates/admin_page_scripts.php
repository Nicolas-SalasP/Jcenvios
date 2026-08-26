<?php
/**
 * Lista de módulos JS del panel de administración.
 *
 * admin.js se dividió en 4 archivos (tenía 2089 líneas) y lo cargan 8 páginas
 * distintas — la lista vive acá para no repetirla, y para que agregar o
 * renombrar un módulo sea un solo cambio.
 *
 * EL ORDEN IMPORTA: admin.js va primero porque su IIFE inicial deja
 * window.cuentasDestino listo (leyendo #app-data) y define
 * window.refreshAdminTable / window.copyToClipboard, que los otros módulos
 * usan. Todo lo compartido entre módulos viaja por window.*, nunca por scope.
 *
 * Uso:
 *   $pageScripts = require __DIR__ . '/../../remesas_private/src/templates/admin_page_scripts.php';
 * o, para sumar el script propio de la página:
 *   $pageScripts = array_merge(require ..., ['operador-pendientes.js']);
 *
 * Todas las páginas cargan el set completo a propósito: cada sección ya está
 * guardada por un `if (!elemento) return;`, así que sobrar un módulo es
 * inocuo (solo bytes), mientras que faltar uno es un bug silencioso.
 */

return [
    'admin.js',
    'admin-usuarios.js',
    'admin-transacciones.js',
    'admin-docs-beneficiarios.js',
];
