<?php
// public_html/webhook_whatsapp.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$basePrivate = __DIR__ . '/../remesas_private';
require_once $basePrivate . '/config.php';

// Carga Manual de Clases
require_once $basePrivate . '/src/App/Database/Database.php';
require_once $basePrivate . '/src/App/Services/LogService.php';
require_once $basePrivate . '/src/App/Repositories/RateRepository.php';
require_once $basePrivate . '/src/App/Repositories/CountryRepository.php';
require_once $basePrivate . '/src/App/Repositories/SystemSettingsRepository.php';
require_once $basePrivate . '/src/App/Repositories/CuentasAdminRepository.php';
require_once $basePrivate . '/src/App/Services/NotificationService.php';
require_once $basePrivate . '/src/App/Services/PricingService.php';
require_once $basePrivate . '/src/App/Controllers/BaseController.php';
require_once $basePrivate . '/src/App/Controllers/BotController.php';

use App\Database\Database;
use App\Services\LogService;
use App\Services\NotificationService;
use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Repositories\CuentasAdminRepository;
use App\Services\PricingService;
use App\Controllers\BotController;

try {
    $db = Database::getInstance();
    $logService = new LogService($db);
    $notifService = new NotificationService($logService);

    $rateRepo     = new RateRepository($db);
    $countryRepo  = new CountryRepository($db);
    $settingsRepo = new SystemSettingsRepository($db);
    $cuentasRepo  = new CuentasAdminRepository($db);

    $pricingService = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService);

    // Pasamos los 3 argumentos requeridos al constructor
    $bot = new BotController($pricingService, $cuentasRepo, $notifService);
    $bot->handleWebhook();

} catch (\Throwable $e) {
    error_log("[WhatsappBot Error] " . $e->getMessage());
    header('Content-Type: text/xml');
    echo "<Response><Message>Error Técnico: " . $e->getMessage() . "</Message></Response>";
    exit;
}