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

$sqlCount = "SELECT COUNT(*) as total FROM transacciones WHERE EstadoID IN ($estadosVisibles)";
$totalRegistros = $conexion->query($sqlCount)->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

$sql = "
    SELECT T.*, U.PrimerNombre, U.PrimerApellido,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    WHERE T.EstadoID IN ($estadosVisibles)
    ORDER BY T.FechaTransaccion DESC
    LIMIT ? OFFSET ?
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
                            <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>">Siguiente</a>
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
            
            // Reset
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

            // Mostrar
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