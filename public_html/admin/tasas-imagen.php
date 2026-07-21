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
            Formatos aceptados: JPG, PNG o WEBP, hasta 10MB. Podés subir varias imágenes por tipo, cada una con
            título y descripción opcionales. El ajuste automático de tasas fuera de horario laboral borra todas
            las imágenes de esta galería.
        </div>
    </div>

    <div class="row g-4" id="tasasImagenContainer">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-whatsapp text-success me-2"></i>Tasas WhatsApp</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3" id="galeria-whatsapp"></div>
                    <p class="text-muted small py-2 d-none" id="galeria-empty-whatsapp">Sin imágenes cargadas todavía.</p>

                    <hr>
                    <form id="form-whatsapp" enctype="multipart/form-data">
                        <input type="hidden" name="tipoFuente" value="whatsapp">
                        <div class="mb-2">
                            <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control" name="titulo" placeholder="Título (opcional)" maxlength="150">
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" name="descripcion" placeholder="Descripción (opcional)" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i> Agregar imagen
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
                    <div class="row g-3 mb-3" id="galeria-web"></div>
                    <p class="text-muted small py-2 d-none" id="galeria-empty-web">Sin imágenes cargadas todavía.</p>

                    <hr>
                    <form id="form-web" enctype="multipart/form-data">
                        <input type="hidden" name="tipoFuente" value="web">
                        <div class="mb-2">
                            <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control" name="titulo" placeholder="Título (opcional)" maxlength="150">
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" name="descripcion" placeholder="Descripción (opcional)" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i> Agregar imagen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview -->
<div class="modal fade" id="previewTasaImagenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewTasaImagenTitulo">Vista previa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-dark">
                <img id="previewTasaImagenImg" src="" class="img-fluid" alt="Vista previa">
            </div>
        </div>
    </div>
</div>

<!-- Editar (recortar/redimensionar) -->
<div class="modal fade" id="editTasaImagenModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-crop"></i> Editar imagen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark" style="max-height: 70vh; overflow: hidden;">
                <input type="hidden" id="editTasaImagenId">
                <div class="img-container" style="height: 500px; width: 100%;">
                    <img id="editTasaImagenImg" src="" style="display: block; max-width: 100%;">
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" id="editTasaImagenRotateLeft"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="editTasaImagenRotateRight"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div>
                    <div class="alert alert-danger d-none py-1 px-2 d-inline-block mb-0 me-2" id="editTasaImagenError"></div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" id="editTasaImagenConfirm">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reemplazar (imagen + titulo + descripcion) -->
<div class="modal fade" id="replaceTasaImagenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Reemplazar imagen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-replace-tasa-imagen">
                <div class="modal-body">
                    <input type="hidden" name="id" id="replaceTasaImagenId">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Imagen nueva</label>
                        <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Título (opcional)</label>
                        <input type="text" class="form-control" name="titulo" id="replaceTasaImagenTitulo" maxlength="150">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Descripción (opcional)</label>
                        <textarea class="form-control" name="descripcion" id="replaceTasaImagenDescripcion" rows="2"></textarea>
                    </div>
                    <div class="alert alert-danger d-none" id="replaceTasaImagenError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Reemplazar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
