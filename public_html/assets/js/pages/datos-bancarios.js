document.addEventListener('DOMContentLoaded', () => {
    const selectPais = document.getElementById('selectPaisOrigen');
    const container = document.getElementById('cuentasContainer');
    const emptyMsg = document.getElementById('cuentasEmpty');

    if (!selectPais || !container) return;

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    };

    const renderCuentas = (cuentas) => {
        container.innerHTML = '';
        if (!cuentas.length) {
            emptyMsg.classList.remove('d-none');
            return;
        }
        emptyMsg.classList.add('d-none');
        container.innerHTML = cuentas.map((c) => `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm" style="border-top: 4px solid ${escapeHtml(c.ColorHex || '#000000')};">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">${escapeHtml(c.FormaPagoNombre)}</h6>
                        <h5 class="card-title mb-3">${escapeHtml(c.Banco)}</h5>
                        <dl class="row mb-0 small">
                            <dt class="col-5">Titular</dt><dd class="col-7">${escapeHtml(c.Titular)}</dd>
                            <dt class="col-5">Tipo cuenta</dt><dd class="col-7">${escapeHtml(c.TipoCuenta)}</dd>
                            <dt class="col-5">N° cuenta</dt><dd class="col-7">${escapeHtml(c.NumeroCuenta)}</dd>
                            <dt class="col-5">RUT/Doc.</dt><dd class="col-7">${escapeHtml(c.RUT)}</dd>
                        </dl>
                        ${c.Instrucciones ? `<p class="small text-muted mt-2 mb-0">${escapeHtml(c.Instrucciones)}</p>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    };

    const cargarCuentas = async (paisId) => {
        container.innerHTML = '<div class="col-12 text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>';
        emptyMsg.classList.add('d-none');
        try {
            const res = await fetch('../api/?accion=getDatosBancariosPorPais&paisId=' + encodeURIComponent(paisId));
            const data = await res.json();
            if (data.success) {
                renderCuentas(data.cuentas || []);
            } else {
                container.innerHTML = '';
                window.showInfoModal('Error', data.error || 'No se pudieron cargar las cuentas.', false);
            }
        } catch (e) {
            container.innerHTML = '';
            window.showInfoModal('Error', 'Error de conexión al cargar las cuentas.', false);
        }
    };

    selectPais.addEventListener('change', () => {
        const paisId = selectPais.value;
        container.innerHTML = '';
        emptyMsg.classList.add('d-none');
        if (paisId) cargarCuentas(paisId);
    });

    (async () => {
        try {
            const res = await fetch('../api/?accion=getPaises&rol=Origen');
            const paises = await res.json();
            selectPais.innerHTML = '<option value="">Selecciona un país...</option>' +
                paises.map((p) => `<option value="${p.PaisID}">${escapeHtml(p.NombrePais)}</option>`).join('');
        } catch (e) {
            console.error('Error cargando países', e);
        }
    })();
});
