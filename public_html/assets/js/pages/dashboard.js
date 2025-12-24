document.addEventListener('DOMContentLoaded', () => {
    // --- REFERENCIAS DOM ---
    const formSteps = document.querySelectorAll('.form-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-order-btn');
    const paisOrigenSelect = document.getElementById('pais-origen');
    const paisDestinoSelect = document.getElementById('pais-destino');
    const beneficiaryListDiv = document.getElementById('beneficiary-list');
    const montoOrigenInput = document.getElementById('monto-origen');
    const montoDestinoInput = document.getElementById('monto-destino');
    const montoUsdInput = document.getElementById('monto-usd');
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

    // Referencias Tasa Paso 1
    const tasaPas1Container = document.getElementById('tasa-referencial-container');
    const tasaPas1Text = document.getElementById('tasa-referencial-paso1');

    let currentStep = 1;
    let commercialRate = 0;
    let bcvRate = 0;
    let fetchRateTimer = null;
    let activeInputId = 'monto-origen';
    let allDocumentTypes = [];
    const LOGGED_IN_USER_ID = userIdInput ? userIdInput.value : null;

    // --- UTILIDADES DE FORMATEO ---
    const parseInput = (val, isUsd = false) => {
        if (!val) return 0;
        let s = val.toString().trim();
        if (isUsd) {
            s = s.replace(',', '.');
        } else {
            s = s.replace(/\./g, '');
            s = s.replace(',', '.');
        }
        return parseFloat(s) || 0;
    };

    const formatDisplay = (num, decimals = 2) => {
        if (isNaN(num) || num === 0) return '';
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    };

    const applyLiveFormat = (input) => {
        let cursorPosition = input.selectionStart;
        let originalLength = input.value.length;
        const isUsdField = input.id.includes('usd');
        if (isUsdField) {
            input.value = input.value.replace(/[^0-9.,]/g, '');
            let parts = input.value.split(/[.,]/);
            if (parts.length > 2) {
                let firstSeparator = input.value.match(/[.,]/)[0];
                input.value = parts[0] + firstSeparator + parts.slice(1).join('').replace(/[.,]/g, '');
            }
        } else {
            let value = input.value.replace(/[^0-9,]/g, '');
            let parts = value.split(',');
            if (parts[0].length > 0) {
                parts[0] = new Intl.NumberFormat('de-DE').format(parseInt(parts[0].replace(/\./g, '')));
            }
            input.value = parts.join(parts.length > 1 ? ',' : '');
        }
        let newLength = input.value.length;
        input.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
    };

    const countryPhoneCodes = [
        { code: '+54', name: 'Argentina', flag: '🇦🇷' },
        { code: '+591', name: 'Bolivia', flag: '🇧🇴' },
        { code: '+55', name: 'Brasil', flag: '🇧🇷' },
        { code: '+56', name: 'Chile', flag: '🇨🇱' },
        { code: '+57', name: 'Colombia', flag: '🇨🇴' },
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
        { code: '+51', name: 'Perú', flag: '🇵🇪' },
        { code: '+1', name: 'Puerto Rico', flag: '🇵🇷' },
        { code: '+1', name: 'Rep. Dominicana', flag: '🇩🇴' },
        { code: '+598', name: 'Uruguay', flag: '🇺🇾' },
        { code: '+58', name: 'Venezuela', flag: '🇻🇪' },
        { code: '+1', name: 'EE.UU.', flag: '🇺🇸' },
        { code: '+34', name: 'España', flag: '🇪🇸' }
    ];
    countryPhoneCodes.sort((a, b) => a.name.localeCompare(b.name));

    // --- LÓGICA DE NAVEGACIÓN ---
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

    // --- CÁLCULOS DE TASAS ---
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
                    tasaPas1Text.textContent = "Tasa no disponible para esta ruta.";
                }
            } catch (e) { tasaPas1Text.textContent = "Error al cargar tasa."; }
        } else {
            tasaPas1Container?.classList.add('d-none');
        }
    };

    const fetchRates = async () => {
        const origenID = paisOrigenSelect.value;
        const destinoID = paisDestinoSelect.value;
        if (!origenID || !destinoID) return;

        try {
            const resBcv = await fetch('../api/?accion=getBcvRate');
            const dataBcv = await resBcv.json();
            if (dataBcv.success && dataBcv.rate > 0) {
                bcvRate = parseFloat(dataBcv.rate);
                bcvRateDisplay.textContent = `1 USD = ${formatDisplay(bcvRate)} VES`;
                bcvRateDisplay.classList.add('text-primary');
            } else {
                bcvRate = 0;
                bcvRateDisplay.textContent = 'No disponible';
            }
        } catch (e) { console.error("Error BCV", e); }

        let montoParaTasa = 0;
        if (activeInputId === 'monto-origen') {
            montoParaTasa = parseInput(montoOrigenInput.value, false);
        } else if (activeInputId === 'monto-destino') {
            let ves = parseInput(montoDestinoInput.value, false);
            montoParaTasa = commercialRate > 0 ? (ves / commercialRate) : 0;
        } else if (activeInputId === 'monto-usd') {
            let usd = parseInput(montoUsdInput.value, true);
            montoParaTasa = (bcvRate > 0 && commercialRate > 0) ? (usd * bcvRate / commercialRate) : 0;
        }

        if (montoParaTasa > 0 && montoParaTasa < 10) montoParaTasa = 0;
        await performRateFetch(origenID, destinoID, montoParaTasa);
        recalculateAll();
    };

    const performRateFetch = async (origenID, destinoID, monto) => {
        tasaComercialDisplay.textContent = 'Calculando tasa...';
        try {
            const respRate = await fetch(`../api/?accion=getCurrentRate&origen=${origenID}&destino=${destinoID}&monto=${monto}`);
            const dataRate = await respRate.json();
            if (dataRate.success && dataRate.tasa) {
                commercialRate = parseFloat(dataRate.tasa.ValorTasa);
                selectedTasaIdInput.value = dataRate.tasa.TasaID;
                const monD = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency || 'VES';
                tasaComercialDisplay.textContent = `Tasa Comercial: 1 CLP = ${commercialRate.toFixed(5)} ${monD}`;
                tasaComercialDisplay.className = 'form-text text-end fw-bold text-primary';
            } else {
                commercialRate = 0;
                selectedTasaIdInput.value = '';
                tasaComercialDisplay.textContent = dataRate.error || 'Tasa no disponible.';
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
        if (activeInputId === 'monto-origen') {
            clp = parseInput(montoOrigenInput.value, false);
            ves = clp * commercialRate;
            if (bcvRate > 0) usd = ves / bcvRate;
            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = formatDisplay(ves);
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = formatDisplay(usd);
        }
        else if (activeInputId === 'monto-destino') {
            ves = parseInput(montoDestinoInput.value, false);
            clp = Math.ceil(ves / commercialRate);
            if (bcvRate > 0) usd = ves / bcvRate;
            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = formatDisplay(clp);
            if (document.activeElement !== montoUsdInput) montoUsdInput.value = formatDisplay(usd);
        }
        else if (activeInputId === 'monto-usd') {
            usd = parseInput(montoUsdInput.value, true);
            if (bcvRate > 0) {
                ves = usd * bcvRate;
                clp = Math.ceil(ves / commercialRate);
            }
            if (document.activeElement !== montoOrigenInput) montoOrigenInput.value = formatDisplay(clp);
            if (document.activeElement !== montoDestinoInput) montoDestinoInput.value = formatDisplay(ves);
        }
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

    // --- CARGA DE DATOS ---
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
                });
            }
        } catch (error) { console.error('Error loadPaises', error); }
    };

    const filterDestinations = () => {
        const selectedOrigenValue = paisOrigenSelect.value;
        Array.from(paisDestinoSelect.options).forEach(opt => {
            if (opt.value === selectedOrigenValue) {
                opt.style.display = 'none';
                if (opt.selected) paisDestinoSelect.value = '';
            } else {
                opt.style.display = 'block';
            }
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

    const loadBeneficiaries = async (paisID) => {
        beneficiaryListDiv.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> Cargando...';
        try {
            const respC = await fetch(`../api/?accion=getCuentas&paisID=${paisID}`);
            const cuentas = await respC.json();
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
            const responseT = await fetch(`../api/?accion=getBeneficiaryTypes`);
            const tipos = await responseT.json();
            benefTipoSelect.innerHTML = '<option value="">Selecciona...</option>';
            tipos.forEach(t => benefTipoSelect.innerHTML += `<option value="${t}">${t}</option>`);
        } catch (e) { console.error(e); }
    };

    const loadTiposDocumento = async () => {
        if (!benefDocTypeSelect) return;
        try {
            const responseD = await fetch(`../api/?accion=getDocumentTypes`);
            const tipos = await responseD.json();
            allDocumentTypes = tipos;
            // Ordenar por relevancia
            const sortOrder = ['RUT', 'Cédula', 'DNI (Perú)', 'Pasaporte', 'E-RUT (RIF)', 'Otros'];
            allDocumentTypes.sort((a, b) => {
                let nameA = a.NombreDocumento || a.nombre || "";
                let nameB = b.NombreDocumento || b.nombre || "";
                let idxA = sortOrder.indexOf(nameA);
                let idxB = sortOrder.indexOf(nameB);
                if (idxA === -1) idxA = 99;
                if (idxB === -1) idxB = 99;
                return idxA - idxB;
            });
        } catch (e) { console.error(e); }
    };

    // --- MANEJO DE PASOS ---
    nextBtn?.addEventListener('click', () => {
        if (currentStep === 1) {
            if (paisOrigenSelect.value && paisDestinoSelect.value && paisOrigenSelect.value !== paisDestinoSelect.value) {
                loadBeneficiaries(paisDestinoSelect.value);
                fetchRates();
                currentStep++;
            } else {
                window.showInfoModal('Atención', 'Selecciona países de origen y destino válidos.', false);
                return;
            }
        } else if (currentStep === 2) {
            const checked = document.querySelector('input[name="beneficiary-radio"]:checked');
            if (checked) {
                selectedCuentaIdInput.value = checked.value;
                fetchRates();
                currentStep++;
            } else {
                window.showInfoModal('Atención', 'Debes seleccionar un beneficiario.', false);
                return;
            }
        } else if (currentStep === 3) {
            const monto = parseInput(montoOrigenInput.value);
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

    prevBtn?.addEventListener('click', () => { if (currentStep > 1) { currentStep--; updateView(); } });

    paisDestinoSelect?.addEventListener('change', () => {
        updateReferentialRateStep1();
        fetchRates();
        loadBeneficiaries(paisDestinoSelect.value);
    });

    const createSummary = () => {
        const origenTxt = paisOrigenSelect.options[paisOrigenSelect.selectedIndex].text;
        const d = paisDestinoSelect.options[paisDestinoSelect.selectedIndex];
        const formaPagoTxt = formaDePagoSelect.value;
        const usdVal = montoUsdInput.value || '0.00';
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
                <li class="list-group-item d-flex justify-content-between"><span>Destino:</span> <strong>${d.text}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Beneficiario:</span> <strong>${benefAlias}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Forma de Pago:</span> <strong>${formaPagoTxt}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Enviar:</span> <strong class="text-primary fs-5">${montoOrigenInput.value} CLP</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Monto a Recibir:</span> <strong class="text-success fs-5">${montoDestinoInput.value} ${d.dataset.currency}</strong></li>
                <li class="list-group-item d-flex justify-content-between bg-light"><span>Ref. Dólar BCV:</span> <strong>${usdVal} USD</strong></li>
                <li class="list-group-item d-flex justify-content-between"><small>Tasa Aplicada:</small> <small>${commercialRate.toFixed(5)}</small></li>
            </ul>`;
    };

    const checkBusinessHours = () => {
        const now = new Date();
        const chileTime = new Date(now.toLocaleString("en-US", { timeZone: "America/Santiago" }));
        const day = chileTime.getDay();
        const hour = chileTime.getHours();
        const minutes = chileTime.getMinutes();
        const totalMinutes = hour * 60 + minutes;
        const startWeekday = 10 * 60 + 30;
        const endWeekday = 20 * 60;
        const startSat = 10 * 60 + 30;
        const endSat = 16 * 60;
        if (day >= 1 && day <= 5) return (totalMinutes >= startWeekday && totalMinutes < endWeekday);
        if (day === 6) return (totalMinutes >= startSat && totalMinutes < endSat);
        return false;
    };

    submitBtn?.addEventListener('click', async () => {
        if (!checkBusinessHours()) {
            const proceed = await window.showConfirmModal(
                'Aviso de Horario',
                'Estás operando fuera de nuestro horario laboral (Lun-Vie 10:30-20:00, Sáb 10:30-16:00). Tu orden será procesada el próximo día hábil. ¿Deseas continuar?'
            );
            if (!proceed) return;
        }
        submitBtn.disabled = true; submitBtn.textContent = 'Procesando...';
        const data = {
            userID: LOGGED_IN_USER_ID,
            cuentaID: selectedCuentaIdInput.value,
            tasaID: selectedTasaIdInput.value,
            montoOrigen: parseInput(montoOrigenInput.value),
            monedaOrigen: 'CLP',
            montoDestino: parseInput(montoDestinoInput.value),
            monedaDestino: paisDestinoSelect.options[paisDestinoSelect.selectedIndex].dataset.currency,
            formaDePago: formaDePagoSelect.value
        };
        try {
            const respTx = await fetch('../api/?accion=createTransaccion', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
            });
            const resultTx = await respTx.json();
            if (resultTx.success) {
                transaccionIdFinal.textContent = resultTx.transaccionID; currentStep++; updateView();
            } else {
                window.showInfoModal('Error', resultTx.error, false); submitBtn.disabled = false; submitBtn.textContent = 'Confirmar';
            }
        } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); submitBtn.disabled = false; }
    });

    // --- LÓGICA DE MODAL (CUENTA BENEFICIARIA) ---
    let addAccountModalInstance = null;
    if (addAccountModalElement) {
        addAccountModalInstance = new bootstrap.Modal(addAccountModalElement);

        addAccountBtn?.addEventListener('click', () => {
            const paisDestinoID = paisDestinoSelect.value;
            if (!paisDestinoID) { window.showInfoModal('Atención', 'Selecciona un país de destino primero.', false); return; }

            // Preparar modal
            benefPaisIdInput.value = paisDestinoID;
            addBeneficiaryForm.reset();
            phoneCodeSelect.innerHTML = countryPhoneCodes.map(c => `<option value="${c.code}">${c.flag} ${c.code}</option>`).join('');

            // Resetear visibilidad campos opcionales
            document.getElementById('container-benef-segundo-nombre').classList.remove('d-none');
            document.getElementById('container-benef-segundo-apellido').classList.remove('d-none');

            updateDocumentTypesList();
            updatePaymentFields();
            addAccountModalInstance.show();
        });

        const toggleInputVisibility = (toggleId, containerId, inputId, fieldName) => {
            const toggle = document.getElementById(toggleId), container = document.getElementById(containerId), input = document.getElementById(inputId);
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
                containerPhoneNum.classList.remove('d-none'); phoneNumberInput.required = true; phoneCodeSelect.required = true;
            } else {
                containerAccountNum.classList.remove('d-none'); inputAccountNum.required = true; if (inputAccountNum.value === 'PAGO MOVIL') inputAccountNum.value = '';
                containerPhoneNum.classList.add('d-none'); phoneNumberInput.required = false; phoneCodeSelect.required = false;
            }
        };
        benefTipoSelect?.addEventListener('change', updatePaymentFields);

        const updateDocumentTypesList = () => {
            if (!benefDocTypeSelect || !paisDestinoSelect) return;
            const isVenezuela = (parseInt(paisDestinoSelect.value) === 3);
            benefDocTypeSelect.innerHTML = '<option value="">Selecciona...</option>';

            allDocumentTypes.forEach(doc => {
                const nombreDoc = doc.NombreDocumento || doc.nombre || "";
                if (!nombreDoc) return;

                const nameUC = nombreDoc.toUpperCase();
                let show = isVenezuela ? !(nameUC.includes('RUT') || nameUC.includes('DNI')) : !nameUC.includes('RIF');

                if (show) {
                    benefDocTypeSelect.innerHTML += `<option value="${doc.TipoDocumentoID || doc.id}">${nombreDoc}</option>`;
                }
            });
            updateDocumentValidation();
        };

        const updateDocumentValidation = () => {
            if (!benefDocTypeSelect || !paisDestinoSelect) return;
            const isVenezuela = (parseInt(paisDestinoSelect.value) === 3);
            const docName = benefDocTypeSelect.options[benefDocTypeSelect.selectedIndex]?.text.toLowerCase() || '';
            benefDocPrefix.classList.add('d-none');
            benefDocNumberInput.oninput = null;
            if (isVenezuela) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = docName.includes('pasaporte') ? '<option value="P">P</option><option value="V">V</option><option value="E">E</option>' : '<option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9]/g, ''); };
            }
        };
        benefDocTypeSelect?.addEventListener('change', updateDocumentValidation);

        addBeneficiaryForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addBeneficiaryForm.closest('.modal-content').querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Guardando...';

            const formData = new FormData(addBeneficiaryForm);
            if (!benefDocPrefix.classList.contains('d-none')) {
                formData.set('numeroDocumento', benefDocPrefix.value + formData.get('numeroDocumento'));
            }
            if (!containerPhoneNum.classList.contains('d-none')) {
                formData.set('numeroTelefono', (formData.get('phoneCode') || '') + (formData.get('phoneNumber') || ''));
            } else {
                formData.set('numeroTelefono', null);
            }

            formData.delete('phoneCode');
            formData.delete('phoneNumber');
            const data = Object.fromEntries(formData.entries());
            data.paisID = paisDestinoSelect.value;

            try {
                const resAdd = await fetch('../api/?accion=addCuenta', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const resJ = await resAdd.json();
                if (resJ.success) {
                    addAccountModalInstance.hide();
                    window.showInfoModal('Éxito', 'Cuenta guardada correctamente.', true);
                    loadBeneficiaries(paisDestinoSelect.value);
                } else {
                    window.showInfoModal('Error', resJ.error, false);
                }
            } catch (err) {
                window.showInfoModal('Error', 'Error de conexión.', false);
            } finally {
                btn.disabled = false; btn.textContent = 'Guardar Cuenta';
            }
        });
    }

    // --- INICIALIZACIÓN ---
    if (LOGGED_IN_USER_ID) {
        loadPaises('Origen', paisOrigenSelect);
        loadPaises('Destino', paisDestinoSelect);
        loadTiposBeneficiario();
        loadTiposDocumento();
        updateView();
        montoOrigenInput.readOnly = false; montoDestinoInput.readOnly = false; montoUsdInput.readOnly = false;
    }
});