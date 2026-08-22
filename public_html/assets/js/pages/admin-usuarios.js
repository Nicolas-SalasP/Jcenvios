/**
 * Verificaciones KYC, gestion de paises y gestion de usuarios
 * (filtros, paginacion, bloqueo, rol y edicion de datos).
 *
 * Extraido de admin.js, que tenia 2089 lineas y lo cargan 8 paginas
 * distintas. Cada modulo abre su propio DOMContentLoaded; lo que se comparte
 * entre ellos viaja por window.* (refreshAdminTable, copyToClipboard,
 * cuentasDestino, escapeHtml de utils/domUtils.js), nunca por scope.
 */
document.addEventListener('DOMContentLoaded', () => {
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

            const getCleanUrl = (path) => path ? `../admin/view_secure_file.php?file=${encodeURIComponent(path.split('?')[0])}&v=${new Date().getTime()}` : '';
            
            const urlFrente = getCleanUrl(btn.dataset.imgFrente);
            const urlReverso = getCleanUrl(btn.dataset.imgReverso);
            const urlPerfil = btn.dataset.fotoPerfil ? getCleanUrl(btn.dataset.fotoPerfil) : defaultProfilePic;

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
            // Sin este chequeo, un 500 con el HTML de error de PHP se inyectaba
            // crudo en la tabla como si fueran resultados.
            if (!response.ok) {
                throw new Error(`El servidor respondió con un error (${response.status}).`);
            }
            const html = await response.text();
            tableContent.innerHTML = html;
            window.history.pushState({}, '', url);
        } catch (error) {
            console.error('Error AJAX:', error);
            // Antes solo se logueaba: la tabla volvía a la normalidad con los
            // datos viejos y parecía que el filtro simplemente "no hizo nada".
            window.showInfoModal('Error', window.formatNetworkError(error, 'No se pudieron cargar los datos.'), false);
        } finally {
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
        // Solo interceptar para AJAX en páginas que tienen el contenedor #table-content
        // (ej. usuarios.php). En páginas sin ese contenedor (ej. admin/index.php), dejar
        // que el enlace navegue normalmente: así la paginación funciona y conserva los
        // filtros que ya vienen en la query string (fix: la paginación dejaba de funcionar
        // al filtrar porque se interceptaba el clic pero loadTableData abortaba sin #table-content).
        const pageLink = target.closest('.page-link');
        if (pageLink && tableContent && !pageLink.parentElement.classList.contains('disabled')) {
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
                    const cleanPath = path.split('?')[0]; 
                    const fullPath = `../admin/view_secure_file.php?file=${encodeURIComponent(cleanPath)}&t=${new Date().getTime()}`;
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
});
