document.addEventListener('DOMContentLoaded', () => {
    // showConfirmModal/showInfoModal vienen globales desde
    // assets/js/utils/modalUtils.js (cargado en toda página vía footer.php).

    const tableBody = document.getElementById('historial-body');
    const noResultsDiv = document.getElementById('no-results');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');

    let allTransactions = [];

    const formatCurrency = (amount, currency) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: currency }).format(amount);

    async function handleConfirmReceipt(btn) {
        const txId = btn.dataset.txId;
        const received = btn.dataset.received === 'true';

        let title, message;
        if (received && btn.textContent.trim().toLowerCase().includes('sí lo recibí')) {
            title = 'Cambiar a recibido';
            message = '¿Confirmas que en realidad SÍ recibiste el dinero? Esta acción no se podrá deshacer después.';
        } else if (received) {
            title = 'Confirmar recepción';
            message = '¿Confirmas que recibiste el dinero correctamente? Esta acción no se podrá deshacer.';
        } else {
            title = 'Reportar no recibido';
            message = 'Vas a reportar que NO recibiste el dinero. El administrador será notificado para contactarte. Podrás cambiar esto a "Recibido" más tarde si en realidad sí llegó.';
        }
        const ok = await window.showConfirmModal(title, message);
        if (!ok) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const resp = await fetch('../api/?accion=confirmReceipt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transactionId: parseInt(txId, 10), received })
            });
            const data = await window.parseJsonResponse(resp);

            if (!data.success) {
                window.showInfoModal('Error', data.error || 'No se pudo actualizar.', false);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                return;
            }

            window.showInfoModal('Listo', received ? 'Confirmaste que recibiste el dinero.' : 'Reportaste que no recibiste el dinero. El administrador fue notificado.', true, () => {
                // Era fetchAndRenderHistorial(), función que no existe en ningún
                // lado: tiraba ReferenceError al cerrar el modal y la tabla se
                // quedaba con el estado viejo hasta recargar a mano.
                loadHistorial();
            });
        } catch (err) {
            console.error(err);
            window.showInfoModal('Error', window.formatNetworkError(err, 'Error de red. Intenta de nuevo.'), false);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    /**
     * Devuelve null si salió bien, o el mensaje de error a mostrar.
     *
     * No abre el modal de error acá: el llamador todavía tiene abierto el modal
     * de confirmación y encimar uno sobre otro deja backdrops huérfanos. El
     * mensaje se muestra recién cuando ese modal terminó de cerrarse.
     */
    async function handleConfirmReceiptById(txId, received) {
        try {
            const resp = await fetch('../api/?accion=confirmReceipt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transactionId: txId, received })
            });
            const data = await window.parseJsonResponse(resp);
            if (!data.success) {
                return data.error || 'No se pudo registrar tu confirmación.';
            }
            loadHistorial();
            return null;
        } catch (err) {
            console.error('confirmReceipt error:', err);
            return window.formatNetworkError(err, 'No se pudo registrar tu confirmación.');
        }
    }

    const getStatusBadge = (statusId, statusName) => {
        const id = parseInt(statusId);
        if (id === 6) return `<span class="badge bg-warning text-dark"><i class="bi bi-pause-circle-fill"></i> Pausado</span>`;
        let badgeClass = 'bg-secondary';
        switch (statusName) {
            case 'Exitoso': badgeClass = 'bg-success'; break;
            case 'En Proceso': badgeClass = 'bg-primary'; break;
            case 'En Verificación': badgeClass = 'bg-info text-dark'; break;
            case 'Cancelado': badgeClass = 'bg-danger'; break;
            case 'Pendiente de Pago': badgeClass = 'bg-warning text-dark'; break;
        }
        return `<span class="badge ${badgeClass}">${statusName}</span>`;
    };

    const getFileExt = (path) => path ? path.split('.').pop().toLowerCase() : 'jpg';

    // =========================================================
    // MODAL DE REANUDAR
    // =========================================================
    const injectResumeForm = () => {
        if (document.getElementById('resumeOrderModal')) {
            document.getElementById('resumeOrderModal').remove();
        }

        const formHtml = `
            <div class="modal fade" id="resumeOrderModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-warning shadow-lg">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> Corregir y Reanudar Orden</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="resume-order-form">
                                <input type="hidden" id="resume-tx-id" name="transactionId">
                                <div class="alert alert-warning small">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Corrige los datos solicitados por el administrador para poder reanudar tu orden.
                                </div>

                                <div class="alert alert-info d-none mb-3 p-2 small" id="monto-edit-alert"></div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Monto Enviado (Origen)</label>
                                    <input type="number" step="0.01" id="edit-monto-origen" class="form-control bg-light" readonly>
                                    <div class="form-text text-muted" style="font-size: 0.75rem;">Modificable solo bajo autorización del administrador.</div>
                                </div>

                                <div class="card bg-light border-0 mb-3">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2">Datos del Beneficiario a Corregir</h6>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold mb-1">Nombre Completo</label>
                                                <input type="text" id="edit-benef-name" name="benefNombre" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold mb-1">N° Documento</label>
                                                <input type="text" id="edit-benef-doc" name="benefDocumento" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold mb-1">Banco Destino</label>
                                                <input type="text" id="edit-benef-bank" name="benefBanco" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold mb-1">N° Cuenta / CCI</label>
                                                <input type="text" id="edit-benef-account" name="benefCuenta" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-bold mb-1">Teléfono Móvil</label>
                                                <input type="text" id="edit-benef-phone" name="benefTelefono" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Mensaje / Nota para el Administrador (Opcional)</label>
                                    <textarea id="resume-message" name="mensaje" class="form-control" rows="2" placeholder="Explica brevemente tu corrección..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Nuevo Comprobante de Pago (Si te lo solicitaron)</label>
                                    <input type="file" id="resume-receipt" name="receiptFile" class="form-control" accept="image/*,application/pdf">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" form="resume-order-form" class="btn btn-warning fw-bold"><i class="bi bi-send-check-fill"></i> Guardar y Reanudar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', formHtml);

        const form = document.getElementById('resume-order-form');
        // El botón submit vive en modal-footer (FUERA del <form>) usando form="resume-order-form".
        // form.querySelector('button[type=submit]') devolvía null y reventaba en btn.innerHTML.
        // Buscamos el botón por el atributo form="..." que es como se conectan en HTML5.
        const submitBtn = document.querySelector('button[form="resume-order-form"][type="submit"]');

        if (form && submitBtn) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = submitBtn;
                const originalText = btn.innerHTML;

                const fileInput = document.getElementById('resume-receipt');
                const montoInput = document.getElementById('edit-monto-origen');
                const txId = document.getElementById('resume-tx-id').value;
                const mensaje = document.getElementById('resume-message').value.trim();

                let originalMonto = 0;
                let nuevoMonto = 0;
                let hasNewAmount = false;

                if (montoInput && montoInput.dataset.original) {
                    originalMonto = parseFloat(montoInput.dataset.original);
                    nuevoMonto = parseFloat(montoInput.value);

                    if (montoInput.dataset.moneda !== 'USD') {
                        nuevoMonto = Math.floor(nuevoMonto);
                    }
                    if (!isNaN(nuevoMonto) && (nuevoMonto !== originalMonto)) {
                        hasNewAmount = true;
                    }
                }

                const beneficiaryData = {
                    nombre: document.getElementById('edit-benef-name').value.trim(),
                    documento: document.getElementById('edit-benef-doc').value.trim(),
                    banco: document.getElementById('edit-benef-bank').value.trim(),
                    cuenta: document.getElementById('edit-benef-account').value.trim(),
                    telefono: document.getElementById('edit-benef-phone').value.trim()
                };

                if (!beneficiaryData.nombre) {
                    window.showInfoModal('Faltan Datos', 'El nombre del beneficiario es obligatorio para la corrección.', false);
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

                try {
                    if (hasNewAmount && !montoInput.readOnly) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Auditando Monto...';
                        const resAmount = await fetch('../api/?accion=updatePausedTransactionAmount', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ txId: txId, nuevoMonto: nuevoMonto })
                        });
                        const dataAmount = await window.parseJsonResponse(resAmount);
                        if (!dataAmount.success) {
                            throw new Error(dataAmount.error || 'Error al actualizar el monto.');
                        }
                    }

                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando Orden...';
                    const formData = new FormData(form);
                    formData.append('beneficiaryData', JSON.stringify(beneficiaryData));
                    formData.append('txId', txId);

                    const res = await fetch('../api/?accion=resumeOrder', {
                        method: 'POST', body: formData
                    });
                    const result = await window.parseJsonResponse(res);

                    if (!result.success) throw new Error(result.error || 'Error al reanudar orden.');

                    bootstrap.Modal.getInstance(document.getElementById('resumeOrderModal')).hide();
                    window.showInfoModal('Éxito', 'Orden actualizada y reanudada correctamente.', true, () => {
                        loadHistorial();
                    });

                } catch (err) {
                    window.showInfoModal('Error', window.formatNetworkError(err), false);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        }
    };

    const loadHistorial = async () => {
        try {
            const response = await fetch('../api/?accion=getHistorialTransacciones');
            const data = await window.parseJsonResponse(response);
            if (!data.success) throw new Error(data.error || 'Error desconocido');
            allTransactions = data.transacciones || [];
            filterData();

            // Muestra modal si hay órdenes canceladas automáticamente
            const canceladasVistas = JSON.parse(localStorage.getItem('jc_canceladas_vistas') || '[]');
            const nuevasCanceladas = allTransactions.filter(tx =>
                parseInt(tx.AutoCancelado, 10) === 1 && !canceladasVistas.includes(tx.TransaccionID)
            );

            const hayAutoCancelados = nuevasCanceladas.length > 0;
            if (hayAutoCancelados) {
                const modalEl = document.getElementById('autoCanceladoModal');
                if (modalEl) {
                    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    bsModal.show();
                    const btnEntendido = modalEl.querySelector('[data-bs-dismiss="modal"]');
                    if (btnEntendido) {
                        btnEntendido.onclick = function () {
                            const nuevosIds = nuevasCanceladas.map(tx => tx.TransaccionID);
                            const actualizados = [...new Set([...canceladasVistas, ...nuevosIds])];
                            localStorage.setItem('jc_canceladas_vistas', JSON.stringify(actualizados));
                        };
                    }
                }
            }

            // Muestra modal de confirmación de recepción 2h después del pago
            if (!hayAutoCancelados) {
                const now = Date.now();
                const pendienteConf = allTransactions.find(tx =>
                    tx.EstadoNombre === 'Exitoso' &&
                    tx.ConfirmacionRecepcion === 'pendiente' &&
                    tx.FechaPago &&
                    (now - new Date(tx.FechaPago.replace(' ', 'T')).getTime()) >= 2 * 60 * 60 * 1000
                );
                if (pendienteConf) {
                    const modalEl = document.getElementById('confirmRecepcionModal');
                    if (modalEl) {
                        const txIdLabel = document.getElementById('confirm-modal-tx-id');
                        if (txIdLabel) txIdLabel.textContent = `#${pendienteConf.TransaccionID}`;

                        // getOrCreateInstance: loadHistorial() corre varias veces
                        // por sesión y crear una instancia nueva sobre el MISMO
                        // elemento apila instancias con sus propios listeners.
                        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

                        const confirmarRecepcion = async (btn, received) => {
                            if (btn.dataset.loading === '1') return;
                            btn.dataset.loading = '1';
                            const yesBtn = document.getElementById('confirm-received-yes');
                            const noBtn = document.getElementById('confirm-received-no');
                            if (yesBtn) yesBtn.disabled = true;
                            if (noBtn) noBtn.disabled = true;

                            const errorMsg = await handleConfirmReceiptById(pendienteConf.TransaccionID, received);

                            if (errorMsg) {
                                modalEl.addEventListener('hidden.bs.modal', () => {
                                    window.showInfoModal('Error', errorMsg, false);
                                }, { once: true });
                            }

                            if (yesBtn) yesBtn.disabled = false;
                            if (noBtn) noBtn.disabled = false;
                            delete btn.dataset.loading;
                            bsModal.hide();
                        };

                        document.getElementById('confirm-received-yes')?.addEventListener('click', function () {
                            confirmarRecepcion(this, true);
                        }, { once: true });

                        document.getElementById('confirm-received-no')?.addEventListener('click', function () {
                            confirmarRecepcion(this, false);
                        }, { once: true });

                        bsModal.show();
                    }
                }
            }

        } catch (error) {
            console.error(error);
            if (tableBody && allTransactions.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar el historial.</td></tr>`;
            }
        }
    };

    const renderTable = (transactions) => {
        if (!tableBody) return;
        if (transactions.length === 0) {
            tableBody.innerHTML = '';
            if (noResultsDiv) {
                noResultsDiv.classList.remove('d-none');
                noResultsDiv.textContent = allTransactions.length === 0 ? "Aún no has realizado ninguna transacción." : "No se encontraron resultados con los filtros actuales.";
            }
            return;
        }
        if (noResultsDiv) noResultsDiv.classList.add('d-none');

        tableBody.innerHTML = transactions.map(tx => {
            const estadoId = parseInt(tx.EstadoID);
            const motivoSafe = (tx.MotivoPausa || '').replace(/"/g, '&quot;');
            let btns = '';

            if (estadoId === 6) {
                btns += `
                    <button class="btn btn-sm btn-info text-white view-reason-btn me-1" data-reason="${motivoSafe}" title="Ver Motivo"><i class="bi bi-info-circle"></i> Ver Motivo</button>
                    <button class="btn btn-sm btn-warning fw-bold resume-order-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#resumeOrderModal" 
                        data-tx-id="${tx.TransaccionID}" 
                        
                        data-monto="${tx.MontoOrigen}"
                        data-permitir="${tx.PermitirEdicionMonto || 0}"
                        data-moneda="${tx.MonedaOrigen || ''}"
                        data-pais="${tx.PaisDestino || ''}"
                        data-benef-name="${tx.BeneficiarioNombre || tx.BeneficiarioAlias || ''}"
                        data-benef-doc="${tx.BeneficiarioDocumento || ''}"
                        data-benef-bank="${tx.BeneficiarioBanco || ''}"
                        data-benef-account="${tx.BeneficiarioNumeroCuenta || ''}"
                        data-benef-phone="${tx.BeneficiarioTelefono || ''}"

                        title="Modificar Datos"><i class="bi bi-pencil-square"></i> Corregir</button>
                `;
            }
            // Cancelable por el propio cliente en Pendiente de Pago (1) y en
            // Verificación (2): si subió el comprobante equivocado, cancelar es
            // la única salida —el backend libera el hash al cancelar, así que
            // ese mismo comprobante queda disponible para la orden correcta.
            // El backend (cancelTransaction) ya admitía ambos estados.
            if (estadoId === 1 || estadoId === 2) {
                btns += `<button class="btn btn-sm btn-outline-danger cancel-btn" data-tx-id="${tx.TransaccionID}" title="Cancelar Orden"><i class="bi bi-x-circle"></i> Cancelar</button>`;
            }
            if (estadoId === 1) {
                const extensionesUsadas = parseInt(tx.ExtensionesPlazoUsadas || 0);
                if (extensionesUsadas < 2) {
                    btns += ` <button class="btn btn-sm btn-outline-success extend-deadline-btn" data-tx-id="${tx.TransaccionID}" title="Confirmar que vas a pagar y obtener 4 horas más de plazo"><i class="bi bi-clock-history"></i> Sí, voy a pagar</button>`;
                }
            }
            if (!tx.ComprobanteURL && estadoId === 1) {
                btns += ` <button class="btn btn-sm btn-warning upload-btn" data-id="${tx.TransaccionID}" data-moneda-origen="${tx.MonedaOrigen || ''}" title="Subir Comprobante"><i class="bi bi-upload"></i> Subir Pago</button>`;
            } else if (tx.ComprobanteURL && estadoId === 1) {
                btns += ` <button class="btn btn-sm btn-secondary upload-btn" data-id="${tx.TransaccionID}" data-moneda-origen="${tx.MonedaOrigen || ''}" title="Reemplazar comprobante de pago"><i class="bi bi-pencil-square"></i> Reemplazar</button>`;
            }

            if (tx.ComprobanteURL) {
                const ext = getFileExt(tx.ComprobanteURL);
                // Mostrar fecha y hora de subida del comprobante en el tooltip y como sub-texto
                const fechaSubida = tx.FechaSubidaComprobante
                    ? new Date(tx.FechaSubidaComprobante.replace(' ', 'T')).toLocaleString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : null;
                const tituloPago = fechaSubida ? `Ver Pago (subido ${fechaSubida})` : 'Ver Pago';
                btns += ` <button class="btn btn-sm btn-outline-secondary view-comprobante-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#viewComprobanteModal" 
                            data-tx-id="${tx.TransaccionID}" 
                            data-comprobante-url="ver-comprobantes.php?id=${tx.TransaccionID}&type=user"
                            data-file-ext="${ext}"
                            data-fecha-subida="${tx.FechaSubidaComprobante || ''}"
                            data-start-type="user" 
                            title="${tituloPago}"><i class="bi bi-eye"></i> Ver Pago${fechaSubida ? ` <small class="opacity-75">(${fechaSubida.split(',')[0]})</small>` : ''}</button>`;
            }

            if (estadoId !== 7) {
                btns += ` <a href="../generar-factura.php?id=${tx.TransaccionID}" target="_blank" class="btn btn-sm btn-info" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i> Ver Orden</a>`;
            } else {
                btns += ` <span class="badge bg-secondary" title="Disponible tras aprobación"><i class="bi bi-clock"></i> Pendiente</span>`;
            }

            if (tx.ComprobanteEnvioURL) {
                const ext = getFileExt(tx.ComprobanteEnvioURL);
                btns += ` <button class="btn btn-sm btn-success view-comprobante-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#viewComprobanteModal" 
                            data-tx-id="${tx.TransaccionID}" 
                            data-envio-url="ver-comprobantes.php?id=${tx.TransaccionID}&type=admin"
                            data-file-ext="${ext}"
                            data-start-type="admin" 
                            title="Ver Comprobante Envío"><i class="bi bi-receipt"></i> Ver Envío</button>`;
            }

            if (tx.EstadoNombre === 'Exitoso') {
                const conf = (tx.ConfirmacionRecepcion || 'pendiente');
                const fechaConf = tx.FechaConfirmacionRecepcion
                    ? new Date(tx.FechaConfirmacionRecepcion.replace(' ', 'T')).toLocaleString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : '';

                if (conf === 'pendiente') {
                    btns += ` <button class="btn btn-sm btn-outline-success confirm-receipt-btn"
                                data-tx-id="${tx.TransaccionID}" data-received="true"
                                title="Confirmar que recibí el dinero"><i class="bi bi-check2-circle"></i> Recibí</button>`;
                    btns += ` <button class="btn btn-sm btn-outline-danger confirm-receipt-btn"
                                data-tx-id="${tx.TransaccionID}" data-received="false"
                                title="Reportar que NO recibí el dinero"><i class="bi bi-x-circle"></i> No recibí</button>`;
                } else if (conf === 'recibido') {
                    btns += ` <span class="badge bg-success" title="Confirmado el ${fechaConf}"><i class="bi bi-check2-all"></i> Recibido${fechaConf ? ` (${fechaConf.split(',')[0]})` : ''}</span>`;
                } else if (conf === 'no_recibido') {
                    btns += ` <span class="badge bg-danger" title="Reportaste no recibir el ${fechaConf}"><i class="bi bi-exclamation-triangle"></i> No recibido</span>`;
                    btns += ` <button class="btn btn-sm btn-outline-success confirm-receipt-btn"
                                data-tx-id="${tx.TransaccionID}" data-received="true"
                                title="En realidad sí lo recibí, cambiar a recibido"><i class="bi bi-check2"></i> Sí lo recibí</button>`;
                }
            }

            return `
                <tr class="${estadoId === 6 ? 'table-warning' : ''}">
                    <th scope="row">#${tx.TransaccionID}</th>
                    <td>${new Date(tx.FechaTransaccion).toLocaleDateString()}</td>
                    <td>${tx.BeneficiarioNombre || tx.BeneficiarioAlias || 'N/A'}</td>
                    <td>${formatCurrency(tx.MontoOrigen, tx.MonedaOrigen)}</td>
                    <td>${formatCurrency(tx.MontoDestino, tx.MonedaDestino)}</td>
                    <td>${getStatusBadge(estadoId, tx.EstadoNombre)}</td>
                    <td class="d-flex flex-wrap gap-1">${btns}</td>
                </tr>
            `;
        }).join('');
    };

    const filterData = () => {
        const term = searchInput ? searchInput.value.toLowerCase() : '';
        const status = statusFilter ? statusFilter.value : 'all';
        if (allTransactions.length === 0) {
            renderTable([]);
            return;
        }

        const filtered = allTransactions.filter(tx => {
            const matchText =
                tx.TransaccionID.toString().includes(term) ||
                (tx.BeneficiarioNombre && tx.BeneficiarioNombre.toLowerCase().includes(term)) ||
                (tx.BeneficiarioAlias && tx.BeneficiarioAlias.toLowerCase().includes(term)) ||
                tx.MontoOrigen.toString().includes(term);
            const matchStatus = status === 'all' || tx.EstadoID == status;
            return matchText && matchStatus;
        });
        renderTable(filtered);
    };

    if (searchInput) searchInput.addEventListener('input', filterData);
    if (statusFilter) statusFilter.addEventListener('change', filterData);

    // Eventos Globales de Tabla
    if (tableBody) {
        tableBody.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            if (btn.classList.contains('view-reason-btn')) {
                const reasonText = document.getElementById('reason-content-text');
                const reasonModalEl = document.getElementById('viewReasonModal');
                if (reasonText && reasonModalEl) {
                    reasonText.textContent = btn.dataset.reason;
                    bootstrap.Modal.getOrCreateInstance(reasonModalEl).show();
                }
            } else if (btn.classList.contains('confirm-receipt-btn')) {
                handleConfirmReceipt(btn);
            } else if (btn.classList.contains('upload-btn')) {
                const txIdField = document.getElementById('transactionIdField');
                const txLabel = document.getElementById('modal-tx-id');
                const modalEl = document.getElementById('uploadReceiptModal');
                if (modalEl) modalEl.dataset.monedaOrigen = btn.dataset.monedaOrigen || '';

                if (txIdField) txIdField.value = btn.dataset.id;
                if (txLabel) txLabel.textContent = btn.dataset.id;
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else if (btn.classList.contains('resume-order-btn')) {
                const resumeField = document.getElementById('resume-tx-id');
                if (resumeField) resumeField.value = btn.dataset.txId;
                const inputs = {
                    'edit-benef-name': btn.dataset.benefName,
                    'edit-benef-doc': btn.dataset.benefDoc,
                    'edit-benef-bank': btn.dataset.benefBank,
                    'edit-benef-account': btn.dataset.benefAccount,
                    'edit-benef-phone': btn.dataset.benefPhone
                };
                const paisDestino = btn.dataset.pais;
                const labelCuenta = document.getElementById('edit-benef-account').previousElementSibling;
                const labelTelefono = document.getElementById('edit-benef-phone').previousElementSibling;

                if (paisDestino === 'Perú') {
                    if (labelCuenta) labelCuenta.textContent = 'CCI / N° Cuenta';
                    if (labelTelefono) labelTelefono.textContent = 'PLIN / YAPE';
                } else if (paisDestino === 'Venezuela') {
                    if (labelCuenta) labelCuenta.textContent = 'N° Cuenta';
                    if (labelTelefono) labelTelefono.textContent = 'Pago Móvil';
                } else {
                    if (labelCuenta) labelCuenta.textContent = 'N° Cuenta / CCI';
                    if (labelTelefono) labelTelefono.textContent = 'Teléfono Móvil';
                }

                for (const [id, value] of Object.entries(inputs)) {
                    const el = document.getElementById(id);
                    if (el) el.value = value || '';
                }
                const montoInput = document.getElementById('edit-monto-origen');
                const montoAlert = document.getElementById('monto-edit-alert');

                if (montoInput) {
                    montoInput.dataset.original = parseFloat(btn.dataset.monto);
                    montoInput.dataset.moneda = btn.dataset.moneda;
                    if (btn.dataset.moneda === 'USD') {
                        montoInput.step = "0.01";
                        montoInput.value = parseFloat(btn.dataset.monto);
                    } else {
                        montoInput.step = "1";
                        montoInput.value = Math.floor(parseFloat(btn.dataset.monto));
                    }

                    if (btn.dataset.permitir == '1') {
                        montoInput.readOnly = false;
                        montoInput.classList.remove('bg-light');
                        montoInput.classList.add('border-warning', 'fw-bold');
                        if (montoAlert) {
                            montoAlert.innerHTML = '<i class="bi bi-unlock-fill"></i> Tienes autorización para corregir el monto de esta orden.';
                            montoAlert.classList.replace('d-none', 'd-block');
                        }
                    } else {
                        montoInput.readOnly = true;
                        montoInput.classList.add('bg-light');
                        montoInput.classList.remove('border-warning', 'fw-bold');
                        if (montoAlert) montoAlert.classList.replace('d-block', 'd-none');
                    }
                }
            }
        });
    }

    // Expuesto como global para que historial-comprobantes.js e
    // historial-visor.js puedan refrescar la tabla sin compartir scope.
    window.reloadHistorial = loadHistorial;

    // --- CARGA INICIAL ---
    injectResumeForm();
    loadHistorial();
    setInterval(loadHistorial, 10000);
});