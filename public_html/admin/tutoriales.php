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

$pageTitle = 'Tutoriales';
$pageScript = 'admin-tutoriales.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-0 fw-bold"><i class="bi bi-camera-video me-2 text-primary"></i>Tutoriales</h1>
            <p class="text-muted mb-0 mt-1">Videos guía para los clientes: sube un archivo o enlaza un video externo (YouTube/Vimeo).</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" id="btnNuevoTutorial">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Tutorial
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
        <div class="small text-muted">
            Recomendado: usa "URL externa" (YouTube/Vimeo) siempre que sea posible — no ocupa espacio en el servidor.
            La subida de archivo admite MP4, WEBM o MOV, hasta 100MB.
        </div>
    </div>

    <div id="tutorialesContainer" class="row g-4">
        <div class="col-12 text-center text-muted py-5" id="tutorialesLoading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Cargando tutoriales...</p>
        </div>
    </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalTutorial" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formTutorial" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTutorialTitle">Nuevo Tutorial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="tutorialId" id="tutorialId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">TÍTULO</label>
                        <input type="text" class="form-control" name="titulo" id="tutorialTitulo" maxlength="150" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">DESCRIPCIÓN</label>
                        <textarea class="form-control" name="descripcion" id="tutorialDescripcion" rows="3" maxlength="1000"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted d-block">FUENTE DEL VIDEO</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipoFuente" id="tipoUrl" value="url" checked>
                            <label class="btn btn-outline-primary" for="tipoUrl"><i class="bi bi-link-45deg me-1"></i>URL Externa</label>

                            <input type="radio" class="btn-check" name="tipoFuente" id="tipoArchivo" value="archivo">
                            <label class="btn btn-outline-primary" for="tipoArchivo"><i class="bi bi-upload me-1"></i>Subir Archivo</label>
                        </div>
                    </div>

                    <div class="mb-3" id="grupoUrlExterna">
                        <label class="form-label small fw-bold text-muted">URL (YouTube o Vimeo)</label>
                        <input type="url" class="form-control" name="urlExterna" id="tutorialUrlExterna" placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                    <div class="mb-3 d-none" id="grupoArchivoVideo">
                        <label class="form-label small fw-bold text-muted">ARCHIVO DE VIDEO (MP4, WEBM, MOV — máx 100MB)</label>
                        <input type="file" class="form-control" name="video" id="tutorialVideoFile" accept="video/mp4,video/webm,video/quicktime">
                        <small class="text-muted d-block mt-1" id="archivoActualInfo"></small>
                    </div>

                    <div class="alert alert-danger d-none" id="tutorialFormError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarTutorial">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminación -->
<div class="modal fade" id="modalEliminarTutorial" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eliminar Tutorial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                ¿Seguro que deseas eliminar este tutorial? Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminarTutorial">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
