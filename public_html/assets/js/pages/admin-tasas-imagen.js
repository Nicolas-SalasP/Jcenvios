document.addEventListener('DOMContentLoaded', () => {
    const tipos = ['whatsapp', 'web'];

    function formatFecha(fecha) {
        if (!fecha) return 'Sin actualizar.';
        const d = new Date(fecha.replace(' ', 'T'));
        if (isNaN(d.getTime())) return 'Sin actualizar.';
        return d.toLocaleString('es-CL');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    async function fetchEstado() {
        try {
            const res = await fetch('../api/?accion=getTasasImagenAdmin');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error al cargar el estado de las tasas visuales.');

            tipos.forEach(tipo => {
                const items = (data.tasasImagen || []).filter(i => i.tipoFuente === tipo);
                renderGaleria(tipo, items);
            });
        } catch (err) {
            window.showInfoModal('Error', err.message, false);
        }
    }

    function renderGaleria(tipo, items) {
        const container = document.getElementById('galeria-' + tipo);
        const emptyEl = document.getElementById('galeria-empty-' + tipo);
        if (!container || !emptyEl) return;

        container.innerHTML = '';
        emptyEl.classList.toggle('d-none', items.length > 0);

        items.forEach(item => {
            const col = document.createElement('div');
            col.className = 'col-6';
            col.innerHTML = `
                <div class="border rounded p-2 h-100 d-flex flex-column">
                    <img src="${escapeHtml(item.url)}" alt="Tasa" class="img-fluid rounded mb-2 tasa-imagen-preview-thumb"
                         data-id="${item.id}" data-titulo="${escapeHtml(item.titulo || '')}"
                         style="object-fit:cover;max-height:140px;cursor:pointer;">
                    ${item.titulo ? `<strong class="small mb-1">${escapeHtml(item.titulo)}</strong>` : ''}
                    ${item.descripcion ? `<p class="small text-muted mb-1">${escapeHtml(item.descripcion)}</p>` : ''}
                    <p class="small text-muted mb-2 mt-auto">${formatFecha(item.fechaActualizacion)}</p>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-tasa-imagen" data-id="${item.id}" data-url="${escapeHtml(item.url)}" title="Editar imagen (recortar)">
                            <i class="bi bi-crop"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-replace-tasa-imagen" data-id="${item.id}" data-titulo="${escapeHtml(item.titulo || '')}" data-descripcion="${escapeHtml(item.descripcion || '')}" title="Reemplazar imagen, título y descripción">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-tasa-imagen ms-auto" data-id="${item.id}" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });

        container.querySelectorAll('.tasa-imagen-preview-thumb').forEach(img => {
            img.addEventListener('click', () => abrirPreview(img.src, img.dataset.titulo));
        });
        container.querySelectorAll('.btn-delete-tasa-imagen').forEach(btn => {
            btn.addEventListener('click', () => eliminarImagen(btn.dataset.id, tipo));
        });
        container.querySelectorAll('.btn-edit-tasa-imagen').forEach(btn => {
            btn.addEventListener('click', () => abrirEditar(btn.dataset.id, btn.dataset.url));
        });
        container.querySelectorAll('.btn-replace-tasa-imagen').forEach(btn => {
            btn.addEventListener('click', () => abrirReemplazar(btn.dataset.id, btn.dataset.titulo, btn.dataset.descripcion));
        });
    }

    async function eliminarImagen(id, tipo) {
        const confirmado = await window.showConfirmModal('Eliminar imagen', '¿Eliminar esta imagen? Esta acción no se puede deshacer.');
        if (!confirmado) return;

        try {
            const formData = new FormData();
            formData.append('id', id);
            const res = await fetch('../api/?accion=deleteTasaImagen', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo eliminar la imagen.');
            fetchEstado();
        } catch (err) {
            window.showInfoModal('Error', err.message, false);
        }
    }

    tipos.forEach(tipo => {
        const form = document.getElementById('form-' + tipo);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                const formData = new FormData(form);
                const res = await fetch('../api/?accion=saveTasaImagen', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'No se pudo subir la imagen.');
                form.reset();
                fetchEstado();
                window.showInfoModal('Listo', 'Imagen subida correctamente.', true);
            } catch (err) {
                window.showInfoModal('Error', err.message, false);
            } finally {
                btn.disabled = false;
            }
        });
    });

    // --- Preview ---
    const previewModalEl = document.getElementById('previewTasaImagenModal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    function abrirPreview(url, titulo) {
        if (!previewModal) return;
        document.getElementById('previewTasaImagenImg').src = url;
        document.getElementById('previewTasaImagenTitulo').textContent = titulo || 'Vista previa';
        previewModal.show();
    }

    // --- Editar (recortar/redimensionar) ---
    const editModalEl = document.getElementById('editTasaImagenModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editImg = document.getElementById('editTasaImagenImg');
    const editIdInput = document.getElementById('editTasaImagenId');
    const editError = document.getElementById('editTasaImagenError');
    let editCropper = null;

    function abrirEditar(id, url) {
        if (!editModal) return;
        editIdInput.value = id;
        editImg.src = url;
        editError.classList.add('d-none');
        editModal.show();
    }

    if (editModalEl) {
        editModalEl.addEventListener('shown.bs.modal', () => {
            if (editCropper) editCropper.destroy();
            editCropper = new Cropper(editImg, {
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                checkOrientation: true,
            });
        });
        editModalEl.addEventListener('hidden.bs.modal', () => {
            if (editCropper) { editCropper.destroy(); editCropper = null; }
            editImg.src = '';
        });
    }

    const rotateLeftBtn = document.getElementById('editTasaImagenRotateLeft');
    const rotateRightBtn = document.getElementById('editTasaImagenRotateRight');
    if (rotateLeftBtn) rotateLeftBtn.addEventListener('click', () => editCropper && editCropper.rotate(-90));
    if (rotateRightBtn) rotateRightBtn.addEventListener('click', () => editCropper && editCropper.rotate(90));

    const editConfirmBtn = document.getElementById('editTasaImagenConfirm');
    if (editConfirmBtn) {
        editConfirmBtn.addEventListener('click', () => {
            if (!editCropper) return;
            const originalText = editConfirmBtn.innerHTML;
            editConfirmBtn.disabled = true;
            editConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

            editCropper.getCroppedCanvas({
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            }).toBlob(async (blob) => {
                try {
                    const formData = new FormData();
                    formData.append('id', editIdInput.value);
                    formData.append('imagen', blob, 'editada.jpg');
                    const res = await fetch('../api/?accion=editTasaImagenArchivo', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.error || 'No se pudo editar la imagen.');
                    editModal.hide();
                    fetchEstado();
                    window.showInfoModal('Listo', 'Imagen editada correctamente.', true);
                } catch (err) {
                    editError.textContent = err.message;
                    editError.classList.remove('d-none');
                } finally {
                    editConfirmBtn.disabled = false;
                    editConfirmBtn.innerHTML = originalText;
                }
            }, 'image/jpeg', 0.92);
        });
    }

    // --- Reemplazar (imagen + titulo + descripcion) ---
    const replaceModalEl = document.getElementById('replaceTasaImagenModal');
    const replaceModal = replaceModalEl ? new bootstrap.Modal(replaceModalEl) : null;
    const replaceForm = document.getElementById('form-replace-tasa-imagen');
    const replaceError = document.getElementById('replaceTasaImagenError');

    function abrirReemplazar(id, titulo, descripcion) {
        if (!replaceModal) return;
        replaceForm.reset();
        document.getElementById('replaceTasaImagenId').value = id;
        document.getElementById('replaceTasaImagenTitulo').value = titulo || '';
        document.getElementById('replaceTasaImagenDescripcion').value = descripcion || '';
        replaceError.classList.add('d-none');
        replaceModal.show();
    }

    if (replaceForm) {
        replaceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            replaceError.classList.add('d-none');
            const btn = replaceForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                const formData = new FormData(replaceForm);
                const res = await fetch('../api/?accion=replaceTasaImagen', { method: 'POST', body: formData });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'No se pudo reemplazar la imagen.');
                replaceModal.hide();
                fetchEstado();
                window.showInfoModal('Listo', 'Imagen reemplazada correctamente.', true);
            } catch (err) {
                replaceError.textContent = err.message;
                replaceError.classList.remove('d-none');
            } finally {
                btn.disabled = false;
            }
        });
    }

    fetchEstado();
});
