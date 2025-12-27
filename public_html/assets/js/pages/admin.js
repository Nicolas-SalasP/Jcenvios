document.addEventListener('DOMContentLoaded', () => {

    // =================================================
    // 0. OPERADORES: LÓGICA DE COPIADO DE DATOS
    // =================================================
    const copyModalElement = document.getElementById('copyDataModal');
    if (copyModalElement) {
        const copyModal = new bootstrap.Modal(copyModalElement);
        const fields = {
            banco: document.getElementById('copy-banco'),
            doc: document.getElementById('copy-doc'),
            cuenta: document.getElementById('copy-cuenta'),
            nombre: document.getElementById('copy-nombre'),
            montoDisplay: document.getElementById('copy-monto-display'),
            montoValue: document.getElementById('copy-monto-value'),
            labelCuenta: document.getElementById('label-cuenta-tipo'),
            txId: document.getElementById('copy-tx-id'),
            btnFinalizar: document.getElementById('btn-ir-a-finalizar')
        };

        document.querySelectorAll('.copy-data-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const data = JSON.parse(e.currentTarget.dataset.datos);

                if (fields.txId) fields.txId.textContent = data.id;
                fields.banco.value = data.banco;
                fields.doc.value = data.doc;
                fields.cuenta.value = data.cuenta;
                fields.nombre.value = data.nombre;
                fields.montoDisplay.textContent = data.monto;
                if (fields.montoValue) fields.montoValue.value = data.monto;
                fields.labelCuenta.textContent = data.tipo;

                if (fields.btnFinalizar) {
                    fields.btnFinalizar.onclick = () => {
                        copyModal.hide();
                        const uploadBtn = document.querySelector(`button[data-bs-target="#adminUploadModal"][data-tx-id="${data.id}"]`);
                        if (uploadBtn) {
                            uploadBtn.click();
                        } else {
                            const adminModal = new bootstrap.Modal(document.getElementById('adminUploadModal'));
                            document.getElementById('modal-admin-tx-id').textContent = data.id;
                            document.getElementById('adminTransactionIdField').value = data.id;
                            adminModal.show();
                        }
                    };
                }
                copyModal.show();
            });
        });
    }

    // ==========================================
    // 1. GESTIÓN DE VERIFICACIONES (KYC)
    // ==========================================
    const verificationModalElement = document.getElementById('verificationModal');

    if (verificationModalElement) {
        let verificationModalInstance = null;
        try {
            verificationModalInstance = bootstrap.Modal.getOrCreateInstance(verificationModalElement);
        } catch (e) { console.error(e); }

        const els = {
            nameHeader: document.getElementById('modalUserName'),
            fullName: document.getElementById('verif-fullname'),
            doc: document.getElementById('verif-doc'),
            email: document.getElementById('verif-email'),
            phone: document.getElementById('verif-phone'),
            imgProfile: document.getElementById('verif-profile-pic'),
            imgF: document.getElementById('modalImgFrente'),
            imgR: document.getElementById('modalImgReverso'),
            linkF: document.getElementById('linkFrente'),
            linkR: document.getElementById('linkReverso')
        };

        const actionButtons = verificationModalElement.querySelectorAll('.action-btn');
        let currentUserId = null;
        const defaultProfilePic = '../assets/img/SoloLogoNegroSinFondo.png';

        verificationModalElement.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            currentUserId = btn.dataset.userId;

            els.nameHeader.textContent = btn.dataset.userName || 'Usuario';
            els.fullName.textContent = btn.dataset.fullName || 'N/A';
            els.email.textContent = btn.dataset.email || 'N/A';
            els.phone.textContent = btn.dataset.phone || 'N/A';
            els.doc.textContent = `${btn.dataset.docType || 'Doc'}: ${btn.dataset.docNum || 'N/A'}`;

            const urlFrente = btn.dataset.imgFrente ? `../admin/view_secure_file.php?file=${encodeURIComponent(btn.dataset.imgFrente)}` : '';
            const urlReverso = btn.dataset.imgReverso ? `../admin/view_secure_file.php?file=${encodeURIComponent(btn.dataset.imgReverso)}` : '';
            const urlPerfil = btn.dataset.fotoPerfil ? `../admin/view_secure_file.php?file=${encodeURIComponent(btn.dataset.fotoPerfil)}` : defaultProfilePic;

            if (els.imgProfile) els.imgProfile.src = urlPerfil;

            if (els.imgF) {
                els.imgF.src = urlFrente || '';
                els.imgF.alt = urlFrente ? "Cargando..." : "No subida";
            }
            if (els.imgR) {
                els.imgR.src = urlReverso || '';
                els.imgR.alt = urlReverso ? "Cargando..." : "No subida";
            }

            if (els.linkF) {
                els.linkF.href = urlFrente || '#';
                els.linkF.classList.toggle('disabled', !urlFrente);
            }
            if (els.linkR) {
                els.linkR.href = urlReverso || '#';
                els.linkR.classList.toggle('disabled', !urlReverso);
            }
        });

        actionButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const action = button.dataset.action;
                if (!currentUserId) return;

                const confirmed = await window.showConfirmModal('Confirmar Acción', `¿Estás seguro de marcar como ${action} al usuario #${currentUserId}?`);

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
                            window.showInfoModal('Éxito', `Usuario marcado como ${action}.`, true, () => window.location.reload());
                        } else {
                            window.showInfoModal('Error', result.error || 'Error al actualizar.', false);
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
                        window.location.reload();
                    }
                } catch (e) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        });
    });

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

    // =================================================
    // 3. GESTIÓN DE USUARIOS (REFACTORIZADO PARA AJAX)
    // =================================================
    const filterForm = document.getElementById('filter-form');
    const tableContent = document.getElementById('table-content');

    // Función principal de carga AJAX
    async function loadUsers(url) {
        if (!tableContent) return;
        try {
            const ajaxUrl = url.includes('?') ? `${url}&ajax=1` : `${url}?ajax=1`;
            tableContent.style.opacity = '0.5';
            const response = await fetch(ajaxUrl);
            const html = await response.text();
            tableContent.innerHTML = html;
            tableContent.style.opacity = '1';
            window.history.pushState({}, '', url);
        } catch (error) {
            console.error('Error AJAX:', error);
            if (tableContent) tableContent.style.opacity = '1';
        }
    }

    // Listener para filtros
    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(this)).toString();
            loadUsers(`usuarios.php?${params}`);
        });
    }

    // Listener para limpiar filtros
    const clearBtn = document.getElementById('clear-filters');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            const s = document.getElementById('search-input');
            const r = document.getElementById('rol-select');
            if (s) s.value = '';
            if (r) r.value = '';
            loadUsers('usuarios.php');
        });
    }

    /**
     * DELEGACIÓN DE EVENTOS PARA USUARIOS
     * Se usa document.addEventListener para que los botones funcionen después de recargar la tabla vía AJAX.
     */
    document.addEventListener('click', async (e) => {
        const target = e.target;

        // 3.1 Paginación
        const pageLink = target.closest('.page-link');
        if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
            const url = pageLink.getAttribute('href');
            if (url && url !== '#' && !url.startsWith('javascript')) {
                e.preventDefault();
                loadUsers(url);
            }
        }

        // 3.2 Bloquear/Desbloquear Usuario
        const blockBtn = target.closest('.block-user-btn');
        if (blockBtn) {
            const userId = blockBtn.dataset.userId;
            const newStatus = blockBtn.dataset.currentStatus === 'active' ? 'blocked' : 'active';
            if (await window.showConfirmModal('Confirmar', `¿${newStatus === 'blocked' ? 'Bloquear' : 'Desbloquear'} usuario?`)) {
                try {
                    const res = await fetch('../api/?accion=toggleUserBlock', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId, newStatus })
                    });
                    if ((await res.json()).success) {
                        loadUsers(window.location.href); // Recarga parcial para mantener filtros
                    }
                } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        }

        // 3.3 Eliminar Usuario
        const deleteBtn = target.closest('.admin-delete-user-btn');
        if (deleteBtn) {
            const userId = deleteBtn.dataset.userId;
            const firstConfirm = await window.showConfirmModal('Confirmar Eliminación', '¿Seguro? Esta acción enviará al usuario a la papelera.');
            if (firstConfirm) {
                await new Promise(resolve => setTimeout(resolve, 500));
                const secondConfirm = await window.showConfirmModal('Confirmación Final', 'Se ocultarán todos los datos de la vista principal. ¿Proceder?');
                if (secondConfirm) {
                    try {
                        const res = await fetch('../api/?accion=deleteUser', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ userId })
                        });
                        const result = await res.json();
                        if (result.success) {
                            window.showInfoModal('Éxito', 'Usuario eliminado.', true);
                            loadUsers(window.location.href);
                        } else {
                            window.showInfoModal('Error', result.error || 'No se pudo eliminar.', false);
                        }
                    } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
                }
            }
        }

        // 3.4 Abrir Modal de Edición
        const editBtn = target.closest('.admin-edit-user-btn');
        if (editBtn) {
            const d = editBtn.dataset;
            document.getElementById('edit-user-id').value = d.userId;
            document.getElementById('edit-nombre1').value = d.nombre1;
            document.getElementById('edit-nombre2').value = d.nombre2 || '';
            document.getElementById('edit-apellido1').value = d.apellido1;
            document.getElementById('edit-apellido2').value = d.apellido2 || '';
            document.getElementById('edit-telefono').value = d.telefono;
            document.getElementById('edit-documento').value = d.documento;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        // 3.5 Ver Documentos (Modal)
        const docsBtn = target.closest('.view-user-docs-btn');
        if (docsBtn) {
            const d = docsBtn.dataset;
            const docsName = document.getElementById('docsUserName');
            const docsImgProfile = document.getElementById('docsProfilePic');
            const docsImgFrente = document.getElementById('docsImgFrente');
            const docsImgReverso = document.getElementById('docsImgReverso');
            const docsLinkFrente = document.getElementById('docsLinkFrente');
            const docsLinkReverso = document.getElementById('docsLinkReverso');
            const defaultPic = '../assets/img/SoloLogoNegroSinFondo.png';

            docsName.textContent = d.userName;
            const urlProfile = d.fotoPerfil ? `../admin/view_secure_file.php?file=${encodeURIComponent(d.fotoPerfil)}` : defaultPic;
            const urlFrente = d.imgFrente ? `../admin/view_secure_file.php?file=${encodeURIComponent(d.imgFrente)}` : '';
            const urlReverso = d.imgReverso ? `../admin/view_secure_file.php?file=${encodeURIComponent(d.imgReverso)}` : '';

            if (docsImgProfile) docsImgProfile.src = urlProfile;
            if (docsImgFrente) { docsImgFrente.src = urlFrente || ''; docsImgFrente.alt = urlFrente ? "Cargando..." : "No disponible"; }
            if (docsImgReverso) { docsImgReverso.src = urlReverso || ''; docsImgReverso.alt = urlReverso ? "Cargando..." : "No disponible"; }
            if (docsLinkFrente) { docsLinkFrente.href = urlFrente || '#'; docsLinkFrente.classList.toggle('disabled', !urlFrente); }
            if (docsLinkReverso) { docsLinkReverso.href = urlReverso || '#'; docsLinkReverso.classList.toggle('disabled', !urlReverso); }

            new bootstrap.Modal(document.getElementById('userDocsModal')).show();
        }
    });

    // 3.6 Cambio de Rol (Select change con delegación)
    document.addEventListener('change', async (e) => {
        if (e.target.classList.contains('admin-role-select')) {
            const select = e.target;
            const confirmed = await window.showConfirmModal('Confirmar', '¿Cambiar rol de usuario?');
            if (confirmed) {
                try {
                    const response = await fetch('../api/?accion=updateUserRole', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId: select.dataset.userId, newRoleId: select.value })
                    });
                    const result = await response.json();
                    if (result.success) {
                        window.showInfoModal('Éxito', 'Rol actualizado correctamente.', true);
                    } else {
                        window.showInfoModal('Error', result.error || 'No se pudo actualizar.', false);
                        loadUsers(window.location.href); // Revertir visualmente
                    }
                } catch (error) {
                    window.showInfoModal('Error', 'Error de conexión.', false);
                    loadUsers(window.location.href);
                }
            } else {
                loadUsers(window.location.href); // Revertir visualmente si cancela
            }
        }
    });

    // 3.7 Envío de Formulario Editar Usuario (Simple submit)
    const editUserForm = document.getElementById('edit-user-form');
    if (editUserForm) {
        editUserForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const res = await fetch('../api/?accion=adminUpdateUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(new FormData(editUserForm).entries()))
                });
                const result = await res.json();
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                    window.showInfoModal('Éxito', 'Datos de usuario actualizados.', true, () => loadUsers(window.location.href));
                } else {
                    window.showInfoModal('Error', result.error, false);
                }
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
        });
    }


    // ==========================================
    // 4. GESTIÓN DE TRANSACCIONES
    // ==========================================

    // Confirmar Pago
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

    // Editar Comisión
    const editCommissionModalElement = document.getElementById('editCommissionModal');
    if (editCommissionModalElement) {
        const editCommissionModal = new bootstrap.Modal(editCommissionModalElement);
        const form = document.getElementById('edit-commission-form');
        const txIdInput = document.getElementById('commission-tx-id');
        const commissionInput = document.getElementById('new-commission-input');

        document.querySelectorAll('.edit-commission-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const d = e.currentTarget.dataset;
                txIdInput.value = d.txId;
                commissionInput.value = d.currentVal;
                editCommissionModal.show();
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true; submitBtn.textContent = 'Guardando...';

            const data = { transactionId: txIdInput.value, newCommission: parseFloat(commissionInput.value) };

            try {
                const res = await fetch('../api/?accion=updateTxCommission', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const r = await res.json();
                if (r.success) {
                    window.showInfoModal('Éxito', 'Comisión actualizada.', true, () => window.location.reload());
                } else window.showInfoModal('Error', r.error, false);
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            finally { submitBtn.disabled = false; submitBtn.textContent = 'Guardar'; }
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

                if (!reason) { alert('Por favor, escribe un motivo.'); return; }
                document.querySelectorAll('.confirm-reject-btn').forEach(b => b.disabled = true);

                try {
                    const response = await fetch('../api/?accion=rejectTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ transactionId: txId, reason: reason, actionType: type })
                    });
                    const result = await response.json();
                    rejectionModal.hide();

                    if (result.success) {
                        window.showInfoModal('Éxito', type === 'retry' ? 'Solicitud enviada.' : 'Cancelada.', true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', result.error, false);
                    }
                } catch (error) { window.showInfoModal('Error', 'Error de conexión.', false); }
                finally { document.querySelectorAll('.confirm-reject-btn').forEach(b => b.disabled = false); }
            });
        });
    }

    // Subida Comprobante Admin
    const adminUploadModalElement = document.getElementById('adminUploadModal');
    if (adminUploadModalElement) {
        const adminUploadForm = document.getElementById('admin-upload-form');
        const adminTxIdLabel = document.getElementById('modal-admin-tx-id');
        const adminTransactionIdField = document.getElementById('adminTransactionIdField');

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
                    const response = await fetch('../api/?accion=adminUploadProof', { method: 'POST', body: formData });
                    const modalInstance = bootstrap.Modal.getInstance(adminUploadModalElement);
                    if (modalInstance) modalInstance.hide();
                    const res = await response.json();
                    if (res.success) {
                        window.showInfoModal('Éxito', 'Transacción completada.', true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', res.error || 'Error al subir.', false);
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
            const secureUrl = `../admin/view_secure_file.php?file=${encodeURIComponent(current.url)}`;
            const fileName = current.url.split('/').pop();
            const ext = fileName.split('.').pop().toLowerCase();

            modalLabel.textContent = `Comprobante (Tx #${currentTxId})`;
            downloadButton.href = secureUrl;
            downloadButton.download = fileName;
            filenameSpan.textContent = fileName;

            if (['jpg', 'jpeg', 'png'].includes(ext)) {
                const img = document.createElement('img');
                img.src = secureUrl;
                img.classList.add('img-fluid');
                img.style.maxHeight = '75vh';
                img.style.display = 'none';
                img.onload = () => { modalPlaceholder.classList.add('d-none'); img.style.display = 'block'; downloadButton.classList.remove('disabled'); };
                modalContent.appendChild(img);
            } else {
                const iframe = document.createElement('iframe');
                iframe.src = secureUrl;
                iframe.style.width = '100%'; iframe.style.height = '75vh';
                iframe.onload = () => { modalPlaceholder.classList.add('d-none'); downloadButton.classList.remove('disabled'); };
                modalContent.appendChild(iframe);
            }

            if (comprobantes.length > 1) {
                indicatorSpan.textContent = `${index + 1} / ${comprobantes.length}`;
                prevButton.disabled = (index === 0);
                nextButton.disabled = (index === comprobantes.length - 1);
            }
        };

        viewModalElement.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (!btn) return;
            currentTxId = btn.dataset.txId;
            const userUrl = btn.dataset.comprobanteUrl;
            const adminUrl = btn.dataset.envioUrl;

            comprobantes = [];
            if (userUrl) comprobantes.push({ url: userUrl });
            if (adminUrl) comprobantes.push({ url: adminUrl });

            navigationDiv.classList.toggle('d-none', comprobantes.length <= 1);
            currentIndex = 0;
            if (btn.classList.contains('btn-success') && comprobantes.length > 1) currentIndex = 1;

            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
            if (comprobantes.length > 0) setTimeout(() => showComprobante(currentIndex), 100);
        });

        prevButton.addEventListener('click', () => showComprobante(currentIndex - 1));
        nextButton.addEventListener('click', () => showComprobante(currentIndex + 1));
    }

    // Copiar al portapapeles
    window.copyToClipboard = (elementId, btnElement) => {
        const input = document.getElementById(elementId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(() => showFeedback(btnElement));
        } else {
            document.execCommand('copy');
            showFeedback(btnElement);
        }
    };

    function showFeedback(btn) {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 1500);
    }

    // Manejo del Modal de Pausa
    const pauseModal = document.getElementById('pauseModal');
    if (pauseModal) {
        pauseModal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            const txId = btn.dataset.txId;
            document.getElementById('pause-tx-id').value = txId;
        });
    }

    // Envío del formulario de Pausa
    const pauseForm = document.getElementById('pause-form');
    if (pauseForm) {
        pauseForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(pauseForm);
            const data = Object.fromEntries(formData.entries());
            try {
                const res = await fetch('../api/?accion=pauseTransaction', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) { location.reload(); }
                else { alert("Error: " + result.error); }
            } catch (err) { alert("Error de conexión."); }
        });
    }

    // Botón Reanudar (Acción directa)
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.resume-btn');
        if (btn) {
            const txId = btn.dataset.txId;
            if (confirm("¿Deseas reanudar esta orden a 'En Proceso'?")) {
                try {
                    const res = await fetch('../api/?accion=processTransaction', {
                        method: 'POST',
                        body: new URLSearchParams({ transactionId: txId })
                    });
                    const data = await res.json();
                    if (data.success) location.reload();
                    else alert(data.error);
                } catch (err) { alert("Error al reanudar."); }
            }
        }
    });

});