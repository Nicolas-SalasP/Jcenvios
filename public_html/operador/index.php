<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Operador') {
    die("Acceso denegado.");
}

$pageTitle = 'Historial de Operaciones';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$registrosPorPagina = 50;
$paginaActual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($paginaActual < 1)
    $paginaActual = 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Incluir estado 6 (Pausado)
$estadosVisibles = "3, 4, 5, 6";
$visibles = array_map('intval', explode(',', $estadosVisibles));

// === Lectura y saneo de filtros (GET) ===
$f_id      = (isset($_GET['f_id']) && ctype_digit((string) $_GET['f_id'])) ? (int) $_GET['f_id'] : '';
$f_user    = isset($_GET['f_user']) ? trim($_GET['f_user']) : '';
$f_estado  = (isset($_GET['f_estado']) && ctype_digit((string) $_GET['f_estado'])) ? (int) $_GET['f_estado'] : '';
$f_origen  = (isset($_GET['f_origen']) && ctype_digit((string) $_GET['f_origen'])) ? (int) $_GET['f_origen'] : '';
$f_destino = (isset($_GET['f_destino']) && ctype_digit((string) $_GET['f_destino'])) ? (int) $_GET['f_destino'] : '';
$f_desde   = (isset($_GET['f_desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['f_desde'])) ? $_GET['f_desde'] : '';
$f_hasta   = (isset($_GET['f_hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['f_hasta'])) ? $_GET['f_hasta'] : '';

// === Construcción del WHERE dinámico ===
$conds  = [];
$params = [];
$types  = '';

if ($f_id !== '') {
    $conds[]  = "T.TransaccionID = ?";
    $params[] = $f_id;
    $types   .= "i";
}
if ($f_user !== '') {
    $like = '%' . $f_user . '%';
    $conds[]  = "(U.PrimerNombre LIKE ? OR U.PrimerApellido LIKE ? OR CONCAT_WS(' ', U.PrimerNombre, U.PrimerApellido) LIKE ? OR T.BeneficiarioNombre LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "ssss";
}
if ($f_estado !== '' && in_array($f_estado, $visibles, true)) {
    $conds[]  = "T.EstadoID = ?";
    $params[] = $f_estado;
    $types   .= "i";
}
if ($f_origen !== '') {
    $conds[]  = "TS.PaisOrigenID = ?";
    $params[] = $f_origen;
    $types   .= "i";
}
if ($f_destino !== '') {
    $conds[]  = "CB.PaisID = ?";
    $params[] = $f_destino;
    $types   .= "i";
}
if ($f_desde !== '') {
    $conds[]  = "DATE(T.FechaTransaccion) >= ?";
    $params[] = $f_desde;
    $types   .= "s";
}
if ($f_hasta !== '') {
    $conds[]  = "DATE(T.FechaTransaccion) <= ?";
    $params[] = $f_hasta;
    $types   .= "s";
}

$whereClause = "WHERE T.EstadoID IN ($estadosVisibles)" . (count($conds) ? " AND " . implode(" AND ", $conds) : "");

// === Listas para los selects de filtros ===
$estadosOperador = $conexion->query("SELECT EstadoID, NombreEstado FROM estados_transaccion WHERE EstadoID IN (3,4,5,6) ORDER BY NombreEstado")->fetch_all(MYSQLI_ASSOC);
$listaPaises = $conexion->query("SELECT PaisID, NombrePais FROM paises WHERE Activo = 1 ORDER BY NombrePais")->fetch_all(MYSQLI_ASSOC);

// === COUNT (mismo WHERE+params) ===
$sqlCount = "
    SELECT COUNT(*) as total
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
    LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
    $whereClause
";
$stmtCount = $conexion->prepare($sqlCount);
if (count($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// === SELECT de datos (mismo WHERE+params + LIMIT/OFFSET) ===
$sql = "
    SELECT T.*, U.PrimerNombre, U.PrimerApellido,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
    LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
    $whereClause
    ORDER BY T.FechaTransaccion DESC
    LIMIT ? OFFSET ?
";

$dataParams = $params;
$dataTypes  = $types;
$dataParams[] = $registrosPorPagina;
$dataParams[] = $offset;
$dataTypes   .= "ii";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// === Filtros para conservar en la paginación ===
$filtros = [
    'f_id'      => $f_id,
    'f_user'    => $f_user,
    'f_estado'  => $f_estado,
    'f_origen'  => $f_origen,
    'f_destino' => $f_destino,
    'f_desde'   => $f_desde,
    'f_hasta'   => $f_hasta,
];

function getStatusBadgeClass($statusName)
{
    return match ($statusName) {
        'Pagado' => 'bg-success',
        'En Proceso' => 'bg-primary',
        'Cancelado' => 'bg-danger',
        'Pausado' => 'bg-warning text-dark',
        default => 'bg-secondary'
    };
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Historial de Operaciones</h1>
        <a href="pendientes.php" class="btn btn-primary">
            <i class="bi bi-list-task"></i> Ir a Pendientes
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h6 class="text-muted mb-3"><i class="bi bi-funnel"></i> Filtrar historial</h6>
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">N° de orden</label>
                    <input type="number" name="f_id" class="form-control form-control-sm"
                        placeholder="Ej: 2024"
                        value="<?php echo htmlspecialchars((string) $f_id); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">Cliente o beneficiario</label>
                    <input type="text" name="f_user" class="form-control form-control-sm"
                        placeholder="Nombre…"
                        value="<?php echo htmlspecialchars($f_user); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Estado</label>
                    <select name="f_estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($estadosOperador as $est): ?>
                            <option value="<?php echo (int) $est['EstadoID']; ?>"
                                <?php echo ($f_estado !== '' && $f_estado == $est['EstadoID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($est['NombreEstado']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">País de origen</label>
                    <select name="f_origen" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($listaPaises as $pais): ?>
                            <option value="<?php echo (int) $pais['PaisID']; ?>"
                                <?php echo ($f_origen !== '' && $f_origen == $pais['PaisID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pais['NombrePais']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">País de destino</label>
                    <select name="f_destino" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($listaPaises as $pais): ?>
                            <option value="<?php echo (int) $pais['PaisID']; ?>"
                                <?php echo ($f_destino !== '' && $f_destino == $pais['PaisID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pais['NombrePais']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Fecha desde</label>
                    <input type="date" name="f_desde" class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($f_desde); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Fecha hasta</label>
                    <input type="date" name="f_hasta" class="form-control form-control-sm"
                        value="<?php echo htmlspecialchars($f_hasta); ?>">
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Beneficiario</th>
                            <th>Monto Destino</th>
                            <th>Estado</th>
                            <th>Comisión</th>
                            <th>Orden PDF</th>
                            <th>Comprobante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No hay historial disponible.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transacciones as $tx): ?>
                                <tr>
                                    <td>#<?php echo $tx['TransaccionID']; ?></td>
                                    <td><?php echo date("d/m H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                                    <td><?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?></td>
                                    <td><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?></td>
                                    <td><?php echo number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column align-items-start">
                                            <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre']); ?>">
                                                <?php echo $tx['EstadoNombre']; ?>
                                            </span>

                                            <?php if ($tx['EstadoNombre'] === 'Pausado' && !empty($tx['MotivoPausa'])): ?>
                                                <button type="button" 
                                                    class="btn btn-sm btn-outline-warning text-dark py-0 px-2 mt-1 rounded-pill" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewPauseReasonModal"
                                                    data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>"
                                                    style="font-size: 0.75rem; border: 1px solid #ffc107; background-color: #fff3cd;">
                                                    <i class="bi bi-eye-fill me-1"></i> Ver Motivo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                                            <?php if (in_array($tx['EstadoNombre'], ['Pagado', 'En Proceso'])): ?>
                                                <button class="btn btn-sm btn-outline-primary edit-commission-btn p-1"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                                    data-current-val="<?php echo $tx['ComisionDestino']; ?>"
                                                    title="Editar Comisión">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                            target="_blank" class="btn btn-sm btn-danger text-white" title="Ver Orden PDF">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                    </td>

                                    <td>
                                        <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                            <button class="btn btn-sm btn-success view-comprobante-btn-admin" data-bs-toggle="modal"
                                                data-bs-target="#viewComprobanteModal"
                                                data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                                data-comprobante-url="<?php echo BASE_URL . '/admin/view_secure_file.php?file=' . urlencode($tx['ComprobanteEnvioURL']); ?>"
                                                title="Ver Comprobante">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($filtros, ['pagina' => $paginaActual - 1])); ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($filtros, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($filtros, ['pagina' => $paginaActual + 1])); ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
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

<div class="modal fade" id="viewPauseReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow">
            <div class="modal-header bg-warning py-2">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-pause-circle-fill me-2"></i>Motivo de Pausa</h6>
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

<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content d-flex flex-column" style="height: 90vh;">
            <div class="modal-header py-2 bg-light">
                <h5 class="modal-title fs-6" id="viewComprobanteModalLabel">Visor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark d-flex align-items-center justify-content-center position-relative flex-grow-1 h-100 overflow-hidden">
                
                <div id="comprobante-placeholder" class="spinner-border text-light"></div>
                
                <img id="comprobante-img-full" class="d-none" style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Comprobante">
                
                <iframe id="comprobante-pdf-full" class="w-100 h-100 d-none" frameborder="0"></iframe>
                
                <a id="download-comprobante-btn" class="btn btn-light position-absolute top-0 end-0 m-3 d-none" download>
                    <i class="bi bi-download"></i> Descargar
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- LÓGICA MODAL MOTIVO PAUSA ---
    const pauseModal = document.getElementById('viewPauseReasonModal');
    if (pauseModal) {
        pauseModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const reason = button.getAttribute('data-reason');
            const modalBodyText = pauseModal.querySelector('#pause-reason-text');
            modalBodyText.textContent = reason;
        });
    }

    // --- LÓGICA MEJORADA VISOR COMPROBANTE ---
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-comprobante-btn-admin');
        if (btn) {
            e.preventDefault();
            
            const url = btn.dataset.comprobanteUrl;
            const imgEl = document.getElementById('comprobante-img-full');
            const pdfEl = document.getElementById('comprobante-pdf-full');
            const placeholder = document.getElementById('comprobante-placeholder');
            const downloadBtn = document.getElementById('download-comprobante-btn');

            imgEl.classList.add('d-none');
            pdfEl.classList.add('d-none');
            placeholder.classList.remove('d-none');
            imgEl.src = '';
            pdfEl.src = '';
            
            // Detectar extensión real
            let extension = '';
            if (url.includes('?')) {
                const urlParams = new URLSearchParams(url.split('?')[1]);
                const fileParam = urlParams.get('file');
                if (fileParam) {
                    extension = fileParam.split('.').pop().toLowerCase();
                }
            } else {
                extension = url.split('.').pop().toLowerCase();
            }

            setTimeout(() => {
                placeholder.classList.add('d-none');
                
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    imgEl.src = url;
                    imgEl.classList.remove('d-none');
                } else if (extension === 'pdf') {
                    pdfEl.src = url;
                    pdfEl.classList.remove('d-none');
                } else {
                    imgEl.src = url;
                    imgEl.classList.remove('d-none');
                }
                
                if(downloadBtn) {
                    downloadBtn.href = url;
                    downloadBtn.classList.remove('d-none');
                }
            }, 500);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>