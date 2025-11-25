document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('verification-form');
    const alertDiv = document.getElementById('verification-alert');

    // Elementos de Cámara
    const cameraSection = document.getElementById('camera-section');
    const videoEl = document.getElementById('camera-video');
    const canvasEl = document.getElementById('camera-canvas');
    const btnCapture = document.getElementById('btn-capture');
    const btnCancelCamera = document.getElementById('btn-cancel-camera');
    const cameraTitle = document.getElementById('camera-title');

    let stream = null;
    let currentTargetInputId = null;

    // --- LÓGICA DE CÁMARA ---

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        videoEl.srcObject = null;
        cameraSection.classList.add('d-none');
        currentTargetInputId = null;
    };

    const startCamera = async (targetId) => {
        try {
            currentTargetInputId = targetId;
            cameraTitle.textContent = targetId === 'docFrente' ? 'Tomando Foto: Lado Frontal' : 'Tomando Foto: Lado Reverso';

            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' }
                }
            });
            videoEl.srcObject = stream;
            cameraSection.classList.remove('d-none');

            cameraSection.scrollIntoView({ behavior: 'smooth', block: 'center' });

        } catch (err) {
            console.error("Error cámara:", err);
            alert("No se pudo iniciar la cámara. Por favor usa el botón 'Seleccionar archivo'.");
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
        ctx.drawImage(videoEl, 0, 0, width, height);

        // Convertir a archivo JPG
        canvasEl.toBlob((blob) => {
            const timestamp = new Date().getTime();
            const suffix = currentTargetInputId === 'docFrente' ? 'frente' : 'reverso';
            const fileName = `foto_${suffix}_${timestamp}.jpg`;

            const file = new File([blob], fileName, { type: 'image/jpeg' });

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            targetInput.files = dataTransfer.files;

            targetInput.classList.add('is-valid');
            setTimeout(() => targetInput.classList.remove('is-valid'), 2000);

            stopCamera();

        }, 'image/jpeg', 0.80);
    };

    document.querySelectorAll('.btn-camera').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const target = e.currentTarget.getAttribute('data-target');
            startCamera(target);
        });
    });

    if (btnCapture) btnCapture.addEventListener('click', takePhoto);
    if (btnCancelCamera) btnCancelCamera.addEventListener('click', stopCamera);

    // --- LÓGICA DE ENVÍO ---

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Enviando...';
            alertDiv.classList.add('d-none');

            try {
                const response = await fetch('../api/?accion=uploadVerificationDocs', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                alertDiv.classList.remove('d-none');
                if (result.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = '¡Documentos enviados! Serás notificado cuando tu cuenta sea verificada. Serás redirigido en 5 segundos.';
                    form.reset();
                    setTimeout(() => window.location.href = 'index.php', 5000);
                } else {
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = 'Error: ' + (result.error || 'Ocurrió un problema.');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Enviar para Verificación';
                }
            } catch (error) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'Error de conexión. Inténtalo de nuevo.';
                submitButton.disabled = false;
                submitButton.textContent = 'Enviar para Verificación';
            }
        });
    }
});