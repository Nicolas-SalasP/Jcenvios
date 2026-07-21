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
            tipos.forEach(tipo => {
                const errEl = document.getElementById('error-' + tipo);
                if (errEl) {
                    errEl.textContent = err.message;
                    errEl.classList.remove('d-none');
                }
            });
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
                    <img src="${escapeHtml(item.url)}" alt="Tasa" class="img-fluid rounded mb-2" style="object-fit:cover;max-height:140px;">
                    ${item.titulo ? `<strong class="small mb-1">${escapeHtml(item.titulo)}</strong>` : ''}
                    ${item.descripcion ? `<p class="small text-muted mb-1">${escapeHtml(item.descripcion)}</p>` : ''}
                    <p class="small text-muted mb-2 mt-auto">${formatFecha(item.fechaActualizacion)}</p>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-tasa-imagen" data-id="${item.id}">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            `;
            container.appendChild(col);
        });

        container.querySelectorAll('.btn-delete-tasa-imagen').forEach(btn => {
            btn.addEventListener('click', () => eliminarImagen(btn.dataset.id, tipo));
        });
    }

    async function eliminarImagen(id, tipo) {
        if (!confirm('¿Eliminar esta imagen?')) return;
        try {
            const formData = new FormData();
            formData.append('id', id);
            const res = await fetch('../api/?accion=deleteTasaImagen', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo eliminar la imagen.');
            fetchEstado();
        } catch (err) {
            const errorEl = document.getElementById('error-' + tipo);
            if (errorEl) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
            }
        }
    }

    tipos.forEach(tipo => {
        const form = document.getElementById('form-' + tipo);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorEl = document.getElementById('error-' + tipo);
            errorEl.classList.add('d-none');
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
            } catch (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
            }
        });
    });

    fetchEstado();
});
