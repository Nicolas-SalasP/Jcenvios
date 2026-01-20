<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit();
}

$pageTitle = 'Ingresar / Registrarse';

$pageScript = '';

$pageScripts = [
    'components/rut-validator.js',
    'pages/login.js'
];

require_once __DIR__ . '/../remesas_private/src/templates/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-body p-0">
                    <div class="row">
                        <div class="col-lg-6 d-none d-lg-block bg-login-image"
                            style="background-image: url('assets/img/LogoNegroSinFondo.png'); background-size: 75%; background-repeat: no-repeat; background-position: center; min-height: 400px;">
                        </div>
                        <div class="col-lg-6 p-5">

                            <ul class="nav nav-tabs nav-fill mb-4" id="authTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab"
                                        data-bs-target="#login-panel" type="button" role="tab"
                                        aria-controls="login-panel" aria-selected="true">Iniciar Sesión</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="register-tab" data-bs-toggle="tab"
                                        data-bs-target="#register-panel" type="button" role="tab"
                                        aria-controls="register-panel" aria-selected="false">Registrarse</button>
                                </li>
                            </ul>

                            <div class="tab-content" id="authTabsContent">
                                <div class="tab-pane fade show active" id="login-panel" role="tabpanel"
                                    aria-labelledby="login-tab">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">¡Bienvenido de Nuevo!</h1>
                                    </div>
                                    <form id="login-form">
                                        <div class="form-group mb-3">
                                            <label for="login-email" class="form-label">Correo Electrónico</label>
                                            <input type="email" class="form-control" id="login-email"
                                                placeholder="Ingresa tu correo..." name="email" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="login-password" class="form-label">Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="login-password"
                                                    placeholder="Contraseña" name="password" required>
                                                <span class="input-group-text toggle-password" style="cursor: pointer;">
                                                    <i class="bi bi-eye-slash-fill"></i>
                                                </span>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-user w-100">
                                            Ingresar
                                        </button>
                                        <div id="login-feedback" class="form-text text-danger mt-2"></div>
                                    </form>
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="forgot-password.php">¿Olvidaste tu contraseña?</a>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="register-panel" role="tabpanel"
                                    aria-labelledby="register-tab">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">¡Crea una Cuenta!</h1>
                                    </div>
                                    <form id="register-form" novalidate>
                                        <input type="hidden" name="tipoPersona" value="Persona Natural">

                                        <div class="row">
                                            <div class="form-group col-sm-12 mb-3">
                                                <label for="register-nombre" class="form-label">Primer Nombre <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="register-nombre"
                                                    placeholder="Primer Nombre" name="primerNombre" required>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div id="container-segundo-nombre">
                                                <label for="register-segundo-nombre" class="form-label">Segundo Nombre
                                                    <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control mb-2"
                                                    id="register-segundo-nombre" placeholder="Segundo Nombre"
                                                    name="segundoNombre" required>
                                            </div>

                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                    id="toggle-segundo-nombre">
                                                <label class="form-check-label small text-muted"
                                                    for="toggle-segundo-nombre">No tengo segundo nombre</label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="form-group col-sm-12 mb-3">
                                                <label for="register-apellido" class="form-label">Primer Apellido <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="register-apellido"
                                                    placeholder="Primer Apellido" name="primerApellido" required>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div id="container-segundo-apellido">
                                                <label for="register-segundo-apellido" class="form-label">Segundo
                                                    Apellido <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control mb-2"
                                                    id="register-segundo-apellido" placeholder="Segundo Apellido"
                                                    name="segundoApellido" required>
                                            </div>

                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                    id="toggle-segundo-apellido">
                                                <label class="form-check-label small text-muted"
                                                    for="toggle-segundo-apellido">No tengo segundo apellido</label>
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="register-email" class="form-label">Correo Electrónico <span
                                                    class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="register-email"
                                                placeholder="Correo Electrónico" name="email" required>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-sm-6 mb-3">
                                                <label for="register-doc-type" class="form-label">Tipo de Documento
                                                    <span class="text-danger">*</span></label>
                                                <select class="form-select" id="register-doc-type" name="tipoDocumento"
                                                    required>
                                                    <option value="">Cargando...</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-sm-6 mb-3">
                                                <label for="register-doc-num" class="form-label">Nro. Documento <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="register-doc-num"
                                                    placeholder="Nro. Documento" name="numeroDocumento" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="form-group col-sm-12 mb-3">
                                                <label for="register-telefono" class="form-label">Teléfono <span
                                                        class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <select class="form-select" id="register-phone-code"
                                                        name="phoneCode" style="max-width: 130px;"></select>
                                                    <input type="tel" class="form-control" id="register-telefono"
                                                        placeholder="Número" name="phoneNumber" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="form-group col-sm-6 mb-3">
                                                <label for="register-password" class="form-label">Contraseña <span
                                                        class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="register-password"
                                                        placeholder="Contraseña" name="password" required>
                                                    <span class="input-group-text toggle-password"
                                                        style="cursor: pointer;">
                                                        <i class="bi bi-eye-slash-fill"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="form-group col-sm-6 mb-3">
                                                <label for="register-password-repeat" class="form-label">Repetir
                                                    Contraseña <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control"
                                                        id="register-password-repeat" placeholder="Repetir Contraseña"
                                                        name="passwordRepeat" required>
                                                    <span class="input-group-text toggle-password"
                                                        style="cursor: pointer;">
                                                        <i class="bi bi-eye-slash-fill"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-user w-100">
                                            Registrar Cuenta
                                        </button>
                                        <div id="register-feedback" class="form-text text-danger mt-2"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../remesas_private/src/templates/footer.php';
?>