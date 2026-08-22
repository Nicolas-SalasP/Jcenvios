/**
 * Recarga el cuerpo de la tabla de pendientes.
 *
 * @param {{auto?: boolean}} opts  auto=true cuando viene del polling de 10s.
 *        En ese caso NO se refresca si el operador tiene un modal de trabajo
 *        abierto: reemplazar el <tbody> le borraba de abajo la fila con la que
 *        estaba operando (mismo bug ya arreglado en admin.js). El refresh
 *        manual y el de los filtros siempre corren.
 */
function cargarTablaPendientes(opts) {
    const isAuto = !!(opts && opts.auto);
    if (isAuto && document.querySelector('.modal.show')) return;

    const btn = document.getElementById('btnRefresh');
    const icon = btn ? btn.querySelector('i') : null;
    if (icon) icon.classList.add('spin-anim');
    if (btn) btn.disabled = true;
    const form = document.getElementById('op-filter-form');
    const qs = form ? new URLSearchParams(new FormData(form)).toString() : '';
    fetch('get_pendientes.php' + (qs ? ('?' + qs) : ''))
        .then(r => {
            // Sin este chequeo, un 500 de PHP se inyectaba tal cual (página de
            // error completa) dentro del <tbody>.
            if (!r.ok) throw new Error('El servidor respondió ' + r.status);
            return r.text();
        })
        .then(html => { document.getElementById('tablaPendientesBody').innerHTML = html; })
        .catch(e => {
            console.error('Error recargando tabla:', e);
            // El refresh automático falla en silencio (reintenta en 10s); el
            // manual sí avisa, porque el operador está esperando el resultado.
            if (!isAuto && window.showInfoModal) {
                window.showInfoModal(
                    'Error al actualizar',
                    window.formatNetworkError
                        ? window.formatNetworkError(e, 'No se pudo actualizar la lista de pendientes.')
                        : 'No se pudo actualizar la lista de pendientes.',
                    false
                );
            }
        })
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
    setInterval(() => cargarTablaPendientes({ auto: true }), 10000);

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
