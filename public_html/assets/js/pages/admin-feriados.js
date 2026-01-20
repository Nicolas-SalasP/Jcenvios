document.addEventListener('DOMContentLoaded', () => {

    let calendar;
    const calendarEl = document.getElementById('calendar');

    // VARIABLES PARA EL MODAL
    let holidayIdToDelete = null;
    const deleteModalEl = document.getElementById('modalEliminar');
    const deleteModal = new bootstrap.Modal(deleteModalEl);
    const btnConfirmDelete = document.getElementById('btnConfirmarEliminar');

    // DETECTAR MÓVIL
    const isMobile = window.innerWidth < 768;

    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            themeSystem: 'standard',

            initialView: 'dayGridMonth',

            headerToolbar: {
                left: isMobile ? 'prev,next' : 'prev,next today',
                center: 'title',
                right: isMobile ? '' : 'dayGridMonth' 
            },

            buttonText: {
                today: 'Hoy', month: 'Mes',
                prev: '<', next: '>'
            },

            locale: 'es',
            height: 'auto',
            events: fetchHolidaysForCalendar,

            eventClick: function (info) {
                prepareDelete(info.event.id);
            },

            eventContent: function (arg) {
                let contentEl = document.createElement('div');
                contentEl.className = 'text-truncate';
                let isWarning = arg.event.classNames.includes('bg-warning');
                let iconClass = isWarning ? 'bi-info-circle-fill text-dark' : 'bi-lock-fill';
                let textClass = isWarning ? 'text-dark fw-bold' : '';

                contentEl.innerHTML = `<i class="bi ${iconClass} me-1"></i><span class="${textClass}">${arg.event.title}</span>`;
                return { domNodes: [contentEl] };
            }
        });
        calendar.render();
    }

    // --- API & DATOS ---
    async function fetchHolidaysForCalendar(fetchInfo, successCallback, failureCallback) {
        try {
            const res = await fetch('../api/?accion=getHolidays');
            const data = await res.json();

            if (!data.success) throw new Error(data.error);

            updateSideList(data.holidays);

            const events = data.holidays.map(h => {
                const now = new Date();
                const start = new Date(h.FechaInicio);
                const end = new Date(h.FechaFin);        
                let className = 'bg-primary';
                let color = '#3788d8'; 

                if (now > end) {
                    // Pasado
                    className = 'bg-secondary';
                    color = '#6c757d';
                } else if (now >= start && now <= end) {
                    // En curso
                    if (h.BloqueoSistema == 1) {
                        className = 'bg-danger animate__animated animate__pulse animate__infinite';
                        color = '#dc3545';
                    } else {
                        className = 'bg-warning animate__animated animate__pulse animate__infinite';
                        color = '#ffc107';
                    }
                } else {
                    // Futuro
                    if (h.BloqueoSistema == 1) {
                        className = 'bg-danger bg-opacity-75';
                        color = '#dc3545';
                    } else {
                        className = 'bg-warning';
                        color = '#ffc107';
                    }
                }

                return {
                    id: h.HolidayID,
                    title: h.Motivo,
                    start: h.FechaInicio,
                    end: h.FechaFin,
                    classNames: [className],
                    backgroundColor: color,
                    borderColor: color,
                };
            });
            successCallback(events);

        } catch (error) {
            console.error(error);
            failureCallback(error);
        }
    }

    // --- LISTA LATERAL (CLÁSICA / DETALLADA) ---
    function updateSideList(holidays) {
        const listContainer = document.getElementById('lista-feriados-compacta');
        if (!listContainer) return;

        const activeHolidays = holidays.filter(h => new Date(h.FechaFin) >= new Date());

        if (activeHolidays.length === 0) {
            listContainer.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-check-circle display-6 text-success mb-2"></i>
                    <p class="small text-muted mb-0">Sistema 100% Operativo</p>
                </div>`;
            return;
        }

        listContainer.innerHTML = activeHolidays.map(h => {
            const start = new Date(h.FechaInicio);
            const isActive = new Date() >= start;
            let statusBadge = '';
            if (isActive) {
                statusBadge = '<span class="badge bg-danger me-2 animate__animated animate__flash animate__slow animate__infinite">EN CURSO</span>';
            } else {
                statusBadge = '<span class="badge bg-primary me-2">PRÓXIMO</span>';
            }
            const lockIcon = h.BloqueoSistema == 1 
                ? '<i class="bi bi-lock-fill text-danger" title="Bloqueo Total"></i>' 
                : '<i class="bi bi-info-circle-fill text-warning" title="Solo Informativo"></i>';

            return `
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-bottom ${isActive ? 'bg-light' : ''}">
                <div class="overflow-hidden me-2">
                    <div class="d-flex align-items-center mb-1">
                        ${statusBadge}
                        <strong class="text-dark text-truncate me-2">${h.Motivo}</strong>
                        ${lockIcon}
                    </div>
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i> ${formatDate(start)} <br>
                        <i class="bi bi-arrow-right ms-3 me-1"></i> ${formatDate(new Date(h.FechaFin))}
                    </div>
                </div>
                <button class="btn btn-outline-danger btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px;" onclick="prepareDelete(${h.HolidayID})" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        }).join('');
    }

    function formatDate(date) {
        return date.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    // --- LÓGICA DE ELIMINACIÓN CON MODAL ---
    window.prepareDelete = (id) => {
        holidayIdToDelete = id;
        deleteModal.show();
    };

    btnConfirmDelete.addEventListener('click', async () => {
        if (!holidayIdToDelete) return;

        const originalText = btnConfirmDelete.innerHTML;
        btnConfirmDelete.disabled = true;
        btnConfirmDelete.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch('../api/?accion=deleteHoliday', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: holidayIdToDelete })
            });
            const data = await res.json();

            if (data.success) {
                deleteModal.hide();
                calendar.refetchEvents(); 
            } else {
                alert('Error al eliminar: ' + data.error);
            }
        } catch (err) {
            alert('Error de conexión.');
        } finally {
            btnConfirmDelete.disabled = false;
            btnConfirmDelete.innerHTML = originalText;
            holidayIdToDelete = null;
        }
    });

    // --- FORMULARIO GUARDAR ---
    const form = document.getElementById('form-feriado');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Guardando...';
            const isLocked = document.getElementById('holidayLockSwitch').checked ? 1 : 0;

            try {
                const res = await fetch('../api/?accion=addHoliday', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        fechaInicio: form.inicio.value,
                        fechaFin: form.fin.value,
                        motivo: form.motivo.value,
                        bloqueo: isLocked
                    })
                });
                const data = await res.json();

                if (data.success) {
                    form.reset();
                    document.getElementById('holidayLockSwitch').checked = true;
                    document.getElementById('lockStatusText').textContent = 'Bloquear Sistema';
                    document.getElementById('lockStatusText').className = 'text-danger fw-bold d-block';
                    document.getElementById('lockStatusDesc').textContent = 'Nadie podrá acceder.';
                    
                    calendar.refetchEvents();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }
});