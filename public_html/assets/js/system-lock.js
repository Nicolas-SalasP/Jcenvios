document.addEventListener('DOMContentLoaded', () => {
    checkSystemStatus();
    setInterval(checkSystemStatus, 30000);
});

let logoutTimer = null;
let countdownInterval = null;

async function checkSystemStatus() {
    try {
        const isInSubfolder = window.location.pathname.includes('/dashboard/') || 
                              window.location.pathname.includes('/admin/') || 
                              window.location.pathname.includes('/operador/');
        
        const basePath = isInSubfolder ? '../api/' : 'api/';
        const apiUrl = basePath + '?accion=checkSystemStatus&_=' + new Date().getTime();
        
        const response = await fetch(apiUrl);
        const data = await response.json();

        if (!data.logged_in) {
            cleanupLockState();
            return;
        }

        if (data.role === 'Admin' || data.role === 'Operador') {
            cleanupLockState();
            return;
        }

        if (data.active === false) {
            const logoutPath = isInSubfolder ? '../logout.php' : 'logout.php';

            showLockModal({
                message: data.message || 'Servicio Suspendido Temporalmente',
                logoutUrl: logoutPath
            });
            
            disableInterface();

        } else if (data.holiday_warning) {
            cleanupLockState();
        } else {
            cleanupLockState();
        }

    } catch (error) {
        console.error(error);
    }
}

function showLockModal({ message, logoutUrl }) {
    if (document.getElementById('system-lock-modal')) return;

    const modalBackdrop = document.createElement('div');
    modalBackdrop.id = 'system-lock-backdrop';
    modalBackdrop.className = 'modal-backdrop show';
    modalBackdrop.style.zIndex = '1050';

    const modal = document.createElement('div');
    modal.id = 'system-lock-modal';
    modal.className = 'modal fade show d-block';
    modal.setAttribute('tabindex', '-1');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.style.zIndex = '1055';
    modal.style.backgroundColor = 'rgba(0,0,0,0.5)';

    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i>Acceso Restringido
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <h4 class="mb-3 fw-bold text-dark">${message}</h4>
                    
                    <div class="alert alert-warning d-inline-block mt-3">
                        Cerrando sesión en <strong id="lock-countdown" class="fs-5">10</strong> segundos...
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-light">
                    <a href="${logoutUrl}" class="btn btn-danger w-100 fw-bold">
                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión Ahora
                    </a>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modalBackdrop);
    document.body.appendChild(modal);
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';

    startCountdown(10, logoutUrl);
}

function startCountdown(seconds, logoutUrl) {
    let counter = seconds;
    const countSpan = document.getElementById('lock-countdown');
    
    if (countdownInterval) clearInterval(countdownInterval);
    if (logoutTimer) clearTimeout(logoutTimer);

    countdownInterval = setInterval(() => {
        counter--;
        if (countSpan) countSpan.textContent = counter;
        
        if (counter <= 0) {
            clearInterval(countdownInterval);
            window.location.href = logoutUrl;
        }
    }, 1000);

    logoutTimer = setTimeout(() => {
        window.location.href = logoutUrl;
    }, (seconds + 1) * 1000);
}

function cleanupLockState() {
    const modal = document.getElementById('system-lock-modal');
    const backdrop = document.getElementById('system-lock-backdrop');

    if (modal) modal.remove();
    if (backdrop) backdrop.remove();
    
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';

    if (countdownInterval) clearInterval(countdownInterval);
    if (logoutTimer) clearTimeout(logoutTimer);
    
    enableInterface();
}

function disableInterface() {
    const elements = document.querySelectorAll('button, input, select, textarea, a');
    elements.forEach(el => {
        if (el.closest('#system-lock-modal')) return;
        el.style.pointerEvents = 'none';
        el.tabIndex = -1;
    });
}

function enableInterface() {
    const elements = document.querySelectorAll('button, input, select, textarea, a');
    elements.forEach(el => {
        el.style.pointerEvents = '';
        el.removeAttribute('tabindex');
    });
}