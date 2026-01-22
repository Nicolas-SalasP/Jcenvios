document.addEventListener('DOMContentLoaded', () => {

    // =================================================
    // 0. UTILIDADES GLOBALES & HELPERS
    // =================================================

    window.showConfirmModal = async (title, message) => {
        return confirm(`${title}\n\n${message}`);
    };

    window.showInfoModal = (title, message, isSuccess = false, callback = null) => {
        alert(`${title}: ${message}`);
        if (callback) callback();
    };

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
        const originalClass = btn.className;

        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.classList.remove('btn-outline-secondary', 'btn-primary');
        btn.classList.add('btn-success');

        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.className = originalClass;
        }, 1500);
    }

    // =================================================
    // 1. OPERADORES: LÓGICA DE COPIADO DE DATOS
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

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.copy-data-btn');
            if (btn) {
                try {
                    const data = JSON.parse(btn.dataset.datos);

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
                                const adminTxLabel = document.getElementById('modal-admin-tx-id');
                                const adminTxField = document.getElementById('adminTransactionIdField');
                                if (adminTxLabel) adminTxLabel.textContent = data.id;
                                if (adminTxField) adminTxField.value = data.id;
                                adminModal.show();
                            }
                        };
                    }
                    copyModal.show();
                } catch (e) { console.error(e); }
            }
        });
    }

    // ==========================================
    // 2. GESTIÓN DE VERIFICACIONES (KYC)
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

                if (await window.showConfirmModal('Confirmar Acción', `¿Estás seguro de marcar como ${action} al usuario #${currentUserId}?`)) {
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
    // 3. GESTIÓN DE PAÍSES
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
    // 4. GESTIÓN DE USUARIOS (FILTROS Y ACCIONES)
    // =================================================
    const filterForm = document.getElementById('filter-form');
    const tableContent = document.getElementById('table-content');

    async function loadTableData(url) {
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

    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams(formData).toString();
            const currentPage = window.location.pathname.split('/').pop();
            loadTableData(`${currentPage}?${params}`);
        });
    }

    const clearBtn = document.getElementById('clear-filters');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            const s = document.getElementById('search-input');
            const r = document.getElementById('rol-select');
            if (s) s.value = '';
            if (r) r.value = '';
            const currentPage = window.location.pathname.split('/').pop();
            loadTableData(currentPage);
        });
    }

    document.addEventListener('click', async (e) => {
        const target = e.target;

        // Paginación
        const pageLink = target.closest('.page-link');
        if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
            const url = pageLink.getAttribute('href');
            if (url && url !== '#' && !url.startsWith('javascript')) {
                e.preventDefault();
                loadTableData(url);
            }
        }

        // Bloquear/Desbloquear Usuario
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
                    if ((await res.json()).success) loadTableData(window.location.href);
                } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        }

        // Eliminar Usuario (Soft Delete)
        const deleteBtn = target.closest('.admin-delete-user-btn');
        if (deleteBtn) {
            const userId = deleteBtn.dataset.userId;
            if (await window.showConfirmModal('Eliminar', '¿Seguro? Esta acción enviará al usuario a la papelera.')) {
                try {
                    const res = await fetch('../api/?accion=deleteUser', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ userId })
                    });
                    if ((await res.json()).success) {
                        window.showInfoModal('Éxito', 'Usuario eliminado.', true);
                        loadTableData(window.location.href);
                    }
                } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            }
        }

        // Editar Usuario (Modal)
        const editUserBtn = target.closest('.admin-edit-user-btn');
        if (editUserBtn) {
            const d = editUserBtn.dataset;
            const safeSetValue = (id, value) => {
                const el = document.getElementById(id);
                if (el) {
                    el.value = value;
                } else {
                    console.warn(`Advertencia: No se encontró el input con ID '${id}' en el modal.`);
                }
            };

            safeSetValue('edit-user-id', d.userId);
            safeSetValue('edit-nombre1', d.nombre1);
            safeSetValue('edit-nombre2', d.nombre2 || '');
            safeSetValue('edit-apellido1', d.apellido1);
            safeSetValue('edit-apellido2', d.apellido2 || '');
            safeSetValue('edit-telefono', d.telefono);
            safeSetValue('edit-documento', d.documento);

            const modalEl = document.getElementById('editUserModal');
            if (modalEl) {
                new bootstrap.Modal(modalEl).show();
            }
        }

        // Ver Docs Usuario
        const docsBtn = target.closest('.view-user-docs-btn');
        if (docsBtn) {
            const d = docsBtn.dataset;
            const userId = d.userId;
            const hiddenInput = document.getElementById('viewDocsUserId');
            if (hiddenInput) {
                hiddenInput.value = userId;
            }
            document.getElementById('docsUserName').textContent = d.userName;

            const updateImg = (imgId, linkId, downloadId, path, editBtnType) => {
                const imgEl = document.getElementById(imgId);
                const linkEl = document.getElementById(linkId);
                const downEl = document.getElementById(downloadId);
                const container = document.getElementById('noDoc' + imgId.replace('docsImg', ''));
                const editBtn = document.querySelector(`.btn-edit-admin-doc[data-doc-type="${editBtnType}"]`);
                if (editBtn) {
                    editBtn.dataset.userId = userId;
                }

                if (path && path.trim() !== '') {
                    const fullPath = `../admin/view_secure_file.php?file=${encodeURIComponent(path)}&t=${new Date().getTime()}`;
                    imgEl.src = fullPath;
                    linkEl.href = fullPath;
                    linkEl.classList.remove('disabled');
                    downEl.href = fullPath;
                    downEl.classList.remove('disabled');
                    imgEl.classList.remove('d-none');
                    if (container) container.classList.add('d-none');
                    if (editBtn) editBtn.disabled = false;
                } else {
                    imgEl.src = '../assets/img/SoloLogoNegroSinFondo.png';
                    linkEl.href = '#';
                    linkEl.classList.add('disabled');
                    downEl.href = '#';
                    downEl.classList.add('disabled');
                    if (container) container.classList.remove('d-none');
                    if (editBtn) editBtn.disabled = true;
                }
            };
            updateImg('docsProfilePic', 'btnProfileView', 'btnProfileDown', d.fotoPerfil, 'perfil');
            updateImg('docsImgFrente', 'btnFrenteView', 'btnFrenteDown', d.imgFrente, 'frente');
            updateImg('docsImgReverso', 'btnReversoView', 'btnReversoDown', d.imgReverso, 'reverso');

            new bootstrap.Modal(document.getElementById('userDocsModal')).show();
        }
    });

    document.addEventListener('change', async (e) => {
        if (e.target.classList.contains('admin-role-select')) {
            const select = e.target;
            if (await window.showConfirmModal('Confirmar', '¿Cambiar rol de usuario?')) {
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
                        loadTableData(window.location.href);
                    }
                } catch (error) {
                    window.showInfoModal('Error', 'Error de conexión.', false);
                    loadTableData(window.location.href);
                }
            } else {
                loadTableData(window.location.href);
            }
        }
    });

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
                    window.showInfoModal('Éxito', 'Datos de usuario actualizados.', true, () => loadTableData(window.location.href));
                } else {
                    window.showInfoModal('Error', result.error, false);
                }
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
        });
    }

    // ==========================================
    // 5. GESTIÓN DE TRANSACCIONES (MODALES Y ACCIONES)
    // ==========================================

    const pauseModalEl = document.getElementById('pauseModal');
    if (pauseModalEl) {
        pauseModalEl.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (btn) document.getElementById('pause-tx-id').value = btn.dataset.txId;
        });

        const pauseForm = document.getElementById('pause-form');
        if (pauseForm) {
            pauseForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = pauseForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                const data = Object.fromEntries(new FormData(pauseForm).entries());
                try {
                    const res = await fetch('../api/?accion=pauseTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await res.json();
                    bootstrap.Modal.getInstance(pauseModalEl).hide();
                    if (result.success) window.location.reload();
                    else window.showInfoModal('Error', result.error, false);
                } catch (err) { window.showInfoModal('Error', 'Error de conexión', false); }
                finally { btn.disabled = false; }
            });
        }
    }

    // 5.2 REANUDAR (MODAL)
    const resumeModalEl = document.getElementById('resumeModal');
    if (resumeModalEl) {
        resumeModalEl.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (btn) document.getElementById('resume-tx-id').value = btn.dataset.txId;
        });

        const resumeForm = document.getElementById('resume-form');
        if (resumeForm) {
            resumeForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = resumeForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                const data = Object.fromEntries(new FormData(resumeForm).entries());
                try {
                    const res = await fetch('../api/?accion=resumeTransactionAdmin', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await res.json();
                    bootstrap.Modal.getInstance(resumeModalEl).hide();
                    if (result.success) window.location.reload();
                    else window.showInfoModal('Error', result.error, false);
                } catch (err) { window.showInfoModal('Error', 'Error de conexión', false); }
                finally { btn.disabled = false; }
            });
        }
    }

    // 5.3 AUTORIZAR RIESGO
    document.querySelectorAll('.authorize-risk-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const txId = e.currentTarget.dataset.txId;
            if (await window.showConfirmModal('Autorizar', '¿Autorizas esta orden de riesgo? El usuario podrá proceder al pago.')) {
                try {
                    const res = await fetch('../api/?accion=authorizeTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ transactionId: txId })
                    });
                    const r = await res.json();
                    if (r.success) window.location.reload();
                    else window.showInfoModal('Error', r.error, false);
                } catch (e) { window.showInfoModal('Error', 'Error de red', false); }
            }
        });
    });

    // 5.4 SUBIR COMPROBANTE (PAGAR) - ACTUALIZADO CON SELECCIÓN DE BANCO
    const adminUploadModalEl = document.getElementById('adminUploadModal');
    if (adminUploadModalEl) {
        const uploadForm = document.getElementById('admin-upload-form');
        const txIdField = document.getElementById('adminTransactionIdField');
        const txIdLabel = document.getElementById('modal-admin-tx-id');
        const comisionInput = document.getElementById('adminComisionDestino') || document.getElementById('opComisionDestino');
        const cuentaSelect = document.getElementById('cuentaSalidaSelect');

        adminUploadModalEl.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (!btn) return;

            const txId = btn.dataset.txId;
            const monto = parseFloat(btn.dataset.montoDestino);
            const paisDestinoId = parseInt(btn.dataset.paisId, 10);

            if (!paisDestinoId || isNaN(paisDestinoId)) {
                cuentaSelect.innerHTML = '<option value="">⚠️ País destino no válido</option>';
                cuentaSelect.disabled = true;
                return;
            }

            cuentaSelect.disabled = false;

            if (txIdField) txIdField.value = txId;
            if (txIdLabel) txIdLabel.textContent = txId;

            if (comisionInput) {
                if (!isNaN(monto) && monto > 0) comisionInput.value = (monto * 0.003).toFixed(2);
                else comisionInput.value = 0;
            }
            if (cuentaSelect) {
                cuentaSelect.innerHTML = '<option value="">-- Seleccionar Banco --</option>';

                if (window.cuentasDestino && Array.isArray(window.cuentasDestino)) {
                    const cuentasFiltradas = window.cuentasDestino.filter(c =>
                        parseInt(c.PaisID, 10) === paisDestinoId
                    );


                    if (cuentasFiltradas.length > 0) {
                        cuentasFiltradas.forEach(cuenta => {
                            const option = document.createElement('option');
                            option.value = cuenta.CuentaAdminID;
                            option.textContent = `${cuenta.Banco} - ${cuenta.Titular} (Saldo: ${cuenta.SaldoActual} ${cuenta.Moneda || ''})`;
                            cuentaSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.textContent = "No hay cuentas de salida para este país";
                        option.disabled = true;
                        cuentaSelect.appendChild(option);
                    }
                }
            }
        });

        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = uploadForm.querySelector('button[type="submit"]');
                const originalText = btn.textContent;
                btn.disabled = true; btn.textContent = 'Procesando...';

                try {
                    const formData = new FormData(uploadForm);
                    if (cuentaSelect && !cuentaSelect.value) {
                        throw new Error("⚠️ Por favor, selecciona la cuenta bancaria desde donde salió el dinero.");
                    }

                    const res = await fetch('../api/?accion=adminUploadProof', {
                        method: 'POST', body: formData
                    });
                    const result = await res.json();

                    if (result.success) {
                        window.showInfoModal('Éxito', 'Transacción completada y saldo descontado.', true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', result.error, false);
                    }
                } catch (e) {
                    window.showInfoModal('Error', e.message || 'Error de red', false);
                } finally {
                    btn.disabled = false; btn.textContent = originalText;
                }
            });
        }
    }

    // 5.5 EDITAR COMISIÓN (Si existe el modal)
    const editCommissionModalElement = document.getElementById('editCommissionModal');
    if (editCommissionModalElement) {
        const editCommissionModal = new bootstrap.Modal(editCommissionModalElement);
        const form = document.getElementById('edit-commission-form');
        const txIdInput = document.getElementById('commission-tx-id');
        const commissionInput = document.getElementById('new-commission-input');

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.edit-commission-btn');
            if (btn) {
                const d = btn.dataset;
                txIdInput.value = d.txId;
                commissionInput.value = d.currentVal;
                document.getElementById('modal-commission-tx-id-label').textContent = d.txId;
                editCommissionModal.show();
            }
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
                if ((await res.json()).success) window.showInfoModal('Éxito', 'Comisión actualizada.', true, () => window.location.reload());
            } catch (err) { window.showInfoModal('Error', 'Error de conexión.', false); }
            finally { submitBtn.disabled = false; submitBtn.textContent = 'Guardar'; }
        });
    }

    // =========================================================
    // 5.6 CONFIRMAR PAGO (EN VERIFICACIÓN)
    // =========================================================
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.process-btn');

        if (btn) {
            e.preventDefault();

            const txId = btn.dataset.txId;
            const confirmado = await window.showConfirmModal(
                'Confirmar Pago',
                '¿Confirmas la recepción del dinero? La orden pasará a "En Proceso".'
            );

            if (confirmado) {
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                try {
                    const res = await fetch('../api/?accion=processTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ transactionId: txId })
                    });

                    const data = await res.json();

                    if (data.success) {
                        window.location.reload();
                    } else {
                        window.showInfoModal('Error', data.error || 'No se pudo procesar.', false);
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                } catch (err) {
                    console.error(err);
                    window.showInfoModal('Error', 'Error de conexión', false);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            }
        }
    });

    // 5.7 RECHAZAR PAGO (MODAL) - CORREGIDO PARA FUNCIONAR SIEMPRE
    const rejectionModalEl = document.getElementById('rejectionModal');
    if (rejectionModalEl) {
        const rejectionModalInstance = new bootstrap.Modal(rejectionModalEl);
        const rejectTxIdInput = document.getElementById('reject-tx-id');
        const rejectReasonInput = document.getElementById('reject-reason');
        rejectionModalEl.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (btn) {
                rejectTxIdInput.value = btn.dataset.txId;
                rejectReasonInput.value = '';
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.reject-btn');
            if (btn && rejectTxIdInput) {
                rejectTxIdInput.value = btn.dataset.txId;
                rejectReasonInput.value = '';
                rejectionModalInstance.show();
            }
        });

        document.querySelectorAll('.confirm-reject-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const txId = rejectTxIdInput.value;
                const reason = rejectReasonInput.value.trim();
                const type = btn.dataset.type;

                if (!reason) { alert('Por favor, escribe un motivo.'); return; }

                const allBtns = document.querySelectorAll('.confirm-reject-btn');
                allBtns.forEach(b => b.disabled = true);

                try {
                    const response = await fetch('../api/?accion=rejectTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ transactionId: txId, reason: reason, actionType: type })
                    });
                    const result = await response.json();
                    rejectionModalInstance.hide();

                    if (result.success) window.location.reload();
                    else window.showInfoModal('Error', result.error, false);
                } catch (error) { window.showInfoModal('Error', 'Error de conexión.', false); }
                finally { allBtns.forEach(b => b.disabled = false); }
            });
        });
    }

// =========================================================
    // 5.8 VISOR DE COMPROBANTES (CORREGIDO: MÓVIL & SIN ALERTS)
    // =========================================================
    const viewComprobanteModalEl = document.getElementById('viewComprobanteModal');
    if (viewComprobanteModalEl) {
        viewComprobanteModalEl.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            // 1. Referencias a elementos estáticos (del HTML nuevo)
            const imgEl = document.getElementById('comprobante-img-full');
            const pdfEl = document.getElementById('comprobante-pdf-full');
            const placeholder = document.getElementById('comprobante-placeholder');
            const downloadBtn = document.getElementById('download-comprobante-btn');
            const modalTitle = viewComprobanteModalEl.querySelector('.modal-title');
            
            // 2. Datos del botón
            const txId = btn.getAttribute('data-tx-id');
            const urlUser = btn.getAttribute('data-comprobante-url');
            const urlAdmin = btn.getAttribute('data-envio-url');
            const startType = btn.getAttribute('data-start-type') || 'user'; 
            
            // 3. Llenar Textos y Sidebar
            if (modalTitle) modalTitle.textContent = `Comprobante Orden #${txId}`;
            
            const elNombre = document.getElementById('visor-nombre-titular');
            const elRut = document.getElementById('visor-rut-titular');
            if (elNombre) elNombre.textContent = btn.getAttribute('data-nombre-titular') || 'No registrado';
            if (elRut) elRut.textContent = btn.getAttribute('data-rut-titular') || 'No registrado';

            // 4. Función de carga controlada
            const loadFile = (fileUrl) => {
                // Reset visual completo
                imgEl.classList.add('d-none');
                pdfEl.classList.add('d-none');
                placeholder.classList.remove('d-none');
                
                // Limpiar fuentes anteriores para evitar parpadeos
                imgEl.removeAttribute('src'); 
                pdfEl.removeAttribute('src');
                
                // Quitar listeners viejos
                imgEl.onload = imgEl.onerror = null; 

                if (!fileUrl) {
                    placeholder.innerHTML = '<div class="text-white">Sin archivo disponible.</div>';
                    return;
                }

                // Detectar extensión
                let ext = 'jpg';
                if (fileUrl.includes('?')) {
                    const match = fileUrl.match(/file=([^&]+)/);
                    if (match) ext = match[1].split('.').pop().toLowerCase();
                } else {
                    ext = fileUrl.split('.').pop().toLowerCase();
                }

                // Configurar Botón Descarga
                if (downloadBtn) {
                    downloadBtn.href = fileUrl;
                    downloadBtn.classList.remove('disabled');
                }

                // Cargar con pequeño delay para que el modal esté listo
                setTimeout(() => {
                    if (['pdf'].includes(ext)) {
                        // MODO PDF
                        pdfEl.src = fileUrl;
                        pdfEl.classList.remove('d-none');
                        // En PDF forzamos ocultar spinner porque el iframe carga interno
                        placeholder.classList.add('d-none'); 
                    } else {
                        // MODO IMAGEN
                        imgEl.onerror = () => {
                            console.warn("Error cargando imagen:", fileUrl); // Sin Alert
                            placeholder.innerHTML = '<div class="text-danger">No se pudo cargar la imagen.</div>';
                        };
                        imgEl.onload = () => {
                            placeholder.classList.add('d-none');
                            imgEl.classList.remove('d-none');
                        };
                        imgEl.src = fileUrl;
                    }
                }, 100);
            };

            // 5. Gestión de Tabs (Cliente vs Admin)
            const btnUser = document.getElementById('tab-btn-user');
            const btnAdmin = document.getElementById('tab-btn-admin');

            const setTab = (type) => {
                if (type === 'admin') {
                    loadFile(urlAdmin);
                    if (btnAdmin) { btnAdmin.classList.add('btn-primary'); btnAdmin.classList.remove('btn-outline-primary', 'btn-outline-secondary'); }
                    if (btnUser) { btnUser.classList.remove('btn-primary'); btnUser.classList.add('btn-outline-primary'); }
                } else {
                    loadFile(urlUser);
                    if (btnUser) { btnUser.classList.add('btn-primary'); btnUser.classList.remove('btn-outline-primary', 'btn-outline-secondary'); }
                    if (btnAdmin) { btnAdmin.classList.remove('btn-primary'); btnAdmin.classList.add('btn-outline-primary'); }
                }
            };

            // Asignar eventos a los botones (Clonamos para limpiar listeners previos)
            if (btnUser) {
                const newBtn = btnUser.cloneNode(true);
                btnUser.parentNode.replaceChild(newBtn, btnUser);
                newBtn.addEventListener('click', () => setTab('user'));
            }
            if (btnAdmin) {
                const newBtn = btnAdmin.cloneNode(true);
                btnAdmin.parentNode.replaceChild(newBtn, btnAdmin);
                newBtn.addEventListener('click', () => setTab('admin'));
            }

            // Iniciar vista por defecto
            setTab(startType);
        });
    }

    // =========================================================
    // 6. LOGICA DE AUTO-REFRESH (POLLING)
    // =========================================================
    function startAutoRefresh() {
        setInterval(async () => {
            if (document.body.classList.contains('modal-open')) return;

            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('ajax', '1');

            try {
                const response = await fetch(currentUrl);
                if (!response.ok) throw new Error('Error en refresh');

                const newHtmlRows = await response.text();

                const tbody = document.getElementById('transactionsTableBody');
                if (tbody && newHtmlRows.trim().length > 0) {
                    tbody.innerHTML = newHtmlRows;
                }
            } catch (error) {
                console.warn('Error en auto-refresh:', error);
            }
        }, 10000);
    }

    // =========================================================
    // 7. AUTORIZAR ORDEN DE RIESGO (Estado 7 -> 1)
    // =========================================================
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.authorize-risk-btn');
        if (btn) {
            const txId = btn.getAttribute('data-tx-id');

            const confirmado = await window.showConfirmModal(
                'Autorizar Riesgo',
                `¿Autorizar la orden #${txId}?\n\nAl hacerlo, el usuario podrá ver la orden como "Pendiente de Pago" y subir su comprobante.`
            );

            if (confirmado) {
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    const res = await fetch('../api/?accion=authorizeTransaction', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            transactionId: txId
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        window.showInfoModal('Éxito', 'Orden autorizada correctamente.', true, () => {
                            location.reload();
                        });
                    } else {
                        window.showInfoModal('Error', data.error || 'No se pudo autorizar.', false);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-shield-check"></i> Autorizar';
                    }
                } catch (error) {
                    console.error(error);
                    window.showInfoModal('Error', 'Error de conexión.', false);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-check"></i> Autorizar';
                }
            }
        }
    });

    // =========================================================
    // 8. EDICIÓN DE DOCS (ADMIN CROPPER)
    // =========================================================

    let adminCropper = null;
    const adminCropModalEl = document.getElementById('adminCropModal');
    const adminImageToCrop = document.getElementById('admin-image-to-crop');
    let currentEditDocType = null;
    let currentEditingUserId = null;

    if (adminCropModalEl) {
        const adminCropModal = new bootstrap.Modal(adminCropModalEl);
        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.btn-edit-admin-doc');
            if (editBtn) {
                currentEditDocType = editBtn.dataset.docType;
                const hiddenInput = document.getElementById('viewDocsUserId');

                if (hiddenInput && hiddenInput.value) {
                    currentEditingUserId = hiddenInput.value;
                } else if (editBtn.dataset.userId) {
                    currentEditingUserId = editBtn.dataset.userId;
                } else {
                    alert("Error: No se pudo identificar el ID del usuario. Por favor cierra y abre el modal de nuevo.");
                    return;
                }
                let imgSourceId = '';
                if (currentEditDocType === 'perfil') imgSourceId = 'docsProfilePic';
                else if (currentEditDocType === 'frente') imgSourceId = 'docsImgFrente';
                else if (currentEditDocType === 'reverso') imgSourceId = 'docsImgReverso';

                const imgEl = document.getElementById(imgSourceId);
                if (imgEl && imgEl.src && !imgEl.src.includes('SoloLogo') && imgEl.src !== window.location.href) {
                    adminImageToCrop.src = imgEl.src;
                    bootstrap.Modal.getInstance(document.getElementById('userDocsModal')).hide();
                    adminCropModal.show();
                } else {
                    alert('No hay una imagen válida cargada para editar.');
                }
            }
        });

        adminCropModalEl.addEventListener('shown.bs.modal', () => {
            if (adminCropper) adminCropper.destroy();

            adminCropper = new Cropper(adminImageToCrop, {
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                checkOrientation: true,
            });
            window.adminCropper = adminCropper;
        });

        adminCropModalEl.addEventListener('hidden.bs.modal', () => {
            if (adminCropper) {
                adminCropper.destroy();
                adminCropper = null;
            }
            adminImageToCrop.src = '';
            const userDocsModal = new bootstrap.Modal(document.getElementById('userDocsModal'));
            userDocsModal.show();
        });

        const rotateLeft = document.getElementById('admin-rotate-left');
        const rotateRight = document.getElementById('admin-rotate-right');
        if (rotateLeft) rotateLeft.addEventListener('click', () => adminCropper && adminCropper.rotate(-90));
        if (rotateRight) rotateRight.addEventListener('click', () => adminCropper && adminCropper.rotate(90));
        const saveBtn = document.getElementById('admin-crop-confirm');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                if (!adminCropper) return;
                if (!currentEditingUserId) {
                    alert("Error crítico: Se perdió el ID del usuario.");
                    return;
                }

                const originalText = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

                adminCropper.getCroppedCanvas({
                    width: 1280,
                    height: 1280,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                }).toBlob(async (blob) => {

                    const formData = new FormData();
                    formData.append('userId', currentEditingUserId);
                    formData.append('docType', currentEditDocType);
                    formData.append('newDocFile', blob, 'edited_by_admin.jpg');

                    try {
                        const res = await fetch('../api/?accion=adminUpdateUserDoc', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();

                        if (result.success) {
                            const newUrl = `../admin/view_secure_file.php?file=${encodeURIComponent(result.newPath)}&t=${new Date().getTime()}`;

                            if (currentEditDocType === 'perfil') {
                                const img = document.getElementById('docsProfilePic');
                                const link = document.getElementById('btnProfileView');
                                if (img) img.src = newUrl;
                                if (link) link.href = newUrl;
                            } else if (currentEditDocType === 'frente') {
                                const img = document.getElementById('docsImgFrente');
                                const link = document.getElementById('btnFrenteView');
                                if (img) img.src = newUrl;
                                if (link) link.href = newUrl;
                            } else if (currentEditDocType === 'reverso') {
                                const img = document.getElementById('docsImgReverso');
                                const link = document.getElementById('btnReversoView');
                                if (img) img.src = newUrl;
                                if (link) link.href = newUrl;
                            }
                            adminCropModal.hide();

                        } else {
                            alert('Error al guardar: ' + (result.error || 'Desconocido'));
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Error de conexión al guardar.');
                    } finally {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                    }

                }, 'image/jpeg', 0.85);
            });
        }
    }

    startAutoRefresh();

});