document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const loginFeedback = document.getElementById('login-feedback');
    const registerFeedback = document.getElementById('register-feedback');
    const docTypeSelect = document.getElementById('register-doc-type');
    const docNumInput = document.getElementById('register-doc-num');

    const registerPhoneCode = document.getElementById('register-phone-code');
    const registerTelefono = document.getElementById('register-telefono');
    const registerRoleInput = document.getElementById('register-role');

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
    // ----------------------------------------------

    const countryPhoneCodes = [
        { code: '+54', name: 'Argentina', flag: '\uD83C\uDDE6\uD83C\uDDF7' },
        { code: '+591', name: 'Bolivia', flag: '\uD83C\uDDE7\uD83C\uDDF4' },
        { code: '+55', name: 'Brasil', flag: '\uD83C\uDDE7\uD83C\uDDF7' },
        { code: '+56', name: 'Chile', flag: '\uD83C\uDDE8\uD83C\uDDF1' },
        { code: '+57', name: 'Colombia', flag: '\uD83C\uDDE8\uD83C\uDDF4' },
        { code: '+506', name: 'Costa Rica', flag: '\uD83C\uDDE8\uD83C\uDDF7' },
        { code: '+53', name: 'Cuba', flag: '\uD83C\uDDE8\uD83C\uDDFA' },
        { code: '+593', name: 'Ecuador', flag: '\uD83C\uDDEA\uD83C\uDDE8' },
        { code: '+503', name: 'El Salvador', flag: '\uD83C\uDDF8\uD83C\uDDFB' },
        { code: '+502', name: 'Guatemala', flag: '\uD83C\uDDEC\uD83C\uDDF9' },
        { code: '+504', name: 'Honduras', flag: '\uD83C\uDDED\uD83C\uDDF3' },
        { code: '+52', name: 'México', flag: '\uD83C\uDDF2\uD83C\uDDFD' },
        { code: '+505', name: 'Nicaragua', flag: '\uD83C\uDDF3\uD83C\uDDEE' },
        { code: '+507', name: 'Panamá', flag: '\uD83C\uDDF5\uD83C\uDDE6' },
        { code: '+595', name: 'Paraguay', flag: '\uD83C\uDDF5\uD83C\uDDFE' },
        { code: '+51', name: 'Perú', flag: '\uD83C\uDDF5\uD83C\uDDEA' },
        { code: '+1', name: 'Puerto Rico', flag: '\uD83C\uDDF5\uD83C\uDDF7' },
        { code: '+1', name: 'Rep. Dominicana', flag: '\uD83C\uDDE9\uD83C\uDDF4' },
        { code: '+598', name: 'Uruguay', flag: '\uD83C\uDDFA\uD83C\uDDFE' },
        { code: '+58', name: 'Venezuela', flag: '\uD83C\uDDFB\uD83C\uDDEA' },
        { code: '+1', name: 'EE.UU.', flag: '\uD83C\uDDFA\uD83C\uDDF8' }
    ];

    const loadPhoneCodes = (selectElement) => {
        if (!selectElement) return;

        countryPhoneCodes.sort((a, b) => a.name.localeCompare(b.name));

        selectElement.innerHTML = '<option value="">C\u00F3digo...</option>';
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
            const tipos = await response.json();

            docTypeSelect.innerHTML = '<option value="">Selecciona...</option>';
            tipos.forEach(tipo => {
                docTypeSelect.innerHTML += `<option value="${tipo.nombre}">${tipo.nombre}</option>`;
            });
        } catch (error) {
            console.error(error);
            docTypeSelect.innerHTML = '<option value="">Error al cargar</option>';
        }
    };

    docTypeSelect?.addEventListener('change', () => {
        docNumInput.removeAttribute('pattern');
        docNumInput.classList.remove('is-invalid', 'is-valid');
        docNumInput.value = '';

        if (docTypeSelect.value === 'RUT') {
            docNumInput.dataset.validateRut = 'true';
            docNumInput.maxLength = 12;
            docNumInput.placeholder = '12.345.678-9';
        } else {
            docNumInput.dataset.validateRut = 'false';
            docNumInput.maxLength = 20;
            docNumInput.placeholder = 'Nro. Documento';
        }

        if (registerRoleInput) {
            if (docTypeSelect.value === 'RIF') {
                registerRoleInput.value = 'Empresa';
            } else {
                registerRoleInput.value = 'Persona Natural';
            }
        }
    });

    docNumInput?.addEventListener('input', (e) => {
        if (docNumInput.dataset.validateRut !== 'true' || typeof cleanRut !== 'function' || typeof validateRut !== 'function' || typeof formatRut !== 'function') {
            return;
        }

        let rutLimpio = cleanRut(e.target.value);

        if (rutLimpio.length > 9) {
            rutLimpio = rutLimpio.slice(0, 9);
        }

        e.target.value = formatRut(rutLimpio);

        docNumInput.classList.remove('is-valid', 'is-invalid');
        if (rutLimpio.length > 1) {
            if (validateRut(rutLimpio)) {
                docNumInput.classList.add('is-valid');
            } else if (rutLimpio.length === 9) {
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
        let missingFields = [];

        if (!secNameInput.value.trim()) missingFields.push("Segundo Nombre");
        if (!secLastInput.value.trim()) missingFields.push("Segundo Apellido");

        if (missingFields.length > 0) {
            const camposFaltantes = missingFields.join(' ni ');
            const msg = `El beneficiario o cliente no tiene ${camposFaltantes}, \u00BFest\u00E1 seguro?`;
            let confirmed = false;
            if (window.showInfoModal && window.showConfirmModal) {
                confirmed = await window.showConfirmModal('Confirmar Acci\u00F3n', msg);
            } else {
                confirmed = confirm(msg);
            }

            if (!confirmed) {
                return;
            }
        }

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

        const wasReadOnly = registerRoleInput.readOnly;
        if (wasReadOnly) {
            registerRoleInput.readOnly = false;
        }

        const formData = new FormData(registerForm);

        if (wasReadOnly) {
            registerRoleInput.readOnly = true;
        }

        if (docNumInput.dataset.validateRut === 'true' && typeof cleanRut === 'function') {
            formData.set('numeroDocumento', cleanRut(docNumInput.value));
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