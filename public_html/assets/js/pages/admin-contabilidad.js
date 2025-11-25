document.addEventListener('DOMContentLoaded', () => {
    const saldosContainer = document.getElementById('saldos-container');
    const saldosLoading = document.getElementById('saldos-loading');
    const saldoPaisSelect = document.getElementById('saldo-pais-id');
    const resumenPaisSelect = document.getElementById('resumen-pais-id');
    const formAgregarFondos = document.getElementById('form-agregar-fondos');
    const formResumenGastos = document.getElementById('form-resumen-gastos');
    const resumenResultado = document.getElementById('resumen-resultado');
    const resumenTotalGastado = document.getElementById('resumen-total-gastado');
    const resumenTextoInfo = document.getElementById('resumen-texto-info');

    const historialContainer = document.getElementById('historial-container');
    const resumenMovimientosTbody = document.getElementById('resumen-movimientos-tbody');

    const numberFormatter = (currencyCode, value) => {
        if (!currencyCode) currencyCode = 'USD';
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currencyCode,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    };

    const cargarSaldos = async () => {
        try {
            saldosLoading.classList.remove('d-none');
            saldosContainer.classList.add('d-none');

            const response = await fetch('../api/?accion=getSaldosContables');
            const result = await response.json();

            if (!result.success) throw new Error(result.error);

            saldosContainer.innerHTML = '';
            saldoPaisSelect.innerHTML = '<option value="">Seleccione un país...</option>';
            resumenPaisSelect.innerHTML = '<option value="">Seleccione un país...</option>';

            if (!result.saldos || result.saldos.length === 0) {
                saldosContainer.innerHTML = '<p class="text-muted text-center">No hay países de destino activos para mostrar contabilidad.</p>';
            }

            result.saldos.forEach(saldo => {
                const saldoActual = parseFloat(saldo.SaldoActual || 0);
                const umbral = parseFloat(saldo.UmbralAlerta || 50000);
                const isLow = saldoActual < umbral;

                const cardHtml = `
                    <div class="col-md-4 mb-3">
                        <div class="card ${isLow ? 'border-danger shadow' : 'shadow-sm'}">
                            <div class="card-body text-center">
                                <h6 class="card-subtitle mb-2 text-muted">${saldo.NombrePais}</h6>
                                <h3 class="card-title ${isLow ? 'text-danger' : ''}">
                                    ${numberFormatter(saldo.CodigoMoneda, saldoActual)}
                                </h3>
                                ${isLow ? '<span class="badge bg-danger">SALDO BAJO</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
                saldosContainer.innerHTML += cardHtml;

                const optionHtml = `<option value="${saldo.PaisID}">${saldo.NombrePais} (${saldo.CodigoMoneda})</option>`;
                saldoPaisSelect.innerHTML += optionHtml;
                resumenPaisSelect.innerHTML += optionHtml;
            });

            saldosLoading.classList.add('d-none');
            saldosContainer.classList.remove('d-none');

        } catch (error) {
            console.error('Error cargando saldos:', error);
            saldosLoading.innerHTML = `<p class="text-danger text-center p-3">${error.message}</p>`;
        }
    };

    formAgregarFondos.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        const data = {
            paisId: saldoPaisSelect.value,
            monto: document.getElementById('saldo-monto').value,
            tipo: e.target.querySelector('input[name="tipoMovimiento"]:checked').value
        };

        try {
            const response = await fetch('../api/?accion=agregarFondos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (!result.success) throw new Error(result.error);

            window.showInfoModal('Éxito', 'Fondos agregados correctamente.', true);
            formAgregarFondos.reset();
            cargarSaldos();

        } catch (error) {
            window.showInfoModal('Error', error.message, false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Registrar Movimiento';
        }
    });

    formResumenGastos.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        resumenResultado.style.display = 'none';
        historialContainer.style.display = 'none';
        resumenMovimientosTbody.innerHTML = '';

        const paisId = resumenPaisSelect.value;
        const [anio, mes] = document.getElementById('resumen-mes').value.split('-');

        try {
            const response = await fetch(`../api/?accion=getResumenContable&paisId=${paisId}&mes=${mes}&anio=${anio}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.error);

            const data = result.resumen;

            resumenTotalGastado.textContent = numberFormatter(data.Moneda, data.TotalGastado);
            resumenTextoInfo.textContent = `Total gastado en ${data.Pais} para ${mes}/${anio}`;
            resumenResultado.style.display = 'block';

            if (data.Movimientos && data.Movimientos.length > 0) {
                data.Movimientos.forEach(mov => {
                    let colorClass = '';
                    let montoStr = '';
                    let tipoStr = '';
                    let detalleStr = '';
                    const monto = parseFloat(mov.Monto);
                    const fecha = new Date(mov.Timestamp).toLocaleString('es-CL');

                    // --- DATOS DEL RESPONSABLE ---
                    const nombreResp = mov.AdminNombre ? `${mov.AdminNombre} ${mov.AdminApellido}` : 'Sistema';
                    const emailResp = mov.AdminEmail ? `(${mov.AdminEmail})` : '';
                    const responsableStr = `<small><strong>${nombreResp}</strong><br>${emailResp}</small>`;
                    // -----------------------------

                    switch (mov.TipoMovimiento) {
                        case 'GASTO_TX':
                            colorClass = 'text-danger';
                            montoStr = `- ${numberFormatter(data.Moneda, monto)}`;
                            tipoStr = 'Gasto Transacción';
                            detalleStr = `TX #${mov.TransaccionID} a ${mov.BeneficiarioNombre || 'N/A'}`;
                            break;
                        case 'GASTO_COMISION':
                            colorClass = 'text-danger';
                            montoStr = `- ${numberFormatter(data.Moneda, monto)}`;
                            tipoStr = 'Gasto Comisión';
                            detalleStr = `Comisión TX #${mov.TransaccionID}`;
                            break;
                        case 'RECARGA':
                            colorClass = 'text-success';
                            montoStr = `+ ${numberFormatter(data.Moneda, monto)}`;
                            tipoStr = 'Recarga';
                            detalleStr = 'Recarga de fondos manual';
                            break;
                        case 'SALDO_INICIAL':
                            colorClass = 'text-success';
                            montoStr = `+ ${numberFormatter(data.Moneda, monto)}`;
                            tipoStr = 'Saldo Inicial';
                            detalleStr = 'Ajuste de saldo inicial';
                            break;
                    }

                    const rowHtml = `
                        <tr>
                            <td>${fecha}</td>
                            <td><span class="fw-bold ${colorClass}">${tipoStr}</span></td>
                            <td>${detalleStr}</td>
                            <td>${responsableStr}</td>
                            <td class="text-end fw-bold ${colorClass} text-nowrap">${montoStr}</td>
                        </tr>
                    `;
                    resumenMovimientosTbody.innerHTML += rowHtml;
                });
                historialContainer.style.display = 'block';
            } else {
                resumenMovimientosTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No se encontraron movimientos para este mes.</td></tr>';
                historialContainer.style.display = 'block';
            }

        } catch (error) {
            window.showInfoModal('Error', error.message, false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Consultar';
        }
    });

    cargarSaldos();
});