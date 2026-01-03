document.addEventListener('DOMContentLoaded', async () => {
    const isDashboard = window.location.pathname.includes('/dashboard/') || window.location.pathname.includes('/perfil/');
    
    if (!isDashboard) return;

    try {
        const apiUrl = (typeof baseUrlJs !== 'undefined' ? baseUrlJs : '..') + '/api/?accion=checkSystemStatus';
        
        const response = await fetch(apiUrl);
        const data = await response.json();

        if (data.success && data.status.available === false) {
            showLockModal(data.status);
            disableInterface();
        }
    } catch (error) {
        console.error("Error verificando estado del sistema:", error);
    }
});

function showLockModal(status) {
    const modalHtml = `
    <div class="modal fade" id="systemLockModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" style="z-index: 10000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white justify-content-center border-0">
                    <h4 class="modal-title fw-bold"><i class="bi bi-shop-window"></i> Cerrado Temporalmente</h4>
                </div>
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <div class="spinner-grow text-danger" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Cerrado</span>
                        </div>
                    </div>
                    <h3 class="mb-3 text-dark fw-bold">Estamos de Vacaciones</h3>
                    
                    <div class="alert alert-light border border-danger text-danger mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i> ${status.message}
                    </div>

                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <small class="text-muted text-uppercase fw-bold">Reanudamos operaciones el:</small>
                            <h4 class="text-dark mt-2 fw-bold">
                                ${new Date(status.ends_at).toLocaleDateString()} 
                                <small class="text-muted fs-6">${new Date(status.ends_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small>
                            </h4>
                        </div>
                    </div>
                    
                    <p class="small text-muted mb-0">Agradecemos su comprensión. Podrá realizar envíos nuevamente en la fecha indicada.</p>
                </div>
                <div class="modal-footer justify-content-center bg-light border-0">
                    <a href="../logout.php" class="btn btn-outline-secondary btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modalEl = document.getElementById('systemLockModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function disableInterface() {
    const selectors = [
        'button[type="submit"]', 
        '.btn-primary', 
        '.btn-success', 
        'input[type="submit"]',
        '#btn-calcular', 
        'a[href*="crear"]' 
    ];
    
    const elements = document.querySelectorAll(selectors.join(','));
    elements.forEach(el => {
        el.classList.add('disabled');
        el.setAttribute('disabled', 'disabled');
        el.style.pointerEvents = 'none';
    });
}