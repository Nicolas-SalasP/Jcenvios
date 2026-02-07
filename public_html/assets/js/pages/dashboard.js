document.addEventListener('DOMContentLoaded', () => {
    // =========================================================
    // 1. CONFIGURACIÓN E IDs
    // =========================================================
    const C_COLOMBIA = 2;
    const C_VENEZUELA = 3;
    const C_PERU = 4;

    // =========================================================
    // 2. REFERENCIAS DOM GENERALES
    // =========================================================
    const formSteps = document.querySelectorAll('.form-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-order-btn');

    // Selectores
    const paisOrigenSelect = document.getElementById('pais-origen');
    const paisDestinoSelect = document.getElementById('pais-destino');
    const formaDePagoSelect = document.getElementById('forma-pago');
    const beneficiaryListDiv = document.getElementById('beneficiary-list');

    // Inputs y Display
    const montoOrigenInput = document.getElementById('monto-origen');
    const montoDestinoInput = document.getElementById('monto-destino');
    const montoUsdInput = document.getElementById('monto-usd');
    const tasaComercialDisplay = document.getElementById('tasa-comercial-display');
    const bcvRateDisplay = document.getElementById('bcv-rate-display');
    const containerMontoUsd = document.getElementById('container-monto-usd');
    const tasaPas1Container = document.getElementById('tasa-referencial-container');
    const tasaPas1Text = document.getElementById('tasa-referencial-paso1');
    const summaryContainer = document.getElementById('summary-container');
    const transaccionIdFinal = document.getElementById('transaccion-id-final');

    // Hidden
    const userIdInput = document.getElementById('user-id');
    const selectedTasaIdInput = document.getElementById('selected-tasa-id');
    const selectedCuentaIdInput = document.getElementById('selected-cuenta-id');
    const LOGGED_IN_USER_ID = userIdInput ? userIdInput.value : null;

    // Stepper
    const stepperWrapper = document.querySelector('.stepper-wrapper');
    const stepperItems = document.querySelectorAll('.stepper-item');

    // Variables de Estado
    let currentStep = 1;
    let commercialRate = 0;
    let bcvRate = 0;
    let fetchRateTimer = null;
    let activeInputId = 'monto-origen';
    let allDocumentTypes = [];
    let calculationMode = 'multiply';

    // --- VARIABLES DE CONTROL ---
    let isRiskyRoute = false;
    let isSubmitting = false; // Evita doble orden

    // =========================================================
    // 3. FUNCIONES DE CÁLCULO Y UTILIDADES
    // =========================================================

    // HELPER: Modal de Confirmación (Blindado)
    const confirmActionWithModal = (title, message) => {
        return new Promise((resolve) => {
            const modalEl = document.getElementById('confirmModal');

            // Fallback nativo si no existe el modal
            if (!modalEl) return resolve(confirm(message));

            const modal = new bootstrap.Modal(modalEl);
            const titleEl = document.getElementById('confirmModalTitle');
            const bodyEl = document.getElementById('confirmModalBody');

            if (titleEl) titleEl.textContent = title;
            if (bodyEl) bodyEl.innerText = message;

            // Buscar botones (soporte para ambos IDs por si acaso)
            const btnYes = document.getElementById('confirmModalYesBtn') || document.getElementById('confirmModalConfirmBtn');
            const btnCancel = document.getElementById('confirmModalCancelBtn');

            if (!btnYes) {
                console.error('Error: Botón confirmar no encontrado');
                return resolve(confirm(message));
            }

            const onYes = () => { cleanup(); modal.hide(); resolve(true); };
            const onCancel = () => { cleanup(); modal.hide(); resolve(false); };

            const cleanup = () => {
                btnYes.removeEventListener('click', onYes);
                if (btnCancel) btnCancel.removeEventListener('click', onCancel);
                modalEl.removeEventListener('hidden.bs.modal', onCancel);
            };

            btnYes.addEventListener('click', onYes);
            if (btnCancel) btnCancel.addEventListener('click', onCancel);
            modalEl.addEventListener('hidden.bs.modal', onCancel, { once: true });

            modal.show();
        });
    };

    const parseInput = (val, isUsd = false) => {
        if (!val) return 0;
        let s = val.toString().trim();
        if (isUsd) s = s.replace(',', '.');
        else { s = s.replace(/\./g, ''); s = s.replace(',', '.'); }
        return parseFloat(s) || 0;
    };

    const formatDisplay = (num, decimals = 2) => {
        if (isNaN(num) || num === 0) return '';
        return new Intl.NumberFormat('de-DE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num);
    };

    const applyLiveFormat = (input) => {
        let cursorPosition = input.selectionStart;
        let originalLength = input.value.length;
        const isUsdField = input.id.includes('usd');
        if (isUsdField) {
            input.value = input.value.replace(/[^0-9.,]/g, '');
        } else {
            let value = input.value.replace(/[^0-9,]/g, '');
            let parts = value.split(',');
            if (parts[0].length > 0) parts[0] = new Intl.NumberFormat('de-DE').format(parseInt(parts[0].replace(/\./g, '')));
            input.value = parts.join(parts.length > 1 ? ',' : '');
        }
        let newLength = input.value.length;
        input.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
    };

    const createSummary = () => {
        const origenTxt = paisOrigenSelect.options[paisOrigenSelect.selectedIndex]?.text || '';
        const monedaOrigen = paisOrigenSelect.options[paisOrigenSelect.selectedIndex]?.dataset.currency || 'CLP';
        const d = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
        const formaPagoTxt = formaDePagoSelect.value;
        const usdVal = montoUsdInput.value || '0.00';
        let benefAlias = "Seleccionado";
        let nombreBanco = "";

        const isVenezuela = (parseInt(paisDestinoSelect.value) === C_VENEZUELA);

        const selectedRadio = document.querySelector('input[name="beneficiary-radio"]:checked');
        if (selectedRadio) {
            const label = selectedRadio.closest('label');
            const strong = label.querySelector('strong');
            nombreBanco = selectedRadio.dataset.banco || "";
            if (strong) benefAlias = strong.textContent;
        }

        let warningHtml = "";
        if (isVenezuela && nombreBanco.toLowerCase().includes("venezuela")) {
            warningHtml = `
            <div class="alert alert-danger d-flex align-items-center mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2 fs-3"></i>
                <div><strong>Advertencia:</strong> Transferencias al Banco de Venezuela pueden presentar retrasos.</div>
            </div>`;
        }

        let lineaDolarBCV = '';
        if (isVenezuela) {
            lineaDolarBCV = `<li class="list-group-item d-flex justify-content-between bg-light"><span>Ref. Dólar BCV:</span> <strong>${usdVal} USD</strong></li>`;
        }

        summaryContainer.innerHTML = `
            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item d-flex justify-content-between"><span>Origen:</span> <strong>${origenTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Destino:</span> <strong>${d ? d.text : ''}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Beneficiario:</span> <strong>${benefAlias}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Forma de Pago:</span> <strong>${formaPagoTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Enviar:</span> <strong class="text-primary fs-5">${montoOrigenInput.value} ${monedaOrigen}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Recibir:</span> <strong class="text-success fs-5">${montoDestinoInput.value} ${d ? d.dataset.currency : ''}</strong></li>
                ${lineaDolarBCV}
                <li class="list-group-item d-flex justify-content-between"><small>Tasa Aplicada:</small> <small>${commercialRate.toFixed(5)}</small></li>
            </ul>
            ${warningHtml}`;
    };

    const toggleBcvFields = () => {
        const destinoID = parseInt(paisDestinoSelect.value || 0);
        const selectedOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
        const monedaDestino = selectedOption ? (selectedOption.dataset.currency || '---') : '---';
        const currencyLabel = document.getElementById('currency-label-destino');
        if (currencyLabel) currencyLabel.textContent = monedaDestino;

        const usdContainer = document.getElementById('container-monto-usd');
        const destContainer = document.getElementById('container-col-destino');
        const bcvRateContainer = document.getElementById('container-bcv-rate');

        if (usdContainer && destContainer) {
            if (destinoID === C_VENEZUELA) {
                usdContainer.classList.remove('d-none');
                destContainer.classList.remove('col-md-12');
                destContainer.classList.add('col-md-6');
                if (bcvRateContainer) bcvRateContainer.classList.remove('d-none');
            } else {
                usdContainer.classList.add('d-none');
                if (montoUsdInput) montoUsdInput.value = '';
                destContainer.classList.remove('col-md-6');
                destContainer.classList.add('col-md-12');
                if (bcvRateContainer) bcvRateContainer.classList.add('d-none');
            }
        }
    };

    const updateReferentialRateStep1 = async () => {
        const origen = paisOrigenSelect.value;
        const destino = paisDestinoSelect.value;
        if (origen && destino && origen !== destino) {
            tasaPas1Container?.classList.remove('d-none');
            if (tasaPas1Text) tasaPas1Text.textContent = "Obteniendo tasa...";
            try {
                const res = await fetch(`../api/?accion=getCurrentRate&origen=${origen}&destino=${destino}&monto=0`);
                const data = await res.json();
                if (data.success && data.tasa) {
                    const valor = parseFloat(data.tasa.ValorTasa).toFixed(5);
                    const moneda = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency || 'VES';
                    tasaPas1Text.innerHTML = `Tasa Referencial: 1 CLP = <strong>${valor} ${moneda}</strong>`;
                } else {
                    tasaPas1Text.textContent = "Tasa no disponible.";
                }
            } catch (e) { tasaPas1Text.textContent = "Error al cargar tasa."; }
        } else {
            tasaPas1Container?.classList.add('d-none');
        }
    };

    const performRateFetch = async (origenID, destinoID, monto) => {
        tasaComercialDisplay.textContent = 'Calculando tasa...';
        try {
            const respRate = await fetch(`../api/?accion=getCurrentRate&origen=${origenID}&destino=${destinoID}&monto=${monto}`);
            const dataRate = await respRate.json();
            if (dataRate.success && dataRate.tasa) {
                commercialRate = parseFloat(dataRate.tasa.ValorTasa);
                selectedTasaIdInput.value = dataRate.tasa.TasaID;
                calculationMode = dataRate.tasa.operation || 'multiply';

                // DETECCIÓN DE RIESGO
                isRiskyRoute = (parseInt(dataRate.tasa.EsRiesgoso) === 1);

                const monD = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency || 'VES';
                tasaComercialDisplay.textContent = `Tasa Comercial: 1 CLP = ${commercialRate.toFixed(5)} ${monD}`;
                tasaComercialDisplay.className = 'form-text text-end fw-bold text-primary';
            } else {
                commercialRate = 0;
                isRiskyRoute = false;
                selectedTasaIdInput.value = '';
                tasaComercialDisplay.textContent = dataRate.error || 'Tasa no disponible.';
                tasaComercialDisplay.className = 'form-text text-end fw-bold text-danger';
            }
        } catch (e) {
            commercialRate = 0;
            isRiskyRoute = false;
            tasaComercialDisplay.textContent = 'Error de conexión.';
        }
    };

    const recalculateAll = () => {
        if (commercialRate <= 0) return;
        let clp = 0, ves = 0, usd = 0;
        if (activeInputId === 'monto-origen') {
            clp = parseInput(montoOrigenInput.value, false);
            ves = (calculationMode === 'divide') ? (clp / commercialRate) : (clp * commercialRate);
            if (bcvRate > 0) usd = ves / bcvRate;
            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = formatDisplay(ves);
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = formatDisplay(usd);
        } else if (activeInputId === 'monto-destino') {
            ves = parseInput(montoDestinoInput.value, false);
            clp = (calculationMode === 'divide') ? (ves * commercialRate) : Math.ceil(ves / commercialRate);
            if (bcvRate > 0) usd = ves / bcvRate;
            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = formatDisplay(clp);
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = formatDisplay(usd);
        } else if (activeInputId === 'monto-usd') {
            usd = parseInput(montoUsdInput.value, true);
            if (bcvRate > 0) {
                ves = usd * bcvRate;
                clp = (calculationMode === 'divide') ? (ves * commercialRate) : Math.ceil(ves / commercialRate);
            }
            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = formatDisplay(clp);
            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = formatDisplay(ves);
        }
    };

    const fetchRates = async () => {
        const origenID = paisOrigenSelect.value;
        const destinoID = paisDestinoSelect.value;
        if (!origenID || !destinoID) return;

        if (parseInt(destinoID) === C_VENEZUELA) {
            try {
                const resBcv = await fetch('../api/?accion=getBcvRate');
                const dataBcv = await resBcv.json();
                if (dataBcv.success && dataBcv.rate > 0) {
                    bcvRate = parseFloat(dataBcv.rate);
                    bcvRateDisplay.textContent = `1 USD = ${formatDisplay(bcvRate)} VES`;
                } else { bcvRate = 0; bcvRateDisplay.textContent = 'No disponible'; }
            } catch (e) { console.error("Error BCV", e); }
        } else { bcvRate = 0; }

        let montoParaTasa = 0;
        if (activeInputId === 'monto-origen') montoParaTasa = parseInput(montoOrigenInput.value, false);
        else if (activeInputId === 'monto-destino') {
            let ves = parseInput(montoDestinoInput.value, false);
            montoParaTasa = commercialRate > 0 ? ((calculationMode === 'divide') ? ves * commercialRate : ves / commercialRate) : 0;
        } else if (activeInputId === 'monto-usd') {
            let usd = parseInput(montoUsdInput.value, true);
            montoParaTasa = (bcvRate > 0 && commercialRate > 0) ? ((calculationMode === 'divide') ? (usd * bcvRate * commercialRate) : (usd * bcvRate / commercialRate)) : 0;
        }

        if (montoParaTasa > 0 && montoParaTasa < 10) montoParaTasa = 0;
        await performRateFetch(origenID, destinoID, montoParaTasa);
        recalculateAll();
    };

    const handleInput = (e) => {
        activeInputId = e.target.id;
        applyLiveFormat(e.target);
        clearTimeout(fetchRateTimer);
        fetchRateTimer = setTimeout(fetchRates, 600);
        recalculateAll();
    };
    montoOrigenInput.addEventListener('input', handleInput);
    montoDestinoInput.addEventListener('input', handleInput);
    montoUsdInput.addEventListener('input', handleInput);

    // =========================================================
    // LÓGICA DE ENVÍO DE ORDEN (CORREGIDA)
    // =========================================================
    submitBtn?.addEventListener('click', async (e) => {
        e.preventDefault();

        if (isSubmitting) return;

        // Validar Horario
        if (!checkBusinessHours()) {
            const proceed = await confirmActionWithModal(
                'Aviso de Horario',
                'Estás operando fuera de nuestro horario laboral (Lun-Vie 10:30-20:00, Sáb 10:30-16:00). Tu orden será procesada el próximo día hábil. ¿Deseas continuar?'
            );
            if (!proceed) return;
        }

        // Validar Ruta Riesgosa
        if (isRiskyRoute) {
            const msgRiesgo = "Su orden requiere aprobación.\nCuando su orden sea aprobada podrá subir su comprobante y continuar el envío.\n\n¿Desea generar la orden bajo estas condiciones?";
            const aceptaRiesgo = await confirmActionWithModal('Atención: Ruta en Verificación', msgRiesgo);
            if (!aceptaRiesgo) return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Procesando...';

        const monedaOrigen = paisOrigenSelect.options[paisOrigenSelect.selectedIndex]?.dataset.currency || 'CLP';

        const data = {
            userID: LOGGED_IN_USER_ID,
            cuentaID: selectedCuentaIdInput.value,
            tasaID: selectedTasaIdInput.value,
            montoOrigen: parseInput(montoOrigenInput.value),
            monedaOrigen: monedaOrigen,
            montoDestino: parseInput(montoDestinoInput.value),
            monedaDestino: paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency,
            formaDePago: formaDePagoSelect.value
        };

        try {
            const resp = await fetch('../api/?accion=createTransaccion', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });

            const textResp = await resp.text();
            let res;
            try {
                res = JSON.parse(textResp);
            } catch (jsonErr) {
                console.error("Error parseando respuesta del servidor:", textResp);
                throw new Error("El servidor devolvió una respuesta inválida. Revisa la consola.");
            }

            if (res.success) {
                let finalId = res.transaccionID;
                if (typeof finalId === 'object' && finalId !== null) {
                    finalId = finalId.TransaccionID || finalId.id || JSON.stringify(finalId);
                }

                if (isRiskyRoute) {
                    transaccionIdFinal.textContent = finalId;
                    const divNormal = document.getElementById('msg-exito-normal');
                    const divRiesgo = document.getElementById('msg-exito-riesgo');
                    if (divNormal) divNormal.classList.add('d-none');
                    if (divRiesgo) divRiesgo.classList.remove('d-none');
                    currentStep++;
                    updateView();
                } else {
                    const wizardContainer = document.querySelector('.card') || document.getElementById('remittance-form').parentNode;

                    if (!wizardContainer) {
                        throw new Error("No se encontró el contenedor para mostrar el formulario de carga.");
                    }

                    const paisOrigenVal = parseInt(paisOrigenSelect.value);
                    const esChile = (paisOrigenVal === 1);
                    const displayRutStyle = esChile ? '' : 'd-none';
                    const requiredRut = esChile ? 'required' : '';
                    let htmlQR = '';
                    if (res.cuentaAdmin && res.cuentaAdmin.QrCodeURL) {
                        htmlQR = `
                            <div class="text-center mb-4 p-3 bg-white rounded border shadow-sm animate__animated animate__fadeInDown">
                                <h6 class="fw-bold text-primary mb-2">Escanea para pagar con ${res.cuentaAdmin.Banco}</h6>
                                <img src="../assets/img/qr/${res.cuentaAdmin.QrCodeURL}" alt="QR Pago" class="img-fluid border" style="max-height: 250px;">
                                <div class="small text-muted mt-2 fw-bold">${res.cuentaAdmin.Titular}</div>
                                <div class="small text-muted">${res.cuentaAdmin.NumeroCuenta}</div>
                            </div>
                        `;
                    }

                    wizardContainer.innerHTML = `
                        <div class="text-center animate__animated animate__fadeIn p-4">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="mb-3 text-success">¡Orden #${finalId} Creada!</h3>
                            
                            ${htmlQR}

                            <p class="text-muted mb-4">Para procesar tu envío rápidamente, por favor sube el comprobante ahora.</p>
                            
                            <div class="card bg-light border-0 p-4 mx-auto shadow-sm" style="max-width: 500px;">
                                <form id="form-comprobante-express">
                                    <input type="hidden" name="transaction_id" value="${finalId}">
                                    
                                    <div class="form-floating mb-3 text-start ${displayRutStyle}">
                                        <input type="text" class="form-control" id="rut_titular_pago" name="rut_titular" ${requiredRut} placeholder="Ej: 12.345.678-9">
                                        <label for="rut_titular_pago">RUT del Titular (Quien transfirió)</label>
                                    </div>

                                    <div class="form-floating mb-3 text-start">
                                        <input type="text" class="form-control" id="nombre_titular_pago" name="nombre_titular" required placeholder="Nombre Completo">
                                        <label for="nombre_titular_pago">Nombre del Titular (Quien transfirió)</label>
                                    </div>

                                    <div class="mb-3 text-start">
                                        <label class="form-label small fw-bold">Adjuntar Comprobante</label>
                                        <input class="form-control" type="file" id="comprobante_file" name="comprobante" accept="image/*,.pdf" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5" id="btn-subir-express">
                                        <i class="bi bi-cloud-upload-fill me-2"></i> Subir y Finalizar
                                    </button>
                                    
                                    <button type="button" class="btn btn-link text-muted mt-3 btn-sm" onclick="window.location.href='historial.php'">
                                        Lo subiré más tarde
                                    </button>
                                </form>
                            </div>
                        </div>
                    `;

                    if (typeof RutValidator !== 'undefined' && esChile) {
                        new RutValidator(document.getElementById('rut_titular_pago'));
                    }

                    document.getElementById('form-comprobante-express').addEventListener('submit', async function (ev) {
                        ev.preventDefault();
                        const btnUp = document.getElementById('btn-subir-express');
                        const originalTxt = btnUp.innerHTML;
                        btnUp.disabled = true;
                        btnUp.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';

                        const fd = new FormData(this);
                        if (!esChile) {
                            fd.set('rut_titular', 'N/A');
                        }

                        try {
                            const upResp = await fetch('../api/?accion=subirComprobanteDetallado', { method: 'POST', body: fd });
                            const upJson = await upResp.json();

                            if (upJson.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Comprobante Recibido!',
                                    text: 'Tu orden está siendo verificada.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => { window.location.href = 'historial.php'; });
                            } else {
                                throw new Error(upJson.message || 'Error al subir');
                            }
                        } catch (errUp) {
                            Swal.fire('Error', errUp.message, 'error');
                            btnUp.disabled = false;
                            btnUp.innerHTML = originalTxt;
                        }
                    });
                }
            } else {
                window.showInfoModal('Error', res.error, false);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirmar Orden';
                isSubmitting = false;
            }
        } catch (e) {
            console.error("Error CRÍTICO al crear orden:", e);
            window.showInfoModal('Error', 'Hubo un problema procesando la respuesta. Revisa la consola.', false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirmar Orden';
            isSubmitting = false;
        }
    });

    // =========================================================
    // 4. CARGA DE LISTAS (PAÍSES, BANCOS, DOCS)
    // =========================================================
    const filterDestinations = () => {
        const selectedOrigenValue = paisOrigenSelect.value;
        Array.from(paisDestinoSelect.options).forEach(opt => {
            if (opt.value === selectedOrigenValue) {
                opt.style.display = 'none';
                if (opt.selected) paisDestinoSelect.value = '';
            } else { opt.style.display = 'block'; }
        });
    };

    const loadFormasDePago = async (origenId) => {
        try {
            const respF = await fetch(`../api/?accion=getFormasDePago&origenId=${origenId}`);
            const opts = await respF.json();
            formaDePagoSelect.innerHTML = opts.length ? '<option value="">Selecciona...</option>' : '<option>Sin opciones</option>';
            opts.forEach(op => formaDePagoSelect.innerHTML += `<option value="${op}">${op}</option>`);
        } catch (e) { console.error(e); }
    };

    const loadPaises = async (rol, selectElement) => {
        try {
            const responseP = await fetch(`../api/?accion=getPaises&rol=${rol}`);
            const paises = await responseP.json();
            selectElement.innerHTML = '<option value="">Selecciona un país</option>';
            paises.forEach(pais => {
                selectElement.innerHTML += `<option value="${pais.PaisID}" data-currency="${pais.CodigoMoneda}">${pais.NombrePais}</option>`;
            });
            if (rol === 'Origen') {
                selectElement.addEventListener('change', () => {
                    filterDestinations();
                    loadFormasDePago(selectElement.value);
                    updateReferentialRateStep1();
                    const selectedOption = selectElement.options[selectElement.selectedIndex];
                    const monedaOrigen = selectedOption.dataset.currency || '---';
                    const spanOrigen = document.getElementById('currency-label-origen');
                    if (spanOrigen) spanOrigen.textContent = monedaOrigen;
                    const labelOrigen = document.getElementById('label-monto-origen');
                    if (labelOrigen) labelOrigen.textContent = `Tú envías (${monedaOrigen})`;
                });
            }
        } catch (error) { console.error('Error loadPaises', error); }
    };

    // =========================================================
    // loadBeneficiaries BLINDADA
    // =========================================================
    const loadBeneficiaries = async (paisID) => {
        beneficiaryListDiv.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> Cargando...';
        try {
            const respC = await fetch(`../api/?accion=getCuentas&paisID=${paisID}`);
            const cuentas = await respC.json();
            beneficiaryListDiv.innerHTML = '';

            if (cuentas.length > 0) {
                cuentas.forEach(c => {
                    let rawCuenta = c.NumeroCuenta || '';
                    let rawTelefono = c.NumeroTelefono || 'Sin N°';
                    let num;
                    if (rawCuenta === 'PAGO MOVIL' || (rawCuenta === '' && c.NumeroTelefono)) {
                        num = rawTelefono;
                    } else {
                        num = rawCuenta.length > 4 ? '...' + rawCuenta.slice(-4) : rawCuenta;
                    }

                    let bancoDisplay = c.NombreBanco || 'Banco';
                    if (c.CCI) bancoDisplay += ' (CCI Registrado)';

                    beneficiaryListDiv.innerHTML += `
                    <label class="list-group-item list-group-item-action d-flex align-items-center" style="cursor:pointer;">
                        <input type="radio" name="beneficiary-radio" value="${c.CuentaID}" 
                               data-banco="${c.NombreBanco || ''}" class="form-check-input me-3">
                        <div><strong>${c.Alias || 'Sin Alias'}</strong> <small class="text-muted">(${bancoDisplay} - ${num})</small></div>
                    </label>`;
                });

                document.querySelectorAll('input[name="beneficiary-radio"]').forEach(r => {
                    r.closest('label').addEventListener('click', () => r.checked = true);
                });
            } else {
                beneficiaryListDiv.innerHTML = '<div class="alert alert-warning">No tienes beneficiarios. Agrega uno.</div>';
            }
        } catch (e) {
            console.error("Error renderizando beneficiarios:", e);
            beneficiaryListDiv.innerHTML = '<div class="alert alert-danger">Error al cargar listado. Por favor recarga la página.</div>';
        }
    };

    // DOCUMENTOS
    const benefDocTypeSelect = document.getElementById('benef-doc-type');
    const benefDocNumberInput = document.getElementById('benef-doc-number');
    const benefDocPrefix = document.getElementById('benef-doc-prefix');
    const docTypeContainer = benefDocTypeSelect ? benefDocTypeSelect.closest('.row') : null;

    const updateDocumentValidation = () => {
        if (!benefDocTypeSelect || !paisDestinoSelect) return;
        const destId = parseInt(paisDestinoSelect.value);
        const isVenezuela = (destId === C_VENEZUELA);
        benefDocPrefix.classList.add('d-none');
        benefDocNumberInput.oninput = null;
        if (isVenezuela) {
            benefDocPrefix.classList.remove('d-none');
            benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9]/g, ''); };
        }
    };

    const loadTiposDocumento = async () => {
        if (!benefDocTypeSelect) return;
        try {
            if (allDocumentTypes.length === 0) {
                const responseD = await fetch(`../api/?accion=getDocumentTypes`);
                allDocumentTypes = await responseD.json();
            }
            benefDocTypeSelect.innerHTML = '<option value="">Seleccione...</option>';
            const destId = parseInt(paisDestinoSelect.value);
            const isVenezuela = (destId === C_VENEZUELA);
            const isColombia = (destId === C_COLOMBIA);
            const isPeru = (destId === C_PERU);

            // Orden Prioritario
            const sortOrder = ['RUT', 'Cédula', 'PPT', 'Pasaporte', 'RIF', 'DNI (Perú)', 'DNI', 'Carnet de Extranjería', 'E-RUT (RIF)', 'Otros'];

            allDocumentTypes.sort((a, b) => {
                let nameA = (a.NombreDocumento || a.nombre || "").toUpperCase();
                let nameB = (b.NombreDocumento || b.nombre || "").toUpperCase();
                if (nameA.includes("OTROS")) return 1; if (nameB.includes("OTROS")) return -1;
                let idxA = -1, idxB = -1;
                for (let i = 0; i < sortOrder.length; i++) if (nameA.includes(sortOrder[i].toUpperCase())) { idxA = i; break; }
                for (let i = 0; i < sortOrder.length; i++) if (nameB.includes(sortOrder[i].toUpperCase())) { idxB = i; break; }
                if (idxA === -1) idxA = 99; if (idxB === -1) idxB = 99;
                return idxA - idxB;
            });

            allDocumentTypes.forEach(doc => {
                const nombreDoc = doc.NombreDocumento || doc.nombre || "";
                if (!nombreDoc) return;
                const nameUC = nombreDoc.toUpperCase();
                let show = false;

                if (isVenezuela) {
                    if (nameUC.includes('CÉDULA') || nameUC.includes('CEDULA') || nameUC.includes('PASAPORTE') || nameUC.includes('RIF')) show = true;
                }
                else if (isColombia) {
                    // Cédula, Pasaporte, PPT, Otros
                    if (nameUC.includes('CÉDULA') || nameUC.includes('CEDULA') || nameUC.includes('PASAPORTE') || nameUC.includes('PPT') || nameUC.includes('OTROS')) show = true;
                }
                else if (isPeru) {
                    // DNI, Pasaporte, Carnet Ext., Otros
                    if (nameUC.includes('DNI') || nameUC.includes('PASAPORTE') || nameUC.includes('CARNET') || nameUC.includes('OTROS')) show = true;
                }
                else {
                    // Otros Países (Default) - MOSTRAR TODO, NO OCULTAR NADA
                    if (!nameUC.includes('RIF')) show = true;
                }

                if (show) benefDocTypeSelect.innerHTML += `<option value="${doc.TipoDocumentoID || doc.id}">${nombreDoc}</option>`;
            });
            updateDocumentValidation();
        } catch (e) { console.error(e); }
    };
    if (benefDocTypeSelect) benefDocTypeSelect.addEventListener('change', updateDocumentValidation);

    // =========================================================
    // 5. MODAL AGREGAR CUENTA
    // =========================================================
    const addAccountModalElement = document.getElementById('addAccountModal');
    let addAccountModalInstance = null;

    if (addAccountModalElement) {
        addAccountModalInstance = new bootstrap.Modal(addAccountModalElement);
        const addAccountBtn = document.getElementById('add-account-btn');
        const addBeneficiaryForm = document.getElementById('add-beneficiary-form');
        const benefPaisIdInput = document.getElementById('benef-pais-id');

        // Referencias
        const benefBankSelect = document.getElementById('benef-bank-select');
        const containerBankSelect = benefBankSelect ? benefBankSelect.closest('.mb-3') : null;
        const inputBankName = document.getElementById('benef-bank');
        const containerBankInputText = inputBankName ? inputBankName.closest('.mb-3') : null;
        const containerOtherBank = document.getElementById('other-bank-container');
        const inputOtherBank = document.getElementById('benef-bank-other');

        // Switches
        const cardOptions = document.getElementById('card-account-details') || document.querySelector('.card.bg-light');
        const checkBank = document.getElementById('check-include-bank');
        const wrapperCheckBank = checkBank ? checkBank.closest('.form-check') : null;
        const checkMobile = document.getElementById('check-include-mobile');
        const wrapperCheckMobile = checkMobile ? checkMobile.closest('.form-check') : null;

        // Inputs
        const containerBankFields = document.getElementById('container-bank-input');
        const containerMobileFields = document.getElementById('container-mobile-input');
        const inputAccount = document.getElementById('benef-account-num');
        const labelAccount = document.getElementById('label-account-num');
        const containerCCI = document.getElementById('container-cci');
        const inputCCI = document.getElementById('benef-cci');
        const inputPhone = document.getElementById('benef-phone-number');
        const labelWallet = document.getElementById('label-wallet-phone');
        const walletPhonePrefix = document.getElementById('wallet-phone-prefix');
        const phoneCodeSelect = document.getElementById('benef-phone-code');

        // --- SOLUCIÓN BOTÓN GUARDAR ---
        const footerSaveBtn = addAccountModalElement.querySelector('.modal-footer .btn-primary');
        if (footerSaveBtn) {
            footerSaveBtn.addEventListener('click', (e) => {
                if (footerSaveBtn.type === 'submit') return;
                e.preventDefault();
                if (addBeneficiaryForm.checkValidity()) {
                    addBeneficiaryForm.dispatchEvent(new Event('submit', { cancelable: true }));
                } else {
                    addBeneficiaryForm.reportValidity();
                }
            });
        }

        const configureModalForCountry = (paisId) => {
            // Reset Visual
            if (containerBankSelect) containerBankSelect.classList.add('d-none');
            if (containerBankInputText) containerBankInputText.classList.add('d-none');
            if (containerOtherBank) containerOtherBank.classList.add('d-none');
            if (containerCCI) containerCCI.classList.add('d-none');
            if (inputCCI) { inputCCI.value = ''; inputCCI.required = false; }
            if (cardOptions) cardOptions.classList.remove('d-none');

            // Mostrar documentos por defecto (REQUERIMIENTO: OTROS PAISES SE MUESTRA)
            if (docTypeContainer) docTypeContainer.classList.remove('d-none');
            if (benefDocTypeSelect) benefDocTypeSelect.required = true;
            if (benefDocNumberInput) benefDocNumberInput.required = true;

            // Reset Valores
            if (benefBankSelect) benefBankSelect.innerHTML = '<option value="">Seleccione...</option>';
            if (inputBankName) inputBankName.value = '';
            if (inputOtherBank) inputOtherBank.value = '';
            if (inputCCI) inputCCI.value = '';
            if (inputAccount) inputAccount.value = '';
            if (inputPhone) inputPhone.value = '';

            // Reset Validaciones
            inputAccount.maxLength = 50;
            inputAccount.placeholder = "Número de cuenta";
            if (inputPhone) {
                inputPhone.maxLength = 20;
                inputPhone.placeholder = "Número de celular";
            }

            // Reset Switches
            if (wrapperCheckBank) wrapperCheckBank.classList.remove('d-none');
            if (checkBank) { checkBank.disabled = false; checkBank.checked = false; }
            if (wrapperCheckMobile) wrapperCheckMobile.classList.remove('d-none');
            if (checkMobile) { checkMobile.disabled = false; checkMobile.checked = false; }

            // Reset Prefijo
            if (walletPhonePrefix) walletPhonePrefix.classList.add('d-none');
            if (phoneCodeSelect) phoneCodeSelect.style.display = 'block';

            // PERÚ (ID 4)
            if (paisId === C_PERU) {
                if (containerBankSelect) containerBankSelect.classList.remove('d-none');
                if (wrapperCheckBank) wrapperCheckBank.classList.add('d-none');
                if (wrapperCheckMobile) wrapperCheckMobile.classList.add('d-none');
                if (walletPhonePrefix) { walletPhonePrefix.textContent = '+51'; walletPhonePrefix.classList.remove('d-none'); }
                if (phoneCodeSelect) phoneCodeSelect.style.display = 'none';

                // Config Peru: 14 dígitos
                inputAccount.maxLength = 14;
                inputAccount.placeholder = "14 dígitos";

                const ops = [
                    { val: 'Interbank', text: 'Interbank (Mismo Banco)' },
                    { val: 'Otro Banco', text: 'Otro Banco (BCP, BBVA...)' },
                    { val: 'Yape', text: 'YAPE' },
                    { val: 'Plin', text: 'PLIN' }
                ];
                ops.forEach(o => benefBankSelect.add(new Option(o.text, o.val)));
            }
            // COLOMBIA (ID 2)
            else if (paisId === C_COLOMBIA) {
                if (containerBankSelect) containerBankSelect.classList.remove('d-none');
                if (wrapperCheckBank) wrapperCheckBank.classList.add('d-none');
                if (wrapperCheckMobile) wrapperCheckMobile.classList.add('d-none');
                if (walletPhonePrefix) { walletPhonePrefix.textContent = '+57'; walletPhonePrefix.classList.remove('d-none'); }
                if (phoneCodeSelect) phoneCodeSelect.style.display = 'none';

                // Config Colombia: 11 dígitos
                inputAccount.maxLength = 11;
                inputAccount.placeholder = "11 dígitos (Ahorros/Corriente)";

                const ops = [
                    { val: 'Bancolombia', text: 'Bancolombia' },
                    { val: 'Nequi', text: 'Nequi' }
                ];
                ops.forEach(o => benefBankSelect.add(new Option(o.text, o.val)));
            }
            // VENEZUELA (ID 3)
            else if (paisId === C_VENEZUELA) {
                if (containerBankInputText) containerBankInputText.classList.remove('d-none');
                if (walletPhonePrefix) {
                    walletPhonePrefix.classList.add('d-none');
                    walletPhonePrefix.textContent = '';
                }
                if (phoneCodeSelect) {
                    phoneCodeSelect.style.display = 'block';
                    phoneCodeSelect.innerHTML = '';
                    ['0412', '0414', '0416', '0424', '0426'].forEach(p => phoneCodeSelect.add(new Option(p, p)));
                }
                if (wrapperCheckMobile) {
                    wrapperCheckMobile.classList.remove('d-none');
                    const labelMobile = wrapperCheckMobile.querySelector('label');
                    if (labelMobile) labelMobile.textContent = 'Registrar Pago Móvil';
                }
                if (inputPhone) {
                    inputPhone.maxLength = 7;
                }

                if (checkBank) checkBank.checked = true;
                if (checkMobile) checkMobile.checked = false;
                inputAccount.maxLength = 20;
                inputAccount.placeholder = "20 dígitos exactos";

                updateInputState();
            }
            else {
                if (containerBankInputText) containerBankInputText.classList.remove('d-none');
                if (wrapperCheckMobile) wrapperCheckMobile.classList.add('d-none');
                if (checkMobile) checkMobile.checked = false;
                if (checkBank) { checkBank.checked = true; checkBank.disabled = true; }
                if (walletPhonePrefix) walletPhonePrefix.textContent = '+00';
                if (docTypeContainer) docTypeContainer.classList.remove('d-none');
                if (benefDocTypeSelect) benefDocTypeSelect.required = true;
                if (benefDocNumberInput) benefDocNumberInput.required = true;

                updateInputState();
            }
        };

        if (benefBankSelect) {
            benefBankSelect.addEventListener('change', function () {
                const val = this.value;
                const paisId = parseInt(benefPaisIdInput.value);
                if (containerOtherBank) containerOtherBank.classList.add('d-none');
                if (containerCCI) containerCCI.classList.add('d-none');
                if (inputCCI) inputCCI.required = false;
                if (containerBankFields) containerBankFields.classList.add('d-none');
                if (containerMobileFields) containerMobileFields.classList.add('d-none');
                if (checkBank) checkBank.checked = false;
                if (checkMobile) checkMobile.checked = false;
                inputAccount.maxLength = 50;
                if (!val) return;

                if (paisId === C_PERU) {
                    if (val === 'Yape' || val === 'Plin') {
                        if (containerMobileFields) containerMobileFields.classList.remove('d-none');
                        if (labelWallet) labelWallet.textContent = `Número de Celular (${val})`;
                        if (inputPhone) inputPhone.required = true;
                        if (inputAccount) inputAccount.required = false;
                        if (checkMobile) checkMobile.checked = true;
                    } else {
                        if (containerBankFields) containerBankFields.classList.remove('d-none');
                        if (inputAccount) inputAccount.required = true;
                        if (inputPhone) inputPhone.required = false;
                        if (checkBank) checkBank.checked = true;
                        inputAccount.maxLength = 14;
                        inputAccount.placeholder = "14 dígitos";
                        if (val === 'Interbank') { if (labelAccount) labelAccount.textContent = 'Número de Cuenta (Interbank)'; }
                        else if (val === 'Otro Banco') {
                            if (containerOtherBank) containerOtherBank.classList.remove('d-none');
                            if (inputOtherBank) inputOtherBank.required = true;
                            if (labelAccount) labelAccount.textContent = 'Número de Cuenta';
                            if (containerCCI) containerCCI.classList.remove('d-none');
                            if (inputCCI) inputCCI.required = true;
                        }
                    }
                } else if (paisId === C_COLOMBIA) {
                    if (val === 'Nequi' || val === 'DaviPlata') {
                        if (containerMobileFields) containerMobileFields.classList.remove('d-none');
                        if (labelWallet) labelWallet.textContent = `Celular (${val})`;
                        if (inputPhone) inputPhone.required = true;
                        if (inputAccount) inputAccount.required = false;
                        if (checkMobile) checkMobile.checked = true;
                    } else {
                        if (containerBankFields) containerBankFields.classList.remove('d-none');
                        if (labelAccount) labelAccount.textContent = 'Número de Cuenta / Ahorros';
                        if (inputAccount) inputAccount.required = true;
                        if (inputPhone) inputPhone.required = false;
                        if (checkBank) checkBank.checked = true;
                        inputAccount.maxLength = 11;
                        inputAccount.placeholder = "11 dígitos";
                    }
                }
            });
        }

        const updateInputState = () => {
            if (checkBank) {
                if (checkBank.checked) {
                    if (containerBankFields) containerBankFields.classList.remove('d-none');
                    if (inputAccount) inputAccount.required = true;
                    if (labelAccount) labelAccount.textContent = 'Número de Cuenta';
                } else {
                    if (containerBankFields) containerBankFields.classList.add('d-none');
                    if (inputAccount) inputAccount.required = false;
                }
            }
            if (checkMobile) {
                if (checkMobile.checked) {
                    if (containerMobileFields) containerMobileFields.classList.remove('d-none');
                    if (inputPhone) inputPhone.required = true;
                    if (labelWallet) labelWallet.textContent = 'Número de Celular';
                } else {
                    if (containerMobileFields) containerMobileFields.classList.add('d-none');
                    if (inputPhone) inputPhone.required = false;
                }
            }
        };
        if (checkBank) checkBank.addEventListener('change', updateInputState);
        if (checkMobile) checkMobile.addEventListener('change', updateInputState);

        // --- VALIDACIONES DE ENTRADA (SOLO NÚMEROS) ---
        [inputAccount, inputPhone, inputCCI, benefDocNumberInput].forEach(input => {
            if (input) {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '');
                });
            }
        });

        addAccountModalElement.addEventListener('show.bs.modal', (e) => {
            const paisDestinoId = parseInt(paisDestinoSelect.value);
            if (!paisDestinoId) {
                setTimeout(() => {
                    addAccountModalInstance.hide();
                    window.showInfoModal('Atención', 'Selecciona un país de destino primero.', false);
                }, 50);
                return;
            }
            benefPaisIdInput.value = paisDestinoId;
            addBeneficiaryForm.reset();
            loadTiposDocumento();
            configureModalForCountry(paisDestinoId);
            ['toggle-benef-segundo-nombre', 'toggle-benef-segundo-apellido'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.checked = false; el.dispatchEvent(new Event('change')); }
            });
        });

        addBeneficiaryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            let btn = addAccountModalElement.querySelector('.modal-footer .btn-primary') || addBeneficiaryForm.querySelector('button[type="submit"]');
            const originalText = btn ? btn.textContent : 'Guardando...';
            if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }

            const formData = new FormData(addBeneficiaryForm);
            const paisId = parseInt(benefPaisIdInput.value);
            const accNum = formData.get('numeroCuenta') || '';
            const phoneNum = formData.get('phoneNumber') || '';
            const isBank = (checkBank && checkBank.checked);
            const isMobile = (checkMobile && checkMobile.checked);

            // --- VALIDACIONES DE LONGITUD ---
            if (isBank) {
                if (paisId === C_VENEZUELA) {
                    if (accNum.length !== 20) {
                        window.showInfoModal('Error', 'Para Venezuela, el número de cuenta debe tener exactamente 20 dígitos.', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                } else if (paisId === C_COLOMBIA) {
                    if (accNum.length !== 11) {
                        window.showInfoModal('Error', 'Para cuentas en Colombia, debe tener 11 dígitos.', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                } else if (paisId === C_PERU) {
                    if (accNum.length !== 14) {
                        window.showInfoModal('Error', 'El número de cuenta en Perú debe tener 14 dígitos.', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                }
            }

            if (isMobile) {
                if (paisId === C_VENEZUELA) {
                    if (phoneNum.length !== 7) {
                        window.showInfoModal('Error', 'El teléfono debe tener los 7 dígitos restantes (sin el prefijo).', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                } else if (paisId === C_COLOMBIA) {
                    if (phoneNum.length !== 10) {
                        window.showInfoModal('Error', 'El celular Nequi debe tener 10 dígitos.', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                } else if (paisId === C_PERU) {
                    if (phoneNum.length !== 9) {
                        window.showInfoModal('Error', 'El celular Yape/Plin debe tener 9 dígitos.', false);
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }
                }
            }
            // Validacion CCI Peru
            if (paisId === C_PERU && inputCCI && inputCCI.value) {
                if (inputCCI.value.length !== 20) {
                    window.showInfoModal('Error', 'El CCI debe tener exactamente 20 dígitos.', false);
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                    return;
                }
            }

            // Nombre de banco manual para otros casos
            if (paisId === C_PERU || paisId === C_COLOMBIA) {
                if (benefBankSelect.value === 'Otro Banco' && inputOtherBank && inputOtherBank.value) {
                    formData.set('nombreBanco', inputOtherBank.value.trim());
                } else if (benefBankSelect.value) {
                    formData.set('nombreBanco', benefBankSelect.value);
                }
            }

            // Prefijo de documento (V, E, J...)
            if (benefDocPrefix && !benefDocPrefix.classList.contains('d-none')) {
                formData.set('numeroDocumento', benefDocPrefix.value + formData.get('numeroDocumento'));
            }

            // --- LÓGICA CRÍTICA DE TELÉFONO ---
            if (checkMobile.checked) {
                let finalCode = '';
                if (walletPhonePrefix && !walletPhonePrefix.classList.contains('d-none')) {
                    finalCode = walletPhonePrefix.textContent;
                }
                else if (phoneCodeSelect && phoneCodeSelect.style.display !== 'none') {
                    finalCode = phoneCodeSelect.value;
                }
                if (finalCode) formData.set('phoneCode', finalCode);

            } else {
                formData.set('phoneNumber', '');
                formData.set('numeroTelefono', '');
                formData.delete('phoneCode');
            }

            formData.set('incluirCuentaBancaria', (checkBank && checkBank.checked) ? '1' : '0');
            formData.set('incluirPagoMovil', (checkMobile && checkMobile.checked) ? '1' : '0');

            try {
                const res = await fetch('../api/?accion=addCuenta', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(formData.entries())) });
                const result = await res.json();
                if (result.success) {
                    addAccountModalInstance.hide();
                    window.showInfoModal('Éxito', 'Beneficiario guardado.', true);
                    loadBeneficiaries(paisDestinoSelect.value);
                } else { window.showInfoModal('Error', result.error, false); }
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            finally { if (btn) { btn.disabled = false; btn.textContent = originalText; } }
        });

        if (addAccountBtn) {
            addAccountBtn.addEventListener('click', () => {
                if (!parseInt(paisDestinoSelect.value)) {
                    window.showInfoModal('Atención', 'Selecciona un país de destino primero.', false);
                    return;
                }
                addAccountModalInstance.show();
            });
        }

        const setupToggle = (toggleId, containerId, inputId) => {
            const t = document.getElementById(toggleId), c = document.getElementById(containerId), i = document.getElementById(inputId);
            if (t && c && i) {
                t.addEventListener('change', async () => {
                    if (t.checked) {
                        if (await confirmActionWithModal('Confirmar', '¿Omitir este campo?')) { c.classList.add('d-none'); i.required = false; i.value = ''; }
                        else t.checked = false;
                    } else { c.classList.remove('d-none'); i.required = true; }
                });
            }
        };
        setupToggle('toggle-benef-segundo-nombre', 'container-benef-segundo-nombre', 'benef-secondname');
        setupToggle('toggle-benef-segundo-apellido', 'container-benef-segundo-apellido', 'benef-secondlastname');
    }

    // =========================================================
    // 7. STEPPER NAVEGACIÓN
    // =========================================================

    const checkBusinessHours = () => {
        const now = new Date();
        const chileTime = new Date(now.toLocaleString("en-US", { timeZone: "America/Santiago" }));
        const day = chileTime.getDay();
        const hour = chileTime.getHours();
        const minutes = chileTime.getMinutes();
        const totalMinutes = hour * 60 + minutes;

        const startMinutes = 10 * 60 + 30; // 10:30 AM (630 min)
        const endWeekday = 20 * 60;        // 20:00 PM (1200 min)
        const endSaturday = 16 * 60;       // 16:00 PM (960 min)

        if (day >= 1 && day <= 5) {
            return (totalMinutes >= startMinutes && totalMinutes < endWeekday);
        }
        if (day === 6) {
            return (totalMinutes >= startMinutes && totalMinutes < endSaturday);
        }
        return false;
    };

    const updateView = () => {
        formSteps.forEach((step, index) => { step.classList.toggle('active', (index + 1) === currentStep); });
        prevBtn.classList.toggle('d-none', currentStep === 1 || currentStep === 5);
        nextBtn.classList.toggle('d-none', currentStep >= 4);
        if (submitBtn) submitBtn.classList.toggle('d-none', currentStep !== 4);
        if (stepperWrapper) stepperWrapper.classList.toggle('d-none', currentStep === 5);
        stepperItems.forEach((item, index) => {
            const step = index + 1;
            if (step < currentStep) { item.classList.add('completed'); item.classList.remove('active'); }
            else if (step === currentStep) { item.classList.add('active'); item.classList.remove('completed'); }
            else { item.classList.remove('active', 'completed'); }
        });
    };

    nextBtn?.addEventListener('click', () => {
        if (currentStep === 1) {
            if (paisOrigenSelect.value && paisDestinoSelect.value && paisOrigenSelect.value !== paisDestinoSelect.value) {
                loadBeneficiaries(paisDestinoSelect.value); fetchRates(); updateReferentialRateStep1(); currentStep++;
            } else window.showInfoModal('Atención', 'Selecciona países válidos.', false);
        } else if (currentStep === 2) {
            const checked = document.querySelector('input[name="beneficiary-radio"]:checked');
            if (checked) { selectedCuentaIdInput.value = checked.value; fetchRates(); currentStep++; }
            else window.showInfoModal('Atención', 'Selecciona un beneficiario.', false);
        } else if (currentStep === 3) {
            if (parseInput(montoOrigenInput.value) > 0 && formaDePagoSelect.value) { createSummary(); currentStep++; }
            else window.showInfoModal('Atención', 'Verifica el monto.', false);
        }
        updateView();
    });

    prevBtn?.addEventListener('click', () => { if (currentStep > 1) { currentStep--; updateView(); } });

    paisDestinoSelect?.addEventListener('change', () => {
        updateReferentialRateStep1(); toggleBcvFields(); fetchRates(); loadBeneficiaries(paisDestinoSelect.value);
    });

    if (LOGGED_IN_USER_ID) {
        loadPaises('Origen', paisOrigenSelect); loadPaises('Destino', paisDestinoSelect);
        loadTiposDocumento(); updateView(); toggleBcvFields();
    }
});