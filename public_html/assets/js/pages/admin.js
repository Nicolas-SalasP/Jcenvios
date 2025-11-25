document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. GESTIÓN DE VERIFICACIONES (KYC)
    // ==========================================
    const verificationModalElement = document.getElementById('verificationModal');
    if (verificationModalElement) {
        let verificationModalInstance = null;
        try {
            verificationModalInstance = bootstrap.Modal.getOrCreateInstance(verificationModalElement);
        } catch (e) { console.error(e); }

        const modalUserName = document.getElementById('modalUserName');
        const modalImgFrente = document.getElementById('modalImgFrente');
        const modalImgReverso = document.getElementById('modalImgReverso');
        const actionButtons = verificationModalElement.querySelectorAll('.action-btn');
        let currentUserId = null;

        verificationModalElement.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            currentUserId = button.dataset.userId;
            modalUserName.textContent = button.dataset.userName || 'Usuario Desconocido';
            const imgFrente = button.dataset.imgFrente || '';
            const imgReverso = button.dataset.imgReverso || '';
            modalImgFrente.src = imgFrente ? `../admin/view_secure_file.php?file=${encodeURIComponent(imgFrente)}` : '';
            modalImgReverso.src = imgReverso ? `../admin/view_secure_file.php?file=${encodeURIComponent(imgReverso)}` : '';
            modalImgFrente.style.objectFit = 'contain';
            modalImgFrente.style.maxHeight = '300px';
            modalImgReverso.style.objectFit = 'contain';
            modalImgReverso.style.maxHeight = '300px';
        });

        actionButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const action = button.dataset.action;
                if (!currentUserId) return;
                const confirmed = await window.showConfirmModal('Confirmar Acción', `¿${action} usuario #${currentUserId}?`);
                if (confirmed) {
                    try {
                        const response = await fetch('../api/?accion=updateVerificationStatus', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ userId: currentUserId, newStatus: action })
                        });
                        const result = await response.json();
                        if (verificationModalInstance) verificationModalInstance.hide();

                        if (result.success) {
                            window.showInfoModal('Éxito', `Usuario ${action}.`, true, () => window.location.reload());
                        } else {
                            window.showInfoModal('Error', result.error, false);
                        }
                    } catch (error) {
                        window.showInfoModal('Error', 'Error de conexión.', false);
                    }
                }
            });
        });
    }

    // ==========================================
    // 2. GESTIÓN DE PAÍSES
    // ==========================================

    // Toggle Estado (Activo/Inactivo)
    document.querySelectorAll('.toggle-status-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const paisId = btn.dataset.paisId;
            const newStatus = btn.dataset.currentStatus === '1' ? 0 : 1;

            if (await window.showConfirmModal('Confirmar', '¿Cambiar estado del país?')) {
                try {
                    const res = await fetch('../api/?accion=togglePaisStatus', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ paisId, newStatus })
                    });
                    if ((await res.json()).success) {
                        btn.dataset.currentStatus = newStatus;
                        btn.textContent = newStatus === 1 ? 'Activo' : 'Inactivo';
                        btn.classList.toggle('btn-success', newStatus === 1);
                        btn.classList.toggle('btn-secondary', newStatus === 0);
                        window.showInfoModal('Éxito', 'Estado actualizado.', true);
                    }
                } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        });
    });

    // Cambiar Rol de País
    document.querySelectorAll('.role-select').forEach(select => {
        let original = select.value;
        select.addEventListener('focus', () => original = select.value);
        select.addEventListener('change', async (e) => {
            if (await window.showConfirmModal('Confirmar', '¿Cambiar rol del país?')) {
                try {
                    const res = await fetch('../api/?accion=updatePaisRol', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ paisId: e.target.dataset.paisId, newRole: e.target.value })
                    });
                    if ((await res.json()).success) {
                        original = e.target.value;
                        window.showInfoModal('Éxito', 'Rol actualizado.', true);
                    } else {
                        e.target.value = original;
                    }
                } catch (e) { e.target.value = original; }
            } else {
                e.target.value = original;
            }
        });
    });

    // Modal Añadir País
    const addPaisForm = document.getElementById('add-pais-form');
    if (addPaisForm) {
        addPaisForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = addPaisForm.querySelector('button[type="submit"]');
            const formData = new FormData(addPaisForm);
            const data = Object.fromEntries(formData.entries());

            btn.disabled = true; btn.textContent = 'Añadiendo...';
            try {
                const res = await fetch('../api/?accion=addPais', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await res.json();
                if (res.ok && result.success) window.showInfoModal('Éxito', 'País añadido.', true, () => window.location.reload());
                else throw new Error(result.error);
            } catch (error) {
                window.showInfoModal('Error', error.message, false);
                btn.disabled = false; btn.textContent = 'Añadir País';
            }
        });
    }

    // Modal Editar País
    const editPaisModalElement = document.getElementById('editPaisModal');
    if (editPaisModalElement) {
        const editPaisModal = new bootstrap.Modal(editPaisModalElement);
        const editForm = document.getElementById('edit-pais-form');
        const inputId = document.getElementById('edit-pais-id');
        const inputNombre = document.getElementById('edit-nombrePais');
        const inputMoneda = document.getElementById('edit-codigoMoneda');

        document.querySelectorAll('.edit-pais-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const t = e.currentTarget;
                inputId.value = t.dataset.paisId;
                inputNombre.value = t.dataset.nombre;
                inputMoneda.value = t.dataset.moneda;
            });
        });

        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = editForm.querySelector('button[type="submit"]');
            const data = { paisId: inputId.value, nombrePais: inputNombre.value, codigoMoneda: inputMoneda.value };

            btn.disabled = true; btn.textContent = 'Guardando...';
            try {
                const res = await fetch('../api/?accion=updatePais', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await res.json();
                if (res.ok && result.success) {
                    editPaisModal.hide();
                    window.showInfoModal('Éxito', 'País actualizado.', true, () => window.location.reload());
                } else throw new Error(result.error);
            } catch (error) {
                window.showInfoModal('Error', error.message, false);
            } finally {
                btn.disabled = false; btn.textContent = 'Guardar Cambios';
            }
        });
    }

    // ==========================================
    // 3. GESTIÓN DE USUARIOS
    // ==========================================

    // Bloquear/Desbloquear
    document.querySelectorAll('.block-user-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const userId = btn.dataset.userId;
            const newStatus = btn.dataset.currentStatus === 'active' ? 'blocked' : 'active';
            if (await window.showConfirmModal('Confirmar', `¿${newStatus === 'blocked' ? 'Bloquear' : 'Desbloquear'} usuario?`)) {
                try {
                    const res = await fetch('../api/?accion=toggleUserBlock', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId, newStatus })
                    });
                    if ((await res.json()).success) window.location.reload();
                } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        });
    });

    // Cambiar Rol de Usuario (Con manejo especial para Límite de Admins)
    document.querySelectorAll('.admin-role-select').forEach(select => {
        let original = select.value;

        select.addEventListener('focus', () => original = select.value);

        select.addEventListener('change', async (e) => {
            const confirmed = await window.showConfirmModal('Confirmar', '¿Cambiar rol de usuario?');

            if (confirmed) {
                try {
                    const response = await fetch('../api/?accion=updateUserRole', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            userId: e.target.dataset.userId,
                            newRoleId: e.target.value
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        original = e.target.value;
                        window.showInfoModal('Éxito', 'Rol actualizado correctamente.', true);
                    } else {
                        e.target.value = original;
                        if (result.error && result.error.includes('Limite alcanzado')) {
                            window.showInfoModal(
                                'Límite de Administradores',
                                "Máximo de 3 Administradores alcanzado.\n\nPor seguridad y política de la empresa, no se pueden asignar más administradores.\n\nSi necesita ampliar este cupo, por favor contacte con el soporte informático.",
                                false
                            );
                        } else {
                            window.showInfoModal('Error', result.error || 'No se pudo actualizar.', false);
                        }
                    }
                } catch (error) {
                    console.error(error);
                    e.target.value = original;
                    window.showInfoModal('Error', 'Error de comunicación con el servidor.', false);
                }
            } else {
                e.target.value = original;
            }
        });
    });

    // Eliminar Usuario (Lógico)
    document.querySelectorAll('.admin-delete-user-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            const userId = e.currentTarget.dataset.userId;
            if (await window.showConfirmModal('Confirmar Eliminación', '¿Seguro? Esta acción es irreversible.')) {
                if (await window.showConfirmModal('Confirmación Final', 'Se borrarán todos los datos. ¿Proceder?')) {
                    try {
                        const res = await fetch('../api/?accion=deleteUser', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ userId })
                        });
                        if ((await res.json()).success) {
                            document.getElementById(`user-row-${userId}`)?.remove();
                            window.showInfoModal('Éxito', 'Usuario eliminado.', true);
                        }
                    } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
                }
            }
        });
    });

    // Modal Detalles Usuario
    const userDetailsModalElement = document.getElementById('userDetailsModal');
    if (userDetailsModalElement) {
        const modalTitle = document.getElementById('userDetailsModalLabel');
        const els = {
            nombre: document.getElementById('modalUserNombreCompleto'),
            email: document.getElementById('modalUserEmail'),
            tel: document.getElementById('modalUserTelefono'),
            fecha: document.getElementById('modalUserFechaRegistro'),
            verif: document.getElementById('modalUserVerificacion'),
            tfa: document.getElementById('modalUser2FA'),
            docF: document.getElementById('modalUserDocFrenteContainer'),
            docR: document.getElementById('modalUserDocReversoContainer')
        };

        userDetailsModalElement.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (!btn || !btn.classList.contains('view-user-details-btn')) return;

            modalTitle.textContent = `Ficha Usuario: #${btn.dataset.userId}`;
            els.nombre.textContent = btn.dataset.nombreCompleto;
            els.email.textContent = btn.dataset.email;
            els.tel.textContent = btn.dataset.telefono;
            els.fecha.textContent = btn.dataset.fechaRegistro;

            els.verif.textContent = btn.dataset.verificacionStatus;
            els.verif.className = `badge ${btn.dataset.verificacionStatus === 'Verificado' ? 'bg-success' : 'bg-secondary'}`;

            els.tfa.textContent = btn.dataset.twoFaStatus === '1' ? 'Activado' : 'Inactivo';
            els.tfa.className = `badge ${btn.dataset.twoFaStatus === '1' ? 'bg-success' : 'bg-secondary'}`;

            const createImg = (url) => url ? `<a href="../admin/view_secure_file.php?file=${encodeURIComponent(url)}" target="_blank"><img src="../admin/view_secure_file.php?file=${encodeURIComponent(url)}" class="img-fluid rounded" style="max-height: 150px;"></a>` : '<span class="text-muted small">No subido</span>';

            els.docF.innerHTML = createImg(btn.dataset.docFrente);
            els.docR.innerHTML = createImg(btn.dataset.docReverso);
        });
    }

    // ==========================================
    // 4. GESTIÓN DE TRANSACCIONES
    // ==========================================

    // Confirmar Pago (Paso a "En Proceso")
    document.querySelectorAll('.process-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            const txId = e.currentTarget.dataset.txId;
            if (await window.showConfirmModal('Confirmar Pago', '¿Confirmas la recepción del dinero?')) {
                try {
                    const res = await fetch('../api/?accion=processTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ transactionId: txId })
                    });
                    if ((await res.json()).success) window.location.reload();
                } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        });
    });

    // --- Editar Comisión (Modal) ---
    const editCommissionModalElement = document.getElementById('editCommissionModal');
    if (editCommissionModalElement) {
        const editCommissionModal = new bootstrap.Modal(editCommissionModalElement);
        const form = document.getElementById('edit-commission-form');
        const txIdLabel = document.getElementById('modal-commission-tx-id-label');
        const txIdInput = document.getElementById('commission-tx-id');
        const commissionInput = document.getElementById('new-commission-input');

        document.querySelectorAll('.edit-commission-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const button = e.currentTarget;
                const txId = button.dataset.txId;
                const currentVal = button.dataset.currentVal;

                txIdLabel.textContent = `#${txId}`;
                txIdInput.value = txId;
                commissionInput.value = currentVal;

                editCommissionModal.show();
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';

            const txId = txIdInput.value;
            const newCommission = parseFloat(commissionInput.value);

            try {
                const response = await fetch('../api/?accion=updateTxCommission', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transactionId: txId,
                        newCommission: newCommission
                    })
                });

                const result = await response.json();
                editCommissionModal.hide();

                if (result.success) {
                    window.showInfoModal('Éxito', 'Comisión actualizada correctamente.', true, () => window.location.reload());
                } else {
                    window.showInfoModal('Error', result.error || 'No se pudo actualizar.', false);
                }
            } catch (error) {
                window.showInfoModal('Error', 'Error de conexión.', false);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Guardar Cambios';
            }
        });
    }

    // Modal de Rechazo
    const rejectionModalElement = document.getElementById('rejectionModal');
    if (rejectionModalElement) {
        const rejectionModal = new bootstrap.Modal(rejectionModalElement);
        const rejectTxIdInput = document.getElementById('reject-tx-id');
        const rejectTxIdLabel = document.getElementById('reject-tx-id-label');
        const rejectReasonInput = document.getElementById('reject-reason');

        rejectionModalElement.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const txId = button.getAttribute('data-tx-id');
            rejectTxIdInput.value = txId;
            if (rejectTxIdLabel) rejectTxIdLabel.textContent = txId;
            rejectReasonInput.value = '';
        });

        document.querySelectorAll('.confirm-reject-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const type = e.currentTarget.dataset.type;
                const reason = rejectReasonInput.value.trim();
                const txId = rejectTxIdInput.value;

                if (!reason) {
                    alert('Por favor, escribe un motivo para el rechazo.');
                    return;
                }
                document.querySelectorAll('.confirm-reject-btn').forEach(b => b.disabled = true);

                try {
                    const response = await fetch('../api/?accion=rejectTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transactionId: txId,
                            reason: reason,
                            actionType: type
                        })
                    });
                    const result = await response.json();
                    rejectionModal.hide();

                    if (result.success) {
                        const msg = type === 'retry'
                            ? 'Solicitud enviada. El cliente podrá volver a subir el comprobante.'
                            : 'Transacción cancelada definitivamente.';
                        window.showInfoModal('Éxito', msg, true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', result.error || 'Error al procesar.', false);
                    }
                } catch (error) {
                    window.showInfoModal('Error', 'Error de conexión.', false);
                } finally {
                    document.querySelectorAll('.confirm-reject-btn').forEach(b => b.disabled = false);
                }
            });
        });
    }

    // Modal Subir Comprobante Admin
    const adminUploadModalElement = document.getElementById('adminUploadModal');
    if (adminUploadModalElement) {
        let adminUploadModalInstance = null;
        try { adminUploadModalInstance = bootstrap.Modal.getOrCreateInstance(adminUploadModalElement); } catch (e) { }

        const adminTxIdLabel = document.getElementById('modal-admin-tx-id');
        const adminTransactionIdField = document.getElementById('adminTransactionIdField');
        const adminUploadForm = document.getElementById('admin-upload-form');

        adminUploadModalElement.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            const txId = button.dataset.txId;
            if (adminTxIdLabel) adminTxIdLabel.textContent = txId;
            if (adminTransactionIdField) adminTransactionIdField.value = txId;
        });

        if (adminUploadForm) {
            adminUploadForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData(adminUploadForm);
                const btn = adminUploadForm.closest('.modal-content').querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Subiendo...'; }

                try {
                    const response = await fetch('../api/?accion=adminUploadProof', {
                        method: 'POST',
                        body: formData
                    });
                    if (adminUploadModalInstance) adminUploadModalInstance.hide();
                    if ((await response.json()).success) {
                        window.showInfoModal('Éxito', 'Transacción completada.', true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', 'No se pudo subir.', false);
                    }
                } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
                finally {
                    if (btn) { btn.disabled = false; btn.textContent = 'Confirmar Envío'; }
                    adminUploadForm.reset();
                }
            });
        }
    }

    // Visor de Comprobantes
    const viewModalElement = document.getElementById('viewComprobanteModal');
    if (viewModalElement) {
        const modalContent = document.getElementById('comprobante-content');
        const modalPlaceholder = document.getElementById('comprobante-placeholder');
        const downloadButton = document.getElementById('download-comprobante');
        const filenameSpan = document.getElementById('comprobante-filename');
        const navigationDiv = document.getElementById('comprobante-navigation');
        const prevButton = document.getElementById('prev-comprobante');
        const nextButton = document.getElementById('next-comprobante');
        const indicatorSpan = document.getElementById('comprobante-indicator');
        const modalLabel = document.getElementById('viewComprobanteModalLabel');

        let comprobantes = [];
        let currentIndex = 0;
        let currentTxId = null;

        const showComprobante = (index) => {
            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
            downloadButton.classList.add('disabled');

            if (!comprobantes[index]) return;

            currentIndex = index;
            const current = comprobantes[index];
            const type = current.type;

            if (typeof baseUrlJs === 'undefined') return;
            const secureUrl = `${baseUrlJs}/dashboard/ver-comprobante.php?id=${currentTxId}&type=${type}`;
            const fileName = decodeURIComponent(current.url.split('/').pop().split('?')[0]);
            const fileExtension = fileName.split('.').pop().toLowerCase();
            const typeText = type === 'user' ? 'Comprobante de Pago' : 'Comprobante de Envío';

            if (modalLabel) modalLabel.textContent = `${typeText} (Transacción #${currentTxId})`;
            downloadButton.href = secureUrl;
            downloadButton.download = fileName;
            if (filenameSpan) filenameSpan.textContent = fileName;

            try {
                if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(fileExtension)) {
                    const img = document.createElement('img');
                    img.src = secureUrl;
                    img.alt = typeText;
                    img.classList.add('img-fluid', 'rounded', 'd-block', 'mx-auto');
                    img.style.maxHeight = '75vh';
                    img.style.maxWidth = '100%';
                    img.style.objectFit = 'contain';
                    img.style.display = 'none';

                    img.onload = () => {
                        modalPlaceholder.classList.add('d-none');
                        img.style.display = 'block';
                        downloadButton.classList.remove('disabled');
                    };
                    modalContent.appendChild(img);
                } else if (fileExtension === 'pdf') {
                    const iframe = document.createElement('iframe');
                    iframe.src = secureUrl;
                    iframe.style.width = '100%';
                    iframe.style.height = '75vh';
                    iframe.style.border = 'none';
                    iframe.onload = () => {
                        modalPlaceholder.classList.add('d-none');
                        downloadButton.classList.remove('disabled');
                    }
                    modalContent.appendChild(iframe);
                }
            } catch (e) { }

            if (comprobantes.length > 1) {
                if (indicatorSpan) indicatorSpan.textContent = `${index + 1} / ${comprobantes.length}`;
                prevButton.disabled = (index === 0);
                nextButton.disabled = (index === comprobantes.length - 1);
            } else {
                prevButton.disabled = true;
                nextButton.disabled = true;
            }
        };

        viewModalElement.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('view-comprobante-btn-admin')) return;

            const userUrl = button.dataset.comprobanteUrl || '';
            const adminUrl = button.dataset.envioUrl || '';
            const startType = button.dataset.startType || 'user';
            currentTxId = button.dataset.txId || 'N/A';

            comprobantes = [];
            if (userUrl) comprobantes.push({ type: 'user', url: userUrl });
            if (adminUrl) comprobantes.push({ type: 'admin', url: adminUrl });

            if (comprobantes.length > 1) navigationDiv.classList.remove('d-none');
            else navigationDiv.classList.add('d-none');

            currentIndex = 0;
            if (startType === 'admin' && adminUrl) currentIndex = comprobantes.findIndex(c => c.type === 'admin');

            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
            if (comprobantes.length > 0) setTimeout(() => showComprobante(currentIndex), 100);
        });

        prevButton.addEventListener('click', () => { if (currentIndex > 0) showComprobante(currentIndex - 1); });
        nextButton.addEventListener('click', () => { if (currentIndex < comprobantes.length - 1) showComprobante(currentIndex + 1); });
        viewModalElement.addEventListener('hidden.bs.modal', () => {
            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
        });
    }
});