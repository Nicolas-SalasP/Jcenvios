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

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-primary h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> Bancos (Origen)</h5>
                    <div>
                        <button class="btn btn-sm btn-light text-primary fw-bold me-1" data-bs-toggle="modal"
                            data-bs-target="#modalRecargaBanco" title="Ingresar dinero">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-light fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalRetiroBanco" title="Retirar dinero">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="bancos-loading" class="text-center p-2">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <div id="bancos-container" class="row"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-success h-100">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-wallet2"></i> Cajas Destino (Países)</h5>
                    <div>
                        <button class="btn btn-sm btn-light text-success fw-bold me-1" data-bs-toggle="modal"
                            data-bs-target="#modalRecargaPais" title="Ingresar saldo manual">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-light fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalRetiroPais" title="Registrar gasto/retiro">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="saldos-loading" class="text-center p-2">
                        <div class="spinner-border text-success"></div>
                    </div>
                    <div id="saldos-container" class="row"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm bg-light mb-4">
        <div class="card-body">
            <h5 class="card-title text-center text-secondary fw-bold mb-3"><i class="bi bi-arrow-left-right"></i> Compra
                de Divisas / Transferencia</h5>
            <form id="form-compra-divisas">
                <div class="row align-items-end justify-content-center">
                    <div class="col-md-3">
                        <label class="form-label small">Desde (Banco)</label>
                        <select id="compra-banco-origen" class="form-select form-select-sm" required>
                            <option>...</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Monto Salida</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="compra-monto-salida"
                            required>
                    </div>
                    <div class="col-md-1 text-center pb-1"><i class="bi bi-arrow-right fs-5 text-muted"></i></div>
                    <div class="col-md-3">
                        <label class="form-label small">Hacia (País)</label>
                        <select id="compra-pais-destino" class="form-select form-select-sm" required>
                            <option>...</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Monto Entrada</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="compra-monto-entrada"
                            required>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-secondary btn-sm w-100"><i
                                class="bi bi-check-lg"></i></button>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 id="resumen-titulo" class="my-0"></h4>
                    <div class="text-end" id="resumen-total-container">
                        <small class="text-muted d-block" id="resumen-texto-info">Gastos</small>
                        <h4 class="text-danger fw-bold m-0" id="resumen-total-gastado"></h4>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Referencia</th>
                                <th>Descripción</th>
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
                <h5 class="modal-title">Ingreso a Banco (Origen)</h5>
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
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="recarga-banco-desc" rows="2"
                            placeholder="Ej: Inyección de capital" required></textarea>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary">Confirmar Ingreso</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetiroBanco" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Retiro de Banco (Origen)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-retiro-banco">
                    <div class="mb-3">
                        <label class="form-label">Cuenta Bancaria</label>
                        <select id="retiro-banco-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto a Retirar</label>
                        <input type="number" step="0.01" class="form-control" id="retiro-banco-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo / Descripción</label>
                        <textarea class="form-control" id="retiro-banco-desc" rows="2"
                            placeholder="Ej: Pago proveedores" required></textarea>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-danger">Confirmar Retiro</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecargaPais" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Carga Manual a País (Destino)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-recarga-pais">
                    <div class="mb-3">
                        <label class="form-label">País</label>
                        <select id="recarga-pais-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto a Cargar</label>
                        <input type="number" step="0.01" class="form-control" id="recarga-pais-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="recarga-pais-desc" rows="2" placeholder="Ej: Ajuste de saldo"
                            required></textarea>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-success">Confirmar Carga</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetiroPais" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Gasto / Retiro de País (Destino)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <label class="form-label">Motivo / Descripción</label>
                        <textarea class="form-control" id="retiro-pais-desc" rows="2"
                            placeholder="Ej: Pago de servicios" required></textarea>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-warning">Confirmar Retiro</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>