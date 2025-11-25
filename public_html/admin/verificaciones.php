<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Verificaciones Pendientes';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$usuariosPendientes = $conexion->query("
    SELECT U.UserID, U.PrimerNombre, U.PrimerApellido, U.Email, U.NumeroDocumento, 
           U.DocumentoImagenURL_Frente, U.DocumentoImagenURL_Reverso
    FROM usuarios U
    LEFT JOIN estados_verificacion EV ON U.VerificacionEstadoID = EV.EstadoID
    WHERE EV.NombreEstado = 'Pendiente'
    ORDER BY U.FechaRegistro ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-4">Verificaciones Pendientes</h1>
    <p><a href="<?php echo BASE_URL; ?>/admin/">Volver al panel principal</a></p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-verificaciones">
            <thead class="table-light">
                <tr>
                    <th>ID Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Documento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuariosPendientes)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay usuarios pendientes de verificación.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuariosPendientes as $usuario): ?>
                        <tr id="user-row-<?php echo $usuario['UserID']; ?>">
                            <td><?php echo $usuario['UserID']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['PrimerNombre'] . ' ' . $usuario['PrimerApellido']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['Email']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['NumeroDocumento']); ?></td>
                            <td>
                                <?php ?>
                                <button class="btn btn-sm btn-primary review-btn" data-bs-toggle="modal"
                                    data-bs-target="#verificationModal" data-user-id="<?php echo $usuario['UserID']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($usuario['PrimerNombre'] . ' ' . $usuario['PrimerApellido']); ?>"
                                    data-img-frente="<?php echo htmlspecialchars($usuario['DocumentoImagenURL_Frente']); ?>"
                                    data-img-reverso="<?php echo htmlspecialchars($usuario['DocumentoImagenURL_Reverso']); ?>">
                                    Revisar
                                </button>
                                <?php ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verificationModalLabel">Revisar Verificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="mb-3">Usuario: <span id="modalUserName"></span></h6>
                <div class="row">
                    <div class="col-md-6 text-center">
                        <p><strong>Frente del Documento</strong></p>
                        <img id="modalImgFrente" src="" class="img-fluid border rounded" alt="Frente del documento">
                    </div>
                    <div class="col-md-6 text-center">
                        <p><strong>Reverso del Documento</strong></p>
                        <img id="modalImgReverso" src="" class="img-fluid border rounded" alt="Reverso del documento">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger action-btn" data-action="Rechazado">Rechazar</button>
                <button type="button" class="btn btn-success action-btn" data-action="Verificado">Aprobar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>