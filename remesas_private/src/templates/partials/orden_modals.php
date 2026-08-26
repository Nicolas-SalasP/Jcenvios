<?php
/**
 * Modales que necesita la tabla de órdenes (orden_row.php).
 * Compartidos por Admin y Operador.
 *
 * IMPORTANTE: incluir SIEMPRE antes de footer.php. footer.php define su propio
 * #viewComprobanteModal y getElementById() se queda con el primero del DOM, así
 * que este debe ir antes para ganar. Ver el aviso de ids duplicados en CLAUDE.md.
 *
 * Espera $ordenRowCtx en scope; solo usa la clave 'puedePagar' para decidir si
 * renderiza #adminUploadModal.
 */

// Le avisa a footer.php que el visor de comprobantes ya está en la página,
// para que no emita el suyo y no se dupliquen los ids.
$GLOBALS['visorComprobanteYaRenderizado'] = true;

$modalesPuedePagar = !empty(($ordenRowCtx ?? [])['puedePagar']);
?>

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

<div class="modal fade" id="clienteInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>Datos del Cliente</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Orden ID</dt><dd class="col-7" id="cliente-info-tx-id"></dd>
                    <dt class="col-5">Nombre</dt><dd class="col-7" id="cliente-info-nombre"></dd>
                    <dt class="col-5">Teléfono</dt><dd class="col-7" id="cliente-info-telefono"></dd>
                    <dt class="col-5">Documento</dt><dd class="col-7" id="cliente-info-doc"></dd>
                </dl>
            </div>
            <div class="modal-footer justify-content-center py-2 bg-light border-0">
                <button type="button" class="btn btn-sm btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if ($modalesPuedePagar): ?>
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
                    <div id="replace-proof-warning" class="alert alert-warning d-none mb-2" role="alert"></div>
                    <button type="submit" class="btn btn-success w-100">Confirmar y Finalizar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                        <button class="btn btn-outline-success btn-sm js-copy-btn"
                            data-copy-target="copy-monto-value"><i class="bi bi-clipboard"></i></button>
                        <input type="hidden" id="copy-monto-value">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Banco / Billetera</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-banco" readonly>
                            <button class="btn btn-outline-secondary js-copy-btn" data-copy-target="copy-banco"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6" id="container-cuenta" style="display: none;">
                        <label class="small text-muted fw-bold">Cuenta Bancaria</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-cuenta" readonly>
                            <button class="btn btn-outline-secondary js-copy-btn" data-copy-target="copy-cuenta"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6" id="container-telefono" style="display: none;">
                        <label class="small text-muted fw-bold">Teléfono (Pago Móvil/Billetera)</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-telefono" readonly>
                            <button class="btn btn-outline-secondary js-copy-btn"
                                data-copy-target="copy-telefono"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Documento</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-doc" readonly>
                            <button class="btn btn-outline-secondary js-copy-btn" data-copy-target="copy-doc"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-bold">Beneficiario</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-nombre" readonly>
                            <button class="btn btn-outline-secondary js-copy-btn" data-copy-target="copy-nombre"><i
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
