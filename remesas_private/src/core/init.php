<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';

$session_path = __DIR__ . '/../../sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0700, true);
}
ini_set('session.save_path', $session_path);

$cookie_lifetime = 60 * 60 * 24 * 30;
$max_server_session_lifetime = 4 * 60 * 60;
ini_set('session.gc_maxlifetime', $max_server_session_lifetime);

session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => defined('SESSION_DOMAIN') ? SESSION_DOMAIN : $_SERVER['HTTP_HOST'],
    'secure' => defined('IS_HTTPS') ? IS_HTTPS : isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

header('X-Content-Type-Options: nosniff');

$cspHost = '';
if (defined('BASE_URL')) {
    $parsedUrl = parse_url(BASE_URL);
    if (isset($parsedUrl['host'])) {
        $host = $parsedUrl['host'];
        $scheme = $parsedUrl['scheme'] ?? 'https';
        if (strpos($host, 'www.') === 0) {
            $cspHost = $scheme . '://' . $host . ' ' . $scheme . '://' . substr($host, 4);
        } else {
            $cspHost = $scheme . '://' . $host . ' ' . $scheme . '://www.' . $host;
        }
    }
}

$cspDirectives = [
    "default-src 'self'",
    "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'",
    "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'",
    "font-src 'self' https://cdn.jsdelivr.net",
    "img-src 'self' data:",
    "frame-src 'self' http://googleusercontent.com/maps.google.com/ https://www.google.com/",
    "connect-src 'self' " . $cspHost . " https://cdn.jsdelivr.net",
    "object-src 'none'",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'"
];
header("Content-Security-Policy: " . implode('; ', $cspDirectives));

session_start();

require_once __DIR__ . '/ErrorHandler.php';
set_exception_handler('App\\Core\\exception_handler');

$tiempo_limite = 15 * 60;

if (isset($_SESSION['user_rol_name']) && in_array($_SESSION['user_rol_name'], ['Admin', 'Operador'])) {
    $tiempo_limite = 4 * 60 * 60;
}

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] > $tiempo_limite)) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?session_expired=1');
        exit();
    }
    $_SESSION['ultima_actividad'] = time();

    $is_admin_or_operador = (isset($_SESSION['user_rol_name']) && ($_SESSION['user_rol_name'] === 'Admin' || $_SESSION['user_rol_name'] === 'Operador'));
    $two_fa_enabled = (isset($_SESSION['twofa_enabled']) && $_SESSION['twofa_enabled'] == 1);

    if ($is_admin_or_operador && $two_fa_enabled) {
        $two_fa_grace_period = $tiempo_limite;
        $current_page = basename($_SERVER['SCRIPT_NAME']);
        $is_on_auth_page = in_array($current_page, ['verify-2fa.php', 'logout.php']);
        $is_api_call = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

        if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] >= $two_fa_grace_period)) {
            $_SESSION['2fa_user_id'] = $_SESSION['user_id'];
            unset($_SESSION['user_id']);

            if (!$is_api_call && !$is_on_auth_page) {
                header('Location: ' . BASE_URL . '/verify-2fa.php?grace_expired=1');
                exit();
            }
        }
    }
}

try {
    $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conexion->connect_error) {
        throw new Exception("Error de conexión: " . $conexion->connect_error);
    }
} catch (Exception $e) {
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']));
    } else {
        die("Servicio no disponible temporalmente.");
    }
}
$conexion->set_charset("utf8mb4");

$container = new class($conexion) {
    private $mysqli;
    public function __construct($m) { $this->mysqli = $m; }

    public function get($class) {
        try {
            $db = \App\Database\Database::getInstance($this->mysqli);

            if ($class === \App\Services\PricingService::class) {
                $rateRepo     = new \App\Repositories\RateRepository($db);
                $countryRepo  = new \App\Repositories\CountryRepository($db);
                $settingsRepo = new \App\Repositories\SystemSettingsRepository($db);
                $holidayRepo  = new \App\Repositories\HolidayRepository($db);
                $logService   = new \App\Services\LogService($db);
                $notifService = new \App\Services\NotificationService($logService);
                $systemService = new \App\Services\SystemSettingsService(
                    $settingsRepo,
                    $holidayRepo,
                    $logService
                );
                return new \App\Services\PricingService(
                    $rateRepo,
                    $countryRepo,
                    $settingsRepo,
                    $notifService,
                    $systemService
                );
            }
        } catch (Throwable $e) {
            error_log("Error en el contenedor de servicios (init.php): " . $e->getMessage());
        }
        return null;
    }
};

function logAction($conexion, $accion, $userId = null, $detalles = '')
{
    $stmt = $conexion->prepare("INSERT INTO logs (UserID, Accion, Detalles) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $userId, $accion, $detalles);
        $stmt->execute();
        $stmt->close();
    }
}