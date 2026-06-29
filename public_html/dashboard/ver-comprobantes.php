<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../remesas_private/src/core/init.php';
require_once __DIR__ . '/../../remesas_private/src/App/Database/Database.php';
require_once __DIR__ . '/../../remesas_private/src/App/Repositories/TransactionRepository.php';
require_once __DIR__ . '/../../remesas_private/src/App/Services/FileHandlerService.php';

use App\Database\Database;
use App\Services\FileHandlerService;

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$loggedInUserId = (int) $_SESSION['user_id'];
$isAdmin = (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Admin');

$transactionId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'user';

if (!is_numeric($transactionId) || $transactionId <= 0) {
    http_response_code(400);
    exit;
}
$transactionId = (int) $transactionId;

try {
    $db = Database::getInstance();
    $conexion = $db->getConnection();
    $fileHandler = new FileHandlerService();

    $columnToSelect = ($type === 'admin') ? 'ComprobanteEnvioURL' : 'ComprobanteURL';

    $sql = "SELECT UserID, $columnToSelect AS FilePath FROM transacciones WHERE TransaccionID = ?";
    if (!$isAdmin) {
        $sql .= " AND UserID = ?";
    }

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        error_log("ver-comprobantes SQL error: " . $conexion->error);
        throw new Exception("Error interno.", 500);
    }

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
        http_response_code(404);
        exit;
    }

    $relativePath = $fila['FilePath'];
    $realFullPath = $fileHandler->getAbsolutePath($relativePath);

    if (!file_exists($realFullPath) || !is_file($realFullPath)) {
        http_response_code(404);
        exit;
    }

    $mimeType = mime_content_type($realFullPath) ?: 'application/octet-stream';
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    if (!in_array($mimeType, $allowedMimes)) {
        http_response_code(403);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFullPath));
    header('Content-Disposition: inline; filename="' . basename($realFullPath) . '"');
    header('Cache-Control: private, max-age=86400');
    header('X-Frame-Options: SAMEORIGIN');

    while (ob_get_level()) ob_end_clean();

    readfile($realFullPath);
    exit();

} catch (Exception $e) {
    error_log("ver-comprobantes error: " . $e->getMessage());
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    exit;
}
?>
