<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (
    !isset($_SESSION['user_rol_name']) ||
    ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')
) {
    die("Acceso denegado.");
}

$pageTitle = 'Órdenes por Pagar';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$isOperator = ($_SESSION['user_rol_name'] === 'Operador');

$estadosSQL = $isOperator ? "3" : "2, 3";

$sql = "
    SELECT T.*, 
        U.PrimerNombre, U.PrimerApellido, U.Email,
        ET.NombreEstado AS EstadoNombre,
        T.BeneficiarioNombre, T.BeneficiarioDocumento, T.BeneficiarioBanco, 
        T.BeneficiarioNumeroCuenta, T.BeneficiarioTelefono,
        T.MontoDestino, T.MonedaDestino,
        T.ComprobanteURL, T.ComprobanteEnvioURL
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    WHERE T.EstadoID IN ($estadosSQL)
    ORDER BY T.FechaTransaccion ASC
";

$stmt = $conexion->prepare($sql);
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <?php echo $isOperator ? 'Órdenes por Pagar' : 'Gestión de Pendientes'; ?>
        </h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Monto a Pagar</th>
                            <th>Estado</th>
                            <th class="text-center">Datos Bancarios</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">¡Todo al día! No hay órdenes pendientes.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transacciones as $tx): ?>
                                <?php
                                $badgeClass = match ($tx['EstadoNombre']) {
                                    'En Verificación' => 'bg-info text-dark',
                                    'En Proceso' => 'bg-primary',
                                    default => 'bg-secondary'
                                };

                                // Datos para el modal de copiado
                                $esPagoMovil = ($tx['BeneficiarioNumeroCuenta'] === 'PAGO MOVIL');
                                $cuentaMostrar = $esPagoMovil ? $tx['BeneficiarioTelefono'] : $tx['BeneficiarioNumeroCuenta'];
                                $tipoCuenta = $esPagoMovil ? 'Pago Móvil' : 'Cuenta Bancaria';

                                $jsonData = htmlspecialchars(json_encode([
                                    'id' => $tx['TransaccionID'],
                                    'banco' => $tx['BeneficiarioBanco'],
                                    'nombre' => $tx['BeneficiarioNombre'],
                                    'doc' => $tx['BeneficiarioDocumento'],
                                    'cuenta' => $cuentaMostrar,
                                    'tipo' => $tipoCuenta,
                                    'monto' => number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']
                                ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $tx['TransaccionID']; ?></strong></td>
                                    <td><?php echo date("d/m H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?php echo number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']; ?>
                                    </td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $tx['EstadoNombre']; ?></span>
                                    </td>

                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary copy-data-btn"
                                            data-datos="<?php echo $jsonData; ?>" title="Copiar Datos">
                                            <i class="bi bi-clipboard-data"></i> Ver Datos
                                        </button>
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">

                                            <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                                target="_blank" class="btn btn-sm btn-danger text-white" title="Ver Orden PDF">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>

                                            <?php if (!empty($tx['ComprobanteURL'])): ?>
                                                <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin"
                                                    data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                                    data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                                    title="Ver Comprobante Cliente">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($tx['EstadoNombre'] === 'En Proceso'): ?>
                                                <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                    data-bs-target="#adminUploadModal"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Finalizar y Subir Pago">
                                                    <i class="bi bi-upload"></i> Finalizar
                                                </button>
                                            <?php endif; ?>

                                            <?php if (!$isOperator && $tx['EstadoNombre'] === 'En Verificación'): ?>
                                                <button class="btn btn-sm btn-success process-btn"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Aprobar">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                    data-bs-target="#rejectionModal"
                                                    data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Rechazar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="copyDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-wallet2"></i> Datos para Transferencia - Orden #<span
                        id="copy-tx-id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4 shadow-sm">
                    <strong class="fs-5 text-muted">Monto a Transferir:</strong>
                    <div class="d-flex align-items-center">
                        <span class="fs-3 fw-bold text-success me-3" id="copy-monto-display"></span>
                        <button class="btn btn-outline-success btn-sm"
                            onclick="copyToClipboard('copy-monto-value', this)"><i class="bi bi-clipboard"></i></button>
                        <input type="hidden" id="copy-monto-value">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Banco Destino</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-banco" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-banco', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold" id="label-cuenta-tipo">Número de Cuenta</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-cuenta" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-cuenta', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Documento ID</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-doc" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-doc', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="small text-muted fw-bold">Nombre Beneficiario</label>
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
                <button type="button" class="btn btn-primary" id="btn-ir-a-finalizar">
                    <i class="bi bi-upload"></i> Ya pagué, subir comprobante
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adminUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Finalizar Transacción #<span id="modal-admin-tx-id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="admin-upload-form">
                    <input type="hidden" id="adminTransactionIdField" name="transactionId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Comprobante de Transferencia</label>
                        <input class="form-control" type="file" name="receiptFile" accept="image/*,application/pdf"
                            required>
                        <div class="form-text">Sube la captura del pago realizado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Comisión</label>
                        <input type="number" step="0.01" class="form-control" name="comisionDestino"
                            placeholder="Si el banco cobró comisión extra">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Confirmar y Finalizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Rechazar Transacción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reject-tx-id">
                <div class="mb-3">
                    <label class="form-label">Motivo del rechazo</label>
                    <textarea class="form-control" id="reject-reason" rows="3"
                        placeholder="Ej: Comprobante ilegible..."></textarea>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-warning confirm-reject-btn" data-type="retry">Solicitar
                        Corrección</button>
                    <button type="button" class="btn btn-danger confirm-reject-btn" data-type="cancel">Cancelar
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
                <h5 class="modal-title fs-6" id="viewComprobanteModalLabel">Visor</h5>
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