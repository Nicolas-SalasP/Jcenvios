<?php
/**
 * Helpers compartidos por la tabla de órdenes de Admin y Operador.
 *
 * Incluir SIEMPRE con require_once: orden_row.php se incluye una vez por fila,
 * así que un require normal redeclararía estas funciones.
 */

if (!function_exists('getStatusBadgeClass')) {
    /**
     * Clase de badge Bootstrap para un nombre de estado de transacción.
     * 'Pagado' y 'Rechazado' no están en el catálogo actual (estados_transaccion),
     * se mantienen como defensa ante datos históricos.
     */
    function getStatusBadgeClass($statusName)
    {
        switch ($statusName) {
            case 'Exitoso':
            case 'Pagado':
                return 'bg-success';
            case 'En Proceso':
                return 'bg-primary';
            case 'En Verificación':
                return 'bg-info text-dark';
            case 'Cancelado':
            case 'Rechazado':
                return 'bg-danger';
            case 'Pendiente de Pago':
                return 'bg-warning text-dark';
            case 'Pausado':
                return 'bg-warning text-dark';
            case 'Riesgo':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
}

if (!function_exists('getPaginationUrl')) {
    /**
     * URL de paginación conservando los filtros activos.
     */
    function getPaginationUrl($page, $filters)
    {
        $params = array_merge($filters, ['pagina' => $page]);
        return '?' . http_build_query($params);
    }
}

if (!function_exists('ordenFiltroEntero')) {
    /**
     * Saneo de un filtro numérico venido de $_GET.
     * Devuelve int válido o '' (que los `if (!empty(...))` descartan).
     * Rechaza arrays (?f[]=1), notación científica y strings tipo "3a" que MySQL
     * convertiría en silencio al bindear como "i".
     */
    function ordenFiltroEntero($valor)
    {
        if (is_array($valor) || $valor === null) {
            return '';
        }
        $valor = trim((string) $valor);
        return ($valor !== '' && ctype_digit($valor)) ? (int) $valor : '';
    }
}

if (!function_exists('ordenFiltroFecha')) {
    /**
     * Saneo de un filtro de fecha. Exige el formato YYYY-MM-DD y que sea una
     * fecha real del calendario: "2024-13-45" o "foo" se descartan.
     */
    function ordenFiltroFecha($valor): string
    {
        if (is_array($valor) || $valor === null) {
            return '';
        }
        $valor = trim((string) $valor);
        if ($valor === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return '';
        }
        [$y, $m, $d] = array_map('intval', explode('-', $valor));
        return checkdate($m, $d, $y) ? $valor : '';
    }
}

if (!function_exists('ordenFiltroMoneda')) {
    /**
     * Saneo de un código de moneda: 3 a 10 caracteres alfabéticos, en mayúsculas.
     */
    function ordenFiltroMoneda($valor): string
    {
        if (is_array($valor) || $valor === null) {
            return '';
        }
        $valor = strtoupper(trim((string) $valor));
        return preg_match('/^[A-Z]{3,10}$/', $valor) ? $valor : '';
    }
}

if (!function_exists('ordenFiltroTexto')) {
    /**
     * Saneo de un filtro de texto libre: recorta y limita el largo para que no
     * viaje una cadena enorme al LIKE.
     */
    function ordenFiltroTexto($valor, int $maxLen = 100): string
    {
        if (is_array($valor) || $valor === null) {
            return '';
        }
        return mb_substr(trim((string) $valor), 0, $maxLen);
    }
}

if (!function_exists('ordenEstadoPermiteEditarComision')) {
    /**
     * Estados en los que la comisión de destino puede editarse.
     * Usa nombres que existen en el catálogo: el bug histórico del panel de
     * operador era filtrar por 'Pagado', que nunca matchea.
     */
    function ordenEstadoPermiteEditarComision(?string $estadoNombre): bool
    {
        return in_array($estadoNombre, ['En Proceso', 'Exitoso'], true);
    }
}
