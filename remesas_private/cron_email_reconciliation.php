<?php
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/src/core/init.php';

if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("Acceso denegado.");
}

use App\Database\Database;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\EstadoTransaccionRepository;
use App\Repositories\FormaPagoRepository;
use App\Repositories\CuentasBeneficiariasRepository;
use App\Repositories\CuentasAdminRepository;
use App\Repositories\RateRepository;
use App\Repositories\ContabilidadRepository;
use App\Repositories\CountryRepository;
use App\Services\LogService;
use App\Services\NotificationService;
use App\Services\FileHandlerService;
use App\Services\PDFService;
use App\Services\ContabilidadService;
use App\Services\TransactionService;
use App\Services\EmailReconciliationService;

try {
    echo "Iniciando conciliación de pagos por correo...\n";

    $db = Database::getInstance();

    // --- Dependencias base (modo SUGERIR: siempre disponibles) ---
    $txRepository        = new TransactionRepository($db);
    $logService          = new LogService($db);
    $notificationService = new NotificationService($logService);
    $fileHandler         = new FileHandlerService();

    // --- TransactionService (modo AUTO opcional). Si falla, degradamos a null (solo sugerir). ---
    $transactionService = null;
    try {
        $contabilidadService = new ContabilidadService(
            new ContabilidadRepository($db),
            new CountryRepository($db),
            $logService,
            $db
        );

        $transactionService = new TransactionService(
            $txRepository,
            new UserRepository($db),
            $notificationService,
            new PDFService(),
            $fileHandler,
            new EstadoTransaccionRepository($db),
            new FormaPagoRepository($db),
            $contabilidadService,
            new CuentasBeneficiariasRepository($db),
            new CuentasAdminRepository($db),
            new RateRepository($db)
        );
    } catch (\Throwable $e) {
        error_log("CRON RECON: no se pudo construir TransactionService, degradando a modo sugerir: " . $e->getMessage());
        echo "Aviso: TransactionService no disponible. El bot operará SOLO en modo sugerir.\n";
        $transactionService = null;
    }

    $reconciliationService = new EmailReconciliationService(
        $txRepository,
        $notificationService,
        $fileHandler,
        $transactionService
    );

    $reconciliationService->procesarCorreosNoLeidos();

    echo "Conciliación finalizada.\n";
} catch (\Throwable $e) {
    error_log("CRON RECON ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
