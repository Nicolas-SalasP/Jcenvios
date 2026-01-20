<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; img-src 'self' https: data: blob:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net;");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$pageTitle = 'Calendario de Feriados';
$pageScript = 'admin-feriados.js';

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="container-xl py-4">
    <div class="row g-4">

        <div class="col-lg-4 order-2 order-lg-1">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-calendar-plus me-2"></i>Nuevo Evento</h5>
                </div>
                <div class="card-body">
                    <form id="form-feriado">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">MOTIVO</label>
                            <input type="text" class="form-control" name="motivo"
                                placeholder="Ej: Feriado Bancario / Mantenimiento" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">INICIO</label>
                                <input type="datetime-local" class="form-control" name="inicio" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">FIN</label>
                                <input type="datetime-local" class="form-control" name="fin" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted mb-2">TIPO DE RESTRICCIÓN</label>
                            <div class="form-check form-switch p-3 border rounded bg-light d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" id="holidayLockSwitch" checked
                                    style="transform: scale(1.3); margin-left: 0.1em; margin-right: 1em; cursor: pointer;">
                                <label class="form-check-label flex-grow-1" for="holidayLockSwitch"
                                    style="cursor: pointer;">
                                    <span id="lockStatusText" class="text-danger fw-bold d-block">Bloquear
                                        Sistema</span>
                                    <small class="text-muted" id="lockStatusDesc"
                                        style="font-size: 0.8em; line-height: 1.2;">Nadie podrá acceder.</small>
                                </label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-save me-2"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Próximos Eventos</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="lista-feriados-compacta">
                        <div class="text-center py-4 text-muted small">
                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                            <div>Cargando lista...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 order-1 order-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-2 p-md-4">
                    <div id="calendar" style="min-height: 600px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-trash3-fill me-2"></i>Eliminar Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                </div>
                <h5 class="mb-2 fw-bold">¿Estás seguro?</h5>
                <p class="text-muted mb-0">Esta acción eliminará el feriado o mantenimiento programado.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-light px-4 border" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger px-4 fw-bold" id="btnConfirmarEliminar">Sí,
                    Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const lockSwitch = document.getElementById('holidayLockSwitch');
        const statusText = document.getElementById('lockStatusText');
        const statusDesc = document.getElementById('lockStatusDesc');

        if (lockSwitch) {
            lockSwitch.addEventListener('change', function () {
                if (this.checked) {
                    statusText.textContent = 'Bloquear Sistema';
                    statusText.className = 'text-danger fw-bold d-block';
                    statusDesc.textContent = 'Nadie podrá acceder.';
                } else {
                    statusText.textContent = 'Solo Informativo';
                    statusText.className = 'text-success fw-bold d-block';
                    statusDesc.textContent = 'Muestra aviso, permite operar.';
                }
            });
        }
    });
</script>

<style>
    a.fc-event {
        color: #fff !important;
        text-decoration: none !important;
        cursor: pointer;
    }

    .fc-event {
        border: none !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        border-radius: 4px;
        padding: 2px;
    }

    .fc-event.bg-danger {
        border-left: 4px solid #721c24 !important;
    }

    .fc-event.bg-warning {
        border-left: 4px solid #856404 !important;
        color: #212529 !important;
    }

    .fc-event.bg-warning .fc-event-title {
        color: #212529 !important;
    }

    .fc-toolbar-title {
        font-size: 1.25rem !important;
        font-weight: 700;
        color: #333;
        text-transform: capitalize;
    }

    .fc-button-primary {
        background-color: #fff !important;
        border: 1px solid #dee2e6 !important;
        color: #555 !important;
    }

    .fc-button-primary:hover {
        background-color: #f8f9fa !important;
        color: #0d6efd !important;
        border-color: #0d6efd !important;
    }

    .fc-button-active {
        background-color: #0d6efd !important;
        color: #fff !important;
        border-color: #0d6efd !important;
    }

    @media (max-width: 768px) {
        .fc-header-toolbar {
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .fc-toolbar-chunk {
            display: flex;
            align-items: center;
        }

        .fc-toolbar-title {
            font-size: 1rem !important;
        }

        .fc-button {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.85rem !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>