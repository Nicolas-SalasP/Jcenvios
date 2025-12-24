<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

use App\Database\Database;
use App\Repositories\{
    UserRepository,
    RateRepository,
    CountryRepository,
    CuentasBeneficiariasRepository,
    TransactionRepository,
    RolRepository,
    EstadoVerificacionRepository,
    TipoDocumentoRepository,
    EstadoTransaccionRepository,
    FormaPagoRepository,
    TipoBeneficiarioRepository,
    ContabilidadRepository,
    TasasHistoricoRepository,
    CuentasAdminRepository,
    SystemSettingsRepository
};
use App\Services\{
    LogService,
    NotificationService,
    PDFService,
    FileHandlerService,
    UserService,
    PricingService,
    TransactionService,
    CuentasBeneficiariasService,
    DashboardService,
    ContabilidadService
};
use App\Controllers\{
    AuthController,
    ClientController,
    AdminController,
    DashboardController,
    ContabilidadController,
    BotController
};

header('Content-Type: application/json');

class Container
{
    private array $instances = [];
    private ?Database $db = null;

    public function getDb(): Database
    {
        if ($this->db === null) {
            $this->db = Database::getInstance();
        }
        return $this->db;
    }

    public function get(string $className)
    {
        if (!isset($this->instances[$className])) {
            $this->instances[$className] = $this->createInstance($className);
        }
        return $this->instances[$className];
    }

    private function createInstance(string $className)
    {
        return match ($className) {
                // Repositorios
            UserRepository::class => new UserRepository($this->getDb()),
            RateRepository::class => new RateRepository($this->getDb()),
            CountryRepository::class => new CountryRepository($this->getDb()),
            CuentasBeneficiariasRepository::class => new CuentasBeneficiariasRepository($this->getDb()),
            TransactionRepository::class => new TransactionRepository($this->getDb()),
            RolRepository::class => new RolRepository($this->getDb()),
            EstadoVerificacionRepository::class => new EstadoVerificacionRepository($this->getDb()),
            TipoDocumentoRepository::class => new TipoDocumentoRepository($this->getDb()),
            EstadoTransaccionRepository::class => new EstadoTransaccionRepository($this->getDb()),
            FormaPagoRepository::class => new FormaPagoRepository($this->getDb()),
            TipoBeneficiarioRepository::class => new TipoBeneficiarioRepository($this->getDb()),
            ContabilidadRepository::class => new ContabilidadRepository($this->getDb()),
            TasasHistoricoRepository::class => new TasasHistoricoRepository($this->getDb()),
            CuentasAdminRepository::class => new CuentasAdminRepository($this->getDb()),
            SystemSettingsRepository::class => new SystemSettingsRepository($this->getDb()),

                // Services
            LogService::class => new LogService($this->getDb()),
            NotificationService::class => new NotificationService($this->get(LogService::class)),
            PDFService::class => new PDFService(),
            FileHandlerService::class => new FileHandlerService(),
            UserService::class => new UserService(
                $this->get(UserRepository::class),
                $this->get(NotificationService::class),
                $this->get(FileHandlerService::class),
                $this->get(EstadoVerificacionRepository::class),
                $this->get(RolRepository::class),
                $this->get(TipoDocumentoRepository::class),
                $this->get(LogService::class)
            ),

            PricingService::class => new PricingService(
                $this->get(RateRepository::class),
                $this->get(CountryRepository::class),
                $this->get(SystemSettingsRepository::class),
                $this->get(NotificationService::class)
            ),
            CuentasBeneficiariasService::class => new CuentasBeneficiariasService(
                $this->get(CuentasBeneficiariasRepository::class),
                $this->get(NotificationService::class),
                $this->get(TipoBeneficiarioRepository::class),
                $this->get(TipoDocumentoRepository::class)
            ),
            ContabilidadService::class => new ContabilidadService(
                $this->get(ContabilidadRepository::class),
                $this->get(CountryRepository::class),
                $this->get(LogService::class),
                $this->getDb()
            ),
            TransactionService::class => new TransactionService(
                $this->get(TransactionRepository::class),
                $this->get(UserRepository::class),
                $this->get(NotificationService::class),
                $this->get(PDFService::class),
                $this->get(FileHandlerService::class),
                $this->get(EstadoTransaccionRepository::class),
                $this->get(FormaPagoRepository::class),
                $this->get(ContabilidadService::class),
                $this->get(CuentasBeneficiariasRepository::class),
                $this->get(CuentasAdminRepository::class)
            ),
            DashboardService::class => new DashboardService(
                $this->get(TransactionRepository::class),
                $this->get(UserRepository::class),
                $this->get(RateRepository::class),
                $this->get(EstadoTransaccionRepository::class),
                $this->get(CountryRepository::class),
                $this->get(TasasHistoricoRepository::class)
            ),

                // Controllers
            AuthController::class => new AuthController($this->get(UserService::class)),
            ClientController::class => new ClientController(
                $this->get(TransactionService::class),
                $this->get(PricingService::class),
                $this->get(CuentasBeneficiariasService::class),
                $this->get(UserService::class),
                $this->get(FormaPagoRepository::class),
                $this->get(TipoBeneficiarioRepository::class),
                $this->get(TipoDocumentoRepository::class),
                $this->get(RolRepository::class),
                $this->get(NotificationService::class)
            ),
            AdminController::class => new AdminController(
                $this->get(TransactionService::class),
                $this->get(PricingService::class),
                $this->get(UserService::class),
                $this->get(DashboardService::class),
                $this->get(RolRepository::class),
                $this->get(CuentasAdminRepository::class)
            ),
            DashboardController::class => new DashboardController(
                $this->get(DashboardService::class),
                $this->get(CountryRepository::class)
            ),
            ContabilidadController::class => new ContabilidadController(
                $this->get(ContabilidadService::class)
            ),
            BotController::class => new BotController(
                $this->get(PricingService::class),
                $this->get(CuentasAdminRepository::class)
            ),

            default => throw new Exception("Clase no configurada en el contenedor: {$className}")
        };
    }
}

try {
    $container = new Container();
    $accion = $_GET['accion'] ?? '';
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    $routes = [
        // Auth & 2FA
        'loginUser' => [AuthController::class, 'loginUser', 'POST'],
        'registerUser' => [AuthController::class, 'registerUser', 'POST'],
        'requestPasswordReset' => [AuthController::class, 'requestPasswordReset', 'POST'],
        'performPasswordReset' => [AuthController::class, 'performPasswordReset', 'POST'],
        'verify2fa' => [AuthController::class, 'verify2FACode', 'POST'],
        'send2faCode' => [AuthController::class, 'send2FACode', 'POST'],
        'resend2faCode' => [AuthController::class, 'send2FACode', 'POST'],
        'update2faMethod' => [ClientController::class, 'update2faMethod', 'POST'],

        // Client
        'submitContactForm' => [ClientController::class, 'handleContactForm', 'POST'],
        'getTasa' => [ClientController::class, 'getTasa', 'GET'],
        'getCurrentRate' => [ClientController::class, 'getCurrentRate', 'GET'],
        'getPaises' => [ClientController::class, 'getPaises', 'GET'],
        'getDolarBcv' => [DashboardController::class, 'getDolarBcvData', 'GET'],
        'getActiveDestinationCountries' => [ClientController::class, 'getActiveDestinationCountries', 'GET'],
        'getCuentas' => [ClientController::class, 'getCuentas', 'GET'],
        'getBeneficiaryDetails' => [ClientController::class, 'getBeneficiaryDetails', 'GET'],
        'addCuenta' => [ClientController::class, 'addCuenta', 'POST'],
        'updateBeneficiary' => [ClientController::class, 'updateBeneficiary', 'POST'],
        'deleteBeneficiary' => [ClientController::class, 'deleteBeneficiary', 'POST'],
        'createTransaccion' => [ClientController::class, 'createTransaccion', 'POST'],
        'cancelTransaction' => [ClientController::class, 'cancelTransaction', 'POST'],
        'uploadReceipt' => [ClientController::class, 'uploadReceipt', 'POST'],
        'getUserProfile' => [ClientController::class, 'getUserProfile', 'GET'],
        'updateUserProfile' => [ClientController::class, 'updateUserProfile', 'POST'],
        'uploadVerificationDocs' => [ClientController::class, 'uploadVerificationDocs', 'POST'],
        'getFormasDePago' => [ClientController::class, 'getFormasDePago', 'GET'],
        'getBeneficiaryTypes' => [ClientController::class, 'getBeneficiaryTypes', 'GET'],
        'getDocumentTypes' => [ClientController::class, 'getDocumentTypes', 'GET'],
        'getAssignableRoles' => [ClientController::class, 'getAssignableRoles', 'GET'],
        'generate2FASecret' => [ClientController::class, 'generate2FASecret', 'POST'],
        'enable2FA' => [ClientController::class, 'enable2FA', 'POST'],
        'disable2FA' => [ClientController::class, 'disable2FA', 'POST'],
        'botWebhook' => [BotController::class, 'handleWebhook', 'POST'],
        'resumeOrder' => [ClientController::class, 'resumeOrder', 'POST'],

        // Admin
        'updateRate' => [AdminController::class, 'upsertRate', 'POST'],
        'deleteRate' => [AdminController::class, 'deleteRate', 'POST'],
        'addPais' => [AdminController::class, 'addPais', 'POST'],
        'updatePais' => [AdminController::class, 'updatePais', 'POST'],
        'updatePaisRol' => [AdminController::class, 'updatePaisRol', 'POST'],
        'togglePaisStatus' => [AdminController::class, 'togglePaisStatus', 'POST'],
        'processTransaction' => [AdminController::class, 'processTransaction', 'POST'],
        'rejectTransaction' => [AdminController::class, 'rejectTransaction', 'POST'],
        'adminUploadProof' => [AdminController::class, 'adminUploadProof', 'POST'],
        'updateVerificationStatus' => [AdminController::class, 'updateVerificationStatus', 'POST'],
        'toggleUserBlock' => [AdminController::class, 'toggleUserBlock', 'POST'],
        'getDashboardStats' => [AdminController::class, 'getDashboardStats', 'GET'],
        'updateUserRole' => [AdminController::class, 'updateUserRole', 'POST'],
        'deleteUser' => [AdminController::class, 'deleteUser', 'POST'],
        'getCuentasAdmin' => [AdminController::class, 'getCuentasAdmin', 'GET'],
        'saveCuentaAdmin' => [AdminController::class, 'saveCuentaAdmin', 'POST'],
        'deleteCuentaAdmin' => [AdminController::class, 'deleteCuentaAdmin', 'POST'],
        'updateTxCommission' => [AdminController::class, 'updateTxCommission', 'POST'],
        'adminUpdateUser' => [AdminController::class, 'adminUpdateUser', 'POST'],
        'updateBcvRate' => [AdminController::class, 'updateBcvRate', 'POST'],
        'getBcvRate' => [ClientController::class, 'getBcvRate', 'GET'],
        'pauseTransaction' => [AdminController::class, 'pauseTransaction', 'POST'],

        // Contabilidad
        'getSaldosContables' => [ContabilidadController::class, 'getSaldos', 'GET'],
        'agregarFondos' => [ContabilidadController::class, 'agregarFondos', 'POST'],
        'retirarFondos' => [ContabilidadController::class, 'retirarFondos', 'POST'],
        'compraDivisas' => [ContabilidadController::class, 'compraDivisas', 'POST'],
        'getResumenContable' => [ContabilidadController::class, 'getResumenMensual', 'GET'],
    ];

    if (isset($routes[$accion])) {
        list($controllerClass, $methodName, $expectedMethod) = $routes[$accion];

        if ($_SERVER['REQUEST_METHOD'] !== $expectedMethod) {
            throw new Exception("Método no permitido. Se esperaba {$expectedMethod}.", 405);
        }

        $controller = $container->get($controllerClass);

        if (method_exists($controller, $methodName)) {
            $controller->$methodName();
        } else {
            throw new Exception("Método API '{$methodName}' no implementado en '{$controllerClass}'.", 501);
        }

    } else {
        throw new Exception('Acción API no válida o no encontrada.', 404);
    }

} catch (\Throwable $e) {
    if (function_exists('\App\Core\exception_handler')) {
        \App\Core\exception_handler($e);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}