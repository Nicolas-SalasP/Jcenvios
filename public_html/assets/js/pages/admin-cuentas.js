document.addEventListener('DOMContentLoaded', () => {
    const tbodyOrigen = document.querySelector('#tabla-origen tbody');
    const tbodyDestino = document.querySelector('#tabla-destino tbody');
    const modalElement = document.getElementById('cuentaModal');
    const modalInstance = new bootstrap.Modal(modalElement);
    const form = document.getElementById('cuenta-form');
    const btnNueva = document.getElementById('btn-nueva-cuenta');
    
    const saldoInicialContainer = document.getElementById('container-saldo-inicial');
    const instruccionesContainer = document.getElementById('container-instrucciones');
    const formaPagoContainer = document.getElementById('container-forma-pago');
    const rolSelect = document.getElementById('rol-cuenta-id');
    const formaPagoSelect = document.getElementById('forma-pago-id');

    const msgModalEl = document.getElementById('msgModal');
    const msgModalInstance = new bootstrap.Modal(msgModalEl);
    const msgTitle = document.getElementById('msgModalTitle');
    const msgBody = document.getElementById('msgModalBody');
    const msgHeader = document.getElementById('msgModalHeader');
    const btnCancel = document.getElementById('btn-msg-cancel');
    const btnConfirm = document.getElementById('btn-msg-confirm');

    let cuentasData = [];
    let deleteIdTarget = null;

    const showMsg = (title, text, type = 'info') => {
        msgTitle.textContent = title;
        msgBody.textContent = text;
        
        msgHeader.className = 'modal-header text-white';
        btnConfirm.classList.add('d-none');
        btnCancel.textContent = 'Cerrar';
        btnCancel.className = 'btn btn-secondary';

        if (type === 'success') {
            msgHeader.classList.add('bg-success');
        } else if (type === 'error') {
            msgHeader.classList.add('bg-danger');
        } else {
            msgHeader.classList.add('bg-primary');
        }
        msgModalInstance.show();
    };

    const executeDelete = async (id) => {
        try {
            await fetch('../api/?accion=deleteCuentaAdmin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            loadCuentas();
            showMsg('Éxito', 'Cuenta eliminada correctamente', 'success');
        } catch (error) {
            showMsg('Error', 'No se pudo eliminar la cuenta', 'error');
        }
    };

    const showConfirm = (text, callbackId) => {
        msgTitle.textContent = 'Confirmación';
        msgBody.textContent = text;
        deleteIdTarget = callbackId;

        msgHeader.className = 'modal-header bg-warning text-dark';
        
        btnCancel.textContent = 'Cancelar';
        btnConfirm.classList.remove('d-none');
        btnConfirm.className = 'btn btn-danger';
        btnConfirm.textContent = 'Eliminar';
        
        const newBtn = btnConfirm.cloneNode(true);
        btnConfirm.parentNode.replaceChild(newBtn, btnConfirm);
        
        newBtn.addEventListener('click', async () => {
            msgModalInstance.hide();
            await executeDelete(deleteIdTarget);
        });

        msgModalInstance.show();
    };

    const loadCuentas = async () => {
        try {
            const response = await fetch('../api/?accion=getCuentasAdmin');
            const result = await response.json();
            if (result.success) {
                cuentasData = result.cuentas;
                renderTables();
            } else {
                showMsg('Error', "Error al cargar datos: " + result.error, 'error');
            }
        } catch (error) {
            console.error(error);
        }
    };

    const renderTables = () => {
        tbodyOrigen.innerHTML = '';
        tbodyDestino.innerHTML = '';

        if (!cuentasData || cuentasData.length === 0) {
            const emptyRow = '<tr><td colspan="8" class="text-center text-muted">No hay cuentas configuradas.</td></tr>';
            tbodyOrigen.innerHTML = emptyRow;
            tbodyDestino.innerHTML = emptyRow;
            return;
        }

        let countOrigen = 0;
        let countDestino = 0;

        cuentasData.forEach(c => {
            const saldo = parseFloat(c.SaldoActual || 0).toLocaleString('es-ES', { minimumFractionDigits: 2 });
            const rolId = parseInt(c.RolCuentaID); 

            const acciones = `
                <button class="btn btn-sm btn-primary btn-edit" data-id="${c.CuentaAdminID}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-danger btn-delete" data-id="${c.CuentaAdminID}"><i class="bi bi-trash"></i></button>
            `;

            const rowHTML = `
                <tr>
                    <td><strong>${c.NombrePais}</strong></td>
                    <td><span class="badge bg-info text-dark">${c.FormaPagoNombre || '-'}</span></td>
                    <td>
                        <strong>${c.Banco}</strong><br>
                        <small>${c.Titular}</small>
                    </td>
                    <td>
                        ${c.TipoCuenta}<br>
                        ${c.NumeroCuenta}
                    </td>
                    <td class="text-end text-success fw-bold">${saldo}</td>
                    <td><div style="width: 30px; height: 30px; background-color: ${c.ColorHex}; border-radius: 4px; border: 1px solid #ccc;"></div></td>
                    <td>
                        <span class="badge ${c.Activo == 1 ? 'bg-success' : 'bg-secondary'}">
                            ${c.Activo == 1 ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td>${acciones}</td>
                </tr>
            `;
                    
            if (rolId === 1 || rolId === 3) {
                tbodyOrigen.innerHTML += rowHTML;
                countOrigen++;
            }
            if (rolId === 2 || rolId === 3) {
                tbodyDestino.innerHTML += rowHTML;
                countDestino++;
            }
        });

        if (countOrigen === 0) tbodyOrigen.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No hay cuentas de Origen configuradas.</td></tr>';
        if (countDestino === 0) tbodyDestino.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No hay cuentas de Destino configuradas.</td></tr>';
    };

    const toggleCamposPorRol = () => {
        const rol = parseInt(rolSelect.value);
        if (rol === 2) { 
            if(instruccionesContainer) instruccionesContainer.classList.add('d-none');
            if(formaPagoContainer) formaPagoContainer.classList.add('d-none');
        } else {
            if(instruccionesContainer) instruccionesContainer.classList.remove('d-none');
            if(formaPagoContainer) formaPagoContainer.classList.remove('d-none');
        }
    };

    rolSelect.addEventListener('change', toggleCamposPorRol);

    btnNueva.addEventListener('click', () => {
        form.reset();
        document.getElementById('cuenta-id').value = '';
        document.getElementById('cuentaModalLabel').textContent = 'Nueva Cuenta Bancaria';
        document.getElementById('color-hex').value = '#000000';
        rolSelect.value = '1';
        
        const paisSelect = document.getElementById('pais-id');
        if (paisSelect.options.length > 0) paisSelect.selectedIndex = 0;
        if (formaPagoSelect.options.length > 0) formaPagoSelect.selectedIndex = 0;

        if (saldoInicialContainer) {
            saldoInicialContainer.classList.remove('d-none');
            document.getElementById('saldo-inicial').value = '0.00';
        }
        toggleCamposPorRol();
    });

    const handleTableClick = async (e) => {
        const btnEdit = e.target.closest('.btn-edit');
        const btnDelete = e.target.closest('.btn-delete');

        if (btnEdit) {
            const id = btnEdit.dataset.id;
            const cuenta = cuentasData.find(c => c.CuentaAdminID == id);
            if (cuenta) {
                document.getElementById('cuenta-id').value = cuenta.CuentaAdminID;
                rolSelect.value = cuenta.RolCuentaID || 1;
                document.getElementById('pais-id').value = cuenta.PaisID;
                document.getElementById('forma-pago-id').value = cuenta.FormaPagoID;
                document.getElementById('banco').value = cuenta.Banco;
                document.getElementById('titular').value = cuenta.Titular;
                document.getElementById('tipo-cuenta').value = cuenta.TipoCuenta;
                document.getElementById('numero-cuenta').value = cuenta.NumeroCuenta;
                document.getElementById('rut').value = cuenta.RUT;
                document.getElementById('email').value = cuenta.Email;
                document.getElementById('instrucciones').value = cuenta.Instrucciones;
                document.getElementById('color-hex').value = cuenta.ColorHex;
                document.getElementById('activo').value = cuenta.Activo;

                document.getElementById('cuentaModalLabel').textContent = 'Editar Cuenta';
                if (saldoInicialContainer) saldoInicialContainer.classList.add('d-none');
                
                toggleCamposPorRol();
                modalInstance.show();
            }
        }

        if (btnDelete) {
            const id = btnDelete.dataset.id;
            showConfirm('¿Estás seguro de que deseas eliminar esta cuenta permanentemente?', id);
        }
    };

    document.querySelector('#tabla-origen').addEventListener('click', handleTableClick);
    document.querySelector('#tabla-destino').addEventListener('click', handleTableClick);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        if (saldoInicialContainer && saldoInicialContainer.classList.contains('d-none')) {
            delete data.saldoInicial;
        }

        try {
            const response = await fetch('../api/?accion=saveCuentaAdmin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            if(result.success) {
                modalInstance.hide();
                loadCuentas();
                showMsg('Éxito', 'Cuenta guardada correctamente', 'success');
            } else {
                showMsg('Error', result.error || 'Ocurrió un error al guardar', 'error');
            }
        } catch(err) {
            showMsg('Error', 'Error de conexión', 'error');
        }
    });

    loadCuentas();
});