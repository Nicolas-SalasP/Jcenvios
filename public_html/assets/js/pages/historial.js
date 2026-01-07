document.addEventListener('DOMContentLoaded', () => {
    if (!window.showConfirmModal) {
        window.showConfirmModal = async (title, message) => confirm(`${title}\n\n${message}`);
    }
    if (!window.showInfoModal) {
        window.showInfoModal = (title, message, isSuccess = false, callback = null) => {
            alert(`${title}: ${message}`);
            if (callback) callback();
        };
    }

    const tableBody = document.getElementById('historial-body');
    const noResultsDiv = document.getElementById('no-results');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');

    let allTransactions = [];

    const formatCurrency = (amount, currency) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: currency }).format(amount);

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

    const loadHistorial = async () => {
        try {
            const response = await fetch('../api/?accion=getHistorialTransacciones');
            const data = await response.json();

            if (!data.success) throw new Error(data.error || 'Error desconocido');

            allTransactions = data.transacciones || [];
            renderTable(allTransactions);

        } catch (error) {
            console.error(error);
            if (tableBody) tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar el historial.</td></tr>`;
        }
    };

    const getFileExt = (path) => path ? path.split('.').pop().toLowerCase() : 'jpg';

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
                    <button class="btn btn-sm btn-primary resume-order-btn" data-bs-toggle="modal" data-bs-target="#resumeOrderModal" data-tx-id="${tx.TransaccionID}" title="Notificar Corrección"><i class="bi bi-check-circle"></i> Corregido</button>
                `;
            }

            if (estadoId === 1) {
                btns += `<button class="btn btn-sm btn-outline-danger cancel-btn" data-tx-id="${tx.TransaccionID}" title="Cancelar Orden"><i class="bi bi-x-circle"></i> Cancelar</button>`;
            }

            if (!tx.ComprobanteURL && estadoId === 1) {
                btns += ` <button class="btn btn-sm btn-warning upload-btn" data-id="${tx.TransaccionID}" title="Subir Comprobante"><i class="bi bi-upload"></i> Subir Pago</button>`;
            } else if (tx.ComprobanteURL && ![4, 5, 6].includes(estadoId)) {
                btns += ` <button class="btn btn-sm btn-secondary upload-btn" data-id="${tx.TransaccionID}" title="Modificar Pago"><i class="bi bi-pencil-square"></i> Modificar</button>`;
            }

            if (tx.ComprobanteURL) {
                const ext = getFileExt(tx.ComprobanteURL);
                const originalName = tx.ComprobanteURL.split('/').pop();
                btns += ` <button class="btn btn-sm btn-outline-secondary view-comprobante-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#viewComprobanteModal" 
                            data-tx-id="${tx.TransaccionID}" 
                            data-comprobante-url="ver-comprobantes.php?id=${tx.TransaccionID}&type=user"
                            data-file-ext="${ext}"
                            data-file-name="${originalName}"
                            data-start-type="user" 
                            title="Ver Pago"><i class="bi bi-eye"></i> Ver Pago</button>`;
            }

            btns += ` <a href="../generar-factura.php?id=${tx.TransaccionID}" target="_blank" class="btn btn-sm btn-info" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i> Ver Orden</a>`;

            if (tx.ComprobanteEnvioURL) {
                const ext = getFileExt(tx.ComprobanteEnvioURL);
                const originalName = tx.ComprobanteEnvioURL.split('/').pop();
                btns += ` <button class="btn btn-sm btn-success view-comprobante-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#viewComprobanteModal" 
                            data-tx-id="${tx.TransaccionID}" 
                            data-envio-url="ver-comprobantes.php?id=${tx.TransaccionID}&type=admin"
                            data-file-ext="${ext}"
                            data-file-name="${originalName}"
                            data-start-type="admin" 
                            title="Ver Comprobante Envío"><i class="bi bi-receipt"></i> Ver Envío</button>`;
            }

            return `
                <tr class="${estadoId === 6 ? 'table-warning' : ''}">
                    <th scope="row">#${tx.TransaccionID}</th>
                    <td>${new Date(tx.FechaTransaccion).toLocaleDateString()}</td>
                    <td>${tx.BeneficiarioNombre || tx.BeneficiarioAlias || 'N/A'}</td>
                    <td>${formatCurrency(tx.MontoOrigen, tx.MonedaOrigen)}</td>
                    <td>${formatCurrency(tx.MontoDestino, tx.MonedaDestino)}</td>
                    <td>
                        ${getStatusBadge(estadoId, tx.EstadoNombre)}
                    </td>
                    <td class="d-flex flex-wrap gap-1">${btns}</td>
                </tr>
            `;
        }).join('');
    };

    const filterData = () => {
        const term = searchInput ? searchInput.value.toLowerCase() : '';
        const status = statusFilter ? statusFilter.value : 'all';

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
                    const modal = new bootstrap.Modal(reasonModalEl);
                    modal.show();
                }
            }
            else if (btn.classList.contains('upload-btn')) {
                const txIdField = document.getElementById('transactionIdField');
                const txLabel = document.getElementById('modal-tx-id');
                const modalEl = document.getElementById('uploadReceiptModal');

                if (txIdField) txIdField.value = btn.dataset.id;
                if (txLabel) txLabel.textContent = btn.dataset.id;

                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }
            else if (btn.classList.contains('resume-order-btn')) {
                const resumeField = document.getElementById('resume-tx-id');
                if (resumeField) resumeField.value = btn.dataset.txId;
            }
        });
    }

    // --- LÓGICA DE SUBIDA (CÁMARA / ARCHIVO) ---
    const uploadModalElement = document.getElementById('uploadReceiptModal');
    const uploadForm = document.getElementById('upload-receipt-form');
    const transactionIdField = document.getElementById('transactionIdField');
    const modalTxIdLabel = document.getElementById('modal-tx-id');

    const cameraSection = document.getElementById('camera-section');
    const videoEl = document.getElementById('camera-video');
    const canvasEl = document.getElementById('camera-canvas');
    const btnStartCamera = document.getElementById('btn-start-camera');
    const btnCapture = document.getElementById('btn-capture');
    const btnCancelCamera = document.getElementById('btn-cancel-camera');
    const cameraToggleContainer = document.getElementById('camera-toggle-container');
    const fileInput = document.getElementById('receiptFile');

    let stream = null;
    let uploadModalInstance = null;

    if (uploadModalElement && uploadForm) {
        uploadModalInstance = bootstrap.Modal.getInstance(uploadModalElement) || new bootstrap.Modal(uploadModalElement);

        const stopCamera = () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            if (videoEl) videoEl.srcObject = null;
            if (cameraSection) cameraSection.classList.add('d-none');
            if (cameraToggleContainer && !cameraToggleContainer.classList.contains('force-hidden')) {
                cameraToggleContainer.classList.remove('d-none');
            }
        };

        const startCamera = async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } }
                });
                videoEl.srcObject = stream;
                cameraSection.classList.remove('d-none');
                cameraToggleContainer.classList.add('d-none');
            } catch (err) {
                console.error("Error cámara:", err);
                alert("No se pudo iniciar la cámara. Verifica permisos o usa 'Seleccionar archivo'.");
            }
        };

        const takePhoto = () => {
            if (!stream || !videoEl || !canvasEl) return;
            const MAX_WIDTH = 1024;
            let width = videoEl.videoWidth;
            let height = videoEl.videoHeight;
            if (width > MAX_WIDTH) { height = height * (MAX_WIDTH / width); width = MAX_WIDTH; }
            canvasEl.width = width; canvasEl.height = height;
            const ctx = canvasEl.getContext('2d');
            ctx.drawImage(videoEl, 0, 0, width, height);
            canvasEl.toBlob((blob) => {
                if (!blob) return;
                const txId = transactionIdField.value || 'temp';
                const file = new File([blob], `foto_${txId}.jpg`, { type: 'image/jpeg' });
                const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
                stopCamera();
            }, 'image/jpeg', 0.85);
        };

        if (btnStartCamera) btnStartCamera.addEventListener('click', startCamera);
        if (btnCapture) btnCapture.addEventListener('click', takePhoto);
        if (btnCancelCamera) btnCancelCamera.addEventListener('click', stopCamera);

        uploadModalElement.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (button) {
                const txId = button.getAttribute('data-tx-id') || button.dataset.id;
                if (transactionIdField) transactionIdField.value = txId;
                if (modalTxIdLabel) modalTxIdLabel.textContent = txId;
            }
            if (uploadForm) uploadForm.reset();
            if (cameraToggleContainer) cameraToggleContainer.classList.remove('d-none');
            if (cameraSection) cameraSection.classList.add('d-none');
        });

        uploadModalElement.addEventListener('hidden.bs.modal', stopCamera);

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = uploadForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            if (fileInput.files.length === 0) { alert("Selecciona archivo o toma foto."); return; }
            submitBtn.disabled = true; submitBtn.textContent = 'Subiendo...';
            try {
                const response = await fetch('../api/?accion=uploadReceipt', {
                    method: 'POST', body: new FormData(uploadForm)
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    if (uploadModalInstance) uploadModalInstance.hide();
                    window.showInfoModal('¡Éxito!', 'Comprobante subido correctamente.', true, () => {
                        loadHistorial();
                    });
                } else {
                    throw new Error(result.error || 'Error al procesar la subida.');
                }
            } catch (error) {
                console.error(error);
                window.showInfoModal('Error', error.message || 'Error de conexión.', false);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // --- REANUDAR ORDEN ---
    const resumeForm = document.getElementById('resume-order-form');
    const resumeTxIdField = document.getElementById('resume-tx-id');
    const resumeModalEl = document.getElementById('resumeOrderModal');

    if (resumeForm) {
        resumeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = resumeForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            const txId = resumeTxIdField.value;
            const mensaje = document.getElementById('resume-message').value.trim();

            if (!mensaje) { alert("Escribe un mensaje."); return; }
            submitBtn.disabled = true; submitBtn.textContent = 'Enviando...';

            try {
                const res = await fetch('../api/?accion=resumeOrder', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ txId, mensaje })
                });
                const data = await res.json();
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(resumeModalEl);
                    if (modal) modal.hide();
                    window.showInfoModal('Enviado', 'Notificación enviada.', true, loadHistorial);
                } else { throw new Error(data.error); }
            } catch (err) { alert(err.message || "Error conexión."); }
            finally { submitBtn.disabled = false; submitBtn.textContent = originalText; }
        });
    }

    // --- CANCELAR ORDEN ---
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cancel-btn');
        if (!btn) return;
        const txId = btn.getAttribute('data-tx-id');
        if (await window.showConfirmModal('Cancelar', `¿Cancelar orden #${txId}?`)) {
            try {
                const res = await fetch('../api/?accion=cancelTransaction', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transactionId: txId })
                });
                const result = await res.json();
                if (result.success) window.showInfoModal('Cancelada', 'Orden cancelada.', true, loadHistorial);
                else throw new Error(result.error);
            } catch (err) { alert(err.message || 'Error conexión'); }
        }
    });

    // --- VISOR DE COMPROBANTES (MODAL) ---
    const viewModalElement = document.getElementById('viewComprobanteModal');
    if (viewModalElement) {
        const modalContent = document.getElementById('comprobante-content');
        const modalPlaceholder = document.getElementById('comprobante-placeholder');
        const downloadButton = document.getElementById('download-comprobante');
        const filenameSpan = document.getElementById('comprobante-filename');
        const navigationDiv = document.getElementById('comprobante-navigation');
        const indicatorSpan = document.getElementById('comprobante-indicator');
        const modalLabel = document.getElementById('viewComprobanteModalLabel');
        const prevButton = document.getElementById('prev-comprobante');
        const nextButton = document.getElementById('next-comprobante');

        let comprobantes = [];
        let currentIndex = 0;
        let currentTxId = '';

        const renderVisor = () => {
            if (!comprobantes[currentIndex]) return;
            const current = comprobantes[currentIndex];
            const typeText = current.type === 'user' ? 'Pago' : 'Envío';

            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');

            if (modalLabel) modalLabel.textContent = `${typeText} #${currentTxId}`;
            if (filenameSpan) filenameSpan.textContent = current.name || 'documento';

            if (downloadButton) {
                downloadButton.href = current.url;
                downloadButton.download = current.name || `comprobante_${currentTxId}`;
            }
            const ext = current.ext || current.url.split('.').pop().toLowerCase();

            let mediaEl;
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                mediaEl = document.createElement('img');
                mediaEl.className = 'img-fluid d-block mx-auto';
                mediaEl.style.maxHeight = '75vh';
            } else if (ext === 'pdf') {
                mediaEl = document.createElement('iframe');
                mediaEl.style.width = '100%'; mediaEl.style.height = '75vh'; mediaEl.style.border = '0';
            } else {
                mediaEl = document.createElement('div');
                mediaEl.textContent = 'Vista previa no disponible. Descarga el archivo para verlo.';
                mediaEl.className = 'text-white text-center p-5';
            }

            mediaEl.src = current.url;
            mediaEl.onload = () => { modalPlaceholder.classList.add('d-none'); };
            modalContent.appendChild(mediaEl);
            if (comprobantes.length > 1) {
                if (navigationDiv) navigationDiv.classList.remove('d-none');
                if (indicatorSpan) indicatorSpan.textContent = `${currentIndex + 1} / ${comprobantes.length}`;
                if (prevButton) prevButton.disabled = currentIndex === 0;
                if (nextButton) nextButton.disabled = currentIndex === comprobantes.length - 1;
            } else {
                if (navigationDiv) navigationDiv.classList.add('d-none');
            }
        };

        viewModalElement.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            if (!btn) return;

            currentTxId = btn.dataset.txId || '';
            comprobantes = [];
            if (btn.dataset.comprobanteUrl) {
                comprobantes.push({
                    type: 'user',
                    url: btn.dataset.comprobanteUrl,
                    ext: btn.dataset.fileExt,
                    name: btn.dataset.fileName
                });
            }
            if (btn.dataset.envioUrl) {
                comprobantes.push({
                    type: 'admin',
                    url: btn.dataset.envioUrl,
                    ext: btn.dataset.fileExt,
                    name: btn.dataset.fileName
                });
            }

            if (comprobantes.length === 0) {
                modalPlaceholder.textContent = 'Documento no disponible.';
                return;
            }

            currentIndex = 0;
            renderVisor();
        });

        if (prevButton) prevButton.addEventListener('click', () => { if (currentIndex > 0) { currentIndex--; renderVisor(); } });
        if (nextButton) nextButton.addEventListener('click', () => { if (currentIndex < comprobantes.length - 1) { currentIndex++; renderVisor(); } });

        viewModalElement.addEventListener('hidden.bs.modal', () => {
            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
        });
    }

    loadHistorial();
});