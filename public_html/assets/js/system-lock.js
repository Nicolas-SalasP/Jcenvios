document.addEventListener('DOMContentLoaded', () => {
    // Ejecutar inmediatamente
    checkSystemStatus();
    // Y revisar cada 30 segundos
    setInterval(checkSystemStatus, 30000);
});

async function checkSystemStatus() {
    try {
        // Detectar ruta correcta para la API
        const basePath = window.location.pathname.includes('/dashboard/') || 
                         window.location.pathname.includes('/admin/') || 
                         window.location.pathname.includes('/operador/')
            ? '../api/' 
            : 'api/';
            
        // Detectar si estamos dentro del sistema (Logueados)
        const isInternalPage = window.location.pathname.includes('/dashboard/') || 
                               window.location.pathname.includes('/admin/') || 
                               window.location.pathname.includes('/operador/');

        const apiUrl = basePath + '?accion=checkSystemStatus';
        
        const response = await fetch(apiUrl);
        const data = await response.json();

        // Limpiar estados previos antes de aplicar el nuevo
        cleanupLockState();

        // ---------------------------------------------------------
        // CASO 1: BLOQUEO TOTAL (active = false)
        // ---------------------------------------------------------
        // ESTE APLICA SIEMPRE, INCLUSO EN LOGIN/INDEX
        if (data.active === false) {
            showLockModal({
                message: data.message || 'Sistema pausado temporalmente.',
                reason: data.reason
            });
            disableInterface(); 
            return;
        }

        // ---------------------------------------------------------
        // CASO 2: SISTEMA ACTIVO PERO CON ADVERTENCIA (Informativo)
        // ---------------------------------------------------------
        // ESTE SOLO APLICA SI ESTAMOS DENTRO DEL DASHBOARD
        if (data.active === true && data.holiday_warning) {
            if (isInternalPage) {
                showWarningBanner(data.holiday_warning.title, data.holiday_warning.message);
            }
            // Si es página pública (Login/Index), NO hacemos nada (no mostramos barra)
            return;
        }

        // CASO 3: TODO NORMAL (Ya se limpió al inicio de la función)

    } catch (error) {
        console.error("Error verificando estado del sistema:", error);
    }
}

function cleanupLockState() {
    // Eliminar modal de bloqueo si existe
    const lockModal = document.getElementById('systemLockModal');
    if (lockModal) {
        const bsModal = bootstrap.Modal.getInstance(lockModal);
        if (bsModal) bsModal.hide();
        lockModal.remove();
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }

    // Eliminar barra de advertencia
    const banner = document.getElementById('system-warning-banner');
    if (banner) banner.remove();

    // Rehabilitar interfaz
    enableInterface();
}

// --- VISUALIZACIÓN: BLOQUEO TOTAL (ROJO) ---
function showLockModal(status) {
    if (document.getElementById('systemLockModal')) return; 

    const modalHtml = `
    <div class="modal fade" id="systemLockModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" style="z-index: 10000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white justify-content-center border-0">
                    <h4 class="modal-title fw-bold"><i class="bi bi-slash-circle"></i> Sistema Pausado</h4>
                </div>
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <div class="spinner-grow text-danger" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Cerrado</span>
                        </div>
                    </div>
                    <h3 class="mb-3 text-dark fw-bold">Atención</h3>
                    
                    <div class="alert alert-light border border-danger text-danger mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i> ${status.message}
                    </div>
                    
                    <p class="small text-muted mb-0">Por favor intente nuevamente más tarde.</p>
                </div>
                <div class="modal-footer justify-content-center bg-light border-0">
                    <a href="../logout.php" class="btn btn-outline-secondary btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modalEl = document.getElementById('systemLockModal');
    const modal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false
    });
    modal.show();
}

// --- VISUALIZACIÓN: BARRA INFORMATIVA (AMARILLA) ---
function showWarningBanner(title, msg) {
    if (document.getElementById('system-warning-banner')) return; 

    const banner = document.createElement('div');
    banner.id = 'system-warning-banner';
    banner.className = 'alert alert-warning text-center mb-0 fw-bold border-0 rounded-0 shadow-sm position-relative animate__animated animate__fadeInDown';
    banner.style.zIndex = '1030';
    
    banner.innerHTML = `
        <div class="container">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <span class="text-uppercase me-2">${title}:</span>
            <span class="fw-normal">${msg}</span>
        </div>
    `;

    const navbar = document.querySelector('nav') || document.querySelector('header');
    if (navbar && navbar.parentNode) {
        navbar.parentNode.insertBefore(banner, navbar.nextSibling);
    } else {
        document.body.prepend(banner);
    }
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

function enableInterface() {
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
        el.classList.remove('disabled');
        el.removeAttribute('disabled');
        el.style.pointerEvents = '';
    });
}