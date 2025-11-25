<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

if (!isset($_SESSION['twofa_enabled']) || $_SESSION['twofa_enabled'] === false) {
    header('Location: ' . BASE_URL . '/dashboard/seguridad.php');
    exit();
}

function getStatusBadgeClass($statusName)
{
    switch ($statusName) {
        case 'Pagado':
            return 'bg-success';
        case 'En Proceso':
            return 'bg-primary';
        case 'En Verificación':
            return 'bg-info text-dark';
        case 'Cancelado':
            return 'bg-danger';
        case 'Pendiente de Pago':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

$pageTitle = 'Panel de Administración';
$pageScript = 'admin.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$registrosPorPagina = 100;
$paginaActual = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($paginaActual < 1)
    $paginaActual = 1;
$offset = ($paginaActual - 1) * $registrosPorPagina;

$sqlCount = "SELECT COUNT(*) as total FROM transacciones";
$totalRegistros = $conexion->query($sqlCount)->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

$sql = "
    SELECT T.*, U.PrimerNombre, U.PrimerApellido,
        T.BeneficiarioNombre AS BeneficiarioNombreCompleto,
        ET.NombreEstado AS EstadoNombre
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    ORDER BY T.FechaTransaccion DESC
    LIMIT ? OFFSET ?
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h1 class="mb-0 me-3">Panel de Administración</h1>
        <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end mt-3 mt-md-0">
            <a href="exportar_transacciones.php" class="btn btn-success">
                <i class="bi bi-file-earmark-excel-fill"></i> Exportar a Excel
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/pendientes.php" class="btn btn-primary">Ver Transacciones
                Pendientes</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Beneficiario</th>
                    <th>Estado</th>
                    <th>Comisión</th>
                    <th>Orden</th>
                    <th>Comp. Usuario</th>
                    <th>Comp. Admin</th>
                </tr>
            </thead>
            <tbody id="transactionsTableBody">
                <?php foreach ($transacciones as $tx): ?>
                    <tr>
                        <td><?php echo $tx['TransaccionID']; ?></td>
                        <td><?php echo date("d/m/y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
                        <td class="search-user">
                            <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
                        </td>
                        <td class="search-beneficiary"><?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto']); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo getStatusBadgeClass($tx['EstadoNombre'] ?? ''); ?>">
                                <?php echo htmlspecialchars($tx['EstadoNombre'] ?? 'Desconocido'); ?>
                            </span>
                        </td>

                        <td>
                            <div class="d-flex align-items-center justify-content-between">
                                <span><?php echo number_format($tx['ComisionDestino'], 2); ?></span>
                                <?php if (in_array($tx['EstadoNombre'], ['Pagado', 'En Proceso'])): ?>
                                    <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2"
                                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                        data-current-val="<?php echo $tx['ComisionDestino']; ?>" title="Editar Comisión">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                                target="_blank" class="btn btn-sm btn-info" title="Ver Orden PDF">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($tx['ComprobanteURL'])): ?>
                                <button class="btn btn-sm btn-info view-comprobante-btn-admin" data-bs-toggle="modal"
                                    data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                    data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                                    data-envio-url="<?php echo !empty($tx['ComprobanteEnvioURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']) : ''; ?>"
                                    data-start-type="user" title="Ver Comprobante de Pago">
                                    <i class="bi bi-eye"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($tx['ComprobanteEnvioURL'])): ?>
                                <button class="btn btn-sm btn-success view-comprobante-btn-admin" data-bs-toggle="modal"
                                    data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                                    data-comprobante-url="<?php echo !empty($tx['ComprobanteURL']) ? BASE_URL . htmlspecialchars($tx['ComprobanteURL']) : ''; ?>"
                                    data-envio-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteEnvioURL']); ?>"
                                    data-start-type="admin" title="Ver Comprobante de Envío">
                                    <i class="bi bi-receipt"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Navegación de páginas">
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

<div class="modal fade" id="editCommissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Comisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Estás editando la comisión para la Orden <strong id="modal-commission-tx-id-label"></strong></p>
                <form id="edit-commission-form">
                    <input type="hidden" id="commission-tx-id" name="transactionId">
                    <div class="mb-3">
                        <label for="new-commission-input" class="form-label">Monto de la Comisión</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="new-commission-input"
                            name="newCommission" required>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Al guardar, el saldo contable de la caja se ajustará automáticamente (sumando o restando la
                        diferencia).
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>