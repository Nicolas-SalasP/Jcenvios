(async function () {
    'use strict';

    // ── Helpers ─────────────────────────────────────────────────────────────

    function fmt(n) {
        return Number(n).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Nombre/email/teléfono los escribe el propio revendedor: van escapados
    // siempre que se interpolen en innerHTML.
    const esc = (s) => window.escapeHtml(s);
    // window.escapeHtml (domUtils.js) NO escapa comillas; dentro de un atributo
    // (data-nombre="...") hacen falta o se puede romper el atributo.
    const escAttr = window.escapeAttr;

    /**
     * Renderiza [{Moneda, Total, Cantidad}, …] como "CLP 5.000,00 + COP 20.000,00".
     *
     * La comisión del revendedor se calcula sobre el monto de origen de cada
     * orden, así que está expresada en la moneda de esa orden. NUNCA se suman
     * monedas distintas acá: antes se sumaban y el resultado se mostraba (y se
     * pagaba) como pesos chilenos.
     */
    function fmtMonedas(lista, extraClass) {
        if (!Array.isArray(lista)) return '<span class="text-muted">—</span>';
        const partes = lista.filter(x => Number(x.Total) > 0).map(x => `${x.Moneda} ${fmt(x.Total)}`);
        if (!partes.length) return '<span class="text-muted">—</span>';
        return `<span class="${extraClass || ''}">${esc(partes.join(' + '))}</span>`;
    }

    /**
     * Junta varias listas [{Moneda, Total}] en un solo total por moneda.
     * Se usa para las tarjetas de resumen (suma sobre revendedores, no sobre monedas).
     */
    function totalizarPorMoneda(listas) {
        const acc = {};
        listas.forEach(lista => {
            (Array.isArray(lista) ? lista : []).forEach(x => {
                const m = x.Moneda || '?';
                acc[m] = (acc[m] || 0) + Number(x.Total || 0);
            });
        });
        return Object.keys(acc).sort().map(m => ({ Moneda: m, Total: acc[m] }));
    }

    function textoMonedas(lista) {
        if (!Array.isArray(lista)) return '—';
        const partes = lista.filter(x => Number(x.Total) > 0).map(x => `${x.Moneda} ${fmt(x.Total)}`);
        return partes.length ? partes.join(' + ') : '—';
    }

    // ── Avatar initials helper ───────────────────────────────────────────────

    function initials(nombre, apellido) {
        return ((nombre || '').charAt(0) + (apellido || '').charAt(0)).toUpperCase();
    }

    const AVATAR_COLORS = ['#0d6efd','#6610f2','#198754','#0dcaf0','#fd7e14','#6f42c1','#20c997'];
    function avatarColor(userId) {
        return AVATAR_COLORS[Number(userId) % AVATAR_COLORS.length];
    }

    // ── Reseller row renderer ────────────────────────────────────────────────

    function resellerRow(r) {
        const nombre  = `${r.PrimerNombre} ${r.PrimerApellido}`;
        const inits   = initials(r.PrimerNombre, r.PrimerApellido);
        const color   = avatarColor(r.UserID);
        const regDate = r.FechaRegistro
            ? new Date(r.FechaRegistro).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' })
            : '';

        const liquidarBtn = r.TienePendiente
            ? `<button class="btn btn-sm btn-primary btn-crear-liq"
                   data-id="${escAttr(r.UserID)}" data-nombre="${escAttr(nombre)}" title="Crear liquidación">
                   <i class="bi bi-cash-stack me-1"></i>Liquidar
               </button>`
            : `<button class="btn btn-sm btn-outline-secondary" disabled title="Sin comisión pendiente">
                   <i class="bi bi-cash-stack"></i>
               </button>`;

        return `
            <tr data-search="${escAttr(nombre.toLowerCase() + ' ' + (r.Email || '').toLowerCase())}">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white"
                             style="width:32px;height:32px;font-size:.75rem;background:${color}">
                            ${esc(inits)}
                        </div>
                        <div>
                            <div class="fw-semibold lh-sm">${esc(nombre)}</div>
                            <small class="text-muted"><i class="bi bi-envelope me-1"></i>${esc(r.Email || '—')}</small>
                            ${r.Telefono ? `<br><small class="text-muted"><i class="bi bi-whatsapp me-1 text-success"></i>${esc(r.Telefono)}</small>` : ''}
                            ${regDate ? `<br><span class="text-muted" style="font-size:.7rem">Desde ${esc(regDate)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">${parseFloat(r.PorcentajeComision)}%</span>
                </td>
                <td class="text-end small">
                    ${fmtMonedas(r.Ganado, 'fw-semibold')}
                </td>
                <td class="text-end small">
                    ${fmtMonedas(r.Pendiente, 'fw-bold text-warning')}
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border">${esc(r.TotalOrdenes)}</span>
                </td>
                <td>
                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                        ${liquidarBtn}
                        <button class="btn btn-sm btn-outline-secondary btn-edit-comision"
                            data-id="${escAttr(r.UserID)}" data-nombre="${escAttr(nombre)}" data-pct="${escAttr(r.PorcentajeComision)}"
                            title="Editar comisión">
                            <i class="bi bi-percent"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info btn-config-paises"
                            data-id="${escAttr(r.UserID)}" data-nombre="${escAttr(nombre)}"
                            title="Comisión por país">
                            <i class="bi bi-globe"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning btn-limite-cuentas"
                            data-id="${escAttr(r.UserID)}" data-nombre="${escAttr(nombre)}"
                            title="Límite de cuentas bancarias">
                            <i class="bi bi-bank"></i>
                        </button>
                        <a href="transacciones.php?revendedorId=${encodeURIComponent(r.UserID)}" class="btn btn-sm btn-outline-dark"
                           title="Ver órdenes">
                            <i class="bi bi-receipt"></i>
                        </a>
                    </div>
                </td>
            </tr>`;
    }

    // ── Load resellers table ─────────────────────────────────────────────────

    let _resellersData = [];

    async function loadResellers() {
        const tbody = document.getElementById('resellers-body');
        try {
            const res  = await fetch('../api/?accion=getResellerList');
            if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}).`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo obtener la lista de revendedores.');
            if (!data.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No hay revendedores registrados.</td></tr>';
                return;
            }

            _resellersData = data.data;

            // Tarjetas de resumen: se suma POR MONEDA entre revendedores, nunca
            // entre monedas distintas.
            const totalOrdenes = data.data.reduce((s, r) => s + Number(r.TotalOrdenes), 0);
            const totalGanado  = totalizarPorMoneda(data.data.map(r => r.Ganado));
            const totalPend    = totalizarPorMoneda(data.data.map(r => r.Pendiente));
            const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            setEl('stat-total-resellers', data.data.length);
            setEl('stat-total-ordenes',   totalOrdenes.toLocaleString('es-CL'));
            setEl('stat-pendiente-total', textoMonedas(totalPend));
            setEl('stat-total-ganado',    textoMonedas(totalGanado));
            const countBadge = document.getElementById('badge-count-resellers');
            if (countBadge) countBadge.textContent = data.data.length;

            tbody.innerHTML = data.data.map(resellerRow).join('');
            wireResellerButtons();

        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">${esc(window.formatNetworkError(e, 'Error al cargar revendedores.'))}</td></tr>`;
        }
    }

    function wireResellerButtons() {
        document.querySelectorAll('.btn-crear-liq').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('liq-user-id').value            = btn.dataset.id;
                document.getElementById('liq-user-nombre').textContent  = btn.dataset.nombre;
                const now = new Date();
                document.getElementById('liq-desde').value  = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                document.getElementById('liq-hasta').value  = now.toISOString().split('T')[0];
                document.getElementById('liq-modo-por-moneda').checked = true;
                resetPreview();
                document.getElementById('liq-notas').value = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('crearLiqModal')).show();
            });
        });
    }

    // ── Search / filter ──────────────────────────────────────────────────────

    function applyResellerFilter() {
        const q = (document.getElementById('reseller-search').value || '').toLowerCase().trim();
        document.querySelectorAll('#resellers-body tr[data-search]').forEach(tr => {
            tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
        });
    }

    document.getElementById('btn-reseller-search').addEventListener('click', applyResellerFilter);
    document.getElementById('reseller-search').addEventListener('keydown', e => { if (e.key === 'Enter') applyResellerFilter(); });
    document.getElementById('btn-reseller-clear').addEventListener('click', () => {
        document.getElementById('reseller-search').value = '';
        applyResellerFilter();
    });

    /**
     * Muestra a qué cambio se pagó cada moneda de una liquidación consolidada.
     * Es el rastro auditable: sin esto, dentro de seis meses nadie puede
     * reconstruir el pago.
     */
    function detalleConversion(l) {
        if (l.ModoLiquidacion !== 'consolidado_clp' || !Array.isArray(l.Detalle) || l.Detalle.length === 0) {
            return '';
        }
        const lineas = l.Detalle.map(d =>
            `${d.Moneda} ${fmt(d.MontoOriginal)} × ${Number(d.TasaConversion)} = CLP ${fmt(d.MontoConvertido)}`
        );
        return `<br><small class="text-muted fw-normal" style="font-size:.7rem">${esc(lineas.join(' · '))}</small>`;
    }

    async function loadLiquidaciones() {
        const tbody = document.getElementById('liq-body');
        try {
            const res  = await fetch('../api/?accion=getLiquidacionesList');
            if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}).`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo obtener el listado de liquidaciones.');
            if (!data.data.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin liquidaciones creadas.</td></tr>';
                return;
            }
            const liqBadge = document.getElementById('badge-count-liq');
            if (liqBadge) liqBadge.textContent = data.data.length;
            tbody.innerHTML = data.data.map(l => `
                <tr>
                    <td><strong>${esc(l.RevendedorNombre)}</strong><br><small class="text-muted">${esc(l.RevendedorEmail)}</small></td>
                    <td class="small">${esc(l.PeriodoDesde)} — ${esc(l.PeriodoHasta)}</td>
                    <td class="text-end fw-bold">
                        ${esc(l.Moneda || 'CLP')} ${esc(fmt(l.Monto))}
                        ${detalleConversion(l)}
                    </td>
                    <td class="text-center">${esc(l.CantidadTransacciones)}</td>
                    <td>${l.Estado === 'pagada'
                        ? '<span class="badge bg-success">Pagada</span>'
                        : '<span class="badge bg-warning text-dark">Pendiente</span>'}</td>
                    <td class="small">${l.FechaPago ? esc(new Date(l.FechaPago).toLocaleDateString('es-CL')) : '—'}</td>
                    <td class="text-end">
                        ${l.Estado !== 'pagada' ? `
                        <button class="btn btn-sm btn-success btn-pagar-liq"
                            data-id="${escAttr(l.LiquidacionID)}"
                            data-monto="${escAttr((l.Moneda || 'CLP') + ' ' + fmt(l.Monto))}"
                            data-nombre="${escAttr(l.RevendedorNombre)}">
                            <i class="bi bi-check-circle me-1"></i> Pagar
                        </button>` :
                        l.ComprobanteURL
                            ? `<a href="../admin/view_secure_file.php?file=${encodeURIComponent(l.ComprobanteURL)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark"></i></a>`
                            : '—'}
                    </td>
                </tr>
            `).join('');

            document.querySelectorAll('.btn-pagar-liq').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('pagar-liq-id').value     = btn.dataset.id;
                    document.getElementById('pagar-liq-monto').textContent  = btn.dataset.monto;
                    document.getElementById('pagar-liq-nombre').textContent = btn.dataset.nombre;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('pagarLiqModal')).show();
                });
            });
        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${esc(window.formatNetworkError(e, 'Error al cargar liquidaciones.'))}</td></tr>`;
        }
    }

    // ── Preview / Crear liquidacion ──────────────────────────────────────────

    // Último desglose calculado. Se limpia al abrir el modal y al cambiar de
    // modo: confirmar con un desglose viejo sería pagar un monto que el admin
    // no está viendo.
    let _liqPreview = null;

    function modoSeleccionado() {
        const el = document.querySelector('input[name="liq-modo"]:checked');
        return el ? el.value : 'por_moneda';
    }

    function resetPreview() {
        _liqPreview = null;
        const preview = document.getElementById('liq-preview');
        preview.classList.add('d-none');
        preview.innerHTML = '';
        document.getElementById('btnConfirmLiq').disabled = true;
    }

    /**
     * Fila editable de conversión (solo en modo consolidado).
     * La tasa se expresa como CLP por 1 unidad de la moneda:
     *   montoCLP = montoOriginal * tasa
     * Se muestra además la equivalencia inversa (1 CLP = X moneda) porque es
     * como el admin está acostumbrado a leer las tasas del panel.
     */
    function filaConversion(d) {
        const sinTasa = d.tasaSugerida === null || d.tasaSugerida === undefined;
        const valor   = sinTasa ? '' : d.tasaSugerida;
        const origen  = d.sentidoTasa === 'inversa'
            ? `sugerida desde la ruta CLP→${d.moneda} (invertida)`
            : d.sentidoTasa === 'directa'
                ? `sugerida desde la ruta ${d.moneda}→CLP`
                : d.moneda === 'CLP' ? 'sin conversión' : '';

        if (d.moneda === 'CLP') {
            return `
                <tr data-moneda="CLP" data-monto="${escAttr(d.total)}">
                    <td class="fw-semibold">CLP</td>
                    <td class="text-end">${esc(fmt(d.total))}</td>
                    <td class="text-center small text-muted">${esc(d.cantidad)}</td>
                    <td class="text-center small text-muted">1 (sin conversión)</td>
                    <td class="text-end fw-semibold liq-clp-cell">${esc(fmt(d.total))}</td>
                </tr>`;
        }

        return `
            <tr data-moneda="${escAttr(d.moneda)}">
                <td class="fw-semibold">${esc(d.moneda)}</td>
                <td class="text-end">${esc(fmt(d.total))}</td>
                <td class="text-center small text-muted">${esc(d.cantidad)}</td>
                <td>
                    <input type="number" step="any" min="0"
                           class="form-control form-control-sm text-end liq-tasa-input"
                           data-moneda="${escAttr(d.moneda)}"
                           data-monto="${escAttr(d.total)}"
                           value="${escAttr(valor)}"
                           placeholder="CLP por 1 ${escAttr(d.moneda)}">
                    <small class="${sinTasa ? 'text-danger' : 'text-muted'}" style="font-size:.7rem">
                        ${sinTasa
                            ? 'Sin ruta de tasas activa: escribí la tasa a mano.'
                            : esc(origen)}
                    </small>
                </td>
                <td class="text-end fw-semibold liq-clp-cell">—</td>
            </tr>`;
    }

    function recalcularConsolidado() {
        const preview = document.getElementById('liq-preview');
        const btn     = document.getElementById('btnConfirmLiq');
        let total     = 0;
        let valido    = true;
        const faltan  = [];

        preview.querySelectorAll('tr[data-moneda]').forEach(tr => {
            const moneda = tr.dataset.moneda;
            const celda  = tr.querySelector('.liq-clp-cell');
            if (moneda === 'CLP') {
                // Monto crudo del data-attr, no el texto formateado de la celda.
                total += parseFloat(tr.dataset.monto) || 0;
                return;
            }
            const input = tr.querySelector('.liq-tasa-input');
            const tasa  = parseFloat(input.value);
            const monto = parseFloat(input.dataset.monto);

            if (!isFinite(tasa) || tasa <= 0) {
                valido = false;
                faltan.push(moneda);
                celda.innerHTML = '<span class="text-danger">falta tasa</span>';
                input.classList.add('is-invalid');
                return;
            }
            input.classList.remove('is-invalid');
            const convertido = monto * tasa;
            total += convertido;
            celda.textContent = fmt(convertido);
        });

        const totalEl = preview.querySelector('#liq-total-clp');
        const avisoEl = preview.querySelector('#liq-total-aviso');
        if (totalEl) totalEl.textContent = valido ? 'CLP ' + fmt(total) : '—';
        if (avisoEl) {
            avisoEl.innerHTML = valido
                ? ''
                : `<div class="alert alert-danger py-2 px-3 mb-0 mt-2 small">
                       Falta la tasa de conversión de <strong>${esc(faltan.join(', '))}</strong>.
                       No se puede consolidar sin ella; también podés liquidar por moneda.
                   </div>`;
        }
        btn.disabled = !valido;
    }

    function renderPreview() {
        const preview = document.getElementById('liq-preview');
        const btn     = document.getElementById('btnConfirmLiq');
        const data    = _liqPreview;

        if (!data || !data.desglose.length) {
            preview.classList.remove('d-none');
            preview.innerHTML = '<div class="alert alert-warning mb-0">No hay comisiones pendientes en ese período.</div>';
            btn.disabled = true;
            return;
        }

        const modo = modoSeleccionado();
        preview.classList.remove('d-none');

        if (modo === 'por_moneda') {
            const filas = data.desglose.map(d => `
                <tr>
                    <td class="fw-semibold">${esc(d.moneda)}</td>
                    <td class="text-end fw-bold">${esc(fmt(d.total))}</td>
                    <td class="text-center small text-muted">${esc(d.cantidad)}</td>
                </tr>`).join('');

            preview.innerHTML = `
                <div class="alert alert-info mb-2 py-2 px-3 small">
                    Se van a crear <strong>${esc(data.desglose.length)}</strong> liquidación(es),
                    una por moneda. Cada una marca solo las órdenes de su propia moneda.
                </div>
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr><th>Moneda</th><th class="text-end">Monto a pagar</th><th class="text-center">Órdenes</th></tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>`;
            btn.disabled = false;
            return;
        }

        // Modo consolidado: tasas visibles y editables.
        const filas = data.desglose.map(filaConversion).join('');
        preview.innerHTML = `
            <div class="alert alert-warning mb-2 py-2 px-3 small">
                Revisá la tasa de cada moneda antes de confirmar. La tasa es
                <strong>CLP por 1 unidad</strong> de esa moneda y queda guardada con la liquidación.
            </div>
            <table class="table table-sm table-bordered align-middle mb-2">
                <thead class="table-light">
                    <tr>
                        <th>Moneda</th>
                        <th class="text-end">Comisión</th>
                        <th class="text-center">Órdenes</th>
                        <th style="min-width:170px">Tasa (CLP por 1)</th>
                        <th class="text-end">Equivale a (CLP)</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">Total a pagar</th>
                        <th class="text-end" id="liq-total-clp">—</th>
                    </tr>
                </tfoot>
            </table>
            <div id="liq-total-aviso"></div>`;

        preview.querySelectorAll('.liq-tasa-input').forEach(inp => {
            inp.addEventListener('input', recalcularConsolidado);
        });
        recalcularConsolidado();
    }

    document.querySelectorAll('input[name="liq-modo"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (_liqPreview) renderPreview();
        });
    });

    document.getElementById('btnPreviewLiq').addEventListener('click', async () => {
        const userId  = document.getElementById('liq-user-id').value;
        const desde   = document.getElementById('liq-desde').value;
        const hasta   = document.getElementById('liq-hasta').value;
        const preview = document.getElementById('liq-preview');

        if (!desde || !hasta) { window.showInfoModal('Falta el período', 'Selecciona período.', false); return; }
        if (desde > hasta) { window.showInfoModal('Período inválido', 'La fecha "desde" no puede ser posterior a "hasta".', false); return; }

        const btnPreview = document.getElementById('btnPreviewLiq');
        const previewOriginal = btnPreview.innerHTML;
        btnPreview.disabled = true;
        btnPreview.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        resetPreview();

        try {
            const params = new URLSearchParams({ userId, desde, hasta });
            const res    = await fetch('../api/?accion=getResellerCommissionPreview&' + params);
            if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}).`);
            const data   = await res.json();

            // Antes no había else ni catch: si la previsualización fallaba, no
            // pasaba nada visible y el botón "Crear liquidación" quedaba
            // deshabilitado sin explicación.
            if (data.success) {
                _liqPreview = data;
                renderPreview();
            } else {
                preview.classList.add('d-none');
                window.showInfoModal('Error', data.error || 'No se pudo calcular la previsualización.', false);
            }
        } catch (err) {
            preview.classList.add('d-none');
            window.showInfoModal('Error', window.formatNetworkError(err, 'No se pudo calcular la previsualización.'), false);
        } finally {
            btnPreview.disabled = false;
            btnPreview.innerHTML = previewOriginal;
        }
    });

    document.getElementById('btnConfirmLiq').addEventListener('click', async () => {
        const userId = document.getElementById('liq-user-id').value;
        const desde  = document.getElementById('liq-desde').value;
        const hasta  = document.getElementById('liq-hasta').value;
        const notas  = document.getElementById('liq-notas').value;
        const modo   = modoSeleccionado();

        if (!_liqPreview) {
            window.showInfoModal('Falta calcular', 'Calculá el monto antes de crear la liquidación.', false);
            return;
        }

        // Tasas solo en modo consolidado. El backend igual valida y rechaza si
        // falta alguna o no es positiva: esto es la primera barrera, no la única.
        const tasas = {};
        if (modo === 'consolidado_clp') {
            let faltante = null;
            document.querySelectorAll('#liq-preview .liq-tasa-input').forEach(inp => {
                const t = parseFloat(inp.value);
                if (!isFinite(t) || t <= 0) { faltante = faltante || inp.dataset.moneda; return; }
                tasas[inp.dataset.moneda] = t;
            });
            if (faltante) {
                window.showInfoModal(
                    'Falta una tasa',
                    `No se puede consolidar en CLP sin la tasa de ${faltante}. Escribila a mano o liquidá por moneda.`,
                    false
                );
                return;
            }
        }

        // No se abre un modal de confirmación acá a propósito: showConfirmModal
        // usa el #confirmModal genérico de footer.php y anidarlo dentro de
        // #crearLiqModal deja backdrops de Bootstrap superpuestos. El control
        // es la previsualización, que ya muestra el desglose y las tasas.

        const btn = document.getElementById('btnConfirmLiq');
        // Deshabilitar YA: un doble click crearía dos liquidaciones.
        btn.disabled = true;
        btn.textContent = 'Creando…';

        try {
            const res  = await fetch('../api/?accion=crearLiquidacion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userId: parseInt(userId), desde, hasta, notas, modo, tasas }),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok) {
                throw new Error((data && data.error) || `El servidor respondió con un error (${res.status}).`);
            }
            if (data && data.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('crearLiqModal')).hide();
                resetPreview();
                await loadResellers();
                await loadLiquidaciones();
            } else {
                throw new Error((data && data.error) || 'Error al crear liquidación.');
            }
        } catch (e) {
            window.showInfoModal('Error', window.formatNetworkError(e, 'Error al crear liquidación.'), false);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear liquidación';
        }
    });

    // ── Pagar liquidacion ────────────────────────────────────────────────────

    document.getElementById('form-pagar-liq').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData  = new FormData(e.target);
        const submitBtn = e.target.querySelector('[type=submit]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guardando…';

        try {
            const res  = await fetch('../api/?accion=pagarLiquidacion', { method: 'POST', body: formData });
            if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}). Es posible que el archivo sea demasiado pesado.`);
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('pagarLiqModal')).hide();
                await loadResellers();
                await loadLiquidaciones();
            } else {
                window.showInfoModal('Error', data.error || 'Error al registrar pago.', false);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirmar pago';
            }
        } catch (e) {
            window.showInfoModal('Error', window.formatNetworkError(e, 'Error al registrar el pago.'), false);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirmar pago';
        }
    });

    // ── Feature 1: Editar % comisión global ─────────────────────────────────

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-edit-comision');
        if (!btn) return;
        document.getElementById('edit-comision-user-id').value      = btn.dataset.id;
        document.getElementById('edit-comision-nombre').textContent = btn.dataset.nombre;
        document.getElementById('edit-comision-pct').value          = btn.dataset.pct;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editComisionModal')).show();
    });

    document.getElementById('btnSaveComision').addEventListener('click', async () => {
        const userId     = parseInt(document.getElementById('edit-comision-user-id').value);
        const porcentaje = parseFloat(document.getElementById('edit-comision-pct').value);

        if (!userId || isNaN(porcentaje) || porcentaje < 0 || porcentaje > 100) {
            window.showInfoModal('Porcentaje inválido', 'Porcentaje inválido (debe estar entre 0 y 100).', false);
            return;
        }

        const btn = document.getElementById('btnSaveComision');
        btn.disabled = true;
        btn.textContent = 'Guardando…';

        try {
            const res  = await fetch('../api/?accion=updateResellerCommission', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userId, porcentaje }),
            });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editComisionModal')).hide();
                await loadResellers();
            } else {
                window.showInfoModal('Error', data.error || 'Error al guardar comisión.', false);
            }
        } catch {
            window.showInfoModal('Error', 'Error de red al guardar comisión.', false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Guardar';
        }
    });

    // ── Feature 2: Configurar comisión por país ──────────────────────────────

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-config-paises');
        if (!btn) return;

        document.getElementById('paises-user-id').value      = btn.dataset.id;
        document.getElementById('paises-user-nombre').textContent = btn.dataset.nombre;

        const list = document.getElementById('paises-config-list');
        list.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('configPaisesModal')).show();

        try {
            const res  = await fetch('../api/?accion=getResellerPaises&userId=' + btn.dataset.id);
            const data = await res.json();
            if (!data.success) {
                list.innerHTML = '<p class="text-danger">Error al cargar países.</p>';
                return;
            }

            list.innerHTML = data.data.map(p => `
                <div class="row align-items-center border-bottom py-2 g-2">
                    <div class="col-4 fw-semibold">
                        ${esc(p.NombrePais)} <small class="text-muted">(${esc(p.CodigoMoneda)})</small>
                    </div>
                    <div class="col-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="pais-activo-${escAttr(p.PaisID)}" data-pais="${escAttr(p.PaisID)}"
                                   ${p.Activo ? 'checked' : ''}>
                            <label class="form-check-label small" for="pais-activo-${escAttr(p.PaisID)}">Habilitado</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control pais-pct-input" data-pais="${escAttr(p.PaisID)}"
                                   min="0" max="100" step="0.01"
                                   value="${escAttr(p.PorcentajeComision !== null ? p.PorcentajeComision : '')}"
                                   placeholder="% (vacío = global)">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch {
            list.innerHTML = '<p class="text-danger">Error de red al cargar países.</p>';
        }
    });

    document.getElementById('btnSavePaises').addEventListener('click', async () => {
        const userId = parseInt(document.getElementById('paises-user-id').value);

        // Collect toggles and pct inputs, keyed by PaisID
        const paisMap = {};
        document.querySelectorAll('#paises-config-list [data-pais]').forEach(el => {
            const pais = el.dataset.pais;
            if (!paisMap[pais]) paisMap[pais] = {};
            if (el.type === 'checkbox') {
                paisMap[pais].Activo = el.checked;
            } else {
                paisMap[pais].PorcentajeComision = el.value !== '' ? parseFloat(el.value) : 0;
            }
        });

        const paises = Object.entries(paisMap).map(([PaisID, v]) => ({
            PaisID: parseInt(PaisID),
            PorcentajeComision: v.PorcentajeComision ?? 0,
            Activo: v.Activo ?? false,
        }));

        const btn = document.getElementById('btnSavePaises');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';

        try {
            const res  = await fetch('../api/?accion=updateResellerPaises', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userId, paises }),
            });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('configPaisesModal')).hide();
            } else {
                window.showInfoModal('Error', data.error || 'Error al guardar configuración de países.', false);
            }
        } catch {
            window.showInfoModal('Error', 'Error de red al guardar configuración de países.', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar configuración';
        }
    });

    // ── Feature 3: Límite de cuentas bancarias propias del revendedor ────────

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-limite-cuentas');
        if (!btn) return;

        document.getElementById('limite-user-id').value = btn.dataset.id;
        document.getElementById('limite-user-nombre').textContent = btn.dataset.nombre;
        document.getElementById('limite-cuentas-input').value = '';
        document.getElementById('limite-actual-info').textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('limiteCuentasModal')).show();

        try {
            const res = await fetch('../api/?accion=getResellerMaxCuentas&userId=' + btn.dataset.id);
            const data = await res.json();
            if (data.success) {
                document.getElementById('limite-cuentas-input').value = data.max;
                document.getElementById('limite-actual-info').textContent = `Cuentas registradas actualmente: ${data.actual}`;
            }
        } catch {
            /* silencioso, el input queda vacío */
        }
    });

    document.getElementById('btnSaveLimiteCuentas').addEventListener('click', async () => {
        const userId = parseInt(document.getElementById('limite-user-id').value, 10);
        const max = parseInt(document.getElementById('limite-cuentas-input').value, 10);

        const btn = document.getElementById('btnSaveLimiteCuentas');
        btn.disabled = true;
        try {
            const res = await fetch('../api/?accion=updateResellerMaxCuentas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ userId, max }),
            });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('limiteCuentasModal')).hide();
            } else {
                window.showInfoModal('Error', data.error || 'Error al actualizar el límite.', false);
            }
        } catch {
            window.showInfoModal('Error', 'Error de red al actualizar el límite.', false);
        } finally {
            btn.disabled = false;
        }
    });

    // ── Config global de referidos (forma manual / forma link) ──────────────

    async function loadReferralSettings() {
        try {
            const res = await fetch('../api/?accion=getReferralSettings');
            const data = await res.json();
            if (!data.success) return;
            document.getElementById('toggle-referido-manual').checked = data.formaManualActiva;
            document.getElementById('toggle-referido-link').checked = data.formaLinkActiva;
        } catch (e) {
            console.error(e);
        }
    }

    async function saveReferralSettings() {
        const manualEl = document.getElementById('toggle-referido-manual');
        const linkEl = document.getElementById('toggle-referido-link');
        const formaManualActiva = manualEl.checked;
        const formaLinkActiva = linkEl.checked;
        manualEl.disabled = true;
        linkEl.disabled = true;
        try {
            const res = await fetch('../api/?accion=updateReferralSettings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ formaManualActiva, formaLinkActiva }),
            });
            if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}).`);
            const data = await res.json();
            // Antes no se leía nada de la respuesta: el switch quedaba en la
            // posición nueva aunque el backend hubiera rechazado el cambio.
            if (!data.success) {
                window.showInfoModal('Error', data.error || 'No se pudo guardar la configuración de referidos.', false);
                loadReferralSettings();
            }
        } catch (e) {
            console.error(e);
            window.showInfoModal('Error', window.formatNetworkError(e, 'No se pudo guardar la configuración de referidos.'), false);
            loadReferralSettings();
        } finally {
            manualEl.disabled = false;
            linkEl.disabled = false;
        }
    }

    const toggleManual = document.getElementById('toggle-referido-manual');
    const toggleLink = document.getElementById('toggle-referido-link');
    if (toggleManual && toggleLink) {
        toggleManual.addEventListener('change', saveReferralSettings);
        toggleLink.addEventListener('change', saveReferralSettings);
        loadReferralSettings();
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    loadResellers();
    loadLiquidaciones();
})();
