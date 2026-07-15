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

$pageTitle = 'Tasas Visuales';
$pageScript = 'admin-tasas-imagen.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-0 fw-bold"><i class="bi bi-image me-2 text-primary"></i>Tasas Visuales</h1>
            <p class="text-muted mb-0 mt-1">Sube una imagen de tasas (captura o gráfico) para mostrarla públicamente en WhatsApp y en la web. Módulo independiente del editor numérico de Tasas.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
        <div class="small text-muted">
            Formatos aceptados: JPG, PNG o WEBP, hasta 10MB. Al subir una nueva imagen se reemplaza la anterior de ese tipo.
        </div>
    </div>

    <div class="row g-4" id="tasasImagenContainer">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-whatsapp text-success me-2"></i>Tasas WhatsApp</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3 tasa-imagen-preview-wrap" id="preview-wrap-whatsapp">
                        <img src="" alt="Tasas WhatsApp" class="img-fluid rounded border d-none" id="preview-whatsapp">
                        <div class="text-muted small py-4" id="preview-empty-whatsapp">Sin imagen cargada todavía.</div>
                    </div>
                    <p class="small text-muted mb-3" id="fecha-whatsapp"></p>
                    <form id="form-whatsapp" enctype="multipart/form-data">
                        <input type="hidden" name="tipoFuente" value="whatsapp">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="alert alert-danger d-none" id="error-whatsapp"></div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i> Subir / Reemplazar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-globe text-primary me-2"></i>Tasas Web</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3 tasa-imagen-preview-wrap" id="preview-wrap-web">
                        <img src="" alt="Tasas Web" class="img-fluid rounded border d-none" id="preview-web">
                        <div class="text-muted small py-4" id="preview-empty-web">Sin imagen cargada todavía.</div>
                    </div>
                    <p class="small text-muted mb-3" id="fecha-web"></p>
                    <form id="form-web" enctype="multipart/form-data">
                        <input type="hidden" name="tipoFuente" value="web">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="alert alert-danger d-none" id="error-web"></div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i> Subir / Reemplazar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
