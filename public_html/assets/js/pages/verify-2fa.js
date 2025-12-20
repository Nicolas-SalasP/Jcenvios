document.addEventListener('DOMContentLoaded', function() {
    const stepSelection = document.getElementById('step-selection');
    const stepCode = document.getElementById('step-code');
    const methodButtons = document.querySelectorAll('.select-method');
    const btnBack = document.getElementById('btn-back-to-selection');
    const descMethod = document.getElementById('method-description');
    const form = document.getElementById('verify-2fa-form');
    const inputCode = document.getElementById('2fa-code');
    const btnResend = document.getElementById('btn-resend');
    const resendContainer = document.getElementById('resend-container');
    const resendStatus = document.getElementById('resend-status');
    const btnSubmit = document.getElementById('btn-submit');

    let currentMethod = '';

    methodButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            currentMethod = btn.getAttribute('data-method');
            
            if (currentMethod === 'email' || currentMethod === 'whatsapp' || currentMethod === 'sms') {
                btn.disabled = true;
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Enviando...';

                const success = await sendCode(currentMethod);
                
                if (!success) {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    return;
                }
                
                descMethod.innerHTML = `Hemos enviado un código a tu <strong>${currentMethod === 'email' ? 'correo' : 'teléfono'}</strong> registrado.`;
                resendContainer.style.display = 'block';
            } else {
                descMethod.innerText = 'Ingresa el código generado por tu aplicación de seguridad.';
                resendContainer.style.display = 'none';
            }

            stepSelection.style.display = 'none';
            stepCode.style.display = 'block';
            inputCode.value = '';
            inputCode.focus();
        });
    });

    btnBack.addEventListener('click', () => {
        stepCode.style.display = 'none';
        stepSelection.style.display = 'block';
        methodButtons.forEach(b => b.disabled = false);
        resendStatus.innerHTML = '';
    });

    async function sendCode(method) {
        try {
            const response = await fetch('api/?accion=send2faCode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ method })
            });
            const res = await response.json();
            
            if (!res.success) {
                window.showInfoModal('Error', res.error || 'No se pudo enviar el código de seguridad.', false);
                return false;
            }
            return true;
        } catch (e) {
            window.showInfoModal('Error', 'Error de red. Verifica tu conexión.', false);
            return false;
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = inputCode.value.trim();
        
        if (code.length !== 6) {
            window.showInfoModal('Atención', 'El código debe tener 6 dígitos.', false);
            return;
        }

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';

        try {
            const response = await fetch('api/?accion=verify2fa', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = result.redirect;
            } else {
                window.showInfoModal('Acceso Denegado', result.error || 'El código ingresado es incorrecto.', false);
                inputCode.value = '';
                inputCode.focus();
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Verificar y Entrar';
            }
        } catch (error) {
            console.error('Error:', error);
            window.showInfoModal('Error', 'Fallo crítico al verificar.', false);
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Verificar y Entrar';
        }
    });

    btnResend.addEventListener('click', async () => {
        btnResend.disabled = true;
        resendStatus.innerHTML = '<span class="text-primary">Enviando nuevo código...</span>';

        const success = await sendCode(currentMethod);

        if (success) {
            resendStatus.innerHTML = '<span class="text-success fw-bold">¡Enviado! Revisa tu bandeja de entrada.</span>';
            
            let counter = 60;
            const interval = setInterval(() => {
                counter--;
                btnResend.innerText = `Reenviar en ${counter}s`;
                if (counter <= 0) {
                    clearInterval(interval);
                    btnResend.disabled = false;
                    btnResend.innerText = 'Reenviar código';
                    resendStatus.innerHTML = '';
                }
            }, 1000);
        } else {
            btnResend.disabled = false;
            resendStatus.innerHTML = '';
        }
    });

    inputCode.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});