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
if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false) {
    header('Location: ' . BASE_URL . '/dashboard/seguridad.php');
    exit();
}

// Helpers compartidos con el panel de operador (saneo de filtros, badges, paginación).
require_once __DIR__ . '/../../remesas_private/src/templates/partials/orden_helpers.php';

$listaEstados = [];
$listaPaises = [];
if (!isset($_GET['ajax'])) {
    $estadosDb = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion ORDER BY NombreEstado ASC");
    $listaEstados = $estadosDb ? $estadosDb->fetch_all(MYSQLI_ASSOC) : [];
    $paisesDb = $conexion->query("SELECT PaisID, NombrePais FROM paises WHERE Activo = 1 ORDER BY NombrePais ASC");
    $listaPaises = $paisesDb ? $paisesDb->fetch_all(MYSQLI_ASSOC) : [];
}

// Todos los filtros se sanean acá: un valor inválido queda en '' y el `if (!empty(...))`
// que sigue lo descarta, en vez de viajar al bind. Rechaza arrays (?f_status[]=1),
// fechas imposibles y strings tipo "3a" que MySQL convertiría en silencio.
$f_id = ordenFiltroEntero($_GET['f_id'] ?? '');
$f_user = ordenFiltroTexto($_GET['f_user'] ?? '');
$f_date = ordenFiltroFecha($_GET['f_date'] ?? '');
$f_status = ordenFiltroEntero($_GET['f_status'] ?? '');
$f_origen = ordenFiltroEntero($_GET['f_origen'] ?? '');
$f_confirm = ordenFiltroTexto($_GET['f_confirm'] ?? '', 20);
$f_destino = ordenFiltroEntero($_GET['f_destino'] ?? '');
$f_emision_desde = ordenFiltroFecha($_GET['f_emision_desde'] ?? '');
$f_emision_hasta = ordenFiltroFecha($_GET['f_emision_hasta'] ?? '');
$f_comprobante_desde = ordenFiltroFecha($_GET['f_comprobante_desde'] ?? '');
$f_comprobante_hasta = ordenFiltroFecha($_GET['f_comprobante_hasta'] ?? '');
$f_completado_desde = ordenFiltroFecha($_GET['f_completado_desde'] ?? '');
$f_completado_hasta = ordenFiltroFecha($_GET['f_completado_hasta'] ?? '');
$f_moneda_origen = ordenFiltroMoneda($_GET['f_moneda_origen'] ?? '');
$f_moneda_destino = ordenFiltroMoneda($_GET['f_moneda_destino'] ?? '');

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($f_id)) {
    $whereClause .= " AND T.TransaccionID = ?";
    $params[] = $f_id;
    $types .= "i";
}
if (!empty($f_user)) {
    $whereClause .= " AND (
        U.PrimerNombre LIKE ? OR 
        U.PrimerApellido LIKE ? OR 
        CONCAT_WS(' ', U.PrimerNombre, U.PrimerApellido) LIKE ? OR
        CONCAT_WS(' ', U.PrimerNombre, U.SegundoNombre, U.PrimerApellido, U.SegundoApellido) LIKE ? OR
        T.BeneficiarioNombre LIKE ?
    )";
    $likeUser = "%" . $f_user . "%";
    array_push($params, $likeUser, $likeUser, $likeUser, $likeUser, $likeUser);
    $types .= "sssss";
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
if (!empty($f_confirm) && in_array($f_confirm, ['pendiente', 'recibido', 'no_recibido'], true)) {
    $whereClause .= " AND T.ConfirmacionRecepcion = ?";
    $params[] = $f_confirm;
    $types .= "s";
}
if (!empty($f_emision_desde)) {
    $whereClause .= " AND DATE(T.FechaTransaccion) >= ?";
    $params[] = $f_emision_desde;
    $types .= "s";
}
if (!empty($f_emision_hasta)) {
    $whereClause .= " AND DATE(T.FechaTransaccion) <= ?";
    $params[] = $f_emision_hasta;
    $types .= "s";
}
if (!empty($f_comprobante_desde)) {
    $whereClause .= " AND DATE(T.FechaSubidaComprobante) >= ?";
    $params[] = $f_comprobante_desde;
    $types .= "s";
}
if (!empty($f_comprobante_hasta)) {
    $whereClause .= " AND DATE(T.FechaSubidaComprobante) <= ?";
    $params[] = $f_comprobante_hasta;
    $types .= "s";
}
if (!empty($f_completado_desde)) {
    $whereClause .= " AND DATE(T.FechaCompletado) >= ?";
    $params[] = $f_completado_desde;
    $types .= "s";
}
if (!empty($f_completado_hasta)) {
    $whereClause .= " AND DATE(T.FechaCompletado) <= ?";
    $params[] = $f_completado_hasta;
    $types .= "s";
}
if (!empty($f_moneda_origen)) {
    $whereClause .= " AND T.MonedaOrigen = ?";
    $params[] = $f_moneda_origen;
    $types .= "s";
}
if (!empty($f_moneda_destino)) {
    $whereClause .= " AND T.MonedaDestino = ?";
    $params[] = $f_moneda_destino;
    $types .= "s";
}

// CB (cuenta beneficiaria -> país destino) y TS (tasa -> país origen) se unen SIEMPRE:
// CB.PaisID es necesario para el botón "Pagar" (el modal filtra los bancos por país destino)
// y ambos se usan en los filtros origen/destino. Son LEFT JOIN 1:1 por PK -> no multiplican
// filas ni alteran el COUNT.
$baseJoins = " LEFT JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID";
$joinClauseCount = "JOIN usuarios U ON T.UserID = U.UserID" . $baseJoins;
$joinClauseData = "JOIN usuarios U ON T.UserID = U.UserID LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID" . $baseJoins;

if (!empty($f_origen)) {
    $whereClause .= " AND TS.PaisOrigenID = ?";
    $params[] = $f_origen;
    $types .= "i";
}
if (!empty($f_destino)) {
    $whereClause .= " AND CB.PaisID = ?";
    $params[] = $f_destino;
    $types .= "i";
}

$registrosPorPagina = 100;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

$totalPaginas = 1;
$totalRegistros = 0;

if (!isset($_GET['ajax'])) {
    $sqlCount = "
        SELECT COUNT(*) as total 
        FROM transacciones T
        $joinClauseCount 
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

$sql = "
    SELECT T.*, U.PrimerNombre, U.PrimerApellido, U.Telefono AS ClienteTelefono,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre,
        U.NumeroDocumento AS UsuarioDocumento,
        CB.PaisID AS PaisDestinoID,
        -- F3.1 ConfirmacionRecepcion ya viene en T.* gracias al ALTER TABLE
        -- F3.2: contar envíos exitosos previos de este usuario a la misma cuenta/teléfono
        (SELECT COUNT(*)
         FROM transacciones T2
         JOIN estados_transaccion ET2 ON T2.EstadoID = ET2.EstadoID
         WHERE T2.UserID = T.UserID
           AND T2.TransaccionID <> T.TransaccionID
           AND ET2.NombreEstado = 'Exitoso'
           AND (
              (COALESCE(T.BeneficiarioNumeroCuenta,'') <> '' AND T2.BeneficiarioNumeroCuenta = T.BeneficiarioNumeroCuenta)
           OR (COALESCE(T.BeneficiarioTelefono,'')     <> '' AND T2.BeneficiarioTelefono     = T.BeneficiarioTelefono)
           )
        ) AS EnviosPreviosMismaCuenta
    FROM transacciones T
    $joinClauseData
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

$sqlCuentas = "
    SELECT c.CuentaAdminID, c.Banco, c.Titular, c.SaldoActual, p.CodigoMoneda, c.PaisID
    FROM cuentas_bancarias_admin c
    JOIN paises p ON c.PaisID = p.PaisID
    WHERE c.Activo = 1 AND c.RolCuentaID IN (2, 3) AND (p.Rol = 'Destino' OR p.Rol = 'Ambos')
";
$cuentasDestino = $conexion->query($sqlCuentas)->fetch_all(MYSQLI_ASSOC);


// Contexto de render de la fila de ordenes. El admin tiene todas las acciones.
$ordenRowCtx = [
    'puedePagar'          => true,
    'puedeEditarComision' => true,
    'secureFileBase'      => BASE_URL . '/admin/view_secure_file.php',
    'ordenUrl'            => BASE_URL . '/admin/orden.php',
    'facturaUrl'          => BASE_URL . '/generar-factura.php',
];

$ordenRowPartial = __DIR__ . '/../../remesas_private/src/templates/partials/orden_row.php';

// --- MODO AJAX (SOLO FILAS) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (empty($transacciones)) {
        echo '<tr><td colspan="11" class="text-center py-4 text-muted">No se encontraron resultados.</td></tr>';
    } else {
        foreach ($transacciones as $tx) {
            include $ordenRowPartial;
        }
    }
    exit();
}

$currentFilters = [
    'f_id' => $f_id, 'f_user' => $f_user, 'f_date' => $f_date, 'f_status' => $f_status,
    'f_origen' => $f_origen, 'f_destino' => $f_destino, 'f_confirm' => $f_confirm,
    'f_emision_desde' => $f_emision_desde, 'f_emision_hasta' => $f_emision_hasta,
    'f_comprobante_desde' => $f_comprobante_desde, 'f_comprobante_hasta' => $f_comprobante_hasta,
    'f_completado_desde' => $f_completado_desde, 'f_completado_hasta' => $f_completado_hasta,
    'f_moneda_origen' => $f_moneda_origen, 'f_moneda_destino' => $f_moneda_destino,
];

$pageTitle = 'Panel de Administración';
$pageScripts = require __DIR__ . '/../../remesas_private/src/templates/admin_page_scripts.php';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div id="app-data" class="d-none" data-cuentas-destino='<?php echo htmlspecialchars(json_encode($cuentasDestino), ENT_QUOTES, "UTF-8"); ?>'></div>

<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="mb-0 me-3">Panel de Administración</h1>
        <div class="d-flex align-items-center gap-3">

            <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end mt-3 mt-md-0">
                <a href="exportar_transacciones.php?mode=dia" target="_blank" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Excel Hoy
                </a>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal"
                    data-bs-target="#exportModal">
                    <i class="bi bi-calendar-range"></i> Histórico
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/pendientes.php" class="btn btn-primary">
                    Ver Transacciones Pendientes
                </a>
            </div>
        </div>
    </div>

    <div class="bg-light p-3 rounded mb-4 border">
        <form method="GET" class="row g-2 align-items-end" id="admin-filter-form">
            <div class="col-6 col-md-1">
                <label class="form-label small fw-bold mb-1">ID</label>
                <input type="number" name="f_id" class="form-control form-control-sm" placeholder="#"
                    value="<?php echo htmlspecialchars($f_id); ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Usuario / Ben.</label>
                <input type="text" name="f_user" class="form-control form-control-sm" placeholder="Nombre..."
                    value="<?php echo htmlspecialchars($f_user); ?>">
            </div>
            <div class="col-6 col-md-2">
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
                <label class="form-label small fw-bold mb-1">Origen</label>
                <select name="f_origen" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($listaPaises as $pais): ?>
                        <option value="<?php echo $pais['PaisID']; ?>" <?php echo ($f_origen == $pais['PaisID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pais['NombrePais']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Destino</label>
                <select name="f_destino" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($listaPaises as $pais): ?>
                        <option value="<?php echo $pais['PaisID']; ?>" <?php echo ($f_destino == $pais['PaisID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pais['NombrePais']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Fecha</label>
                <input type="date" name="f_date" class="form-control form-control-sm"
                    value="<?php echo htmlspecialchars($f_date); ?>">
            </div>
            <?php /* Filtro confirmación cliente */ ?>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Confirmación</label>
                <select name="f_confirm" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="pendiente" <?= ($f_confirm === 'pendiente') ? 'selected' : '' ?>>Sin confirmar</option>
                    <option value="recibido" <?= ($f_confirm === 'recibido') ? 'selected' : '' ?>>Cliente recibió ✓</option>
                    <option value="no_recibido" <?= ($f_confirm === 'no_recibido') ? 'selected' : '' ?>>Cliente NO recibió ✗</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button>
                <a href="index.php" class="btn btn-sm btn-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            </div>

            <div class="col-12">
                <button type="button" class="btn btn-sm btn-link px-0" data-bs-toggle="collapse" data-bs-target="#masFiltros">
                    <i class="bi bi-funnel"></i> Más filtros (fechas por evento, moneda)
                </button>
            </div>

            <?php
            $hayFiltrosExtra = !empty($f_emision_desde) || !empty($f_emision_hasta) || !empty($f_comprobante_desde)
                || !empty($f_comprobante_hasta) || !empty($f_completado_desde) || !empty($f_completado_hasta)
                || !empty($f_moneda_origen) || !empty($f_moneda_destino);
            ?>
            <div id="masFiltros" class="collapse row g-2 align-items-end <?php echo $hayFiltrosExtra ? 'show' : ''; ?>">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Emisión desde</label>
                    <input type="date" name="f_emision_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_emision_desde); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Emisión hasta</label>
                    <input type="date" name="f_emision_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_emision_hasta); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Comprobante desde</label>
                    <input type="date" name="f_comprobante_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_comprobante_desde); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Comprobante hasta</label>
                    <input type="date" name="f_comprobante_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_comprobante_hasta); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Completada desde</label>
                    <input type="date" name="f_completado_desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_completado_desde); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Completada hasta</label>
                    <input type="date" name="f_completado_hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($f_completado_hasta); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Moneda origen</label>
                    <input type="text" name="f_moneda_origen" maxlength="10" class="form-control form-control-sm" placeholder="CLP" value="<?php echo htmlspecialchars($f_moneda_origen); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Moneda destino</label>
                    <input type="text" name="f_moneda_destino" maxlength="10" class="form-control form-control-sm" placeholder="VES" value="<?php echo htmlspecialchars($f_moneda_destino); ?>">
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive position-relative">
        <table class="table table-bordered table-hover align-middle">
                <?php require __DIR__ . '/../../remesas_private/src/templates/partials/orden_thead.php'; ?>
            <tbody id="transactionsTableBody">
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">No se encontraron resultados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $tx): ?>
                        <?php include $ordenRowPartial; ?>
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

<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fs-6">Exportar Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="exportar_transacciones.php" method="GET" target="_blank" id="formExport">
                    <input type="hidden" name="mode" value="rango">

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Desde:</label>
                        <input type="date" name="start" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Hasta:</label>
                        <input type="date" name="end" class="form-control form-control-sm"
                            value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <hr class="my-2">

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Origen (Opcional):</label>
                        <select name="origin_id" id="exportOrigin" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($listaPaises as $pais): ?>
                                <option value="<?php echo $pais['PaisID']; ?>">
                                    <?php echo htmlspecialchars($pais['NombrePais']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Destino (Opcional):</label>
                        <select name="dest_id" id="exportDest" class="form-select form-select-sm">
                            <option value="">Cualquiera</option>
                            <?php foreach ($listaPaises as $pais): ?>
                                <option value="<?php echo $pais['PaisID']; ?>">
                                    <?php echo htmlspecialchars($pais['NombrePais']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-download me-1"></i> Descargar Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../remesas_private/src/templates/partials/orden_modals.php'; ?>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>