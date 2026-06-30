<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    http_response_code(403);
    die("Acceso denegado.");
}

$revendedorId = isset($_GET['revendedorId']) ? (int)$_GET['revendedorId'] : 0;
$buscar       = trim($_GET['buscar'] ?? '');
$estadoFiltro = isset($_GET['estado']) ? (int)$_GET['estado'] : 0;

// Datos del revendedor si viene filtrado por uno
$revendedor = null;
if ($revendedorId > 0) {
    $stmtR = $conexion->prepare(
        "SELECT UserID, PrimerNombre, PrimerApellido, Email FROM usuarios WHERE UserID = ? AND RolID = 4 LIMIT 1"
    );
    $stmtR->bind_param("i", $revendedorId);
    $stmtR->execute();
    $revendedor = $stmtR->get_result()->fetch_assoc();
    $stmtR->close();
    if (!$revendedor) {
        header('Location: revendedores.php');
        exit();
    }
}

// Query
$where  = ["1=1"];
$params = [];
$types  = "";

if ($revendedorId > 0) {
    $where[]  = "T.UserID = ?";
    $params[] = $revendedorId;
    $types   .= "i";
}
if ($buscar !== '') {
    $where[]  = "(T.BeneficiarioNombre LIKE ? OR U.PrimerNombre LIKE ? OR U.PrimerApellido LIKE ? OR T.TransaccionID LIKE ?)";
    $term     = "%$buscar%";
    $params   = array_merge($params, [$term, $term, $term, $term]);
    $types   .= "ssss";
}
if ($estadoFiltro > 0) {
    $where[]  = "T.EstadoID = ?";
    $params[] = $estadoFiltro;
    $types   .= "i";
}

$whereClause = implode(" AND ", $where);
$sql = "
    SELECT T.TransaccionID, T.MontoOrigen, T.MontoDestino, T.FechaTransaccion,
           U.PrimerNombre, U.PrimerApellido, U.Email,
           T.BeneficiarioNombre, ET.NombreEstado, ET.EstadoID,
           P.NombrePais AS PaisDestino,
           CB.CodigoMoneda
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
    LEFT JOIN paises P ON CB.PaisID = P.PaisID
    WHERE $whereClause
    ORDER BY T.FechaTransaccion DESC
    LIMIT 500
";

$stmt = $conexion->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$estados = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion ORDER BY EstadoID")->fetch_all(MYSQLI_ASSOC);

$pageTitle  = $revendedor
    ? 'Órdenes de ' . htmlspecialchars($revendedor['PrimerNombre'] . ' ' . $revendedor['PrimerApellido'])
    : 'Historial de Órdenes';
$pageScript = '';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$estadoBadge = [
    1 => ['bg-secondary',     'Pendiente pago'],
    2 => ['bg-warning text-dark', 'En verificación'],
    3 => ['bg-info text-dark',    'En proceso'],
    4 => ['bg-danger',            'Cancelado'],
    5 => ['bg-success',           'Exitoso'],
    6 => ['bg-secondary',         'Pausado'],
    7 => ['bg-primary',           'En revisión'],
];
?>

<div class="container mt-4">

    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="revendedores.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="mb-0 h4 fw-bold">
                <?php if ($revendedor): ?>
                    <i class="bi bi-receipt text-primary me-1"></i>
                    Órdenes de <?php echo htmlspecialchars($revendedor['PrimerNombre'] . ' ' . $revendedor['PrimerApellido']); ?>
                <?php else: ?>
                    <i class="bi bi-receipt text-primary me-1"></i> Historial de Órdenes
                <?php endif; ?>
            </h1>
            <?php if ($revendedor): ?>
                <small class="text-muted"><?php echo htmlspecialchars($revendedor['Email']); ?></small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-3 bg-light">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <?php if ($revendedorId > 0): ?>
                    <input type="hidden" name="revendedorId" value="<?php echo $revendedorId; ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <input type="text" name="buscar" class="form-control form-control-sm"
                           placeholder="Buscar por beneficiario, cliente o ID…"
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="0">Todos los estados</option>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?php echo $e['EstadoID']; ?>"
                                <?php echo $estadoFiltro === (int)$e['EstadoID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['NombreEstado']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <a href="transacciones.php<?php echo $revendedorId ? '?revendedorId=' . $revendedorId : ''; ?>"
                       class="btn btn-outline-secondary btn-sm ms-1">Limpiar</a>
                </div>
                <div class="col-auto ms-auto">
                    <span class="badge bg-secondary"><?php echo count($transacciones); ?> órdenes</span>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <?php if (!$revendedor): ?><th>Cliente</th><?php endif; ?>
                            <th>Beneficiario</th>
                            <th class="text-end">Monto origen</th>
                            <th class="text-end">Monto destino</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    No hay órdenes para mostrar.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($transacciones as $tx):
                            [$badgeClass, $badgeText] = $estadoBadge[$tx['EstadoID']] ?? ['bg-secondary', $tx['NombreEstado']];
                        ?>
                        <tr>
                            <td class="text-muted small">#<?php echo $tx['TransaccionID']; ?></td>
                            <?php if (!$revendedor): ?>
                            <td>
                                <div class="fw-semibold small"><?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($tx['Email']); ?></small>
                            </td>
                            <?php endif; ?>
                            <td class="fw-semibold small"><?php echo htmlspecialchars($tx['BeneficiarioNombre']); ?></td>
                            <td class="text-end small"><?php echo number_format($tx['MontoOrigen'], 2, ',', '.'); ?></td>
                            <td class="text-end small">
                                <?php echo $tx['CodigoMoneda'] ? htmlspecialchars($tx['CodigoMoneda']) . ' ' : ''; ?>
                                <?php echo number_format($tx['MontoDestino'], 2, ',', '.'); ?>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($tx['PaisDestino'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                            <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($tx['FechaTransaccion'])); ?></td>
                            <td>
                                <a href="orden.php?id=<?php echo $tx['TransaccionID']; ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
