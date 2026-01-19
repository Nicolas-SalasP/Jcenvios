document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('verification-form');
    const alertDiv = document.getElementById('verification-alert');

    // Elementos de Cámara (Modal o Sección en Pagina)
    const cameraSection = document.getElementById('camera-section');
    const videoEl = document.getElementById('camera-video');
    const canvasEl = document.getElementById('camera-canvas');
    const btnCapture = document.getElementById('btn-capture');
    const btnCancelCamera = document.getElementById('btn-cancel-camera');
    const cameraTitle = document.getElementById('camera-title');

    let stream = null;
    let currentTargetInputId = null;

    // --- LÓGICA DE PREVISUALIZACIÓN (Archivos seleccionados) ---
    const setupPreview = (inputId, imgId) => {
        const input = document.getElementById(inputId);
        const img = document.getElementById(imgId);
        const container = document.getElementById('container-' + imgId);

        if (input && img) {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (evt) => {
                        img.src = evt.target.result;
                        if (container) container.classList.remove('d-none');
                        if (inputId === 'doc-selfie') {
                            document.getElementById('error-selfie')?.classList.add('d-none');
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    };

    setupPreview('docFrente', 'preview-frente');
    setupPreview('docReverso', 'preview-reverso');
    setupPreview('doc-selfie', 'preview-selfie');

    // --- LÓGICA DE CÁMARA ---

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        if (videoEl) videoEl.srcObject = null;
        if (cameraSection) cameraSection.classList.add('d-none');
        currentTargetInputId = null;
    };

    const startCamera = async (targetId) => {
        try {
            currentTargetInputId = targetId;

            // Título dinámico
            let titleText = 'Tomando Foto';
            if (targetId === 'docFrente') titleText = 'Lado Frontal';
            else if (targetId === 'docReverso') titleText = 'Lado Reverso';
            else if (targetId === 'doc-selfie') titleText = 'Selfie en Vivo';

            if (cameraTitle) cameraTitle.textContent = titleText;

            // Configuración de cámara (Frontal para selfie, trasera para docs)
            const constraints = {
                video: {
                    facingMode: (targetId === 'doc-selfie') ? 'user' : { ideal: 'environment' }
                }
            };

            stream = await navigator.mediaDevices.getUserMedia(constraints);
            if (videoEl) {
                videoEl.srcObject = stream;
                cameraSection.classList.remove('d-none');
                cameraSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

        } catch (err) {
            console.error("Error cámara:", err);
            alert("No se pudo iniciar la cámara. Por favor usa el botón 'Seleccionar archivo' o verifica los permisos.");
        }
    };

    const takePhoto = () => {
        if (!stream || !currentTargetInputId) return;

        const targetInput = document.getElementById(currentTargetInputId);
        if (!targetInput) return;

        // Configuración de calidad
        const width = videoEl.videoWidth;
        const height = videoEl.videoHeight;
        canvasEl.width = width;
        canvasEl.height = height;

        const ctx = canvasEl.getContext('2d');

        // Espejo para selfie (opcional, para que se sienta natural)
        if (currentTargetInputId === 'doc-selfie') {
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
        }

        ctx.drawImage(videoEl, 0, 0, width, height);

        // Convertir a archivo JPG
        canvasEl.toBlob((blob) => {
            const timestamp = new Date().getTime();
            const suffix = currentTargetInputId.replace('doc', '').toLowerCase(); // frente, reverso, selfie
            const fileName = `foto_${suffix}_${timestamp}.jpg`;

            const file = new File([blob], fileName, { type: 'image/jpeg' });

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            targetInput.files = dataTransfer.files;

            // Disparar evento change para actualizar la previsualización
            targetInput.dispatchEvent(new Event('change'));

            targetInput.classList.add('is-valid');
            setTimeout(() => targetInput.classList.remove('is-valid'), 2000);

            stopCamera();

        }, 'image/jpeg', 0.85); // Calidad 85%
    };

    document.querySelectorAll('.btn-camera').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Evitar submit si está dentro de form
            e.preventDefault();
            const target = e.currentTarget.getAttribute('data-target');
            startCamera(target);
        });
    });

    if (btnCapture) btnCapture.addEventListener('click', (e) => { e.preventDefault(); takePhoto(); });
    if (btnCancelCamera) btnCancelCamera.addEventListener('click', (e) => { e.preventDefault(); stopCamera(); });

    // --- LÓGICA DE ENVÍO ---

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Validación Manual de Selfie (Extra seguridad visual)
            const selfieInput = document.getElementById('doc-selfie');
            if (selfieInput && selfieInput.files.length === 0) {
                const errDiv = document.getElementById('error-selfie');
                if (errDiv) errDiv.classList.remove('d-none');

                selfieInput.closest('.card').scrollIntoView({ behavior: 'smooth' });
                if (window.showInfoModal) {
                    window.showInfoModal('Falta Información', 'La selfie es obligatoria.', false);
                } else {
                    alert('La selfie es obligatoria.');
                }
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

            if (alertDiv) alertDiv.classList.add('d-none');

            const formData = new FormData(form);

            try {
                const response = await fetch('../api/?accion=uploadVerificationDocs', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (alertDiv) alertDiv.classList.remove('d-none');

                if (result.success) {
                    if (alertDiv) {
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = '¡Documentos enviados! Tu perfil se ha actualizado con tu selfie. Revisaremos tu cuenta pronto.';
                    }
                    form.reset();
                    // Recargar para que se vea la nueva foto de perfil en el header si es posible
                    setTimeout(() => window.location.href = 'index.php', 3000);
                } else {
                    if (alertDiv) {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = 'Error: ' + (result.error || 'Ocurrió un problema.');
                    }
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            } catch (error) {
                if (alertDiv) {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Error de conexión. Inténtalo de nuevo.';
                }
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        });
    }
});