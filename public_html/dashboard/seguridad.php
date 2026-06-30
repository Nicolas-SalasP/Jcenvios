<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: ' . BASE_URL . '/login.php?expired=1'); 
    exit(); 
}


$pageTitle = 'Seguridad de la Cuenta';
$pageScript = 'seguridad.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4 p-md-5 shadow-sm">
                <?php ?>
                <?php if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false): ?>
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading"><i class="bi bi-shield-lock-fill"></i> ¡Acción Requerida!</h4>
                        <p>Para proteger tu cuenta, es obligatorio configurar la autenticación de dos factores (2FA) antes de continuar.</p>
                        <hr>
                        <p class="mb-0">Sigue los pasos a continuación para activarla.</p>
                    </div>
                <?php endif; ?>

                <h1 class="mb-4">Seguridad de la Cuenta</h1>
                
                <div id="2fa-status-container">
                    <div class="d-flex align-items-center">
                        <strong>Estado 2FA:</strong>
                        <div class="spinner-border spinner-border-sm ms-2" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
                
                <hr>

                <div id="setup-2fa-section" class="d-none">
                    <h3 class="h4">Configurar Doble Factor (2FA)</h3>
                    <p>Escanea el siguiente código QR con tu aplicación de autenticación (como Google Authenticator, Authy, etc.).</p>
                    
                    <div class="text-center my-3" style="min-height: 200px;">
                        <div id="qr-code-container" class="d-inline-block border p-2">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando QR...</span>
                            </div>
                        </div>
                        <p class="mt-2">O ingresa manualmente esta clave:</p>
                        <code id="secret-key-display" class="fs-5 user-select-all bg-light p-2 rounded">Cargando...</code>
                    </div>
                    
                    <form id="verify-2fa-form">
                        <div class="mb-3">
                            <label for="2fa-code" class="form-label">Código de Verificación</label>
                            <input type="text" class="form-control" id="2fa-code" name="code" inputmode="numeric" maxlength="6" required autocomplete="off" placeholder="Ingresa el código de 6 dígitos">
                        </div>
                        <button type="submit" class="btn btn-success">Activar y Verificar</button>
                    </form>
                </div>
                
                <div id="disable-2fa-section" class="d-none">
                    <h3 class="h4">Desactivar Doble Factor (2FA)</h3>
                    <p class="text-danger">Tu cuenta estará menos segura si desactivas la autenticación de doble factor.</p>
                    <button id="disable-2fa-btn" class="btn btn-danger">Desactivar 2FA</button>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="backupCodesModal" tabindex="-1" aria-labelledby="backupCodesModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="backupCodesModalLabel">¡2FA Activado! Guarda tus Códigos de Respaldo</h5>
      </div>
      <div class="modal-body">
        <p class="lead">Guarda estos códigos en un lugar seguro (como un gestor de contraseñas). Te permitirán acceder a tu cuenta si pierdes tu dispositivo.</p>
        <div class="bg-light p-3 rounded">
            <ul id="backup-codes-list" class="list-unstyled mb-0" style="font-family: monospace; font-size: 1.1rem; column-count: 2;">
                </ul>
        </div>
        <p class="mt-3 text-danger fw-bold">No volverás a ver estos códigos. Cópialos antes de cerrar.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido, los he guardado</button>
      </div>
    </div>
  </div>
</div>


<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>