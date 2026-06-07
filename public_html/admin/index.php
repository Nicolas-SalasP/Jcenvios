<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}
if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false) {
    header('Location: ' . BASE_URL . '/dashboard/seguridad.php');
    exit();
}

$listaEstados = [];
$listaPaises = [];
if (!isset($_GET['ajax'])) {
    $estadosDb = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion ORDER BY NombreEstado ASC");
    $listaEstados = $estadosDb ? $estadosDb->fetch_all(MYSQLI_ASSOC) : [];
    $paisesDb = $conexion->query("SELECT PaisID, NombrePais FROM paises WHERE Activo = 1 ORDER BY NombrePais ASC");
    $listaPaises = $paisesDb ? $paisesDb->fetch_all(MYSQLI_ASSOC) : [];
}

$f_id = $_GET['f_id'] ?? '';
$f_user = $_GET['f_user'] ?? '';
$f_date = $_GET['f_date'] ?? '';
$f_status = $_GET['f_status'] ?? '';
$f_origen = $_GET['f_origen'] ?? '';
$f_confirm = $_GET['f_confirm'] ?? '';
$f_destino = $_GET['f_destino'] ?? '';

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
    SELECT T.*, U.PrimerNombre, U.PrimerApellido,
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


// Helpers
function getStatusBadgeClass($statusName)
{
    switch ($statusName) {
        case 'Exitoso':
        case 'Pagado':
            return 'bg-success';
        case 'En Proceso':
            return 'bg-primary';
        case 'En Verificación':
            return 'bg-info text-dark';
        case 'Cancelado':
        case 'Rechazado':
            return 'bg-danger';
        case 'Pendiente de Pago':
            return 'bg-warning text-dark';
        case 'Pausado':
            return 'bg-warning text-dark';
        case 'Riesgo':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// --- MODO AJAX (SOLO TABLA) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (empty($transacciones)) {
        echo '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron resultados.</td></tr>';
    } else {
        foreach ($transacciones as $tx) {
            $nombreTitular = !empty($tx['NombreTitularOrigen']) ? $tx['NombreTitularOrigen'] : ($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']);
            $rutTitular = !empty($tx['RutTitularOrigen']) ? $tx['RutTitularOrigen'] : ($tx['UsuarioDocumento'] ?? 'N/A');
            ?>
            <tr>
                <td><?php echo $tx['TransaccionID']; ?></td>
                <td><?php echo date("d/m/y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                <td class="search-user">
                    <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                </td>
                <td class="search-beneficiary">
                    <?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?>
                    <?php
                        $previos = (int)($tx['EnviosPreviosMismaCuenta'] ?? 0);
                        if ($previos > 0):
                            $color = $previos >= 5 ? 'bg-warning text-dark' : 'bg-info text-white';
                    ?>
                        <button type="button"
                                class="badge <?php echo $color; ?> border-0 view-prev-sends-btn ms-1"
                                data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                title="Ver envíos previos exitosos a esta misma cuenta"
                                style="cursor:pointer;font-size:0.7rem;">
                            <i class="bi bi-arrow-repeat"></i> Envío #<?php echo $previos + 1; ?>
                        </button>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre'] ?? ''); ?>">
                        <?php echo htmlspecialchars($tx['EstadoNombre'] ?? 'Desconocido'); ?>
                    </span>
                    <?php if (($tx['EstadoNombre'] ?? '') === 'Pausado' && !empty($tx['MotivoPausa'])): ?>
                        <div class="mt-1">
                            <button type="button" class="btn btn-sm py-0 px-2 rounded-pill view-pause-reason-btn"
                                data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>"
                                style="font-size: 0.7rem; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                                <i class="bi bi-eye-fill me-1"></i> Ver Motivo
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php
                        $conf = $tx['ConfirmacionRecepcion'] ?? 'pendiente';
                        if (($tx['EstadoNombre'] ?? '') === 'Exitoso' && $conf !== 'pendiente'):
                            $fechaConf = !empty($tx['FechaConfirmacionRecepcion'])
                                ? date('d/m/Y H:i', strtotime($tx['FechaConfirmacionRecepcion']))
                                : '';
                            if ($conf === 'recibido'):
                    ?>
                        <div class="mt-1">
                            <span class="badge bg-success" title="Cliente confirmó recepción el <?php echo $fechaConf; ?>">
                                <i class="bi bi-check2-all"></i> Cliente recibió
                            </span>
                        </div>
                    <?php elseif ($conf === 'no_recibido'): ?>
                        <div class="mt-1">
                            <span class="badge bg-danger" title="¡Atención! Cliente reportó no recibir el <?php echo $fechaConf; ?>">
                                <i class="bi bi-exclamation-triangle-fill"></i> Cliente NO recibió
                            </span>
                        </div>
                    <?php
                            endif;
                        endif;
                    ?>
                </td>
                <td>
                    <div class="d-flex align-items-center justify-content-between">
                        <span><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                        <?php if (in_array($tx['EstadoNombre'], ['Exitoso', 'Pagado', 'En Proceso'])): ?>
                            <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2 border-0"
                                data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                data-current-val="<?php echo $tx['ComisionDestino']; ?>" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center">
                    <?php
                        // M5: botón "Copiar datos generales" para admin (idéntico al de operador, con fecha).
                        $hasCuenta_a   = !empty(trim($tx['BeneficiarioNumeroCuenta'] ?? ''));
                        $hasTelefono_a = !empty(trim($tx['BeneficiarioTelefono'] ?? ''));
                        $fechaGen_a    = !empty($tx['FechaTransaccion'])
                            ? date('d/m/Y H:i', strtotime($tx['FechaTransaccion']))
                            : '';

                        $textoCopiado_a  = "ORDEN #{$tx['TransaccionID']}\n";
                        if ($fechaGen_a) $textoCopiado_a .= "Fecha: {$fechaGen_a}\n";
                        $textoCopiado_a .= "Banco: " . ($tx['BeneficiarioBanco'] ?? '') . "\n";
                        $textoCopiado_a .= "Beneficiario: " . ($tx['BeneficiarioNombre'] ?? '') . "\n";
                        if ($hasCuenta_a)   $textoCopiado_a .= "Cuenta: {$tx['BeneficiarioNumeroCuenta']}\n";
                        if ($hasTelefono_a) $textoCopiado_a .= "Teléfono: {$tx['BeneficiarioTelefono']}\n";
                        $textoCopiado_a .= "Doc: " . ($tx['BeneficiarioDocumento'] ?? '') . "\n";
                        $textoCopiado_a .= "Monto: " . number_format($tx['MontoDestino'] ?? 0, 2, ',', '.') . ' ' . ($tx['MonedaDestino'] ?? '');

                        $textoBase64_a = base64_encode($textoCopiado_a);

                        $jsonData_a = htmlspecialchars(json_encode([
                            'id'         => $tx['TransaccionID'],
                            'banco'      => $tx['BeneficiarioBanco'] ?? '',
                            'nombre'     => $tx['BeneficiarioNombre'] ?? '',
                            'doc'        => $tx['BeneficiarioDocumento'] ?? '',
                            'cuenta'     => $tx['BeneficiarioNumeroCuenta'] ?? '',
                            'telefono'   => $tx['BeneficiarioTelefono'] ?? '',
                            'hasCuenta'  => $hasCuenta_a,
                            'hasTelefono'=> $hasTelefono_a,
                            'monto'      => number_format($tx['MontoDestino'] ?? 0, 2, ',', '.') . ' ' . ($tx['MonedaDestino'] ?? '')
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="d-flex gap-1 justify-content-center align-items-center">
                        <!-- Primario: Abrir orden -->
                        <a href="orden.php?id=<?php echo $tx['TransaccionID']; ?>" class="btn btn-sm btn-dark" title="Abrir orden (pantalla dividida)">
                            <i class="bi bi-window-split"></i>
                        </a>

                        <!-- Primario contextual: Pagar (solo En Proceso) -->
                        <?php if ($tx['EstadoNombre'] === 'En Proceso'): ?>
                            <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal" data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>" data-monto-destino="<?php echo $tx['MontoDestino']; ?>" data-pais-id="<?php echo $tx['PaisDestinoID'] ?? ''; ?>" data-moneda-destino="<?php echo htmlspecialchars($tx['MonedaDestino'] ?? ''); ?>" title="Pagar">
                                <i class="bi bi-currency-dollar"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Menú con el resto -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Más acciones">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item" type="button" onclick="copiarDatosDirecto(this, '<?php echo $textoBase64_a; ?>')">
                                        <i class="bi bi-clipboard-check me-2"></i> Copiar datos
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item copy-data-btn" type="button" data-datos="<?php echo $jsonData_a; ?>">
                                        <i class="bi bi-eye me-2"></i> Copiar por partes
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-2"></i> Descargar PDF
                                    </a>
                                </li>
                                <?php if (!empty($tx['ComprobanteURL'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-nombre-titular="<?php echo htmlspecialchars($nombreTitular); ?>"
                                        data-rut-titular="<?php echo htmlspecialchars($rutTitular); ?>"
                                        data-comprobante-url="view_secure_file.php?file=<?php echo urlencode($tx['ComprobanteURL']); ?>"
                                        data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? 'view_secure_file.php?file=' . urlencode($tx['ComprobanteEnvioURL']) : ''; ?>"
                                        data-start-type="user">
                                        <i class="bi bi-eye me-2"></i> Ver comprobante cliente
                                    </button>
                                </li>
                                <?php endif; ?>
                                <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                <li>
                                    <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? 'view_secure_file.php?file=' . urlencode($tx['ComprobanteURL']) : ''; ?>"
                                        data-envio-url="view_secure_file.php?file=<?php echo urlencode($tx['ComprobanteEnvioURL']); ?>"
                                        data-start-type="admin">
                                        <i class="bi bi-receipt me-2"></i> Ver comprobante envío
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
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
$currentFilters = ['f_id' => $f_id, 'f_user' => $f_user, 'f_date' => $f_date, 'f_status' => $f_status, 'f_origen' => $f_origen, 'f_destino' => $f_destino, 'f_confirm' => $f_confirm];

$pageTitle = 'Panel de Administración';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

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
            <?php /* F3.1: filtro confirmación cliente */ ?>
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
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="transactionsTableBody">
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No se encontraron resultados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $tx):
                        $nombreTitular = !empty($tx['NombreTitularOrigen']) ? $tx['NombreTitularOrigen'] : ($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']);
                        $rutTitular = !empty($tx['RutTitularOrigen']) ? $tx['RutTitularOrigen'] : ($tx['UsuarioDocumento'] ?? 'N/A');
                        ?>
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
                                <?php if (($tx['EstadoNombre'] ?? '') === 'Pausado' && !empty($tx['MotivoPausa'])): ?>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-sm py-0 px-2 rounded-pill view-pause-reason-btn"
                                            data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>"
                                            style="font-size: 0.7rem; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                                            <i class="bi bi-eye-fill me-1"></i> Ver Motivo
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                                    <?php if (in_array($tx['EstadoNombre'], ['Exitoso', 'Pagado', 'En Proceso'])): ?>
                                        <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2 border-0"
                                            data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-current-val="<?php echo $tx['ComisionDestino']; ?>" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php
                                    $hasCuenta_a   = !empty(trim($tx['BeneficiarioNumeroCuenta'] ?? ''));
                                    $hasTelefono_a = !empty(trim($tx['BeneficiarioTelefono'] ?? ''));
                                    $fechaGen_a    = !empty($tx['FechaTransaccion'])
                                        ? date('d/m/Y H:i', strtotime($tx['FechaTransaccion']))
                                        : '';

                                    $textoCopiado_a  = "ORDEN #{$tx['TransaccionID']}\n";
                                    if ($fechaGen_a) $textoCopiado_a .= "Fecha: {$fechaGen_a}\n";
                                    $textoCopiado_a .= "Banco: " . ($tx['BeneficiarioBanco'] ?? '') . "\n";
                                    $textoCopiado_a .= "Beneficiario: " . ($tx['BeneficiarioNombre'] ?? '') . "\n";
                                    if ($hasCuenta_a)   $textoCopiado_a .= "Cuenta: {$tx['BeneficiarioNumeroCuenta']}\n";
                                    if ($hasTelefono_a) $textoCopiado_a .= "Teléfono: {$tx['BeneficiarioTelefono']}\n";
                                    $textoCopiado_a .= "Doc: " . ($tx['BeneficiarioDocumento'] ?? '') . "\n";
                                    $textoCopiado_a .= "Monto: " . number_format($tx['MontoDestino'] ?? 0, 2, ',', '.') . ' ' . ($tx['MonedaDestino'] ?? '');

                                    $textoBase64_a = base64_encode($textoCopiado_a);

                                    $jsonData_a = htmlspecialchars(json_encode([
                                        'id'         => $tx['TransaccionID'],
                                        'banco'      => $tx['BeneficiarioBanco'] ?? '',
                                        'nombre'     => $tx['BeneficiarioNombre'] ?? '',
                                        'doc'        => $tx['BeneficiarioDocumento'] ?? '',
                                        'cuenta'     => $tx['BeneficiarioNumeroCuenta'] ?? '',
                                        'telefono'   => $tx['BeneficiarioTelefono'] ?? '',
                                        'hasCuenta'  => $hasCuenta_a,
                                        'hasTelefono'=> $hasTelefono_a,
                                        'monto'      => number_format($tx['MontoDestino'] ?? 0, 2, ',', '.') . ' ' . ($tx['MonedaDestino'] ?? '')
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="d-flex gap-1 justify-content-center align-items-center">
                                    <!-- Primario: Abrir orden -->
                                    <a href="orden.php?id=<?php echo $tx['TransaccionID']; ?>" class="btn btn-sm btn-dark" title="Abrir orden (pantalla dividida)">
                                        <i class="bi bi-window-split"></i>
                                    </a>

                                    <!-- Primario contextual: Pagar (solo En Proceso) -->
                                    <?php if ($tx['EstadoNombre'] === 'En Proceso'): ?>
                                        <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal" data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>" data-monto-destino="<?php echo $tx['MontoDestino']; ?>" data-pais-id="<?php echo $tx['PaisDestinoID'] ?? ''; ?>" data-moneda-destino="<?php echo htmlspecialchars($tx['MonedaDestino'] ?? ''); ?>" title="Pagar">
                                            <i class="bi bi-currency-dollar"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Menú con el resto -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Más acciones">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <button class="dropdown-item" type="button" onclick="copiarDatosDirecto(this, '<?php echo $textoBase64_a; ?>')">
                                                    <i class="bi bi-clipboard-check me-2"></i> Copiar datos
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item copy-data-btn" type="button" data-datos="<?php echo $jsonData_a; ?>">
                                                    <i class="bi bi-eye me-2"></i> Copiar por partes
                                                </button>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>" target="_blank">
                                                    <i class="bi bi-file-earmark-pdf me-2"></i> Descargar PDF
                                                </a>
                                            </li>
                                            <?php if (!empty($tx['ComprobanteURL'])): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                                    data-nombre-titular="<?php echo htmlspecialchars($nombreTitular); ?>"
                                                    data-rut-titular="<?php echo htmlspecialchars($rutTitular); ?>"
                                                    data-comprobante-url="view_secure_file.php?file=<?php echo urlencode($tx['ComprobanteURL']); ?>"
                                                    data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? 'view_secure_file.php?file=' . urlencode($tx['ComprobanteEnvioURL']) : ''; ?>"
                                                    data-start-type="user">
                                                    <i class="bi bi-eye me-2"></i> Ver comprobante cliente
                                                </button>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                            <li>
                                                <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                                    data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? 'view_secure_file.php?file=' . urlencode($tx['ComprobanteURL']) : ''; ?>"
                                                    data-envio-url="view_secure_file.php?file=<?php echo urlencode($tx['ComprobanteEnvioURL']); ?>"
                                                    data-start-type="admin">
                                                    <i class="bi bi-receipt me-2"></i> Ver comprobante envío
                                                </button>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selOrigin = document.getElementById('exportOrigin');
        const selDest = document.getElementById('exportDest');

        const updateOptions = () => {
            const valOrigin = selOrigin.value;
            const valDest = selDest.value;
            Array.from(selDest.options).forEach(opt => {
                if (opt.value !== "" && opt.value === valOrigin) {
                    opt.disabled = true;
                    if (opt.selected) selDest.value = "";
                } else {
                    opt.disabled = false;
                }
            });
            Array.from(selOrigin.options).forEach(opt => {
                if (opt.value !== "" && opt.value === valDest) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
        };

        if (selOrigin && selDest) {
            selOrigin.addEventListener('change', updateOptions);
            selDest.addEventListener('change', updateOptions);
        }
    });
</script>

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
                        Al guardar, el saldo contable de la caja se ajustará automáticamente.
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
        <div class="modal-content border-0" style="height: 85vh;">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fs-6">Comprobante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column flex-lg-row h-100 flex-grow-1 overflow-hidden">
                <div class="bg-light p-3 border-end overflow-auto" style="min-width: 250px; max-width: 300px;">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Datos del Titular (Origen)</h6>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Nombre Titular</label>
                        <div class="fs-6 text-dark" id="visor-nombre-titular">Cargando...</div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">RUT / Documento</label>
                        <div class="fs-6 text-dark" id="visor-rut-titular">Cargando...</div>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary active" id="tab-btn-user">Pago
                            Cliente</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-btn-admin">Envío
                            Admin</button>
                    </div>
                </div>

                <div class="flex-grow-1 bg-dark position-relative d-flex align-items-center justify-content-center"
                    style="background-color: #333;">
                    <div id="comprobante-placeholder" class="spinner-border text-light"></div>

                    <div id="comprobante-content"
                        class="w-100 h-100 d-flex align-items-center justify-content-center p-3">
                        <img id="comprobante-img-full" src="" class="img-fluid d-none"
                            style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Comprobante">
                        <iframe id="comprobante-pdf-full" class="w-100 h-100 d-none border-0" style="background:white;"
                            loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center py-1 bg-light">
                <a href="#" id="download-comprobante-btn" class="btn btn-sm btn-dark" download target="_blank">
                    <i class="bi bi-download me-2"></i>Descargar
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewPauseReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow">
            <div class="modal-header bg-warning py-2">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-pause-circle-fill me-2"></i>Motivo de Pausa
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="bi bi-info-circle text-warning display-4 mb-3 d-block"></i>
                <p class="mb-0 fw-medium" id="pause-reason-text" style="font-size: 1.1rem;"></p>
            </div>
            <div class="modal-footer justify-content-center py-2 bg-light border-0">
                <button type="button" class="btn btn-sm btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Finalizar Orden #<span id="modal-admin-tx-id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="admin-upload-form" enctype="multipart/form-data">
                    <input type="hidden" id="adminTransactionIdField" name="transactionId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cuenta de Salida (Desde dónde pagas)</label>
                        <select class="form-select" name="cuentaSalidaID" id="cuentaSalidaSelect" required>
                            <option value="">-- Cargando Bancos... --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comprobante de Pago</label>
                        <input class="form-control" type="file" name="receiptFile" required
                            accept="image/*,application/pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comisión</label>
                        <input type="number" step="0.01" class="form-control" id="adminComisionDestino"
                            name="comisionDestino" value="0">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Confirmar y Finalizar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalTitle">Info</h5><button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="infoModalBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    id="infoModalCloseBtn">Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="copyDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Datos para Transferencia - Orden #<span id="copy-tx-id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4 shadow-sm">
                    <strong class="fs-5 text-muted">Monto a Pagar:</strong>
                    <div class="d-flex align-items-center">
                        <span class="fs-3 fw-bold text-success me-3" id="copy-monto-display"></span>
                        <button class="btn btn-outline-success btn-sm"
                            onclick="copyToClipboard('copy-monto-value', this)"><i class="bi bi-clipboard"></i></button>
                        <input type="hidden" id="copy-monto-value">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Banco / Billetera</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-banco" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-banco', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6" id="container-cuenta" style="display: none;">
                        <label class="small text-muted fw-bold">Cuenta Bancaria</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-cuenta" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-cuenta', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6" id="container-telefono" style="display: none;">
                        <label class="small text-muted fw-bold">Teléfono (Pago Móvil/Billetera)</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-telefono" readonly>
                            <button class="btn btn-outline-secondary"
                                onclick="copyToClipboard('copy-telefono', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Documento</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-doc" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-doc', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-bold">Beneficiario</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-nombre" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-nombre', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.cuentasDestino = <?php echo json_encode($cuentasDestino); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.view-pause-reason-btn');
            if (btn) {
                e.preventDefault();
                const reason = btn.getAttribute('data-reason');
                const modalBody = document.getElementById('pause-reason-text');
                if (modalBody) modalBody.textContent = reason;
                const modalEl = document.getElementById('viewPauseReasonModal');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }
        });

        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.view-comprobante-btn-admin');
            if (btn) {
                e.preventDefault();

                document.getElementById('visor-nombre-titular').textContent = btn.dataset.nombreTitular || 'No registrado';
                document.getElementById('visor-rut-titular').textContent = btn.dataset.rutTitular || 'No registrado';

                const urlUser = btn.dataset.comprobanteUrl;
                const urlAdmin = btn.dataset.envioUrl;
                const startType = btn.dataset.startType || 'user';
                const urlToLoad = (startType === 'admin' && urlAdmin) ? urlAdmin : urlUser;
                const imgEl = document.getElementById('comprobante-img-full');
                const pdfEl = document.getElementById('comprobante-pdf-full');
                const placeholder = document.getElementById('comprobante-placeholder');
                const downloadBtn = document.getElementById('download-comprobante-btn');

                imgEl.classList.add('d-none');
                pdfEl.classList.add('d-none');
                placeholder.classList.remove('d-none');

                let extension = '';
                if (urlToLoad.includes('?')) {
                    const urlParams = new URLSearchParams(urlToLoad.split('?')[1]);
                    const fileParam = urlParams.get('file');
                    if (fileParam) extension = fileParam.split('.').pop().toLowerCase();
                } else {
                    extension = urlToLoad.split('.').pop().toLowerCase();
                }

                setTimeout(() => {
                    placeholder.classList.add('d-none');
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                        imgEl.src = urlToLoad;
                        imgEl.classList.remove('d-none');
                    } else {
                        pdfEl.src = urlToLoad;
                        pdfEl.classList.remove('d-none');
                    }
                    if (downloadBtn) downloadBtn.href = urlToLoad;
                }, 500);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>