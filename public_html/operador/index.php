<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Operador') {
    http_response_code(403);
    die("Acceso denegado.");
}

// Helpers compartidos con el panel de admin (saneo de filtros, badges, paginación).
require_once __DIR__ . '/../../remesas_private/src/templates/partials/orden_helpers.php';

// Estados que el operador tiene permitido ver en su historial:
// 3 En Proceso, 4 Exitoso, 5 Cancelado, 6 Pausado.
// El filtro de estado solo puede ESTRECHAR este conjunto, nunca ampliarlo.
// Literales fijos en el código: nunca se arma desde entrada del usuario.
$estadosVisibles = [3, 4, 5, 6];

$listaEstados = [];
$listaPaises = [];
if (!isset($_GET['ajax'])) {
    $inEstados = implode(',', $estadosVisibles);
    $estadosDb = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion WHERE EstadoID IN ($inEstados) ORDER BY NombreEstado ASC");
    $listaEstados = $estadosDb ? $estadosDb->fetch_all(MYSQLI_ASSOC) : [];
    $paisesDb = $conexion->query("SELECT PaisID, NombrePais FROM paises WHERE Activo = 1 ORDER BY NombrePais ASC");
    $listaPaises = $paisesDb ? $paisesDb->fetch_all(MYSQLI_ASSOC) : [];
}

// Todos los filtros se sanean acá: un valor inválido queda en '' y se descarta,
// en vez de viajar al bind. Rechaza arrays (?f_status[]=1), fechas imposibles y
// strings tipo "3a" que MySQL convertiría en silencio.
$f_id = ordenFiltroEntero($_GET['f_id'] ?? '');
$f_user = ordenFiltroTexto($_GET['f_user'] ?? '');
$f_date = ordenFiltroFecha($_GET['f_date'] ?? '');
// Se aceptan los nombres viejos del historial de operador para no romper enlaces guardados.
$f_status = ordenFiltroEntero($_GET['f_status'] ?? $_GET['f_estado'] ?? '');
$f_origen = ordenFiltroEntero($_GET['f_origen'] ?? '');
$f_confirm = ordenFiltroTexto($_GET['f_confirm'] ?? '', 20);
$f_destino = ordenFiltroEntero($_GET['f_destino'] ?? '');
$f_emision_desde = ordenFiltroFecha($_GET['f_emision_desde'] ?? $_GET['f_desde'] ?? '');
$f_emision_hasta = ordenFiltroFecha($_GET['f_emision_hasta'] ?? $_GET['f_hasta'] ?? '');
$f_comprobante_desde = ordenFiltroFecha($_GET['f_comprobante_desde'] ?? '');
$f_comprobante_hasta = ordenFiltroFecha($_GET['f_comprobante_hasta'] ?? '');
$f_completado_desde = ordenFiltroFecha($_GET['f_completado_desde'] ?? '');
$f_completado_hasta = ordenFiltroFecha($_GET['f_completado_hasta'] ?? '');
$f_moneda_origen = ordenFiltroMoneda($_GET['f_moneda_origen'] ?? '');
$f_moneda_destino = ordenFiltroMoneda($_GET['f_moneda_destino'] ?? '');

// El IN de estados permitidos se fija ANTES de cualquier filtro y no depende del GET,
// así la rama AJAX (segundo punto de entrada) hereda el mismo blindaje.
$whereClause = "WHERE T.EstadoID IN (" . implode(',', $estadosVisibles) . ")";
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
// Solo estrecha: un EstadoID fuera de la lista permitida se ignora por completo.
// $f_status ya viene saneado a int o '' por ordenFiltroEntero().
if ($f_status !== '' && in_array($f_status, $estadosVisibles, true)) {
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

// Mismos joins que el panel de admin: CB.PaisID lo necesita el botón "Pagar"
// (el modal filtra los bancos por país destino) y ambos alimentan los filtros
// origen/destino. LEFT JOIN 1:1 por PK -> no multiplican filas ni alteran el COUNT.
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

// Cuentas de salida para el modal de pago (window.cuentasDestino).
$sqlCuentas = "
    SELECT c.CuentaAdminID, c.Banco, c.Titular, c.SaldoActual, p.CodigoMoneda, c.PaisID
    FROM cuentas_bancarias_admin c
    JOIN paises p ON c.PaisID = p.PaisID
    WHERE c.Activo = 1 AND c.RolCuentaID IN (2, 3) AND (p.Rol = 'Destino' OR p.Rol = 'Ambos')
";
$cuentasDestino = $conexion->query($sqlCuentas)->fetch_all(MYSQLI_ASSOC);

// Contexto de render de la fila. El operador tiene las mismas acciones que el admin
// en esta tabla; aprobar/verificar comprobantes vive en pendientes.php y allí sigue
// restringido (ver get_pendientes.php).
$ordenRowCtx = [
    'puedePagar'          => true,
    'puedeEditarComision' => true,
    'secureFileBase'      => BASE_URL . '/admin/view_secure_file.php',
    'ordenUrl'            => BASE_URL . '/admin/orden.php',
    'facturaUrl'          => BASE_URL . '/generar-factura.php',
];

$ordenRowPartial = __DIR__ . '/../../remesas_private/src/templates/partials/orden_row.php';

// --- MODO AJAX (SOLO FILAS) ---
// Debe ir antes del header: el auto-refresh escribe la respuesta en el innerHTML del tbody.
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

$hayFiltrosExtra = ($f_emision_desde !== '' || $f_emision_hasta !== ''
    || $f_comprobante_desde !== '' || $f_comprobante_hasta !== ''
    || $f_completado_desde !== '' || $f_completado_hasta !== ''
    || $f_moneda_origen !== '' || $f_moneda_destino !== '');

$pageTitle = 'Historial de Operaciones';
$pageScripts = require __DIR__ . '/../../remesas_private/src/templates/admin_page_scripts.php';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div id="app-data" class="d-none" data-cuentas-destino='<?php echo htmlspecialchars(json_encode($cuentasDestino), ENT_QUOTES, "UTF-8"); ?>'></div>

<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="mb-0 me-3">Historial de Operaciones</h1>
        <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end mt-3 mt-md-0">
            <a href="pendientes.php" class="btn btn-primary">
                <i class="bi bi-list-task"></i> Ir a Pendientes
            </a>
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

            <div class="collapse <?php echo $hayFiltrosExtra ? 'show' : ''; ?> col-12" id="masFiltros">
                <div class="row g-2 align-items-end">
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

<?php require __DIR__ . '/../../remesas_private/src/templates/partials/orden_modals.php'; ?>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
