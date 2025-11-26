<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Gestión de Usuarios';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

// Paginación
$registrosPorPagina = 50;
$paginaActual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Consulta de conteo
$sqlCount = "SELECT COUNT(*) as total FROM usuarios";
$totalRegistros = $conexion->query($sqlCount)->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Consulta de usuarios (TODOS)
$sql = "SELECT 
            U.*, 
            R.NombreRol,
            TD.NombreDocumento as TipoDocNombre
        FROM usuarios U
        LEFT JOIN roles R ON U.RolID = R.RolID
        LEFT JOIN tipos_documento TD ON U.TipoDocumentoID = TD.TipoDocumentoID
        ORDER BY 
            CASE WHEN U.UserID = 1 THEN 0 ELSE 1 END,
            U.FechaRegistro DESC
        LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$currentAdminId = (int)$_SESSION['user_id'];
?>

<div class="container mt-4">
    <h1 class="mb-4">Gestión de Usuarios</h1>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Contacto</th>
                            <th>Documento</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                            <?php 
                                $isSuperAdmin = ($user['UserID'] == 1);
                                $isMe = ($user['UserID'] == $currentAdminId);
                                $disabledAttr = ($isSuperAdmin || $isMe) ? 'disabled' : '';
                            ?>
                            <tr id="user-row-<?php echo $user['UserID']; ?>" class="<?php echo $isSuperAdmin ? 'table-warning' : ''; ?>">
                                <td><?php echo $user['UserID']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?></strong>
                                    <?php if($isSuperAdmin): ?> <span class="badge bg-warning text-dark">SUPER</span> <?php endif; ?><br>
                                    <small class="text-muted">Reg: <?php echo date("d/m/Y", strtotime($user['FechaRegistro'])); ?></small>
                                </td>
                                <td>
                                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['Email']); ?><br>
                                    <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($user['Telefono'] ?? 'Sin Tlf'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['TipoDocNombre'] ?? 'Doc'); ?>: 
                                    <?php echo htmlspecialchars($user['NumeroDocumento']); ?>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm admin-role-select" 
                                            data-user-id="<?php echo $user['UserID']; ?>" 
                                            style="width: 140px;"
                                            <?php echo $disabledAttr; ?>>
                                        <option value="2" <?php echo $user['RolID'] == 2 ? 'selected' : ''; ?>>P. Natural</option>
                                        <option value="3" <?php echo $user['RolID'] == 3 ? 'selected' : ''; ?>>Empresa</option>
                                        <option value="4" <?php echo $user['RolID'] == 4 ? 'selected' : ''; ?>>Revendedor</option>
                                        <option value="5" <?php echo $user['RolID'] == 5 ? 'selected' : ''; ?>>Operador</option>
                                        <option value="1" <?php echo $user['RolID'] == 1 ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <?php 
                                        $isBlocked = !empty($user['LockoutUntil']) && strtotime($user['LockoutUntil']) > time();
                                        $btnClass = $isBlocked ? 'btn-danger' : 'btn-success';
                                        $btnIcon = $isBlocked ? 'bi-lock-fill' : 'bi-unlock-fill';
                                        $statusText = $isBlocked ? 'blocked' : 'active';
                                    ?>
                                    <button class="btn btn-sm <?php echo $btnClass; ?> block-user-btn" 
                                            data-user-id="<?php echo $user['UserID']; ?>" 
                                            data-current-status="<?php echo $statusText; ?>"
                                            title="<?php echo $isBlocked ? 'Desbloquear' : 'Bloquear'; ?>"
                                            <?php echo $disabledAttr; ?>>
                                        <i class="bi <?php echo $btnIcon; ?>"></i>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info view-user-docs-btn text-white me-1"
                                            title="Ver Documentos"
                                            data-user-id="<?php echo $user['UserID']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?>"
                                            data-img-frente="<?php echo htmlspecialchars($user['DocumentoImagenURL_Frente']); ?>"
                                            data-img-reverso="<?php echo htmlspecialchars($user['DocumentoImagenURL_Reverso']); ?>"
                                            data-foto-perfil="<?php echo htmlspecialchars($user['FotoPerfilURL']); ?>">
                                        <i class="bi bi-file-earmark-person"></i>
                                    </button>

                                    <button class="btn btn-sm btn-primary admin-edit-user-btn me-1" 
                                            title="Editar Datos"
                                            data-user-id="<?php echo $user['UserID']; ?>"
                                            data-nombre1="<?php echo htmlspecialchars($user['PrimerNombre']); ?>"
                                            data-nombre2="<?php echo htmlspecialchars($user['SegundoNombre'] ?? ''); ?>"
                                            data-apellido1="<?php echo htmlspecialchars($user['PrimerApellido']); ?>"
                                            data-apellido2="<?php echo htmlspecialchars($user['SegundoApellido'] ?? ''); ?>"
                                            data-telefono="<?php echo htmlspecialchars($user['Telefono'] ?? ''); ?>"
                                            data-documento="<?php echo htmlspecialchars($user['NumeroDocumento']); ?>"
                                            <?php echo $user['UserID'] == 1 ? 'disabled' : ''; ?>>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <button class="btn btn-sm btn-outline-danger admin-delete-user-btn" 
                                            data-user-id="<?php echo $user['UserID']; ?>" 
                                            title="Eliminar Usuario"
                                            <?php echo $disabledAttr; ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="userDocsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title">Documentos de: <strong id="docsUserName" class="text-primary"></strong></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-lg-4 text-center border-end">
                <h5 class="text-secondary mb-3">Foto de Perfil</h5>
                <img id="docsProfilePic" src="" class="rounded-circle shadow-sm border" style="width: 180px; height: 180px; object-fit: cover;">
            </div>
            <div class="col-lg-8">
                <h5 class="text-secondary mb-3 ps-2">Documento de Identidad</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header fw-bold text-center">Frente</div>
                            <div class="card-body p-1 bg-dark d-flex align-items-center justify-content-center" style="min-height: 250px;">
                                <img id="docsImgFrente" src="" class="img-fluid" style="max-height: 250px;">
                            </div>
                            <div class="card-footer text-center">
                                <a href="#" id="docsLinkFrente" target="_blank" class="btn btn-sm btn-outline-primary">Ver Original</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header fw-bold text-center">Reverso</div>
                            <div class="card-body p-1 bg-dark d-flex align-items-center justify-content-center" style="min-height: 250px;">
                                <img id="docsImgReverso" src="" class="img-fluid" style="max-height: 250px;">
                            </div>
                            <div class="card-footer text-center">
                                <a href="#" id="docsLinkReverso" target="_blank" class="btn btn-sm btn-outline-primary">Ver Original</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form">
                    <input type="hidden" id="edit-user-id" name="userId">
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Primer Nombre</label><input type="text" class="form-control" id="edit-nombre1" name="primerNombre" required></div>
                        <div class="col-6"><label class="form-label">Segundo Nombre</label><input type="text" class="form-control" id="edit-nombre2" name="segundoNombre"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Primer Apellido</label><input type="text" class="form-control" id="edit-apellido1" name="primerApellido" required></div>
                        <div class="col-6"><label class="form-label">Segundo Apellido</label><input type="text" class="form-control" id="edit-apellido2" name="segundoApellido"></div>
                    </div>
                    <div class="mb-3"><label class="form-label fw-bold">Teléfono</label><input type="tel" class="form-control" id="edit-telefono" name="telefono" required></div>
                    <div class="mb-3"><label class="form-label">Número Documento</label><input type="text" class="form-control" id="edit-documento" name="numeroDocumento" required></div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>