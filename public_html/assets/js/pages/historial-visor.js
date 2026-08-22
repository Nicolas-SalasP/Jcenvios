/**
 * Visor de comprobantes ya subidos (navegacion y descarga).
 *
 * Extraido de historial.js (1020 lineas) para bajarle la complejidad.
 * Abre su propio DOMContentLoaded y no comparte scope con el nucleo: para
 * refrescar la tabla usa window.reloadHistorial, que historial.js expone
 * justamente para esto.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- VISOR DE COMPROBANTES ---
    const viewModalElement = document.getElementById('viewComprobanteModal');
    if (viewModalElement) {
        const modalContent = document.getElementById('comprobante-content');
        const modalPlaceholder = document.getElementById('comprobante-placeholder');
        const downloadButton = document.getElementById('download-comprobante');
        const filenameSpan = document.getElementById('comprobante-filename');
        const navigationDiv = document.getElementById('comprobante-navigation');
        const indicatorSpan = document.getElementById('comprobante-indicator');
        const modalLabel = document.querySelector('#viewComprobanteModal .modal-title');
        const prevButton = document.getElementById('prev-comprobante');
        const nextButton = document.getElementById('next-comprobante');

        let comprobantes = [];
        let currentIndex = 0;
        let currentTxId = '';

        const handleShare = async () => {
            if (!comprobantes[currentIndex]) return;
            const current = comprobantes[currentIndex];
            const url = current.url;
            const btnShare = document.getElementById('share-comprobante');
            if (!btnShare) return;

            const originalText = btnShare.innerHTML;
            btnShare.disabled = true;
            btnShare.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

            try {
                const response = await fetch(url);
                // Sin este chequeo, un 403/500 de view_secure_file.php se
                // empaquetaba igual como "comprobante_123.jpg" y el cliente
                // terminaba compartiendo/descargando un archivo corrupto (en
                // realidad la página de error del servidor).
                if (!response.ok) {
                    throw new Error(response.status === 403
                        ? 'No tienes permiso para acceder a este comprobante.'
                        : 'El servidor no pudo entregar el archivo (error ' + response.status + ').');
                }
                const blob = await response.blob();

                const mimeType = current.ext === 'pdf' ? 'application/pdf' : blob.type;
                const fileName = `comprobante_${currentTxId}.${current.ext}`;
                const file = new File([blob], fileName, { type: mimeType });

                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: `Comprobante Orden #${currentTxId}`,
                        text: `Adjunto comprobante de la orden #${currentTxId}.`
                    });
                } else {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = fileName;
                    a.click();
                    URL.revokeObjectURL(a.href);
                    window.showInfoModal('Descarga completa', 'Tu dispositivo no soporta la función de compartir directo. El archivo se ha descargado.', true);
                }
            } catch (error) {
                console.error("Error al compartir:", error);
                if (error.name !== 'AbortError') {
                    window.showInfoModal('Error', window.formatNetworkError(error, 'No se pudo compartir el archivo.'), false);
                }
            } finally {
                btnShare.disabled = false;
                btnShare.innerHTML = originalText;
            }
        };

        const renderVisor = () => {
            if (!comprobantes[currentIndex]) return;
            const current = comprobantes[currentIndex];
            const typeText = current.type === 'user' ? 'Pago Cliente' : 'Comprobante Envío';

            modalContent.innerHTML = '';
            modalPlaceholder.classList.remove('d-none');

            if (modalLabel) modalLabel.textContent = `${typeText} #${currentTxId}`;
            if (filenameSpan) filenameSpan.textContent = current.name || 'documento';
            const finalUrl = current.url;

            if (downloadButton) {
                downloadButton.href = finalUrl;
                downloadButton.download = `comprobante_${currentTxId}.${current.ext}`;
            }
            const currentShareBtn = document.getElementById('share-comprobante');
            if (currentShareBtn) {
                const newBtn = currentShareBtn.cloneNode(true);
                currentShareBtn.parentNode.replaceChild(newBtn, currentShareBtn);
                newBtn.addEventListener('click', handleShare);
            }

            const isPdf = current.url.includes('type=admin') || current.ext === 'pdf';

            let mediaEl;
            if (isPdf) {
                mediaEl = document.createElement('iframe');
                mediaEl.style.width = '100%';
                mediaEl.style.height = '75vh';
                mediaEl.style.border = '0';
                setTimeout(() => modalPlaceholder.classList.add('d-none'), 500);
            } else {
                mediaEl = document.createElement('img');
                mediaEl.style.maxWidth = '100%';
                mediaEl.style.maxHeight = '75vh';
                mediaEl.style.objectFit = 'contain';
                mediaEl.style.display = 'block';
                mediaEl.style.margin = '0 auto';

                mediaEl.onload = () => modalPlaceholder.classList.add('d-none');
                mediaEl.onerror = () => {
                    modalPlaceholder.classList.add('d-none');
                    modalContent.innerHTML = '<div class="text-danger bg-white p-3 rounded shadow mt-3"><i class="bi bi-exclamation-triangle"></i> No se pudo cargar la vista previa. Intenta descargarlo directamente.</div>';
                };
                setTimeout(() => modalPlaceholder.classList.add('d-none'), 3000);
            }

            mediaEl.src = finalUrl;
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
                    ext: btn.dataset.fileExt || 'jpg',
                    name: btn.dataset.fileName
                });
            }
            if (btn.dataset.envioUrl) {
                comprobantes.push({
                    type: 'admin',
                    url: btn.dataset.envioUrl,
                    ext: btn.dataset.fileExt || 'pdf',
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

});
