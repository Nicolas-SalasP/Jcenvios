<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

if (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Admin') {
    header('Location: ' . BASE_URL . '/admin/');
    exit();
}
if (isset($_SESSION['user_rol_name']) && $_SESSION['user_rol_name'] === 'Operador') {
    header('Location: ' . BASE_URL . '/operador/pendientes.php');
    exit();
}

$estadosBloqueados = ['No Verificado', 'Rechazado', 'Pendiente'];

if (!isset($_SESSION['verification_status']) || in_array($_SESSION['verification_status'], $estadosBloqueados)) {
    header('Location: ' . BASE_URL . '/dashboard/verificar.php');
    exit();
}

$pageTitle = 'Realizar Transacción';
$pageScript = 'dashboard.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-5 pt-5 mb-5">
    <div class="row">
        <div class="col-lg-10 offset-lg-1 col-xl-8 offset-xl-2">
            <div class="card p-4 p-md-5 shadow-sm border-0">
                <form id="remittance-form" novalidate>
                    <input type="hidden" id="user-id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                    <input type="hidden" id="selected-tasa-id">
                    <input type="hidden" id="selected-cuenta-id">

                    <div class="form-step active" id="step-1">
                        <h3 class="text-center mb-4 fw-bold text-primary">Paso 1: Selecciona la Ruta</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pais-origen" class="form-label fw-bold">País de Origen</label>
                                <select id="pais-origen" class="form-select form-select-lg" required>
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pais-destino" class="form-label fw-bold">País de Destino</label>
                                <select id="pais-destino" class="form-select form-select-lg" required>
                                    <option value="">Selecciona un origen</option>
                                </select>
                            </div>
                        </div>
                        <div id="tasa-referencial-container"
                            class="alert alert-info d-none mt-3 text-center animate__animated animate__fadeIn">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <span id="tasa-referencial-paso1" class="fw-bold">Calculando tasa...</span>
                        </div>
                    </div>

                    <div class="form-step" id="step-2">
                        <h3 class="text-center mb-4 fw-bold text-primary">Paso 2: Beneficiario</h3>
                        <div class="form-group">
                            <label class="form-label">Cuentas Guardadas</label>
                            <div id="beneficiary-list" class="list-group shadow-sm"></div>
                        </div>
                        <button type="button" id="add-account-btn"
                            class="btn btn-outline-success w-100 mt-3 border-2 dashed">
                            <i class="bi bi-plus-circle me-2"></i> Registrar Nueva Cuenta
                        </button>
                    </div>

                    <div class="form-step" id="step-3">
                        <h3 class="text-center mb-4 fw-bold text-primary">Paso 3: Ingresa el Monto</h3>

                        <div class="alert alert-light border border-info d-flex align-items-center mb-4 bg-light"
                            role="alert">
                            <i class="bi bi-calculator text-info me-3 fs-3"></i>
                            <div>
                                <span id="container-bcv-rate" class="d-none">
                                    <strong>Ref. BCV:</strong> <span id="bcv-rate-display">...</span><br>
                                </span>
                                <div class="small text-muted">Ingresa el monto en cualquier campo, calcularemos el
                                    resto.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="monto-origen" class="form-label fw-bold" id="label-monto-origen">
                                    Tú envías (CLP)
                                </label>
                                <div class="input-group input-group-lg">
                                    <input type="text" inputmode="decimal" id="monto-origen"
                                        class="form-control fw-bold" placeholder="0" required>
                                    <span class="input-group-text bg-light text-muted fw-bold"
                                        id="currency-label-origen">CLP</span>
                                </div>
                                <div class="form-text text-end mt-1" id="tasa-comercial-display">Tasa: ...</div>
                            </div>

                            <div class="col-md-6" id="container-col-destino">
                                <label for="monto-destino" class="form-label fw-bold text-success">
                                    Beneficiario recibe
                                </label>
                                <div class="input-group input-group-lg">
                                    <input type="text" inputmode="decimal" id="monto-destino"
                                        class="form-control border-success text-success fw-bold" placeholder="0,00">
                                    <span class="input-group-text bg-success text-white fw-bold"
                                        id="currency-label-destino">...</span>
                                </div>
                            </div>

                            <div class="col-md-6 d-none" id="container-monto-usd">
                                <label for="monto-usd" class="form-label fw-bold text-primary">Equivalente (USD)</label>
                                <div class="input-group input-group-lg">
                                    <input type="text" inputmode="decimal" id="monto-usd"
                                        class="form-control border-primary text-primary fw-bold" placeholder="0,00">
                                    <span class="input-group-text bg-primary text-white fw-bold">USD</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="forma-pago" class="form-label fw-bold">Método de Pago</label>
                            <select id="forma-pago" class="form-select form-select-lg" required>
                                <option value="">Cargando opciones...</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-step" id="step-4">
                        <h3 class="text-center mb-4 fw-bold text-primary">Paso 4: Resumen</h3>
                        <div id="summary-container" class="mb-4"></div>
                        <div class="alert alert-warning border-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Por favor revisa los datos antes de confirmar.
                        </div>
                    </div>

                    <div class="form-step" id="step-5">
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                            <h2 class="mt-3 fw-bold text-success">¡Orden Registrada!</h2>

                            <p class="text-muted fs-5 mt-2">
                                ID de Transacción:
                                <strong id="transaccion-id-final"
                                    class="text-dark bg-light px-2 py-1 rounded border">...</strong>
                            </p>

                            <hr class="my-4">

                            <div id="msg-exito-normal">
                                <div class="alert alert-light border border-2 border-warning shadow-sm p-4 mb-4">
                                    <h4 class="alert-heading fw-bold text-warning"><i
                                            class="bi bi-upload me-2"></i>Falta un paso importante</h4>
                                    <p class="mb-0 fs-5">
                                        Para procesar tu envío, es <strong>obligatorio</strong> que subas el comprobante
                                        de transferencia.
                                    </p>
                                </div>
                                <a href="historial.php"
                                    class="btn btn-success btn-lg w-100 py-3 fs-5 shadow hover-scale">
                                    <i class="bi bi-file-earmark-arrow-up-fill me-2"></i> Subir Comprobante Ahora
                                </a>
                            </div>

                            <div id="msg-exito-riesgo" class="d-none">
                                <div class="alert alert-light border border-2 border-info shadow-sm p-4 mb-4">
                                    <h4 class="alert-heading fw-bold text-info"><i
                                            class="bi bi-shield-lock-fill me-2"></i>Orden en Revisión</h4>
                                    <p class="mb-0 fs-5">
                                        Tu orden requiere aprobación de seguridad. Te notificaremos cuando esté aprobada
                                        para que puedas realizar el pago y subir el comprobante.
                                    </p>
                                </div>
                                <a href="historial.php" class="btn btn-primary btn-lg w-100 py-3 fs-5 shadow">
                                    <i class="bi bi-clock-history me-2"></i> Ir al Historial
                                </a>
                            </div>

                            <div class="mt-3">
                                <a href="index.php" class="text-decoration-none text-muted small">Realizar otro
                                    envío</a>
                            </div>
                        </div>
                    </div>

                    <div class="navigation-buttons mt-4 pt-4 border-top d-flex justify-content-between">
                        <button type="button" id="prev-btn" class="btn btn-outline-secondary px-4 d-none">
                            <i class="bi bi-arrow-left me-2"></i>Anterior
                        </button>
                        <button type="button" id="next-btn" class="btn btn-primary px-5 ms-auto shadow-sm">
                            Siguiente <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                        <button type="button" id="submit-order-btn" class="btn btn-success px-5 ms-auto d-none shadow">
                            Confirmar Orden <i class="bi bi-check-lg ms-2"></i>
                        </button>
                    </div>
                </form>
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
                    <input type="hidden" id="benef-pais-id" name="paisID">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="benef-alias" class="form-label">Alias (Nombre corto)</label>
                            <input type="text" class="form-control" id="benef-alias" name="alias" required
                                placeholder="Ej: Mamá Banesco">
                        </div>
                    </div>
                    <h6 class="text-primary mt-3"><i class="bi bi-person-vcard me-2"></i>Datos del Titular</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Primer Nombre</label>
                            <input type="text" class="form-control" name="primerNombre" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-nombre">
                            <label class="form-label">Segundo Nombre</label>
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
                            <label class="form-label">Primer Apellido</label>
                            <input type="text" class="form-control" name="primerApellido" required>
                        </div>
                        <div class="col-md-6 mb-3" id="container-benef-segundo-apellido">
                            <label class="form-label">Segundo Apellido</label>
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
                                </select>
                                <input type="text" class="form-control" id="benef-doc-number" name="numeroDocumento"
                                    required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-primary"><i class="bi bi-bank me-2"></i>Datos Bancarios</h6>
                    <div class="mb-3 d-none" id="container-bank-select">
                        <label for="benef-bank-select" class="form-label">Banco / Billetera</label>
                        <select class="form-select" id="benef-bank-select" name="nombreBancoSelect">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="container-bank-input-text">
                        <label for="benef-bank" class="form-label">Nombre del Banco</label>
                        <input type="text" class="form-control" id="benef-bank" name="nombreBanco"
                            placeholder="Ej: Banesco, Mercantil...">
                    </div>
                    <div class="mb-3 d-none" id="other-bank-container">
                        <label class="form-label small text-muted">Escribe el nombre del Banco</label>
                        <input type="text" class="form-control" id="benef-bank-other" maxlength="20"
                            placeholder="Ej: Pichincha">
                    </div>
                    <div class="card bg-light border-0 p-3 mb-3" id="card-account-details">
                        <div class="form-check form-switch mb-2" id="wrapper-check-bank">
                            <input class="form-check-input" type="checkbox" id="check-include-bank"
                                name="incluirCuentaBancaria" checked>
                            <label class="form-check-label fw-bold" for="check-include-bank">Registrar Cuenta
                                Bancaria</label>
                        </div>
                        <div id="container-bank-input" class="mb-3 ps-4">
                            <label class="form-label small" id="label-account-num">Número de Cuenta</label>
                            <input type="text" class="form-control" id="benef-account-num" name="numeroCuenta"
                                maxlength="20" placeholder="Número de cuenta">
                            <div class="mt-2 d-none" id="container-cci">
                                <label class="form-label fw-bold text-primary small">Número de Cuenta Interbancaria
                                    (CCI)</label>
                                <input type="text" class="form-control" id="benef-cci" name="cci" maxlength="20"
                                    placeholder="20 dígitos">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-2" id="wrapper-check-mobile">
                            <input class="form-check-input" type="checkbox" id="check-include-mobile"
                                name="incluirPagoMovil">
                            <label class="form-check-label fw-bold" for="check-include-mobile">Registrar Pago Móvil /
                                Billetera</label>
                        </div>
                        <div id="container-mobile-input" class="mb-3 ps-4 d-none">
                            <label class="form-label small" id="label-wallet-phone">Número de Celular</label>
                            <div class="input-group">
                                <select class="input-group-text" id="benef-phone-code" name="phoneCode"
                                    style="max-width: 130px;"></select>
                                <span class="input-group-text d-none" id="wallet-phone-prefix"></span>
                                <input type="tel" class="form-control" id="benef-phone-number" name="phoneNumber"
                                    placeholder="Ej: 987654321">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary px-4" form="add-beneficiary-form">Guardar Todo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTasaNoDisponible" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Ruta No Disponible</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="lead mb-2">Lo sentimos, no hay tasa de cambio activa para esta ruta.</p>
                <p class="text-muted small">Por el momento no podemos procesar envíos entre los países seleccionados.
                    Por favor intenta con otra combinación o vuelve más tarde.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const nextBtn = document.getElementById('next-btn');
        const tasaInput = document.getElementById('selected-tasa-id');
        const modalTasa = new bootstrap.Modal(document.getElementById('modalTasaNoDisponible'));
        nextBtn.addEventListener('click', (e) => {
            const pasoActual = document.querySelector('.form-step.active');
            if (pasoActual && pasoActual.id === 'step-1') {
                const tasaId = tasaInput.value;
                if (!tasaId || tasaId == '0' || tasaId === '') {
                    e.preventDefault();
                    e.stopPropagation();
                    modalTasa.show();
                }
            }
        }, true);
    });
</script>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>