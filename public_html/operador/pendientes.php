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
                        <label class="small text-muted fw-bold">Banco</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-banco" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-banco', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold" id="label-cuenta-tipo">Cuenta</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-cuenta" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-cuenta', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Documento</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-doc" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-doc', this)"><i
                                    class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-8">
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
                        <input class="form-control" type="file" name="receiptFile" accept="image/*,application/pdf"
                            required>
                        <div class="form-text">Sube la captura del pago realizado.</div>
                    </div>
                    <div class="mb-3">
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
                <h5 class="modal-title fs-6" id="viewComprobanteModalLabel">Visor de Comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark d-flex align-items-center justify-content-center position-relative flex-grow-1 h-100 overflow-hidden">
                
                <div id="comprobante-placeholder" class="spinner-border text-light" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>

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
    function copiarDatosDirecto(btn, base64Text) {
        try {
            const text = atob(base64Text);

            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = btn.innerHTML;
                const originalClass = btn.className;

                btn.innerHTML = '<i class="bi bi-check2-all"></i> ¡Copiado!';
                btn.className = 'btn btn-sm btn-success d-flex align-items-center gap-1';

                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.className = originalClass;
                }, 1500);
            }).catch(err => {
                console.error(err);
                alert('No se pudo acceder al portapapeles. Verifica permisos del navegador.');
            });
        } catch (e) {
            console.error(e);
            alert('Error al copiar datos.');
        }
    }

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

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.copy-data-btn');
        if (btn) {
            try {
                const data = JSON.parse(btn.dataset.datos);
                document.getElementById('copy-tx-id').textContent = data.id;
                document.getElementById('copy-monto-display').textContent = data.monto;
                document.getElementById('copy-monto-value').value = data.monto;
                document.getElementById('copy-banco').value = data.banco;
                document.getElementById('copy-nombre').value = data.nombre;
                document.getElementById('copy-doc').value = data.doc;
                document.getElementById('copy-cuenta').value = data.cuenta;
                document.getElementById('label-cuenta-tipo').textContent = data.tipo === 'Pago Móvil' ? 'Teléfono' : 'Cuenta';
                const modal = new bootstrap.Modal(document.getElementById('copyDataModal'));
                modal.show();
            } catch (err) { console.error("Error JSON:", err); }
        }
    });

    const style = document.createElement('style');
    style.innerHTML = `
        .spin-anim { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);

    document.addEventListener('DOMContentLoaded', () => {
        cargarTablaPendientes();
        setInterval(cargarTablaPendientes, 10000);

        // --- LÓGICA MODAL MOTIVO PAUSA (MANUAL) ---
        // Solución para evitar error 'backdrop'
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-pause-reason-btn');
            if (btn) {
                e.preventDefault();
                
                const reason = btn.getAttribute('data-reason');
                const modalBodyText = document.getElementById('pause-reason-text');
                if (modalBodyText) modalBodyText.textContent = reason;

                const modalEl = document.getElementById('viewPauseReasonModal');
                if (modalEl) {
                    // Verificar si ya existe instancia, sino crearla
                    let modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(modalEl);
                    }
                    modalInstance.show();
                }
            }
        });

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