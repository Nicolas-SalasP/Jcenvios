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

    // El color va dentro de un atributo `style`, y escapeHtml NO escapa
    // comillas: un valor tipo `red" onmouseover="...` se saldría del atributo.
    // Solo se aceptan colores hex, cualquier otra cosa cae al negro.
    const safeHexColor = (value) => {
        const v = String(value || '').trim();
        return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v) ? v : '#000000';
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
                <div class="card h-100 shadow-sm" style="border-top: 4px solid ${safeHexColor(c.ColorHex)};">
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
            if (!res.ok && res.status >= 500) {
                throw new Error('El servidor no pudo procesar la solicitud (error ' + res.status + ').');
            }
            const data = await res.json();
            if (data.success) {
                renderCuentas(data.cuentas || []);
            } else {
                container.innerHTML = '';
                window.showInfoModal('Error', data.error || 'No se pudieron cargar las cuentas.', false);
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = '';
            window.showInfoModal('Error', window.formatNetworkError(e, 'Error de conexión al cargar las cuentas.'), false);
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
            if (!res.ok) throw new Error('El servidor respondió ' + res.status);
            const paises = await res.json();
            if (!Array.isArray(paises)) throw new Error('Respuesta inesperada del servidor.');

            // Se arma con nodos en vez de innerHTML: el nombre del país sale de
            // la BD y escapeHtml no escapa comillas, así que interpolarlo en un
            // atributo no sería seguro.
            selectPais.replaceChildren();
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecciona un país...';
            selectPais.appendChild(placeholder);

            paises.forEach((p) => {
                const opt = document.createElement('option');
                opt.value = p.PaisID;
                opt.textContent = p.NombrePais || '';
                selectPais.appendChild(opt);
            });
        } catch (e) {
            // Antes esto moría en el console.error y el select quedaba vacío
            // sin ninguna explicación para el usuario.
            console.error('Error cargando países', e);
            selectPais.replaceChildren();
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Error al cargar los países';
            selectPais.appendChild(opt);
            selectPais.disabled = true;
            window.showInfoModal(
                'Error',
                window.formatNetworkError(e, 'No se pudo cargar la lista de países. Recarga la página e intenta de nuevo.'),
                false
            );
        }
    })();
});
