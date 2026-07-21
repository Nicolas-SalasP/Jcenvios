<?php
// Sirve públicamente (sin login) una imagen de la galería de Tasas Visuales
// por Id. Es contenido de marketing, no sensible, por eso no requiere
// sesión — a diferencia de tutoriales_stream.php.
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT RutaImagen FROM tasas_imagen WHERE Id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row || empty($row['RutaImagen'])) {
    http_response_code(404);
    exit;
}

$baseUploadPath = realpath(__DIR__ . '/../remesas_private/uploads');
$relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($row['RutaImagen'], '/\\'));
$fullPath = $baseUploadPath . DIRECTORY_SEPARATOR . $relative;
$realFullPath = realpath($fullPath);

if (!$realFullPath || strpos($realFullPath, $baseUploadPath) !== 0 || !is_file($realFullPath)) {
    http_response_code(404);
    exit;
}

$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$ext = strtolower(pathinfo($realFullPath, PATHINFO_EXTENSION));
$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realFullPath));
header('Cache-Control: public, max-age=3600');
header('X-Frame-Options: SAMEORIGIN');

readfile($realFullPath);
exit;
