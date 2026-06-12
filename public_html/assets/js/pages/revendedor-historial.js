(async function () {
    'use strict';

    let currentPage = 1;
    const limit = 20;

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

    async function load() {
        const search   = document.getElementById('searchInput').value.trim();
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo   = document.getElementById('dateTo').value;
        const tbody    = document.getElementById('hist-body');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>';

        const params = new URLSearchParams({ limit, page: currentPage, search });
        if (dateFrom) params.set('dateFrom', dateFrom);
        if (dateTo)   params.set('dateTo', dateTo);

        try {
            const res  = await fetch('../api/?accion=getResellerTransactions&' + params);
            const data = await res.json();
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Error al cargar.</td></tr>';
                return;
            }

            if (!data.data.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No hay transacciones.</td></tr>';
                document.getElementById('pagination-wrap').innerHTML = '';
                document.getElementById('summary-bar').style.display = 'none';
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

            // Summary bar
            const totalComision = data.data.reduce((s, t) => s + Number(t.ComisionRevendedor), 0);
            const bar = document.getElementById('summary-bar');
            bar.style.display = '';
            document.getElementById('period-total').textContent = fmt(totalComision);
            document.getElementById('period-count').textContent = data.total + ' transacciones en total';

            renderPagination(data.totalPages);
        } catch (e) {
            console.error(e);
            document.getElementById('hist-body').innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Error de red.</td></tr>';
        }
    }

    function renderPagination(totalPages) {
        const wrap = document.getElementById('pagination-wrap');
        if (totalPages <= 1) { wrap.innerHTML = ''; return; }
        let html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
        for (let i = 1; i <= totalPages; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <button class="page-link" data-page="${i}">${i}</button></li>`;
        }
        html += '</ul></nav>';
        wrap.innerHTML = html;
        wrap.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => { currentPage = parseInt(btn.dataset.page); load(); });
        });
    }

    let searchTimer;
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; load(); }, 400);
    });
    document.getElementById('dateFrom').addEventListener('change', () => { currentPage = 1; load(); });
    document.getElementById('dateTo').addEventListener('change', () => { currentPage = 1; load(); });
    document.getElementById('btnClear').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        currentPage = 1;
        load();
    });

    load();
})();
