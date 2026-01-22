<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$sqlCuentas = "
    SELECT
        c.CuentaAdminID,
        c.Banco,
        c.Titular,
        c.SaldoActual,
        p.CodigoMoneda,
        c.PaisID
    FROM cuentas_bancarias_admin c
    JOIN paises p ON c.PaisID = p.PaisID
    WHERE c.Activo = 1
    AND c.RolCuentaID IN (2, 3) 
    AND (p.Rol = 'Destino' OR p.Rol = 'Ambos')
";
$cuentasDestino = $conexion->query($sqlCuentas)->fetch_all(MYSQLI_ASSOC);

$sql = "
    SELECT T.*,
        U.PrimerNombre, U.PrimerApellido, U.UserID as UsuarioID, U.NumeroDocumento AS UsuarioDocumento,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre,
        ET.EstadoID,
        CB.PaisID AS PaisDestinoID
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
    WHERE ET.EstadoID IN (2, 3, 6, 7)
    ORDER BY CASE WHEN ET.EstadoID = 7 THEN 0 ELSE 1 END, T.FechaTransaccion ASC
";
$transacciones = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    renderTableRows($transacciones);
    exit;
}

$pageTitle = 'Transacciones Pendientes';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Transacciones Pendientes</h1>
        <button class="btn btn-outline-secondary btn-sm" onclick="location.reload();">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Beneficiario</th>
                    <th>Comprobante</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="transactionsTableBody">
                <?php renderTableRows($transacciones); ?>
            </tbody>
        </table>
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
                            accept="image/*, application/pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comisión (0.3% Sugerido)</label>
                        <input type="number" step="0.01" class="form-control" id="adminComisionDestino"
                            name="comisionDestino" value="0">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Confirmar y Finalizar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pauseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pause-circle-fill"></i> Pausar Transacción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>La orden quedará pausada para el usuario. Escribe el motivo para que pueda corregirlo</p>
                <form id="pause-form">
                    <input type="hidden" id="pause-tx-id" name="txId">
                    <div class="mb-3">
                        <textarea class="form-control" name="motivo" rows="3" required
                            placeholder="Ej: Cuenta destino inactiva..."></textarea>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Pausar Orden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resumeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-play-circle-fill"></i> Reanudar Transacción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>La orden volverá al estado <strong>"En Proceso"</strong>.</p>
                <form id="resume-form">
                    <input type="hidden" id="resume-tx-id" name="txId">
                    <div class="mb-3">
                        <label class="form-label">Nota interna (Opcional):</label>
                        <input type="text" class="form-control" name="nota" placeholder="Ej: Cliente corrigió datos">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Reanudación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Rechazar Orden</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reject-tx-id">
                <div class="mb-3">
                    <label class="form-label">Motivo del rechazo:</label>
                    <textarea class="form-control" id="reject-reason" rows="3"
                        placeholder="Ej: Comprobante ilegible..."></textarea>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-warning confirm-reject-btn" data-type="retry">Solicitar Corrección</button>
                    <button class="btn btn-danger confirm-reject-btn" data-type="cancel">Cancelar
                        Definitivamente</button>
                </div>
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
            <div class="modal-header py-2 bg-dark text-white">
                <h5 class="modal-title fs-6"><i class="bi bi-eye"></i> Revisión de Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column flex-lg-row h-100 flex-grow-1 overflow-hidden">

                <div class="bg-light p-3 border-end overflow-auto" style="min-width: 300px; max-width: 350px;">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Datos del Titular (Origen)</h6>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Nombre Titular</label>
                        <div class="fs-6 text-dark" id="visor-nombre-titular">Cargando...</div>
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold">RUT / Documento</label>
                        <div class="fs-6 text-dark" id="visor-rut-titular">Cargando...</div>
                    </div>

                    <div class="alert alert-info small mt-4">
                        <i class="bi bi-info-circle-fill"></i>
                        Verifique que estos datos coincidan con la imagen del comprobante.
                    </div>
                </div>

                <div class="flex-grow-1 bg-dark d-flex align-items-center justify-content-center position-relative h-100" style="background-color: #333;">
                    <div id="comprobante-placeholder" class="spinner-border text-light"></div>
                    
                    <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center">
                        <img id="comprobante-img-full" class="d-none" style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Comprobante">
                        <iframe id="comprobante-pdf-full" class="w-100 h-100 d-none" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="confirmModalTitle">Confirmación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                ¿Estás seguro de realizar esta acción?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmModalCancelBtn">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmModalYesBtn">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="infoModalHeader">
                <h5 class="modal-title" id="infoModalTitle">Información</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="infoModalBody">
                Operación realizada.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="infoModalCloseBtn">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.cuentasDestino = <?php echo json_encode($cuentasDestino); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        
        // --- LOGICA MODAL MOTIVO PAUSA (MANUAL) ---
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-pause-reason-btn');
            if (btn) {
                e.preventDefault();
                
                const reason = btn.getAttribute('data-reason');
                const modalBodyText = document.getElementById('pause-reason-text');
                if (modalBodyText) modalBodyText.textContent = reason;

                const modalEl = document.getElementById('viewPauseReasonModal');
                if (modalEl) {
                    const modalInstance = new bootstrap.Modal(modalEl);
                    modalInstance.show();
                }
            }
        });

        // --- VISOR DE COMPROBANTES ---
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-comprobante-btn-admin');
            if (btn) {
                e.preventDefault();
                
                document.getElementById('visor-nombre-titular').textContent = btn.dataset.nombreTitular || 'No registrado';
                document.getElementById('visor-rut-titular').textContent = btn.dataset.rutTitular || 'No registrado';

                const url = btn.dataset.comprobanteUrl;
                const imgEl = document.getElementById('comprobante-img-full');
                const pdfEl = document.getElementById('comprobante-pdf-full');
                const placeholder = document.getElementById('comprobante-placeholder');
                
                // Reset
                imgEl.classList.add('d-none');
                pdfEl.classList.add('d-none');
                placeholder.classList.remove('d-none');
                imgEl.src = '';
                pdfEl.src = '';
                
                // Detectar extensión
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
                }, 500);
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';

function renderTableRows($transacciones)
{
    if (empty($transacciones)) {
        echo '<tr><td colspan="6" class="text-center py-5"><h4 class="text-muted">¡Todo al día! No hay órdenes pendientes.</h4></td></tr>';
        return;
    }

    foreach ($transacciones as $tx) {
        $rowClass = '';
        $estadoId = (int) $tx['EstadoID'];

        if ($estadoId === 7)
            $rowClass = 'table-danger border-danger';
        elseif ($estadoId === 6)
            $rowClass = 'table-warning';
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td><strong>#<?php echo $tx['TransaccionID']; ?></strong></td>
            <td>
                <?php if ($estadoId === 7): ?>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Riesgo</span>
                <?php elseif ($estadoId === 6): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-pause-circle"></i> Pausado</span>
                <?php elseif ($estadoId === 3): ?>
                    <span class="badge bg-primary">En Proceso</span>
                <?php elseif ($estadoId === 2): ?>
                    <span class="badge bg-info text-dark">Verificación</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?php echo $tx['EstadoNombre']; ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                <div class="small text-muted">ID: <?php echo $tx['UsuarioID']; ?></div>
            </td>
            <td><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?></td>
            <td>
                <?php if ($estadoId === 7): ?>
                    <span class="text-muted small"><i class="bi bi-lock"></i> Bloqueado</span>
                <?php elseif (!empty($tx['ComprobanteURL'])): ?>
                    <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin" data-bs-toggle="modal"
                        data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-nombre-titular="<?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>"
                        data-rut-titular="<?php echo htmlspecialchars($tx['UsuarioDocumento'] ?? 'No registrado'); ?>"
                        data-comprobante-url="view_secure_file.php?file=<?php echo urlencode($tx['ComprobanteURL']); ?>"
                        data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? 'view_secure_file.php?file=' . urlencode($tx['ComprobanteEnvioURL']) : ''; ?>">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td class="d-flex flex-wrap gap-1">
                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>" target="_blank"
                    class="btn btn-sm btn-outline-dark" title="Ver Orden"><i class="bi bi-file-earmark-pdf"></i></a>

                <?php if ($estadoId === 7): ?>
                    <button class="btn btn-sm btn-success authorize-risk-btn w-100"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"><i class="bi bi-shield-check"></i> Autorizar</button>
                    <button class="btn btn-sm btn-danger reject-btn w-100" 
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"><i class="bi bi-x-circle"></i> Rechazar</button>

                <?php elseif ($estadoId === 6): ?>
                    <?php if (!empty($tx['MotivoPausa'])): ?>
                        <button type="button" 
                            class="btn btn-sm btn-warning view-pause-reason-btn" 
                            data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>"
                            title="Ver Motivo de Pausa">
                            <i class="bi bi-info-circle-fill"></i>
                        </button>
                    <?php endif; ?>

                    <button class="btn btn-sm btn-outline-primary resume-btn-modal" data-bs-toggle="modal"
                        data-bs-target="#resumeModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"><i
                            class="bi bi-play-fill"></i> Reanudar</button>
                    <button class="btn btn-sm btn-danger reject-btn" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        title="Cancelar Orden"><i class="bi bi-x-circle"></i></button>

                <?php elseif ($estadoId === 2): ?>
                    <button class="btn btn-sm btn-success process-btn"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Confirmar</button>
                    <button class="btn btn-sm btn-danger reject-btn"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Rechazar</button>

                <?php elseif ($estadoId === 3): ?>
                    <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal"
                        data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-monto-destino="<?php echo $tx['MontoDestino']; ?>" data-pais-id="<?php echo $tx['PaisDestinoID']; ?>">
                        Pagar
                    </button>
                    <button class="btn btn-sm btn-warning pause-btn-modal" data-bs-toggle="modal" data-bs-target="#pauseModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"><i class="bi bi-pause-circle-fill"></i></button>
                    <button class="btn btn-sm btn-danger reject-btn" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        title="Cancelar Orden"><i class="bi bi-x-circle"></i></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php
    }
}
?>