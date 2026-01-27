<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$pageTitle = 'Mi Perfil';
$pageScripts = [
    'components/rut-validator.js',
    'pages/perfil.js'
];

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card p-4 shadow-sm">
                <h1 class="mb-4 h3">Mi Perfil</h1>
                <div id="profile-loading" class="text-center">
                    <div class="spinner-border text-primary" role="status"><span
                            class="visually-hidden">Cargando...</span></div>
                </div>

                <form id="profile-form" class="d-none" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="profile-img-preview"
                                src="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png" alt="Foto de perfil"
                                class="rounded-circle border" style="width: 150px; height: 150px; object-fit: cover;">
                            <div id="photo-required-badge"
                                class="d-none position-absolute top-0 end-0 bg-danger text-white rounded-circle p-1"
                                style="width: 20px; height: 20px; border: 2px solid white;"></div>
                        </div>
                        <div class="mt-2">
                            <button type="button" id="btn-open-camera" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-camera-fill me-1"></i> Tomar Selfie
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" id="profile-nombre" class="form-control" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" id="profile-email" class="form-control" readonly disabled>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Documento</label>
                            <input type="text" id="profile-documento" class="form-control" readonly disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Teléfono</label>
                            <div class="input-group">
                                <select class="input-group-text" id="profile-phone-code" style="max-width: 80px;"
                                    disabled></select>
                                <input type="tel" id="profile-telefono" class="form-control" readonly disabled>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                        <span class="small fw-bold text-muted">Estado:</span>
                        <span id="profile-estado" class="badge bg-secondary">Cargando...</span>
                    </div>
                    <div id="verification-link-container" class="mb-3 text-center"></div>

                    <button type="submit" id="profile-save-btn" class="btn btn-primary w-100">Guardar Cambios</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0">Mis Beneficiarios</h2>
                    <button id="btn-new-beneficiary" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addAccountModal">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo
                    </button>
                </div>
                <div id="beneficiarios-loading" class="text-center py-3">
                    <div class="spinner-border text-secondary" role="status"><span
                            class="visually-hidden">Cargando...</span></div>
                </div>
                <div id="beneficiary-list-container" class="list-group list-group-flush"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addAccountModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Registrar
                    Nuevo Beneficiario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-beneficiary-form">
                    <input type="hidden" id="benef-cuenta-id" name="cuentaId">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="benef-pais-id" class="form-label">País Destino</label>
                            <select class="form-select" id="benef-pais-id" name="paisID" required>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="benef-alias" class="form-label">Alias (Nombre corto)</label>
                            <input type="text" class="form-control" id="benef-alias" name="alias" required
                                placeholder="Ej: Mamá Banesco">
                        </div>
                    </div>

                    <h6 class="text-primary mt-3"><i class="bi bi-person-vcard me-2"></i>Datos del Titular</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Primer Nombre</label>
                            <input type="text" class="form-control" name="primerNombre" id="benef-firstname" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-nombre">
                            <label class="form-label">Segundo Nombre</label>
                            <input type="text" class="form-control" id="benef-secondname" name="segundoNombre">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="toggle-benef-segundo-nombre" checked>
                        <label class="form-check-label small text-muted" for="toggle-benef-segundo-nombre">No tiene
                            segundo nombre</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Primer Apellido</label>
                            <input type="text" class="form-control" name="primerApellido" id="benef-lastname" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-apellido">
                            <label class="form-label">Segundo Apellido</label>
                            <input type="text" class="form-control" id="benef-secondlastname" name="segundoApellido">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="toggle-benef-segundo-apellido" checked>
                        <label class="form-check-label small text-muted" for="toggle-benef-segundo-apellido">No tiene
                            segundo apellido</label>
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="benef-doc-type" class="form-label">Tipo Documento</label>
                            <select id="benef-doc-type" name="tipoDocumento" class="form-select" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label">Número Documento</label>
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

                    <hr>
                    <h6 class="text-primary mt-3"><i class="bi bi-bank me-2"></i>Datos Bancarios</h6>

                    <div class="mb-3 d-none" id="container-bank-select">
                        <label for="benef-bank-select" class="form-label">Banco / Billetera</label>
                        <select class="form-select" id="benef-bank-select" name="nombreBancoSelect">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="container-bank-input-text">
                        <label for="benef-bank" class="form-label">Nombre del Banco</label>
                        <input type="text" class="form-control" id="benef-bank" name="nombreBanco"
                            placeholder="Ej: Banesco, Mercantil...">
                    </div>

                    <div class="mb-3 d-none" id="other-bank-container">
                        <label class="form-label small text-muted">Especifique Nombre del Banco</label>
                        <input type="text" class="form-control" id="benef-bank-other" placeholder="Ej: Pichincha">
                    </div>

                    <div class="mb-3 d-none" id="wrapper-checks-type">
                        <div class="card bg-light border-0 p-3">
                            <label class="form-label d-block small fw-bold">Datos a registrar:</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="check-include-bank"
                                        name="incluirCuentaBancaria" checked>
                                    <label class="form-check-label" for="check-include-bank">Cuenta Bancaria</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="check-include-mobile"
                                        name="incluirPagoMovil">
                                    <label class="form-check-label" for="check-include-mobile">Pago Móvil</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="container-bank-input">
                        <label class="form-label" id="label-account-num">Número de Cuenta</label>
                        <input type="text" class="form-control font-monospace" id="benef-account-num"
                            name="numeroCuenta" maxlength="20" placeholder="Número de cuenta">
                    </div>

                    <div class="mb-3 d-none" id="container-cci">
                        <label class="form-label">CCI (Código Interbancario)</label>
                        <input type="text" class="form-control font-monospace" id="benef-cci" name="cci"
                            placeholder="20 dígitos">
                    </div>

                    <div class="mb-3 d-none" id="container-mobile-input">
                        <label class="form-label" id="label-wallet-phone">Número de Teléfono</label>
                        <div class="input-group">
                            <select class="input-group-text d-none" id="benef-phone-code" name="phoneCode"
                                style="max-width: 100px;"></select>
                            <span class="input-group-text d-none" id="wallet-phone-prefix"></span>
                            <input type="tel" class="form-control" id="benef-phone-number" name="phoneNumber"
                                placeholder="Ej: 999 999 999">
                        </div>
                    </div>

                    <div class="modal-footer px-0 pb-0 border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4">Guardar Beneficiario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cameraModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fs-6">Tomar Selfie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    id="btn-close-camera"></button>
            </div>
            <div class="modal-body text-center p-3 position-relative">
                <div class="rounded overflow-hidden position-relative mx-auto" style="max-width: 400px;">
                    <video id="video-feed" autoplay playsinline
                        style="width: 100%; border-radius: 10px; transform: scaleX(-1);"></video>
                </div>
                <canvas id="capture-canvas" class="d-none"></canvas>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" id="btn-capture-photo" class="btn btn-light rounded-circle shadow-lg"
                    style="width: 60px; height: 60px;">
                    <i class="bi bi-circle-fill text-danger fs-3"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>