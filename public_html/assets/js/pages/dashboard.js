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

    const montoOrigenInput = document.getElementById('monto-origen');
    const montoDestinoInput = document.getElementById('monto-destino');
    const tasaDisplayInput = document.getElementById('tasa-display');
    const currencyLabelDestino = document.getElementById('currency-label-destino');
    const swapCurrencyBtn = document.getElementById('swap-currency-btn');
    const formaDePagoSelect = document.getElementById('forma-pago');

    const summaryContainer = document.getElementById('summary-container');
    const transaccionIdFinal = document.getElementById('transaccion-id-final');

    const userIdInput = document.getElementById('user-id');
    const selectedTasaIdInput = document.getElementById('selected-tasa-id');
    const selectedCuentaIdInput = document.getElementById('selected-cuenta-id');
    const stepperWrapper = document.querySelector('.stepper-wrapper');
    const stepperItems = document.querySelectorAll('.stepper-item');

    // ==========================================
    // 2. SELECTORES DEL MODAL DE BENEFICIARIO (NUEVO)
    // ==========================================
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

    // Contenedores dinámicos del modal
    const containerAccountNum = document.getElementById('container-account-number');
    const containerPhoneNum = document.getElementById('container-phone-number');
    const inputAccountNum = document.getElementById('benef-account-num');

    // ==========================================
    // 3. VARIABLES GLOBALES Y UTILIDADES
    // ==========================================
    let currentStep = 1;
    let activeInput = 'origen';
    let currentRate = 0;
    let isCalculating = false;
    let fetchRateTimer = null;
    let allDocumentTypes = [];
    const LOGGED_IN_USER_ID = userIdInput ? userIdInput.value : null;

    const numberFormatter = new Intl.NumberFormat('es-ES', { style: 'decimal', maximumFractionDigits: 2, minimumFractionDigits: 2 });

    const cleanNumber = (value) => {
        if (typeof value !== 'string' || !value) return '';
        return value.replace(/\./g, '').replace(',', '.');
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
    // 4. LÓGICA DE VISTA (STEPS)
    // ==========================================
    const updateView = () => {
        formSteps.forEach((step, index) => {
            step.classList.toggle('active', (index + 1) === currentStep);
        });
        prevBtn.classList.toggle('d-none', currentStep === 1 || currentStep === 5);
        nextBtn.classList.toggle('d-none', currentStep >= 4);
        if (submitBtn) submitBtn.classList.toggle('d-none', currentStep !== 4);

        if (stepperWrapper) {
            stepperWrapper.classList.toggle('d-none', currentStep === 5);
        }
        stepperItems.forEach((item, index) => {
            const step = index + 1;
            if (step < currentStep) {
                item.classList.add('completed');
                item.classList.remove('active');
            } else if (step === currentStep) {
                item.classList.add('active');
                item.classList.remove('completed');
            } else {
                item.classList.remove('active', 'completed');
            }
        });
    };

    // ==========================================
    // 5. LÓGICA DEL MODAL DE BENEFICIARIO
    // ==========================================

    const toggleInputVisibility = (toggleId, containerId, inputId, fieldName) => {
        const toggle = document.getElementById(toggleId);
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);

        if (toggle && container && input) {
            toggle.checked = false;
            container.classList.remove('d-none');
            input.required = true;
            toggle.addEventListener('change', async (e) => {
                if (toggle.checked) {
                    const confirmed = await window.showConfirmModal('Confirmar Acción', `El beneficiario no tiene ${fieldName}, ¿está seguro?`);

                    if (confirmed) {
                        container.classList.add('d-none');
                        input.required = false;
                        input.value = '';
                    } else {
                        toggle.checked = false;
                    }
                } else {
                    container.classList.remove('d-none');
                    input.required = true;
                }
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
            containerAccountNum.classList.add('d-none');
            inputAccountNum.required = false;
            inputAccountNum.value = 'PAGO MOVIL';

            containerPhoneNum.classList.remove('d-none');
            phoneNumberInput.required = true;
            if (phoneCodeSelect) phoneCodeSelect.required = true;
        } else {
            containerAccountNum.classList.remove('d-none');
            inputAccountNum.required = true;
            if (inputAccountNum.value === 'PAGO MOVIL') inputAccountNum.value = '';

            containerPhoneNum.classList.add('d-none');
            phoneNumberInput.required = false;
            if (phoneCodeSelect) phoneCodeSelect.required = false;
        }
    };

    const updateDocumentValidation = () => {
        if (!benefDocTypeSelect || !benefPaisIdInput) return;

        const paisId = parseInt(benefPaisIdInput.value);
        const docTypeOption = benefDocTypeSelect.options[benefDocTypeSelect.selectedIndex];
        const docName = docTypeOption ? docTypeOption.text.toLowerCase() : '';
        const isVenezuela = (paisId === 3);

        benefDocPrefix.classList.add('d-none');
        benefDocNumberInput.value = benefDocNumberInput.value.replace(/[^0-9a-zA-Z]/g, '');
        benefDocNumberInput.maxLength = 20;
        benefDocNumberInput.oninput = null;

        if (isVenezuela) {
            if (docName.includes('cédula') || docName.includes('cedula')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 8;
                benefDocNumberInput.placeholder = '12345678';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9]/g, ''); };
            } else if (docName.includes('rif')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option><option value="J">J</option><option value="G">G</option>';
                benefDocNumberInput.maxLength = 9;
                benefDocNumberInput.placeholder = '123456789';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9]/g, ''); };
            } else if (docName.includes('pasaporte')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="P">P</option><option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 15;
                benefDocNumberInput.placeholder = 'Num Pasaporte';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^a-zA-Z0-9]/g, ''); };
            }
        } else {
            if (docName.includes('rut')) {
                benefDocNumberInput.maxLength = 12;
                benefDocNumberInput.placeholder = '12.345.678-9';
            } else {
                benefDocNumberInput.maxLength = 15;
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^a-zA-Z0-9]/g, ''); };
            }
        }
    };

    const updateDocumentTypesList = () => {
        const paisId = parseInt(benefPaisIdInput.value);
        const isVenezuela = (paisId === 3);

        benefDocTypeSelect.innerHTML = '<option value="">Selecciona...</option>';

        allDocumentTypes.forEach(doc => {
            const name = doc.nombre.toUpperCase();
            let show = true;

            if (isVenezuela) {
                if (name === 'RUT' || name === 'E-RUT' || name === 'DNI') show = false;
            } else {
                if (name === 'RIF') show = false;
            }

            if (show) {
                benefDocTypeSelect.innerHTML += `<option value="${doc.id}">${doc.nombre}</option>`;
            }
        });
        updateDocumentValidation();
    };

    if (benefTipoSelect) benefTipoSelect.addEventListener('change', updatePaymentFields);
    if (benefDocTypeSelect) benefDocTypeSelect.addEventListener('change', updateDocumentValidation);
    if (inputAccountNum) inputAccountNum.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 20);
    });

    const inputsNombres = ['benef-firstname', 'benef-secondname', 'benef-lastname', 'benef-secondlastname'];
    
    inputsNombres.forEach(id => {
        const el = document.getElementById(id);
        if(el) {
            el.maxLength = 12;
            el.addEventListener('input', function() {
                this.value = this.value.replace(/\s/g, '');
            });
        }
    });


    // ==========================================
    // 6. CARGA DE DATOS (API)
    // ==========================================

    const loadPaises = async (rol, selectElement) => {
        if (!selectElement) return;
        selectElement.disabled = true;
        selectElement.innerHTML = '<option value="">Cargando...</option>';
        try {
            const response = await fetch(`../api/?accion=getPaises&rol=${rol}`);
            if (!response.ok) throw new Error('Error al cargar países');
            const paises = await response.json();
            selectElement.innerHTML = '<option value="">Selecciona un país</option>';
            paises.forEach(pais => {
                selectElement.innerHTML += `<option value="${pais.PaisID}" data-currency="${pais.CodigoMoneda}">${pais.NombrePais}</option>`;
            });
            selectElement.disabled = false;

            if (rol === 'Origen') {
                if (selectElement.value) {
                    loadFormasDePago(selectElement.value);
                }
            }

        } catch (error) {
            console.error('Error loadPaises:', error);
            selectElement.innerHTML = '<option value="">Error al cargar</option>';
            selectElement.disabled = false;
        }
    };

    const loadBeneficiaries = async (paisID) => {
        if (!beneficiaryListDiv || !paisID) return;

        beneficiaryListDiv.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando beneficiarios...</div>';

        try {
            const targetPaisId = parseInt(paisID);
            const response = await fetch(`../api/?accion=getCuentas&paisID=${targetPaisId}`);
            if (!response.ok) throw new Error('Error al cargar beneficiarios');

            const cuentas = await response.json();
            beneficiaryListDiv.innerHTML = '';

            if (cuentas && cuentas.length > 0) {
                cuentas.forEach(cuenta => {
                    const label = document.createElement('label');
                    label.className = "list-group-item d-flex align-items-center list-group-item-action";
                    label.style.cursor = "pointer";

                    const radio = document.createElement('input');
                    radio.type = "radio";
                    radio.className = "form-check-input me-3";
                    radio.name = "beneficiary-radio";
                    radio.value = cuenta.CuentaID;

                    const infoDiv = document.createElement('div');
                    const alias = cuenta.Alias || 'Beneficiario sin alias';
                    const banco = cuenta.NombreBanco || 'Banco desconoc.';

                    let numero = cuenta.NumeroCuenta;
                    if (numero === 'PAGO MOVIL' || numero.length < 5) {
                        numero = cuenta.NumeroTelefono || 'Teléfono';
                    } else {
                        numero = '...' + numero.slice(-4);
                    }

                    infoDiv.innerHTML = `<strong>${alias}</strong> <small class="text-muted">(${banco} - ${numero})</small>`;

                    label.appendChild(radio);
                    label.appendChild(infoDiv);
                    beneficiaryListDiv.appendChild(label);

                    label.addEventListener('click', () => { radio.checked = true; });
                });
            } else {
                beneficiaryListDiv.innerHTML = `
                    <div class="alert alert-light border text-center" role="alert">
                        <i class="bi bi-info-circle text-muted display-6 d-block mb-2"></i>
                        No tienes beneficiarios registrados para este país.<br>
                        <small>Haz clic en <strong>+ Registrar Nueva Cuenta</strong> para agregar uno.</small>
                    </div>`;
            }
        } catch (error) {
            console.error('Error loadBeneficiaries:', error);
            beneficiaryListDiv.innerHTML = '<p class="text-danger text-center">Error al cargar los beneficiarios.</p>';
        }
    };

    const loadFormasDePago = async (origenId = 0) => {
        if (!formaDePagoSelect) return;
        formaDePagoSelect.disabled = true;
        formaDePagoSelect.innerHTML = '<option value="">Cargando...</option>';
        try {
            const response = await fetch(`../api/?accion=getFormasDePago&origenId=${origenId}`);
            if (!response.ok) throw new Error('Error al cargar formas de pago');
            const opciones = await response.json();

            if (opciones.length === 0) {
                formaDePagoSelect.innerHTML = '<option value="">No hay métodos disponibles</option>';
            } else {
                formaDePagoSelect.innerHTML = '<option value="">Selecciona una opción...</option>';
                opciones.forEach(opcion => {
                    formaDePagoSelect.innerHTML += `<option value="${opcion}">${opcion}</option>`;
                });
                formaDePagoSelect.disabled = false;
            }
        } catch (error) {
            console.error('Error loadFormasDePago:', error);
            formaDePagoSelect.innerHTML = '<option value="">Error al cargar</option>';
            formaDePagoSelect.disabled = false;
        }
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
    // 7. LÓGICA DE TASA Y CÁLCULO
    // ==========================================
    const fetchRate = async () => {
        const origenID = paisOrigenSelect.value;
        const destinoID = paisDestinoSelect.value;

        let montoOrigen = 0;
        if (activeInput === 'origen') {
            montoOrigen = parseFloat(cleanNumber(montoOrigenInput.value)) || 0;
        } else {
            const montoDestino = parseFloat(cleanNumber(montoDestinoInput.value)) || 0;
            if (currentRate > 0 && montoDestino > 0) {
                montoOrigen = montoDestino / currentRate;
            }
        }

        tasaDisplayInput.value = 'Calculando...';
        selectedTasaIdInput.value = '';

        if (!origenID || !destinoID) {
            tasaDisplayInput.value = 'Selecciona origen y destino';
            currentRate = 0;
            updateCalculation();
            return;
        }
        if (origenID === destinoID) {
            tasaDisplayInput.value = 'Origen y destino deben ser diferentes';
            currentRate = 0;
            updateCalculation();
            return;
        }

        try {
            const response = await fetch(`../api/?accion=getTasa&origenID=${origenID}&destinoID=${destinoID}&montoOrigen=${montoOrigen}`);
            if (!response.ok) {
                if (response.status === 404) {
                    tasaDisplayInput.value = 'Tasa no disponible para esta ruta.';
                } else {
                    tasaDisplayInput.value = 'Error al obtener tasa.';
                }
                currentRate = 0;
                selectedTasaIdInput.value = '';
            } else {
                const tasaInfo = await response.json();
                if (tasaInfo && typeof tasaInfo.ValorTasa !== 'undefined' && tasaInfo.TasaID) {
                    currentRate = parseFloat(tasaInfo.ValorTasa);
                    const selectedDestinoOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
                    const monedaDestino = selectedDestinoOption ? selectedDestinoOption.getAttribute('data-currency') : 'N/A';

                    tasaDisplayInput.value = `1 CLP ≈ ${currentRate.toFixed(5)} ${monedaDestino}`;
                    selectedTasaIdInput.value = tasaInfo.TasaID;
                } else {
                    tasaDisplayInput.value = 'Tasa no disponible.';
                    currentRate = 0;
                    selectedTasaIdInput.value = '';
                }
            }
        } catch (e) {
            console.error("Error fetchRate:", e);
            tasaDisplayInput.value = 'Error de red.';
            currentRate = 0;
            selectedTasaIdInput.value = '';
        }
        updateCalculation();
    };

    const updateCalculation = () => {
        if (isCalculating) return;
        isCalculating = true;
        let sourceInput = (activeInput === 'origen') ? montoOrigenInput : montoDestinoInput;
        let targetInput = (activeInput === 'origen') ? montoDestinoInput : montoOrigenInput;
        const sourceValue = parseFloat(cleanNumber(sourceInput.value)) || 0;
        let targetValue = 0;

        if (currentRate > 0 && sourceValue > 0) {
            targetValue = (activeInput === 'origen') ? sourceValue * currentRate : sourceValue / currentRate;
            targetInput.value = numberFormatter.format(targetValue);
        } else {
            targetInput.value = '';
        }
        setTimeout(() => { isCalculating = false; }, 50);
    };

    const handleAmountInput = () => {
        clearTimeout(fetchRateTimer);
        fetchRateTimer = setTimeout(() => { fetchRate(); }, 300);
    };

    const createSummary = () => {
        const origenText = paisOrigenSelect.options[paisOrigenSelect.selectedIndex]?.text || 'N/A';
        const destinoOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
        const destinoText = destinoOption?.text || 'N/A';
        const monedaDestinoText = destinoOption?.getAttribute('data-currency') || '';
        const formaPagoText = formaDePagoSelect.value || 'No seleccionada';
        const beneficiarioSeleccionado = document.querySelector('input[name="beneficiary-radio"]:checked');
        let beneficiarioAlias = 'No seleccionado';
        if (beneficiarioSeleccionado) {
            const label = beneficiarioSeleccionado.closest('label');
            if (label) {
                const strongTag = label.querySelector('strong');
                beneficiarioAlias = strongTag ? strongTag.textContent : label.textContent.trim();
            }
        }

        summaryContainer.innerHTML = `
            <h4 class="mb-3">Confirma tu Envío:</h4>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>País Origen:</span> <strong>${origenText}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>País Destino:</span> <strong>${destinoText}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Beneficiario:</span> <strong>${beneficiarioAlias}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Forma de Pago (Tuya):</span> <strong>${formaPagoText}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Enviar:</span> <strong class="text-danger">${montoOrigenInput.value || '0,00'} CLP</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Recibir (Aprox.):</span> <strong class="text-success">${montoDestinoInput.value || '0,00'} ${monedaDestinoText}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Tasa Aplicada:</span> <strong>${tasaDisplayInput.value || 'N/A'}</strong></li>
            </ul>`;
    };

    const submitTransaction = async () => {
        if (!submitBtn) return;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Procesando...';

        const selectedDestinoOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
        const monedaDestinoValue = selectedDestinoOption ? selectedDestinoOption.getAttribute('data-currency') : null;

        if (!monedaDestinoValue) {
            window.showInfoModal('Error', 'No se pudo determinar la moneda de destino.', false);
            submitBtn.disabled = false;
            return;
        }

        const transactionData = {
            userID: LOGGED_IN_USER_ID,
            cuentaID: selectedCuentaIdInput.value,
            tasaID: selectedTasaIdInput.value,
            montoOrigen: cleanNumber(montoOrigenInput.value),
            monedaOrigen: 'CLP',
            montoDestino: cleanNumber(montoDestinoInput.value),
            monedaDestino: monedaDestinoValue,
            formaDePago: formaDePagoSelect.value
        };

        try {
            const response = await fetch('../api/?accion=createTransaccion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(transactionData)
            });
            const result = await response.json();

            if (result.success) {
                transaccionIdFinal.textContent = result.transaccionID;
                currentStep++;
                updateView();
            } else {
                window.showInfoModal('Error', result.error || 'Error desconocido', false);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirmar y Generar Orden';
            }
        } catch (e) {
            window.showInfoModal('Error', 'Error de conexión', false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirmar y Generar Orden';
        }
    };

    // ==========================================
    // 8. EVENTOS DEL FLUJO (CON VALIDACIÓN MODAL)
    // ==========================================
    nextBtn?.addEventListener('click', async () => {
        let isValid = false;
        if (currentStep === 1) {
            if (paisOrigenSelect.value && paisDestinoSelect.value && paisOrigenSelect.value !== paisDestinoSelect.value) {
                try {
                    await loadBeneficiaries(paisDestinoSelect.value);
                    isValid = true;
                } catch (e) {
                    window.showInfoModal('Error', 'Error al cargar beneficiarios.', false);
                }
            } else if (paisOrigenSelect.value === paisDestinoSelect.value) {
                window.showInfoModal('Atención', 'El país de origen y destino no pueden ser iguales.', false);
            } else {
                window.showInfoModal('Atención', 'Selecciona países válidos.', false);
            }
        } else if (currentStep === 2) {
            const beneficiarioSeleccionado = document.querySelector('input[name="beneficiary-radio"]:checked');
            if (beneficiarioSeleccionado) {
                selectedCuentaIdInput.value = beneficiarioSeleccionado.value;
                isValid = true;
            } else {
                window.showInfoModal('Atención', 'Debes seleccionar un beneficiario.', false);
            }
        } else if (currentStep === 3) {
            const monto = parseFloat(cleanNumber(montoOrigenInput.value)) || 0;
            if (monto > 0 && formaDePagoSelect.value && currentRate > 0 && selectedTasaIdInput.value) {
                createSummary();
                isValid = true;
            } else {
                window.showInfoModal('Atención', 'Verifica el monto, la tasa y la forma de pago.', false);
            }
        }

        if (isValid && currentStep < 4) {
            currentStep++;
            updateView();
        }
    });

    prevBtn?.addEventListener('click', () => { if (currentStep > 1 && currentStep < 5) { currentStep--; updateView(); } });

    paisOrigenSelect?.addEventListener('change', () => {
        paisDestinoSelect.value = '';
        loadPaises('Destino', paisDestinoSelect);
        loadFormasDePago(paisOrigenSelect.value);
    });
    paisDestinoSelect?.addEventListener('change', () => { fetchRate(); loadBeneficiaries(paisDestinoSelect.value); });
    swapCurrencyBtn?.addEventListener('click', () => { activeInput = activeInput === 'origen' ? 'destino' : 'origen'; handleAmountInput(); });
    montoOrigenInput?.addEventListener('input', handleAmountInput);
    montoDestinoInput?.addEventListener('input', handleAmountInput);
    submitBtn?.addEventListener('click', submitTransaction);

    // ==========================================
    // 9. MODAL AÑADIR BENEFICIARIO
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

            // BLOQUEAR PAÍS EN EL MODAL
            benefPaisIdInput.innerHTML = '';
            const selectedOption = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
            if (selectedOption) {
                const option = document.createElement('option');
                option.value = selectedOption.value;
                option.text = selectedOption.text;
                option.selected = true;
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
            data.paisID = benefPaisIdInput.value; // Usar valor aunque esté disabled

            try {
                const res = await fetch('../api/?accion=addCuenta', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    addAccountModalInstance.hide();
                    window.showInfoModal('Éxito', 'Cuenta guardada.', true);
                    loadBeneficiaries(paisDestinoSelect.value);
                } else {
                    window.showInfoModal('Error', result.error, false);
                }
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

        montoOrigenInput.readOnly = false; montoDestinoInput.readOnly = true;
        montoOrigenInput.classList.remove('bg-light'); montoDestinoInput.classList.add('bg-light');
    }
});