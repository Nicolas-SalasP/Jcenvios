document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('forgot-password-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const feedback = document.getElementById('feedback-message');
        const submitButton = form.querySelector('button[type="submit"]');
        const emailInput = document.getElementById('email');
        
        feedback.textContent = '';
        feedback.className = 'mt-3';
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...';

        try {
            const response = await fetch('../api/?accion=requestPasswordReset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: emailInput.value })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                feedback.className = 'alert alert-success';
                feedback.textContent = result.message || 'Enlace enviado con éxito.';
                form.reset();
            } else {
                throw new Error(result.error || 'No se pudo procesar la solicitud.');
            }
        } catch (error) {
            feedback.className = 'alert alert-danger';
            feedback.textContent = error.message;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Enviar Enlace de Recuperación';
        }
    });
});