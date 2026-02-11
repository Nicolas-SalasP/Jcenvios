<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

// Verificación de Seguridad
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso denegado.");
}

// Configuración de Paginación y Filtros
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$rolFiltro = isset($_GET['rol']) ? $_GET['rol'] : '';
$registrosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Construcción de la Consulta
$conditions = "U.Eliminado = 0 AND U.VerificacionEstadoID = 3";
$params = [];
$types = "";

if ($busqueda !== '') {
    $conditions .= " AND (U.PrimerNombre LIKE ? OR U.PrimerApellido LIKE ? OR U.Email LIKE ? OR U.NumeroDocumento LIKE ?)";
    $term = "%$busqueda%";
    array_push($params, $term, $term, $term, $term);
    $types .= "ssss";
}

if ($rolFiltro !== '') {
    $conditions .= " AND U.RolID = ?";
    array_push($params, (int)$rolFiltro);
    $types .= "i";
}

// Contar total de registros
$sqlCount = "SELECT COUNT(*) as total FROM usuarios U WHERE $conditions";
$stmtCount = $conexion->prepare($sqlCount);
if (!empty($params)) { 
    $stmtCount->bind_param($types, ...$params); 
}
$stmtCount->execute();
$totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$stmtCount->close();

// Obtener usuarios
$sql = "SELECT 
            U.*, R.NombreRol, TD.NombreDocumento as TipoDocNombre
        FROM usuarios U
        LEFT JOIN roles R ON U.RolID = R.RolID
        LEFT JOIN tipos_documento TD ON U.TipoDocumentoID = TD.TipoDocumentoID
        WHERE $conditions
        ORDER BY 
            CASE WHEN U.UserID = 1 THEN 0 ELSE 1 END,
            U.FechaRegistro DESC
        LIMIT ? OFFSET ?";

$paramsQuery = $params;
$paramsQuery[] = $registrosPorPagina;
$paramsQuery[] = $offset;
$typesQuery = $types . "ii";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($typesQuery, ...$paramsQuery);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$currentAdminId = (int)$_SESSION['user_id'];

function paginationUrl($page, $busqueda, $rolFiltro) {
    return "?" . http_build_query(['pagina' => $page, 'buscar' => $busqueda, 'rol' => $rolFiltro]);
}

$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1');

if (!$isAjax) {
    $pageTitle = 'Gestión de Usuarios';
    $pageScript = 'admin.js';
    require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
}
?>

<?php if (!$isAjax): ?>
<div class="container-fluid px-4 mt-4">
    <h1 class="mb-4">Gestión de Usuarios</h1>

    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body">
            <form id="filter-form" method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="buscar" id="search-input" class="form-control" placeholder="Nombre, Email o Documento..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="col-md-3">
                    <select name="rol" id="rol-select" class="form-select">
                        <option value="">Todos los roles</option>
                        <option value="2" <?php echo $rolFiltro == '2' ? 'selected' : ''; ?>>Persona Natural</option>
                        <option value="3" <?php echo $rolFiltro == '3' ? 'selected' : ''; ?>>Empresa</option>
                        <option value="4" <?php echo $rolFiltro == '4' ? 'selected' : ''; ?>>Revendedor</option>
                        <option value="5" <?php echo $rolFiltro == '5' ? 'selected' : ''; ?>>Operador</option>
                        <option value="1" <?php echo $rolFiltro == '1' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clear-filters" class="btn btn-outline-secondary w-100">Limpiar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0" id="table-content">
<?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Contacto</th>
                            <th>Documento</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th style="min-width: 180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-search display-6 d-block mb-2"></i>
                                    No se encontraron resultados.
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($usuarios as $user): ?>
                            <?php 
                                $isSuperAdmin = ($user['UserID'] == 1);
                                $isMe = ($user['UserID'] == $currentAdminId);
                                $disabledAttr = ($isSuperAdmin || $isMe) ? 'disabled' : '';
                            ?>
                            <tr id="user-row-<?php echo $user['UserID']; ?>" class="<?php echo $isSuperAdmin ? 'table-warning' : ''; ?>">
                                <td><?php echo $user['UserID']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?></strong><br>
                                    <small class="text-muted">Reg: <?php echo date("d/m/Y", strtotime($user['FechaRegistro'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column small">
                                        <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['Email']); ?></span>
                                        <span><i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($user['Telefono'] ?? '---'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['TipoDocNombre'] ?? 'Doc'); ?></small><br>
                                    <span class="fw-bold"><?php echo htmlspecialchars($user['NumeroDocumento']); ?></span>
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
                                        $iconClass = $isBlocked ? 'bi-lock-fill' : 'bi-unlock-fill';
                                        $statusText = $isBlocked ? 'Bloqueado' : 'Activo';
                                    ?>
                                    <button class="btn btn-sm <?php echo $btnClass; ?> block-user-btn" 
                                            data-user-id="<?php echo $user['UserID']; ?>" 
                                            data-current-status="<?php echo $isBlocked ? 'blocked' : 'active'; ?>"
                                            title="<?php echo $statusText; ?>"
                                            <?php echo $disabledAttr; ?>>
                                        <i class="bi <?php echo $iconClass; ?>"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-info btn-ver-beneficiarios text-white" 
                                                data-userid="<?php echo $user['UserID']; ?>" 
                                                title="Ver Beneficiarios">
                                            <i class="bi bi-person-lines-fill"></i> <span class="d-none d-xxl-inline">Benef.</span>
                                        </button>

                                        <button class="btn btn-sm btn-secondary view-user-docs-btn" 
                                                title="Ver Documentos"
                                                data-user-id="<?php echo $user['UserID']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($user['PrimerNombre'] . ' ' . $user['PrimerApellido']); ?>"
                                                data-img-frente="<?php echo htmlspecialchars($user['DocumentoImagenURL_Frente']); ?>"
                                                data-img-reverso="<?php echo htmlspecialchars($user['DocumentoImagenURL_Reverso']); ?>"
                                                data-foto-perfil="<?php echo htmlspecialchars($user['FotoPerfilURL']); ?>">
                                            <i class="bi bi-file-earmark-person"></i> <span class="d-none d-xxl-inline">Docs</span>
                                        </button>

                                        <button class="btn btn-sm btn-primary admin-edit-user-btn" 
                                                title="Editar Datos"
                                                data-user-id="<?php echo $user['UserID']; ?>"
                                                data-nombre1="<?php echo htmlspecialchars($user['PrimerNombre']); ?>"
                                                data-nombre2="<?php echo htmlspecialchars($user['SegundoNombre'] ?? ''); ?>" 
                                                data-apellido1="<?php echo htmlspecialchars($user['PrimerApellido']); ?>"
                                                data-apellido2="<?php echo htmlspecialchars($user['SegundoApellido'] ?? ''); ?>"
                                                data-documento="<?php echo htmlspecialchars($user['NumeroDocumento']); ?>"
                                                data-telefono="<?php echo htmlspecialchars($user['Telefono'] ?? ''); ?>" 
                                                data-email="<?php echo htmlspecialchars($user['Email']); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-danger admin-delete-user-btn" 
                                                title="Eliminar"
                                                data-user-id="<?php echo $user['UserID']; ?>" 
                                                <?php echo $disabledAttr; ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4 pb-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($paginaActual - 1, $busqueda, $rolFiltro); ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo paginationUrl($i, $busqueda, $rolFiltro); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo paginationUrl($paginaActual + 1, $busqueda, $rolFiltro); ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

<?php if (!$isAjax): ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBeneficiariosUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Gestión de Beneficiarios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="listaBeneficiariosLoader" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Cargando datos...</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaBeneficiariosUser">
                        <thead class="table-light">
                            <tr>
                                <th>Banco / País</th>
                                <th>Titular</th>
                                <th>Detalles Cuenta</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAdminEditarBeneficiario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Editar Beneficiario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAdminEditarBeneficiario">
                <div class="modal-body">
                    <input type="hidden" id="editBenId" name="cuentaId">
                    <input type="hidden" id="editBenUserId" name="userId">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre del Titular</label>
                        <input type="text" class="form-control" id="editBenNombre" name="nombre" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Documento</label>
                            <input type="text" class="form-control" id="editBenDoc" name="documento" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Banco</label>
                            <input type="text" class="form-control" id="editBenBanco" name="banco" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Número de Cuenta / Teléfono</label>
                        <input type="text" class="form-control" id="editBenCuenta" name="cuenta" required>
                        <div class="form-text small text-muted">
                            <i class="bi bi-info-circle"></i> Si es Pago Móvil, ingresa el teléfono. Si es cuenta, el número.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="userDocsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Documentos: <strong id="docsUserName" class="text-primary"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="viewDocsUserId">
                <div class="row g-4">
                    <div class="col-lg-4 text-center border-end">
                        <h6 class="text-muted mb-3">Foto de Perfil</h6>
                        <div class="position-relative d-inline-block">
                            <img id="docsProfilePic" src="" class="rounded-circle shadow-sm border" style="width: 180px; height: 180px; object-fit: cover;">
                            <div class="mt-2">
                                <a id="btnProfileView" href="#" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-edit-admin-doc" data-doc-type="perfil" title="Editar"><i class="bi bi-pencil"></i></button>
                                <a id="btnProfileDown" href="#" download class="btn btn-sm btn-outline-dark" title="Descargar"><i class="bi bi-download"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6 text-center">
                                <p class="small fw-bold mb-1">Frente</p>
                                <div class="bg-light p-2 rounded border d-flex align-items-center justify-content-center" style="min-height: 250px;">
                                    <img id="docsImgFrente" src="" class="img-fluid rounded" style="max-height: 230px;">
                                    <div id="noDocFrente" class="text-muted d-none">No disponible</div>
                                </div>
                                <div class="mt-2 btn-group">
                                    <a id="btnFrenteView" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-edit-admin-doc" data-doc-type="frente"><i class="bi bi-pencil"></i></button>
                                    <a id="btnFrenteDown" href="#" download class="btn btn-sm btn-outline-dark"><i class="bi bi-download"></i></a>
                                </div>
                            </div>
                            <div class="col-md-6 text-center">
                                <p class="small fw-bold mb-1">Reverso</p>
                                <div class="bg-light p-2 rounded border d-flex align-items-center justify-content-center" style="min-height: 250px;">
                                    <img id="docsImgReverso" src="" class="img-fluid rounded" style="max-height: 230px;">
                                    <div id="noDocReverso" class="text-muted d-none">No disponible</div>
                                </div>
                                <div class="mt-2 btn-group">
                                    <a id="btnReversoView" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark btn-edit-admin-doc" data-doc-type="reverso"><i class="bi bi-pencil"></i></button>
                                    <a id="btnReversoDown" href="#" download class="btn btn-sm btn-outline-dark"><i class="bi bi-download"></i></a>
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Editar Datos Personales</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit-user-form">
                <div class="modal-body">
                    <input type="hidden" id="edit-user-id" name="userId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Primer Nombre</label>
                            <input type="text" class="form-control" id="edit-nombre1" name="primerNombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Segundo Nombre</label>
                            <input type="text" class="form-control" id="edit-nombre2" name="segundoNombre">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Primer Apellido</label>
                            <input type="text" class="form-control" id="edit-apellido1" name="primerApellido" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Segundo Apellido</label>
                            <input type="text" class="form-control" id="edit-apellido2" name="segundoApellido">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nro. Documento</label>
                            <input type="text" class="form-control" id="edit-documento" name="numeroDocumento" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Teléfono</label>
                            <input type="text" class="form-control" id="edit-telefono" name="telefono">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="adminCropModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-crop"></i> Recortar Imagen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark" style="max-height: 70vh; overflow: hidden;">
                <div class="img-container" style="height: 500px; width: 100%;">
                    <img id="admin-image-to-crop" src="" style="display: block; max-width: 100%;">
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" id="admin-rotate-left"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="admin-rotate-right"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" id="admin-crop-confirm">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
endif;
?>