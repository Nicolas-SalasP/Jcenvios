/**
 * Recorte de documentos del usuario (Cropper), gestion de beneficiarios,
 * export de transacciones y tasas especiales por cliente.
 *
 * Extraido de admin.js, que tenia 2089 lineas y lo cargan 8 paginas
 * distintas. Cada modulo abre su propio DOMContentLoaded; lo que se comparte
 * entre ellos viaja por window.* (refreshAdminTable, copyToClipboard,
 * cuentasDestino, escapeHtml de utils/domUtils.js), nunca por scope.
 */
document.addEventListener('DOMContentLoaded', () => {
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
                    window.showInfoModal('Error', 'No se pudo identificar el ID del usuario. Por favor cierra y abre el modal de nuevo.', false);
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
                    window.showInfoModal('Error', 'No hay una imagen válida cargada para editar.', false);
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
            saveBtn.replaceWith(saveBtn.cloneNode(true));
            const newSaveBtn = document.getElementById('admin-crop-confirm');

            newSaveBtn.addEventListener('click', async () => {
                if (!adminCropper) return;
                if (!currentEditingUserId) {
                    window.showInfoModal('Error Crítico', 'Se perdió el ID del usuario.', false);
                    return;
                }
                const motivo = await window.showPromptModal(
                    'Auditoría de Cambio', 
                    'Estás reemplazando un documento en la ficha del usuario. Si quieres, ingresa el motivo para el historial.',
                    'Ej: Actualización de documento vencido (opcional)',
                    false  // Motivo NO obligatorio
                );
                
                // Solo abortamos si el usuario CANCELA el modal (motivo === null).
                // Antes: cualquier valor falsy (incl. string vacío) abortaba, lo que hacía obligatorio el texto.
                if (motivo === null) return;

                const originalText = newSaveBtn.innerHTML;
                newSaveBtn.disabled = true;
                newSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

                adminCropper.getCroppedCanvas({
                    width: 1280,
                    height: 1280,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                }).toBlob(async (blob) => {

                    const formData = new FormData();
                    formData.append('userId', currentEditingUserId);
                    formData.append('docType', currentEditDocType);
                    formData.append('motivo', motivo); // Se va al backend para historial
                    formData.append('newDocFile', blob, 'edited_by_admin.jpg');

                    try {
                        const res = await fetch('../api/?accion=adminUpdateUserDoc', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await res.json();

                        if (result.success) {
                            const cleanPath = result.newPath.split('?')[0];
                            const newUrl = `../admin/view_secure_file.php?file=${encodeURIComponent(cleanPath)}&t=${new Date().getTime()}`;
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

                            const tableBtn = document.querySelector(`.view-user-docs-btn[data-user-id="${currentEditingUserId}"]`);
                            if (tableBtn) {
                                if (currentEditDocType === 'perfil') tableBtn.dataset.fotoPerfil = cleanPath;
                                else if (currentEditDocType === 'frente') tableBtn.dataset.imgFrente = cleanPath;
                                else if (currentEditDocType === 'reverso') tableBtn.dataset.imgReverso = cleanPath;
                            }
                            
                            adminCropModal.hide();
                            setTimeout(() => {
                                window.showInfoModal('Éxito', 'Documento actualizado y registrado en la bitácora de auditoría.', true);
                                const modalesAbiertos = document.querySelectorAll('.modal.show').length;
                                const fondosOscuros = document.querySelectorAll('.modal-backdrop');
                                if (fondosOscuros.length > modalesAbiertos) {
                                    fondosOscuros.forEach((fondo, index) => {
                                        if (index >= modalesAbiertos) fondo.remove();
                                    });
                                }
                            }, 400);

                        } else {
                            window.showInfoModal('Error al Guardar', result.error || 'Ocurrió un error desconocido.', false);
                        }
                    } catch (e) {
                        console.error(e);
                        window.showInfoModal('Error de Conexión', 'No se pudo contactar con el servidor. Revisa tu red.', false);
                    } finally {
                        newSaveBtn.disabled = false;
                        newSaveBtn.innerHTML = originalText;
                    }

                }, 'image/jpeg', 0.85);
            });
        }
    }

    const adminFileInput = document.getElementById('adminHiddenFileInput');
    document.addEventListener('click', (e) => {
        const uploadBtn = e.target.closest('.btn-upload-admin-doc');
        if (uploadBtn) {
            const hiddenInput = document.getElementById('viewDocsUserId');
            if (hiddenInput && hiddenInput.value) {
                currentEditingUserId = hiddenInput.value;
            } else {
                window.showInfoModal('Error', 'No se pudo identificar el ID del usuario.', false);
                return;
            }
            
            currentEditDocType = uploadBtn.dataset.docType;
            if(adminFileInput) adminFileInput.click();
        }
    });

    if (adminFileInput) {
        adminFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            e.target.value = '';

            const reader = new FileReader();
            reader.onload = function(event) {
                adminImageToCrop.src = event.target.result;
                const docsModalEl = document.getElementById('userDocsModal');
                if (docsModalEl) {
                    const docsModal = bootstrap.Modal.getInstance(docsModalEl);
                    if (docsModal) docsModal.hide();
                }
                const cropModalEl = document.getElementById('adminCropModal');
                if (cropModalEl) {
                    bootstrap.Modal.getOrCreateInstance(cropModalEl).show();
                }
            };
            reader.readAsDataURL(file);
        });
    }
    // =====================================================

    // =================================================
    // 9. GESTIÓN DE BENEFICIARIOS (VISUAL MEJORADA)
    // =================================================

    document.addEventListener('click', async (e) => {
        const btnVer = e.target.closest('.btn-ver-beneficiarios');
        if (btnVer) {
            const userId = btnVer.dataset.userid;
            const row = btnVer.closest('tr');
            const userNameElement = row ? row.querySelector('td:nth-child(2) strong') : null;
            const userName = userNameElement ? userNameElement.innerText : 'Usuario #' + userId;

            const modalElement = document.getElementById('modalBeneficiariosUser');
            if (!modalElement) return;

            const modal = new bootstrap.Modal(modalElement);
            const tbody = document.querySelector('#tablaBeneficiariosUser tbody');
            const loader = document.getElementById('listaBeneficiariosLoader');
            const modalTitle = modalElement.querySelector('.modal-title');
            modalTitle.innerHTML = `Beneficiarios de: <span class="text-primary fw-bold">${userName}</span>`;

            modal.show();
            tbody.innerHTML = '';
            if (loader) loader.classList.remove('d-none');

            try {
                const response = await fetch(`../api/?accion=adminGetUserBeneficiaries&userId=${userId}`);
                const data = await response.json();

                if (data.success) {
                    if (!data.beneficiarios || data.beneficiarios.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4"><i class="bi bi-folder2-open display-6 d-block mb-2"></i>Sin beneficiarios registrados.</td></tr>';
                    } else {
                        let htmlRows = '';
                        data.beneficiarios.forEach(ben => {
                            let detallesCuenta = '';

                            // Teléfono (Pago Móvil, Yape, Plin)
                            if (ben.NumeroTelefono) {
                                detallesCuenta += `<div class="mb-1 text-nowrap"><i class="bi bi-phone-vibrate text-success me-1"></i> ${window.escapeHtml(ben.NumeroTelefono)}</div>`;
                            }
                            // Cuenta Bancaria tradicional
                            if (ben.NumeroCuenta) {
                                detallesCuenta += `<div class="mb-1 text-nowrap"><i class="bi bi-credit-card-2-front text-secondary me-1"></i> <span class="fw-bold text-dark">${window.escapeHtml(ben.NumeroCuenta)}</span></div>`;
                            }
                            // Documento (Cédula/DNI)
                            if (ben.TitularNumeroDocumento) {
                                detallesCuenta += `<div class="text-muted small"><i class="bi bi-person-vcard me-1"></i> ${window.escapeHtml(ben.TitularNumeroDocumento)}</div>`;
                            }
                            // CCI
                            if (ben.CCI) {
                                detallesCuenta += `<div class="text-muted small">CCI: ${window.escapeHtml(ben.CCI)}</div>`;
                            }

                            const bancoInfo = `<div class="fw-bold text-dark">${window.escapeHtml(ben.NombreBanco)}</div>
                                             <small class="text-primary"><i class="bi bi-globe-americas"></i> ${window.escapeHtml(ben.NombrePais)} <span class="text-muted">(${window.escapeHtml(ben.CodigoMoneda || '')})</span></small>
                                             ${ben.Alias ? `<br><span class="badge bg-light text-dark border mt-1"><i class="bi bi-tag"></i> ${window.escapeHtml(ben.Alias)}</span>` : ''}`;

                            const titularInfo = `<div class="fw-bold text-dark">${window.escapeHtml(ben.BeneficiarioNombre)}</div>
                                               <small class="text-muted">${window.escapeHtml(ben.TipoBeneficiarioNombre || 'Destinatario')}</small>`;

                            let actionBtns = '';
                            if (ben.PermitirEdicion == 1) {
                                const jsonSafe = JSON.stringify(ben).replace(/"/g, '&quot;');
                                actionBtns = `<button class="btn btn-sm btn-warning btn-admin-edit-ben mb-1 w-100 shadow-sm" data-json="${jsonSafe}"><i class="bi bi-pencil-square"></i> Editar</button>
                                              <div class="text-success small text-center fw-bold"><i class="bi bi-unlock-fill"></i> Habilitado</div>`;
                            } else {
                                actionBtns = `<button class="btn btn-sm btn-outline-primary btn-request-access mb-1 w-100" data-id="${ben.CuentaID}" data-user="${ben.UserID}">
                                                <i class="bi bi-bell"></i> Solicitar
                                              </button>
                                              <div class="text-muted small text-center"><i class="bi bi-lock-fill"></i> Bloqueado</div>`;
                            }

                            htmlRows += `
                                <tr class="align-middle">
                                    <td>${bancoInfo}</td>
                                    <td>${titularInfo}</td>
                                    <td>${detallesCuenta}</td>
                                    <td style="width: 150px;">${actionBtns}</td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = htmlRows;
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center p-3">Error: ${data.error}</td></tr>`;
                }
            } catch (error) {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center p-3">Error de conexión con la API.</td></tr>';
            } finally {
                if (loader) loader.classList.add('d-none');
            }
        }

        const btnRequest = e.target.closest('.btn-request-access');
        if (btnRequest) {
            const modalEl = document.getElementById('modalSolicitarEdicion');
            if (modalEl) {
                document.getElementById('reqBenId').value = btnRequest.dataset.id;
                document.getElementById('reqBenUserId').value = btnRequest.dataset.user;
                document.getElementById('formSolicitarEdicion').reset();
                new bootstrap.Modal(modalEl).show();
            }
        }

        const btnEditBen = e.target.closest('.btn-admin-edit-ben');
        if (btnEditBen) {
            let data;
            try {
                const rawJson = btnEditBen.dataset.json.replace(/&quot;/g, '"');
                data = JSON.parse(rawJson);
            } catch (err) { return; }

            const modalEl = document.getElementById('modalAdminEditarBeneficiario');
            if (modalEl) {
                document.getElementById('editBenId').value = data.CuentaID;
                document.getElementById('editBenUserId').value = data.UserID;
                document.getElementById('editBenAlias').value = data.Alias || 'Sin Alias';
                document.getElementById('editBenNombre').value = data.BeneficiarioNombre || '';
                document.getElementById('editBenDoc').value = data.TitularNumeroDocumento || '';
                document.getElementById('editBenBanco').value = data.NombreBanco || '';

                document.getElementById('editBenCuenta').value = data.NumeroCuenta || '';
                document.getElementById('editBenTelefono').value = data.NumeroTelefono || '';
                document.getElementById('editBenCCI').value = data.CCI || '';
                document.getElementById('divEditBenCCI').style.display = (parseInt(data.PaisID) === 4) ? 'block' : 'none';

                new bootstrap.Modal(modalEl).show();
            }
        }
    });

    // =================================================
    // 9.1 ENVIAR LA SOLICITUD DE EDICIÓN AL CLIENTE
    // =================================================
    const formReq = document.getElementById('formSolicitarEdicion');
    if (formReq) {
        formReq.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formReq.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

            const formData = new FormData(formReq);
            const data = {
                cuentaId: formData.get('cuentaId'),
                userId: formData.get('userId'),
                motivo: formData.get('motivo'),
                campos: formData.getAll('campos[]')
            };

            try {
                const res = await fetch('../api/?accion=requestBeneficiaryEdit', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalSolicitarEdicion')).hide();
                    window.showInfoModal("Solicitud Enviada", "Se ha notificado al usuario correctamente. La edición estará bloqueada hasta que el cliente la apruebe.", true);
                    const origBtn = document.querySelector(`.btn-request-access[data-id="${data.cuentaId}"]`);
                    if (origBtn) {
                        origBtn.innerHTML = '<i class="bi bi-clock-history"></i> Petición en espera...';
                        origBtn.classList.replace('btn-outline-primary', 'btn-secondary');
                    }
                } else {
                    window.showInfoModal("Error", result.error || "No se pudo enviar la solicitud.", false);
                }
            } catch (err) {
                window.showInfoModal("Error de Conexión", "No se pudo contactar con el servidor.", false);
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        });
    }

    // =================================================
    // 9.2 EJECUTAR LA EDICIÓN (SOLO SI FUE APROBADA)
    // =================================================

    const formEditBen = document.getElementById('formAdminEditarBeneficiario');
    if (formEditBen) {
        formEditBen.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = formEditBen.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando y Auditando...';

            const formData = new FormData(formEditBen);
            const payload = {
                cuentaId: formData.get('cuentaId'),
                userId: formData.get('userId'),
                nuevosDatos: {
                    nombre: formData.get('nombre'),
                    documento: formData.get('documento'),
                    banco: formData.get('banco'),
                    cuenta: formData.get('cuenta'),
                    telefono: formData.get('telefono'),
                    cci: formData.get('cci')
                }
            };

            try {
                const res = await fetch('../api/?accion=executeBeneficiaryEdit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();

                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalAdminEditarBeneficiario')).hide();
                    window.showInfoModal('Éxito de Auditoría', 'Beneficiario actualizado y guardado en la bitácora inmutable.', true);
                    const userId = payload.userId;
                    const btnVer = document.querySelector(`.btn-ver-beneficiarios[data-userid="${userId}"]`);
                    if (btnVer) btnVer.click();

                } else {
                    window.showInfoModal('Bloqueo de Seguridad', result.error, false);
                }
            } catch (error) {
                window.showInfoModal('Error Crítico', 'Error de conexión al guardar.', false);
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        });
    }

    // startAutoRefresh() se llama ahora en admin-transacciones.js, junto a su
    // definición (antes quedaba como bootstrap suelto en medio de esta sección).

    // --- admin/index.php: filtro de países en export de transacciones ---
    const selOrigin = document.getElementById('exportOrigin');
    const selDest = document.getElementById('exportDest');
    if (selOrigin && selDest) {
        const updateExportOptions = () => {
            const valOrigin = selOrigin.value;
            const valDest = selDest.value;
            Array.from(selDest.options).forEach(opt => {
                if (opt.value !== "" && opt.value === valOrigin) {
                    opt.disabled = true;
                    if (opt.selected) selDest.value = "";
                } else {
                    opt.disabled = false;
                }
            });
            Array.from(selOrigin.options).forEach(opt => {
                if (opt.value !== "" && opt.value === valDest) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
        };
        selOrigin.addEventListener('change', updateExportOptions);
        selDest.addEventListener('change', updateExportOptions);
    }

    // --- admin/index.php: modal de motivo de pausa + visor de comprobante admin ---
    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-pause-reason-btn');
        if (btn) {
            e.preventDefault();
            const reason = btn.getAttribute('data-reason');
            const modalBody = document.getElementById('pause-reason-text');
            if (modalBody) modalBody.textContent = reason;
            const modalEl = document.getElementById('viewPauseReasonModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });

    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-cliente-info');
        if (!btn) return;
        const modalEl = document.getElementById('clienteInfoModal');
        if (!modalEl) return;
        document.getElementById('cliente-info-tx-id').textContent = '#' + (btn.dataset.txId || '');
        document.getElementById('cliente-info-nombre').textContent = btn.dataset.nombre || '';
        document.getElementById('cliente-info-telefono').textContent = btn.dataset.telefono || 'No registrado';
        document.getElementById('cliente-info-doc').textContent = btn.dataset.doc || 'No registrado';
        new bootstrap.Modal(modalEl).show();
    });

    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-comprobante-btn-admin');
        // Guard: este visor (con nombre/rut de titular) es específico de admin/index.php.
        // operador/index.php reutiliza la misma clase para un visor más simple con su
        // propio handler (operador-index.js) — sin este guard, este listener compartido
        // en admin.js tira TypeError ahí porque #visor-nombre-titular no existe.
        if (btn && document.getElementById('visor-nombre-titular')) {
            e.preventDefault();

            document.getElementById('visor-nombre-titular').textContent = btn.dataset.nombreTitular || 'No registrado';
            document.getElementById('visor-rut-titular').textContent = btn.dataset.rutTitular || 'No registrado';

            const urlUser = btn.dataset.comprobanteUrl;
            const urlAdmin = btn.dataset.envioUrl;
            const startType = btn.dataset.startType || 'user';
            const urlToLoad = (startType === 'admin' && urlAdmin) ? urlAdmin : urlUser;
            const imgEl = document.getElementById('comprobante-img-full');
            const pdfEl = document.getElementById('comprobante-pdf-full');
            const placeholder = document.getElementById('comprobante-placeholder');
            const downloadBtn = document.getElementById('download-comprobante-btn');

            imgEl.classList.add('d-none');
            pdfEl.classList.add('d-none');
            placeholder.classList.remove('d-none');

            let extension = '';
            if (urlToLoad.includes('?')) {
                const urlParams = new URLSearchParams(urlToLoad.split('?')[1]);
                const fileParam = urlParams.get('file');
                if (fileParam) extension = fileParam.split('.').pop().toLowerCase();
            } else {
                extension = urlToLoad.split('.').pop().toLowerCase();
            }

            setTimeout(() => {
                placeholder.classList.add('d-none');
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    imgEl.src = urlToLoad;
                    imgEl.classList.remove('d-none');
                } else {
                    pdfEl.src = urlToLoad;
                    pdfEl.classList.remove('d-none');
                }
                if (downloadBtn) downloadBtn.href = urlToLoad;
            }, 500);
        }
    });

    // --- Tasas Especiales por Cliente (admin/usuarios.php) ---
    const tasaEspecialModalEl = document.getElementById('tasaEspecialModal');
    if (tasaEspecialModalEl) {
        const tasaEspecialModal = new bootstrap.Modal(tasaEspecialModalEl);
        const tasaEspecialForm = document.getElementById('tasa-especial-form');
        const tasaEspecialUserId = document.getElementById('tasa-especial-user-id');
        const tasaEspecialUserName = document.getElementById('tasa-especial-user-name');
        const selOrigen = document.getElementById('tasa-especial-origen');
        const selDestino = document.getElementById('tasa-especial-destino');
        const historialEl = document.getElementById('tasa-especial-historial');

        const escapeHtmlTe = (s) => {
            const div = document.createElement('div');
            div.textContent = s == null ? '' : String(s);
            return div.innerHTML;
        };

        const cargarPaisesSelect = async (selectEl, rol) => {
            try {
                const res = await fetch('../api/?accion=getPaises&rol=' + rol);
                const paises = await res.json();
                selectEl.innerHTML = paises.map(p => `<option value="${p.PaisID}">${escapeHtmlTe(p.NombrePais)}</option>`).join('');
            } catch (e) { console.error('Error cargando países', e); }
        };

        const cargarHistorial = async (userId) => {
            historialEl.innerHTML = '<span class="text-muted">Cargando...</span>';
            try {
                const res = await fetch('../api/?accion=adminGetTasasEspecialesByUser&userId=' + userId);
                const data = await res.json();
                if (!data.success || !data.tasas.length) {
                    historialEl.innerHTML = '<span class="text-muted">Sin tasas especiales previas.</span>';
                    return;
                }
                historialEl.innerHTML = data.tasas.map(t => `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <span>
                            ${escapeHtmlTe(t.PaisOrigenNombre)} → ${escapeHtmlTe(t.PaisDestinoNombre)}:
                            <strong>${escapeHtmlTe(t.ValorTasa)}</strong>
                            ${t.Activa == 1 ? '<span class="badge bg-success ms-1">Activa</span>' : '<span class="badge bg-secondary ms-1">Usada/Inactiva</span>'}
                        </span>
                        ${t.Activa == 1 ? `<button type="button" class="btn btn-sm btn-outline-danger btn-desactivar-tasa-especial" data-id="${t.TasaEspecialID}">Desactivar</button>` : ''}
                    </div>
                `).join('');
            } catch (e) {
                historialEl.innerHTML = '<span class="text-danger">Error al cargar historial.</span>';
            }
        };

        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-tasa-especial');
            if (!btn) return;
            tasaEspecialUserId.value = btn.dataset.userId;
            tasaEspecialUserName.textContent = btn.dataset.userName || '';
            tasaEspecialForm.reset();
            tasaEspecialUserId.value = btn.dataset.userId;
            cargarPaisesSelect(selOrigen, 'Origen');
            cargarPaisesSelect(selDestino, 'Destino');
            cargarHistorial(btn.dataset.userId);
            tasaEspecialModal.show();
        });

        historialEl.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-desactivar-tasa-especial');
            if (!btn) return;
            btn.disabled = true;
            try {
                const res = await fetch('../api/?accion=adminDeactivateTasaEspecial', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: btn.dataset.id })
                });
                const data = await res.json();
                if (data.success) {
                    cargarHistorial(tasaEspecialUserId.value);
                } else {
                    window.showInfoModal('Error', 'No se pudo desactivar.', false);
                    btn.disabled = false;
                }
            } catch (e2) {
                window.showInfoModal('Error', 'Error de conexión.', false);
                btn.disabled = false;
            }
        });

        tasaEspecialForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = tasaEspecialForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            try {
                const body = {
                    userId: tasaEspecialUserId.value,
                    paisOrigenId: selOrigen.value,
                    paisDestinoId: selDestino.value,
                    valor: document.getElementById('tasa-especial-valor').value
                };
                const res = await fetch('../api/?accion=adminAssignTasaEspecial', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('tasa-especial-valor').value = '';
                    cargarHistorial(tasaEspecialUserId.value);
                    window.showInfoModal('Listo', 'Tasa especial asignada correctamente.', true);
                } else {
                    window.showInfoModal('Error', data.error || 'No se pudo asignar la tasa.', false);
                }
            } catch (e3) {
                window.showInfoModal('Error', 'Error de conexión.', false);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

});