<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Verificación de Identidad';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

// Consultar usuarios pendientes con todos sus datos
$sql = "SELECT U.*, TD.NombreDocumento 
        FROM usuarios U 
        LEFT JOIN tipos_documento TD ON U.TipoDocumentoID = TD.TipoDocumentoID
        WHERE U.VerificacionEstadoID = (SELECT EstadoID FROM estados_verificacion WHERE NombreEstado = 'Pendiente')
        ORDER BY U.FechaRegistro ASC";

$result = $conexion->query($sql);
$usuariosPendientes = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-4">Verificaciones Pendientes</h1>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Fecha Registro</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuariosPendientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay solicitudes de verificación
                                    pendientes.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuariosPendientes as $user): ?>
                                <tr>
                                    <td><?php echo $user['UserID']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?></strong><br>
                                        <small
                                            class="text-muted"><?php echo htmlspecialchars($user['NombreDocumento'] . ': ' . $user['NumeroDocumento']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($user['FechaRegistro'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm view-verification-btn" data-bs-toggle="modal"
                                            data-bs-target="#verificationModal" data-user-id="<?php echo $user['UserID']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?>"
                                            /* IMÁGENES */
                                            data-img-frente="<?php echo htmlspecialchars($user['DocumentoImagenURL_Frente']); ?>"
                                            data-img-reverso="<?php echo htmlspecialchars($user['DocumentoImagenURL_Reverso']); ?>"
                                            data-foto-perfil="<?php echo htmlspecialchars($user['FotoPerfilURL']); ?>" /* DATOS
                                            TEXTO */
                                            data-full-name="<?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['SegundoNombre'] . ' ' . $user['PrimerApellido'] . ' ' . $user['SegundoApellido']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['Email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($user['Telefono']); ?>"
                                            data-doc-type="<?php echo htmlspecialchars($user['NombreDocumento']); ?>"
                                            data-doc-num="<?php echo htmlspecialchars($user['NumeroDocumento']); ?>">
                                            Revisar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="verificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Verificando a: <strong id="modalUserName" class="text-primary"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-4 border-end text-center">
                        <h5 class="mb-3 text-secondary">Perfil del Usuario</h5>

                        <div class="mb-3">
                            <img id="verif-profile-pic" src="" class="rounded-circle shadow-sm border"
                                style="width: 150px; height: 150px; object-fit: cover;" alt="Foto Perfil">
                        </div>

                        <ul class="list-group list-group-flush text-start mb-3">
                            <li class="list-group-item">
                                <small class="text-muted">Nombre Completo</small><br>
                                <span id="verif-fullname" class="fw-bold"></span>
                            </li>
                            <li class="list-group-item">
                                <small class="text-muted">Documento ID</small><br>
                                <span id="verif-doc" class="fw-bold text-dark"></span>
                            </li>
                            <li class="list-group-item">
                                <small class="text-muted">Email</small><br>
                                <span id="verif-email"></span>
                            </li>
                            <li class="list-group-item">
                                <small class="text-muted">Teléfono</small><br>
                                <span id="verif-phone"></span>
                            </li>
                        </ul>
                    </div>

                    <div class="col-lg-8">
                        <h5 class="mb-3 text-secondary ps-2">Documentos de Identidad</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header text-center fw-bold bg-primary text-white small">Lado
                                        Frontal</div>
                                    <div class="card-body p-1 bg-dark d-flex align-items-center justify-content-center"
                                        style="min-height: 300px;">
                                        <img id="modalImgFrente" src="" class="img-fluid" style="max-height: 300px;"
                                            alt="Frente">
                                    </div>
                                    <div class="card-footer text-center bg-white">
                                        <a href="#" id="linkFrente" target="_blank"
                                            class="btn btn-sm btn-outline-primary w-100">
                                            <i class="bi bi-zoom-in"></i> Ver Tamaño Completo
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header text-center fw-bold bg-primary text-white small">Lado
                                        Reverso</div>
                                    <div class="card-body p-1 bg-dark d-flex align-items-center justify-content-center"
                                        style="min-height: 300px;">
                                        <img id="modalImgReverso" src="" class="img-fluid" style="max-height: 300px;"
                                            alt="Reverso">
                                    </div>
                                    <div class="card-footer text-center bg-white">
                                        <a href="#" id="linkReverso" target="_blank"
                                            class="btn btn-sm btn-outline-primary w-100">
                                            <i class="bi bi-zoom-in"></i> Ver Tamaño Completo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger action-btn" data-action="Rechazado">
                        <i class="bi bi-x-circle"></i> Rechazar
                    </button>
                    <button type="button" class="btn btn-success action-btn px-4" data-action="Verificado">
                        <i class="bi bi-check-circle"></i> Aprobar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>