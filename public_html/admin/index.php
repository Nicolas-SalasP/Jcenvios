<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

// 1. SEGURIDAD
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}
if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false) {
    header('Location: ' . BASE_URL . '/dashboard/seguridad.php');
    exit();
}

// 2. OBTENER ESTADOS (Solo si NO es ajax)
$listaEstados = [];
if (!isset($_GET['ajax'])) {
    $estadosDb = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion ORDER BY NombreEstado ASC");
    $listaEstados = $estadosDb ? $estadosDb->fetch_all(MYSQLI_ASSOC) : [];
}

// 3. CAPTURAR FILTROS
$f_id = $_GET['f_id'] ?? '';
$f_user = $_GET['f_user'] ?? '';
$f_date = $_GET['f_date'] ?? '';
$f_status = $_GET['f_status'] ?? '';

// 4. CONSTRUCCIÓN DE LA CONSULTA
$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($f_id)) {
    $whereClause .= " AND T.TransaccionID = ?";
    $params[] = $f_id;
    $types .= "i";
}
if (!empty($f_user)) {
    $whereClause .= " AND (U.PrimerNombre LIKE ? OR U.PrimerApellido LIKE ?)";
    $likeUser = "%" . $f_user . "%";
    $params[] = $likeUser;
    $params[] = $likeUser;
    $types .= "ss";
}
if (!empty($f_date)) {
    $whereClause .= " AND DATE(T.FechaTransaccion) = ?";
    $params[] = $f_date;
    $types .= "s";
}
if (!empty($f_status)) {
    $whereClause .= " AND T.EstadoID = ?";
    $params[] = $f_status;
    $types .= "i";
}

// 5. CONFIGURACIÓN DE PAGINACIÓN
$registrosPorPagina = 100;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// 6. CONTAR TOTAL
$totalPaginas = 1;
$totalRegistros = 0;

if (!isset($_GET['ajax'])) {
    $sqlCount = "
        SELECT COUNT(*) as total 
        FROM transacciones T
        JOIN usuarios U ON T.UserID = U.UserID 
        $whereClause
    ";
    $stmtCount = $conexion->prepare($sqlCount);
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
    $stmtCount->close();
}

// 7. OBTENER DATOS
$sql = "
    SELECT T.*, U.PrimerNombre, U.PrimerApellido,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    $whereClause
    ORDER BY T.FechaTransaccion DESC
    LIMIT ? OFFSET ?
";

$queryParams = $params;
$queryTypes = $types . "ii";
$queryParams[] = $registrosPorPagina;
$queryParams[] = $offset;

$stmt = $conexion->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Helpers
function getStatusBadgeClass($statusName)
{
    switch ($statusName) {
        case 'Exitoso':
            return 'bg-success';
        case 'En Proceso':
            return 'bg-primary';
        case 'En Verificación':
            return 'bg-info text-dark';
        case 'Cancelado':
            return 'bg-danger';
        case 'Pendiente de Pago':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

// --- MODO AJAX (SOLO TABLA) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (empty($transacciones)) {
        echo '<tr><td colspan="9" class="text-center py-4 text-muted">No se encontraron resultados.</td></tr>';
    } else {
        foreach ($transacciones as $tx) {
            ?>
            <tr>
                <td><?php echo $tx['TransaccionID']; ?></td>
                <td><?php echo date("d/m/y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                <td class="search-user">
                    <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                </td>
                <td class="search-beneficiary"><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?></td>
                <td>
                    <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre'] ?? ''); ?>">
                        <?php echo htmlspecialchars($tx['EstadoNombre'] ?? 'Desconocido'); ?>
                    </span>
                </td>
                <td>
                    <div class="d-flex align-items-center justify-content-between">
                        <span><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                        <?php if (in_array($tx['EstadoNombre'], ['Exitoso', 'En Proceso'])): ?>
                            <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2 border-0"
                                data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                data-current-val="<?php echo $tx['ComisionDestino']; ?>" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center">
                    <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>" target="_blank"
                        class="btn btn-sm btn-info text-white" title="PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                </td>
                <td class="text-center">
                    <?php if (!empty($tx['ComprobanteURL'])): ?>
                        <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin" data-bs-toggle="modal"
                            data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                            data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                            data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>"
                            data-start-type="user" title="Ver">
                            <i class="bi bi-eye"></i>
                        </button>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                        <button class="btn btn-sm btn-success view-comprobante-btn-admin" data-bs-toggle="modal"
                            data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                            data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteURL']) : ''; ?>"
                            data-envio-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']); ?>"
                            data-start-type="admin" title="Ver">
                            <i class="bi bi-receipt"></i>
                        </button>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    exit();
}

function getPaginationUrl($page, $filters)
{
    $params = array_merge($filters, ['pagina' => $page]);
    return '?' . http_build_query($params);
}
$currentFilters = ['f_id' => $f_id, 'f_user' => $f_user, 'f_date' => $f_date, 'f_status' => $f_status];

$pageTitle = 'Panel de Administración';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="mb-0 me-3">Panel de Administración</h1>
        <div class="d-flex align-items-center gap-3">

            <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end mt-3 mt-md-0">
                <a href="exportar_transacciones.php" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel-fill"></i> Exportar a Excel
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/pendientes.php" class="btn btn-primary">
                    Ver Transacciones Pendientes
                </a>
            </div>
        </div>
    </div>

    <div class="bg-light p-3 rounded mb-4 border">
        <form method="GET" class="row g-2 align-items-end" id="admin-filter-form">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">ID Orden</label>
                <input type="number" name="f_id" class="form-control form-control-sm" placeholder="#"
                    value="<?php echo htmlspecialchars($f_id); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold mb-1">Usuario</label>
                <input type="text" name="f_user" class="form-control form-control-sm" placeholder="Nombre..."
                    value="<?php echo htmlspecialchars($f_user); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold mb-1">Estado</label>
                <select name="f_status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($listaEstados as $estado): ?>
                        <option value="<?php echo $estado['EstadoID']; ?>" <?php echo ($f_status == $estado['EstadoID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado['NombreEstado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Fecha</label>
                <input type="date" name="f_date" class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($f_date); ?>">
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button>
                <a href="index.php" class="btn btn-sm btn-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive position-relative">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Beneficiario</th>
                    <th>Estado</th>
                    <th>Comisión</th>
                    <th>Orden</th>
                    <th>Comp. Usuario</th>
                    <th>Comp. Admin</th>
                </tr>
            </thead>
            <tbody id="transactionsTableBody">
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No se encontraron resultados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $tx): ?>
                        <tr>
                            <td><?php echo $tx['TransaccionID']; ?></td>
                            <td><?php echo date("d/m/y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                            <td class="search-user">
                                <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                            </td>
                            <td class="search-beneficiary"><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($tx['EstadoNombre'] ?? 'Desconocido'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                                    <?php if (in_array($tx['EstadoNombre'], ['Exitoso', 'En Proceso'])): ?>
                                        <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2 border-0"
                                            data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-current-val="<?php echo $tx['ComisionDestino']; ?>" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                    target="_blank" class="btn btn-sm btn-info text-white" title="PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($tx['ComprobanteURL'])): ?>
                                    <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin" data-bs-toggle="modal"
                                        data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                        data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>"
                                        data-start-type="user" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                    <button class="btn btn-sm btn-success view-comprobante-btn-admin" data-bs-toggle="modal"
                                        data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteURL']) : ''; ?>"
                                        data-envio-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']); ?>"
                                        data-start-type="admin" title="Ver">
                                        <i class="bi bi-receipt"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Navegación de páginas" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="<?php echo getPaginationUrl($paginaActual - 1, $currentFilters); ?>">Anterior</a>
                </li>
                <?php
                $rango = 2;
                for ($i = 1; $i <= $totalPaginas; $i++):
                    if ($i == 1 || $i == $totalPaginas || ($i >= $paginaActual - $rango && $i <= $paginaActual + $rango)):
                        ?>
                        <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo getPaginationUrl($i, $currentFilters); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php elseif ($i == $paginaActual - $rango - 1 || $i == $paginaActual + $rango + 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; endfor; ?>
                <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="<?php echo getPaginationUrl($paginaActual + 1, $currentFilters); ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="editCommissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Comisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Estás editando la comisión para la Orden <strong id="modal-commission-tx-id-label"></strong></p>
                <form id="edit-commission-form">
                    <input type="hidden" id="commission-tx-id" name="transactionId">
                    <div class="mb-3">
                        <label for="new-commission-input" class="form-label">Monto de la Comisión</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="new-commission-input"
                            name="newCommission" required>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Al guardar, el saldo contable se ajustará automáticamente.
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light p-0">
                <div class="btn-group w-100 rounded-0" role="group">
                    <button type="button" class="btn btn-primary" id="tab-btn-user"><i
                            class="bi bi-person me-2"></i>Pago Cliente</button>
                    <button type="button" class="btn btn-outline-primary" id="tab-btn-admin"><i
                            class="bi bi-send me-2"></i>Envío Admin</button>
                </div>
                <div class="p-3"
                    style="min-height: 400px; display: flex; align-items: center; justify-content: center;">
                    <img id="comprobante-img-full" src="" class="img-fluid rounded shadow-sm" style="max-height: 70vh;"
                        alt="Comprobante">
                </div>
            </div>
            <div class="modal-footer justify-content-center border-top-0">
                <a href="#" id="download-comprobante-btn" class="btn btn-dark" download target="_blank"><i
                        class="bi bi-download me-2"></i>Descargar</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>