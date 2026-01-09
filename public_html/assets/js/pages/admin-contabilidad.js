document.addEventListener('DOMContentLoaded', () => {

    // --- ESTADO GLOBAL ---
    const STATE = {
        todasLasCuentas: []
    };

    // --- REFERENCIAS AL DOM ---
    const DOM = {
        containerOrigen: document.getElementById('container-origen'),
        containerDestino: document.getElementById('container-destino'),
        filtroDestino: document.getElementById('filtro-destino'),

        // Selects Sección Transferencia
        txOrigen: document.getElementById('tx-origen-id'),
        txDestino: document.getElementById('tx-destino-id'),
        txSaldoOrigen: document.getElementById('tx-saldo-origen'),
        txSaldoDestino: document.getElementById('tx-saldo-destino'),

        // Formularios Principales
        formTx: document.getElementById('form-transferencia'),
        formResumen: document.getElementById('form-resumen-gastos'),

        // Resultados Historial
        resumenResultado: document.getElementById('resumen-resultado'),
        resumenTitulo: document.getElementById('resumen-titulo'),
        resumenTotalGastado: document.getElementById('resumen-total-gastado'),
        resumenMovimientosTbody: document.getElementById('resumen-movimientos-tbody')
    };

    // --- UTILIDADES ---

    const requiereDecimales = (currencyCode) => {
        const conDecimales = ['USD', 'PEN', 'EUR', 'BRL', 'GBP'];
        return conDecimales.includes(currencyCode);
    };

    const numberFormatter = (currencyCode, value) => {
        try {
            const decimals = requiereDecimales(currencyCode) ? 2 : 0;
            return new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: currencyCode || 'USD',
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(parseFloat(value) || 0);
        } catch (e) { return value; }
    };

    // --- CARGA INICIAL DE DATOS ---
    window.cargarDatosGenerales = async () => {
        try {
            if (DOM.containerOrigen) DOM.containerOrigen.innerHTML = '<div class="col-12 text-center p-4"><div class="spinner-border text-primary"></div></div>';
            if (DOM.containerDestino) DOM.containerDestino.innerHTML = '<div class="col-12 text-center p-4"><div class="spinner-border text-success"></div></div>';

            const response = await fetch('../api/?accion=getContabilidadGlobal');
            const result = await response.json();

            if (!result.success) throw new Error(result.error);

            STATE.todasLasCuentas = result.origen || [];

            // DEBUG: Descomenta esto si necesitas ver qué IDs trae tu API
            // console.log("Cuentas cargadas:", STATE.todasLasCuentas);

            renderizarInterfaz();
        } catch (error) {
            console.error("Error al cargar datos:", error);
            if (window.showInfoModal) window.showInfoModal('Error', 'No se pudieron cargar los saldos.', false);
        }
    };

    const renderizarInterfaz = () => {
        renderOrigen();
        renderDestino();
        actualizarTodosLosSelects();
    };

    // --- RENDERIZADO DE CUADRADOS (GRID) ---

    const renderOrigen = () => {
        if (!DOM.containerOrigen) return;

        // CORRECCIÓN CLAVE: 
        // Filtramos por RolCuentaID == 1 (Origen según tu DB) o RolID == 1
        // Usamos == para que no importe si es string "1" o numero 1.
        const origen = STATE.todasLasCuentas.filter(acc =>
            acc.RolCuentaID == 1 || acc.RolID == 1 || acc.Rol === 'Origen'
        );

        DOM.containerOrigen.innerHTML = '';
        if (origen.length === 0) {
            DOM.containerOrigen.innerHTML = '<div class="col-12 text-center text-muted p-3">No hay cuentas de origen.</div>';
            return;
        }

        origen.forEach(acc => {
            DOM.containerOrigen.innerHTML += `
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm border-primary border-start border-4 h-100">
                        <div class="card-body">
                            <h6 class="text-primary fw-bold text-truncate mb-1" title="${acc.Banco}">${acc.Banco}</h6>
                            <h4 class="text-dark mb-1">${numberFormatter(acc.CodigoMoneda, acc.SaldoActual)}</h4>
                            <small class="text-muted d-block text-truncate small">${acc.Titular}</small>
                        </div>
                    </div>
                </div>`;
        });
    };

    const renderDestino = () => {
        if (!DOM.containerDestino) return;

        const busqueda = (DOM.filtroDestino?.value || '').toLowerCase();

        // CORRECCIÓN CLAVE:
        // Filtramos por RolCuentaID == 2 (Destino según tu DB)
        const destino = STATE.todasLasCuentas.filter(acc => {
            // Aceptamos RolCuentaID 2, RolID 2 o explícitamente el texto 'Destino'
            const esDestino = (acc.RolCuentaID == 2 || acc.RolID == 2 || acc.Rol === 'Destino');

            const coincide = (acc.Banco || '').toLowerCase().includes(busqueda) ||
                (acc.NombrePais || '').toLowerCase().includes(busqueda);
            return esDestino && coincide;
        });

        DOM.containerDestino.innerHTML = '';
        if (destino.length === 0) {
            DOM.containerDestino.innerHTML = '<div class="col-12 text-center text-muted p-3">No hay cuentas de destino.</div>';
            return;
        }

        destino.forEach(acc => {
            const saldo = parseFloat(acc.SaldoActual);
            const alerta = saldo < 50000 ? 'border-danger' : 'border-success';
            const colorSaldo = saldo < 50000 ? 'text-danger' : 'text-success';

            DOM.containerDestino.innerHTML += `
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm ${alerta} border-start border-4 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="text-dark fw-bold text-truncate mb-0" style="max-width: 70%">${acc.Banco}</h6>
                                <span class="badge bg-light text-dark border small" style="font-size: 0.65rem;">${acc.NombrePais}</span>
                            </div>
                            <h4 class="${colorSaldo} mb-1">${numberFormatter(acc.CodigoMoneda, acc.SaldoActual)}</h4>
                            <small class="text-muted d-block text-truncate small">${acc.Titular}</small>
                        </div>
                    </div>
                </div>`;
        });
    };

    // --- LÓGICA DE SELECTS ---

    const actualizarTodosLosSelects = () => {
        const buildOptions = (list) => {
            return '<option value="">Seleccione cuenta...</option>' +
                list.map(acc => `<option value="${acc.CuentaAdminID}" data-saldo="${acc.SaldoActual}" data-moneda="${acc.CodigoMoneda}">
                        ${acc.Banco} - ${acc.NombrePais}
                   </option>`).join('');
        };

        // Aplicamos la misma lógica estricta a los selectores
        const origen = STATE.todasLasCuentas.filter(acc => acc.RolCuentaID == 1 || acc.RolID == 1 || acc.Rol === 'Origen');
        const destino = STATE.todasLasCuentas.filter(acc => acc.RolCuentaID == 2 || acc.RolID == 2 || acc.Rol === 'Destino');

        if (DOM.txOrigen) DOM.txOrigen.innerHTML = buildOptions(origen);
        if (DOM.txDestino) DOM.txDestino.innerHTML = buildOptions(destino);

        const mapaModales = {
            'recarga-origen-select': origen, 'retiro-origen-select': origen,
            'recarga-destino-select': destino, 'retiro-destino-select': destino
        };

        Object.keys(mapaModales).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = buildOptions(mapaModales[id]);
        });

        actualizarDropdownHistorial();
    };

    const actualizarDropdownHistorial = () => {
        const tipo = document.getElementById('resumen-tipo').value;
        const select = document.getElementById('resumen-entidad-id');
        if (!select) return;

        select.innerHTML = '<option value="">Seleccione...</option>';

        // Misma lógica estricta para el historial
        const list = tipo === 'banco'
            ? STATE.todasLasCuentas.filter(acc => acc.RolCuentaID == 1 || acc.RolID == 1 || acc.Rol === 'Origen')
            : STATE.todasLasCuentas.filter(acc => acc.RolCuentaID == 2 || acc.RolID == 2 || acc.Rol === 'Destino');

        list.forEach(acc => {
            select.innerHTML += `<option value="${acc.CuentaAdminID}">${acc.Banco} (${acc.NombrePais})</option>`;
        });
    };

    [DOM.txOrigen, DOM.txDestino].forEach((sel, i) => {
        if (!sel) return;
        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            const info = i === 0 ? DOM.txSaldoOrigen : DOM.txSaldoDestino;
            if (opt && opt.value) {
                info.innerHTML = `Saldo: <strong>${numberFormatter(opt.dataset.moneda, opt.dataset.saldo)}</strong>`;
            } else { info.innerText = 'Saldo: -'; }
        });
    });

    if (DOM.filtroDestino) {
        DOM.filtroDestino.addEventListener('input', renderDestino);
    }

    if (document.getElementById('resumen-tipo')) {
        document.getElementById('resumen-tipo').addEventListener('change', actualizarDropdownHistorial);
    }

    // --- ENVÍO DE FORMULARIOS ---

    const setupFormHandler = (formId, action, successMsg, isTransfer = false) => {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                let payload = {};
                if (isTransfer) {
                    payload = {
                        origen_id: DOM.txOrigen.value,
                        destino_id: DOM.txDestino.value,
                        monto_salida: document.getElementById('tx-monto-salida').value,
                        monto_entrada: document.getElementById('tx-monto-entrada').value
                    };
                } else {
                    const select = form.querySelector('select');
                    const monto = form.querySelector('input[type="number"]');
                    const desc = form.querySelector('textarea');

                    payload = {
                        bancoId: select.value,
                        monto: monto.value,
                        descripcion: desc.value
                    };
                }

                const res = await fetch(`../api/?accion=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();

                if (result.success) {
                    if (window.showInfoModal) window.showInfoModal('Éxito', successMsg, true);
                    form.reset();
                    bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
                    cargarDatosGenerales();
                } else throw new Error(result.error);
            } catch (err) {
                if (window.showInfoModal) window.showInfoModal('Error', err.message, false);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    };

    setupFormHandler('form-transferencia', 'transferenciaInterna', 'Transferencia realizada con éxito.', true);
    setupFormHandler('form-recarga-banco', 'agregarFondos', 'Ingreso registrado correctamente.');
    setupFormHandler('form-retiro-banco', 'retirarFondos', 'Retiro registrado correctamente.');
    setupFormHandler('form-recarga-pais', 'agregarFondos', 'Ajuste positivo registrado.');
    setupFormHandler('form-retiro-pais', 'retirarFondos', 'Gasto/Retiro registrado.');

    // --- LÓGICA DEL HISTORIAL ---

    if (DOM.formResumen) {
        DOM.formResumen.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('resumen-entidad-id').value;
            const [anio, mes] = document.getElementById('resumen-mes').value.split('-');

            if (!id) return;

            const btn = DOM.formResumen.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch(`../api/?accion=getResumenContable&id=${id}&mes=${mes}&anio=${anio}`);
                const result = await res.json();

                if (result.success) {
                    const data = result.resumen;
                    DOM.resumenResultado.style.display = 'block';
                    DOM.resumenTitulo.innerText = `${data.Entidad} - ${mes}/${anio}`;

                    if (DOM.resumenTotalGastado) {
                        DOM.resumenTotalGastado.innerText = numberFormatter(data.Moneda, data.TotalGastado || 0);
                    }

                    DOM.resumenMovimientosTbody.innerHTML = '';
                    if (data.Movimientos && data.Movimientos.length > 0) {
                        data.Movimientos.forEach(m => {
                            const esIngreso = ['RECARGA', 'INGRESO_VENTA', 'COMPRA_DIVISA', 'SALDO_INICIAL', 'DEPOSITO'].includes(m.TipoMovimiento);

                            DOM.resumenMovimientosTbody.innerHTML += `
                                <tr>
                                    <td><small>${new Date(m.Timestamp).toLocaleString('es-CL')}</small></td>
                                    <td><span class="badge bg-light text-dark border small">${m.NombreVisible || m.TipoMovimiento}</span></td>
                                    <td>${m.TransaccionID ? '<strong>#' + m.TransaccionID + '</strong>' : '-'}</td>
                                    <td><small>${m.Descripcion || ''}</small></td>
                                    <td>
                                        <div class="fw-bold small">${m.AdminNombre || 'Sistema'} ${m.AdminApellido || ''}</div>
                                        <div class="text-muted" style="font-size: 0.65rem;">${m.AdminEmail || ''}</div>
                                    </td>
                                    <td class="text-end fw-bold ${esIngreso ? 'text-success' : 'text-danger'}">
                                        ${esIngreso ? '+' : '-'}${numberFormatter(data.Moneda, m.Monto)}
                                    </td>
                                </tr>`;
                        });
                    } else {
                        DOM.resumenMovimientosTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted p-4">Sin movimientos.</td></tr>';
                    }
                } else throw new Error(result.error);
            } catch (err) {
                if (window.showInfoModal) window.showInfoModal('Error', err.message, false);
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Consultar';
            }
        });
    }

    cargarDatosGenerales();
});