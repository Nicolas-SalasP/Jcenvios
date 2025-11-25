<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Logs del Sistema';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$logsPorPagina = 20;
$paginaActual = (int) ($_GET['page'] ?? 1);
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';
$emailUsuario = $_GET['email_usuario'] ?? '';
$offset = ($paginaActual - 1) * $logsPorPagina;

$sqlBase = "FROM logs l LEFT JOIN usuarios u ON l.UserID = u.UserID";
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($fechaInicio)) {
    $whereConditions[] = "l.Timestamp >= ?";
    $params[] = $fechaInicio . " 00:00:00";
    $paramTypes .= 's';
}
if (!empty($fechaFin)) {
    $whereConditions[] = "l.Timestamp <= ?";
    $params[] = $fechaFin . " 23:59:59";
    $paramTypes .= 's';
}
if (!empty($emailUsuario)) {
    $whereConditions[] = "u.Email LIKE ?";
    $params[] = "%" . $emailUsuario . "%";
    $paramTypes .= 's';
}

$sqlWhere = '';
if (!empty($whereConditions)) {
    $sqlWhere = " WHERE " . implode(' AND ', $whereConditions);
}

$sqlTotal = "SELECT COUNT(*) as total " . $sqlBase . $sqlWhere;
$stmtTotal = $conexion->prepare($sqlTotal);
if (!empty($params)) {
    $stmtTotal->bind_param($paramTypes, ...$params);
}
$stmtTotal->execute();
$totalLogs = $stmtTotal->get_result()->fetch_assoc()['total'];
$stmtTotal->close();
$totalPaginas = ceil($totalLogs / $logsPorPagina);

// --- CAMBIO: TRAER NOMBRE Y APELLIDO ---
$sqlLogs = "SELECT l.LogID, l.Accion, l.Detalles, l.Timestamp, u.Email, u.PrimerNombre, u.PrimerApellido " . $sqlBase . $sqlWhere . " ORDER BY l.Timestamp DESC LIMIT ? OFFSET ?";
$stmtLogs = $conexion->prepare($sqlLogs);
$params[] = $logsPorPagina;
$params[] = $offset;
$paramTypes .= 'ii';
if (!empty($params)) {
    $stmtLogs->bind_param($paramTypes, ...$params);
}
$stmtLogs->execute();
$logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLogs->close();
?>

<div class="container mt-4">
    <h1 class="mb-4">Logs del Sistema</h1>

    <div class="row">
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <h5>Filtrar Logs</h5>
                <form action="logs.php" method="GET">
                    <div class="mb-3">
                        <label for="fecha_inicio" class="form-label">Desde:</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                            value="<?php echo htmlspecialchars($fechaInicio); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="fecha_fin" class="form-label">Hasta:</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                            value="<?php echo htmlspecialchars($fechaFin); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email_usuario" class="form-label">Email del Usuario:</label>
                        <input type="text" class="form-control" id="email_usuario" name="email_usuario"
                            placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($emailUsuario); ?>">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="logs.php" class="btn btn-secondary">Limpiar Filtros</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-9">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Usuario / Responsable</th>
                            <th>Accion</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No se encontraron logs con los filtros aplicados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date("d/m/Y H:i:s", strtotime($log['Timestamp'])); ?></td>
                                    <td>
                                        <?php
                                        // --- CAMBIO: MOSTRAR NOMBRE + EMAIL ---
                                        if (!empty($log['Email'])) {
                                            echo "<strong>" . htmlspecialchars($log['PrimerNombre'] . ' ' . $log['PrimerApellido']) . "</strong><br>";
                                            echo "<small class='text-muted'>" . htmlspecialchars($log['Email']) . "</small>";
                                        } else {
                                            echo "<em>Sistema</em>";
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['Accion']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Detalles']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Paginación de logs">
                <ul class="pagination justify-content-center">
                    <?php if ($paginaActual > 1): ?>
                        <li class="page-item"><a class="page-link"
                                href="?page=<?php echo $paginaActual - 1; ?>&<?php echo http_build_query(['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'email_usuario' => $emailUsuario]); ?>">Anterior</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?php if ($i == $paginaActual)
                            echo 'active'; ?>">
                            <a class="page-link"
                                href="?page=<?php echo $i; ?>&<?php echo http_build_query(['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'email_usuario' => $emailUsuario]); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <li class="page-item"><a class="page-link"
                                href="?page=<?php echo $paginaActual + 1; ?>&<?php echo http_build_query(['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'email_usuario' => $emailUsuario]); ?>">Siguiente</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>