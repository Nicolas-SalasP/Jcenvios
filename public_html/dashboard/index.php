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

$estadosBloqueados = ['No Verificado', 'Rechazado'];
if (!isset($_SESSION['verification_status']) || in_array($_SESSION['verification_status'], $estadosBloqueados)) {
    header('Location: ' . BASE_URL . '/dashboard/verificar.php');
    exit();
}

$pageTitle = 'Realizar Transacción';
$pageScript = 'dashboard.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-lg-10 offset-lg-1 col-xl-8 offset-xl-2">
            <div class="card p-4 p-md-5 shadow-sm">
                <form id="remittance-form" novalidate>
                    <input type="hidden" id="user-id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                    <input type="hidden" id="selected-tasa-id">
                    <input type="hidden" id="selected-cuenta-id">

                    <div class="form-step active" id="step-1">
                        <h3 class="text-center mb-4">Paso 1: Selecciona la Ruta del Envío</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pais-origen" class="form-label">País de Origen</label>
                                <select id="pais-origen" class="form-select" required>
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pais-destino" class="form-label">País de Destino</label>
                                <select id="pais-destino" class="form-select" required>
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
                        <h3 class="text-center mb-4">Paso 2: Selecciona el Beneficiario</h3>
                        <div class="form-group">
                            <label class="form-label">Cuentas Guardadas</label>
                            <div id="beneficiary-list" class="list-group"></div>
                        </div>
                        <button type="button" id="add-account-btn" class="btn btn-success mt-3">+ Registrar Nueva
                            Cuenta</button>
                    </div>

                    <div class="form-step" id="step-3">
                        <h3 class="text-center mb-4">Paso 3: Ingresa el Monto</h3>

                        <div class="alert alert-light border border-info d-flex align-items-center mb-4" role="alert">
                            <i class="bi bi-info-circle-fill text-info me-2 fs-4"></i>
                            <div>
                                <span id="container-bcv-rate" class="d-none">
                                    <strong>Referencia BCV:</strong> <span id="bcv-rate-display">Cargando...</span><br>
                                </span>
                                <div class="small text-muted">Puedes ingresar el monto en cualquiera de los campos
                                    disponibles.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="monto-origen" class="form-label fw-bold" id="label-monto-origen">
                                    Tú envías (CLP)
                                </label>
                                <div class="input-group input-group-lg">
                                    <input type="text" inputmode="decimal" id="monto-origen" class="form-control"
                                        placeholder="0" required>
                                    <span class="input-group-text bg-light text-muted"
                                        id="currency-label-origen">CLP</span>
                                </div>
                                <div class="form-text text-end" id="tasa-comercial-display">Tasa: ...</div>
                            </div>

                            <div class="col-md-6" id="container-col-destino">
                                <label for="monto-destino" class="form-label fw-bold text-success">
                                    Beneficiario recibe
                                </label>
                                <div class="input-group">
                                    <input type="text" inputmode="decimal" id="monto-destino"
                                        class="form-control border-success" placeholder="0,00">
                                    <span class="input-group-text bg-success text-white"
                                        id="currency-label-destino">...</span>
                                </div>
                            </div>

                            <div class="col-md-6 d-none" id="container-monto-usd">
                                <label for="monto-usd" class="form-label fw-bold text-primary">Equivalente en Dólares
                                    (BCV)</label>
                                <div class="input-group">
                                    <input type="text" inputmode="decimal" id="monto-usd"
                                        class="form-control border-primary" placeholder="0,00">
                                    <span class="input-group-text bg-primary text-white">USD</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="forma-pago" class="form-label">¿Cómo nos transferirás?</label>
                            <select id="forma-pago" class="form-select" required>
                                <option value="">Cargando opciones...</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-step" id="step-4">
                        <h3 class="text-center mb-4">Paso 4: Resumen de la Orden</h3>
                        <div id="summary-container" class="mb-4"></div>
                        <div class="alert alert-info">Por favor, revisa que todos los datos sean correctos antes de
                            continuar.</div>
                    </div>

                    <div class="form-step" id="step-5">
                        <h3 class="text-center text-success">¡Orden Registrada!</h3>
                        <p class="text-center">Tu orden ha sido registrada con éxito con el ID: <strong
                                id="transaccion-id-final"></strong>. <br>Por favor, ve a tu <a
                                href="historial.php">historial de transacciones</a> para subir el comprobante de pago.
                        </p>
                    </div>

                    <div class="navigation-buttons mt-4 pt-4 border-top">
                        <button type="button" id="prev-btn" class="btn btn-secondary d-none">Anterior</button>
                        <button type="button" id="next-btn" class="btn btn-primary ms-auto">Siguiente</button>
                        <button type="button" id="submit-order-btn" class="btn btn-primary ms-auto d-none">Confirmar y
                            Generar Orden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
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
                <button type="submit" class="btn btn-primary px-4" form="add-beneficiary-form">Guardar Todo</button>
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