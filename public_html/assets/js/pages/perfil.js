document.addEventListener('DOMContentLoaded', () => {

    // --- REFERENCIAS DOM PERFIL ---
    const profileForm = document.getElementById('profile-form');
    const profileLoading = document.getElementById('profile-loading');
    const profileImgPreview = document.getElementById('profile-img-preview');
    const profileSaveBtn = document.getElementById('profile-save-btn');
    const nombreCompletoEl = document.getElementById('profile-nombre');
    const emailEl = document.getElementById('profile-email');
    const documentoEl = document.getElementById('profile-documento');
    const telefonoEl = document.getElementById('profile-telefono');
    const profilePhoneCodeEl = document.getElementById('profile-phone-code');
    const estadoBadge = document.getElementById('profile-estado');
    const defaultPhoto = `../assets/img/SoloLogoNegroSinFondo.png`;

    // --- REFERENCIAS CÁMARA ---
    const btnOpenCamera = document.getElementById('btn-open-camera');
    const cameraModalEl = document.getElementById('cameraModal');
    const video = document.getElementById('video-feed');
    const canvas = document.getElementById('capture-canvas');
    const btnCapture = document.getElementById('btn-capture-photo');
    const btnCloseCamera = document.getElementById('btn-close-camera');
    const photoRequiredBadge = document.getElementById('photo-required-badge');

    let stream = null;
    let cameraModal = null;
    let capturedBlob = null;

    if (cameraModalEl) {
        cameraModal = new bootstrap.Modal(cameraModalEl);
    }

    // --- REFERENCIAS BENEFICIARIOS ---
    const beneficiariosLoading = document.getElementById('beneficiarios-loading');
    const beneficiaryListContainer = document.getElementById('beneficiary-list-container');
    const addAccountModalElement = document.getElementById('addAccountModal');
    const addAccountModal = new bootstrap.Modal(addAccountModalElement);
    const addBeneficiaryForm = document.getElementById('add-beneficiary-form');
    const addAccountModalLabel = document.getElementById('addAccountModalLabel');

    const benefCuentaIdInput = document.getElementById('benef-cuenta-id');
    const benefPaisIdInput = document.getElementById('benef-pais-id');
    const benefDocTypeSelect = document.getElementById('benef-doc-type');
    const benefDocNumberInput = document.getElementById('benef-doc-number');
    const benefDocPrefix = document.getElementById('benef-doc-prefix');

    const checkIncludeBank = document.getElementById('check-include-bank');
    const checkIncludeMobile = document.getElementById('check-include-mobile');
    const containerBankInput = document.getElementById('container-bank-input');
    const containerMobileInput = document.getElementById('container-mobile-input');
    const inputAccountNum = document.getElementById('benef-account-num');
    const inputPhoneNum = document.getElementById('benef-phone-number');
    const selectPhoneCode = document.getElementById('benef-phone-code');

    let allDocumentTypes = [];
    let currentBeneficiaries = [];
    let isSubmittingBeneficiary = false;

    // =========================================================
    // 1. LOGICA DE INTERFAZ Y VALIDACIONES
    // =========================================================
    
    const updateSwitchUI = () => {
        if (containerBankInput) {
            containerBankInput.classList.toggle('d-none', !checkIncludeBank.checked);
            inputAccountNum.required = checkIncludeBank.checked;
        }
        if (containerMobileInput) {
            containerMobileInput.classList.toggle('d-none', !checkIncludeMobile.checked);
            inputPhoneNum.required = checkIncludeMobile.checked;
            if (selectPhoneCode) selectPhoneCode.required = checkIncludeMobile.checked;
        }
    };

    if (checkIncludeBank) checkIncludeBank.addEventListener('change', updateSwitchUI);
    if (checkIncludeMobile) checkIncludeMobile.addEventListener('change', updateSwitchUI);

    addBeneficiaryForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!checkIncludeBank.checked && !checkIncludeMobile.checked) {
            window.showInfoModal('Error', 'Debes seleccionar al menos una opción (Cuenta Bancaria o Pago Móvil).', false);
            return;
        }

        if (isSubmittingBeneficiary) return;
        isSubmittingBeneficiary = true;

        const formData = new FormData(addBeneficiaryForm);
        
        if (!benefDocPrefix.classList.contains('d-none')) {
            const rawDoc = formData.get('numeroDocumento').replace(/\D/g, ''); 
            formData.set('numeroDocumento', benefDocPrefix.value + rawDoc);
        }

        const data = Object.fromEntries(formData.entries());

        data.incluirCuentaBancaria = checkIncludeBank.checked;
        data.incluirPagoMovil = checkIncludeMobile.checked;

        if (!checkIncludeBank.checked) {
            data.numeroCuenta = null;
        }

        if (checkIncludeMobile.checked) {
            // Unir prefijo + número
            data.numeroTelefono = (data.phoneCode || '') + (data.phoneNumber || '');
        } else {
            data.numeroTelefono = null;
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
            isSubmittingBeneficiary = false;
        }
    });

    // --- Lógica Cámara ---
    const startCamera = async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
            });
            video.srcObject = stream;
            cameraModal.show();
        } catch (err) {
            alert('No se pudo acceder a la cámara. Por favor, concede permisos.');
            console.error(err);
        }
    };

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    };

    const takePhoto = () => {
        if (!video || !canvas) return;
        const size = Math.min(video.videoWidth, video.videoHeight);
        const startX = (video.videoWidth - size) / 2;
        const startY = (video.videoHeight - size) / 2;

        const outputSize = 500;
        canvas.width = outputSize;
        canvas.height = outputSize;

        const ctx = canvas.getContext('2d');
        ctx.translate(outputSize, 0);
        ctx.scale(-1, 1);

        ctx.drawImage(video, startX, startY, size, size, 0, 0, outputSize, outputSize);
        canvas.toBlob((blob) => {
            if (!blob) { alert("Error al procesar la imagen"); return; }

            capturedBlob = blob;
            const url = URL.createObjectURL(blob);
            profileImgPreview.src = url;

            if (photoRequiredBadge) photoRequiredBadge.classList.add('d-none');

            stopCamera();
            cameraModal.hide();
        }, 'image/jpeg', 0.70);
    };

    if (btnOpenCamera) btnOpenCamera.addEventListener('click', startCamera);
    if (btnCapture) btnCapture.addEventListener('click', takePhoto);

    if (cameraModalEl) {
        cameraModalEl.addEventListener('hidden.bs.modal', stopCamera);
    }
    if (btnCloseCamera) {
        btnCloseCamera.addEventListener('click', () => {
            stopCamera();
            cameraModal.hide();
        });
    }

    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isDefaultPhoto = profileImgPreview.src.includes('SoloLogoNegroSinFondo');
            if (isDefaultPhoto && !capturedBlob) {
                alert("La foto de perfil es obligatoria. Por favor, tome una selfie.");
                return;
            }

            profileSaveBtn.disabled = true;
            const originalText = profileSaveBtn.innerHTML;
            profileSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

            const formData = new FormData();
            const fullPhone = (profilePhoneCodeEl.value || '') + (telefonoEl.value || '');
            formData.append('telefono', fullPhone);
            if (capturedBlob) {
                formData.append('fotoPerfil', capturedBlob, 'perfil_cam.jpg');
            }

            try {
                const response = await fetch('../api/?accion=updateUserProfile', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.showInfoModal('Éxito', 'Perfil actualizado correctamente.', true);
                    loadUserProfile();
                    capturedBlob = null;
                } else {
                    window.showInfoModal('Error', result.error || 'No se pudo actualizar.', false);
                }
            } catch (error) {
                window.showInfoModal('Error', 'Error de conexión.', false);
            } finally {
                profileSaveBtn.disabled = false;
                profileSaveBtn.innerHTML = originalText;
            }
        });
    }

    // =========================================================
    // 2. UTILIDADES Y CARGA DE DATOS
    // =========================================================

    const countryPhoneCodes = [
        { code: '+58', name: 'Venezuela', flag: '🇻🇪' },
        { code: '+56', name: 'Chile', flag: '🇨🇱' },
        { code: '+57', name: 'Colombia', flag: '🇨🇴' },
        { code: '+51', name: 'Perú', flag: '🇵🇪' },
        { code: '+54', name: 'Argentina', flag: '🇦🇷' },
        { code: '+55', name: 'Brasil', flag: '🇧🇷' },
        { code: '+593', name: 'Ecuador', flag: '🇪🇨' },
        { code: '+1', name: 'EE.UU.', flag: '🇺🇸' },
        { code: '+34', name: 'España', flag: '🇪🇸' },
        { code: '+52', name: 'México', flag: '🇲🇽' },
        { code: '+507', name: 'Panamá', flag: '🇵🇦' }
    ];

    // Prefijos Específicos para Venezuela (Pago Móvil)
    const venezuelaPrefixes = ['0412', '0414', '0424', '0416', '0426'];

    const loadPhoneCodes = (selectElement) => {
        if (!selectElement) return;
        selectElement.innerHTML = '<option value="">Código...</option>';
        countryPhoneCodes.forEach(country => {
            selectElement.innerHTML += `<option value="${country.code}">${country.flag} ${country.code}</option>`;
        });
    };

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

    // MODIFICADO: Ahora maneja lógica especial para Venezuela (Prefijos vs Códigos País)
    const setPhoneCodeByPais = (paisId, selectElement) => {
        if (!selectElement) return;
        
        selectElement.innerHTML = '';
        
        // Si es Venezuela (ID 3), cargamos prefijos de operadoras
        if (paisId == 3) {
            venezuelaPrefixes.forEach(prefix => {
                selectElement.innerHTML += `<option value="${prefix}">${prefix}</option>`;
            });
        } else {
            // Para otros países, cargamos códigos internacionales normales
            countryPhoneCodes.forEach(country => {
                selectElement.innerHTML += `<option value="${country.code}">${country.flag} ${country.code}</option>`;
            });
            
            // Pre-seleccionar por defecto si existe match
            const map = { "1": "+56", "2": "+57", "5": "+51" }; // Ajusta según IDs
            if (map[paisId]) {
                selectElement.value = map[paisId];
            }
        }
    };

    const updateDocumentValidation = () => {
        const paisId = parseInt(benefPaisIdInput.value);
        const docTypeOption = benefDocTypeSelect.options[benefDocTypeSelect.selectedIndex];
        const docName = docTypeOption ? (docTypeOption.text || "").toLowerCase() : '';
        const isVenezuela = (paisId === 3);

        benefDocPrefix.classList.add('d-none');
        benefDocNumberInput.maxLength = 20;
        benefDocNumberInput.placeholder = 'Número Documento';
        
        benefDocNumberInput.oninput = function () { 
            this.value = this.value.replace(/[^0-9]/g, ''); 
        };

        if (isVenezuela) {
            if (docName.includes('cédula') || docName.includes('cedula') || docName.includes('v') || docName.includes('e')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 9;
                benefDocNumberInput.placeholder = '12345678';
                
            } else if (docName.includes('rif') || docName.includes('jurídico')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="J">J</option><option value="G">G</option><option value="V">V</option><option value="E">E</option>';
                benefDocNumberInput.maxLength = 10;
                benefDocNumberInput.placeholder = '123456789';
            
            } else if (docName.includes('pasaporte')) {
                benefDocPrefix.classList.remove('d-none');
                benefDocPrefix.innerHTML = '<option value="P">P</option>';
                benefDocNumberInput.maxLength = 15;
                benefDocNumberInput.placeholder = 'Número Pasaporte';
            }
        } else {
            if (docName.includes('rut')) {
                benefDocNumberInput.maxLength = 12;
                benefDocNumberInput.placeholder = '12345678-9';
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^0-9kK-]/g, ''); };
            } else {
                benefDocNumberInput.oninput = function () { this.value = this.value.replace(/[^a-zA-Z0-9]/g, ''); };
            }
        }
    };

    const updateDocumentTypesList = () => {
        const paisId = parseInt(benefPaisIdInput.value);
        benefDocTypeSelect.innerHTML = '<option value="">Selecciona...</option>';
        allDocumentTypes.forEach(doc => {
            const nombreDoc = doc.nombre || doc.NombreDocumento || "";
            benefDocTypeSelect.innerHTML += `<option value="${doc.id || doc.TipoDocumentoID}">${nombreDoc}</option>`;
        });
    };

    benefDocTypeSelect.addEventListener('change', updateDocumentValidation);
    
    // Al cambiar país, actualizamos prefijos de teléfono y validaciones
    benefPaisIdInput.addEventListener('change', () => {
        const paisId = benefPaisIdInput.value;
        setPhoneCodeByPais(paisId, selectPhoneCode);
        updateDocumentValidation();
    });

    if (inputAccountNum) {
        inputAccountNum.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 20);
        });
    }

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

                if (p.FotoPerfilURL) {
                    let url = `../admin/view_secure_file.php?file=${encodeURIComponent(p.FotoPerfilURL)}`;
                    profileImgPreview.src = url;
                    if (photoRequiredBadge) photoRequiredBadge.classList.add('d-none');
                } else {
                    profileImgPreview.src = defaultPhoto;
                    if (photoRequiredBadge) photoRequiredBadge.classList.remove('d-none');
                }

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
                    let detalle = '';
                    if (c.NumeroCuenta && c.NumeroTelefono) {
                        detalle = `Cta: ...${c.NumeroCuenta.slice(-4)} | Móvil: ${c.NumeroTelefono}`;
                    } else if (c.NumeroCuenta) {
                        detalle = `Cta: ${c.NumeroCuenta}`;
                    } else if (c.NumeroTelefono) {
                        detalle = `Pago Móvil: ${c.NumeroTelefono}`;
                    } else {
                        detalle = 'Sin datos de pago';
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
        const secNameInput = document.getElementById('benef-secondname');
        const secLastInput = document.getElementById('benef-secondlastname');

        if (containerSecName) containerSecName.classList.remove('d-none');
        if (containerSecLast) containerSecLast.classList.remove('d-none');
        if (secNameInput) secNameInput.required = true;
        if (secLastInput) secLastInput.required = true;
        
        updateDocumentValidation();
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
                    
                    // CARGAR CÓDIGOS DE TELÉFONO CORRECTOS SEGÚN PAIS
                    setPhoneCodeByPais(d.PaisID, selectPhoneCode);
                    
                    document.getElementById('benef-alias').value = d.Alias;
                    document.getElementById('benef-firstname').value = d.TitularPrimerNombre;
                    document.getElementById('benef-lastname').value = d.TitularPrimerApellido;

                    const secNameInput = document.getElementById('benef-secondname');
                    const secNameToggle = document.getElementById('toggle-benef-segundo-nombre');
                    const secNameContainer = document.getElementById('container-benef-segundo-nombre');
                    
                    if (d.TitularSegundoNombre) {
                        secNameInput.value = d.TitularSegundoNombre;
                        secNameContainer.classList.remove('d-none');
                        secNameToggle.checked = false;
                        secNameInput.required = true;
                    } else {
                        secNameInput.value = '';
                        secNameContainer.classList.add('d-none');
                        secNameToggle.checked = true;
                        secNameInput.required = false;
                    }

                    const secLastInput = document.getElementById('benef-secondlastname');
                    const secLastToggle = document.getElementById('toggle-benef-segundo-apellido');
                    const secLastContainer = document.getElementById('container-benef-segundo-apellido');
                    
                    if (d.TitularSegundoApellido) {
                        secLastInput.value = d.TitularSegundoApellido;
                        secLastContainer.classList.remove('d-none');
                        secLastToggle.checked = false;
                        secLastInput.required = true;
                    } else {
                        secLastInput.value = '';
                        secLastContainer.classList.add('d-none');
                        secLastToggle.checked = true;
                        secLastInput.required = false;
                    }

                    benefDocTypeSelect.value = d.TitularTipoDocumentoID;
                    updateDocumentValidation(); 

                    let docNum = d.TitularNumeroDocumento || "";
                    const firstChar = docNum.charAt(0).toUpperCase();
                    if (d.PaisID == 3 && ['V', 'E', 'J', 'G', 'P'].includes(firstChar)) {
                        if (!benefDocPrefix.classList.contains('d-none')) {
                            benefDocPrefix.value = firstChar;
                            docNum = docNum.substring(1);
                        }
                    }
                    document.getElementById('benef-doc-number').value = docNum;

                    document.getElementById('benef-bank').value = d.NombreBanco || '';
                    document.getElementById('benef-account-num').value = d.NumeroCuenta || '';
                    
                    if (d.NumeroTelefono) {
                        // Buscar match con prefijos o códigos
                        if (d.PaisID == 3) {
                            // Para Venezuela buscamos en los prefijos 0412, etc.
                            const prefix = venezuelaPrefixes.find(p => d.NumeroTelefono.startsWith(p));
                            if (prefix) {
                                selectPhoneCode.value = prefix;
                                inputPhoneNum.value = d.NumeroTelefono.substring(prefix.length);
                            } else {
                                inputPhoneNum.value = d.NumeroTelefono;
                            }
                        } else {
                            const codeMatch = countryPhoneCodes.find(c => d.NumeroTelefono.startsWith(c.code));
                            if (codeMatch) {
                                selectPhoneCode.value = codeMatch.code;
                                inputPhoneNum.value = d.NumeroTelefono.substring(codeMatch.code.length);
                            } else {
                                inputPhoneNum.value = d.NumeroTelefono;
                            }
                        }
                    }

                    checkIncludeBank.checked = !!d.NumeroCuenta;
                    checkIncludeMobile.checked = !!d.NumeroTelefono;
                    updateSwitchUI();

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

    // Carga inicial
    Promise.all([
        loadDropdownData('getPaises&rol=Destino', benefPaisIdInput, 'NombrePais', 'PaisID'),
        loadDropdownData('getDocumentTypes', benefDocTypeSelect, 'nombre')
    ]).then(() => {
        loadPhoneCodes(selectPhoneCode);
        loadUserProfile();
        loadBeneficiaries();
    });
});