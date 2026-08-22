document.addEventListener('DOMContentLoaded', () => {
    const statusContainer = document.getElementById('2fa-status-container');
    const setupSection = document.getElementById('setup-2fa-section');
    const disableSection = document.getElementById('disable-2fa-section');
    const qrContainer = document.getElementById('qr-code-container');
    const secretKeyDisplay = document.getElementById('secret-key-display');
    const verifyForm = document.getElementById('verify-2fa-form');
    const disableBtn = document.getElementById('disable-2fa-btn');
    
    const backupCodesModalEl = document.getElementById('backupCodesModal');
    const backupCodesModal = backupCodesModalEl ? bootstrap.Modal.getOrCreateInstance(backupCodesModalEl) : null;
    const backupCodesList = document.getElementById('backup-codes-list');

    if (typeof QRCode === 'undefined') {
        console.error('Librería QRCode.js no está cargada. 2FA no funcionará.');
        if (statusContainer) {
            statusContainer.innerHTML = '<p class="text-danger">Error al cargar el componente 2FA. Contacte a soporte.</p>';
        }
        return;
    }

    let is2FAEnabled = false;

    /**
     * Pide una contraseña en un modal Bootstrap. Devuelve la contraseña, o null
     * si el usuario cancela.
     *
     * Reemplaza al window.prompt() nativo que quedaba acá (era el último
     * diálogo nativo del proyecto). NO se usa window.showPromptModal porque ese
     * lo define pages/admin.js, que solo se carga en páginas de admin: esta
     * pantalla corre en el dashboard del cliente y ahí no existe. Además ese
     * usa un <textarea>, inservible para una contraseña.
     */
    const askPassword = (title, message) => {
        return new Promise((resolve) => {
            const id = 'seguridad-password-prompt-modal';
            const existing = document.getElementById(id);
            if (existing) existing.remove();

            const modalEl = document.createElement('div');
            modalEl.id = id;
            modalEl.className = 'modal fade';
            modalEl.setAttribute('tabindex', '-1');
            // Sin datos externos interpolados: todo el texto se setea con
            // textContent más abajo.
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold" data-role="title"></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="mb-3 text-dark" data-role="message"></p>
                            <input type="password" class="form-control" autocomplete="current-password" data-role="input">
                            <div class="form-text text-danger d-none" data-role="error">Debes ingresar tu contraseña.</div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary px-4" data-role="confirm">Confirmar</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modalEl);

            modalEl.querySelector('[data-role="title"]').textContent = title;
            modalEl.querySelector('[data-role="message"]').textContent = message;

            const inputEl = modalEl.querySelector('[data-role="input"]');
            const errorEl = modalEl.querySelector('[data-role="error"]');
            const confirmBtn = modalEl.querySelector('[data-role="confirm"]');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            let resolvedValue = null;

            const submit = () => {
                const val = inputEl.value;
                if (!val) {
                    errorEl.classList.remove('d-none');
                    inputEl.focus();
                    return;
                }
                resolvedValue = val;
                modal.hide();
            };

            confirmBtn.addEventListener('click', submit);
            inputEl.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') { ev.preventDefault(); submit(); }
            });
            inputEl.addEventListener('input', () => errorEl.classList.add('d-none'));

            modalEl.addEventListener('shown.bs.modal', () => inputEl.focus());
            modalEl.addEventListener('hidden.bs.modal', () => {
                // La contraseña no queda colgando en el DOM.
                inputEl.value = '';
                modalEl.remove();
                resolve(resolvedValue);
            });

            modal.show();
        });
    };

    const update2FAStatus = () => {
        if (!statusContainer || !setupSection || !disableSection) return;
        
        if (is2FAEnabled) {
            statusContainer.innerHTML = '<p class="lead text-success fw-bold"><i class="bi bi-shield-check"></i> Doble Factor (2FA) está ACTIVADO.</p>';
            setupSection.classList.add('d-none');
            disableSection.classList.remove('d-none');
        } else {
            statusContainer.innerHTML = '<p class="lead text-warning fw-bold"><i class="bi bi-shield-exclamation"></i> Doble Factor (2FA) está DESACTIVADO.</p>';
            setupSection.classList.remove('d-none');
            disableSection.classList.add('d-none');
            generate2FASecret();
        }
    };

    const getProfileStatus = async () => {
        try {
            const response = await fetch('../api/?accion=getUserProfile');
            const result = await window.parseJsonResponse(response);
            if (result.success && result.profile) {
                is2FAEnabled = result.profile.twofa_enabled || false;
            } else {
                throw new Error(result.error || 'No se pudo obtener el perfil');
            }
        } catch (e) {
            console.error(e);
            if (statusContainer) {
                // e.message puede venir de result.error (texto del servidor):
                // se escapa antes de meterlo en innerHTML.
                statusContainer.innerHTML = `<p class="text-danger">Error al cargar estado 2FA: ${window.escapeHtml(window.formatNetworkError(e))}</p>`;
            }
        }
        update2FAStatus();
    };

    const generate2FASecret = async () => {
        if (!qrContainer || !secretKeyDisplay) return;
        
        qrContainer.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>';
        secretKeyDisplay.textContent = 'Cargando...';
        try {
            const response = await fetch('../api/?accion=generate2FASecret', { method: 'POST' });
            const result = await window.parseJsonResponse(response);
            if (!result.success) throw new Error(result.error || "Error desconocido al generar secreto");

            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: result.qrCodeUrl,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            secretKeyDisplay.textContent = result.secret;
            
        } catch (e) {
            console.error(e);
            qrContainer.innerHTML = `<p class="text-danger">Error al generar QR: ${window.escapeHtml(window.formatNetworkError(e))}</p>`;
            secretKeyDisplay.textContent = 'Error';
        }
    };

    verifyForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = document.getElementById('2fa-code').value;
        const submitButton = verifyForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Verificando...';

        try {
            const response = await fetch('../api/?accion=enable2FA', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });
            const result = await window.parseJsonResponse(response);
            
            if (result.success) {
                is2FAEnabled = true;
                update2FAStatus();
                
                if (backupCodesList && result.backup_codes && result.backup_codes.length > 0) {
                    backupCodesList.innerHTML = '';
                    result.backup_codes.forEach(code => {
                        const li = document.createElement('li');
                        li.textContent = code;
                        backupCodesList.appendChild(li);
                    });
                    if(backupCodesModal) backupCodesModal.show();
                } else {
                    window.showInfoModal('Éxito', '2FA activado correctamente.', true);
                }
                
                verifyForm.reset();
            } else {
                throw new Error(result.error || 'Código inválido');
            }
        } catch (e) {
            console.error(e);
            window.showInfoModal('Error', window.formatNetworkError(e), false);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Activar y Verificar';
        }
    });

    disableBtn?.addEventListener('click', async () => {
        const codeInput = document.getElementById('disable-code');
        const code = codeInput ? codeInput.value.trim() : '';

        if (!code) {
             window.showInfoModal('Código Requerido', 'Por seguridad, debes ingresar el código actual de tu autenticador para desactivar la protección.', false);
             if(codeInput) codeInput.focus();
             return;
        }

        // Antes esto era un window.prompt() nativo. El orden (contraseña y
        // después confirmación) se mantiene a propósito: askPassword resuelve
        // recién en 'hidden.bs.modal', o sea con su modal YA cerrado, así el
        // showConfirmModal que sigue no se abre encima de otro modal a medio
        // cerrar (eso deja backdrops huérfanos).
        const password = await askPassword(
            'Confirma tu contraseña',
            'Por seguridad, ingresa tu contraseña para desactivar el doble factor.'
        );
        if (!password) return;

        const confirmed = await window.showConfirmModal(
            'Confirmar Desactivación',
            '¿Estás seguro de que quieres desactivar 2FA? Tu cuenta será menos segura.'
        );
        if (!confirmed) return;

        disableBtn.disabled = true;
        disableBtn.textContent = 'Desactivando...';

        try {
            const response = await fetch('../api/?accion=disable2FA', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code, password: password })
            });
            
            const result = await window.parseJsonResponse(response);

            if (!result.success) {
                throw new Error(result.error || "Código incorrecto o error desconocido");
            }
            
            is2FAEnabled = false;
            update2FAStatus();
            if(codeInput) codeInput.value = '';
            window.showInfoModal('2FA Desactivado', 'El doble factor ha sido desactivado correctamente.', true);

        } catch (e) {
            console.error(e);
            window.showInfoModal('Error al Desactivar', window.formatNetworkError(e), false);
        } finally {
            disableBtn.disabled = false;
            disableBtn.textContent = 'Confirmar y Desactivar';
        }
    });

    getProfileStatus();
});