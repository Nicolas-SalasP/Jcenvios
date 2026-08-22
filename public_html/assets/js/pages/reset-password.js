document.getElementById('reset-password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = document.getElementById('token').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const feedback = document.getElementById('feedback-message');

    if (newPassword !== confirmPassword) {
        feedback.textContent = 'Las contraseñas no coinciden.';
        feedback.className = 'alert alert-danger';
        return;
    }
    if (newPassword.length < 8) {
        feedback.textContent = 'La contraseña debe tener al menos 8 caracteres.';
        feedback.className = 'alert alert-danger';
        return;
    }

    // Sin deshabilitar el botón, un doble click mandaba dos POST a
    // performPasswordReset (el segundo con el token ya consumido, mostrando un
    // falso "token inválido" después del éxito real).
    const submitButton = e.target.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';
    if (submitButton) {
        if (submitButton.disabled) return;
        submitButton.disabled = true;
        submitButton.textContent = 'Guardando...';
    }

    const restoreButton = () => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    };

    try {
        const response = await fetch('../api/?accion=performPasswordReset', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ token, newPassword })
        });
        // Si el servidor responde HTML (500), .json() tira un SyntaxError con
        // "Unexpected token '<'" que no le dice nada al usuario.
        if (!response.ok && response.status >= 500) {
            throw new Error('El servidor no pudo procesar la solicitud. Intenta de nuevo en unos minutos.');
        }
        const result = await response.json();
        if (result.success) {
            feedback.className = 'alert alert-success';
            feedback.textContent = result.message;
            // El form se oculta: el botón queda deshabilitado a propósito.
            e.target.style.display = 'none';
        } else {
            feedback.className = 'alert alert-danger';
            feedback.textContent = result.error || 'No se pudo cambiar la contraseña.';
            restoreButton();
        }
    } catch (error) {
        feedback.className = 'alert alert-danger';
        feedback.textContent = window.formatNetworkError
            ? window.formatNetworkError(error, 'Error de conexión con el servidor.')
            : 'Error de conexión con el servidor.';
        restoreButton();
    }
});