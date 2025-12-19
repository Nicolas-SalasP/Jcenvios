<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

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
        case 'Pausado':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

$pageTitle = 'Mi Historial';
$pageScript = 'historial.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$userID = (int) $_SESSION['user_id'];
$transacciones = [];

global $conexion;
if (!$conexion) {
    error_log("Error crítico: No se pudo establecer la conexión a la base de datos en historial.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Error interno al conectar con la base de datos. Intente más tarde.</div></div>";
    require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
    exit();
}

// SQL ACTUALIZADO: Se añadieron T.MotivoPausa y T.MensajeReanudacion
$sql = "SELECT
            T.TransaccionID, T.FechaTransaccion, T.MontoOrigen, T.MonedaOrigen,
            T.MontoDestino, T.MonedaDestino, T.ComprobanteURL, T.ComprobanteEnvioURL,
            T.BeneficiarioNombre AS BeneficiarioAlias,
            T.FormaPagoID, 
            P.NombrePais AS PaisDestino,
            ET.NombreEstado AS EstadoNombre,
            T.MotivoPausa, T.MensajeReanudacion
        FROM transacciones AS T
        JOIN cuentas_beneficiarias AS C ON T.CuentaBeneficiariaID = C.CuentaID
        JOIN paises AS P ON C.PaisID = P.PaisID
        LEFT JOIN estados_transaccion AS ET ON T.EstadoID = ET.EstadoID
        WHERE T.UserID = ?
        ORDER BY T.FechaTransaccion DESC";

$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $userID);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        if ($resultado) {
            $transacciones = $resultado->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}
?>

<div class="container mt-4">
    <div class="card p-4 p-md-5 shadow-sm">
        <h1 class="mb-4">Mi Historial de Transacciones</h1>

        <?php if (empty($transacciones)): ?>
            <div class="alert alert-info">Aún no has realizado ninguna transacción. <a
                    href="<?php echo BASE_URL; ?>/dashboard/" class="alert-link">Haz tu primer envío aquí</a>.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle caption-top">
                    <caption>Mostrando tus transacciones más recientes primero.</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Beneficiario</th>
                            <th scope="col">Monto Enviado</th>
                            <th scope="col">Monto Recibido</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php foreach ($transacciones as $tx): ?>
                            <tr>
                                <th scope="row"><?php echo htmlspecialchars($tx['TransaccionID']); ?></th>
                                <td><?php echo date("d/m/Y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                                <td><?php echo htmlspecialchars($tx['BeneficiarioAlias'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(number_format($tx['MontoOrigen'], 2, ',', '.')) . ' ' . htmlspecialchars($tx['MonedaOrigen']); ?>
                                </td>
                                <td><?php echo htmlspecialchars(number_format($tx['MontoDestino'], 2, ',', '.')) . ' ' . htmlspecialchars($tx['MonedaDestino']); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($tx['EstadoNombre'] ?? 'Desconocido'); ?>
                                    </span>

                                    <?php if (($tx['EstadoNombre'] ?? '') === 'Pausado'): ?>
                                        <div class="mt-1">
                                            <small class="text-danger d-block" style="font-size: 0.75rem; max-width: 150px;">
                                                <strong>Motivo:</strong>
                                                <?php echo htmlspecialchars($tx['MotivoPausa'] ?? 'Revisión requerida'); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex flex-wrap gap-1">
                                    <?php if (($tx['EstadoNombre'] ?? '') === 'Pendiente de Pago'): ?>
                                        <button class="btn btn-sm btn-outline-danger cancel-btn"
                                            data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Cancelar Orden">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </button>
                                    <?php endif; ?>

                                    <?php if (empty($tx['ComprobanteURL']) && ($tx['EstadoNombre'] ?? '') === 'Pendiente de Pago'): ?>
                                        <button class="btn btn-sm btn-warning upload-btn" data-bs-toggle="modal"
                                            data-bs-target="#uploadReceiptModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-forma-pago-id="<?php echo $tx['FormaPagoID']; ?>"
                                            title="Subir Comprobante de Pago">
                                            <i class="bi bi-upload"></i> Subir Pago
                                        </button>
                                    <?php elseif (!empty($tx['ComprobanteURL']) && !in_array(($tx['EstadoNombre'] ?? ''), ['Exitoso', 'Cancelado'])): ?>
                                        <button class="btn btn-sm btn-secondary upload-btn" data-bs-toggle="modal"
                                            data-bs-target="#uploadReceiptModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-forma-pago-id="<?php echo $tx['FormaPagoID']; ?>"
                                            title="Modificar Comprobante de Pago">
                                            <i class="bi bi-pencil-square"></i> Modificar Pago
                                        </button>
                                    <?php endif; ?>

                                    <?php if (($tx['EstadoNombre'] ?? '') === 'Pausado'): ?>
                                        <button class="btn btn-sm btn-primary resume-order-btn" data-bs-toggle="modal"
                                            data-bs-target="#resumeOrderModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            title="Notificar Corrección">
                                            <i class="bi bi-check-circle"></i> Notificar Corrección
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($tx['ComprobanteURL'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary view-comprobante-btn" data-bs-toggle="modal"
                                            data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-comprobante-url="<?php echo BASE_URL . '/' . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                            data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . '/' . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>"
                                            data-start-type="user" title="Ver Comprobante de Pago">
                                            <i class="bi bi-eye"></i> Ver Pago
                                        </button>
                                    <?php endif; ?>

                                    <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                        target="_blank" class="btn btn-sm btn-info" title="Descargar Orden en PDF">
                                        <i class="bi bi-file-earmark-pdf"></i> Ver Orden
                                    </a>

                                    <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                        <button class="btn btn-sm btn-success view-comprobante-btn" data-bs-toggle="modal"
                                            data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                            data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? BASE_URL . '/' . htmlspecialchars($tx['ComprobanteURL']) : ''; ?>"
                                            data-envio-url="<?php echo BASE_URL . '/' . htmlspecialchars($tx['ComprobanteEnvioURL']); ?>"
                                            data-start-type="admin" title="Ver Comprobante de Envío">
                                            <i class="bi bi-receipt"></i> Ver Envío
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="uploadReceiptModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Subir Comprobante de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Estás subiendo el comprobante para la transacción <strong id="modal-tx-id">[ID]</strong>.</p>

                <div id="camera-section" class="d-none mb-3 text-center bg-dark rounded p-2 position-relative">
                    <video id="camera-video" class="w-100 rounded" autoplay playsinline
                        style="max-height: 300px; object-fit: contain;"></video>
                    <canvas id="camera-canvas" class="d-none"></canvas>
                    <div class="mt-2">
                        <button type="button" id="btn-capture" class="btn btn-light rounded-circle p-3 shadow"
                            title="Tomar Foto">
                            <i class="bi bi-circle-fill fs-4 text-danger"></i>
                        </button>
                        <button type="button" id="btn-cancel-camera"
                            class="btn btn-outline-light btn-sm ms-2 position-absolute top-0 end-0 m-2">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <form id="upload-receipt-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="receiptFile" class="form-label">Selecciona el archivo</label>

                        <div class="d-grid gap-2 mb-3 d-none" id="camera-toggle-container">
                            <button type="button" id="btn-start-camera" class="btn btn-outline-primary">
                                <i class="bi bi-camera-fill me-2"></i> Tomar foto del comprobante
                            </button>
                        </div>

                        <input class="form-control" type="file" id="receiptFile" name="receiptFile"
                            accept="image/png, image/jpeg, application/pdf" capture="environment" required>
                        <div class="form-text">Formatos aceptados: JPG, PNG, PDF. Máx: 5MB.</div>
                    </div>
                    <input type="hidden" id="transactionIdField" name="transactionId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" form="upload-receipt-form">Subir Archivo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resumeOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Notificar Corrección de Datos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Si ya corregiste los datos solicitados por el operador, envía un mensaje para que la orden continúe.
                </p>
                <form id="resume-order-form">
                    <input type="hidden" id="resume-tx-id" name="txId">
                    <div class="mb-3">
                        <label for="resume-message" class="form-label">Mensaje para el operador</label>
                        <textarea class="form-control" id="resume-message" name="mensaje" rows="3" required
                            placeholder="Ej: Ya corregí el número de cuenta. Quedo atento."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary" form="resume-order-form">Enviar Notificación</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>