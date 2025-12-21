<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

// Redirecciones de sesión
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Admin') {
        header('Location: ' . BASE_URL . '/admin/');
        exit();
    }
    if (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Operador') {
        header('Location: ' . BASE_URL . '/operador/pendientes.php');
        exit();
    }
    header('Location: ' . BASE_URL . '/dashboard/');
    exit();
}

$pageTitle = 'Inicio';
$pageScript = 'home.js';

require_once __DIR__ . '/../remesas_private/src/templates/header.php';
?>

<div class="container py-5">
    <section class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Envía dinero al extranjero <span class="text-primary">al mejor precio</span>
        </h1>
        <p class="lead text-muted col-lg-8 mx-auto">Calcula tu envío en tiempo real, sin comisiones ocultas y con la
            tasa más competitiva del mercado.</p>
    </section>

    <section class="row g-4 mb-5">
        <div class="col-lg-6">
            <div class="card shadow-lg border-0 h-100">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="mb-0"><i class="bi bi-calculator-fill me-2"></i>Cotiza tu Envío</h4>
                </div>
                <div class="card-body p-4">
                    <form id="public-calculator-form" novalidate>
                        <div id="bcv-alert-box"
                            class="alert alert-light border border-info d-flex align-items-center mb-3 d-none"
                            role="alert">
                            <i class="bi bi-info-circle-fill text-info me-2 fs-5"></i>
                            <div class="small">
                                <strong>Ref. BCV:</strong> <span id="bcv-rate-display-calc">Cargando...</span>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-muted">Origen</label>
                                <select id="calc-pais-origen" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-muted">Destino</label>
                                <select id="calc-pais-destino" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="calc-monto-origen" class="form-label fw-bold">Tú envías</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold label-moneda"
                                    id="label-moneda-origen">---</span>
                                <input type="text" inputmode="decimal" id="calc-monto-origen"
                                    class="form-control form-control-lg fw-bold" placeholder="100.000">
                            </div>
                            <div class="form-text text-end text-primary fw-bold" id="calc-tasa-info">Cargando tasa...
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6" id="col-monto-destino">
                                <label for="calc-monto-destino" class="form-label fw-bold text-success">Reciben</label>
                                <div class="input-group">
                                    <input type="text" inputmode="decimal" id="calc-monto-destino"
                                        class="form-control border-success text-success fw-bold">
                                    <span class="input-group-text bg-success text-white fw-bold label-moneda"
                                        id="label-moneda-destino">VES</span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6" id="col-monto-usd">
                                <label for="calc-monto-usd" class="form-label fw-bold text-primary">Ref. BCV
                                    (USD)</label>
                                <div class="input-group">
                                    <input type="text" inputmode="decimal" id="calc-monto-usd"
                                        class="form-control border-primary text-primary fw-bold">
                                    <span class="input-group-text bg-primary text-white label-moneda">USD</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <a href="<?php echo BASE_URL; ?>/login.php"
                                class="btn btn-warning btn-lg fw-bold shadow-sm">
                                ¡Quiero Enviar Ahora! <i class="bi bi-arrow-right-circle-fill ms-2"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100" id="rate-container">
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <div class="text-center mb-4">
                        <h5 class="text-muted text-uppercase ls-1">Tasa de Hoy</h5>
                        <h2 id="rate-valor-actual" class="display-5 fw-bold text-dark my-3">...</h2>
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"
                            id="rate-description">Tasa Promedio</span>
                    </div>
                    <div class="chart-container" style="position: relative; height:250px; width:100%">
                        <canvas id="rate-history-chart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small id="rate-ultima-actualizacion" class="text-muted"></small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row text-center g-4">
        <div class="col-md-4">
            <div class="p-4 rounded-3 bg-white shadow-sm h-100 border-bottom border-primary border-4">
                <i class="bi bi-shield-check display-4 text-primary mb-3"></i>
                <h3 class="h5 fw-bold">100% Seguro</h3>
                <p class="text-muted">Protegido con cifrado de grado bancario.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 bg-white shadow-sm h-100 border-bottom border-success border-4">
                <i class="bi bi-lightning-charge display-4 text-success mb-3"></i>
                <h3 class="h5 fw-bold">Ultra Rápido</h3>
                <p class="text-muted">Dinero en destino en tiempo récord.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 bg-white shadow-sm h-100 border-bottom border-info border-4">
                <i class="bi bi-headset display-4 text-info mb-3"></i>
                <h3 class="h5 fw-bold">Soporte Real</h3>
                <p class="text-muted">Atención personalizada vía WhatsApp.</p>
            </div>
        </div>
    </section>
</div>

<style>
    .transition-all {
        transition: all 0.3s ease-in-out;
    }

    .label-moneda {
        min-width: 70px;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .form-control-lg {
            font-size: 1.15rem;
        }
    }
</style>

<?php require_once __DIR__ . '/../remesas_private/src/templates/footer.php'; ?>