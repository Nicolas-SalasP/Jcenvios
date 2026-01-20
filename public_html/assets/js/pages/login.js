document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const loginFeedback = document.getElementById('login-feedback');
    const registerFeedback = document.getElementById('register-feedback');
    const docTypeSelect = document.getElementById('register-doc-type');
    const docNumInput = document.getElementById('register-doc-num');

    const registerPhoneCode = document.getElementById('register-phone-code');
    const registerTelefono = document.getElementById('register-telefono');

    const setupFieldVisibility = (toggleId, containerId, inputId) => {
        const toggle = document.getElementById(toggleId);
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);

        if (toggle && container && input) {
            toggle.addEventListener('change', () => {
                if (toggle.checked) {
                    container.classList.add('d-none');
                    input.value = '';
                    input.required = false;
                } else {
                    container.classList.remove('d-none');
                    input.required = true;
                    input.focus();
                }
            });
        }
    };

    // Inicializar
    setupFieldVisibility('toggle-segundo-nombre', 'container-segundo-nombre', 'register-segundo-nombre');
    setupFieldVisibility('toggle-segundo-apellido', 'container-segundo-apellido', 'register-segundo-apellido');

    const countryPhoneCodes = [
        { code: '+49', name: 'Alemania', flag: '🇩🇪' },
        { code: '+54', name: 'Argentina', flag: '🇦🇷' },
        { code: '+32', name: 'Bélgica', flag: '🇧🇪' },
        { code: '+591', name: 'Bolivia', flag: '🇧🇴' },
        { code: '+55', name: 'Brasil', flag: '🇧🇷' },
        { code: '+56', name: 'Chile', flag: '🇨🇱' },
        { code: '+57', name: 'Colombia', flag: '🇨🇴' },
        { code: '+506', name: 'Costa Rica', flag: '🇨🇷' },
        { code: '+53', name: 'Cuba', flag: '🇨🇺' },
        { code: '+593', name: 'Ecuador', flag: '🇪🇨' },
        { code: '+1', name: 'EE.UU.', flag: '🇺🇸' },
        { code: '+503', name: 'El Salvador', flag: '🇸🇻' },
        { code: '+34', name: 'España', flag: '🇪🇸' },
        { code: '+33', name: 'Francia', flag: '🇫🇷' },
        { code: '+502', name: 'Guatemala', flag: '🇬🇹' },
        { code: '+504', name: 'Honduras', flag: '🇭🇳' },
        { code: '+39', name: 'Italia', flag: '🇮🇹' },
        { code: '+52', name: 'México', flag: '🇲🇽' },
        { code: '+505', name: 'Nicaragua', flag: '🇳🇮' },
        { code: '+31', name: 'Países Bajos', flag: '🇳🇱' },
        { code: '+507', name: 'Panamá', flag: '🇵🇦' },
        { code: '+595', name: 'Paraguay', flag: '🇵🇾' },
        { code: '+51', name: 'Perú', flag: '🇵🇪' },
        { code: '+351', name: 'Portugal', flag: '🇵🇹' },
        { code: '+1', name: 'Puerto Rico', flag: '🇵🇷' },
        { code: '+44', name: 'Reino Unido', flag: '🇬🇧' },
        { code: '+1', name: 'Rep. Dominicana', flag: '🇩🇴' },
        { code: '+41', name: 'Suiza', flag: '🇨🇭' },
        { code: '+598', name: 'Uruguay', flag: '🇺🇾' },
        { code: '+58', name: 'Venezuela', flag: '🇻🇪' }
    ];

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

    registerTelefono?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '');
    });

    const loadDocumentTypes = async () => {
        try {
            const response = await fetch('api/?accion=getDocumentTypes');
            if (!response.ok) throw new Error('Error al cargar tipos de documento');
            const tiposDesdeDB = await response.json();

            docTypeSelect.innerHTML = '<option value="">Selecciona...</option>';
            tiposDesdeDB.forEach(tipo => {
                docTypeSelect.innerHTML += `<option value="${tipo.NombreDocumento}">${tipo.NombreDocumento}</option>`;
            });
        } catch (error) {
            console.error(error);
            docTypeSelect.innerHTML = '<option value="">Error al cargar</option>';
        }
    };

    const enforceNameFormat = (inputId) => {
        const input = document.getElementById(inputId);
        if (!input) return;

        input.maxLength = 12;

        input.addEventListener('input', function () {
            this.value = this.value.replace(/\s/g, '');
            if (this.value.length > 12) this.value = this.value.substring(0, 12);
        });
    };

    enforceNameFormat('register-nombre');
    enforceNameFormat('register-segundo-nombre');
    enforceNameFormat('register-apellido');
    enforceNameFormat('register-segundo-apellido');

    docTypeSelect?.addEventListener('change', () => {
        docNumInput.classList.remove('is-invalid', 'is-valid');
        docNumInput.value = '';
        const val = docTypeSelect.value;

        if (val === 'RUT') {
            docNumInput.dataset.validateRut = 'true';
            docNumInput.maxLength = 12;
            docNumInput.placeholder = '12.345.678-9';
        } else {
            docNumInput.dataset.validateRut = 'false';
            docNumInput.maxLength = 15;
            docNumInput.placeholder = 'Nro. Documento';
        }
    });
    const formatNumberWithDots = (num) => {
        return num.replace(/\D/g, '')
            .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    };

    docNumInput?.addEventListener('input', (e) => {
        const isRut = docNumInput.dataset.validateRut === 'true';
        let rawVal = e.target.value.replace(/[^0-9kK\-\.]/g, '');

        if (isRut) {
            if (typeof cleanRut === 'function' && typeof formatRut === 'function') {
                let rutLimpio = cleanRut(rawVal);
                if (rutLimpio.length > 9) rutLimpio = rutLimpio.slice(0, 9);
                e.target.value = formatRut(rutLimpio);

                docNumInput.classList.remove('is-valid', 'is-invalid');
                if (rutLimpio.length > 1) {
                    if (validateRut(rutLimpio)) docNumInput.classList.add('is-valid');
                    else if (rutLimpio.length === 9) docNumInput.classList.add('is-invalid');
                }
            }
        } else {
            let cleanNum = rawVal.replace(/\D/g, '');
            if (cleanNum.length > 15) cleanNum = cleanNum.slice(0, 15);
            e.target.value = formatNumberWithDots(cleanNum);

            docNumInput.classList.remove('is-valid', 'is-invalid');
            if (cleanNum.length >= 8) {
                docNumInput.classList.add('is-valid');
            } else if (cleanNum.length > 0) {
                docNumInput.classList.add('is-invalid');
            }
        }
    });

    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginFeedback.textContent = '';
        const formData = new FormData(loginForm);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('api/?accion=loginUser', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (response.ok && result.success) {
                window.location.href = result.redirect;
            } else {
                const errorMsg = result.error || 'Error desconocido';
                if (window.showInfoModal) {
                    window.showInfoModal('Error de Inicio de Sesion', errorMsg, false);
                } else {
                    loginFeedback.textContent = errorMsg;
                }
            }
        } catch (error) {
            const errorMsg = 'Error de conexión. Inténtalo de nuevo.';
            if (window.showInfoModal) {
                window.showInfoModal('Error de Conexión', errorMsg, false);
            } else {
                loginFeedback.textContent = errorMsg;
            }
        }
    });

    registerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        registerFeedback.textContent = '';

        const submitButton = registerForm.querySelector('button[type="submit"]');
        const secNameInput = document.getElementById('register-segundo-nombre');
        const secLastInput = document.getElementById('register-segundo-apellido');

        submitButton.disabled = true;
        submitButton.textContent = 'Registrando...';

        const password = registerForm.password.value;
        const passwordRepeat = registerForm.passwordRepeat.value;

        if (password !== passwordRepeat) {
            registerFeedback.textContent = 'Las contraseñas no coinciden.';
            submitButton.disabled = false;
            submitButton.textContent = 'Registrar Cuenta';
            return;
        }

        if (password.length < 6) {
            registerFeedback.textContent = 'La contraseña debe tener al menos 6 caracteres.';
            submitButton.disabled = false;
            submitButton.textContent = 'Registrar Cuenta';
            return;
        }

        if (docNumInput.dataset.validateRut === 'true' && (typeof validateRut !== 'function' || !validateRut(cleanRut(docNumInput.value)))) {
            registerFeedback.textContent = 'El RUT ingresado no es válido.';
            submitButton.disabled = false;
            submitButton.textContent = 'Registrar Cuenta';
            return;
        }

        if (docNumInput.dataset.validateRut !== 'true') {
            const cleanDoc = docNumInput.value.replace(/\D/g, '');
            if (cleanDoc.length < 8) {
                registerFeedback.textContent = 'El número de documento debe tener al menos 8 dígitos.';
                submitButton.disabled = false;
                submitButton.textContent = 'Registrar Cuenta';
                return;
            }
        }

        const formData = new FormData(registerForm);

        if (docNumInput.dataset.validateRut === 'true' && typeof cleanRut === 'function') {
            formData.set('numeroDocumento', cleanRut(docNumInput.value));
        } else {
            formData.set('numeroDocumento', docNumInput.value.replace(/\D/g, ''));
        }

        const phoneInput = formData.get('phoneNumber');
        formData.set('phoneNumber', phoneInput.replace(/\D/g, ''));

        try {
            const response = await fetch('api/?accion=registerUser', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok && result.success) {
                window.location.href = result.redirect;
            } else {
                const errorMsg = result.error || 'Error al registrar la cuenta.';
                if (window.showInfoModal) {
                    window.showInfoModal('Error de Registro', errorMsg, false);
                } else {
                    registerFeedback.textContent = errorMsg;
                }
            }

        } catch (error) {
            const errorMsg = 'Error de conexión. Inténtalo de nuevo.';
            if (window.showInfoModal) {
                window.showInfoModal('Error de Red', errorMsg, false);
            } else {
                registerFeedback.textContent = errorMsg;
            }
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Registrar Cuenta';
        }
    });

    if (docTypeSelect) {
        loadDocumentTypes();
    }

    if (registerPhoneCode) {
        loadPhoneCodes(registerPhoneCode);
    }
});