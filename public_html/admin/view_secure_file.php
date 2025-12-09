<?php
// Desactivar salida de errores para no corromper la imagen
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../remesas_private/src/core/init.php';

// 1. Seguridad Básica: Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$rol = $_SESSION['user_rol_name'] ?? '';
$isAdminOrOperator = ($rol === 'Admin' || $rol === 'Operador');

// 2. Validar parámetro
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    exit;
}

$fileRequest = urldecode($_GET['file']);

// --- 3. LIMPIEZA INTELIGENTE DE RUTA ---
$fileRequest = str_replace([
    'http://' . $_SERVER['HTTP_HOST'], 
    'https://' . $_SERVER['HTTP_HOST'],
    'http://', 
    'https://',
    BASE_URL
], '', $fileRequest);

$fileRequest = ltrim($fileRequest, '/\\');
$fileRequest = str_replace(['../', '..\\'], '', $fileRequest);

if (strpos($fileRequest, 'public_html/') === 0) {
    $fileRequest = substr($fileRequest, 12);
}

// --- 4. VALIDACIÓN DE PERMISOS ---
$hasPermission = false;

if ($isAdminOrOperator) {
    $hasPermission = true;
} 
else {
    if (strpos($fileRequest, 'profile_pics') !== false) {
        $filename = basename($fileRequest);
        $prefix = 'user_profile_' . $userId . '_';
        if (strpos($filename, $prefix) === 0) {
            $hasPermission = true;
        }
    }
}

if (!$hasPermission) {
    http_response_code(403);
    exit;
}

// 5. Definir Rutas Base Posibles
$basePrivate = realpath(__DIR__ . '/../../remesas_private');

$candidates = [
    $basePrivate . DIRECTORY_SEPARATOR . $fileRequest,
    $basePrivate . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fileRequest,
    $basePrivate . DIRECTORY_SEPARATOR . str_replace('uploads/', '', $fileRequest)
];

$realFullPath = null;

foreach ($candidates as $candidate) {
    $candidate = str_replace(['//', '\\\\'], DIRECTORY_SEPARATOR, $candidate);
    
    if (file_exists($candidate) && is_file($candidate)) {
        $realFullPath = realpath($candidate);
        if ($realFullPath && strpos($realFullPath, $basePrivate) === 0) {
            break; 
        } else {
            $realFullPath = null; 
        }
    }
}

// 6. Servir el archivo
if ($realFullPath && file_exists($realFullPath)) {
    
    $mimeType = null;
    if (function_exists('mime_content_type')) {
        $mimeType = @mime_content_type($realFullPath);
    }
    
    if (!$mimeType) {
        $ext = strtolower(pathinfo($realFullPath, PATHINFO_EXTENSION));
        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'pdf' => 'application/pdf'
        ];
        $mimeType = $mimes[$ext] ?? 'application/octet-stream';
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFullPath));
    header('Content-Disposition: inline; filename="' . basename($realFullPath) . '"');
    header('Cache-Control: private, max-age=86400');
    
    if (ob_get_length()) ob_clean();
    flush();
    
    readfile($realFullPath);
    exit;

} else {
    http_response_code(404);
    exit;
}