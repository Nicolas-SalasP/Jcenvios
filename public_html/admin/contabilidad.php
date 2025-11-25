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

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Saldos Actuales (Cajas de Destino)</h5>
        </div>
        <div class="card-body">
            <div id="saldos-loading" class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
            <div id="saldos-container" class="row d-none">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">Gestión de Fondos (Recargas)</h5>
                </div>
                <div class="card-body">
                    <form id="form-agregar-fondos">
                        <div class="mb-3">
                            <label for="saldo-pais-id" class="form-label">País de Destino</label>
                            <select id="saldo-pais-id" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="saldo-monto" class="form-label">Monto a Agregar</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="saldo-monto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoMovimiento" id="tipo-recarga"
                                    value="recarga" checked>
                                <label class="form-check-label" for="tipo-recarga">Recarga (Agregar fondos)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipoMovimiento" id="tipo-inicial"
                                    value="inicial">
                                <label class="form-check-label" for="tipo-inicial">Ajuste / Saldo Inicial</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Registrar Movimiento</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0">Resumen de Gastos Mensuales</h5>
                </div>
                <div class="card-body">
                    <form id="form-resumen-gastos" class="row g-2">
                        <div class="col-md-5">
                            <label for="resumen-pais-id" class="form-label">País</label>
                            <select id="resumen-pais-id" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="resumen-mes" class="form-label">Mes</label>
                            <input type="month" class="form-control" id="resumen-mes" required
                                value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Consultar</button>
                        </div>
                    </form>
                    <hr>
                    <div id="resumen-resultado" class="mt-3 text-center" style="display:none;">
                        <p id="resumen-texto-info" class="text-muted mb-1"></p>
                        <h3 id="resumen-total-gastado" class="display-6 fw-bold text-danger"></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="historial-container" style="display:none;">
        <div class="card-header">
            <h5 class="mb-0">Historial de Movimientos del Mes</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Detalle</th>
                            <th>Responsable</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody id="resumen-movimientos-tbody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>