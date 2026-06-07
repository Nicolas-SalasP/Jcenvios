<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';
$pageTitle = 'Restablecer Contraseña';
$pageScript = 'forgot-password.js'; 
require_once __DIR__ . '/../remesas_private/src/templates/header.php';
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center">Restablecer Contraseña</h3>
                    <p class="text-center text-muted">Ingresa tu correo y te enviaremos un enlace para recuperar tu cuenta.</p>
                    <form id="forgot-password-form">
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enviar Enlace de Recuperación</button>
                    </form>
                    <div id="feedback-message" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
require_once __DIR__ . '/../remesas_private/src/templates/footer.php'; 
?>