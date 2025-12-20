<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

// Si no hay una sesión de 2FA pendiente, redirigir al login
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$pageTitle = "Verificación de Identidad - JC Envíos";
include __DIR__ . '/../remesas_private/src/templates/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-md-5">

                    <div id="step-selection">
                        <div class="text-center mb-4">
                            <div class="bg-primary bg-opacity-10 d-inline-block p-3 rounded-circle mb-3">
                                <i class="fas fa-user-shield text-primary fa-2x"></i>
                            </div>
                            <h4 class="fw-bold">Verifica tu identidad</h4>
                            <p class="text-muted small">Elige cómo deseas recibir tu código de seguridad para acceder a
                                tu cuenta.</p>
                        </div>

                        <div class="list-group gap-3 border-0">
                            <button type="button"
                                class="list-group-item list-group-item-action d-flex align-items-center p-3 border rounded-3 select-method"
                                data-method="google">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                    <i class="fas fa-mobile-alt text-primary fa-lg"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold">App Autenticadora</h6>
                                    <small class="text-muted">Google Authenticator o Authy</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </button>

                            <button type="button"
                                class="list-group-item list-group-item-action d-flex align-items-center p-3 border rounded-3 select-method"
                                data-method="email">
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                                    <i class="fas fa-envelope text-success fa-lg"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold">Correo Electrónico</h6>
                                    <small class="text-muted">Enviar código a tu email</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </button>

                            <button type="button"
                                class="list-group-item list-group-item-action d-flex align-items-center p-3 border rounded-3 select-method"
                                data-method="whatsapp">
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3">
                                    <i class="fab fa-whatsapp text-info fa-lg"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold">WhatsApp / SMS</h6>
                                    <small class="text-muted">Enviar código a tu teléfono</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </button>
                        </div>
                    </div>

                    <div id="step-code" style="display: none;">
                        <div class="text-center mb-4">
                            <button class="btn btn-sm btn-link text-decoration-none mb-3" id="btn-back-to-selection">
                                <i class="fas fa-arrow-left me-1"></i> Volver a elegir método
                            </button>
                            <h4 class="fw-bold">Ingresa el código</h4>
                            <p class="text-muted small" id="method-description">
                                Ingresa el código de 6 dígitos para continuar.
                            </p>
                        </div>

                        <form id="verify-2fa-form">
                            <div class="mb-4 text-center">
                                <input type="text" id="2fa-code" class="form-control form-control-lg text-center"
                                    placeholder="000000" maxlength="6" required pattern="\d{6}"
                                    autocomplete="one-time-code"
                                    style="letter-spacing: 12px; font-size: 2.5rem; font-weight: bold; height: 80px; border-width: 2px;">
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold"
                                id="btn-submit">
                                Verificar y Entrar
                            </button>
                        </form>

                        <div class="text-center mt-4" id="resend-container" style="display: none;">
                            <p class="small text-muted mb-1">¿No recibiste el código?</p>
                            <button id="btn-resend" class="btn btn-link btn-sm text-decoration-none p-0">Reenviar
                                código</button>
                            <div id="resend-status" class="mt-2 small"></div>
                        </div>
                    </div>

                    <div class="text-center mt-5">
                        <a href="logout.php" class="text-muted small text-decoration-none">
                            Cancelar y salir
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/pages/verify-2fa.js"></script>

<?php include __DIR__ . '/../remesas_private/src/templates/footer.php'; ?>