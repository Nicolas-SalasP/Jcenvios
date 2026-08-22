<?php
/**
 * Cron: cancelación automática de órdenes expiradas.
 * Cancela las transacciones en "Pendiente de Pago" que llevan más de 4 horas
 * sin que el cliente haya subido un comprobante de pago.
 *
 * Ejecutar cada 30 minutos con crontab (NO usar "*_/30": corta este mismo
 * comentario de bloque; se usa la forma equivalente "0,30"):
 *   0,30 * * * * php /ruta/remesas_private/cron_auto_cancelacion.php >> /var/log/jcenvios_cancel.log 2>&1
 */

date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
}

if (!\App\Support\CronLock::acquire('auto_cancelacion')) {
    echo "[" . date('Y-m-d H:i:s') . "] Ya hay una instancia de cron_auto_cancelacion.php en ejecución. Saliendo sin hacer nada.\n";
    exit(0);
}

try {
    /** @var \App\Services\TransactionService $txService */
    $txService = $container->get(\App\Services\TransactionService::class);

    $canceladas = $txService->autoCancelExpired(4);

    $ts = date('Y-m-d H:i:s');
    if ($canceladas > 0) {
        echo "[{$ts}] {$canceladas} orden(es) cancelada(s) automáticamente por tiempo expirado.\n";
    } else {
        echo "[{$ts}] Sin órdenes expiradas.\n";
    }
} catch (\Throwable $e) {
    // \Throwable: un Error de PHP 8 no es Exception y se escaparía del catch.
    error_log("CRON AUTO_CANCEL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
