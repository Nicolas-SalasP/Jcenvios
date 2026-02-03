document.addEventListener('DOMContentLoaded', async () => {
    const isInSubfolder = window.location.pathname.includes('/dashboard/') || 
                          window.location.pathname.includes('/admin/') || 
                          window.location.pathname.includes('/operador/');
    
    const apiUrl = (isInSubfolder ? '../api/' : 'api/') + '?accion=checkSystemStatus';

    try {
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        if (data.success) {
            if (data.active === false) {
                showPublicAlert({
                    message: data.message || 'Cerrado temporalmente',
                    ends_at: data.ends_at
                }, 'danger');
            } 
            else if (data.holiday_warning) {
                showPublicAlert({
                    message: data.holiday_warning.message,
                    ends_at: data.holiday_warning.ends_at
                }, 'info');
            }
        }
    } catch (error) {
        console.error(error);
    }
});

function showPublicAlert(status, type = 'danger') {
    const alertBar = document.getElementById('holiday-alert-bar');
    const msgElement = document.getElementById('holiday-message');
    const dateElement = document.getElementById('holiday-date');

    if (alertBar && msgElement) {
        if (type === 'info') {
            alertBar.classList.remove('alert-danger', 'bg-danger');
            alertBar.classList.add('alert-info', 'bg-info');
        } else {
            alertBar.classList.remove('alert-info', 'bg-info');
            alertBar.classList.add('alert-danger', 'bg-danger');
        }

        msgElement.textContent = status.message;
        
        if (dateElement && status.ends_at) {
            const endDate = new Date(status.ends_at);
            const options = { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' };
            const dateStr = endDate.toLocaleDateString('es-CL', options);
            dateElement.textContent = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
            dateElement.style.display = 'inline';
        } else if (dateElement) {
            dateElement.style.display = 'none';
        }

        alertBar.classList.remove('d-none');
        setTimeout(() => {
            alertBar.classList.add('show');
        }, 10);
    }
}