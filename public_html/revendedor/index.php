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

$pageTitle = 'Panel Revendedor';
$pageScript = 'revendedor-dashboard.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0 fw-bold">Panel de Revendedor</h1>
            <p class="text-muted mb-0">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></p>
        </div>
        <a href="<?php echo BASE_URL; ?>/dashboard/index.php" class="btn btn-primary">
            <i class="bi bi-send-fill me-1"></i> Nuevo Envío
        </a>
    </div>

    <!-- Tarjetas resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-cash-coin fs-4 text-success"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Comisión pendiente</p>
                        <h4 class="mb-0 fw-bold text-success" id="stat-pendiente">—</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="bi bi-check-circle fs-4 text-primary"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Total recibido</p>
                        <h4 class="mb-0 fw-bold text-primary" id="stat-pagado">—</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="bi bi-percent fs-4 text-info"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Tu comisión</p>
                        <h4 class="mb-0 fw-bold text-info"><?php echo htmlspecialchars($_SESSION['user_porcentaje_comision'] ?? '0'); ?>%</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de liquidaciones -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h5 class="fw-bold mb-0">Pagos recibidos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Período</th>
                            <th class="text-end">Monto</th>
                            <th>Órdenes</th>
                            <th>Estado</th>
                            <th>Fecha pago</th>
                            <th>Comprobante</th>
                        </tr>
                    </thead>
                    <tbody id="liq-body">
                        <tr><td colspan="6" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-secondary me-2"></div> Cargando…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimas transacciones con comisión -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Mis últimas transacciones</h5>
            <a href="historial.php" class="btn btn-sm btn-outline-primary">Ver todo</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Beneficiario</th>
                            <th class="text-end">Enviado</th>
                            <th class="text-end">Recibido</th>
                            <th class="text-end text-success fw-bold">Comisión</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="recent-body">
                        <tr><td colspan="7" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-secondary me-2"></div> Cargando…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
