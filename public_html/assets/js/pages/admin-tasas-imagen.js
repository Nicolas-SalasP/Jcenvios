document.addEventListener('DOMContentLoaded', () => {
    const tipos = ['whatsapp', 'web'];

    function formatFecha(fecha) {
        if (!fecha) return 'Sin actualizar.';
        const d = new Date(fecha.replace(' ', 'T'));
        if (isNaN(d.getTime())) return 'Sin actualizar.';
        return 'Última actualización: ' + d.toLocaleString('es-CL');
    }

    async function fetchEstado() {
        try {
            const res = await fetch('../api/?accion=getTasasImagenAdmin');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error al cargar el estado de las tasas visuales.');
            (data.tasasImagen || []).forEach(item => renderEstado(item));
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

    function renderEstado(item) {
        const tipo = item.tipoFuente;
        const img = document.getElementById('preview-' + tipo);
        const empty = document.getElementById('preview-empty-' + tipo);
        const fechaEl = document.getElementById('fecha-' + tipo);
        if (!img || !empty || !fechaEl) return;

        if (item.url) {
            img.src = item.url;
            img.classList.remove('d-none');
            empty.classList.add('d-none');
        } else {
            img.classList.add('d-none');
            empty.classList.remove('d-none');
        }
        fechaEl.textContent = formatFecha(item.fechaActualizacion);
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
