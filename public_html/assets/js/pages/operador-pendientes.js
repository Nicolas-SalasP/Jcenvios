function cargarTablaPendientes() {
    const btn = document.getElementById('btnRefresh');
    const icon = btn ? btn.querySelector('i') : null;
    if (icon) icon.classList.add('spin-anim');
    if (btn) btn.disabled = true;
    const form = document.getElementById('op-filter-form');
    const qs = form ? new URLSearchParams(new FormData(form)).toString() : '';
    fetch('get_pendientes.php' + (qs ? ('?' + qs) : ''))
        .then(r => r.text())
        .then(html => { document.getElementById('tablaPendientesBody').innerHTML = html; })
        .catch(e => console.error('Error recargando tabla:', e))
        .finally(() => { if (icon) icon.classList.remove('spin-anim'); if (btn) btn.disabled = false; });
}

const opPendientesStyle = document.createElement('style');
opPendientesStyle.innerHTML = `
    .spin-anim { animation: spin 1s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
`;
document.head.appendChild(opPendientesStyle);

document.addEventListener('DOMContentLoaded', () => {
    const btnRefresh = document.getElementById('btnRefresh');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => cargarTablaPendientes());
    }

    cargarTablaPendientes();
    setInterval(cargarTablaPendientes, 10000);

    const opForm = document.getElementById('op-filter-form');
    if (opForm) {
        opForm.addEventListener('submit', function (e) {
            e.preventDefault();
            cargarTablaPendientes();
        });
    }
    const opClear = document.getElementById('op-clear-filters');
    if (opClear && opForm) {
        opClear.addEventListener('click', function () {
            opForm.reset();
            cargarTablaPendientes();
        });
    }

    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-pause-reason-btn');
        if (btn) {
            e.preventDefault();
            const reason = btn.getAttribute('data-reason');
            const modalBodyText = document.getElementById('pause-reason-text');
            if (modalBodyText) modalBodyText.textContent = reason;

            const modalEl = document.getElementById('viewPauseReasonModal');
            if (modalEl) {
                let modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (!modalInstance) {
                    modalInstance = new bootstrap.Modal(modalEl);
                }
                modalInstance.show();
            }
        }
    });
});

if (typeof window.copyToClipboard === 'undefined') {
    window.copyToClipboard = (elementId, btnElement) => {
        const input = document.getElementById(elementId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            const orig = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="bi bi-check"></i>';
            setTimeout(() => btnElement.innerHTML = orig, 1000);
        });
    };
}
