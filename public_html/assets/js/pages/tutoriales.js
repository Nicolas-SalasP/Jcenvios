document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('tutorialesClienteContainer');
    const loadingEl = document.getElementById('tutorialesClienteLoading');
    const baseUrl = document.body.dataset.baseUrl || '';

    const modalEl = document.getElementById('modalReproductor');
    const modal = new bootstrap.Modal(modalEl);
    const playerContainer = document.getElementById('reproductorContainer');
    const modalTitle = document.getElementById('modalReproductorTitle');
    const modalDesc = document.getElementById('modalReproductorDesc');

    modalEl.addEventListener('hidden.bs.modal', () => {
        playerContainer.innerHTML = '';
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function toEmbedUrl(url) {
        try {
            const u = new URL(url);
            const host = u.hostname.replace('www.', '').replace('m.', '');
            if (host === 'youtu.be') {
                return `https://www.youtube.com/embed/${u.pathname.slice(1)}`;
            }
            if (host === 'youtube.com') {
                const videoId = u.searchParams.get('v');
                if (videoId) return `https://www.youtube.com/embed/${videoId}`;
                if (u.pathname.startsWith('/embed/')) return url;
            }
            if (host === 'vimeo.com' || host === 'player.vimeo.com') {
                const parts = u.pathname.split('/').filter(Boolean);
                const videoId = parts[parts.length - 1];
                return `https://player.vimeo.com/video/${videoId}`;
            }
        } catch (e) {
            // ignore, fallback abajo
        }
        return url;
    }

    function openPlayer(t) {
        modalTitle.textContent = t.Titulo;
        modalDesc.textContent = t.Descripcion || '';
        playerContainer.innerHTML = '';

        if (t.TipoFuente === 'archivo') {
            const video = document.createElement('video');
            video.controls = true;
            video.className = 'w-100 h-100';
            video.src = `${baseUrl}/tutoriales_stream.php?id=${t.TutorialID}`;
            playerContainer.appendChild(video);
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = toEmbedUrl(t.URLExterna);
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;
            playerContainer.appendChild(iframe);
        }
        modal.show();
    }

    function renderTutoriales(tutoriales) {
        loadingEl.classList.add('d-none');
        container.querySelectorAll('.tutorial-card-col').forEach(el => el.remove());

        if (tutoriales.length === 0) {
            container.insertAdjacentHTML('beforeend', `
                <div class="col-12 tutorial-card-col">
                    <div class="alert alert-light border text-center text-muted">
                        Todavía no hay tutoriales disponibles.
                    </div>
                </div>`);
            return;
        }

        tutoriales.forEach(t => {
            const fuenteIcon = t.TipoFuente === 'archivo' ? 'bi-file-earmark-play' : 'bi-youtube';
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 tutorial-card-col';
            col.innerHTML = `
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 mb-3" style="width:56px;">
                            <i class="bi ${fuenteIcon} fs-4 text-primary"></i>
                        </div>
                        <h5 class="fw-bold mb-1">${escapeHtml(t.Titulo)}</h5>
                        <p class="text-muted small flex-grow-1">${escapeHtml(t.Descripcion || '')}</p>
                        <button class="btn btn-primary w-100 btn-ver-tutorial">
                            <i class="bi bi-play-circle me-2"></i> Ver Video
                        </button>
                    </div>
                </div>`;
            col.querySelector('.btn-ver-tutorial').addEventListener('click', () => openPlayer(t));
            container.appendChild(col);
        });
    }

    async function fetchTutoriales() {
        try {
            const res = await fetch(`${baseUrl}/api/?accion=getTutorialesActivos`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error al cargar tutoriales.');
            renderTutoriales(data.tutoriales || []);
        } catch (err) {
            loadingEl.classList.add('d-none');
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger">${err.message}</div></div>`;
        }
    }

    fetchTutoriales();
});
