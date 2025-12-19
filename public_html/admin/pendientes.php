<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Transacciones Pendientes';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

// Actualizamos la consulta para incluir el estado 'Pausado'
$transacciones = $conexion->query("
    SELECT T.*,
           U.PrimerNombre, U.PrimerApellido,
           T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
           ET.NombreEstado AS EstadoNombre
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    WHERE ET.NombreEstado IN ('En Verificación', 'En Proceso', 'Pausado')
    ORDER BY T.FechaTransaccion ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-4">Transacciones Pendientes</h1>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Beneficiario</th>
                    <th>Comprobante de Pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transacciones)): ?>
                    <tr>
                        <td colspan="5" class="text-center">¡Excelente! No hay transacciones que requieran tu atención.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacciones as $tx): ?>
                        <tr class="<?php echo ($tx['EstadoNombre'] == 'Pausado') ? 'table-warning' : ''; ?>">
                            <td><?php echo $tx['TransaccionID']; ?></td>
                            <td><?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?></td>
                            <td><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?></td>
                            <td>
                                <?php if (!empty($tx['ComprobanteURL'])): ?>
                                    <button class="btn btn-sm btn-info view-comprobante-btn-admin" data-bs-toggle="modal"
                                        data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                        data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>"
                                        data-start-type="user" title="Ver Comprobante de Pago">
                                        Ver Comprobante
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">No subido</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-flex flex-wrap gap-1">
                                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                    target="_blank" class="btn btn-sm btn-outline-info" title="Ver Orden PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>

                                <?php if ($tx['EstadoNombre'] == 'En Verificación'): ?>
                                    <button class="btn btn-sm btn-success process-btn"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Confirmar</button>
                                    <button class="btn btn-sm btn-danger reject-btn"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">Rechazar</button>

                                <?php elseif ($tx['EstadoNombre'] == 'En Proceso'): ?>
                                    <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal"
                                        data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-upload"></i> Envío
                                    </button>
                                    <button class="btn btn-sm btn-warning pause-btn" data-bs-toggle="modal"
                                        data-bs-target="#pauseModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-pause-fill"></i> Pausar
                                    </button>

                                <?php elseif ($tx['EstadoNombre'] == 'Pausado'): ?>
                                    <button class="btn btn-sm btn-outline-primary resume-btn"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>">
                                        <i class="bi bi-play-fill"></i> Reanudar
                                    </button>
                                    <?php if (!empty($tx['MensajeReanudacion'])): ?>
                                        <div class="mt-1 w-100">
                                            <small class="badge bg-info text-dark text-wrap d-block text-start">
                                                <strong>Cliente:</strong> <?php echo htmlspecialchars($tx['MensajeReanudacion']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="adminUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Comprobante de Envío</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Estás subiendo el comprobante para la transacción <strong id="modal-admin-tx-id"></strong>.</p>
                <form id="admin-upload-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="adminReceiptFile" class="form-label">Selecciona el archivo</label>
                        <input class="form-control" type="file" id="adminReceiptFile" name="receiptFile" required
                            accept="image/png, image/jpeg, application/pdf">
                    </div>
                    <div class="mb-3">
                        <label for="adminComisionDestino" class="form-label">Comisión Pagada</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="adminComisionDestino"
                            name="comisionDestino" value="0" required>
                    </div>
                    <input type="hidden" id="adminTransactionIdField" name="transactionId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="admin-upload-form">Confirmar Envío</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pauseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pause-circle-fill"></i> Pausar Transacción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>La orden quedará pausada para el usuario. Escribe el motivo para que pueda corregirlo.</p>
                <form id="pause-form">
                    <input type="hidden" id="pause-tx-id" name="txId">
                    <div class="mb-3">
                        <label for="motivo-pausa" class="form-label">Mensaje para el usuario</label>
                        <textarea class="form-control" id="motivo-pausa" name="motivo" rows="3" required
                            placeholder="Ej: La cuenta bancaria no coincide con el nombre del titular. Por favor, actualízala."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-warning" form="pause-form">Pausar Temporalmente</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>