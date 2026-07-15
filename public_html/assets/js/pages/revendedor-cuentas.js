(function () {
    'use strict';

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    let cuentaModal = null;

    async function loadReferralCode() {
        try {
            const res = await fetch('../api/?accion=getResellerReferralCode');
            const data = await res.json();
            if (!data.success) return;
            document.getElementById('referral-code').value = data.codigo;
            document.getElementById('referral-link').value = `${window.location.origin}/login.php?ref=${data.codigo}`;
        } catch (e) {
            console.error(e);
        }
    }

    function cuentaRow(c) {
        const estado = Number(c.Activo) === 1
            ? '<span class="badge bg-success">Activa</span>'
            : '<span class="badge bg-secondary">Inactiva</span>';
        return `
            <tr>
                <td>${esc(c.Banco)}</td>
                <td>${esc(c.TipoCuenta)}</td>
                <td>${esc(c.NumeroCuenta)}</td>
                <td>${esc(c.TitularNombre)}</td>
                <td>${estado}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary btn-edit-cuenta"
                        data-id="${c.CuentaID}" data-banco="${esc(c.Banco)}" data-tipo="${esc(c.TipoCuenta)}"
                        data-numero="${esc(c.NumeroCuenta)}" data-titular="${esc(c.TitularNombre)}"
                        data-doc="${esc(c.TitularDocumento)}" data-instrucciones="${esc(c.Instrucciones || '')}"
                        title="Editar"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-secondary btn-toggle-cuenta"
                        data-id="${c.CuentaID}" data-activo="${Number(c.Activo)}"
                        title="${Number(c.Activo) === 1 ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${Number(c.Activo) === 1 ? 'pause' : 'play'}"></i></button>
                    <button class="btn btn-sm btn-outline-danger btn-delete-cuenta" data-id="${c.CuentaID}" title="Eliminar">
                        <i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
    }

    async function loadCuentas() {
        try {
            const res = await fetch('../api/?accion=getResellerAccounts');
            const data = await res.json();
            const tbody = document.getElementById('cuentas-body');
            document.getElementById('cuentas-max').textContent = data.max ?? '—';

            if (!data.success || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Aún no registras cuentas bancarias.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(cuentaRow).join('');
        } catch (e) {
            console.error(e);
        }
    }

    function resetModal() {
        document.getElementById('cuentaModalTitle').textContent = 'Agregar cuenta bancaria';
        document.getElementById('cuenta-id').value = '';
        document.getElementById('cuenta-banco').value = '';
        document.getElementById('cuenta-tipo').value = '';
        document.getElementById('cuenta-numero').value = '';
        document.getElementById('cuenta-titular-nombre').value = '';
        document.getElementById('cuenta-titular-doc').value = '';
        document.getElementById('cuenta-instrucciones').value = '';
        document.getElementById('cuenta-feedback').textContent = '';
    }

    document.addEventListener('DOMContentLoaded', () => {
        cuentaModal = new bootstrap.Modal(document.getElementById('cuentaModal'));
        loadReferralCode();
        loadCuentas();

        document.getElementById('btnAddCuenta').addEventListener('click', resetModal);

        document.getElementById('btnCopyCode').addEventListener('click', () => {
            navigator.clipboard.writeText(document.getElementById('referral-code').value);
        });
        document.getElementById('btnCopyLink').addEventListener('click', () => {
            navigator.clipboard.writeText(document.getElementById('referral-link').value);
        });

        document.getElementById('cuentas-body').addEventListener('click', async (e) => {
            const editBtn = e.target.closest('.btn-edit-cuenta');
            const toggleBtn = e.target.closest('.btn-toggle-cuenta');
            const deleteBtn = e.target.closest('.btn-delete-cuenta');

            if (editBtn) {
                document.getElementById('cuentaModalTitle').textContent = 'Editar cuenta bancaria';
                document.getElementById('cuenta-id').value = editBtn.dataset.id;
                document.getElementById('cuenta-banco').value = editBtn.dataset.banco;
                document.getElementById('cuenta-tipo').value = editBtn.dataset.tipo;
                document.getElementById('cuenta-numero').value = editBtn.dataset.numero;
                document.getElementById('cuenta-titular-nombre').value = editBtn.dataset.titular;
                document.getElementById('cuenta-titular-doc').value = editBtn.dataset.doc;
                document.getElementById('cuenta-instrucciones').value = editBtn.dataset.instrucciones;
                document.getElementById('cuenta-feedback').textContent = '';
                cuentaModal.show();
            }

            if (toggleBtn) {
                const activo = toggleBtn.dataset.activo === '1';
                toggleBtn.disabled = true;
                try {
                    await fetch('../api/?accion=toggleResellerAccount', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cuentaId: parseInt(toggleBtn.dataset.id, 10), activo: !activo }),
                    });
                    await loadCuentas();
                } finally {
                    toggleBtn.disabled = false;
                }
            }

            if (deleteBtn) {
                if (!confirm('¿Eliminar esta cuenta bancaria?')) return;
                deleteBtn.disabled = true;
                try {
                    await fetch('../api/?accion=deleteResellerAccount', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cuentaId: parseInt(deleteBtn.dataset.id, 10) }),
                    });
                    await loadCuentas();
                } finally {
                    deleteBtn.disabled = false;
                }
            }
        });

        document.getElementById('btnSaveCuenta').addEventListener('click', async () => {
            const cuentaId = document.getElementById('cuenta-id').value;
            const payload = {
                banco: document.getElementById('cuenta-banco').value.trim(),
                tipoCuenta: document.getElementById('cuenta-tipo').value.trim(),
                numeroCuenta: document.getElementById('cuenta-numero').value.trim(),
                titularNombre: document.getElementById('cuenta-titular-nombre').value.trim(),
                titularDocumento: document.getElementById('cuenta-titular-doc').value.trim(),
                instrucciones: document.getElementById('cuenta-instrucciones').value.trim(),
            };

            const accion = cuentaId ? 'updateResellerAccount' : 'addResellerAccount';
            if (cuentaId) payload.cuentaId = parseInt(cuentaId, 10);

            const btn = document.getElementById('btnSaveCuenta');
            btn.disabled = true;
            try {
                const res = await fetch(`../api/?accion=${accion}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    cuentaModal.hide();
                    await loadCuentas();
                } else {
                    document.getElementById('cuenta-feedback').textContent = data.error || 'Error al guardar la cuenta.';
                }
            } catch (e) {
                document.getElementById('cuenta-feedback').textContent = 'Error de conexión.';
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
