document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.sessionWatch !== '1') return;

    let isChecking = false;
    const CHECK_INTERVAL = 30000;
    const baseUrl = document.body.dataset.baseUrl || '';

    const checkStatus = async () => {
        if (isChecking || document.hidden) return;
        isChecking = true;
        try {
            const response = await fetch(`${baseUrl}/api/?accion=checkSessionStatus&_=${new Date().getTime()}`);
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.needs_refresh) {
                    window.location.reload();
                }
            }
        } catch (error) {
        } finally {
            isChecking = false;
        }
    };
    setInterval(checkStatus, CHECK_INTERVAL);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) checkStatus();
    });
});
