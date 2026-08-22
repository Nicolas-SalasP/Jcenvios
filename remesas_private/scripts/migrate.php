<?php
/**
 * Runner de migraciones — corre migrations/*.sql pendientes, en orden, una
 * sola vez cada una (tabla de control _migrations_applied). Pensado para
 * correr por CLI, tanto a mano (SSH) como automático desde el pipeline de
 * deploy en cada push a main.
 *
 * Uso: php remesas_private/scripts/migrate.php
 * Exit code 0 = OK (incluye "nada pendiente"). Exit code 1 = falló alguna.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede correr por CLI.');
}

// Desde PHP 8.1 mysqli reporta los errores lanzando mysqli_sql_exception en vez
// de devolver false. Sin esto, el `if (!$mysqli->multi_query(...))` de abajo
// NUNCA se evaluaba: la excepción salía sin capturar, el script moría con exit
// 255 y no imprimía qué migración ni qué error. Fue exactamente lo que pasó en
// el deploy del 2026-08-22: "Aplicando 023 ..." y nada más.
//
// Se apaga el modo excepción para que el manejo de errores explícito de este
// archivo (que sí informa el archivo y el mensaje) vuelva a tener efecto, y
// igual se envuelve todo en un catch por si algo más falla.
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Error de conexión: {$mysqli->connect_error}\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$mysqli->query("
    CREATE TABLE IF NOT EXISTS _migrations_applied (
        Filename VARCHAR(255) NOT NULL PRIMARY KEY,
        AppliedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$migrationsDir = __DIR__ . '/../../migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_STRING);

$applied = [];
$res = $mysqli->query("SELECT Filename FROM _migrations_applied");
while ($row = $res->fetch_assoc()) {
    $applied[$row['Filename']] = true;
}

$pending = array_filter($files, fn($f) => !isset($applied[basename($f)]));

if (empty($pending)) {
    echo "Sin migraciones pendientes (" . count($files) . " ya aplicadas).\n";
    exit(0);
}

echo count($pending) . " migración(es) pendiente(s):\n";

$hadError = false;
try {
foreach ($pending as $file) {
    $name = basename($file);
    echo "-> Aplicando $name ... ";

    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "ERROR (no se pudo leer el archivo)\n";
        $hadError = true;
        break;
    }

    if (!$mysqli->multi_query($sql)) {
        echo "ERROR: {$mysqli->error}\n";
        $hadError = true;
        break;
    }
    // Hay que consumir todos los resultsets de multi_query antes de seguir.
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->error) {
        echo "ERROR: {$mysqli->error}\n";
        $hadError = true;
        break;
    }

    $stmt = $mysqli->prepare("INSERT INTO _migrations_applied (Filename) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();

    echo "OK\n";
}

} catch (\Throwable $e) {
    // Red de seguridad: cualquier cosa que se escape del manejo explícito de
    // arriba (un Error de PHP, una excepción de una extensión) tiene que decir
    // QUÉ pasó. Morir en silencio con exit 255 deja el deploy sin diagnóstico.
    echo "ERROR INESPERADO: " . get_class($e) . ": " . $e->getMessage()
       . " (" . $e->getFile() . ':' . $e->getLine() . ")\n";
    $hadError = true;
}

$mysqli->close();

if ($hadError) {
    fwrite(STDERR, "Migración detenida por error. Las siguientes no se aplicaron.\n");
    exit(1);
}

echo "Listo.\n";
exit(0);
