<?php
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
}

// Protección de concurrencia: si dos instancias se solapan, ambas pueden pasar
// el guard de last_run y el ajuste porcentual se aplicaría dos veces en cascada
// sobre ValorTasa. Es dinero real, no hay vuelta atrás automática.
if (!\App\Support\CronLock::acquire('tasas')) {
    echo "[" . date('Y-m-d H:i:s') . "] Ya hay una instancia de cron_tasas.php en ejecución. Saliendo sin hacer nada.\n";
    exit(0);
}

try {
    $pricingService = $container->get(\App\Services\PricingService::class);

    echo "Iniciando chequeo de tasas...\n";
    $resultado = $pricingService->runScheduledAdjustment();

    if ($resultado) {
        echo "Ajuste aplicado correctamente.\n";
    } else {
        echo "No se requiere ajuste en este momento.\n";
    }
} catch (\Throwable $e) {
    // \Throwable y no \Exception: en PHP 8 un TypeError/Error no es Exception,
    // y un catch(Exception) deja morir el cron sin dejar rastro útil.
    error_log("CRON ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}