<?php
/**
 * Cron: purga de PDF de órdenes en public_html/temp_orders/.
 *
 * Los PDF se generan como 'orden_{id}_{32 hex}.pdf' y se entregan al cliente por
 * URL directa. Contienen datos personales (nombre, tipo y número de documento,
 * email del remitente y cuenta bancaria completa del beneficiario), así que no
 * pueden acumularse indefinidamente: el link no es enumerable, pero si se filtra
 * queda vivo para siempre.
 *
 * RETENCIÓN: 7 días. Justificación: la orden se auto-cancela a las 4 horas si no
 * se paga, y el PDF es sólo una copia de conveniencia — el cliente lo puede
 * volver a generar desde el historial cuando quiera (TransactionService lo
 * regenera con savePdfTemporarily). 7 días cubre un fin de semana largo más
 * margen para que el cliente descargue el link que recibió, sin dejar datos
 * personales sueltos por meses.
 *
 * Ejecutar diariamente con crontab:
 *   30 4 * * * php /ruta/remesas_private/cron_purge_temp_orders.php >> /var/log/jcenvios_purge_temp.log 2>&1
 */

date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
}

use App\Services\FileHandlerService;

if (!\App\Support\CronLock::acquire('purge_temp_orders')) {
    echo "[" . date('Y-m-d H:i:s') . "] Ya hay una instancia de cron_purge_temp_orders.php en ejecución. Saliendo sin hacer nada.\n";
    exit(0);
}

const DIAS_RETENCION_PDF_ORDENES = 7;

try {
    $fileHandler = new FileHandlerService();
    $r = $fileHandler->purgeOldOrderPdfs(DIAS_RETENCION_PDF_ORDENES);

    $ts = date('Y-m-d H:i:s');
    if ($r['borrados'] > 0 || $r['fallidos'] > 0) {
        echo "[{$ts}] {$r['borrados']} PDF de orden purgado(s) (más de " . DIAS_RETENCION_PDF_ORDENES . " días), {$r['fallidos']} fallido(s), de {$r['revisados']} archivo(s) revisado(s).\n";
    } else {
        echo "[{$ts}] Sin PDF de orden para purgar ({$r['revisados']} archivo(s) revisado(s)).\n";
    }
} catch (\Throwable $e) {
    // \Throwable: un Error de PHP 8 no es Exception y se escaparía del catch.
    error_log("CRON PURGE_TEMP_ORDERS ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
