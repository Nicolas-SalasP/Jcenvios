<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso denegado.");
}

$pageTitle = 'Gestión de Revendedores';
$pageScript = 'admin-revendedores.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 fw-bold">Revendedores</h1>
    </div>

    <!-- Tabla revendedores -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h5 class="fw-bold mb-0">Resumen por revendedor</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Revendedor</th>
                            <th>Email</th>
                            <th class="text-center">Comisión %</th>
                            <th class="text-end">Total ganado</th>
                            <th class="text-end text-warning fw-bold">Pendiente cobro</th>
                            <th class="text-center">Órdenes</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="resellers-body">
                        <tr><td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Cargando revendedores…</p>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Historial de liquidaciones -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h5 class="fw-bold mb-0">Historial de pagos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Revendedor</th>
                            <th>Período</th>
                            <th class="text-end">Monto</th>
                            <th class="text-center">Órdenes</th>
                            <th>Estado</th>
                            <th>Fecha pago</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="liq-body">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear liquidación -->
<div class="modal fade" id="crearLiqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Crear liquidación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="liq-user-id">
                <p class="text-muted mb-3">Revendedor: <strong id="liq-user-nombre"></strong></p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Desde</label>
                        <input type="date" id="liq-desde" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hasta</label>
                        <input type="date" id="liq-hasta" class="form-control">
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm mb-3" id="btnPreviewLiq">
                    <i class="bi bi-calculator me-1"></i> Calcular monto
                </button>
                <div id="liq-preview" class="alert alert-info d-none mb-3"></div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas (opcional)</label>
                    <textarea id="liq-notas" class="form-control" rows="2" placeholder="Ej: Pago mensual mayo 2026"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmLiq" disabled>
                    <i class="bi bi-check-lg me-1"></i> Crear liquidación
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Marcar como pagada -->
<div class="modal fade" id="pagarLiqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Registrar pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-pagar-liq" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="liquidacionId" id="pagar-liq-id">
                    <p class="text-muted mb-1">Monto: <strong id="pagar-liq-monto"></strong></p>
                    <p class="text-muted mb-3">Revendedor: <strong id="pagar-liq-nombre"></strong></p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Comprobante de pago (opcional)</label>
                        <input type="file" class="form-control" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Confirmar pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar comisión global -->
<div class="modal fade" id="editComisionModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Editar comisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-comision-user-id">
                <p class="text-muted mb-2">Revendedor: <strong id="edit-comision-nombre"></strong></p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Porcentaje de comisión global</label>
                    <div class="input-group">
                        <input type="number" id="edit-comision-pct" class="form-control" min="0" max="100" step="0.01" placeholder="Ej: 2.50">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveComision">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Configurar comisión por país -->
<div class="modal fade" id="configPaisesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Comisión por país</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="paises-user-id">
                <p class="text-muted mb-2">Revendedor: <strong id="paises-user-nombre"></strong></p>
                <p class="text-muted small mb-3">Si no hay configuración por país, se usa el % global. Si un país está desactivado, la comisión es 0 para ese destino.</p>
                <div id="paises-config-list">
                    <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSavePaises">
                    <i class="bi bi-save me-1"></i> Guardar configuración
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
