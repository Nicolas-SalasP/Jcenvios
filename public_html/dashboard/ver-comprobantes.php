<?php
// 1. CONFIGURACIÓN DE SEGURIDAD Y DEPURACIÓN
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Desactivar compresión para ver errores en texto plano si ocurren
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
if (ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', 'Off');
}

// 2. RESOLUCIÓN DE RUTAS ABSOLUTAS (A prueba de balas)
// Buscamos la raíz del servidor (donde está public_html)
$documentRoot = $_SERVER['DOCUMENT_ROOT'];

// Asumimos que remesas_private está AL MISMO NIVEL que public_html
// Ajustamos la ruta subiendo un nivel desde public_html
$baseDir = dirname($documentRoot);
$initPath = $baseDir . '/remesas_private/src/core/init.php';

// 3. VERIFICACIÓN DE EXISTENCIA DEL NÚCLEO
if (!file_exists($initPath)) {
    // Si no lo encuentra así, intentamos buscarlo dentro de public_html por si acaso
    $initPathAlternative = $documentRoot . '/remesas_private/src/core/init.php';

    if (file_exists($initPathAlternative)) {
        $initPath = $initPathAlternative;
    } else {
        // ERROR CRÍTICO VISIBLE
        header('HTTP/1.1 500 Internal Server Error');
        echo "<h1>Error Crítico de Configuración</h1>";
        echo "<p>No se encuentra el archivo núcleo del sistema (init.php).</p>";
        echo "<h3>Rutas intentadas:</h3>";
        echo "<ul>";
        echo "<li>Opción 1 (Fuera de public_html): " . htmlspecialchars($baseDir . '/remesas_private/src/core/init.php') . "</li>";
        echo "<li>Opción 2 (Dentro de public_html): " . htmlspecialchars($initPathAlternative) . "</li>";
        echo "</ul>";
        echo "<p>Verifica la ubicación de la carpeta <strong>remesas_private</strong> en tu servidor.</p>";
        exit();
    }
}

// 4. CARGA DEL NÚCLEO
require_once $initPath;
require_once $baseDir . '/remesas_private/src/App/Database/Database.php';
require_once $baseDir . '/remesas_private/src/App/Repositories/TransactionRepository.php';
require_once $baseDir . '/remesas_private/src/App/Services/FileHandlerService.php';

use App\Database\Database;
use App\Services\FileHandlerService;

// 5. VALIDACIÓN DE SESIÓN
if (!isset($_SESSION['user_id'])) {
    die("Error: Sesión no iniciada. Por favor, loguéate nuevamente.");
}

$loggedInUserId = (int) $_SESSION['user_id'];
$isAdmin = (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Admin');

$transactionId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'user';

if (!is_numeric($transactionId) || $transactionId <= 0) {
    die("Error: ID de transacción inválido.");
}
$transactionId = (int) $transactionId;

try {
    $db = Database::getInstance();
    $conexion = $db->getConnection();
    $fileHandler = new FileHandlerService();

    // Determinar columna
    $columnToSelect = ($type === 'admin') ? 'ComprobanteEnvioURL' : 'ComprobanteURL';

    // Buscar archivo en BD
    $sql = "SELECT UserID, $columnToSelect AS FilePath FROM transacciones WHERE TransaccionID = ?";
    if (!$isAdmin) {
        $sql .= " AND UserID = ?";
    }

    $stmt = $conexion->prepare($sql);
    if (!$stmt)
        throw new Exception("Error SQL: " . $conexion->error);

    if (!$isAdmin) {
        $stmt->bind_param("ii", $transactionId, $loggedInUserId);
    } else {
        $stmt->bind_param("i", $transactionId);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    if (!$fila || empty($fila['FilePath'])) {
        die("Error: No se encontró el archivo o no tienes permisos.");
    }

    // Resolver ruta física usando el servicio
    $relativePath = $fila['FilePath'];
    $realFullPath = $fileHandler->getAbsolutePath($relativePath);

    // Verificación final del archivo
    if (!file_exists($realFullPath) || !is_file($realFullPath)) {
        echo "<h1>Archivo no encontrado en disco</h1>";
        echo "<p>El sistema buscó en:</p>";
        echo "<pre>" . htmlspecialchars($realFullPath) . "</pre>";
        echo "<p>Confirma que el archivo existe en esa ruta vía FTP/cPanel.</p>";
        exit();
    }

    // Servir archivo
    $mimeType = mime_content_type($realFullPath) ?: 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFullPath));
    header('Content-Disposition: inline; filename="' . basename($realFullPath) . '"');

    // Limpieza de buffer vital para imágenes
    while (ob_get_level())
        ob_end_clean();

    readfile($realFullPath);
    exit();

} catch (Exception $e) {
    echo "<h1>Error del Sistema</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>