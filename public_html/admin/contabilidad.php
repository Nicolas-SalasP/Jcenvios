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
        <button class="btn btn-outline-secondary btn-sm" onclick="cargarDatosGenerales()">
            <i class="bi bi-arrow-clockwise"></i> Actualizar
        </button>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-primary h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bank"></i> Bancos (Origen)</h5>
                    <div>
                        <button class="btn btn-sm btn-light text-primary fw-bold me-1" data-bs-toggle="modal"
                            data-bs-target="#modalRecargaOrigen" title="Ingresar dinero">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-light fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalRetiroOrigen" title="Retirar dinero">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="container-origen" class="row">
                        <div class="col-12 text-center p-3">
                            <div class="spinner-border text-primary"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-success h-100">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-globe-americas"></i> Bancos Destino</h5>
                    <div>
                        <button class="btn btn-sm btn-light text-success fw-bold me-1" data-bs-toggle="modal"
                            data-bs-target="#modalRecargaDestino" title="Ingresar saldo manual">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-light fw-bold" data-bs-toggle="modal"
                            data-bs-target="#modalRetiroDestino" title="Registrar gasto/retiro">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input type="text" id="filtro-destino" class="form-control form-control-sm shadow-sm"
                            placeholder="Buscar país o banco...">
                    </div>
                    <div id="container-destino" class="row" style="max-height: 500px; overflow-y: auto;">
                        <div class="col-12 text-center p-3">
                            <div class="spinner-border text-success"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm bg-light mb-4 border-0">
        <div class="card-body p-4">
            <h5 class="card-title text-center text-secondary fw-bold mb-4">
                <i class="bi bi-arrow-left-right"></i> Compra de Divisas / Transferencia
            </h5>
            <form id="form-transferencia">
                <div class="row align-items-start justify-content-center g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-flex justify-content-between mb-1">
                            Desde (Origen) <span id="tx-saldo-origen" class="text-muted fw-normal"
                                style="font-size: 0.75rem;">Saldo: -</span>
                        </label>
                        <select id="tx-origen-id" class="form-select form-select-sm shadow-sm" required></select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Monto Salida</label>
                        <input type="number" step="0.01" class="form-control form-control-sm shadow-sm"
                            id="tx-monto-salida" required>
                    </div>

                    <div class="col-md-1 text-center align-self-center mt-3">
                        <i class="bi bi-arrow-right fs-4 text-muted"></i>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-flex justify-content-between mb-1">
                            Hacia (Destino) <span id="tx-saldo-destino" class="text-muted fw-normal"
                                style="font-size: 0.75rem;">Saldo: -</span>
                        </label>
                        <select id="tx-destino-id" class="form-select form-select-sm shadow-sm" required></select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Monto Entrada</label>
                        <input type="number" step="0.01" class="form-control form-control-sm shadow-sm"
                            id="tx-monto-entrada" required>
                    </div>

                    <div class="col-md-1 align-self-center mt-3 text-end">
                        <button type="submit" class="btn btn-secondary btn-sm w-100 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Historial de Movimientos</h5>
        </div>
        <div class="card-body">
            <form id="form-resumen-gastos" class="row g-2 mb-4 bg-light p-3 rounded shadow-sm">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Tipo de Cuenta</label>
                    <select id="resumen-tipo" class="form-select form-select-sm">
                        <option value="pais">Ver Destino (Internacional)</option>
                        <option value="banco">Ver Banco (Origen)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold text-muted mb-1">Seleccionar Cuenta</label>
                    <select id="resumen-entidad-id" class="form-select form-select-sm" required>
                        <option>Seleccione...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Mes</label>
                    <input type="month" class="form-control form-control-sm" id="resumen-mes" required
                        value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Consultar</button>
                </div>
            </form>

            <div id="resumen-resultado" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 id="resumen-titulo" class="my-0 fw-bold text-dark"></h4>
                    <div class="text-end" id="resumen-total-container">
                        <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem;">Total
                            Salidas</small>
                        <h4 class="text-danger fw-bold m-0" id="resumen-total-gastado"></h4>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-sm border">
                        <thead class="table-light">
                            <tr class="text-muted small text-uppercase">
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Referencia</th>
                                <th>Descripción</th>
                                <th>Responsable</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="resumen-movimientos-tbody" class="small"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecargaOrigen" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Ingreso a Origen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-recarga-banco">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Seleccionar Banco</label>
                        <select id="recarga-origen-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monto a Ingresar (+)</label>
                        <input type="number" step="0.01" class="form-control" id="recarga-banco-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descripción / Nota</label>
                        <textarea class="form-control" id="recarga-banco-desc" rows="2" placeholder="..."
                            required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetiroOrigen" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Retiro de Origen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-retiro-banco">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Seleccionar Banco</label>
                        <select id="retiro-origen-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monto a Retirar (-)</label>
                        <input type="number" step="0.01" class="form-control" id="retiro-banco-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Motivo</label>
                        <textarea class="form-control" id="retiro-banco-desc" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Confirmar Retiro</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecargaDestino" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Ajuste Positivo Destino</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-recarga-pais">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cuenta Destino</label>
                        <select id="recarga-destino-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monto (+)</label>
                        <input type="number" step="0.01" class="form-control" id="recarga-pais-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descripción</label>
                        <textarea class="form-control" id="recarga-pais-desc" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetiroDestino" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Gasto / Retiro Destino</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-retiro-pais">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cuenta Destino</label>
                        <select id="retiro-destino-select" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monto (-)</label>
                        <input type="number" step="0.01" class="form-control" id="retiro-pais-monto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Motivo</label>
                        <textarea class="form-control" id="retiro-pais-desc" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Registrar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>