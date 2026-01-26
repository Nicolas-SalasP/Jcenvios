<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Cuentas Bancarias (Empresa)';
$pageScript = 'admin-cuentas.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$formasPago = $conexion->query("SELECT FormaPagoID, Nombre FROM formas_pago WHERE Activo = 1")->fetch_all(MYSQLI_ASSOC);
$paises = $conexion->query("SELECT PaisID, NombrePais FROM paises WHERE Activo = 1 ORDER BY NombrePais")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestión de Cuentas Bancarias</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cuentaModal" id="btn-nueva-cuenta">
            <i class="bi bi-plus-circle"></i> Nueva Cuenta
        </button>
    </div>

    <div class="card shadow-sm mb-5 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-arrow-down-circle"></i> Cuentas de Origen (Donde Recibes Dinero)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabla-origen">
                    <thead class="table-light">
                        <tr>
                            <th>País</th>
                            <th>Forma de Pago</th>
                            <th>Banco / Titular</th>
                            <th>Datos de Cuenta</th>
                            <th>Saldo Actual</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Cuentas de Destino (Desde Donde Pagas)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabla-destino">
                    <thead class="table-light">
                        <tr>
                            <th>País</th>
                            <th>Forma de Pago</th>
                            <th>Banco / Titular</th>
                            <th>Datos de Cuenta</th>
                            <th>Saldo Actual</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cuentaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cuentaModalLabel">Nueva Cuenta Bancaria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="cuenta-form" enctype="multipart/form-data">
                    <input type="hidden" id="cuenta-id" name="CuentaAdminID">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Uso de la Cuenta (Rol)</label>
                            <select class="form-select border-primary" id="rol-cuenta-id" name="RolCuentaID" required>
                                <option value="1">Origen (Entrada)</option>
                                <option value="2">Destino (Salida)</option>
                                <option value="3">Mixta (Entrada y Salida)</option>
                            </select>
                            <div class="form-text small">Mixta = Sirve para recibir y pagar.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">País de la Cuenta</label>
                            <select class="form-select" id="pais-id" name="PaisID" required>
                                <?php foreach ($paises as $pais): ?>
                                    <option value="<?= $pais['PaisID'] ?>"><?= $pais['NombrePais'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4" id="container-forma-pago">
                            <label class="form-label">Asignar a Forma de Pago</label>
                            <select class="form-select" id="forma-pago-id" name="FormaPagoID" required>
                                <?php foreach ($formasPago as $fp): ?>
                                    <option value="<?= $fp['FormaPagoID'] ?>"><?= $fp['Nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Banco (o Plataforma)</label>
                            <input type="text" class="form-control" id="banco" name="Banco" required placeholder="Ej: Banco Santander o Zelle">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titular (o Email Zelle)</label>
                            <input type="text" class="form-control" id="titular" name="Titular" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Cuenta</label>
                            <input type="text" class="form-control" id="tipo-cuenta" name="TipoCuenta" required placeholder="Ej: Cta Corriente o Correo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número de Cuenta (o Teléfono)</label>
                            <input type="text" class="form-control" id="numero-cuenta" name="NumeroCuenta" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">RUT / ID / DNI</label>
                            <input type="text" class="form-control" id="rut" name="RUT" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email de Notificación (Opcional)</label>
                            <input type="email" class="form-control" id="email" name="Email">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Color del Título (PDF)</label>
                            <input type="color" class="form-control form-control-color w-100" id="color-hex" name="ColorHex" value="#000000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="activo" name="Activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="container-saldo-inicial">
                            <label class="form-label fw-bold text-success">Saldo Inicial</label>
                            <input type="number" step="0.01" class="form-control" id="saldo-inicial" name="saldoInicial" value="0.00">
                            <div class="form-text small text-danger"><i class="bi bi-exclamation-circle"></i> Solo disponible al crear.</div>
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded">
                        <label for="account-qr" class="form-label fw-bold"><i class="bi bi-qr-code-scan me-2"></i>Imagen Código QR (Opcional)</label>
                        <input type="file" class="form-control" id="account-qr" name="qrFile" accept="image/*">
                        <div class="form-text">Ideal para Nequi, Bancolombia QR o Zelle. Se mostrará en el PDF de instrucciones.</div>
                        
                        <div id="qr-preview-container" class="mt-3 d-none border-top pt-2">
                            <div class="d-flex align-items-center">
                                <img id="qr-preview-img" src="" alt="QR Actual" class="img-thumbnail me-3" style="max-height: 80px;">
                                <div>
                                    <span class="badge bg-success mb-2">QR Cargado Actualmente</span>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="delete-qr" name="deleteQr">
                                        <label class="form-check-label text-danger fw-bold small" for="delete-qr">
                                            Eliminar esta imagen QR
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3" id="container-instrucciones">
                        <label class="form-label">Instrucciones Adicionales (PDF)</label>
                        <textarea class="form-control" id="instrucciones" name="Instrucciones" rows="3" placeholder="Ej: Notas importantes, referencia de pago, etc."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Guardar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="msgModalHeader">
                <h5 class="modal-title" id="msgModalTitle">Aviso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="msgModalBody" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn-msg-cancel">Cerrar</button>
                <button type="button" class="btn btn-primary d-none" id="btn-msg-confirm">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>