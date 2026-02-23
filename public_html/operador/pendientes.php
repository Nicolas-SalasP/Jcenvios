<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (
    !isset($_SESSION['user_rol_name']) ||
    ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')
) {
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
    JOIN paises p 
        ON c.PaisID = p.PaisID
    WHERE c.Activo = 1
      AND (p.Rol = 'Destino' OR p.Rol = 'Ambos')
";
$cuentasDestino = $conexion->query($sqlCuentas)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Órdenes por Pagar';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$isOperator = ($_SESSION['user_rol_name'] === 'Operador');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <?php echo $isOperator ? 'Órdenes por Pagar' : 'Gestión de Pendientes'; ?>
        </h1>

        <button id="btnRefresh" class="btn btn-primary" onclick="cargarTablaPendientes()">
            <i class="bi bi-arrow-clockwise"></i> Actualizar Lista
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                    <tbody id="tablaPendientesBody">
                        <tr>
                            <td colspan="7" class="text-center p-5">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">Cargando órdenes...</p>
                            </td>
                        </tr>
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

<div class="modal fade" id="adminUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Finalizar Transacción #<span id="modal-admin-tx-id"></span></h5>
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
                        <input class="form-control" type="file" name="receiptFile" id="adminReceiptFileInput" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <div class="form-text">Sube la captura del pago realizado (JPG, PNG o PDF).</div>
                    </div>

                    <div id="upload-preview-container" class="mt-3 d-none border rounded p-3 text-center position-relative bg-light shadow-sm">
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-2" id="clear-upload-preview-btn" aria-label="Eliminar" title="Quitar archivo"></button>
                        <span class="badge bg-primary mb-2 shadow-sm"><i class="bi bi-eye"></i> Vista Previa del Documento</span>
                        <img id="upload-preview-img" class="img-fluid d-none rounded border" style="max-height: 250px; object-fit: contain; width: 100%;" alt="Vista previa de imagen">
                        <iframe id="upload-preview-pdf" class="w-100 d-none rounded border" style="height: 300px;" frameborder="0"></iframe>
                        <div id="upload-preview-info" class="small text-muted mt-2 fw-medium"></div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold">Comisión (0.3% Sugerido)</label>
                        <input type="number" step="0.01" class="form-control" id="opComisionDestino"
                            name="comisionDestino" placeholder="0.00" value="0">
                        <div class="form-text">Calculado automáticamente. Verifica el valor.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Confirmar Envío</button>
                    </div>
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
                <p>La orden quedará pausada para el usuario. Escribe el motivo para que pueda corregirlo.</p>
                <form id="pause-form">
                    <input type="hidden" id="pause-tx-id" name="txId">
                    <div class="mb-3">
                        <textarea class="form-control" name="motivo" rows="3" required
                            placeholder="Ej: Cuenta destino inactiva / Datos incorrectos..."></textarea>
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
                    <label class="form-label">Motivo</label>
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

<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" id="modal-content-visor">
            <div class="modal-header py-2 bg-dark text-white">
                <h5 class="modal-title fs-6"><i class="bi bi-eye"></i> Revisión de Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0 d-flex flex-column flex-lg-row">
                <div class="bg-light p-3 border-bottom border-lg-bottom-0 border-lg-end overflow-auto sidebar-datos">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Datos del Titular (Origen)</h6>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Nombre Titular</label>
                        <div class="fs-6 text-dark text-break" id="visor-nombre-titular">Cargando...</div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">RUT / Documento</label>
                        <div class="fs-6 text-dark" id="visor-rut-titular">Cargando...</div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle-fill"></i> Verifique que estos datos coincidan con la imagen del
                        comprobante.
                    </div>
                </div>

                <div class="flex-grow-1 bg-dark d-flex align-items-center justify-content-center position-relative visor-container">
                    <div id="comprobante-placeholder" class="spinner-border text-light"></div>
                    <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center p-2">
                        <img id="comprobante-img-full" class="d-none shadow rounded"
                            style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Comprobante">
                        <iframe id="comprobante-pdf-full" class="w-100 h-100 d-none rounded border-0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #modal-content-visor {
        height: auto;
        min-height: 80vh;
    }
    .sidebar-datos {
        width: 100%;
        max-height: 300px;
    }
    .visor-container {
        min-height: 50vh;
        background-color: #333;
    }
    @media (min-width: 992px) {
        #modal-content-visor {
            height: 90vh;
        }
        .modal-body {
            height: 100%;
            overflow: hidden;
        }
        .sidebar-datos {
            width: 320px;
            min-width: 320px;
            height: 100%;
            max-height: none;
        }
        .visor-container {
            height: 100%;
        }
    }
</style>

<script>
    function cargarTablaPendientes() {
        const btn = document.getElementById('btnRefresh');
        const icon = btn.querySelector('i');

        icon.classList.add('spin-anim');
        btn.disabled = true;

        fetch('get_pendientes.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('tablaPendientesBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Error recargando tabla:', error);
            })
            .finally(() => {
                icon.classList.remove('spin-anim');
                btn.disabled = false;
            });
    }

    const style = document.createElement('style');
    style.innerHTML = `
        .spin-anim { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);

    document.addEventListener('DOMContentLoaded', () => {
        cargarTablaPendientes();
        setInterval(cargarTablaPendientes, 10000);

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

    if (typeof window.copyToClipboard === 'undefined') {
        window.copyToClipboard = (elementId, btnElement) => {
            const input = document.getElementById(elementId);
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                const orig = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="bi bi-check"></i>';
                setTimeout(() => btnElement.innerHTML = orig, 1000);
            });
        };
    }
</script>

<script>
    window.cuentasDestino = <?php echo json_encode($cuentasDestino); ?>;
</script>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>