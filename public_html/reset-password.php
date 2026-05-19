<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';
$token = $_GET['token'] ?? '';

$stmt = $conexion->prepare("SELECT * FROM passwordresets WHERE Token = ? AND Used = FALSE AND ExpiresAt > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();
$tokenValido = $resultado->num_rows === 1;
$stmt->close();

$pageTitle = 'Crear Nueva Contraseña';
$pageScript = 'reset-password.js';
require_once __DIR__ . '/../remesas_private/src/templates/header.php';
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center">Crear Nueva Contraseña</h3>
                    <?php if ($tokenValido): ?>
                        <form id="reset-password-form">
                            <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
                            <div class="mb-3">
                                <label for="new-password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="new-password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm-password" class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" id="confirm-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Contraseña</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">El enlace de restablecimiento no es válido o ha expirado. Por favor, solicita uno nuevo.</div>
                    <?php endif; ?>
                    <div id="feedback-message" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
require_once __DIR__ . '/../remesas_private/src/templates/footer.php';
?>