<?php
// Desactivar salida de errores para no corromper la imagen
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../remesas_private/src/core/init.php';

// 1. Seguridad: Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// 2. Seguridad: Roles (Admin y Operador)
$rol = $_SESSION['user_rol_name'] ?? '';
if ($rol !== 'Admin' && $rol !== 'Operador') {
    http_response_code(403);
    exit;
}

// 3. Validar parámetro
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    exit;
}

$fileRequest = urldecode($_GET['file']);

// --- 4. LIMPIEZA INTELIGENTE DE RUTA (FIX DEL ERROR) ---

// A) Quitar protocolo y dominio si vienen en el string
// Esto convierte "https://jcenvios.cl/uploads/archivo.jpg" en "/uploads/archivo.jpg"
$fileRequest = str_replace([
    'http://' . $_SERVER['HTTP_HOST'], 
    'https://' . $_SERVER['HTTP_HOST'],
    'http://', 
    'https://',
    BASE_URL // Si tienes definida esta constante
], '', $fileRequest);

// B) Quitar barras iniciales y limpieza básica
$fileRequest = ltrim($fileRequest, '/\\');
$fileRequest = str_replace(['../', '..\\'], '', $fileRequest); // Anti-hack

// C) Si la ruta empieza con "public_html/", quitarlo (a veces pasa)
if (strpos($fileRequest, 'public_html/') === 0) {
    $fileRequest = substr($fileRequest, 12);
}

// -------------------------------------------------------

// 5. Definir Rutas Base Posibles
$basePrivate = realpath(__DIR__ . '/../../remesas_private');

// Vamos a probar varias combinaciones para encontrar el archivo
$candidates = [
    // 1. Buscar en uploads directamente (ej: uploads/recibos/foto.jpg)
    $basePrivate . DIRECTORY_SEPARATOR . $fileRequest,
    
    // 2. Buscar asumiendo que faltó 'uploads/' (ej: recibos/foto.jpg -> uploads/recibos/foto.jpg)
    $basePrivate . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fileRequest,
    
    // 3. Caso raro: Si la BD guardó "uploads/" duplicado, lo limpiamos
    $basePrivate . DIRECTORY_SEPARATOR . str_replace('uploads/', '', $fileRequest)
];

$realFullPath = null;

foreach ($candidates as $candidate) {
    // Limpiar dobles barras por si acaso
    $candidate = str_replace(['//', '\\\\'], DIRECTORY_SEPARATOR, $candidate);
    
    if (file_exists($candidate) && is_file($candidate)) {
        $realFullPath = realpath($candidate);
        // Seguridad final: Verificar que esté dentro de la carpeta privada
        if ($realFullPath && strpos($realFullPath, $basePrivate) === 0) {
            break; 
        } else {
            $realFullPath = null; // Encontrado pero fuera de zona segura
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
    // Error 404
    http_response_code(404);
    // Descomenta la siguiente línea SOLO para depurar si sigue fallando (luego bórrala):
    // echo "Debug: No encontrado. Probé: " . implode(' | ', $candidates);
    exit;
}