document.addEventListener('DOMContentLoaded', () => {
    // --- REFERENCIAS DOM ---
    const saldosContainer = document.getElementById('saldos-container');
    const saldosLoading = document.getElementById('saldos-loading');
    const bancosContainer = document.getElementById('bancos-container');
    const bancosLoading = document.getElementById('bancos-loading');

    // Selectores de los Formularios Principales
    const saldoPaisSelect = document.getElementById('saldo-pais-id');
    const resumenPaisSelect = document.getElementById('resumen-pais-id');
    const compraBancoOrigen = document.getElementById('compra-banco-origen');
    const compraPaisDestino = document.getElementById('compra-pais-destino');
    const gastoBancoOrigen = document.getElementById('gasto-banco-origen');

    // --- Selectores de los Modales ---
    const modalRecargaBancoSelect = document.getElementById('recarga-banco-select');
    const modalRetiroPaisSelect = document.getElementById('retiro-pais-select');

    // Selectores de Historial (Filtro Avanzado)
    const resumenTipo = document.getElementById('resumen-tipo');
    const resumenEntidad = document.getElementById('resumen-entidad-id');

    // Formularios
    const formAgregarFondos = document.getElementById('form-agregar-fondos');
    const formResumenGastos = document.getElementById('form-resumen-gastos');
    const formCompraDivisas = document.getElementById('form-compra-divisas');
    const formGastoVario = document.getElementById('form-gasto-vario');
    const formRecargaBanco = document.getElementById('form-recarga-banco');
    const formRetiroPais = document.getElementById('form-retiro-pais');

    // Elementos Visuales Resumen
    const resumenResultado = document.getElementById('resumen-resultado');
    const resumenTotalGastado = document.getElementById('resumen-total-gastado');
    const resumenTextoInfo = document.getElementById('resumen-texto-info');
    const historialContainer = document.getElementById('historial-container');
    const resumenMovimientosTbody = document.getElementById('resumen-movimientos-tbody');
    const resumenTitulo = document.getElementById('resumen-titulo');

    // VARIABLES PARA CACHÉ
    let cachePaises = [];
    let cacheBancos = [];

    const numberFormatter = (currencyCode, value) => {
        try {
            const code = (currencyCode && currencyCode.length === 3) ? currencyCode : 'USD';
            const val = isNaN(parseFloat(value)) ? 0 : parseFloat(value);
            return new Intl.NumberFormat('es-ES', { style: 'currency', currency: code }).format(val);
        } catch (e) { return value; }
    };

    // --- FUNCIÓN: ACTUALIZAR DROPDOWN DEL HISTORIAL ---
    const actualizarDropdownHistorial = () => {
        if (!resumenEntidad || !resumenTipo) return;

        const tipo = resumenTipo.value;
        resumenEntidad.innerHTML = '<option value="">Seleccione...</option>';

        if (tipo === 'pais') {
            cachePaises.forEach(p => {
                resumenEntidad.innerHTML += `<option value="${p.PaisID}">${p.NombrePais} (${p.CodigoMoneda})</option>`;
            });
        } else {
            cacheBancos.forEach(b => {
                resumenEntidad.innerHTML += `<option value="${b.CuentaAdminID}">${b.Banco} - ${b.Titular}</option>`;
            });
        }
    };

    if (resumenTipo) {
        resumenTipo.addEventListener('change', actualizarDropdownHistorial);
    }

    const cargarDatos = async () => {
        try {
            if (saldosLoading) saldosLoading.classList.remove('d-none');
            if (bancosLoading) bancosLoading.classList.remove('d-none');
            if (saldosContainer) saldosContainer.classList.add('d-none');
            if (bancosContainer) bancosContainer.classList.add('d-none');

            const response = await fetch('../api/?accion=getSaldosContables');
            const result = await response.json();

            if (!result.success) throw new Error(result.error);

            cachePaises = result.saldos || [];
            cacheBancos = result.bancos || [];

            // 1. RENDERIZAR PAÍSES Y LLENAR SELECTS DE PAÍS
            if (saldosContainer) {
                saldosContainer.innerHTML = '';

                const paisOptions = '<option value="">Seleccione...</option>' +
                    cachePaises.map(s => `<option value="${s.PaisID}">${s.NombrePais} (${s.CodigoMoneda})</option>`).join('');

                if (saldoPaisSelect) saldoPaisSelect.innerHTML = paisOptions;
                if (resumenPaisSelect) resumenPaisSelect.innerHTML = paisOptions;
                if (compraPaisDestino) compraPaisDestino.innerHTML = paisOptions;
                if (modalRetiroPaisSelect) modalRetiroPaisSelect.innerHTML = paisOptions;

                if (cachePaises.length === 0) {
                    saldosContainer.innerHTML = '<div class="col-12 text-center text-muted">No hay países.</div>';
                } else {
                    cachePaises.forEach(p => {
                        const val = parseFloat(p.SaldoActual);
                        const isLow = val < parseFloat(p.UmbralAlerta || 50000);
                        saldosContainer.innerHTML += `
                            <div class="col-md-4 mb-3">
                                <div class="card ${isLow ? 'border-danger' : 'shadow-sm'} h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted">${p.NombrePais}</h6>
                                        <h3 class="${isLow ? 'text-danger' : 'text-success'}">${numberFormatter(p.CodigoMoneda, val)}</h3>
                                    </div>
                                </div>
                            </div>`;
                    });
                }
            }

            // 2. RENDERIZAR BANCOS Y LLENAR SELECTS DE BANCO
            if (bancosContainer) {
                bancosContainer.innerHTML = '';

                const bancoOptions = '<option value="">Seleccione cuenta...</option>' +
                    cacheBancos.map(b => `<option value="${b.CuentaAdminID}">${b.Banco} - ${b.Titular} (${b.CodigoMoneda})</option>`).join('');

                if (compraBancoOrigen) compraBancoOrigen.innerHTML = bancoOptions;
                if (gastoBancoOrigen) gastoBancoOrigen.innerHTML = bancoOptions;
                if (modalRecargaBancoSelect) modalRecargaBancoSelect.innerHTML = bancoOptions;

                if (cacheBancos.length === 0) {
                    bancosContainer.innerHTML = '<div class="col-12 text-center text-muted">No hay bancos.</div>';
                } else {
                    cacheBancos.forEach(b => {
                        const val = parseFloat(b.SaldoActual);
                        bancosContainer.innerHTML += `
                            <div class="col-md-4 mb-3">
                                <div class="card shadow-sm border-primary h-100">
                                    <div class="card-body">
                                        <h6 class="text-primary fw-bold text-truncate">${b.Banco}</h6>
                                        <h4 class="text-dark">${numberFormatter(b.CodigoMoneda, val)}</h4>
                                        <small class="text-muted d-block text-truncate">${b.Titular}</small>
                                    </div>
                                </div>
                            </div>`;
                    });
                }
            }

            actualizarDropdownHistorial();

        } catch (error) {
            console.error(error);
        } finally {
            if (saldosLoading) saldosLoading.classList.add('d-none');
            if (bancosLoading) bancosLoading.classList.add('d-none');
            if (saldosContainer) saldosContainer.classList.remove('d-none');
            if (bancosContainer) bancosContainer.classList.remove('d-none');
        }
    };

    // --- EVENTOS DE FORMULARIOS ---

    // 1. Recarga Manual (Caja Destino)
    if (formAgregarFondos) {
        formAgregarFondos.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!confirm('¿Confirmas esta recarga manual?')) return;
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            try {
                const res = await fetch('../api/?accion=agregarFondos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        paisId: saldoPaisSelect.value,
                        monto: document.getElementById('saldo-monto').value
                    })
                });
                const r = await res.json();
                if (r.success) {
                    window.showInfoModal('Éxito', 'Recarga registrada.', true);
                    formAgregarFondos.reset();
                    cargarDatos();
                } else throw new Error(r.error);
            } catch (e) { window.showInfoModal('Error', e.message, false); }
            finally { btn.disabled = false; }
        });
    }

    // 2. Recarga Banco (Modal)
    if (formRecargaBanco) {
        formRecargaBanco.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;

            try {
                const res = await fetch('../api/?accion=agregarFondos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bancoId: modalRecargaBancoSelect.value,
                        monto: document.getElementById('recarga-banco-monto').value
                    })
                });
                const r = await res.json();
                if (r.success) {
                    window.showInfoModal('Éxito', 'Saldo bancario actualizado.', true);
                    const modalEl = document.getElementById('modalRecargaBanco');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();

                    formRecargaBanco.reset();
                    cargarDatos();
                } else throw new Error(r.error);
            } catch (err) { window.showInfoModal('Error', err.message, false); }
            finally { btn.disabled = false; }
        });
    }

    // 3. Retiro País (Modal)
    if (formRetiroPais) {
        formRetiroPais.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;

            try {
                const res = await fetch('../api/?accion=registrarGastoVario', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        paisId: modalRetiroPaisSelect.value,
                        monto: document.getElementById('retiro-pais-monto').value,
                        motivo: document.getElementById('retiro-pais-motivo').value
                    })
                });
                const r = await res.json();
                if (r.success) {
                    window.showInfoModal('Éxito', 'Retiro registrado.', true);
                    const modalEl = document.getElementById('modalRetiroPais');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();

                    formRetiroPais.reset();
                    cargarDatos();
                } else throw new Error(r.error);
            } catch (err) { window.showInfoModal('Error', err.message, false); }
            finally { btn.disabled = false; }
        });
    }

    // 4. Compra Divisas
    if (formCompraDivisas) {
        formCompraDivisas.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!await window.showConfirmModal('Confirmar', '¿Procesar compra de divisas?')) return;
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            try {
                const res = await fetch('../api/?accion=compraDivisas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bancoOrigenId: compraBancoOrigen.value,
                        paisDestinoId: compraPaisDestino.value,
                        montoSalida: document.getElementById('compra-monto-salida').value,
                        montoEntrada: document.getElementById('compra-monto-entrada').value
                    })
                });
                const r = await res.json();
                if (r.success) {
                    window.showInfoModal('Éxito', 'Operación registrada.', true);
                    formCompraDivisas.reset();
                    cargarDatos();
                } else throw new Error(r.error);
            } catch (e) { window.showInfoModal('Error', e.message, false); }
            finally { btn.disabled = false; }
        });
    }

    // 5. Historial (Filtro Avanzado)
    if (formResumenGastos) {
        formResumenGastos.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!resumenEntidad.value) {
                window.showInfoModal('Error', 'Por favor selecciona un País o Banco.', false);
                return;
            }

            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            resumenResultado.style.display = 'none';
            const tipo = resumenTipo.value;
            const id = resumenEntidad.value;
            const [anio, mes] = document.getElementById('resumen-mes').value.split('-');

            try {
                const res = await fetch(`../api/?accion=getResumenContable&tipo=${tipo}&id=${id}&mes=${mes}&anio=${anio}`);
                const r = await res.json();

                if (r.success) {
                    const d = r.resumen;
                    if (resumenTitulo) resumenTitulo.textContent = `${d.Entidad} - ${mes}/${anio}`;
                    if (resumenTotalGastado) {
                        if (tipo === 'pais') {
                            resumenTotalGastado.textContent = numberFormatter(d.Moneda, d.TotalGastado);
                            resumenTextoInfo.textContent = `Gastos operativos`;
                        } else {
                            resumenTotalGastado.textContent = '';
                            resumenTextoInfo.textContent = 'Movimientos del Banco';
                        }
                    }

                    resumenMovimientosTbody.innerHTML = '';
                    if (d.Movimientos && d.Movimientos.length > 0) {
                        d.Movimientos.forEach(m => {
                            let color = 'text-dark';
                            const tipoMov = m.TipoMovimiento;
                            if (['GASTO_TX', 'GASTO_COMISION', 'GASTO_VARIO', 'RETIRO_DIVISAS'].includes(tipoMov)) color = 'text-danger';
                            if (['RECARGA', 'INGRESO_VENTA', 'COMPRA_DIVISA', 'SALDO_INICIAL'].includes(tipoMov)) color = 'text-success';

                            const resp = m.AdminNombre ? `<small>${m.AdminNombre} ${m.AdminApellido}</small>` : '<small>Sistema</small>';
                            const detalle = m.TransaccionID ? `Orden #${m.TransaccionID}` : (m.Detalle || '-');
                            const nombreTipo = m.NombreVisible || m.TipoMovimiento;

                            resumenMovimientosTbody.innerHTML += `
                                <tr>
                                    <td>${new Date(m.Timestamp).toLocaleString('es-CL')}</td>
                                    <td><span class="fw-bold small ${color}">${nombreTipo}</span></td>
                                    <td>${detalle}</td>
                                    <td>${resp}</td>
                                    <td class="text-end fw-bold ${color}">${numberFormatter(d.Moneda, m.Monto)}</td>
                                </tr>`;
                        });
                    } else {
                        resumenMovimientosTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-3">No hay movimientos en este período.</td></tr>';
                    }
                    resumenResultado.style.display = 'block';
                } else throw new Error(r.error);

            } catch (err) {
                window.showInfoModal('Error', err.message, false);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Consultar';
            }
        });
    }

    cargarDatos();
});