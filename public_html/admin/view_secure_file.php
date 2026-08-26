<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$rol = $_SESSION['user_rol_name'] ?? '';
$isAdminOrOperator = ($rol === 'Admin' || $rol === 'Operador');

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    exit;
}

// PHP ya decodificó el query string: un urldecode() extra convertiría %252e%252e%252f
// en ../ y reabriría el traversal. Se usa el valor tal cual llega.
$fileRequest = (string) $_GET['file'];

$fileRequest = str_replace([
    'http://' . $_SERVER['HTTP_HOST'],
    'https://' . $_SERVER['HTTP_HOST'],
    'http://',
    'https://',
    BASE_URL
], '', $fileRequest);

$fileRequest = ltrim($fileRequest, '/\\');

if (strpos($fileRequest, 'public_html/') === 0) {
    $fileRequest = substr($fileRequest, 12);
}

// Las rutas se guardan en la base con separador de Windows (receipts\tx_recibo_1.jpg),
// así que la comparación de pertenencia contra transacciones necesita el valor tal
// como llegó. La normalización se usa solo para validar la ruta.
$fileRequestDb = $fileRequest;

$fileRequest = str_replace('\\', '/', $fileRequest);
$fileRequest = preg_replace('#/+#', '/', $fileRequest);

// Rechazo temprano de traversal y de bytes nulos, antes de tocar el disco.
if ($fileRequest === ''
    || strpos($fileRequest, "\0") !== false
    || strpos($fileRequest, '..') !== false
    || preg_match('#(^|/)\.#', $fileRequest)) {
    http_response_code(400);
    exit;
}

// Whitelist de directorios servibles. Este endpoint SOLO entrega archivos subidos
// por usuarios; nunca código, configuración, respaldos ni logs. Sin esta lista,
// un Admin u Operador podía pedir `config.php` y bajarse las credenciales de la
// base, la clave de cifrado y los secretos de SMTP/Twilio/IMAP.
// 'uploads' se admite porque los candidatos de resolución más abajo aceptan esa
// forma; dentro solo hay directorios de archivos subidos, y el filtro de ".." y el
// de extensión siguen aplicando igual.
$directoriosPermitidos = ['receipts', 'proof_of_sending', 'profile_pics', 'verifications', 'liquidaciones', 'temp_orders', 'uploads'];
$primerSegmento = explode('/', $fileRequest)[0];
if (!in_array($primerSegmento, $directoriosPermitidos, true)) {
    http_response_code(403);
    exit;
}

// Whitelist de extensiones: solo lo que un usuario puede subir como comprobante
// o documento. Bloquea .php, .sql, .log, .env y cualquier otra cosa ejecutable.
$extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$extension = strtolower(pathinfo($fileRequest, PATHINFO_EXTENSION));
if (!in_array($extension, $extensionesPermitidas, true)) {
    http_response_code(403);
    exit;
}

$hasPermission = false;

if ($isAdminOrOperator) {
    $hasPermission = true;
}
else {
    // Se compara contra el primer segmento de la ruta, no con strpos() en cualquier
    // posición: "receipts/profile_pics_ajeno.jpg" no debe pasar por la puerta de perfiles.
    if ($primerSegmento === 'profile_pics') {
        $filename = basename($fileRequest);
        $prefix = 'user_profile_' . $userId . '_';
        if (strpos($filename, $prefix) === 0) {
            $hasPermission = true;
        }
    }
    if (!$hasPermission && ($primerSegmento === 'receipts' || $primerSegmento === 'proof_of_sending')) {
        // Se prueban las tres formas del separador: la base guarda rutas con "\",
        // pero la petición puede llegar con "/" o tal cual vino.
        $dbPath = $fileRequestDb;
        $dbPathBarra = $fileRequest;
        $dbPathBackslash = str_replace('/', '\\', $fileRequest);

        $sqlPerm = "SELECT TransaccionID FROM transacciones
                    WHERE UserID = ?
                    AND (ComprobanteURL IN (?, ?, ?) OR ComprobanteEnvioURL IN (?, ?, ?))";

        $stmtPerm = $conexion->prepare($sqlPerm);
        if ($stmtPerm) {
            $stmtPerm->bind_param(
                "issssss",
                $userId,
                $dbPath, $dbPathBarra, $dbPathBackslash,
                $dbPath, $dbPathBarra, $dbPathBackslash
            );
            $stmtPerm->execute();
            $resPerm = $stmtPerm->get_result();
            if ($resPerm->num_rows > 0) {
                $hasPermission = true;
            }
            $stmtPerm->close();
        }
    }
}

if (!$hasPermission) {
    http_response_code(403);
    exit;
}

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
        // El separador final es necesario: sin él, un directorio hermano como
        // "remesas_private_bak" pasaría el prefijo.
        if ($realFullPath && strpos($realFullPath, $basePrivate . DIRECTORY_SEPARATOR) === 0) {
            break;
        } else {
            $realFullPath = null;
        }
    }
}

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

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    // Un MIME fuera de la lista se rechaza. Antes se servía igual como "attachment",
    // así que un archivo con extensión permitida pero contenido de otro tipo salía igual.
    if (!in_array($mimeType, $allowed_mimes, true)) {
        http_response_code(403);
        exit;
    }
    $disposition = 'inline';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realFullPath));
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($realFullPath) . '"');
    header('Cache-Control: private, max-age=86400');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; plugin-types application/pdf;");
    
    if (ob_get_length()) ob_clean();
    flush();
    
    readfile($realFullPath);
    exit;

} else {
    http_response_code(404);
    exit;
}