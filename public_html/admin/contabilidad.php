<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Contabilidad de Saldos';
$pageScript = 'admin-contabilidad.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Contabilidad y Saldos</h1>
    </div>

    <div class="card shadow-sm border-primary mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-bank"></i> Bancos (Recaudación)</h5>
            <button class="btn btn-sm btn-light text-primary fw-bold" data-bs-toggle="modal"
                data-bs-target="#modalRecargaBanco">
                <i class="bi bi-plus-circle"></i> Recargar Saldo
            </button>
        </div>
        <div class="card-body">
            <div id="bancos-loading" class="text-center p-2">
                <div class="spinner-border text-primary"></div>
            </div>
            <div id="bancos-container" class="row d-none"></div>
        </div>
    </div>

    <div class="card shadow-sm border-success mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-wallet2"></i> Cajas Destino (Dispersión)</h5>
            <button class="btn btn-sm btn-light text-success fw-bold" data-bs-toggle="modal"
                data-bs-target="#modalRetiroPais">
                <i class="bi bi-dash-circle"></i> Retiro / Gasto
            </button>
        </div>
        <div class="card-body">
            <div id="saldos-loading" class="text-center p-2">
                <div class="spinner-border text-success"></div>
            </div>
            <div id="saldos-container" class="row d-none"></div>
        </div>
    </div>

    <div class="card shadow-sm bg-light mb-4">
        <div class="card-body">
            <h5 class="card-title text-center text-warning fw-bold mb-3"><i class="bi bi-arrow-left-right"></i> Compra
                de Divisas / Transferencia</h5>
            <form id="form-compra-divisas">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Desde (Banco)</label>
                        <select id="compra-banco-origen" class="form-select" required>
                            <option>...</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Monto Salida</label>
                        <input type="number" step="0.01" class="form-control" id="compra-monto-salida" required>
                    </div>
                    <div class="col-md-1 text-center pb-2"><i class="bi bi-arrow-right fs-4"></i></div>
                    <div class="col-md-3">
                        <label class="form-label">Hacia (País)</label>
                        <select id="compra-pais-destino" class="form-select" required>
                            <option>...</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Monto Entrada</label>
                        <input type="number" step="0.01" class="form-control" id="compra-monto-entrada" required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-warning w-100"><i class="bi bi-check-lg"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Historial de Movimientos</h5>
        </div>
        <div class="card-body">
            <form id="form-resumen-gastos" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select id="resumen-tipo" class="form-select">
                        <option value="pais">Ver País (Caja Destino)</option>
                        <option value="banco">Ver Banco (Origen)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select id="resumen-entidad-id" class="form-select" required>
                        <option>Seleccione...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="month" class="form-control" id="resumen-mes" required
                        value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
            </form>

            <div id="resumen-resultado" style="display:none;">
                <h4 id="resumen-titulo" class="text-center my-3"></h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Detalle</th>
                                <th>Responsable</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="resumen-movimientos-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecargaBanco" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Recargar Banco (Ingreso Manual)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-recarga-banco">
                    <div class="mb-3">
                        <label class="form-label">Cuenta Bancaria</label>
                        <select id="recarga-banco-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto a Ingresar</label>
                        <input type="number" step="0.01" class="form-control" id="recarga-banco-monto" required>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary">Confirmar Ingreso</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetiroPais" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Retiro de Fondos (Caja Destino)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-retiro-pais">
                    <div class="mb-3">
                        <label class="form-label">País</label>
                        <select id="retiro-pais-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto a Retirar</label>
                        <input type="number" step="0.01" class="form-control" id="retiro-pais-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <input type="text" class="form-control" id="retiro-pais-motivo"
                            placeholder="Ej: Pago de servicios" required>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-danger">Confirmar Retiro</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>