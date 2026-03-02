document.addEventListener('DOMContentLoaded', () => {
    checkSystemStatus();
    setInterval(checkSystemStatus, 45000);
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
        if (!response.ok) return;
        
        const data = await response.json();
        const alertBar = document.getElementById('holiday-alert-bar');
        if (alertBar) {
            if (data.holiday_warning) {
                const msgElement = document.getElementById('holiday-message');
                const dateElement = document.getElementById('holiday-date');
                const titleElement = document.getElementById('holiday-title');
                
                if (titleElement) titleElement.textContent = data.holiday_warning.title || 'AVISO';
                if (msgElement) msgElement.textContent = data.holiday_warning.message;
                
                if (dateElement) {
                    const endDate = new Date(data.holiday_warning.ends_at);
                    const options = { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' };
                    dateElement.textContent = endDate.toLocaleDateString('es-CL', options);
                }

                alertBar.classList.remove('d-none');
                setTimeout(() => alertBar.classList.add('show'), 100);
            } else {
                alertBar.classList.remove('show');
                setTimeout(() => alertBar.classList.add('d-none'), 600);
            }
        }

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
        } else {
            cleanupLockState();
        }

    } catch (error) {
        // console.warn("Polling omitido temporalmente:", error);
    }
}

function showLockModal(status) {
    if (document.getElementById('system-lock-modal')) return; 

    const modalHtml = `
        <div id="system-lock-backdrop" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); z-index: 9998; backdrop-filter: blur(8px);"></div>
        <div id="system-lock-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 40px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); z-index: 9999; text-align: center; max-width: 90%; width: 450px;">
            <div style="background: #fee2e2; color: #dc2626; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i class="bi bi-lock-fill" style="font-size: 2.5rem;"></i>
            </div>
            <h3 style="color: #111827; font-weight: 700; margin-bottom: 16px; font-size: 1.5rem;">Sistema en Mantenimiento</h3>
            <p style="color: #4b5563; font-size: 1.1rem; line-height: 1.5; margin-bottom: 24px; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                ${status.message}
            </p>
            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                <p style="color: #6b7280; font-size: 0.95rem; margin-bottom: 16px;">
                    Serás desconectado por seguridad en <strong id="lock-countdown" style="color: #dc2626; font-size: 1.2rem;">30</strong> segundos.
                </p>
                <a href="${status.logoutUrl}" style="display: inline-block; background: #dc2626; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; width: 100%; transition: background 0.2s;">
                    Cerrar Sesión Ahora
                </a>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';

    startLogoutCountdown(30, status.logoutUrl);
}

function startLogoutCountdown(seconds, logoutUrl) {
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
        el.tabIndex = 0;
    });
}