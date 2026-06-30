<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    http_response_code(403);
    die("Acceso denegado.");
}
if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false) {
    header('Location: ' . BASE_URL . '/dashboard/seguridad.php');
    exit();
}

$pageTitle = 'Dashboard de Estadísticas';
$pageScript = 'admin-dashboard.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Dashboard de Estadísticas</h1>
    </div>

    <div id="dashboard-loading" class="text-center p-5">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-3">Cargando estadísticas...</p>
    </div>

    <div id="dashboard-content" class="d-none">
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Usuarios Totales</h6>
                        <h2 class="card-title" id="kpi-total-users">...</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Trans. Pendientes</h6>
                        <h2 class="card-title" id="kpi-pending-txs">...</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Promedio Diario (Trans.)</h6>
                        <h2 class="card-title" id="kpi-avg-daily">...</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="card-subtitle mb-2 text-muted">Mes más Concurrido</h6>
                        <h2 class="card-title fs-5" id="kpi-busiest-month">...</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Top 5 - Países de Destino</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-top-destino"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Top 5 - Países de Origen</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-top-origen"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Top 5 - Usuarios por Transacciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Total Trans.</th>
                                    </tr>
                                </thead>
                                <tbody id="table-top-users">
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div> </div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>