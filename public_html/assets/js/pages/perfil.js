document.addEventListener('DOMContentLoaded', () => {

    // 1. CONSTANTES
    const C_COLOMBIA = 2;
    const C_VENEZUELA = 3;
    const C_PERU = 4;

    // 2. REFERENCIAS DOM
    const profileForm = document.getElementById('profile-form');
    const profileLoading = document.getElementById('profile-loading');
    const profileImgPreview = document.getElementById('profile-img-preview');
    const profileSaveBtn = document.getElementById('profile-save-btn');
    const photoRequiredBadge = document.getElementById('photo-required-badge');
    
    // Cámara
    const btnOpenCamera = document.getElementById('btn-open-camera');
    const cameraModalEl = document.getElementById('cameraModal');
    const video = document.getElementById('video-feed');
    const canvas = document.getElementById('capture-canvas');
    const btnCapture = document.getElementById('btn-capture-photo');
    const btnCloseCamera = document.getElementById('btn-close-camera');
    let stream = null;
    let cameraModal = null;
    let capturedBlob = null;

    // Beneficiarios
    const listContainer = document.getElementById('beneficiary-list-container');
    const loadingDiv = document.getElementById('beneficiarios-loading');
    const addAccountModalEl = document.getElementById('addAccountModal');
    let addAccountModal = null;
    if(addAccountModalEl) addAccountModal = new bootstrap.Modal(addAccountModalEl);
    
    const addBeneficiaryForm = document.getElementById('add-beneficiary-form');
    const addAccountModalLabel = document.getElementById('addAccountModalLabel');
    const btnNewBeneficiary = document.getElementById('btn-new-beneficiary'); 

    // Inputs Modal
    const benefCuentaIdInput = document.getElementById('benef-cuenta-id');
    const benefPaisIdInput = document.getElementById('benef-pais-id'); 
    const benefAliasInput = document.getElementById('benef-alias');
    
    const inputPrimerNombre = document.getElementById('benef-firstname');
    const inputPrimerApellido = document.getElementById('benef-lastname');
    
    const benefDocTypeSelect = document.getElementById('benef-doc-type');
    const benefDocNumberInput = document.getElementById('benef-doc-number');
    const benefDocPrefix = document.getElementById('benef-doc-prefix');

    const benefBankSelect = document.getElementById('benef-bank-select'); 
    const inputBankNameText = document.getElementById('benef-bank'); 
    const inputOtherBank = document.getElementById('benef-bank-other');
    
    const inputAccountNum = document.getElementById('benef-account-num');
    const inputCCI = document.getElementById('benef-cci');
    const inputPhoneNum = document.getElementById('benef-phone-number');
    const selectPhoneCode = document.getElementById('benef-phone-code');
    const walletPhonePrefix = document.getElementById('wallet-phone-prefix');

    // Contenedores Visibilidad
    const containerBankSelect = document.getElementById('container-bank-select');
    const containerBankInputText = document.getElementById('container-bank-input-text');
    const containerOtherBank = document.getElementById('other-bank-container');
    const containerBankInput = document.getElementById('container-bank-input'); 
    const containerMobileInput = document.getElementById('container-mobile-input'); 
    const containerCCI = document.getElementById('container-cci');
    const wrapperChecksType = document.getElementById('wrapper-checks-type');

    const checkIncludeBank = document.getElementById('check-include-bank');
    const checkIncludeMobile = document.getElementById('check-include-mobile');

    const labelAccount = document.getElementById('label-account-num');
    const labelWallet = document.getElementById('label-wallet-phone');

    // Datos Auxiliares
    const venezuelaPrefixes = ['0412', '0414', '0424', '0416', '0426'];
    const countryPhoneCodes = [
        { code: '+56', name: 'Chile' }, { code: '+58', name: 'Venezuela' },
        { code: '+57', name: 'Colombia' }, { code: '+51', name: 'Perú' },
        { code: '+54', name: 'Argentina' }, { code: '+55', name: 'Brasil' },
        { code: '+1', name: 'USA' }, { code: '+34', name: 'España' }
    ];

    // =========================================================
    // 3. FUNCIONES LÓGICAS
    // =========================================================

    if (cameraModalEl) {
        cameraModalEl.addEventListener('hidden.bs.modal', () => {
            if (stream) { stream.getTracks().forEach(track => track.stop()); stream = null; }
        });
    }

    const loadPhoneCodes = (el, isVzla = false) => {
        if (!el) return;
        el.innerHTML = '';
        if (isVzla) {
            venezuelaPrefixes.forEach(p => el.add(new Option(p, p)));
        } else {
            el.add(new Option('Cod...', ''));
            countryPhoneCodes.forEach(c => el.add(new Option(`${c.code}`, c.code)));
        }
    };

    const loadUserProfile = async () => {
        try {
            const r = await fetch('../api/?accion=getUserProfile');
            const res = await r.json();
            if (res.success && res.profile) {
                const p = res.profile;
                const setVal = (id, v) => { const e = document.getElementById(id); if(e) e.value = v; }
                
                setVal('profile-nombre', `${p.PrimerNombre} ${p.PrimerApellido}`);
                setVal('profile-email', p.Email);
                setVal('profile-documento', p.NumeroDocumento);
                
                const pCode = document.getElementById('profile-phone-code');
                const pNum = document.getElementById('profile-telefono');
                if(pCode && pNum) {
                    loadPhoneCodes(pCode);
                    const full = p.Telefono || '';
                    const c = countryPhoneCodes.find(co => full.startsWith(co.code));
                    if(c) { pCode.value=c.code; pNum.value=full.substring(c.code.length); }
                    else pNum.value=full;
                }
                
                const st = document.getElementById('profile-estado');
                if(st) {
                    st.textContent = p.VerificacionEstado;
                    st.className = `badge bg-${p.VerificacionEstado === 'Verificado' ? 'success' : 'warning'} rounded-pill px-3`;
                }
                
                if(profileImgPreview) {
                    profileImgPreview.src = p.FotoPerfilURL ? `../admin/view_secure_file.php?file=${encodeURIComponent(p.FotoPerfilURL)}` : defaultPhoto;
                    if(photoRequiredBadge) p.FotoPerfilURL ? photoRequiredBadge.classList.add('d-none') : photoRequiredBadge.classList.remove('d-none');
                }
                if(profileLoading) profileLoading.classList.add('d-none');
                if(profileForm) profileForm.classList.remove('d-none');
            }
        } catch(e) { console.error("Error perfil", e); }
    };

    const configureModalForCountry = (paisId) => {
        // RESET
        [containerBankSelect, containerBankInputText, containerOtherBank, containerCCI, containerBankInput, containerMobileInput, wrapperChecksType].forEach(el => el && el.classList.add('d-none'));
        
        if(benefBankSelect) benefBankSelect.innerHTML = '<option value="">Seleccione...</option>';
        if(inputBankNameText) inputBankNameText.value = '';
        if(inputOtherBank) inputOtherBank.value = '';
        if(inputAccountNum) inputAccountNum.value = '';
        if(inputPhoneNum) inputPhoneNum.value = '';
        if(inputCCI) inputCCI.value = '';

        if(checkIncludeBank) { checkIncludeBank.checked = false; checkIncludeBank.disabled = false; }
        if(checkIncludeMobile) { checkIncludeMobile.checked = false; checkIncludeMobile.disabled = false; }

        if(walletPhonePrefix) walletPhonePrefix.classList.add('d-none');
        if(selectPhoneCode) { selectPhoneCode.classList.add('d-none'); selectPhoneCode.innerHTML = ''; }

        // LOGIC
        if (paisId === C_VENEZUELA) {
            containerBankInputText.classList.remove('d-none');
            wrapperChecksType.classList.remove('d-none');
            checkIncludeBank.checked = true;
            
            if(selectPhoneCode) {
                selectPhoneCode.classList.remove('d-none');
                loadPhoneCodes(selectPhoneCode, true);
            }
            if(inputAccountNum) { inputAccountNum.maxLength = 20; inputAccountNum.placeholder = "20 dígitos exactos"; }
            if(inputPhoneNum) { inputPhoneNum.maxLength = 7; inputPhoneNum.placeholder = "7 dígitos"; }
            updateSwitchUI(); 
        }
        else if (paisId === C_PERU) {
            containerBankSelect.classList.remove('d-none');
            if(walletPhonePrefix) { walletPhonePrefix.classList.remove('d-none'); walletPhonePrefix.textContent = '+51'; }
            ['Interbank', 'Otro Banco', 'Yape', 'Plin'].forEach(o => benefBankSelect.add(new Option(o, o)));
            if(inputAccountNum) { inputAccountNum.maxLength = 14; inputAccountNum.placeholder = "13 o 14 dígitos"; }
            if(inputPhoneNum) { inputPhoneNum.maxLength = 9; inputPhoneNum.placeholder = "9 dígitos"; }
        }
        else if (paisId === C_COLOMBIA) {
            containerBankSelect.classList.remove('d-none');
            if(walletPhonePrefix) { walletPhonePrefix.classList.remove('d-none'); walletPhonePrefix.textContent = '+57'; }
            ['Bancolombia', 'Nequi'].forEach(o => benefBankSelect.add(new Option(o, o)));
            if(inputAccountNum) { inputAccountNum.maxLength = 11; inputAccountNum.placeholder = "11 dígitos"; }
            if(inputPhoneNum) { inputPhoneNum.maxLength = 10; inputPhoneNum.placeholder = "10 dígitos"; }
        }
        else {
            containerBankInputText.classList.remove('d-none');
            containerBankInput.classList.remove('d-none');
            if(checkIncludeBank) { checkIncludeBank.checked = true; checkIncludeBank.disabled = true; }
            if(inputAccountNum) inputAccountNum.placeholder = "Número de cuenta";
        }
    };

    const updateSwitchUI = () => {
        if(checkIncludeBank && containerBankInput) {
            if(checkIncludeBank.checked) {
                containerBankInput.classList.remove('d-none');
                inputAccountNum.required = true;
            } else {
                containerBankInput.classList.add('d-none');
                inputAccountNum.required = false;
            }
        }
        if(checkIncludeMobile && containerMobileInput) {
            if(checkIncludeMobile.checked) {
                containerMobileInput.classList.remove('d-none');
                inputPhoneNum.required = true;
            } else {
                containerMobileInput.classList.add('d-none');
                inputPhoneNum.required = false;
            }
        }
    };

    const loadBeneficiaries = async () => {
        if(!listContainer) return;
        if(loadingDiv) loadingDiv.classList.remove('d-none');
        listContainer.innerHTML = '';
        try {
            const r = await fetch('../api/?accion=getCuentas&paisID=0');
            const d = await r.json();
            if(loadingDiv) loadingDiv.classList.add('d-none');
            
            if(d.length > 0) {
                d.forEach(c => {
                    let num = c.NumeroCuenta;
                    if(!num || num==='PAGO MOVIL') num = c.NumeroTelefono || 'Sin N°';
                    const div = document.createElement('div');
                    div.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0 border-bottom py-3 px-3';
                    div.innerHTML = `
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="bi bi-person-fill fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">${c.Alias}</h6>
                                <small class="text-muted">${c.NombreBanco} • ${num}</small>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-1 btn-edit rounded-circle" data-json='${JSON.stringify(c).replace(/'/g, "&apos;")}'><i class="bi bi-pencil-fill"></i></button>
                            <button class="btn btn-sm btn-outline-danger rounded-circle btn-delete" data-id="${c.CuentaID}"><i class="bi bi-trash-fill"></i></button>
                        </div>
                    `;
                    listContainer.appendChild(div);
                });
                
                document.querySelectorAll('.btn-edit').forEach(b => b.addEventListener('click', e => openEditModal(JSON.parse(e.currentTarget.dataset.json))));
                
                // --- CORRECCIÓN: Capturar el ID antes del await ---
                document.querySelectorAll('.btn-delete').forEach(b => b.addEventListener('click', async e => {
                    const idToDelete = e.currentTarget.dataset.id; // GUARDAR REFERENCIA
                    if(await window.showConfirmModal('Eliminar', '¿Estás seguro de eliminar este beneficiario?')) {
                        await fetch('../api/?accion=deleteBeneficiary', {
                            method:'POST', 
                            body:JSON.stringify({id: idToDelete}), 
                            headers:{'Content-Type':'application/json'}
                        });
                        loadBeneficiaries();
                    }
                }));
                // ------------------------------------------------
            } else listContainer.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi bi-person-badge fs-1 d-block mb-2"></i>No tienes beneficiarios registrados.</div>';
        } catch(e) { if(loadingDiv) loadingDiv.classList.add('d-none'); }
    };

    const openEditModal = (d) => {
        addAccountModalLabel.textContent = 'Editar Beneficiario';
        benefCuentaIdInput.value = d.CuentaID;
        benefPaisIdInput.value = d.PaisID;
        configureModalForCountry(parseInt(d.PaisID)); 
        benefAliasInput.value = d.Alias;

        if(inputPrimerNombre) inputPrimerNombre.value = d.TitularPrimerNombre;
        if(inputPrimerApellido) inputPrimerApellido.value = d.TitularPrimerApellido;

        const toggleField = (val, checkId, contId, inputId) => {
            const ck = document.getElementById(checkId);
            const ct = document.getElementById(contId);
            const ip = document.getElementById(inputId);
            if(val) { ip.value=val; ct.classList.remove('d-none'); ck.checked=false; ip.required=true; }
            else { ip.value=''; ct.classList.add('d-none'); ck.checked=true; ip.required=false; }
        };
        toggleField(d.TitularSegundoNombre, 'toggle-benef-segundo-nombre', 'container-benef-segundo-nombre', 'benef-secondname');
        toggleField(d.TitularSegundoApellido, 'toggle-benef-segundo-apellido', 'container-benef-segundo-apellido', 'benef-secondlastname');

        if(benefDocTypeSelect) { benefDocTypeSelect.value = d.TitularTipoDocumentoID; updateDocValidation(); }
        if(benefDocNumberInput) {
            let num = d.TitularNumeroDocumento;
            if(parseInt(d.PaisID)===3 && benefDocPrefix) {
                const p = num.charAt(0);
                if(['V','E','J','G','P'].includes(p)) { benefDocPrefix.value=p; num=num.substring(1); }
            }
            benefDocNumberInput.value = num;
        }

        const banco = d.NombreBanco;
        if(!benefBankSelect.closest('.d-none')) {
            let exists = Array.from(benefBankSelect.options).some(o=>o.value===banco);
            if(exists) benefBankSelect.value = banco;
            else { benefBankSelect.value='Otro Banco'; if(inputOtherBank) inputOtherBank.value=banco; }
            benefBankSelect.dispatchEvent(new Event('change'));
        } else {
            if(inputBankNameText) inputBankNameText.value = banco;
        }

        if(inputAccountNum) inputAccountNum.value = d.NumeroCuenta || '';
        if(inputCCI) inputCCI.value = d.CCI || '';
        if(inputPhoneNum && d.NumeroTelefono) {
            let ph = d.NumeroTelefono;
            if(parseInt(d.PaisID)===3 && selectPhoneCode) {
                const pre = ['0412','0414','0424','0416','0426'].find(x=>ph.startsWith(x));
                if(pre) { selectPhoneCode.value=pre; ph=ph.replace(pre,''); }
            }
            inputPhoneNum.value = ph;
        }

        if(parseInt(d.PaisID)===3) {
            checkIncludeBank.checked = !!d.NumeroCuenta && d.NumeroCuenta!=='PAGO MOVIL';
            checkIncludeMobile.checked = !!d.NumeroTelefono;
            updateSwitchUI();
        }
        addAccountModal.show();
    };

    // =========================================================
    // 4. EVENTOS DOM
    // =========================================================

    if(benefPaisIdInput) {
        benefPaisIdInput.addEventListener('change', (e) => configureModalForCountry(parseInt(e.target.value)));
    }

    if (benefBankSelect) {
        benefBankSelect.addEventListener('change', function() {
            const val = this.value;
            const paisId = parseInt(benefPaisIdInput.value);

            [containerOtherBank, containerCCI, containerBankInput, containerMobileInput].forEach(el => el.classList.add('d-none'));
            if(checkIncludeBank) checkIncludeBank.checked = false;
            if(checkIncludeMobile) checkIncludeMobile.checked = false;

            if(!val) return;

            if (paisId === C_PERU) {
                if (['Yape', 'Plin'].includes(val)) {
                    if(containerMobileInput) containerMobileInput.classList.remove('d-none');
                    if(labelWallet) labelWallet.textContent = "Número de Celular";
                    if(checkIncludeMobile) checkIncludeMobile.checked = true;
                    if(inputPhoneNum) inputPhoneNum.required = true;
                    if(inputAccountNum) inputAccountNum.required = false;
                } else {
                    if(containerBankInput) containerBankInput.classList.remove('d-none');
                    if(labelAccount) labelAccount.textContent = "Número de Cuenta";
                    if(checkIncludeBank) checkIncludeBank.checked = true;
                    if(inputAccountNum) inputAccountNum.required = true;
                    if(inputPhoneNum) inputPhoneNum.required = false;

                    if (val === 'Interbank') {
                        if(labelAccount) labelAccount.textContent = "Cuenta Interbank";
                    } else if (val === 'Otro Banco') {
                        if(containerOtherBank) containerOtherBank.classList.remove('d-none');
                        if(containerCCI) containerCCI.classList.remove('d-none'); 
                        if(inputOtherBank) inputOtherBank.required = true;
                    }
                }
            }
            else if (paisId === C_COLOMBIA) {
                if (val === 'Nequi') {
                    if(containerMobileInput) containerMobileInput.classList.remove('d-none');
                    if(labelWallet) labelWallet.textContent = "Número de Celular";
                    if(checkIncludeMobile) checkIncludeMobile.checked = true;
                    if(inputPhoneNum) inputPhoneNum.required = true;
                    if(inputAccountNum) inputAccountNum.required = false;
                } else {
                    if(containerBankInput) containerBankInput.classList.remove('d-none');
                    if(labelAccount) labelAccount.textContent = "Número de Cuenta";
                    if(checkIncludeBank) checkIncludeBank.checked = true;
                    if(inputAccountNum) inputAccountNum.required = true;
                    if(inputPhoneNum) inputPhoneNum.required = false;
                }
            }
        });
    }

    if(checkIncludeBank) checkIncludeBank.addEventListener('change', updateSwitchUI);
    if(checkIncludeMobile) checkIncludeMobile.addEventListener('change', updateSwitchUI);

    [inputAccountNum, inputPhoneNum, inputCCI, benefDocNumberInput].forEach(inp => {
        if(inp) inp.addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); });
    });

    const updateDocValidation = () => {
        const p = parseInt(benefPaisIdInput.value);
        if(p===3 && benefDocPrefix) benefDocPrefix.classList.remove('d-none'); 
        else if(benefDocPrefix) benefDocPrefix.classList.add('d-none');
    };
    if(benefDocTypeSelect) benefDocTypeSelect.addEventListener('change', updateDocValidation);

    const setupNameToggle = (checkId, contId, inputId) => {
        const ck = document.getElementById(checkId);
        const ct = document.getElementById(contId);
        const ip = document.getElementById(inputId);
        if(ck && ct && ip) {
            ck.addEventListener('change', () => {
                if(ck.checked) { ct.classList.add('d-none'); ip.value=''; ip.required=false; }
                else { ct.classList.remove('d-none'); ip.required=true; }
            });
            if(ck.checked) { ct.classList.add('d-none'); ip.required=false; }
        }
    };
    setupNameToggle('toggle-benef-segundo-nombre', 'container-benef-segundo-nombre', 'benef-secondname');
    setupNameToggle('toggle-benef-segundo-apellido', 'container-benef-segundo-apellido', 'benef-secondlastname');

    if(btnNewBeneficiary) {
        btnNewBeneficiary.addEventListener('click', () => {
            addBeneficiaryForm.reset();
            addAccountModalLabel.textContent = 'Nuevo Beneficiario';
            benefCuentaIdInput.value = '';
            benefPaisIdInput.value = 3; 
            configureModalForCountry(3);
            if(addAccountModal) addAccountModal.show();
        });
    }

    addBeneficiaryForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.querySelector('#addAccountModal .btn-primary');
        const txt = btn.textContent; btn.disabled = true; btn.textContent = 'Guardando...';

        const fd = new FormData(addBeneficiaryForm);
        const pais = parseInt(fd.get('paisID'));
        
        if (!pais || isNaN(pais)) {
            window.showInfoModal('Error', 'Seleccione un país.', false);
            btn.disabled = false; btn.textContent = txt; return;
        }

        const banco = fd.get('nombreBancoSelect') || fd.get('nombreBanco');
        const acc = fd.get('numeroCuenta');
        const phone = fd.get('phoneNumber');

        let isCta = false;
        let isMov = false;

        if (pais === C_COLOMBIA || pais === C_PERU) {
            if(['Yape','Plin','Nequi'].includes(banco)) isMov = true; else isCta = true;
        } else {
            isCta = checkIncludeBank ? checkIncludeBank.checked : true;
            isMov = checkIncludeMobile ? checkIncludeMobile.checked : false;
        }

        if(pais === C_PERU) {
            if(isMov && phone.length !== 9) { alert(`${banco} debe tener 9 dígitos.`); btn.disabled=false; btn.textContent=txt; return; }
            if(isCta && (acc.length !== 13 && acc.length !== 14)) { alert('La cuenta en Perú debe tener 13 o 14 dígitos.'); btn.disabled=false; btn.textContent=txt; return; }
        }
        else if(pais === C_COLOMBIA) {
            if(isMov && phone.length !== 10) { alert('Celular debe tener 10 dígitos.'); btn.disabled=false; btn.textContent=txt; return; }
            if(isCta && acc.length !== 11) { alert('Cuenta Colombia debe tener 11 dígitos.'); btn.disabled=false; btn.textContent=txt; return; }
        }
        else if(pais === C_VENEZUELA) {
            if(isCta && acc.length !== 20) { alert('Cuenta Venezuela debe tener 20 dígitos.'); btn.disabled=false; btn.textContent=txt; return; }
            if(isMov && phone.length !== 7) { alert('Teléfono debe tener 7 dígitos (sin prefijo).'); btn.disabled=false; btn.textContent=txt; return; }
        }

        if(benefDocPrefix && !benefDocPrefix.classList.contains('d-none')) {
            fd.set('numeroDocumento', benefDocPrefix.value + fd.get('numeroDocumento'));
        }
        if(isMov) {
            let pre = '';
            if(!walletPhonePrefix.classList.contains('d-none')) pre = walletPhonePrefix.textContent.replace('+','');
            else if(!selectPhoneCode.classList.contains('d-none')) pre = selectPhoneCode.value;
            fd.set('phoneCode', '+'+pre);
        } else { fd.delete('phoneNumber'); }

        if(banco === 'Otro Banco' && inputOtherBank) fd.set('nombreBanco', inputOtherBank.value);
        else if(fd.get('nombreBancoSelect')) fd.set('nombreBanco', fd.get('nombreBancoSelect'));

        fd.set('incluirCuentaBancaria', isCta ? '1' : '0');
        fd.set('incluirPagoMovil', isMov ? '1' : '0');

        try {
            const act = fd.get('cuentaId') ? 'updateBeneficiary' : 'addCuenta';
            const r = await fetch(`../api/?accion=${act}`, { 
                method: 'POST', body: JSON.stringify(Object.fromEntries(fd)), headers:{'Content-Type':'application/json'} 
            });
            const j = await r.json();
            if(j.success) {
                if(addAccountModal) addAccountModal.hide();
                loadBeneficiaries();
                window.showInfoModal('Éxito', 'Guardado correctamente.', true);
            } else {
                window.showInfoModal('Error', j.error, false);
            }
        } catch(e) { window.showInfoModal('Error', 'Error de conexión.', false); }
        finally { btn.disabled = false; btn.textContent = txt; }
    });

    if(profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if(profileImgPreview.src.includes('SoloLogoNegroSinFondo') && !capturedBlob) {
                alert("Selfie obligatoria."); return;
            }
            profileSaveBtn.disabled = true; profileSaveBtn.textContent = 'Guardando...';
            const fd = new FormData();
            const ph = (profilePhoneCodeEl?.value||'') + (document.getElementById('profile-telefono')?.value||'');
            fd.append('telefono', ph);
            if(capturedBlob) fd.append('fotoPerfil', capturedBlob, 'perfil.jpg');
            try {
                const r = await fetch('../api/?accion=updateUserProfile', {method:'POST', body:fd});
                const j = await r.json();
                if(j.success) { window.showInfoModal('Éxito','Perfil actualizado.',true); loadUserProfile(); capturedBlob=null; }
                else window.showInfoModal('Error', j.error, false);
            } catch(e) {} finally { profileSaveBtn.disabled=false; profileSaveBtn.textContent='Guardar Cambios'; }
        });
    }

    const startCam = async () => {
        try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } }); video.srcObject = stream; cameraModal.show(); } 
        catch(e){ alert('Cámara bloqueada o no disponible'); }
    };
    if(btnOpenCamera) btnOpenCamera.addEventListener('click', startCam);
    if(btnCapture) btnCapture.addEventListener('click', () => {
        if(!video) return;
        const sz = Math.min(video.videoWidth, video.videoHeight);
        canvas.width=500; canvas.height=500;
        const ctx = canvas.getContext('2d');
        ctx.translate(500,0); ctx.scale(-1,1);
        ctx.drawImage(video, (video.videoWidth-sz)/2, (video.videoHeight-sz)/2, sz, sz, 0,0, 500,500);
        canvas.toBlob(b => { 
            capturedBlob=b; profileImgPreview.src=URL.createObjectURL(b); 
            if(stream) stream.getTracks().forEach(t=>t.stop()); cameraModal.hide();
            if(photoRequiredBadge) photoRequiredBadge.classList.add('d-none');
        }, 'image/jpeg');
    });

    const loadSelect = async (act, el, txt, val) => {
        try {
            const r = await fetch(`../api/?accion=${act}`);
            const d = await r.json();
            el.innerHTML = '<option value="">Seleccione...</option>';
            d.forEach(i => el.innerHTML+=`<option value="${i[val]||i.PaisID||i.TipoDocumentoID}">${i[txt]||i.NombrePais||i.NombreDocumento}</option>`);
        }catch(e){}
    };

    Promise.all([
        loadSelect('getPaises&rol=Destino', benefPaisIdInput, 'NombrePais', 'PaisID'),
        loadSelect('getDocumentTypes', benefDocTypeSelect, 'NombreDocumento', 'TipoDocumentoID')
    ]).then(() => { 
        loadUserProfile(); 
        loadBeneficiaries(); 
    });

});