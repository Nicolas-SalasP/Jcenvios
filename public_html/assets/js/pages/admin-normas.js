document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('contenidoNormas');
    const fechaEl = document.getElementById('fechaActualizacion');
    const form = document.getElementById('formNormas');
    const errorEl = document.getElementById('errorNormas');
    const successEl = document.getElementById('successNormas');
    const btn = document.getElementById('btnGuardarNormas');

    function formatFecha(fecha) {
        if (!fecha) return 'Sin actualizar todavía.';
        const d = new Date(fecha.replace(' ', 'T'));
        if (isNaN(d.getTime())) return 'Sin actualizar todavía.';
        return 'Última actualización: ' + d.toLocaleString('es-CL');
    }

    async function cargarNormas() {
        try {
            const res = await fetch('../api/?accion=getNormasAdmin');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error al cargar las Normas.');
            textarea.value = data.contenido || '';
            fechaEl.textContent = formatFecha(data.fechaActualizacion);
        } catch (err) {
            fechaEl.textContent = '';
            errorEl.textContent = err.message;
            errorEl.classList.remove('d-none');
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorEl.classList.add('d-none');
        successEl.classList.add('d-none');
        btn.disabled = true;

        try {
            const formData = new FormData(form);
            const res = await fetch('../api/?accion=saveNormas', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudieron guardar las Normas.');
            successEl.textContent = data.message || 'Normas actualizadas correctamente.';
            successEl.classList.remove('d-none');
            cargarNormas();
        } catch (err) {
            errorEl.textContent = err.message;
            errorEl.classList.remove('d-none');
        } finally {
            btn.disabled = false;
        }
    });

    cargarNormas();
});
