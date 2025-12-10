<?php
// public_html/webhook_whatsapp.php

// 1. Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ---------------------------------------------------------
// 2. CARGAR CONFIGURACIÓN Y CLASES
// ---------------------------------------------------------

$basePrivate = __DIR__ . '/../remesas_private';
$configFile = $basePrivate . '/config.php';

if (file_exists($configFile)) {
    require_once $configFile;
} else {
    error_log("[WhatsappBot] No config found.");
    exit;
}

// Carga de Clases (Manual)
// A) Base de Datos
require_once $basePrivate . '/src/App/Database/Database.php';

// B) Servicios Base (Nivel 1)
require_once $basePrivate . '/src/App/Services/LogService.php'; // <--- Nuevo ingrediente

// C) Repositorios
require_once $basePrivate . '/src/App/Repositories/RateRepository.php';
require_once $basePrivate . '/src/App/Repositories/CountryRepository.php';
require_once $basePrivate . '/src/App/Repositories/SystemSettingsRepository.php';

// D) Servicios Avanzados (Nivel 2)
require_once $basePrivate . '/src/App/Services/NotificationService.php';
require_once $basePrivate . '/src/App/Services/PricingService.php';

// E) Controladores
require_once $basePrivate . '/src/App/Controllers/BaseController.php';
require_once $basePrivate . '/src/App/Controllers/BotController.php';

// Importar Namespaces
use App\Database\Database;
use App\Services\LogService;            // <--- Nuevo
use App\Services\NotificationService;
use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Services\PricingService;
use App\Controllers\BotController;

try {
    // ---------------------------------------------------------
    // 3. ARMANDO EL ROBOT (Cadena de Dependencias)
    // ---------------------------------------------------------

    // Paso 1: Base de Datos
    $db = Database::getInstance();

    // Paso 2: Servicio de Logs (Necesita DB)
    $logService = new LogService($db);

    // Paso 3: Servicio de Notificaciones (Necesita LogService)
    $notifService = new NotificationService($logService);

    // Paso 4: Repositorios (Necesitan DB)
    $rateRepo     = new RateRepository($db);
    $countryRepo  = new CountryRepository($db);
    $settingsRepo = new SystemSettingsRepository($db);

    // Paso 5: Pricing Service (El más exigente, necesita 4 cosas)
    $pricingService = new PricingService(
        $rateRepo, 
        $countryRepo, 
        $settingsRepo, 
        $notifService
    );

    // Paso 6: Bot Controller
    $bot = new BotController($pricingService);

    // ---------------------------------------------------------
    // 4. EJECUCIÓN
    // ---------------------------------------------------------
    $bot->handleWebhook();

} catch (\Throwable $e) {
    // MODO DEBUG
    $errorMsg = $e->getMessage();
    error_log("[WhatsappBot Error] " . $errorMsg);
    
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "<Response><Message>Error Técnico: {$errorMsg}</Message></Response>";
    exit;
}
?>