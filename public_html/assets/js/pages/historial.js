document.addEventListener('DOMContentLoaded', () => {
    // ==========================================
    // 1. VARIABLES Y REFERENCIAS
    // ==========================================
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

    // ==========================================
    // 2. LÓGICA DE LA CÁMARA
    // ==========================================
    if (uploadModalElement && uploadForm) {
        let uploadModalInstance = null;

        const stopCamera = () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            videoEl.srcObject = null;
            cameraSection.classList.add('d-none');

            if (cameraToggleContainer && !cameraToggleContainer.classList.contains('force-hidden')) {
                cameraToggleContainer.classList.remove('d-none');
            }
        };

        const startCamera = async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' }
                    }
                });
                videoEl.srcObject = stream;
                cameraSection.classList.remove('d-none');
                cameraToggleContainer.classList.add('d-none');
            } catch (err) {
                console.error("Error cámara:", err);
                alert("No se pudo iniciar la cámara. Asegúrate de dar permisos o usa el botón 'Seleccionar archivo'.");
            }
        };

        const takePhoto = () => {
            if (!stream) return;

            const MAX_WIDTH = 1024;
            let width = videoEl.videoWidth;
            let height = videoEl.videoHeight;

            if (width > MAX_WIDTH) {
                height = height * (MAX_WIDTH / width);
                width = MAX_WIDTH;
            }

            canvasEl.width = width;
            canvasEl.height = height;

            const ctx = canvasEl.getContext('2d');
            ctx.drawImage(videoEl, 0, 0, width, height);
            canvasEl.toBlob((blob) => {
                const txId = transactionIdField.value || 'temp';
                const timestamp = new Date().getTime();
                const fileName = `foto_comprobante_${txId}_${timestamp}.jpg`;

                const file = new File([blob], fileName, { type: 'image/jpeg' });

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;

                stopCamera();

            }, 'image/jpeg', 0.70);
        };

        if (btnStartCamera) btnStartCamera.addEventListener('click', startCamera);
        if (btnCapture) btnCapture.addEventListener('click', takePhoto);
        if (btnCancelCamera) btnCancelCamera.addEventListener('click', stopCamera);

        uploadModalElement.addEventListener('show.bs.modal', function (event) {
            uploadModalInstance = bootstrap.Modal.getInstance(uploadModalElement) || new bootstrap.Modal(uploadModalElement);
            const button = event.relatedTarget;
            const transactionId = button.getAttribute('data-tx-id');

            transactionIdField.value = transactionId;
            modalTxIdLabel.textContent = transactionId;
            uploadForm.reset();

            if (cameraToggleContainer) {
                cameraToggleContainer.classList.remove('d-none');
                cameraToggleContainer.classList.remove('force-hidden');
            }

            cameraSection.classList.add('d-none');
        });

        uploadModalElement.addEventListener('hidden.bs.modal', function () {
            stopCamera();
        });

        uploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(uploadForm);
            const submitButton = uploadModalElement.querySelector('button[type="submit"]');

            if (!submitButton) return;

            submitButton.disabled = true;
            submitButton.textContent = 'Subiendo...';

            try {
                const response = await fetch('../api/?accion=uploadReceipt', {
                    method: 'POST',
                    body: formData
                });

                let result;
                try { result = await response.json(); } catch (e) { result = { success: false, error: 'Error servidor.' }; }

                if (uploadModalInstance) uploadModalInstance.hide();

                if (response.ok && result.success) {
                    if (window.showInfoModal) {
                        window.showInfoModal('¡Éxito!', `Comprobante subido correctamente.`, true, () => { window.location.reload(); });
                    } else {
                        alert(`Comprobante subido correctamente.`);
                        window.location.reload();
                    }
                } else {
                    if (window.showInfoModal) window.showInfoModal('Error', result.error || 'Fallo al subir.', false);
                    else alert(result.error);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Subir Archivo';
                }
            } catch (error) {
                if (uploadModalInstance) uploadModalInstance.hide();
                if (window.showInfoModal) window.showInfoModal('Error', 'Error de conexión.', false);
                submitButton.disabled = false;
                submitButton.textContent = 'Subir Archivo';
            }
        });
    }

    // ==========================================
    // 3. NUEVO: LÓGICA DE REANUDACIÓN (PAUSA)
    // ==========================================
    const resumeModal = document.getElementById('resumeOrderModal');
    if (resumeModal) {
        resumeModal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            document.getElementById('resume-tx-id').value = btn.dataset.txId;
        });
    }

    const resumeForm = document.getElementById('resume-order-form');
    if (resumeForm) {
        resumeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const txId = document.getElementById('resume-tx-id').value;
            const mensaje = document.getElementById('resume-message').value;

            try {
                const res = await fetch('../api/?accion=resumeOrder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ txId: txId, mensaje: mensaje })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert("Error: " + (data.error || "No se pudo enviar la notificación"));
                }
            } catch (err) {
                console.error(err);
                alert("Error de conexión al servidor.");
            }
        });
    }

    // ==========================================
    // 4. CANCELACIÓN DE TRANSACCIONES
    // ==========================================
    const cancelButtons = document.querySelectorAll('.cancel-btn');
    cancelButtons.forEach(button => {
        button.addEventListener('click', async (e) => {
            const transactionId = e.target.closest('button').dataset.txId;
            if (!transactionId) return;

            const confirmed = await window.showConfirmModal(
                'Confirmar Cancelación',
                `¿Estás seguro de que quieres cancelar la transacción #${transactionId}? Esta acción no se puede deshacer.`
            );

            if (confirmed) {
                const btn = e.target.closest('button');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Cancelando...';

                try {
                    const response = await fetch('../api/?accion=cancelTransaction', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ transactionId })
                    });

                    let result;
                    try { result = await response.json(); } catch (e) { result = { success: false }; }

                    if (response.ok && result.success) {
                        if (window.showInfoModal) window.showInfoModal('Éxito', 'Transacción cancelada.', true, () => window.location.reload());
                        else location.reload();
                    } else {
                        if (window.showInfoModal) window.showInfoModal('Error', result.error || 'Error al cancelar.', false);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar';
                    }
                } catch (error) {
                    if (window.showInfoModal) window.showInfoModal('Error', 'Error de conexión.', false);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar';
                }
            }
        });
    });

    // ==========================================
    // 5. VISOR DE COMPROBANTES (COMPLETO)
    // ==========================================
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
            modalLabel.textContent = `${typeText} (Transacción #${currentTxId})`;

            downloadButton.href = secureUrl;
            downloadButton.download = fileName;
            filenameSpan.textContent = fileName;

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
            } catch (e) { console.error("Error al cargar archivo:", e); }

            if (comprobantes.length > 1) {
                indicatorSpan.textContent = `${index + 1} / ${comprobantes.length}`;
                prevButton.disabled = (index === 0);
                nextButton.disabled = (index === comprobantes.length - 1);
            } else {
                prevButton.disabled = true;
                nextButton.disabled = true;
            }
        };

        viewModalElement.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;

            const userUrl = button.dataset.comprobanteUrl || '';
            const adminUrl = button.dataset.envioUrl || '';
            currentTxId = button.dataset.txId || 'N/A';

            comprobantes = [];
            if (userUrl) comprobantes.push({ type: 'user', url: userUrl });
            if (adminUrl) comprobantes.push({ type: 'admin', url: adminUrl });

            if (comprobantes.length > 1) navigationDiv.classList.remove('d-none');
            else navigationDiv.classList.add('d-none');

            currentIndex = 0;
            if (button.dataset.startType === 'admin' && adminUrl) {
                currentIndex = comprobantes.findIndex(c => c.type === 'admin');
            }

            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');

            if (comprobantes.length > 0) {
                setTimeout(() => showComprobante(currentIndex), 100);
            } else {
                modalPlaceholder.textContent = 'No hay comprobantes disponibles para esta transacción.';
                downloadButton.classList.add('disabled');
            }
        });

        prevButton.addEventListener('click', () => { if (currentIndex > 0) showComprobante(currentIndex - 1); });
        nextButton.addEventListener('click', () => { if (currentIndex < comprobantes.length - 1) showComprobante(currentIndex + 1); });

        viewModalElement.addEventListener('hidden.bs.modal', () => {
            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');
        });
    }
});