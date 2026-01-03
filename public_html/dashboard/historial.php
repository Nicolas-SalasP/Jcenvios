<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$pageTitle = 'Mi Historial';
$pageScript = 'historial.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4">
    <div class="card p-4 p-md-5 shadow-sm">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Mi Historial de Transacciones</h1>
            <a href="index.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Envío</a>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Buscar por ID, nombre del beneficiario o monto...">
                </div>
            </div>
            <div class="col-md-4">
                <select id="statusFilter" class="form-select">
                    <option value="all">Todos los estados</option>
                    <option value="1">Pendiente de Pago</option>
                    <option value="2">En Verificación</option>
                    <option value="3">En Proceso</option>
                    <option value="6">Pausado (Requiere Acción)</option>
                    <option value="4">Exitoso</option>
                    <option value="5">Cancelado</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle caption-top" id="historial-table">
                <caption>Mostrando tus transacciones más recientes primero.</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Beneficiario</th>
                        <th scope="col">Monto Enviado</th>
                        <th scope="col">Monto Recibido</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody id="historial-body" class="table-group-divider">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Cargando historial...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="no-results" class="alert alert-light text-center d-none mt-3">
            <i class="bi bi-search me-2"></i> No se encontraron resultados con los filtros actuales.
        </div>
    </div>
</div>

<div class="modal fade" id="viewReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Atención Requerida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold mb-2">Su orden fue pausada por el siguiente motivo:</p>
                <div class="alert alert-light border border-warning">
                    <p class="mb-0" id="reason-content-text" style="white-space: pre-wrap;">...</p>
                </div>
                <p class="small text-muted mb-0">
                    Por favor, corrija el error o contacte a soporte. Luego presione el botón 
                    <strong>"Corregido"</strong> para notificar al operador.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Transacción <strong id="modal-tx-id">#</strong></p>

                <div id="camera-section" class="d-none mb-3 text-center bg-dark rounded p-2 position-relative">
                    <video id="camera-video" class="w-100 rounded" autoplay playsinline style="max-height: 300px; object-fit: contain;"></video>
                    <canvas id="camera-canvas" class="d-none"></canvas>
                    <div class="mt-2">
                        <button type="button" id="btn-capture" class="btn btn-light rounded-circle p-3 shadow"><i class="bi bi-circle-fill fs-4 text-danger"></i></button>
                        <button type="button" id="btn-cancel-camera" class="btn btn-outline-light btn-sm ms-2 position-absolute top-0 end-0 m-2"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                <form id="upload-receipt-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="receiptFile" class="form-label">Selecciona el archivo</label>
                        <div class="d-grid gap-2 mb-3" id="camera-toggle-container">
                            <button type="button" id="btn-start-camera" class="btn btn-outline-primary"><i class="bi bi-camera-fill me-2"></i> Tomar foto</button>
                        </div>
                        <input class="form-control" type="file" id="receiptFile" name="receiptFile" accept="image/png, image/jpeg, application/pdf" capture="environment" required>
                    </div>
                    <input type="hidden" id="transactionIdField" name="transactionId">
                    <div class="d-grid"><button type="submit" class="btn btn-primary">Subir Archivo</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resumeOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Notificar Corrección</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Si ya corregiste los datos, envía un mensaje para continuar.</p>
                <form id="resume-order-form">
                    <input type="hidden" id="resume-tx-id" name="txId">
                    <div class="mb-3">
                        <label for="resume-message" class="form-label">Mensaje para el operador</label>
                        <textarea class="form-control" id="resume-message" name="mensaje" rows="3" required placeholder="Ej: Ya corregí el número de cuenta."></textarea>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary">Enviar</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header py-2 bg-light">
                <h5 class="modal-title fs-6">Visor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark d-flex align-items-center justify-content-center">
                <div id="comprobante-placeholder" class="text-white">Cargando...</div>
                <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center"></div>
            </div>
            <div class="modal-footer py-1 bg-light justify-content-between">
                <span id="comprobante-filename" class="text-muted small"></span>
                <a href="#" id="download-comprobante" class="btn btn-sm btn-primary">Descargar</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>