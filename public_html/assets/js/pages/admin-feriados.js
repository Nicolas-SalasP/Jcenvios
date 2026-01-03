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
                let iconClass = arg.event.classNames.includes('bg-danger') ? 'bi-lock-fill' : 'bi-calendar-event';

                contentEl.innerHTML = `<i class="bi ${iconClass} me-1"></i>${arg.event.title}`;
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

                let color = '#3788d8';
                let className = 'bg-primary';

                if (now >= start && now <= end) {
                    color = '#dc3545';
                    className = 'bg-danger animate__animated animate__pulse animate__infinite';
                } else if (now > end) {
                    color = '#6c757d';
                    className = 'bg-secondary';
                }

                return {
                    id: h.HolidayID,
                    title: h.Motivo,
                    start: h.FechaInicio,
                    end: h.FechaFin,
                    backgroundColor: color,
                    borderColor: color,
                    classNames: [className],
                    textColor: '#fff'
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

            return `
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-bottom ${isActive ? 'bg-danger bg-opacity-10' : ''}">
                <div class="overflow-hidden me-2">
                    <div class="d-flex align-items-center mb-1">
                        ${isActive ? '<span class="badge bg-danger me-2">ACTIVO</span>' : '<span class="badge bg-primary me-2">FUTURO</span>'}
                        <strong class="text-dark text-truncate">${h.Motivo}</strong>
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>';

            try {
                const res = await fetch('../api/?accion=addHoliday', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        inicio: form.inicio.value,
                        fin: form.fin.value,
                        motivo: form.motivo.value
                    })
                });
                const data = await res.json();

                if (data.success) {
                    form.reset();
                    calendar.refetchEvents();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Error de conexión.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        });
    }
});