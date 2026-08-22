/**
 * Subida de comprobantes del cliente (camara, compresion y envio).
 *
 * Extraido de historial.js (1020 lineas) para bajarle la complejidad.
 * Abre su propio DOMContentLoaded y no comparte scope con el nucleo: para
 * refrescar la tabla usa window.reloadHistorial, que historial.js expone
 * justamente para esto.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- LÓGICA DE SUBIDA OPTIMIZADA ---
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

    const previewContainer = document.getElementById('historial-preview-container');
    const previewImg = document.getElementById('historial-preview-img');
    const previewPdf = document.getElementById('historial-preview-pdf');
    const handleFilePreview = (file) => {
        if (!previewContainer) return;
        if (file) {
            previewContainer.classList.remove('d-none');
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    previewImg.src = evt.target.result;
                    previewImg.classList.remove('d-none');
                    previewPdf.classList.add('d-none');
                }
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                previewImg.classList.add('d-none');
                previewPdf.classList.remove('d-none');
            }
        } else {
            previewContainer.classList.add('d-none');
        }
    };

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            handleFilePreview(e.target.files[0]);
        });
    }

    const compressImage = (file) => {
        return new Promise((resolve, reject) => {
            if (!file.type.match(/image.*/)) {
                resolve(file);
                return;
            }
            const maxWidth = 1280;
            const quality = 0.8;
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob((blob) => {
                        if (!blob) { reject(new Error('Error al comprimir')); return; }
                        const compressedFile = new File([blob], file.name, {
                            type: 'image/jpeg', lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', quality);
                };
                img.onerror = (err) => reject(err);
            };
            reader.onerror = (err) => reject(err);
        });
    };

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
                window.showInfoModal('Error', 'No se pudo iniciar la cámara.', false);
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
                const dt = new DataTransfer(); dt.items.add(file); 
                fileInput.files = dt.files;
                handleFilePreview(file);

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
            if (uploadForm) {
                uploadForm.reset();
                if (previewContainer) previewContainer.classList.add('d-none');
            }

            // Race condition con click handler: la lógica del RUT debe ir DESPUÉS
            // del uploadForm.reset(), porque sino el reset borra el value='N/A' que se
            // pusiera antes. La moneda viene en uploadModalElement.dataset.monedaOrigen
            // (la setea el click handler antes de abrir el modal).
            const monedaOrigen = uploadModalElement.dataset.monedaOrigen || '';
            const isChile = (monedaOrigen === 'CLP');
            const rutInput = document.getElementById('rutTitularOrigen');
            const rutContainer = rutInput ? rutInput.closest('.mb-3') : null;
            if (!isChile) {
                if (rutContainer) rutContainer.classList.add('d-none');
                if (rutInput) { rutInput.required = false; rutInput.value = 'N/A'; }
            } else {
                if (rutContainer) rutContainer.classList.remove('d-none');
                if (rutInput) { rutInput.required = true; rutInput.value = ''; }
            }

            if (cameraToggleContainer) cameraToggleContainer.classList.remove('d-none');
            if (cameraSection) cameraSection.classList.add('d-none');
        });

        uploadModalElement.addEventListener('hidden.bs.modal', stopCamera);

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = uploadForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            const rutInput = document.getElementById('rutTitularOrigen');
            const nombreInput = document.getElementById('nombreTitularOrigen');
            const isRutRequired = rutInput && rutInput.required;
            // Defensa: si el RUT no es requerido (origen no Chile) y el value
            // está vacío por cualquier motivo, forzar 'N/A'. Esto evita que el backend
            // rechace con "RUT obligatorio" si algo en el flujo borra el value.
            if (rutInput && !isRutRequired && !rutInput.value.trim()) {
                rutInput.value = 'N/A';
            }
            const rutOrigen = rutInput ? rutInput.value.trim() : '';
            const nombreOrigen = nombreInput ? nombreInput.value.trim() : '';

            if ((isRutRequired && !rutOrigen) || !nombreOrigen) {
                window.showInfoModal('Faltan Datos', 'Debes completar el Nombre y/o RUT del titular solicitados.', false);
                return;
            }

            if (fileInput.files.length === 0) {
                window.showInfoModal('Falta el comprobante', 'Selecciona el archivo del comprobante.', false);
                return;
            }

            // Límite igual al del servidor (FileHandlerService::saveReceiptFile, 10MB).
            // Las imágenes se comprimen antes de subir (más abajo) así que este límite
            // real solo importa para PDFs, que se suben tal cual.
            const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
            const originalFileCheck = fileInput.files[0];
            if (!originalFileCheck.type.startsWith('image/') && originalFileCheck.size > MAX_UPLOAD_BYTES) {
                window.showInfoModal('Archivo muy pesado', `El comprobante pesa ${(originalFileCheck.size / 1024 / 1024).toFixed(1)}MB. El máximo permitido es 10MB — si es un PDF escaneado, intenta con una foto en su lugar.`, false);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Procesando...';

            try {
                const originalFile = fileInput.files[0];
                let fileToUpload = originalFile;
                if (originalFile.type.startsWith('image/')) {
                    fileToUpload = await compressImage(originalFile);
                }

                const formData = new FormData(uploadForm);
                formData.set('receiptFile', fileToUpload, fileToUpload.name);

                const response = await fetch('../api/?accion=uploadReceipt', {
                    method: 'POST',
                    body: formData
                });
                // Un 500 de PHP contesta HTML: .json() tiraría
                // "Unexpected token '<'" y el catch se lo mostraba al cliente.
                let result = null;
                try {
                    result = await response.json();
                } catch (parseError) {
                    throw new Error('El servidor no pudo procesar la subida (error ' + response.status + '). Intenta de nuevo en unos minutos.');
                }

                if (response.ok && result.success) {
                    const activeModal = bootstrap.Modal.getInstance(uploadModalElement);
                    if (activeModal) {
                        activeModal.hide();
                    } else {
                        new bootstrap.Modal(uploadModalElement).hide();
                    }
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style = '';

                    setTimeout(() => {
                        window.showInfoModal('¡Éxito!', 'Comprobante subido correctamente.', true, () => {
                            window.reloadHistorial();
                        });
                    }, 300);

                } else {
                    throw new Error(result.error || 'Error al subir.');
                }
            } catch (error) {
                console.error(error);
                // 'Failed to fetch' es el error nativo del navegador cuando la conexión
                // se cae/interrumpe a mitad de la subida (típicamente archivo muy pesado
                // para la conexión, o timeout) — no dice nada útil al usuario tal cual.
                const msg = error.message === 'Failed to fetch'
                    ? 'No se pudo subir el archivo. Verifica tu conexión a internet e intenta de nuevo con una foto más liviana si el problema persiste.'
                    : (error.message || 'Error de conexión.');
                window.showInfoModal('Error', msg, false);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    /**
     * Lee la respuesta de la API tolerando que el servidor devuelva HTML.
     *
     * Un 500 de PHP contesta la página de error completa; hacer .json() sobre
     * eso tira `SyntaxError: Unexpected token '<'` y el catch se lo mostraba
     * crudo al cliente.
     */
    const parseApiResponse = async (res) => {
        let result = null;
        try {
            result = await res.json();
        } catch (e) {
            throw new Error(res.ok
                ? 'El servidor devolvió una respuesta inesperada. Intenta de nuevo.'
                : 'El servidor no pudo procesar la solicitud (error ' + res.status + '). Intenta de nuevo en unos minutos.');
        }
        if (!res.ok || !result || !result.success) {
            throw new Error((result && result.error) || 'No se pudo completar la operación.');
        }
        return result;
    };

    // --- CANCELAR ORDEN ---
    // OJO: cancelar dispara una reversión contable en el backend. Cada click
    // extra era otro POST a cancelTransaction, así que el botón se bloquea
    // apenas se confirma y NO se re-habilita si la operación salió bien (la
    // orden ya no es cancelable; la tabla se recarga sola).
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cancel-btn');
        if (!btn || btn.disabled || btn.dataset.busy === '1') return;
        const txId = btn.getAttribute('data-tx-id');
        if (!(await window.showConfirmModal('Cancelar', `¿Cancelar orden #${txId}?`))) return;

        // Se re-chequea después del await del modal: entre que se abrió la
        // confirmación y se aceptó, pudo haberse disparado otra.
        if (btn.dataset.busy === '1') return;
        btn.dataset.busy = '1';
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch('../api/?accion=cancelTransaction', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transactionId: txId })
            });
            await parseApiResponse(res);
            window.showInfoModal('Cancelada', 'Orden cancelada.', true, window.reloadHistorial);
        } catch (err) {
            console.error(err);
            window.showInfoModal('Error', window.formatNetworkError(err, 'No se pudo cancelar la orden.'), false);
            btn.dataset.busy = '0';
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    // --- EXTENDER PLAZO DE PAGO ("Sí, voy a pagar") ---
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.extend-deadline-btn');
        if (!btn || btn.disabled || btn.dataset.busy === '1') return;
        const txId = btn.getAttribute('data-tx-id');
        if (!(await window.showConfirmModal('Extender plazo', `¿Confirmas que vas a pagar la orden #${txId}? Tendrás 4 horas más para subir el comprobante.`))) return;

        if (btn.dataset.busy === '1') return;
        btn.dataset.busy = '1';
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch('../api/?accion=extendPaymentDeadline', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transactionId: txId })
            });
            const result = await parseApiResponse(res);
            window.showInfoModal('Plazo extendido', result.message || 'Tienes 4 horas más para pagar.', true, window.reloadHistorial);
        } catch (err) {
            console.error(err);
            window.showInfoModal('Error', window.formatNetworkError(err, 'No se pudo extender el plazo.'), false);
            btn.dataset.busy = '0';
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

});
