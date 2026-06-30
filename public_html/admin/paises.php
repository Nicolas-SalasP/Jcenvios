<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    http_response_code(403);
    die("Acceso denegado.");
}

$pageTitle = 'Gestionar Países';
$pageScript = 'admin.js';

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$paises = $conexion->query("SELECT * FROM paises ORDER BY NombrePais")->fetch_all(MYSQLI_ASSOC);
$rolesPosibles = ['Origen', 'Destino', 'Ambos'];
?>

<div class="container mt-4">
    <h1 class="mb-4">Gestionar Países</h1>
    <p><a href="<?php echo BASE_URL; ?>/admin/">Volver al panel principal</a></p>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4>Añadir Nuevo País</h4>
                </div>
                <div class="card-body">
                    <form id="add-pais-form">
                        <div class="mb-3">
                            <label for="nombrePais" class="form-label">Nombre del País</label>
                            <input type="text" class="form-control" id="nombrePais" name="nombrePais" required>
                        </div>
                        <div class="mb-3">
                            <label for="codigoMoneda" class="form-label">Código de Moneda (3 letras)</label>
                            <input type="text" class="form-control" id="codigoMoneda" name="codigoMoneda" required maxlength="3" style="text-transform:uppercase">
                        </div>
                        <div class="mb-3">
                            <label for="rolPais" class="form-label">Rol del País</label>
                            <select id="rolPais" name="rol" class="form-select" required>
                                <option value="Destino" selected>Destino</option>
                                <option value="Origen">Origen</option>
                                <option value="Ambos">Ambos</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Añadir País</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <h4>Países Existentes</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Moneda</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paises as $pais): ?>
                            <tr>
                                <td class="pais-nombre"><?php echo htmlspecialchars($pais['NombrePais']); ?></td>
                                <td class="pais-moneda"><?php echo htmlspecialchars($pais['CodigoMoneda']); ?></td>
                                <td>
                                    <select class="form-select form-select-sm role-select" data-pais-id="<?php echo $pais['PaisID']; ?>">
                                        <?php foreach ($rolesPosibles as $rol): ?>
                                            <option value="<?php echo $rol; ?>" <?php echo ($pais['Rol'] == $rol) ? 'selected' : ''; ?>>
                                                <?php echo $rol; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    </td>
                                <td>
                                    <button class="btn btn-sm toggle-status-btn <?php echo $pais['Activo'] ? 'btn-success' : 'btn-secondary'; ?>" 
                                            data-pais-id="<?php echo $pais['PaisID']; ?>"
                                            data-current-status="<?php echo $pais['Activo']; ?>">
                                        <?php echo $pais['Activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-pais-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPaisModal"
                                            data-pais-id="<?php echo $pais['PaisID']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($pais['NombrePais']); ?>"
                                            data-moneda="<?php echo htmlspecialchars($pais['CodigoMoneda']); ?>"
                                            title="Editar País">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>