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

$mysqli->close();

if ($hadError) {
    fwrite(STDERR, "Migración detenida por error. Las siguientes no se aplicaron.\n");
    exit(1);
}

echo "Listo.\n";
exit(0);
