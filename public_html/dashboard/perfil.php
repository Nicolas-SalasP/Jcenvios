<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$pageTitle = 'Mi Perfil';

$pageScript = '';

$pageScripts = [
    'components/rut-validator.js',
    'pages/perfil.js'
];

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card p-4 p-md-5 shadow-sm">
                <h1 class="mb-4">Mi Perfil</h1>
                <div id="profile-loading" class="text-center">
                    <div class="spinner-border text-primary" role="status"><span
                            class="visually-hidden">Cargando...</span></div>
                </div>

                <form id="profile-form" class="d-none" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="profile-img-preview" src="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png"
                                alt="Foto de perfil" class="rounded-circle"
                                style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #eee;">
                            
                            <div id="photo-required-badge" class="d-none position-absolute top-0 end-0 bg-danger text-white rounded-circle p-1" style="width: 20px; height: 20px; border: 2px solid white;"></div>
                        </div>

                        <div class="mt-3">
                            <button type="button" id="btn-open-camera" class="btn btn-sm btn-primary">
                                <i class="bi bi-camera-fill me-1"></i> Tomar Foto
                            </button>
                            <p class="small text-muted mt-1 mb-0" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle"></i> Obligatorio: Selfie en vivo.
                            </p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="profile-nombre" class="form-label">Nombre</label>
                        <input type="text" id="profile-nombre" class="form-control" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label for="profile-email" class="form-label">Email</label>
                        <input type="email" id="profile-email" class="form-control" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label for="profile-documento" class="form-label">Documento</label>
                        <input type="text" id="profile-documento" class="form-control" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label for="profile-telefono" class="form-label">Teléfono </label>
                        <div class="input-group">
                            <select class="input-group-text" id="profile-phone-code" name="profilePhoneCode"
                                style="max-width: 130px;" disabled></select>
                            <input type="tel" id="profile-telefono" name="telefono" class="form-control"
                                style="background-color: #fff;"> </div>
                        <div class="form-text text-muted">Mantenga su número actualizado.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado de Verificación</label>
                        <div><span id="profile-estado" class="badge">Cargando...</span></div>
                    </div>
                    <div id="verification-link-container" class="mt-2 mb-3"></div>

                    <button type="submit" id="profile-save-btn" class="btn btn-primary w-100">Guardar Cambios</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card p-4 p-md-5 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">Mis Beneficiarios</h2>
                    <button id="add-account-btn" class="btn btn-success" data-bs-toggle="modal"
                        data-bs-target="#addAccountModal">
                        <i class="bi bi-plus-circle"></i> Nuevo Beneficiario
                    </button>
                </div>
                <div id="beneficiarios-loading" class="text-center">
                    <div class="spinner-border text-secondary" role="status"><span
                            class="visually-hidden">Cargando...</span></div>
                </div>
                <div id="beneficiary-list-container" class="list-group"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountModalLabel">Registrar Beneficiario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-beneficiary-form" novalidate>
                    <input type="hidden" id="benef-cuenta-id" name="cuentaId">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="benef-pais-id" class="form-label">País de Destino</label>
                            <select id="benef-pais-id" name="paisID" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="benef-alias" class="form-label">Alias de la cuenta</label>
                            <input type="text" class="form-control" id="benef-alias" name="alias" required
                                placeholder="Ej: Mamá Banesco">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="benef-tipo" class="form-label">Tipo de Transferencia</label>
                            <select id="benef-tipo" name="tipoBeneficiario" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted">Datos del Titular</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="benef-firstname" class="form-label">Primer Nombre <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="benef-firstname" name="primerNombre" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-nombre">
                            <label for="benef-secondname" class="form-label">Segundo Nombre <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="benef-secondname" name="segundoNombre" required>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="toggle-benef-segundo-nombre">
                        <label class="form-check-label small text-muted" for="toggle-benef-segundo-nombre">No tiene
                            segundo nombre</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="benef-lastname" class="form-label">Primer Apellido <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="benef-lastname" name="primerApellido" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-apellido">
                            <label for="benef-secondlastname" class="form-label">Segundo Apellido <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="benef-secondlastname" name="segundoApellido"
                                required>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="toggle-benef-segundo-apellido">
                        <label class="form-check-label small text-muted" for="toggle-benef-segundo-apellido">No tiene
                            segundo apellido</label>
                    </div>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="benef-doc-type" class="form-label">Tipo de Documento</label>
                            <select id="benef-doc-type" name="tipoDocumento" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label for="benef-doc-number" class="form-label">Número de Documento</label>
                            <div class="input-group">
                                <select class="input-group-text d-none" id="benef-doc-prefix" style="max-width: 80px;">
                                    <option value="V">V</option>
                                    <option value="E">E</option>
                                    <option value="J">J</option>
                                    <option value="G">G</option>
                                    <option value="P">P</option>
                                </select>
                                <input type="text" class="form-control" id="benef-doc-number" name="numeroDocumento"
                                    required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="benef-bank" class="form-label">Nombre del Banco</label>
                        <input type="text" class="form-control" id="benef-bank" name="nombreBanco" required
                            placeholder="Ej: Banesco, Venezuela, Mercantil">
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3" id="container-account-number">
                            <label for="benef-account-num" class="form-label">Número de Cuenta (20 dígitos)</label>
                            <input type="text" class="form-control" id="benef-account-num" name="numeroCuenta" required
                                maxlength="20" placeholder="0102...">
                        </div>

                        <div class="col-md-12 mb-3 d-none" id="container-phone-number">
                            <label for="benef-phone-number" class="form-label">Teléfono (Pago Móvil)</label>
                            <div class="input-group">
                                <select class="input-group-text" id="benef-phone-code" name="phoneCode"
                                    style="max-width: 130px;"></select>
                                <input type="tel" class="form-control" id="benef-phone-number" name="phoneNumber"
                                    placeholder="Número">
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary" form="add-beneficiary-form">Guardar Beneficiario</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cameraModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fs-6">Tomar Selfie (Perfil)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="btn-close-camera"></button>
            </div>
            <div class="modal-body text-center p-3 position-relative">
                <div class="rounded overflow-hidden position-relative mx-auto" style="max-width: 400px;">
                    <video id="video-feed" autoplay playsinline style="width: 100%; height: auto; transform: scaleX(-1); border-radius: 10px;"></video>
                    
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                                width: 180px; height: 240px; 
                                border: 2px dashed rgba(255,255,255,0.8); 
                                border-radius: 50%; pointer-events: none;
                                box-shadow: 0 0 0 999px rgba(0,0,0,0.5);"></div>
                    <div class="position-absolute bottom-0 w-100 text-center pb-2 text-white small" style="z-index: 5;">
                        Ubique su rostro en el óvalo
                    </div>
                </div>
                
                <canvas id="capture-canvas" class="d-none"></canvas>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" id="btn-capture-photo" class="btn btn-light rounded-circle p-3 shadow-lg">
                    <i class="bi bi-circle-fill text-danger fs-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>