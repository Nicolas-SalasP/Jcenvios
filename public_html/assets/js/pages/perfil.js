document.addEventListener('DOMContentLoaded', () => {

    // --- REFERENCIAS DOM ---
    const profileForm = document.getElementById('profile-form');
    const profileLoading = document.getElementById('profile-loading');
    const profileImgPreview = document.getElementById('profile-img-preview');
    const profileFotoInput = document.getElementById('profile-foto-input');
    const profileSaveBtn = document.getElementById('profile-save-btn');
    const nombreCompletoEl = document.getElementById('profile-nombre');
    const emailEl = document.getElementById('profile-email');
    const documentoEl = document.getElementById('profile-documento');
    const telefonoEl = document.getElementById('profile-telefono');
    const profilePhoneCodeEl = document.getElementById('profile-phone-code');
    const estadoBadge = document.getElementById('profile-estado');
    const defaultPhoto = `${baseUrlJs}/assets/img/SoloLogoNegroSinFondo.png`;

    // Referencias Modal Beneficiario
    const beneficiariosLoading = document.getElementById('beneficiarios-loading');
    const beneficiaryListContainer = document.getElementById('beneficiary-list-container');
    const addAccountModalElement = document.getElementById('addAccountModal');
    const addAccountModal = new bootstrap.Modal(addAccountModalElement);
    const addBeneficiaryForm = document.getElementById('add-beneficiary-form');
    const addAccountModalLabel = document.getElementById('addAccountModalLabel');

    const benefCuentaIdInput = document.getElementById('benef-cuenta-id');
    const benefPaisIdInput = document.getElementById('benef-pais-id');
    const benefTipoSelect = document.getElementById('benef-tipo');
    const benefDocTypeSelect = document.getElementById('benef-doc-type');
    const benefDocNumberInput = document.getElementById('benef-doc-number');
    const benefDocPrefix = document.getElementById('benef-doc-prefix');

    // Contenedores Dinámicos
    const containerAccountNum = document.getElementById('container-account-number');
    const containerPhoneNum = document.getElementById('container-phone-number');
    const inputAccountNum = document.getElementById('benef-account-num');
    const inputPhoneNum = document.getElementById('benef-phone-number');
    const selectPhoneCode = document.getElementById('benef-phone-code');

    let allDocumentTypes = [];

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
                    const confirmed = await window.showConfirmModal(
                        'Confirmar Acción',
                        `El beneficiario no tiene ${fieldName}, ¿está seguro?`
                    );
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
        { code: '+39', name: 'Italia', flag: '🇮🇹' },
        { code: '+34', name: 'España', flag: '🇪🇸' },
        { code: '+351', name: 'Portugal', flag: '🇵🇹' },
        { code: '+33', name: 'Francia', flag: '🇫🇷' },
        { code: '+49', name: 'Alemania', flag: '🇩🇪' },
        { code: '+44', name: 'Reino Unido', flag: '🇬🇧' },
        { code: '+41', name: 'Suiza', flag: '🇨🇭' },
        { code: '+32', name: 'Bélgica', flag: '🇧🇪' },
        { code: '+31', name: 'Países Bajos', flag: '🇳🇱' }
    ];

    let currentBeneficiaries = [];
    let isSubmittingBeneficiary = false;

    const loadPhoneCodes = (selectElement) => {
        if (!selectElement) return;
        countryPhoneCodes.sort((a, b) => a.name.localeCompare(b.name));
        selectElement.innerHTML = '<option value="">Código...</option>';
        countryPhoneCodes.forEach(country => {
            if (country.code) {
                selectElement.innerHTML += `<option value="${country.code}">${country.flag} ${country.code}</option>`;
            }
        });
    };

    const setPhoneCodeByPais = (paisId, selectElement) => {
        if (!selectElement) return;
        const map = { "1": "+56", "3": "+58", "2": "+57", "5": "+51" };
        selectElement.value = map[paisId.toString()] || "";
    };

    const updateDocumentValidation = () => {
        const paisId = parseInt(benefPaisIdInput.value);
        const docTypeOption = benefDocTypeSelect.options[benefDocTypeSelect.selectedIndex];
        // FIX: Respaldo para evitar undefined antes de toLowerCase
        const docName = docTypeOption ? (docTypeOption.text || "").toLowerCase() : '';

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

            } else if (docName.includes('rif') || docName.includes('e-rut')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 9;
                benefDocNumberInput.placeholder = '123456789';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9]/g, ''); };

            } else if (docName.includes('pasaporte')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="P">P</option><option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 15;
                benefDocNumberInput.placeholder = 'Número Pasaporte';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^a-zA-Z0-9]/g, ''); };
            }
        } else {
            if (docName.includes('rut')) {
                benefDocNumberInput.maxLength = 12;
                benefDocNumberInput.placeholder = '12.345.678-9';
            } else {
                benefDocNumberInput.maxLength = 15;
                benefDocNumberInput.placeholder = 'Número Documento';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^a-zA-Z0-9]/g, ''); };
            }
        }
    };

    const enforceNameFormat = (inputId) => {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.maxLength = 12;
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\s/g, '');
        });
    };

    enforceNameFormat('benef-firstname');
    enforceNameFormat('benef-secondname');
    enforceNameFormat('benef-lastname');
    enforceNameFormat('benef-secondlastname');

    const updateDocumentTypesList = () => {
        const paisId = parseInt(benefPaisIdInput.value);
        const isVenezuela = (paisId === 3);

        benefDocTypeSelect.innerHTML = '<option value="">Selecciona...</option>';

        allDocumentTypes.forEach(doc => {
            // FIX: Uso de NombreDocumento para evitar TypeError
            const nombreDoc = doc.nombre || doc.NombreDocumento || "";
            const name = nombreDoc.toUpperCase();
            let show = true;

            if (isVenezuela) {
                if (name === 'RUT' || name === 'DNI') show = false;
            } else {
                if (name === 'RIF' || name === 'E-RUT (RIF)') show = false;
            }

            if (show) {
                benefDocTypeSelect.innerHTML += `<option value="${doc.id || doc.TipoDocumentoID}">${nombreDoc}</option>`;
            }
        });
        updateDocumentValidation();
    };

    const updatePaymentFields = () => {
        const typeText = benefTipoSelect.options[benefTipoSelect.selectedIndex]?.text.toLowerCase() || '';
        const isMobile = typeText.includes('móvil') || typeText.includes('movil');

        if (isMobile) {
            containerAccountNum.classList.add('d-none');
            inputAccountNum.required = false;
            inputAccountNum.value = 'PAGO MOVIL';
            containerPhoneNum.classList.remove('d-none');
            inputPhoneNum.required = true;
            inputPhoneNum.value = '';
            if (selectPhoneCode) selectPhoneCode.required = true;
        } else {
            containerAccountNum.classList.remove('d-none');
            inputAccountNum.required = true;
            if (inputAccountNum.value === 'PAGO MOVIL') inputAccountNum.value = '';

            containerPhoneNum.classList.add('d-none');
            inputPhoneNum.required = false;
            if (selectPhoneCode) selectPhoneCode.required = false;
        }
    };

    benefTipoSelect.addEventListener('change', updatePaymentFields);
    benefDocTypeSelect.addEventListener('change', updateDocumentValidation);

    benefPaisIdInput.addEventListener('change', () => {
        setPhoneCodeByPais(benefPaisIdInput.value, selectPhoneCode);
        updateDocumentTypesList();
    });

    if (inputAccountNum) {
        inputAccountNum.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 20);
        });
    }

    // --- LÓGICA DE PERFIL (FOTO Y EDICIÓN) ---

    // 1. Previsualización de Foto
    profileFotoInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                window.showInfoModal('Error', 'La imagen no debe superar los 2MB.', false);
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => profileImgPreview.src = e.target.result;
            reader.readAsDataURL(file);
        }
    });

    // 2. Guardar Cambios de Perfil
    profileForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        profileSaveBtn.disabled = true;
        const originalText = profileSaveBtn.innerHTML;
        profileSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

        // Usamos FormData para soportar la subida de la imagen
        const formData = new FormData(profileForm);
        const fullPhone = (profilePhoneCodeEl.value || '') + (telefonoEl.value || '');
        formData.set('telefono', fullPhone);

        try {
            // FIX: Se cambió 'updateProfile' por 'updateUserProfile' para coincidir con el Router de la API
            const response = await fetch('../api/?accion=updateUserProfile', {
                method: 'POST',
                body: formData 
            });
            const result = await response.json();
            if (result.success) {
                window.showInfoModal('Éxito', 'Perfil actualizado correctamente.', true);
                loadUserProfile();
            } else {
                window.showInfoModal('Error', result.error || 'No se pudo actualizar.', false);
            }
        } catch (error) {
            window.showInfoModal('Error', 'Error de conexión con el servidor.', false);
        } finally {
            profileSaveBtn.disabled = false;
            profileSaveBtn.innerHTML = originalText;
        }
    });


    const loadDropdownData = async (endpoint, selectElement, textKey = 'nombre', valueKey = '') => {
        const valKey = valueKey || textKey;
        selectElement.disabled = true;
        selectElement.innerHTML = '<option value="">Cargando...</option>';
        try {
            const response = await fetch(`../api/?accion=${endpoint}`);
            if (!response.ok) throw new Error(`Error al cargar ${endpoint}`);
            const data = await response.json();

            if (endpoint === 'getDocumentTypes') {
                allDocumentTypes = data;
            }

            selectElement.innerHTML = '<option value="">Selecciona...</option>';
            data.forEach(item => {
                let text, value;
                if (typeof item === 'object') {
                    // FIX: Soporte dinámico para NombreDocumento o NombreTipo
                    text = item[textKey] || item['NombreDocumento'] || item['NombreTipo'] || "";
                    value = item[valKey] || item['TipoDocumentoID'] || item['PaisID'] || "";
                } else {
                    text = item;
                    value = item;
                }
                selectElement.innerHTML += `<option value="${value}">${text}</option>`;
            });
            selectElement.disabled = false;
        } catch (error) {
            console.error(`Error en ${endpoint}:`, error);
            selectElement.innerHTML = '<option value="">Error al cargar</option>';
        }
    };

    const loadUserProfile = async () => {
        try {
            const response = await fetch('../api/?accion=getUserProfile');
            const result = await response.json();
            if (result.success && result.profile) {
                const p = result.profile;
                // Mostramos nombre y apellido unidos en el campo de solo lectura (o el que uses para visualización)
                nombreCompletoEl.value = `${p.PrimerNombre || ''} ${p.PrimerApellido || ''}`.trim();
                emailEl.value = p.Email;
                documentoEl.value = p.NumeroDocumento;

                loadPhoneCodes(profilePhoneCodeEl);
                const fullPhone = p.Telefono || '';
                const code = countryPhoneCodes.find(c => fullPhone.startsWith(c.code));
                if (code) {
                    profilePhoneCodeEl.value = code.code;
                    telefonoEl.value = fullPhone.substring(code.code.length);
                } else {
                    telefonoEl.value = fullPhone;
                }

                estadoBadge.textContent = p.VerificacionEstado;
                estadoBadge.className = 'badge';
                if (p.VerificacionEstado === 'Verificado') {
                    estadoBadge.classList.add('bg-success');
                } else {
                    estadoBadge.classList.add('bg-warning');
                }

                const photoUrl = p.FotoPerfilURL ? `${baseUrlJs}/admin/view_secure_file.php?file=${encodeURIComponent(p.FotoPerfilURL)}` : defaultPhoto;
                profileImgPreview.src = photoUrl;

                profileLoading.classList.add('d-none');
                profileForm.classList.remove('d-none');
            }
        } catch (e) { console.error(e); }
    };

    const loadBeneficiaries = async () => {
        try {
            beneficiariosLoading.classList.remove('d-none');
            beneficiaryListContainer.innerHTML = '';
            const res = await fetch(`../api/?accion=getCuentas`);
            const cuentas = await res.json();
            currentBeneficiaries = cuentas;

            if (cuentas.length > 0) {
                cuentas.forEach(c => {
                    let detalle = c.NumeroCuenta;
                    if (detalle === 'PAGO MOVIL' || detalle.length < 6) {
                        detalle = c.NumeroTelefono || 'Teléfono';
                    }

                    beneficiaryListContainer.innerHTML += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">${c.Alias} (${c.NombrePais})</h6>
                                <small class="text-muted">${c.NombreBanco} - ${detalle}</small>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary edit-benef-btn" data-id="${c.CuentaID}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger del-benef-btn" data-id="${c.CuentaID}"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>`;
                });
            } else {
                beneficiaryListContainer.innerHTML = '<p class="text-muted text-center p-3">No tienes beneficiarios.</p>';
            }
        } catch (e) { console.error(e); }
        finally { beneficiariosLoading.classList.add('d-none'); }
    };

    document.getElementById('add-account-btn').addEventListener('click', () => {
        addBeneficiaryForm.reset();
        addAccountModalLabel.textContent = 'Registrar Nuevo Beneficiario';
        benefCuentaIdInput.value = '';
        benefPaisIdInput.disabled = false;

        const containerSecName = document.getElementById('container-benef-segundo-nombre');
        const containerSecLast = document.getElementById('container-benef-segundo-apellido');
        if (containerSecName) containerSecName.classList.remove('d-none');
        if (containerSecLast) containerSecLast.classList.remove('d-none');

        updatePaymentFields();
    });

    addBeneficiaryForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isSubmittingBeneficiary) return;
        isSubmittingBeneficiary = true;

        const submitBtn = addBeneficiaryForm.closest('.modal-content').querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        const formData = new FormData(addBeneficiaryForm);

        // Reconstrucción del número de documento con prefijo si aplica
        if (!benefDocPrefix.classList.contains('d-none')) {
            const fullDoc = benefDocPrefix.value + formData.get('numeroDocumento');
            formData.set('numeroDocumento', fullDoc);
        }

        const data = Object.fromEntries(formData.entries());

        // Manejo del teléfono dinámico
        if (containerPhoneNum.classList.contains('d-none')) {
            data.numeroTelefono = null;
        } else {
            data.numeroTelefono = (data.phoneCode || '') + (data.phoneNumber || '');
        }
        delete data.phoneCode;
        delete data.phoneNumber;

        // Asegurar valor PAGO MOVIL si aplica
        if (containerAccountNum.classList.contains('d-none')) {
            data.numeroCuenta = 'PAGO MOVIL';
        }

        // FIX: Asegurar que el paisID se envíe incluso si el campo está disabled en el DOM
        if (benefPaisIdInput.disabled) {
            data.paisID = benefPaisIdInput.value;
        }

        const action = data.cuentaId ? 'updateBeneficiary' : 'addCuenta';
        
        try {
            const res = await fetch(`../api/?accion=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                addAccountModal.hide();
                window.showInfoModal('Éxito', 'Beneficiario guardado correctamente.', true);
                loadBeneficiaries();
            } else throw new Error(result.error);
        } catch (err) {
            window.showInfoModal('Error', err.message, false);
        } finally {
            submitBtn.disabled = false;
            isSubmittingBeneficiary = false;
        }
    });

    beneficiaryListContainer.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-benef-btn');
        const delBtn = e.target.closest('.del-benef-btn');

        if (editBtn) {
            const id = editBtn.dataset.id;
            try {
                const res = await fetch(`../api/?accion=getBeneficiaryDetails&id=${id}`);
                const r = await res.json();
                if (r.success) {
                    const d = r.details;
                    addBeneficiaryForm.reset();
                    addAccountModalLabel.textContent = 'Editar Beneficiario';
                    benefCuentaIdInput.value = d.CuentaID;
                    benefPaisIdInput.value = d.PaisID;
                    benefPaisIdInput.disabled = true;

                    setPhoneCodeByPais(d.PaisID, selectPhoneCode);
                    updateDocumentTypesList();

                    document.getElementById('benef-alias').value = d.Alias;
                    benefTipoSelect.value = d.TipoBeneficiarioNombre;
                    updatePaymentFields();

                    document.getElementById('benef-firstname').value = d.TitularPrimerNombre;
                    document.getElementById('benef-lastname').value = d.TitularPrimerApellido;

                    // Manejo de visibilidad de nombres opcionales en edición
                    const secNameInput = document.getElementById('benef-secondname');
                    if (d.TitularSegundoNombre) {
                        secNameInput.value = d.TitularSegundoNombre;
                        document.getElementById('container-benef-segundo-nombre').classList.remove('d-none');
                        document.getElementById('toggle-benef-segundo-nombre').checked = false;
                    } else {
                        document.getElementById('toggle-benef-segundo-nombre').checked = true;
                        document.getElementById('container-benef-segundo-nombre').classList.add('d-none');
                    }

                    const secLastInput = document.getElementById('benef-secondlastname');
                    if (d.TitularSegundoApellido) {
                        secLastInput.value = d.TitularSegundoApellido;
                        document.getElementById('container-benef-segundo-apellido').classList.remove('d-none');
                        document.getElementById('toggle-benef-segundo-apellido').checked = false;
                    } else {
                        document.getElementById('toggle-benef-segundo-apellido').checked = true;
                        document.getElementById('container-benef-segundo-apellido').classList.add('d-none');
                    }

                    document.getElementById('benef-bank').value = d.NombreBanco;
                    document.getElementById('benef-account-num').value = d.NumeroCuenta;

                    // Extracción de prefijo de documento (V/E/P...)
                    let docNum = d.TitularNumeroDocumento;
                    const firstChar = (docNum || "").charAt(0).toUpperCase();
                    if (['V', 'E', 'J', 'G', 'P'].includes(firstChar) && !benefDocPrefix.classList.contains('d-none')) {
                        benefDocPrefix.value = firstChar;
                        docNum = docNum.substring(1);
                    }
                    document.getElementById('benef-doc-number').value = docNum;

                    if (d.NumeroTelefono) {
                        const codeMatch = countryPhoneCodes.find(c => d.NumeroTelefono.startsWith(c.code));
                        if (codeMatch) {
                            selectPhoneCode.value = codeMatch.code;
                            inputPhoneNum.value = d.NumeroTelefono.substring(codeMatch.code.length);
                        } else {
                            inputPhoneNum.value = d.NumeroTelefono;
                        }
                    }

                    addAccountModal.show();
                }
            } catch (e) { console.error(e); }
        }

        if (delBtn) {
            if (await window.showConfirmModal('Eliminar', '¿Estás seguro de eliminar este beneficiario?')) {
                const id = delBtn.dataset.id;
                await fetch('../api/?accion=deleteBeneficiary', {
                    method: 'POST',
                    body: JSON.stringify({ id }),
                    headers: { 'Content-Type': 'application/json' }
                });
                loadBeneficiaries();
            }
        }
    });

    Promise.all([
        loadDropdownData('getPaises&rol=Destino', benefPaisIdInput, 'NombrePais', 'PaisID'),
        loadDropdownData('getBeneficiaryTypes', benefTipoSelect, 'nombre'),
        loadDropdownData('getDocumentTypes', benefDocTypeSelect, 'nombre')
    ]).then(() => {
        loadPhoneCodes(selectPhoneCode);
        loadUserProfile();
        loadBeneficiaries();
    });
});