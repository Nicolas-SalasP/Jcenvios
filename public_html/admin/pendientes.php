<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Transacciones Pendientes';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$sql = "
    SELECT T.*,
           U.PrimerNombre, U.PrimerApellido, U.UserID as UsuarioID,
           T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
           ET.NombreEstado AS EstadoNombre,
           ET.EstadoID
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    WHERE ET.EstadoID IN (2, 3, 6, 7)
    ORDER BY CASE WHEN ET.EstadoID = 7 THEN 0 ELSE 1 END, T.FechaTransaccion ASC
";

$transacciones = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-4">Transacciones Pendientes</h1>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
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
            <tbody>
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <h4 class="text-muted">¡Todo al día! No hay órdenes pendientes.</h4>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $tx): ?>
                        <?php
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
                                        data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                        data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="d-flex flex-wrap gap-1">
                                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                    target="_blank" class="btn btn-sm btn-outline-dark" title="Ver Orden">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>

                                <?php if ($estadoId === 7): ?>
                                    <button class="btn btn-sm btn-success authorize-risk-btn w-100"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-shield-check"></i> Autorizar
                                    </button>

                                <?php elseif ($estadoId === 6): ?>
                                    <button class="btn btn-sm btn-outline-primary resume-btn-modal" data-bs-toggle="modal"
                                        data-bs-target="#resumeModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-play-fill"></i> Reanudar
                                    </button>

                                <?php elseif ($estadoId === 2): ?>
                                    <button class="btn btn-sm btn-success process-btn"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Confirmar</button>
                                    <button class="btn btn-sm btn-danger reject-btn"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Rechazar</button>

                                <?php elseif ($estadoId === 3): ?>
                                    <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal"
                                        data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-monto-destino="<?php echo $tx['MontoDestino']; ?>">
                                        Pagar
                                    </button>

                                    <button class="btn btn-sm btn-warning pause-btn-modal" data-bs-toggle="modal"
                                        data-bs-target="#pauseModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-pause-circle-fill"></i> Pausar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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

<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header py-2 bg-light">
                <h5 class="modal-title fs-6">Visor de Comprobantes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark d-flex align-items-center justify-content-center">
                <div id="comprobante-placeholder" class="spinner-border text-light"></div>
                <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center">
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>