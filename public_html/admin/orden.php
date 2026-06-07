<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

// --- Control de acceso: Admin u Operador ---
if (
    !isset($_SESSION['user_rol_name']) ||
    ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')
) {
    die("Acceso denegado.");
}

$txId = (int) ($_GET['id'] ?? 0);

// --- Cargar cuentas destino (para window.cuentasDestino, igual que admin/pendientes.php) ---
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

// --- Manejo de ID inválido (sin fatal) ---
if ($txId <= 0) {
    $pageTitle = 'Orden no encontrada';
    $pageScript = 'admin.js';
    require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning shadow-sm">
            <h4 class="mb-2"><i class="bi bi-exclamation-triangle"></i> Orden no encontrada</h4>
            <p class="mb-3">No se proporcionó un identificador de orden válido.</p>
            <a href="pendientes.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver a pendientes
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
    exit;
}

// --- Consulta de detalle (copia del SELECT de getFullTransactionDetails) ---
$sqlDetalle = "SELECT
        T.TransaccionID, T.UserID, T.CuentaBeneficiariaID, T.TasaID_Al_Momento, T.TasaCapturada,
        T.MontoOrigen, T.MonedaOrigen, T.MontoDestino, T.ComisionDestino, T.MonedaDestino,
        T.FechaTransaccion, T.ComprobanteURL, T.ComprobanteEnvioURL,
        T.FechaSubidaComprobante,
        T.ConfirmacionRecepcion, T.FechaConfirmacionRecepcion,
        T.RutTitularOrigen,
        T.NombreTitularOrigen,
        U.PrimerNombre, U.PrimerApellido, U.Email, U.NumeroDocumento, U.Telefono, U.FotoPerfilURL,
        TD_U.NombreDocumento AS UsuarioTipoDocumentoNombre,
        R.NombreRol AS UsuarioRolNombre,
        EV.NombreEstado AS UsuarioVerificacionEstadoNombre,

        T.BeneficiarioNombre,
        T.BeneficiarioDocumento,
        T.BeneficiarioBanco,
        T.BeneficiarioNumeroCuenta,
        T.BeneficiarioCCI,
        T.BeneficiarioTelefono,

        TS.ValorTasa,
        TS.PaisOrigenID,

        ET.EstadoID, ET.NombreEstado AS Estado,
        FP.FormaPagoID, FP.Nombre AS FormaDePago,

        CB.PaisID AS PaisDestinoID,
        TD_B.NombreDocumento AS BeneficiarioTipoDocumentoNombre,
        TB.Nombre AS BeneficiarioTipoNombre,

        T.MotivoPausa, T.MensajeReanudacion,
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

    FROM transacciones AS T
    JOIN usuarios AS U ON T.UserID = U.UserID
    JOIN tasas AS TS ON T.TasaID_Al_Momento = TS.TasaID
    LEFT JOIN estados_transaccion AS ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN formas_pago AS FP ON T.FormaPagoID = FP.FormaPagoID
    LEFT JOIN tipos_documento AS TD_U ON U.TipoDocumentoID = TD_U.TipoDocumentoID
    LEFT JOIN roles AS R ON U.RolID = R.RolID
    LEFT JOIN estados_verificacion AS EV ON U.VerificacionEstadoID = EV.EstadoID
    LEFT JOIN cuentas_beneficiarias AS CB ON T.CuentaBeneficiariaID = CB.CuentaID
    LEFT JOIN tipos_documento AS TD_B ON CB.TitularTipoDocumentoID = TD_B.TipoDocumentoID
    LEFT JOIN tipos_beneficiario AS TB ON CB.TipoBeneficiarioID = TB.TipoBeneficiarioID
    WHERE T.TransaccionID = ?";

$stmt = $conexion->prepare($sqlDetalle);
$stmt->bind_param("i", $txId);
$stmt->execute();
$orden = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Orden inexistente (sin fatal) ---
if (!$orden) {
    $pageTitle = 'Orden no encontrada';
    $pageScript = 'admin.js';
    require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning shadow-sm">
            <h4 class="mb-2"><i class="bi bi-exclamation-triangle"></i> Orden no encontrada</h4>
            <p class="mb-3">No existe ninguna orden con el identificador #<?php echo htmlspecialchars((string) $txId); ?>.</p>
            <a href="pendientes.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver a pendientes
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
    exit;
}

// --- Normalización de datos ---
$estadoId = (int) ($orden['EstadoID'] ?? 0);
$estadoNombre = $orden['Estado'] ?? 'Desconocido';

$badgeMap = [
    1 => 'bg-warning text-dark',
    2 => 'bg-info text-dark',
    3 => 'bg-primary',
    4 => 'bg-success',
    5 => 'bg-secondary',
    6 => 'bg-warning text-dark',
    7 => 'bg-danger',
];
$badgeClass = $badgeMap[$estadoId] ?? 'bg-secondary';

$montoDestino = $orden['MontoDestino'] ?? 0;
$monedaDestino = $orden['MonedaDestino'] ?? '';
$paisDestinoId = $orden['PaisDestinoID'] ?? '';

$nombreTitularOrigen = !empty($orden['NombreTitularOrigen'])
    ? $orden['NombreTitularOrigen']
    : trim(($orden['PrimerNombre'] ?? '') . ' ' . ($orden['PrimerApellido'] ?? ''));

$rutTitularOrigen = !empty($orden['RutTitularOrigen'])
    ? $orden['RutTitularOrigen']
    : ($orden['NumeroDocumento'] ?? 'No registrado');

$tasaMostrar = !empty($orden['TasaCapturada']) ? $orden['TasaCapturada'] : ($orden['ValorTasa'] ?? 0);

$comprobanteURL = trim($orden['ComprobanteURL'] ?? '');
$comprobanteEnvioURL = trim($orden['ComprobanteEnvioURL'] ?? '');
$enviosPrevios = (int) ($orden['EnviosPreviosMismaCuenta'] ?? 0);

// Detectar extensión del comprobante del cliente
$extComprobante = '';
if ($comprobanteURL !== '') {
    $extComprobante = strtolower(pathinfo($comprobanteURL, PATHINFO_EXTENSION));
}
$esImagen = in_array($extComprobante, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

$pageTitle = 'Orden #' . $txId;
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container-fluid mt-4 mb-5 px-3 px-lg-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="h3 mb-0">Orden #<?php echo htmlspecialchars((string) $txId); ?></h1>
            <span class="badge <?php echo $badgeClass; ?> fs-6"><?php echo htmlspecialchars($estadoNombre); ?></span>
            <?php if ($enviosPrevios > 0): ?>
                <span class="badge bg-info text-white"><i class="bi bi-arrow-repeat"></i> Envío #<?php echo $enviosPrevios + 1; ?></span>
            <?php endif; ?>
        </div>
        <a href="pendientes.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row g-3">

        <!-- IZQUIERDA: Comprobante del cliente -->
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-receipt"></i> Comprobante del Cliente</span>
                    <?php if ($comprobanteEnvioURL !== ''): ?>
                        <a href="view_secure_file.php?file=<?php echo urlencode($comprobanteEnvioURL); ?>" target="_blank"
                            class="btn btn-sm btn-outline-light">
                            <i class="bi bi-box-arrow-up-right"></i> Comprobante de envío (admin)
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-2 bg-light">
                    <?php if ($comprobanteURL === ''): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-info-circle-fill"></i> El cliente aún no subió comprobante.
                        </div>
                    <?php elseif ($extComprobante === 'pdf'): ?>
                        <iframe src="view_secure_file.php?file=<?php echo urlencode($comprobanteURL); ?>"
                            class="w-100 border rounded" style="height:75vh;" frameborder="0"></iframe>
                    <?php elseif ($esImagen): ?>
                        <div class="text-center">
                            <img src="view_secure_file.php?file=<?php echo urlencode($comprobanteURL); ?>"
                                class="img-fluid rounded border" alt="Comprobante del cliente"
                                onerror="this.style.display='none'; document.getElementById('comp-error').classList.remove('d-none');">
                            <div id="comp-error" class="alert alert-danger mt-2 d-none">No se pudo cargar el comprobante.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0 text-center">
                            <p class="mb-2">Tipo de archivo no previsualizable.</p>
                            <a href="view_secure_file.php?file=<?php echo urlencode($comprobanteURL); ?>" target="_blank"
                                class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right"></i> Abrir comprobante
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DERECHA: Datos de la orden + acciones -->
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-card-list"></i> Datos de la Orden
                </div>
                <div class="card-body">

                    <!-- Resumen -->
                    <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">Resumen</h6>
                    <dl class="row mb-3 small">
                        <dt class="col-5">Estado</dt>
                        <dd class="col-7"><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($estadoNombre); ?></span></dd>
                        <dt class="col-5">Fecha</dt>
                        <dd class="col-7"><?php echo !empty($orden['FechaTransaccion']) ? date('d/m/Y H:i', strtotime($orden['FechaTransaccion'])) : '-'; ?></dd>
                        <dt class="col-5">Forma de pago</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($orden['FormaDePago'] ?? '-'); ?></dd>
                    </dl>

                    <!-- Montos -->
                    <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">Montos</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="small text-muted">Envía</div>
                            <div class="fw-bold"><?php echo number_format((float) ($orden['MontoOrigen'] ?? 0), 2); ?> <?php echo htmlspecialchars($orden['MonedaOrigen'] ?? ''); ?></div>
                        </div>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <div class="text-end">
                            <div class="small text-muted">Recibe</div>
                            <div class="fw-bold text-success"><?php echo number_format((float) $montoDestino, 2); ?> <?php echo htmlspecialchars($monedaDestino); ?></div>
                        </div>
                    </div>
                    <dl class="row mb-3 small">
                        <dt class="col-5">Tasa</dt>
                        <dd class="col-7"><?php echo number_format((float) $tasaMostrar, 2); ?></dd>
                        <dt class="col-5">Comisión</dt>
                        <dd class="col-7"><?php echo number_format((float) ($orden['ComisionDestino'] ?? 0), 2); ?></dd>
                    </dl>

                    <!-- Titular que pagó (ORIGEN) -->
                    <div class="alert alert-warning py-2 px-3 mb-3">
                        <h6 class="text-uppercase small fw-bold mb-2"><i class="bi bi-person-badge"></i> Titular que pagó (Origen)</h6>
                        <div class="small mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($nombreTitularOrigen); ?></div>
                        <div class="small mb-2"><strong>RUT / Doc:</strong> <?php echo htmlspecialchars($rutTitularOrigen); ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            Verifica que coincida con el comprobante y que el monto transferido sea correcto.
                        </div>
                    </div>

                    <!-- Beneficiario (DESTINO) -->
                    <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">Beneficiario (Destino)</h6>
                    <dl class="row mb-3 small">
                        <?php if (!empty($orden['BeneficiarioNombre'])): ?>
                            <dt class="col-5">Nombre</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioNombre']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($orden['BeneficiarioDocumento'])): ?>
                            <dt class="col-5">Documento</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioDocumento']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($orden['BeneficiarioBanco'])): ?>
                            <dt class="col-5">Banco</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioBanco']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($orden['BeneficiarioNumeroCuenta'])): ?>
                            <dt class="col-5">Cuenta</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioNumeroCuenta']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($orden['BeneficiarioCCI'])): ?>
                            <dt class="col-5">CCI</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioCCI']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($orden['BeneficiarioTelefono'])): ?>
                            <dt class="col-5">Teléfono</dt>
                            <dd class="col-7"><?php echo htmlspecialchars($orden['BeneficiarioTelefono']); ?></dd>
                        <?php endif; ?>
                    </dl>

                    <!-- Cliente (cuenta) -->
                    <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">Cliente (Cuenta)</h6>
                    <dl class="row mb-3 small">
                        <dt class="col-5">Nombre</dt>
                        <dd class="col-7"><?php echo htmlspecialchars(trim(($orden['PrimerNombre'] ?? '') . ' ' . ($orden['PrimerApellido'] ?? ''))); ?></dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7 text-break"><?php echo htmlspecialchars($orden['Email'] ?? '-'); ?></dd>
                        <dt class="col-5">Teléfono</dt>
                        <dd class="col-7"><?php echo htmlspecialchars($orden['Telefono'] ?? '-'); ?></dd>
                    </dl>

                    <!-- Acciones según estado -->
                    <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">Acciones</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php if ($estadoId === 2): ?>
                            <button class="btn btn-sm btn-success process-btn"
                                data-tx-id="<?php echo $txId; ?>">Confirmar</button>
                            <button class="btn btn-sm btn-danger reject-btn"
                                data-tx-id="<?php echo $txId; ?>">Rechazar</button>

                        <?php elseif ($estadoId === 3): ?>
                            <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal"
                                data-bs-target="#adminUploadModal" data-tx-id="<?php echo $txId; ?>"
                                data-monto-destino="<?php echo htmlspecialchars((string) $montoDestino); ?>"
                                data-pais-id="<?php echo htmlspecialchars((string) $paisDestinoId); ?>"
                                data-moneda-destino="<?php echo htmlspecialchars($monedaDestino); ?>">Pagar</button>
                            <button class="btn btn-sm btn-warning pause-btn-modal" data-bs-toggle="modal"
                                data-bs-target="#pauseModal" data-tx-id="<?php echo $txId; ?>">
                                <i class="bi bi-pause-circle-fill"></i> Pausar</button>
                            <button class="btn btn-sm btn-danger reject-btn"
                                data-tx-id="<?php echo $txId; ?>">Rechazar</button>

                        <?php elseif ($estadoId === 6): ?>
                            <button class="btn btn-sm btn-outline-primary resume-btn-modal" data-bs-toggle="modal"
                                data-bs-target="#resumeModal" data-tx-id="<?php echo $txId; ?>">
                                <i class="bi bi-play-fill"></i> Reanudar</button>
                            <?php if (!empty($orden['MotivoPausa'])): ?>
                                <button type="button" class="btn btn-sm btn-warning view-pause-reason-btn"
                                    data-reason="<?php echo htmlspecialchars($orden['MotivoPausa']); ?>">
                                    <i class="bi bi-info-circle-fill"></i> Motivo</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger reject-btn"
                                data-tx-id="<?php echo $txId; ?>">Rechazar</button>

                        <?php elseif ($estadoId === 7): ?>
                            <button class="btn btn-sm btn-success authorize-risk-btn"
                                data-tx-id="<?php echo $txId; ?>">
                                <i class="bi bi-shield-check"></i> Autorizar</button>
                            <button class="btn btn-sm btn-danger reject-btn"
                                data-tx-id="<?php echo $txId; ?>">Rechazar</button>

                        <?php elseif ($estadoId === 4): ?>
                            <div class="alert alert-success mb-0 w-100 py-2">
                                <i class="bi bi-check-circle-fill"></i> Esta orden está finalizada.
                            </div>

                        <?php elseif ($estadoId === 5): ?>
                            <div class="alert alert-secondary mb-0 w-100 py-2">
                                <i class="bi bi-x-circle-fill"></i> Esta orden está cancelada.
                            </div>

                        <?php elseif ($estadoId === 1): ?>
                            <div class="alert alert-warning mb-0 w-100 py-2">
                                <i class="bi bi-hourglass-split"></i> El cliente aún no ha pagado esta orden.
                            </div>

                        <?php else: ?>
                            <div class="alert alert-light border mb-0 w-100 py-2">Sin acciones disponibles para este estado.</div>
                        <?php endif; ?>
                    </div>

                    <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $txId; ?>" target="_blank"
                        class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-file-earmark-pdf"></i> Ver PDF de la orden
                    </a>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============ MODALES (copiados verbatim de admin/pendientes.php) ============ -->

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
                        <label class="form-label fw-bold">Comprobante de Transferencia</label>
                        <input class="form-control" type="file" name="receiptFile" id="adminReceiptFileInput"
                            accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <div class="form-text">Sube la captura del pago realizado (JPG, PNG o PDF).</div>
                    </div>

                    <div id="upload-preview-container"
                        class="mt-3 d-none border rounded p-3 text-center position-relative bg-light shadow-sm">
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-2"
                            id="clear-upload-preview-btn" aria-label="Eliminar" title="Quitar archivo"></button>
                        <span class="badge bg-primary mb-2 shadow-sm"><i class="bi bi-eye"></i> Vista Previa del
                            Documento</span>
                        <img id="upload-preview-img" class="img-fluid d-none rounded border"
                            style="max-height: 250px; object-fit: contain; width: 100%;" alt="Vista previa de imagen">
                        <iframe id="upload-preview-pdf" class="w-100 d-none rounded border" style="height: 300px;"
                            frameborder="0"></iframe>
                        <div id="upload-preview-info" class="small text-muted mt-2 fw-medium"></div>
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

<script>
    window.cuentasDestino = <?php echo json_encode($cuentasDestino); ?>;
</script>

<script>
    // Handler de .view-pause-reason-btn (no está en admin.js, va inline en cada página)
    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.view-pause-reason-btn');
            if (btn) {
                e.preventDefault();
                const reason = btn.getAttribute('data-reason');
                const modalBodyText = document.getElementById('pause-reason-text');
                if (modalBodyText) modalBodyText.textContent = reason;

                const modalEl = document.getElementById('viewPauseReasonModal');
                if (modalEl) {
                    let modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(modalEl);
                    }
                    modalInstance.show();
                }
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
