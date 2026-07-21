document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('tutorialesContainer');
    const loadingEl = document.getElementById('tutorialesLoading');

    const modalTutorialEl = document.getElementById('modalTutorial');
    const modalTutorial = new bootstrap.Modal(modalTutorialEl);
    const form = document.getElementById('formTutorial');
    const formError = document.getElementById('tutorialFormError');

    const modalEliminarEl = document.getElementById('modalEliminarTutorial');
    const modalEliminar = new bootstrap.Modal(modalEliminarEl);
    let tutorialIdToDelete = null;

    document.getElementById('btnNuevoTutorial').addEventListener('click', () => openModal());

    document.querySelectorAll('input[name="tipoFuente"]').forEach(radio => {
        radio.addEventListener('change', toggleFuenteFields);
    });

    function toggleFuenteFields() {
        const esArchivo = document.getElementById('tipoArchivo').checked;
        document.getElementById('grupoUrlExterna').classList.toggle('d-none', esArchivo);
        document.getElementById('grupoArchivoVideo').classList.toggle('d-none', !esArchivo);
    }

    function openModal(tutorial = null) {
        form.reset();
        formError.classList.add('d-none');
        document.getElementById('archivoActualInfo').textContent = '';

        if (tutorial) {
            document.getElementById('modalTutorialTitle').textContent = 'Editar Tutorial';
            document.getElementById('tutorialId').value = tutorial.TutorialID;
            document.getElementById('tutorialTitulo').value = tutorial.Titulo;
            document.getElementById('tutorialDescripcion').value = tutorial.Descripcion || '';

            if (tutorial.TipoFuente === 'archivo') {
                document.getElementById('tipoArchivo').checked = true;
                document.getElementById('archivoActualInfo').textContent = tutorial.RutaArchivo
                    ? 'Archivo actual: ' + tutorial.RutaArchivo.split('/').pop() + ' (deja vacío para mantenerlo)'
                    : '';
            } else {
                document.getElementById('tipoUrl').checked = true;
                document.getElementById('tutorialUrlExterna').value = tutorial.URLExterna || '';
            }
        } else {
            document.getElementById('modalTutorialTitle').textContent = 'Nuevo Tutorial';
            document.getElementById('tutorialId').value = '';
            document.getElementById('tipoUrl').checked = true;
        }
        toggleFuenteFields();
        modalTutorial.show();
    }

    async function fetchTutoriales() {
        loadingEl.classList.remove('d-none');
        try {
            const res = await fetch('../api/?accion=getTutorialesAdmin');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error al cargar tutoriales.');
            renderTutoriales(data.tutoriales || []);
        } catch (err) {
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger">${err.message}</div></div>`;
        }
    }

    function renderTutoriales(tutoriales) {
        container.querySelectorAll('.tutorial-card-col').forEach(el => el.remove());
        loadingEl.classList.add('d-none');

        if (tutoriales.length === 0) {
            container.insertAdjacentHTML('beforeend', `
                <div class="col-12 tutorial-card-col">
                    <div class="alert alert-light border text-center text-muted">
                        No hay tutoriales cargados todavía.
                    </div>
                </div>`);
            return;
        }

        tutoriales.forEach(t => {
            const activo = Number(t.Activo) === 1;
            const badgeClass = activo ? 'success' : 'secondary';
            const badgeText = activo ? 'Activo' : 'Inactivo';
            const fuenteIcon = t.TipoFuente === 'archivo' ? 'bi-file-earmark-play' : 'bi-link-45deg';
            const fuenteText = t.TipoFuente === 'archivo' ? 'Archivo subido' : 'URL externa';

            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 tutorial-card-col';
            col.innerHTML = `
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-${badgeClass} bg-opacity-10 text-${badgeClass} border border-${badgeClass} border-opacity-25">${badgeText}</span>
                            <span class="badge bg-light text-dark border"><i class="bi ${fuenteIcon} me-1"></i>${fuenteText}</span>
                        </div>
                        <h5 class="fw-bold mb-1">${escapeHtml(t.Titulo)}</h5>
                        <p class="text-muted small flex-grow-1">${escapeHtml(t.Descripcion || '')}</p>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button class="btn btn-sm btn-outline-primary btn-editar" data-id="${t.TutorialID}"><i class="bi bi-pencil"></i> Editar</button>
                            <button class="btn btn-sm btn-outline-${activo ? 'warning' : 'success'} btn-toggle" data-id="${t.TutorialID}">
                                <i class="bi bi-${activo ? 'eye-slash' : 'eye'}"></i> ${activo ? 'Desactivar' : 'Activar'}
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-eliminar" data-id="${t.TutorialID}"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            container.appendChild(col);

            col.querySelector('.btn-editar').addEventListener('click', () => openModal(t));
            col.querySelector('.btn-toggle').addEventListener('click', () => toggleStatus(t.TutorialID));
            col.querySelector('.btn-eliminar').addEventListener('click', () => {
                tutorialIdToDelete = t.TutorialID;
                modalEliminar.show();
            });
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    async function toggleStatus(id) {
        try {
            const res = await fetch('../api/?accion=toggleTutorialStatus', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo cambiar el estado.');
            fetchTutoriales();
        } catch (err) {
            window.showInfoModal('Error', err.message, false);
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        formError.classList.add('d-none');
        const btn = document.getElementById('btnGuardarTutorial');
        btn.disabled = true;

        try {
            const formData = new FormData(form);
            const res = await fetch('../api/?accion=saveTutorial', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo guardar el tutorial.');
            modalTutorial.hide();
            fetchTutoriales();
        } catch (err) {
            formError.textContent = err.message;
            formError.classList.remove('d-none');
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('btnConfirmarEliminarTutorial').addEventListener('click', async () => {
        if (!tutorialIdToDelete) return;
        try {
            const res = await fetch('../api/?accion=deleteTutorial', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: tutorialIdToDelete })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'No se pudo eliminar el tutorial.');
            modalEliminar.hide();
            fetchTutoriales();
        } catch (err) {
            window.showInfoModal('Error', err.message, false);
        } finally {
            tutorialIdToDelete = null;
        }
    });

    fetchTutoriales();
});
