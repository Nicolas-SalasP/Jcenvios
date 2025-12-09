document.addEventListener('DOMContentLoaded', () => {
    // ==========================================
    // 1. SELECTORES DEL FLUJO DE TRANSACCIÓN
    // ==========================================
    const formSteps = document.querySelectorAll('.form-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-order-btn');

    const paisOrigenSelect = document.getElementById('pais-origen');
    const paisDestinoSelect = document.getElementById('pais-destino');
    const beneficiaryListDiv = document.getElementById('beneficiary-list');

    // --- NUEVOS INPUTS Y DISPLAYS ---
    const montoOrigenInput = document.getElementById('monto-origen'); // CLP
    const montoDestinoInput = document.getElementById('monto-destino'); // VES
    const montoUsdInput = document.getElementById('monto-usd'); // USD BCV (Referencia)

    const tasaComercialDisplay = document.getElementById('tasa-comercial-display');
    const bcvRateDisplay = document.getElementById('bcv-rate-display');
    const formaDePagoSelect = document.getElementById('forma-pago');

    const summaryContainer = document.getElementById('summary-container');
    const transaccionIdFinal = document.getElementById('transaccion-id-final');

    const userIdInput = document.getElementById('user-id');
    const selectedTasaIdInput = document.getElementById('selected-tasa-id');
    const selectedCuentaIdInput = document.getElementById('selected-cuenta-id');
    const stepperWrapper = document.querySelector('.stepper-wrapper');
    const stepperItems = document.querySelectorAll('.stepper-item');

    // Selectores Modal Beneficiario
    const addAccountBtn = document.getElementById('add-account-btn');
    const addAccountModalElement = document.getElementById('addAccountModal');
    const addBeneficiaryForm = document.getElementById('add-beneficiary-form');
    const benefPaisIdInput = document.getElementById('benef-pais-id');
    const phoneCodeSelect = document.getElementById('benef-phone-code');
    const phoneNumberInput = document.getElementById('benef-phone-number');
    const benefTipoSelect = document.getElementById('benef-tipo');
    const benefDocTypeSelect = document.getElementById('benef-doc-type');
    const benefDocNumberInput = document.getElementById('benef-doc-number');
    const benefDocPrefix = document.getElementById('benef-doc-prefix');
    const containerAccountNum = document.getElementById('container-account-number');
    const containerPhoneNum = document.getElementById('container-phone-number');
    const inputAccountNum = document.getElementById('benef-account-num');

    // ==========================================
    // 2. VARIABLES GLOBALES Y UTILIDADES
    // ==========================================
    let currentStep = 1;
    let commercialRate = 0; // Tasa Venta (CLP -> VES)
    let bcvRate = 0;        // Tasa Referencia (USD -> VES)
    let fetchRateTimer = null;
    let activeInputId = 'monto-origen'; // Trackea en qué input escribe el usuario
    let allDocumentTypes = [];
    const LOGGED_IN_USER_ID = userIdInput ? userIdInput.value : null;

    const numberFormatter = new Intl.NumberFormat('es-ES', { style: 'decimal', maximumFractionDigits: 2, minimumFractionDigits: 2 });

    const cleanNumber = (value) => {
        if (typeof value !== 'string' || !value) return '';
        // Elimina separadores de miles (.) y cambia coma (,) por punto (.)
        return value.replace(/\./g, '').replace(',', '.');
    };

    const formatCurrency = (val) => {
        if (isNaN(val)) return '';
        return numberFormatter.format(val);
    };

    const countryPhoneCodes = [
        { code: '+54', name: 'Argentina', flag: '🇦🇷', paisId: 7 },
        { code: '+591', name: 'Bolivia', flag: '🇧🇴', paisId: 8 },
        { code: '+55', name: 'Brasil', flag: '🇧🇷' },
        { code: '+56', name: 'Chile', flag: '🇨🇱', paisId: 1 },
        { code: '+57', name: 'Colombia', flag: '🇨🇴', paisId: 2 },
        { code: '+506', name: 'Costa Rica', flag: '🇨🇷' },
        { code: '+53', name: 'Cuba', flag: '🇨🇺' },
        { code: '+593', name: 'Ecuador', flag: '🇪🇨' },
        { code: '+503', name: 'El Salvador', flag: '🇸🇻' },
        { code: '+502', name: 'Guatemala', flag: '🇬🇹' },
        { code: '+504', name: 'Honduras', flag: '🇭🇳' },
        { code: '+52', name: 'México', flag: '🇲🇽' },
        { code: '+505', name: 'Nicaragua', flag: '🇳🇮' },
        { code: '+507', name: 'Panamá', flag: '🇵🇦' },
        { code: '+595', name: 'Paraguay', flag: '🇵🇾' },
        { code: '+51', name: 'Perú', flag: '🇵🇪', paisId: 4 },
        { code: '+1', name: 'Puerto Rico', flag: '🇵🇷' },
        { code: '+1', name: 'Rep. Dominicana', flag: '🇩🇴' },
        { code: '+598', name: 'Uruguay', flag: '🇺🇾' },
        { code: '+58', name: 'Venezuela', flag: '🇻🇪', paisId: 3 },
        { code: '+1', name: 'EE.UU.', flag: '🇺🇸', paisId: 5 }
    ];
    countryPhoneCodes.sort((a, b) => a.name.localeCompare(b.name));

    // ==========================================
    // 3. LÓGICA DE VISTA (STEPS)
    // ==========================================
    const updateView = () => {
        formSteps.forEach((step, index) => {
            step.classList.toggle('active', (index + 1) === currentStep);
        });
        prevBtn.classList.toggle('d-none', currentStep === 1 || currentStep === 5);
        nextBtn.classList.toggle('d-none', currentStep >= 4);
        if (submitBtn) submitBtn.classList.toggle('d-none', currentStep !== 4);

        if (stepperWrapper) stepperWrapper.classList.toggle('d-none', currentStep === 5);

        stepperItems.forEach((item, index) => {
            const step = index + 1;
            if (step < currentStep) {
                item.classList.add('completed'); item.classList.remove('active');
            } else if (step === currentStep) {
                item.classList.add('active'); item.classList.remove('completed');
            } else {
                item.classList.remove('active', 'completed');
            }
        });
    };

    // ==========================================
    // 4. LÓGICA DE CÁLCULO Y TASAS (CORE)
    // ==========================================

    const fetchRates = async () => {
        const origenID = paisOrigenSelect.value;
        const destinoID = paisDestinoSelect.value;

        if (!origenID || !destinoID) return;

        // 1. Obtener Tasa BCV
        try {
            const resBcv = await fetch('../api/?accion=getBcvRate');
            const dataBcv = await resBcv.json();
            if (dataBcv.success && dataBcv.rate > 0) {
                bcvRate = parseFloat(dataBcv.rate);
                bcvRateDisplay.textContent = `1 USD = ${formatCurrency(bcvRate)} VES`;
                bcvRateDisplay.classList.add('text-primary');
            } else {
                bcvRate = 0;
                bcvRateDisplay.textContent = 'No disponible';
            }
        } catch (e) { console.error("Error BCV", e); }

        // 2. Obtener Tasa Comercial (Tiered Pricing)
        // Usamos el monto en CLP (origen) como base para buscar el rango,
        // incluso si el usuario está escribiendo en otro campo, estimamos el origen.
        let estimatedMontoOrigen = parseFloat(cleanNumber(montoOrigenInput.value)) || 0;

        // Si el usuario escribe en Destino o USD, necesitamos una tasa base para buscar el rango
        // Hacemos una primera llamada con monto 0 para obtener la tasa base
        if (estimatedMontoOrigen === 0) {
            await performRateFetch(origenID, destinoID, 0);
        } else {
            await performRateFetch(origenID, destinoID, estimatedMontoOrigen);
        }

        // Ejecutamos cálculo inicial
        recalculateAll();
    };

    const performRateFetch = async (origenID, destinoID, monto) => {
        tasaComercialDisplay.textContent = 'Calculando tasa...';
        try {
            const response = await fetch(`../api/?accion=getTasa&origenID=${origenID}&destinoID=${destinoID}&montoOrigen=${monto}`);
            const data = await response.json();

            if (data && data.ValorTasa) {
                commercialRate = parseFloat(data.ValorTasa);
                selectedTasaIdInput.value = data.TasaID;

                const monedaDestino = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency || 'VES';
                tasaComercialDisplay.textContent = `Tasa Comercial: 1 CLP = ${commercialRate.toFixed(5)} ${monedaDestino}`;
                tasaComercialDisplay.className = 'form-text text-end fw-bold text-primary';
            } else {
                commercialRate = 0;
                selectedTasaIdInput.value = '';
                tasaComercialDisplay.textContent = 'Tasa no disponible para este monto.';
                tasaComercialDisplay.className = 'form-text text-end fw-bold text-danger';
            }
        } catch (e) {
            commercialRate = 0;
            tasaComercialDisplay.textContent = 'Error de conexión.';
        }
    };

    const recalculateAll = () => {
        if (commercialRate <= 0) return;

        let clp = 0, ves = 0, usd = 0;

        // Caso 1: Usuario escribe en CLP
        if (activeInputId === 'monto-origen') {
            clp = parseFloat(cleanNumber(montoOrigenInput.value)) || 0;
            ves = clp * commercialRate;
            if (bcvRate > 0) usd = ves / bcvRate;

            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = ves > 0 ? formatCurrency(ves) : '';
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = usd > 0 ? formatCurrency(usd) : '';
        }
        // Caso 2: Usuario escribe en VES (Inverso)
        else if (activeInputId === 'monto-destino') {
            ves = parseFloat(cleanNumber(montoDestinoInput.value)) || 0;
            // CLP = VES / TasaComercial (Redondeo hacia arriba solicitado: Math.ceil)
            clp = Math.ceil(ves / commercialRate);
            if (bcvRate > 0) usd = ves / bcvRate;

            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = clp > 0 ? formatCurrency(clp) : '';
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = usd > 0 ? formatCurrency(usd) : '';
        }
        // Caso 3: Usuario escribe en USD (BCV Referencia)
        else if (activeInputId === 'monto-usd') {
            usd = parseFloat(cleanNumber(montoUsdInput.value)) || 0;
            if (bcvRate > 0) {
                ves = usd * bcvRate;
                clp = Math.ceil(ves / commercialRate);
            }

            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = clp > 0 ? formatCurrency(clp) : '';
            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = ves > 0 ? formatCurrency(ves) : '';
        }
    };

    const handleInput = (e) => {
        activeInputId = e.target.id;

        // Debounce para no saturar la API buscando tasas por rango a cada tecla
        clearTimeout(fetchRateTimer);
        fetchRateTimer = setTimeout(() => {
            // Si el monto CLP cambió significativamente (incluso si se calculó indirectamente),
            // verificamos si eso cambia el rango de tasa (tier pricing)
            let currentClp = parseFloat(cleanNumber(montoOrigenInput.value)) || 0;
            const origenID = paisOrigenSelect.value;
            const destinoID = paisDestinoSelect.value;

            if (origenID && destinoID && currentClp > 0) {
                performRateFetch(origenID, destinoID, currentClp).then(() => {
                    recalculateAll(); // Recalcular con la nueva tasa posible (si cambió el rango)
                });
            }
        }, 500);

        // Cálculo inmediato visual (usando la tasa que ya tenemos cargada)
        recalculateAll();
    };

    // Listeners de Inputs
    montoOrigenInput.addEventListener('input', handleInput);
    montoDestinoInput.addEventListener('input', handleInput);
    montoUsdInput.addEventListener('input', handleInput);

    // ==========================================
    // 5. CARGA DE DATOS (API) - Paises, Beneficiarios, etc.
    // ==========================================

    const loadPaises = async (rol, selectElement) => {
        try {
            const response = await fetch(`../api/?accion=getPaises&rol=${rol}`);
            const paises = await response.json();
            selectElement.innerHTML = '<option value="">Selecciona un país</option>';
            paises.forEach(pais => {
                selectElement.innerHTML += `<option value="${pais.PaisID}" data-currency="${pais.CodigoMoneda}">${pais.NombrePais}</option>`;
            });
            if (rol === 'Origen') {
                selectElement.addEventListener('change', () => loadFormasDePago(selectElement.value));
            }
        } catch (error) { console.error('Error loadPaises', error); }
    };

    const loadFormasDePago = async (origenId) => {
        try {
            const res = await fetch(`../api/?accion=getFormasDePago&origenId=${origenId}`);
            const opts = await res.json();
            formaDePagoSelect.innerHTML = opts.length ? '<option value="">Selecciona...</option>' : '<option>Sin opciones</option>';
            opts.forEach(op => formaDePagoSelect.innerHTML += `<option value="${op}">${op}</option>`);
        } catch (e) { console.error(e); }
    };

    const loadBeneficiaries = async (paisID) => {
        beneficiaryListDiv.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> Cargando...';
        try {
            const res = await fetch(`../api/?accion=getCuentas&paisID=${paisID}`);
            const cuentas = await res.json();
            beneficiaryListDiv.innerHTML = '';
            if (cuentas.length > 0) {
                cuentas.forEach(c => {
                    let num = c.NumeroCuenta === 'PAGO MOVIL' ? c.NumeroTelefono : '...' + c.NumeroCuenta.slice(-4);
                    beneficiaryListDiv.innerHTML += `
                        <label class="list-group-item list-group-item-action d-flex align-items-center" style="cursor:pointer;">
                            <input type="radio" name="beneficiary-radio" value="${c.CuentaID}" class="form-check-input me-3">
                            <div><strong>${c.Alias}</strong> <small class="text-muted">(${c.NombreBanco} - ${num})</small></div>
                        </label>`;
                });
                document.querySelectorAll('input[name="beneficiary-radio"]').forEach(r => {
                    r.closest('label').addEventListener('click', () => r.checked = true);
                });
            } else {
                beneficiaryListDiv.innerHTML = '<div class="alert alert-warning">No tienes beneficiarios. Agrega uno.</div>';
            }
        } catch (e) { beneficiaryListDiv.innerHTML = 'Error al cargar.'; }
    };

    const loadTiposBeneficiario = async () => {
        if (!benefTipoSelect) return;
        try {
            const response = await fetch(`../api/?accion=getBeneficiaryTypes`);
            const tipos = await response.json();
            benefTipoSelect.innerHTML = '<option value="">Selecciona...</option>';
            tipos.forEach(t => benefTipoSelect.innerHTML += `<option value="${t}">${t}</option>`);
        } catch (e) { console.error(e); }
    };

    const loadTiposDocumento = async () => {
        if (!benefDocTypeSelect) return;
        try {
            const response = await fetch(`../api/?accion=getDocumentTypes`);
            const tipos = await response.json();
            allDocumentTypes = tipos;
        } catch (e) { console.error(e); }
    };

    // ==========================================
    // 6. EVENTOS DEL FLUJO
    // ==========================================
    nextBtn?.addEventListener('click', () => {
        if (currentStep === 1) {
            if (paisOrigenSelect.value && paisDestinoSelect.value && paisOrigenSelect.value !== paisDestinoSelect.value) {
                loadBeneficiaries(paisDestinoSelect.value);
                fetchRates(); // Cargar tasas al avanzar al paso 2 (para tenerlas listas en el 3)
                currentStep++;
            } else {
                window.showInfoModal('Atención', 'Selecciona países de origen y destino válidos.', false);
                return;
            }
        } else if (currentStep === 2) {
            if (document.querySelector('input[name="beneficiary-radio"]:checked')) {
                selectedCuentaIdInput.value = document.querySelector('input[name="beneficiary-radio"]:checked').value;
                fetchRates(); // Asegurar tasas frescas al entrar al paso 3
                currentStep++;
            } else {
                window.showInfoModal('Atención', 'Debes seleccionar un beneficiario.', false);
                return;
            }
        } else if (currentStep === 3) {
            const monto = parseFloat(cleanNumber(montoOrigenInput.value));
            if (monto > 0 && formaDePagoSelect.value && selectedTasaIdInput.value) {
                createSummary();
                currentStep++;
            } else {
                window.showInfoModal('Atención', 'Verifica el monto y la forma de pago.', false);
                return;
            }
        }
        updateView();
    });

    prevBtn?.addEventListener('click', () => {
        if (currentStep > 1) { currentStep--; updateView(); }
    });

    // Cambios en selects disparan recarga de tasas
    paisOrigenSelect?.addEventListener('change', () => {
        paisDestinoSelect.value = '';
        loadPaises('Destino', paisDestinoSelect);
        loadFormasDePago(paisOrigenSelect.value);
    });
    paisDestinoSelect?.addEventListener('change', () => {
        fetchRates();
        loadBeneficiaries(paisDestinoSelect.value);
    });

    const createSummary = () => {
        const origenTxt = paisOrigenSelect.options[paisOrigenSelect.selectedIndex].text;
        const destinoTxt = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].text;
        const monedaDest = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency;
        const formaPagoTxt = formaDePagoSelect.value;
        const usdVal = montoUsdInput.value || '0.00';

        // Obtener alias del beneficiario seleccionado
        let benefAlias = "Seleccionado";
        const selectedRadio = document.querySelector('input[name="beneficiary-radio"]:checked');
        if (selectedRadio) {
            const label = selectedRadio.closest('label');
            const strong = label.querySelector('strong');
            if (strong) benefAlias = strong.textContent;
        }

        summaryContainer.innerHTML = `
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Origen:</span> <strong>${origenTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Destino:</span> <strong>${destinoTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Beneficiario:</span> <strong>${benefAlias}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Forma de Pago:</span> <strong>${formaPagoTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Enviar:</span> <strong class="text-primary fs-5">${montoOrigenInput.value} CLP</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Recibir:</span> <strong class="text-success fs-5">${montoDestinoInput.value} ${monedaDest}</strong></li>
                <li class="list-group-item d-flex justify-content-between bg-light"><span>Ref. Dólar BCV:</span> <strong>${usdVal} USD</strong></li>
                <li class="list-group-item d-flex justify-content-between"><small>Tasa Aplicada:</small> <small>${commercialRate.toFixed(5)}</small></li>
            </ul>`;
    };

    submitBtn?.addEventListener('click', async () => {
        submitBtn.disabled = true; submitBtn.textContent = 'Procesando...';
        const data = {
            userID: LOGGED_IN_USER_ID,
            cuentaID: selectedCuentaIdInput.value,
            tasaID: selectedTasaIdInput.value,
            montoOrigen: cleanNumber(montoOrigenInput.value),
            monedaOrigen: 'CLP',
            montoDestino: cleanNumber(montoDestinoInput.value),
            monedaDestino: paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency,
            formaDePago: formaDePagoSelect.value
        };

        try {
            const res = await fetch('../api/?accion=createTransaccion', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                transaccionIdFinal.textContent = result.transaccionID;
                currentStep++; updateView();
            } else {
                window.showInfoModal('Error', result.error, false);
                submitBtn.disabled = false; submitBtn.textContent = 'Confirmar';
            }
        } catch (e) {
            window.showInfoModal('Error', 'Error de conexión.', false);
            submitBtn.disabled = false; submitBtn.textContent = 'Confirmar';
        }
    });

    // ==========================================
    // 9. MODAL AÑADIR BENEFICIARIO (Lógica Mantenida)
    // ==========================================
    let addAccountModalInstance = null;
    if (addAccountModalElement) {
        addAccountModalInstance = new bootstrap.Modal(addAccountModalElement);

        addAccountBtn?.addEventListener('click', () => {
            const paisDestinoID = paisDestinoSelect.value;
            if (!paisDestinoID) {
                window.showInfoModal('Atención', 'Selecciona un país de destino primero.', false);
                return;
            }
            benefPaisIdInput.innerHTML = '';
            const selectedOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
            if (selectedOption) {
                const option = document.createElement('option');
                option.value = selectedOption.value; option.text = selectedOption.text; option.selected = true;
                benefPaisIdInput.appendChild(option);
                benefPaisIdInput.value = paisDestinoID;
            }
            benefPaisIdInput.disabled = true;
            addBeneficiaryForm.reset();
            phoneCodeSelect.innerHTML = '<option>...</option>';
            countryPhoneCodes.forEach(c => phoneCodeSelect.innerHTML += `<option value="${c.code}">${c.flag} ${c.code}</option>`);

            document.getElementById('container-benef-segundo-nombre').classList.remove('d-none');
            document.getElementById('container-benef-segundo-apellido').classList.remove('d-none');

            updateDocumentTypesList();
            updatePaymentFields();
            addAccountModalInstance.show();
        });

        // Funciones auxiliares del modal (Visibilidad inputs, validaciones doc, etc.)
        const toggleInputVisibility = (toggleId, containerId, inputId, fieldName) => {
            const toggle = document.getElementById(toggleId);
            const container = document.getElementById(containerId);
            const input = document.getElementById(inputId);
            if (toggle && container && input) {
                toggle.checked = false; container.classList.remove('d-none'); input.required = true;
                toggle.addEventListener('change', async () => {
                    if (toggle.checked) {
                        if (await window.showConfirmModal('Confirmar', `¿Sin ${fieldName}?`)) {
                            container.classList.add('d-none'); input.required = false; input.value = '';
                        } else toggle.checked = false;
                    } else { container.classList.remove('d-none'); input.required = true; }
                });
            }
        };
        toggleInputVisibility('toggle-benef-segundo-nombre', 'container-benef-segundo-nombre', 'benef-secondname', 'segundo nombre');
        toggleInputVisibility('toggle-benef-segundo-apellido', 'container-benef-segundo-apellido', 'benef-secondlastname', 'segundo apellido');

        const updatePaymentFields = () => {
            if (!benefTipoSelect) return;
            const typeText = benefTipoSelect.options[benefTipoSelect.selectedIndex]?.text.toLowerCase() || '';
            const isMobile = typeText.includes('móvil') || typeText.includes('movil');
            if (isMobile) {
                containerAccountNum.classList.add('d-none'); inputAccountNum.required = false; inputAccountNum.value = 'PAGO MOVIL';
                containerPhoneNum.classList.remove('d-none'); phoneNumberInput.required = true; if (phoneCodeSelect) phoneCodeSelect.required = true;
            } else {
                containerAccountNum.classList.remove('d-none'); inputAccountNum.required = true; if (inputAccountNum.value === 'PAGO MOVIL') inputAccountNum.value = '';
                containerPhoneNum.classList.add('d-none'); phoneNumberInput.required = false; if (phoneCodeSelect) phoneCodeSelect.required = false;
            }
        };
        benefTipoSelect?.addEventListener('change', updatePaymentFields);

        const updateDocumentValidation = () => {
            if (!benefDocTypeSelect || !benefPaisIdInput) return;
            const paisId = parseInt(benefPaisIdInput.value);
            const docName = benefDocTypeSelect.options[benefDocTypeSelect.selectedIndex]?.text.toLowerCase() || '';
            benefDocPrefix.classList.add('d-none');
            benefDocNumberInput.value = benefDocNumberInput.value.replace(/[^0-9a-zA-Z]/g, '');
            if (paisId === 3) { // Venezuela
                benefDocPrefix.classList.remove('d-none');
                if (docName.includes('rif')) benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option><option value="J">J</option><option value="G">G</option>';
                else if (docName.includes('pasaporte')) benefDocPrefix.innerHTML = '<option value="P">P</option>';
                else benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option>';
            }
        };
        benefDocTypeSelect?.addEventListener('change', updateDocumentValidation);

        addBeneficiaryForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addBeneficiaryForm.closest('.modal-content').querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Guardando...';
            const formData = new FormData(addBeneficiaryForm);
            if (!benefDocPrefix.classList.contains('d-none')) formData.set('numeroDocumento', benefDocPrefix.value + formData.get('numeroDocumento'));
            if (containerPhoneNum.classList.contains('d-none')) formData.set('numeroTelefono', null);
            else formData.set('numeroTelefono', (formData.get('phoneCode') || '') + (formData.get('phoneNumber') || ''));
            formData.delete('phoneCode'); formData.delete('phoneNumber');
            if (containerAccountNum.classList.contains('d-none')) formData.set('numeroCuenta', 'PAGO MOVIL');

            const data = Object.fromEntries(formData.entries());
            data.paisID = benefPaisIdInput.value;

            try {
                const res = await fetch('../api/?accion=addCuenta', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    addAccountModalInstance.hide();
                    window.showInfoModal('Éxito', 'Cuenta guardada.', true);
                    loadBeneficiaries(paisDestinoSelect.value);
                } else window.showInfoModal('Error', result.error, false);
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            finally { btn.disabled = false; btn.textContent = 'Guardar Cuenta'; }
        });
    }

    // ==========================================
    // 10. INICIALIZACIÓN
    // ==========================================
    if (LOGGED_IN_USER_ID) {
        loadPaises('Origen', paisOrigenSelect);
        loadTiposBeneficiario();
        loadTiposDocumento();
        updateView();
        montoOrigenInput.readOnly = false; montoDestinoInput.readOnly = false; montoUsdInput.readOnly = false;
    }
});