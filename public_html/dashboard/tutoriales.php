<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}

$pageTitle = 'Tutoriales';
$pageScript = 'tutoriales.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0 fw-bold"><i class="bi bi-camera-video me-2 text-primary"></i>Tutoriales</h1>
            <p class="text-muted mb-0 mt-1">Videos guía para aprender a usar la plataforma paso a paso.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/dashboard/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div id="tutorialesClienteContainer" class="row g-4">
        <div class="col-12 text-center text-muted py-5" id="tutorialesClienteLoading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Cargando tutoriales...</p>
        </div>
    </div>
</div>

<!-- Modal reproductor -->
<div class="modal fade" id="modalReproductor" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalReproductorTitle">Tutorial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="btnCerrarReproductor"></button>
            </div>
            <div class="modal-body p-0 bg-dark">
                <div class="ratio ratio-16x9" id="reproductorContainer"></div>
            </div>
            <div class="modal-footer">
                <p class="text-muted small mb-0 me-auto" id="modalReproductorDesc"></p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
