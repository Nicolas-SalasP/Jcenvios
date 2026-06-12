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

$pageTitle = 'Mis Comisiones';
$pageScript = 'revendedor-historial.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0 fw-bold">Mis Comisiones</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Panel
        </a>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="ID o beneficiario…">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Desde</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Hasta</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>
                <div class="col-md-1">
                    <button id="btnClear" class="btn btn-outline-secondary w-100" title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary bar -->
    <div class="alert alert-success border-0 shadow-sm d-flex justify-content-between align-items-center mb-4" id="summary-bar" style="display:none">
        <span><i class="bi bi-info-circle me-2"></i> Total comisión en período: <strong id="period-total">—</strong></span>
        <span class="text-muted small" id="period-count"></span>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
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
                    <tbody id="hist-body">
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            <div class="spinner-border text-secondary" role="status"></div>
                            <p class="mt-2">Cargando…</p>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0" id="pagination-wrap"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
