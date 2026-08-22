/**
 * Ciclo de vida de una orden desde el panel: pausar/reanudar, autorizar
 * riesgo, subir comprobante de pago, comision, confirmar/rechazar, visor
 * de comprobantes, polling de la tabla y autorizacion de cambio de monto.
 *
 * Extraido de admin.js, que tenia 2089 lineas y lo cargan 8 paginas
 * distintas. Cada modulo abre su propio DOMContentLoaded; lo que se comparte
 * entre ellos viaja por window.* (refreshAdminTable, copyToClipboard,
 * cuentasDestino, escapeHtml de utils/domUtils.js), nunca por scope.
 */
document.addEventListener('DOMContentLoaded', () => {
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
    // El handler vive en la sección 7 (delegado en document). Antes había
    // además un querySelectorAll().forEach acá: los dos se disparaban con el
    // mismo click y salían DOS modales de confirmación encimados. El directo
    // encima moría en el primer refresh del tbody (cada 5s), así que el bug
    // parecía intermitente.

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

            // Guard: las páginas de operador no tienen #cuentaSalidaSelect
            // (más abajo, en L172, sí se validaba) — sin esto tiraba TypeError.
            if (!paisDestinoId || isNaN(paisDestinoId)) {
                if (cuentaSelect) {
                    cuentaSelect.innerHTML = '<option value="">⚠️ País destino no válido</option>';
                    cuentaSelect.disabled = true;
                }
                return;
            }

            if (cuentaSelect) cuentaSelect.disabled = false;

            if (txIdField) txIdField.value = txId;
            if (txIdLabel) txIdLabel.textContent = txId;

            // Verificar si aún puede reemplazar comprobante (ventana de 1 hora)
            const submitBtn = uploadForm?.querySelector('button[type="submit"]');
            const replaceWarning = document.getElementById('replace-proof-warning');
            if (submitBtn) {
                // Estado limpio ANTES del fetch: si esta llamada fallaba, el
                // botón se quedaba con el disabled/title de la orden anterior
                // (el hidden.bs.modal nunca los reseteaba), y el admin veía el
                // botón bloqueado sin motivo en una orden nueva.
                submitBtn.disabled = false;
                submitBtn.title = '';
                if (replaceWarning) replaceWarning.classList.add('d-none');

                fetch(`../api/?accion=canReplaceAdminProof&txId=${txId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && !data.can_replace) {
                            submitBtn.disabled = true;
                            submitBtn.title = 'El plazo de 1 hora para reemplazar el comprobante ha vencido.';
                            if (replaceWarning) {
                                replaceWarning.textContent = 'El comprobante ya no puede reemplazarse (venció el plazo de 1 hora).';
                                replaceWarning.classList.remove('d-none');
                            }
                        }
                    })
                    .catch(() => {
                        // Fail-open a propósito: el backend revalida el plazo de
                        // 1h y devuelve 403, así que dejar el botón habilitado no
                        // es un agujero. Se avisa para que no sorprenda el rechazo.
                        if (replaceWarning) {
                            replaceWarning.textContent = 'No se pudo verificar el plazo de reemplazo; el servidor lo validará al enviar.';
                            replaceWarning.classList.remove('d-none');
                        }
                    });
            }

            if (comisionInput) {
                // Comisión 0.3% aplica SOLO para Venezuela. Resto en 0.
                // El ID de país se lee del data-pais-id del botón. Confirmar el ID en `paises`.
                // Por seguridad chequeamos también el código de moneda (VES) si está disponible.
                const ID_VENEZUELA = 3;
                const monedaDestino = (btn.dataset.monedaDestino || '').toUpperCase();
                const esVenezuela = (paisDestinoId === ID_VENEZUELA) || (monedaDestino === 'VES') || (monedaDestino === 'BS') || (monedaDestino === 'BSS');
                if (esVenezuela && !isNaN(monto) && monto > 0) {
                    comisionInput.value = (monto * 0.003).toFixed(2);
                } else {
                    comisionInput.value = 0;
                }
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
                    if (!res.ok) {
                        throw new Error(`Error del servidor (${res.status}). Es posible que el archivo sea demasiado pesado.`);
                    }
                    const result = await res.json();

                    if (result.success) {
                        window.showInfoModal('Éxito', 'Transacción completada y saldo descontado.', true, () => window.location.reload());
                    } else {
                        window.showInfoModal('Error', result.error, false);
                    }
                } catch (e) {
                    let msg = e.message || 'Error de red desconocido.';
                    if (msg.includes('Failed to fetch') || msg.includes('NetworkError')) {
                        msg = 'Error de conexión. Verifica que el archivo no sea muy pesado (máximo recomendado 5MB) o tu conexión a internet.';
                    }
                    window.showInfoModal('Error', msg, false);
                } finally {
                    btn.disabled = false; btn.textContent = originalText;
                }
            });
        }
    }
    // =========================================================
    // UX: PREVISUALIZACIÓN DE DOCUMENTOS ANTES DE SUBIR
    // =========================================================
    const fileInput = document.getElementById('adminReceiptFileInput');
    const previewContainer = document.getElementById('upload-preview-container');
    const previewImg = document.getElementById('upload-preview-img');
    const previewPdf = document.getElementById('upload-preview-pdf');
    const clearPreviewBtn = document.getElementById('clear-upload-preview-btn');
    const previewInfo = document.getElementById('upload-preview-info');

    let currentObjectURL = null;

    if (fileInput && previewContainer) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (currentObjectURL) URL.revokeObjectURL(currentObjectURL);

            if (file) {
                const fileType = file.type;
                currentObjectURL = URL.createObjectURL(file);
                previewImg.classList.add('d-none');
                previewPdf.classList.add('d-none');
                previewContainer.classList.remove('d-none');
                previewInfo.innerHTML = `<i class="bi bi-file-earmark-check text-success"></i> ${window.escapeHtml(file.name)} <span class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</span>`;

                if (fileType.startsWith('image/')) {
                    previewImg.src = currentObjectURL;
                    previewImg.classList.remove('d-none');
                } else if (fileType === 'application/pdf') {
                    previewPdf.src = currentObjectURL;
                    previewPdf.classList.remove('d-none');
                } else {
                    previewInfo.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Formato no soportado para vista previa. Guarde como JPG, PNG o PDF.</span>`;
                    fileInput.value = '';
                }
            } else {
                previewContainer.classList.add('d-none');
            }
        });

        clearPreviewBtn.addEventListener('click', () => {
            fileInput.value = '';
            previewContainer.classList.add('d-none');
            if (currentObjectURL) {
                URL.revokeObjectURL(currentObjectURL);
                currentObjectURL = null;
            }
            previewImg.src = '';
            previewPdf.src = '';
        });

        const adminUploadModalEl = document.getElementById('adminUploadModal');
        if (adminUploadModalEl) {
            adminUploadModalEl.addEventListener('hidden.bs.modal', () => {
                clearPreviewBtn.click();
                // Defensa extra: que el estado del botón/aviso no sobreviva al
                // cierre y contamine la próxima orden que se abra.
                const submitBtn = adminUploadModalEl.querySelector('form button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.title = '';
                }
                const replaceWarning = document.getElementById('replace-proof-warning');
                if (replaceWarning) replaceWarning.classList.add('d-none');
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
                if (!res.ok) throw new Error(`El servidor respondió con un error (${res.status}).`);
                const result = await res.json();
                // Antes no había `else`: si el backend rechazaba, no salía
                // ningún mensaje y el admin creía que había guardado.
                if (result.success) {
                    window.showInfoModal('Éxito', 'Comisión actualizada.', true, () => window.location.reload());
                } else {
                    window.showInfoModal('Error', result.error || 'No se pudo actualizar la comisión.', false);
                }
            } catch (err) {
                window.showInfoModal('Error', window.formatNetworkError(err, 'No se pudo actualizar la comisión.'), false);
            }
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

    // 5.7 RECHAZAR PAGO (MODAL)
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

                if (!reason) { window.showInfoModal('Falta el motivo', 'Por favor, escribe un motivo.', false); return; }

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
    // 5.8 VISOR DE COMPROBANTES
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
            const loadFile = async (fileUrl) => {
                imgEl.classList.add('d-none');
                pdfEl.classList.add('d-none');
                placeholder.classList.remove('d-none');
                placeholder.innerHTML = '<div class="text-white mt-3"><span class="spinner-border spinner-border-sm mb-2"></span><br>Cargando documento...</div>';
                imgEl.removeAttribute('src');
                pdfEl.removeAttribute('src');

                const filenameEl = document.getElementById('visor-filename');
                if (filenameEl) {
                    let name = fileUrl.split('file=').pop();
                    if (name.includes('&')) name = name.split('&')[0];
                    filenameEl.textContent = decodeURIComponent(name).split('/').pop() || 'documento_adjunto';
                }

                imgEl.onload = imgEl.onerror = null;

                if (!fileUrl) {
                    placeholder.innerHTML = '<div class="text-white mt-3"><i class="bi bi-file-earmark-x display-4 text-danger"></i><br>Sin archivo disponible.</div>';
                    return;
                }

                let ext = 'jpg';
                if (fileUrl.includes('?')) {
                    const match = fileUrl.match(/file=([^&]+)/);
                    if (match) ext = match[1].split('.').pop().toLowerCase();
                } else {
                    ext = fileUrl.split('.').pop().toLowerCase();
                }

                if (downloadBtn) {
                    downloadBtn.href = fileUrl;
                    downloadBtn.classList.remove('disabled');
                }

                if (['pdf'].includes(ext)) {
                    pdfEl.src = fileUrl;
                    pdfEl.classList.remove('d-none');
                    placeholder.classList.add('d-none');
                } else {
                    imgEl.onerror = () => {
                        placeholder.innerHTML = '<div class="text-danger mt-3 bg-white p-2 rounded shadow"><i class="bi bi-exclamation-triangle"></i> Imagen corrupta o no encontrada.</div>';
                    };
                    imgEl.onload = () => {
                        placeholder.classList.add('d-none');
                        imgEl.classList.remove('d-none');
                    };
                    imgEl.src = fileUrl;
                    setTimeout(() => {
                        placeholder.classList.add('d-none');
                        imgEl.classList.remove('d-none');
                    }, 3000);
                }
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
    // Antes: se saltaba ENTERO el refresh mientras hubiera cualquier modal
    // abierto (document.body tiene 'modal-open' con CUALQUIER modal de
    // Bootstrap, no solo uno relacionado a la tabla). Como el admin suele
    // dejar abierto "Ver comprobante"/"Cliente info"/etc. varios segundos
    // mientras revisa una orden, la confirmación de recepción del cliente
    // quedaba sin actualizar hasta que cerraba TODO modal y esperaba el
    // siguiente ciclo — de ahí el reporte de "no sale hasta actualizar".
    // Los modales viven fuera de #transactionsTableBody (son <div> aparte
    // al final de la página), así que reemplazar el tbody no les afecta:
    // ya no hace falta saltarse el refresh por eso.
    async function refreshAdminTableRows() {
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
    }

    function startAutoRefresh() {
        setInterval(refreshAdminTableRows, 5000);
        // Refresco inmediato apenas se cierra cualquier modal, para no
        // esperar hasta el próximo ciclo si el admin justo confirmó algo
        // (ej. subir comprobante) mientras el modal estaba abierto.
        document.addEventListener('hidden.bs.modal', refreshAdminTableRows);
    }

    // =========================================================
    // 6.5 AUTORIZACIÓN DE CAMBIO DE MONTO (ZERO-TRUST)
    // =========================================================
    document.addEventListener('click', async (e) => {
        const btnToggleMonto = e.target.closest('.toggle-monto-btn');
        if (btnToggleMonto) {
            e.preventDefault();
            const txId = btnToggleMonto.dataset.txId;
            const newStatus = btnToggleMonto.dataset.status;

            const isPermitting = newStatus == 1 || newStatus == '1';
            const confirmado = await window.showConfirmModal(
                'Autorizar Cambio de Monto',
                `¿Estás seguro de ${isPermitting ? 'PERMITIR' : 'REVOCAR'} que el cliente modifique el monto de la orden #${txId}?`
            );

            if (!confirmado) return;

            const originalHtml = btnToggleMonto.innerHTML;
            btnToggleMonto.disabled = true;
            btnToggleMonto.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch('../api/?accion=toggleMontoEditPermission', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ txId: txId, estado: newStatus })
                });
                const data = await res.json();

                if (data.success) {
                    window.showInfoModal('Éxito', data.message, true, () => {
                        if (typeof window.refreshAdminTable === 'function') {
                            window.refreshAdminTable();
                        } else {
                            window.location.reload();
                        }
                    });
                } else {
                    window.showInfoModal('Error', data.error, false);
                    btnToggleMonto.disabled = false;
                    btnToggleMonto.innerHTML = originalHtml;
                }
            } catch (err) {
                window.showInfoModal('Error', 'Error de conexión', false);
                btnToggleMonto.disabled = false;
                btnToggleMonto.innerHTML = originalHtml;
            }
        }
    });

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

    startAutoRefresh();
});
