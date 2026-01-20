<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// SOLO redirigimos si ya está APROBADO (Verificado).
if (isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'Verificado') {
    header('Location: ' . BASE_URL . '/dashboard/');
    exit();
}

// Detectar si está pendiente
$isPending = (isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'Pendiente');

$pageTitle = 'Verificar Identidad';
// IMPORTANTE: Cargamos el script SIEMPRE para que funcione la lógica de cámara/recorte si el usuario decide corregir.
$pageScript = 'verificar.js'; 

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div id="state-pending" class="<?php echo $isPending ? '' : 'd-none'; ?> animate__animated animate__fadeIn">
                <div class="card p-5 shadow text-center border-warning">
                    <div class="mb-4">
                        <div class="spinner-border text-warning" style="width: 4rem; height: 4rem;" role="status"></div>
                    </div>
                    <h2 class="mb-3 text-warning">Verificación en Proceso</h2>
                    <p class="lead text-muted">Hemos recibido tus documentos y tu selfie.</p>
                    <p>Nuestro equipo de seguridad está revisando tu información.<br>
                    <strong>Por favor espera, esta página se actualizará automáticamente cuando tu cuenta sea aprobada.</strong></p>
                    
                    <div class="alert alert-light border mt-3 mx-auto" style="max-width: 500px;">
                        <h6 class="fw-bold"><i class="bi bi-question-circle"></i> ¿Te equivocaste de foto?</h6>
                        <p class="small text-muted mb-2">Si subiste un documento borroso o incorrecto, puedes actualizarlo ahora antes de que sea revisado.</p>
                        <button id="btn-enable-edit" class="btn btn-outline-warning text-dark fw-bold px-4">
                            <i class="bi bi-pencil-square me-2"></i> Corregir / Subir de Nuevo
                        </button>
                    </div>

                    <hr class="mt-4">
                    <a href="../logout.php" class="btn btn-outline-dark btn-sm mt-2">Cerrar Sesión</a>
                </div>

                <?php if ($isPending): ?>
                <script>
                    setInterval(async () => {
                        try {
                            const res = await fetch('../api/?accion=checkSessionStatus');
                            const data = await res.json();
                            // Si cambia el estado (ej. a Verificado o Rechazado), recargar.
                            if (data.success && data.needs_refresh) {
                                window.location.reload(); 
                            }
                        } catch (e) {}
                    }, 5000);
                </script>
                <?php endif; ?>
            </div>

            <div id="state-form" class="<?php echo $isPending ? 'd-none' : 'animate__animated animate__fadeIn'; ?>">
                <div class="card p-4 shadow-sm">
                    <h1 class="text-center mb-3">🪪 Verificación de Identidad Obligatoria 🪪</h1>
                    <p class="text-center text-muted">Para garantizar la seguridad de tus transacciones, necesitamos validar tu identidad.</p>

                    <div id="verification-alert" class="alert d-none" role="alert"></div>

                    <div id="camera-section" class="d-none mb-4 text-center bg-dark rounded p-3 position-relative">
                        <h5 class="text-white mb-2" id="camera-title">Tomando Foto...</h5>
                        <video id="camera-video" class="w-100 rounded" autoplay playsinline style="max-height: 400px; object-fit: contain;"></video>
                        <canvas id="camera-canvas" class="d-none"></canvas>
                        <div class="mt-3">
                            <button type="button" id="btn-capture" class="btn btn-light rounded-circle p-3 shadow"><i class="bi bi-circle-fill fs-2 text-danger"></i></button>
                            <button type="button" id="btn-cancel-camera" class="btn btn-outline-light btn-sm position-absolute top-0 end-0 m-3"><i class="bi bi-x-lg"></i> Cancelar</button>
                        </div>
                    </div>

                    <form id="verification-form">
                        <h5 class="mb-3 text-primary border-bottom pb-2">Paso 1: Documento de Identidad</h5>
                        
                        <div class="mb-4">
                            <label for="docFrente" class="form-label fw-bold">1. Lado Frontal</label>
                            <div class="mb-2 text-center d-none" id="container-preview-frente"><img id="preview-frente" class="img-fluid rounded border shadow-sm" style="max-height: 200px;"></div>
                            <div class="input-group">
                                <input class="form-control" type="file" id="docFrente" name="docFrente" accept="image/jpeg, image/png" required>
                                <button class="btn btn-outline-primary btn-camera" type="button" data-target="docFrente"><i class="bi bi-camera-fill"></i> Usar Cámara</button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="docReverso" class="form-label fw-bold">2. Lado Reverso</label>
                            <div class="mb-2 text-center d-none" id="container-preview-reverso"><img id="preview-reverso" class="img-fluid rounded border shadow-sm" style="max-height: 200px;"></div>
                            <div class="input-group">
                                <input class="form-control" type="file" id="docReverso" name="docReverso" accept="image/jpeg, image/png" required>
                                <button class="btn btn-outline-primary btn-camera" type="button" data-target="docReverso"><i class="bi bi-camera-fill"></i> Usar Cámara</button>
                            </div>
                        </div>

                        <div class="card shadow-sm border-primary mb-4 mt-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-person-bounding-box me-2"></i>Paso 2: Selfie en Vivo</h6>
                            </div>
                            <div class="card-body text-center">
                                <p class="text-muted small mb-3"><strong>Obligatorio:</strong> Tómate una selfie ahora mismo para validar tu identidad. <br>Esta foto se establecerá automáticamente como tu foto de perfil.</p>
                                
                                <div class="mb-3 d-flex justify-content-center">
                                    <div class="position-relative">
                                        <img id="preview-selfie" src="../assets/img/SoloLogoNegroSinFondo.png" class="rounded-circle border border-3 border-light shadow" style="width: 130px; height: 130px; object-fit: cover; background: #f8f9fa;">
                                        <label for="doc-selfie" class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle shadow" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 2px solid white; cursor: pointer;">
                                            <i class="bi bi-camera-fill"></i>
                                        </label>
                                    </div>
                                </div>

                                <input type="file" id="doc-selfie" name="selfie" class="d-none" accept="image/*" capture="user" required>
                                <div id="error-selfie" class="text-danger small fw-bold d-none mt-2"><i class="bi bi-exclamation-circle-fill"></i> La selfie es obligatoria.</div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Enviar Solicitud</button>
                            <?php if ($isPending): ?>
                                <button type="button" id="btn-cancel-edit" class="btn btn-link text-muted mt-2">Cancelar y volver a esperar</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<div class="modal fade" id="cropModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-crop"></i> Ajustar Imagen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #333; max-height: 70vh; overflow: hidden;">
                <div class="img-container" style="height: 500px; width: 100%;">
                    <img id="image-to-crop" src="" style="display: block; max-width: 100%;">
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" id="btn-rotate-left" title="Girar Izquierda">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-rotate-right" title="Girar Derecha">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" id="btn-crop-confirm">
                        <i class="bi bi-check-lg"></i> Confirmar Recorte
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Lógica para alternar entre "Pendiente" y "Formulario"
    document.addEventListener('DOMContentLoaded', () => {
        const btnEnable = document.getElementById('btn-enable-edit');
        const btnCancel = document.getElementById('btn-cancel-edit');
        const statePending = document.getElementById('state-pending');
        const stateForm = document.getElementById('state-form');

        if (btnEnable) {
            btnEnable.addEventListener('click', () => {
                statePending.classList.add('d-none');
                stateForm.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        if (btnCancel) {
            btnCancel.addEventListener('click', () => {
                stateForm.classList.add('d-none');
                statePending.classList.remove('d-none');
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>