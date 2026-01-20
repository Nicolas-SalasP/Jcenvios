document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('verification-form');
    const alertDiv = document.getElementById('verification-alert');

    // --- VARIABLES GLOBALES PARA CROPPER ---
    let cropper;
    const cropModalEl = document.getElementById('cropModal');
    if (!cropModalEl) {
        console.error("Falta el modal #cropModal en el HTML. Verifica verificar.php");
        return;
    }
    const cropModal = new bootstrap.Modal(cropModalEl);
    const imageToCrop = document.getElementById('image-to-crop');
    let currentInputId = null;
    const cameraSection = document.getElementById('camera-section');
    const videoEl = document.getElementById('camera-video');
    const canvasEl = document.getElementById('camera-canvas');
    const btnCapture = document.getElementById('btn-capture');
    const btnCancelCamera = document.getElementById('btn-cancel-camera');
    const cameraTitle = document.getElementById('camera-title');
    let stream = null;

    // --- LÓGICA DE EDITOR (CROPPER) ---

    const handleFileSelect = (e) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            if (!file.type.startsWith('image/')) {
                alert('Por favor selecciona un archivo de imagen válido.');
                return;
            }

            currentInputId = e.target.id;
            
            const reader = new FileReader();
            reader.onload = (evt) => {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                imageToCrop.src = evt.target.result;
                cropModal.show();
            };
            reader.readAsDataURL(file);
        }
    };

    ['docFrente', 'docReverso', 'doc-selfie'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('click', () => { input.value = null; });
            input.addEventListener('change', handleFileSelect);
        }
    });
    cropModalEl.addEventListener('shown.bs.modal', () => {
        cropper = new Cropper(imageToCrop, {
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
    });

    // Destruir cropper al cerrar modal para liberar memoria
    cropModalEl.addEventListener('hidden.bs.modal', () => {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        imageToCrop.src = '';
    });

    // Botones de Rotación dentro del Modal
    document.getElementById('btn-rotate-left').addEventListener('click', () => {
        if(cropper) cropper.rotate(-90);
    });
    document.getElementById('btn-rotate-right').addEventListener('click', () => {
        if(cropper) cropper.rotate(90);
    });

    // --- ACCIÓN PRINCIPAL: CONFIRMAR RECORTE ---
    document.getElementById('btn-crop-confirm').addEventListener('click', () => {
        if (!cropper || !currentInputId) return;
        const canvas = cropper.getCroppedCanvas({
            width: 1280,
            height: 1280, 
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) return;

        canvas.toBlob((blob) => {
            const timestamp = new Date().getTime();
            const suffix = currentInputId.replace('doc', '').replace('Frente', '_frente').replace('Reverso', '_reverso').replace('-selfie', '_selfie');
            const fileName = `editado${suffix}_${timestamp}.jpg`;
            
            const newFile = new File([blob], fileName, { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(newFile);
            const input = document.getElementById(currentInputId);
            input.files = dataTransfer.files;
            let imgPreviewId = '';
            if (currentInputId === 'docFrente') imgPreviewId = 'preview-frente';
            else if (currentInputId === 'docReverso') imgPreviewId = 'preview-reverso';
            else if (currentInputId === 'doc-selfie') imgPreviewId = 'preview-selfie';

            const imgPreview = document.getElementById(imgPreviewId);
            const container = document.getElementById('container-' + imgPreviewId);

            if (imgPreview) {
                imgPreview.src = URL.createObjectURL(blob);
                if (container) container.classList.remove('d-none');
                if (currentInputId === 'doc-selfie') {
                    document.getElementById('error-selfie')?.classList.add('d-none');
                }
            }
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 2000);
            cropModal.hide();
        }, 'image/jpeg', 0.85);
    });


    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        if (videoEl) videoEl.srcObject = null;
        if (cameraSection) cameraSection.classList.add('d-none');
    };

    const startCamera = async (targetId) => {
        try {
            currentInputId = targetId;
            
            let titleText = 'Tomando Foto';
            if (targetId === 'docFrente') titleText = 'Lado Frontal';
            else if (targetId === 'docReverso') titleText = 'Lado Reverso';
            else if (targetId === 'doc-selfie') titleText = 'Selfie en Vivo';

            if (cameraTitle) cameraTitle.textContent = titleText;

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
            alert("No se pudo iniciar la cámara. Verifica permisos o usa 'Seleccionar archivo'.");
        }
    };

    const takePhoto = () => {
        if (!stream) return;

        const width = videoEl.videoWidth;
        const height = videoEl.videoHeight;
        canvasEl.width = width;
        canvasEl.height = height;
        const ctx = canvasEl.getContext('2d');

        if (currentInputId === 'doc-selfie') {
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
        }

        ctx.drawImage(videoEl, 0, 0, width, height);
        const dataUrl = canvasEl.toDataURL('image/jpeg');
        stopCamera();
        imageToCrop.src = dataUrl;
        cropModal.show();
    };

    document.querySelectorAll('.btn-camera').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.currentTarget.getAttribute('data-target');
            startCamera(target);
        });
    });

    if (btnCapture) btnCapture.addEventListener('click', (e) => { e.preventDefault(); takePhoto(); });
    if (btnCancelCamera) btnCancelCamera.addEventListener('click', (e) => { e.preventDefault(); stopCamera(); });


    // --- LÓGICA DE ENVÍO DEL FORMULARIO (Se mantiene casi igual) ---
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Validación: La selfie es obligatoria
            const selfieInput = document.getElementById('doc-selfie');
            if (selfieInput && (!selfieInput.files || selfieInput.files.length === 0)) {
                const errDiv = document.getElementById('error-selfie');
                if (errDiv) errDiv.classList.remove('d-none');
                selfieInput.closest('.card').scrollIntoView({ behavior: 'smooth' });
                alert('La selfie es obligatoria.');
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
                        alertDiv.textContent = '¡Documentos enviados! Redirigiendo...';
                    }
                    setTimeout(() => window.location.href = 'index.php', 2000);
                } else {
                    if (alertDiv) {
                        alertDiv.className = 'alert alert-danger';
                        alertDiv.textContent = 'Error: ' + (result.error || 'Ocurrió un problema.');
                    }
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            } catch (error) {
                console.error(error);
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