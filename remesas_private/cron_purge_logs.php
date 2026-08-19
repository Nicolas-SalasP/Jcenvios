<?php
/**
 * Cron: purga de bitácora antigua (tabla `logs`).
 * Borra los registros de `logs` con más de 12 meses de antigüedad. Es tabla
 * de auditoría de acciones administrativas (no dato con retención legal
 * obligatoria - eso está cubierto aparte para AML/KYC en
 * public_html/legal/programa-aml.html), así que se purga sin archivar.
 *
 * Ejecutar semanalmente con crontab (no es urgente, no hace falta diario):
 *   0 4 * * 0 php /ruta/remesas_private/cron_purge_logs.php >> /var/log/jcenvios_purge_logs.log 2>&1
 */

date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
}

use App\Database\Database;
use App\Services\LogService;

try {
    $db = Database::getInstance();
    $logService = new LogService($db);

    $borrados = $logService->purgeOldLogs(12);

    $ts = date('Y-m-d H:i:s');
    if ($borrados > 0) {
        echo "[{$ts}] {$borrados} registro(s) de bitácora purgado(s) (más de 12 meses de antigüedad).\n";
    } else {
        echo "[{$ts}] Sin registros de bitácora para purgar.\n";
    }
} catch (\Exception $e) {
    error_log("CRON PURGE_LOGS ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
