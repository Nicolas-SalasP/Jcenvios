<?php
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
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
} catch (\Exception $e) {
    error_log("CRON ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}