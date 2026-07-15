<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Revendedor') {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit();
}

$pageTitle = 'Mis Cuentas y Referidos';
$pageScript = 'revendedor-cuentas.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 fw-bold">Mis Cuentas y Referidos</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Panel
        </a>
    </div>

    <!-- Código de referido -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-2"><i class="bi bi-link-45deg text-primary me-1"></i> Mi código de referido</h5>
            <p class="text-muted small mb-3">Comparte tu código o tu link para que los nuevos clientes queden vinculados a ti automáticamente.</p>
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light">Código</span>
                        <input type="text" id="referral-code" class="form-control fw-bold" readonly value="Cargando…">
                        <button class="btn btn-outline-secondary" id="btnCopyCode" title="Copiar código"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light">Link</span>
                        <input type="text" id="referral-link" class="form-control" readonly value="Cargando…">
                        <button class="btn btn-outline-secondary" id="btnCopyLink" title="Copiar link"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cuentas bancarias propias -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0">Mis cuentas bancarias</h5>
                <p class="text-muted small mb-0">Tus clientes referidos podrán depositar directamente en estas cuentas. Límite: <strong id="cuentas-max">—</strong>.</p>
            </div>
            <button class="btn btn-primary" id="btnAddCuenta" data-bs-toggle="modal" data-bs-target="#cuentaModal">
                <i class="bi bi-plus-lg me-1"></i> Agregar cuenta
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Banco</th>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Titular</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuentas-body">
                        <tr><td colspan="6" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-secondary me-2"></div> Cargando…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal agregar/editar cuenta -->
<div class="modal fade" id="cuentaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cuentaModalTitle">Agregar cuenta bancaria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cuenta-id" value="">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Banco</label>
                    <input type="text" class="form-control" id="cuenta-banco">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Tipo de cuenta</label>
                        <input type="text" class="form-control" id="cuenta-tipo" placeholder="Ahorro, Corriente…">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Número de cuenta</label>
                        <input type="text" class="form-control" id="cuenta-numero">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Titular</label>
                        <input type="text" class="form-control" id="cuenta-titular-nombre">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold">Documento del titular</label>
                        <input type="text" class="form-control" id="cuenta-titular-doc">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Instrucciones (opcional)</label>
                    <textarea class="form-control" id="cuenta-instrucciones" rows="2"></textarea>
                </div>
                <div id="cuenta-feedback" class="text-danger small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveCuenta">Guardar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
