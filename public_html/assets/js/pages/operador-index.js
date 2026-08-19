document.addEventListener('DOMContentLoaded', () => {

    // --- LÓGICA MODAL MOTIVO PAUSA ---
    const pauseModal = document.getElementById('viewPauseReasonModal');
    if (pauseModal) {
        pauseModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const reason = button.getAttribute('data-reason');
            const modalBodyText = pauseModal.querySelector('#pause-reason-text');
            modalBodyText.textContent = reason;
        });
    }

    // --- LÓGICA MEJORADA VISOR COMPROBANTE ---
    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.view-comprobante-btn-admin');
        if (btn) {
            e.preventDefault();

            const url = btn.dataset.comprobanteUrl;
            const imgEl = document.getElementById('comprobante-img-full');
            const pdfEl = document.getElementById('comprobante-pdf-full');
            const placeholder = document.getElementById('comprobante-placeholder');
            const downloadBtn = document.getElementById('download-comprobante-btn');

            imgEl.classList.add('d-none');
            pdfEl.classList.add('d-none');
            placeholder.classList.remove('d-none');
            imgEl.src = '';
            pdfEl.src = '';

            // Detectar extensión real
            let extension = '';
            if (url.includes('?')) {
                const urlParams = new URLSearchParams(url.split('?')[1]);
                const fileParam = urlParams.get('file');
                if (fileParam) {
                    extension = fileParam.split('.').pop().toLowerCase();
                }
            } else {
                extension = url.split('.').pop().toLowerCase();
            }

            setTimeout(() => {
                placeholder.classList.add('d-none');

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    imgEl.src = url;
                    imgEl.classList.remove('d-none');
                } else if (extension === 'pdf') {
                    pdfEl.src = url;
                    pdfEl.classList.remove('d-none');
                } else {
                    imgEl.src = url;
                    imgEl.classList.remove('d-none');
                }

                if (downloadBtn) {
                    downloadBtn.href = url;
                    downloadBtn.classList.remove('d-none');
                }
            }, 500);
        }
    });
});
