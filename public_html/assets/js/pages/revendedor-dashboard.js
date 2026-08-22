(async function () {
    'use strict';

    function fmt(n) {
        return Number(n).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Recibe [{Moneda, Total, Cantidad}, ...] y lo muestra como
     * "CLP 5.000,00 + COP 20.000,00". Nunca suma monedas distintas.
     */
    function fmtMonedas(lista) {
        if (!Array.isArray(lista) || lista.length === 0) return '—';
        return lista
            .filter(x => Number(x.Total) > 0)
            .map(x => `${x.Moneda} ${fmt(x.Total)}`)
            .join(' + ') || '—';
    }

    async function loadSummary() {
        try {
            const res = await fetch('../api/?accion=getResellerSummary');
            const data = await res.json();
            if (!data.success) return;

            // Las comisiones se generan en la moneda de origen de cada orden
            // (CLP, COP, PEN…). Se muestran desglosadas: sumarlas todas y
            // rotularlas "CLP" era mentirle al revendedor sobre lo que se le debe.
            document.getElementById('stat-pendiente').textContent = fmtMonedas(data.pendiente);
            document.getElementById('stat-pagado').textContent    = fmtMonedas(data.pagado);

            const tbody = document.getElementById('liq-body');
            if (!data.liquidaciones || data.liquidaciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Sin pagos registrados aún.</td></tr>';
                return;
            }
            tbody.innerHTML = data.liquidaciones.map(l => `
                <tr>
                    <td>${l.PeriodoDesde} — ${l.PeriodoHasta}</td>
                    <td class="text-end fw-bold">${esc(l.Moneda || 'CLP')} ${fmt(l.Monto)}</td>
                    <td class="text-center">${l.CantidadTransacciones}</td>
                    <td>${l.Estado === 'pagada'
                        ? '<span class="badge bg-success">Pagada</span>'
                        : '<span class="badge bg-warning text-dark">Pendiente</span>'}</td>
                    <td>${l.FechaPago ? new Date(l.FechaPago).toLocaleDateString('es-CL') : '—'}</td>
                    <td>${l.ComprobanteURL
                        ? `<a href="../admin/view_secure_file.php?file=${encodeURIComponent(l.ComprobanteURL)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark"></i></a>`
                        : '—'}</td>
                </tr>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    }

    async function loadRecent() {
        try {
            const res = await fetch('../api/?accion=getResellerTransactions&limit=5&page=1');
            const data = await res.json();
            const tbody = document.getElementById('recent-body');
            if (!data.success || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin transacciones aún.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(tx => `
                <tr>
                    <td><strong>#${tx.TransaccionID}</strong></td>
                    <td>${new Date(tx.FechaTransaccion).toLocaleDateString('es-CL')}</td>
                    <td>${esc(tx.BeneficiarioNombre)}</td>
                    <td class="text-end">${fmt(tx.MontoOrigen)} ${esc(tx.MonedaOrigen)}</td>
                    <td class="text-end">${fmt(tx.MontoDestino)} ${esc(tx.MonedaDestino)}</td>
                    <td class="text-end text-success fw-bold">${fmt(tx.ComisionRevendedor)} ${esc(tx.MonedaOrigen)}</td>
                    <td><span class="badge bg-success">${esc(tx.NombreEstado)}</span></td>
                </tr>
            `).join('');
        } catch (e) { console.error(e); }
    }

    loadSummary();
    loadRecent();
})();
