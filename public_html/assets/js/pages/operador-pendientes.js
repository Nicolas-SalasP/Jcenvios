/**
 * Recarga el cuerpo de la tabla de pendientes.
 *
 * Antes: se saltaba ENTERO el refresh automático mientras hubiera cualquier
 * modal de Bootstrap abierto ("Rechazar", "Ver motivo de pausa", "Copiar
 * datos", etc — no solo uno relacionado a la tabla). El operador que dejaba
 * un modal abierto unos segundos revisando una orden se comía varios ciclos
 * de 10s sin refrescar, y al cerrarlo tenía que esperar hasta el próximo tick
 * — de ahí el reporte de "tarda mucho" y "a veces no recarga". Mismo bug ya
 * encontrado y arreglado en admin-transacciones.js (ver su comentario en
 * startAutoRefresh): los modales viven fuera de #tablaPendientesBody, así que
 * reemplazar el <tbody> no les afecta, no hace falta saltarse el refresh.
 */
function cargarTablaPendientes(opts) {
    const isAuto = !!(opts && opts.auto);

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
            // El refresh automático falla en silencio (reintenta en 5s); el
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
    setInterval(() => cargarTablaPendientes({ auto: true }), 5000);
    // Refresco inmediato apenas se cierra cualquier modal, para no esperar
    // hasta el próximo ciclo si el operador justo actuó sobre una orden
    // mientras el modal estaba abierto (mismo patrón que admin-transacciones.js).
    document.addEventListener('hidden.bs.modal', () => cargarTablaPendientes({ auto: true }));

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
