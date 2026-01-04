document.addEventListener('DOMContentLoaded', async () => {
    const apiUrl = 'api/?accion=checkSystemStatus';

    try {
        const response = await fetch(apiUrl);
        const data = await response.json();
        if (data.success && data.status.available === false) {
            showPublicAlert(data.status);
        }
    } catch (error) {
        console.error("No se pudo verificar el estado del sistema", error);
    }
});

function showPublicAlert(status) {
    const alertBar = document.getElementById('holiday-alert-bar');
    const msgElement = document.getElementById('holiday-message');
    const dateElement = document.getElementById('holiday-date');

    if (alertBar && msgElement && dateElement) {
        msgElement.textContent = status.message;
        
        const endDate = new Date(status.ends_at);
        const options = { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' };
        dateElement.textContent = endDate.toLocaleDateString('es-CL', options);

        alertBar.classList.remove('d-none');
        setTimeout(() => {
            alertBar.classList.add('show');
        }, 10);
    }
}