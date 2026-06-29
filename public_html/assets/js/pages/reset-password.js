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

    try {
        const response = await fetch('../api/?accion=performPasswordReset', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ token, newPassword })
        });
        const result = await response.json();
        if (result.success) {
            feedback.className = 'alert alert-success';
            feedback.textContent = result.message;
            e.target.style.display = 'none';
        } else {
            feedback.className = 'alert alert-danger';
            feedback.textContent = result.error;
        }
    } catch (error) {
        feedback.className = 'alert alert-danger';
        feedback.textContent = 'Error de conexión con el servidor.';
    }
});