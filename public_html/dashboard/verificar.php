<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}


if (isset($_SESSION['verification_status']) && in_array($_SESSION['verification_status'], ['Verificado', 'Pendiente'])) {
    header('Location: ' . BASE_URL . '/dashboard/');
    exit();
}

$pageTitle = 'Verificar Identidad';
$pageScript = 'verificar.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4 shadow-sm">
                <h1 class="text-center mb-3">🪪 Verificación de Identidad Obligatoria 🪪</h1>
                <p class="text-center text-muted">Para garantizar la seguridad de tus transacciones, necesitamos validar
                    tu identidad. Por favor, sube una foto clara tu documento de identidad por ambos lados (frontal y
                    reverso)</p>

                <div id="verification-alert" class="alert d-none" role="alert"></div>

                <div id="camera-section" class="d-none mb-4 text-center bg-dark rounded p-3 position-relative">
                    <h5 class="text-white mb-2" id="camera-title">Tomando Foto...</h5>
                    <video id="camera-video" class="w-100 rounded" autoplay playsinline
                        style="max-height: 400px; object-fit: contain;"></video>
                    <canvas id="camera-canvas" class="d-none"></canvas>

                    <div class="mt-3">
                        <button type="button" id="btn-capture" class="btn btn-light rounded-circle p-3 shadow"
                            title="Capturar">
                            <i class="bi bi-circle-fill fs-2 text-danger"></i>
                        </button>
                        <button type="button" id="btn-cancel-camera"
                            class="btn btn-outline-light btn-sm position-absolute top-0 end-0 m-3">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </button>
                    </div>
                </div>
                <form id="verification-form">
                    <div class="mb-4">
                        <label for="docFrente" class="form-label fw-bold">1. RUT (Lado Frontal)</label>
                        <div class="input-group">
                            <input class="form-control" type="file" id="docFrente" name="docFrente"
                                accept="image/jpeg, image/png" required>
                            <button class="btn btn-outline-primary btn-camera" type="button" data-target="docFrente">
                                <i class="bi bi-camera-fill"></i> Usar Camara
                            </button>
                        </div>
                        <div class="form-text">Sube una foto clara o tómala ahora mismo.</div>
                    </div>

                    <div class="mb-4">
                        <label for="docReverso" class="form-label fw-bold">2. RUT (Lado Reverso)</label>
                        <div class="input-group">
                            <input class="form-control" type="file" id="docReverso" name="docReverso"
                                accept="image/jpeg, image/png" required>
                            <button class="btn btn-outline-primary btn-camera" type="button" data-target="docReverso">
                                <i class="bi bi-camera-fill"></i> Usar Cámara
                            </button>
                        </div>
                        <div class="form-text">Asegurate de que los datos sean legibles.</div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Enviar para Verificación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>