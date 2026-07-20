<?php
// Sirve (con soporte de Range/streaming) los videos de tutoriales subidos como archivo.
// Los tutoriales con URLExterna (YouTube/Vimeo) no pasan por aquí: se embeben directo.
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$isAdmin = ($_SESSION['user_rol_name'] ?? '') === 'Admin';

$tutorialId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tutorialId <= 0) {
    http_response_code(400);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT RutaArchivo, TipoFuente, Activo FROM tutoriales WHERE TutorialID = ? LIMIT 1");
$stmt->bind_param("i", $tutorialId);
$stmt->execute();
$result = $stmt->get_result();
$tutorial = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$tutorial || $tutorial['TipoFuente'] !== 'archivo' || empty($tutorial['RutaArchivo'])) {
    http_response_code(404);
    exit;
}

if (!$isAdmin && (int)$tutorial['Activo'] !== 1) {
    http_response_code(403);
    exit;
}

$baseUploadPath = realpath(__DIR__ . '/../remesas_private/uploads');
$relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($tutorial['RutaArchivo'], '/\\'));
$fullPath = $baseUploadPath . DIRECTORY_SEPARATOR . $relative;
$realFullPath = realpath($fullPath);

if (!$realFullPath || strpos($realFullPath, $baseUploadPath) !== 0 || !is_file($realFullPath)) {
    http_response_code(404);
    exit;
}

$mimeMap = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
];
$ext = strtolower(pathinfo($realFullPath, PATHINFO_EXTENSION));
$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

$fileSize = filesize($realFullPath);
$start = 0;
$end = $fileSize - 1;

header('Content-Type: ' . $mimeType);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');
header('X-Frame-Options: SAMEORIGIN');

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        if ($matches[1] !== '') {
            $start = (int)$matches[1];
        }
        if ($matches[2] !== '') {
            $end = (int)$matches[2];
        }
    }
    if ($start > $end || $start >= $fileSize) {
        http_response_code(416);
        header("Content-Range: bytes */{$fileSize}");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

$fp = fopen($realFullPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);
$bufferSize = 8192;
$bytesRemaining = $length;
if (ob_get_level()) {
    ob_end_clean();
}
while ($bytesRemaining > 0 && !feof($fp)) {
    $readSize = min($bufferSize, $bytesRemaining);
    echo fread($fp, $readSize);
    $bytesRemaining -= $readSize;
    flush();
}
fclose($fp);
exit;
