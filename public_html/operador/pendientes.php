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
                        <button class="btn btn-outline-success btn-sm" onclick="copyToClipboard('copy-monto-value', this)"><i class="bi bi-clipboard"></i></button>
                        <input type="hidden" id="copy-monto-value">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold">Banco</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-banco" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-banco', this)"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-bold" id="label-cuenta-tipo">Cuenta</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" id="copy-cuenta" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-cuenta', this)"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">Documento</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-doc" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-doc', this)"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="small text-muted fw-bold">Beneficiario</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="copy-nombre" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('copy-nombre', this)"><i class="bi bi-clipboard"></i></button>
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
                <form id="admin-upload-form">
                    <input type="hidden" id="adminTransactionIdField" name="transactionId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Comprobante de Transferencia</label>
                        <input class="form-control" type="file" name="receiptFile" accept="image/*,application/pdf" required>
                        <div class="form-text">Sube la captura del pago realizado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Comisión (Opcional)</label>
                        <input type="number" step="0.01" class="form-control" name="comisionDestino" placeholder="0.00" value="0">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Confirmar Envío</button>
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
                    <textarea class="form-control" id="reject-reason" rows="3" placeholder="Ej: Comprobante ilegible..."></textarea>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-warning confirm-reject-btn" data-type="retry">Solicitar Corrección</button>
                    <button type="button" class="btn btn-danger confirm-reject-btn" data-type="cancel">Cancelar Definitivamente</button>
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
                <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center"></div>
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

    document.addEventListener('click', function(e) {
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

            } catch (err) {
                console.error("Error al parsear datos JSON del botón:", err);
            }
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

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>