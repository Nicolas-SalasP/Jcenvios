<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Gestionar Tasas de Cambio';
$pageScript = 'tasas.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$paisesActivos = $conexion->query("
    SELECT PaisID, NombrePais, CodigoMoneda, Rol FROM paises 
    WHERE Activo = TRUE 
    ORDER BY NombrePais ASC
")->fetch_all(MYSQLI_ASSOC);

$tasasExistentes = $conexion->query("
    SELECT 
        T.TasaID, T.ValorTasa, T.MontoMinimo, T.MontoMaximo,
        PO.PaisID as OrigenID, PO.NombrePais AS PaisOrigen,
        PD.PaisID as DestinoID, PD.NombrePais AS PaisDestino
    FROM tasas T
    JOIN paises PO ON T.PaisOrigenID = PO.PaisID
    JOIN paises PD ON T.PaisDestinoID = PD.PaisID
    WHERE PO.Activo = TRUE AND PD.Activo = TRUE AND T.Activa = 1
    ORDER BY PO.NombrePais, PD.NombrePais, T.MontoMinimo
")->fetch_all(MYSQLI_ASSOC);

$paisesOrigen = [];
$paisesDestino = [];
foreach ($paisesActivos as $pais) {
    if ($pais['Rol'] === 'Origen' || $pais['Rol'] === 'Ambos') {
        $paisesOrigen[] = $pais;
    }
    if ($pais['Rol'] === 'Destino' || $pais['Rol'] === 'Ambos') {
        $paisesDestino[] = $pais;
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Gestionar Tasas de Cambio</h1>
    </div>

    <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-bank"></i> Tasa Dólar BCV (Referencia)</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">Esta tasa se usa como referencia para mostrar a los clientes el equivalente en
                dólares de su envío.</p>
            <form id="bcv-rate-form" class="row row-cols-lg-auto g-3 align-items-center">
                <div class="col-12">
                    <label class="visually-hidden" for="bcv-rate">Tasa BCV</label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">1 USD =</span>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="bcv-rate"
                            placeholder="0.00" required>
                        <span class="input-group-text">VES</span>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="btn-save-bcv">Actualizar Referencia</button>
                </div>
            </form>
            <div id="bcv-feedback" class="mt-2"></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Editor de Tasas Comerciales (Venta)</h5>
        </div>
        <div class="card-body">
            <form id="rate-editor-form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="pais-origen" class="form-label">De (Origen):</label>
                        <select id="pais-origen" class="form-select">
                            <option value="">Seleccionar país...</option>
                            <?php foreach ($paisesOrigen as $pais): ?>
                                <option value="<?php echo $pais['PaisID']; ?>">
                                    <?php echo htmlspecialchars($pais['NombrePais']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="pais-destino" class="form-label">A (Destino):</label>
                        <select id="pais-destino" class="form-select">
                            <option value="">Seleccionar país...</option>
                            <?php foreach ($paisesDestino as $pais): ?>
                                <option value="<?php echo $pais['PaisID']; ?>">
                                    <?php echo htmlspecialchars($pais['NombrePais']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="rate-value" class="form-label">Valor Tasa:</label>
                        <input type="number" step="0.000001" min="0" class="form-control" id="rate-value"
                            placeholder="0.00000" disabled>
                    </div>

                    <div class="col-md-2">
                        <label for="rate-monto-min" class="form-label">Monto Mínimo:</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="rate-monto-min"
                            placeholder="0.00" value="0.00" disabled>
                    </div>
                    <div class="col-md-2">
                        <label for="rate-monto-max" class="form-label">Monto Máximo:</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="rate-monto-max"
                            placeholder="Sin límite" value="9999999999.99" disabled>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-12 text-end">
                        <button type="submit" id="save-rate-btn" class="btn btn-primary" title="Guardar Tasa" disabled>
                            <i class="bi bi-save"></i> Guardar Tasa
                        </button>
                    </div>
                </div>
            </form>
            <div id="feedback-message" class="mt-3"></div>
            <input type="hidden" id="current-tasa-id" value="new">
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Tasas Configuradas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="existing-rates-table">
                    <thead class="table-light">
                        <tr>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Monto Mínimo</th>
                            <th>Monto Máximo</th>
                            <th>Valor Tasa</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasasExistentes)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay tasas configuradas. Usa el editor para crear la
                                    primera.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasasExistentes as $tasa): ?>
                                <tr id="tasa-row-<?php echo $tasa['TasaID']; ?>"
                                    data-origen-id="<?php echo $tasa['OrigenID']; ?>"
                                    data-destino-id="<?php echo $tasa['DestinoID']; ?>">
                                    <td><?php echo htmlspecialchars($tasa['PaisOrigen']); ?></td>
                                    <td><?php echo htmlspecialchars($tasa['PaisDestino']); ?></td>
                                    <td class="rate-min-cell">
                                        <?php echo htmlspecialchars(number_format($tasa['MontoMinimo'], 2, ',', '.')); ?>
                                    </td>
                                    <td class="rate-max-cell">
                                        <?php echo htmlspecialchars(number_format($tasa['MontoMaximo'], 2, ',', '.')); ?>
                                    </td>
                                    <td class="rate-value-cell">
                                        <?php echo htmlspecialchars(number_format($tasa['ValorTasa'], 5, ',', '.')); ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-rate-btn me-1"
                                            data-tasa-id="<?php echo $tasa['TasaID']; ?>"
                                            data-origen-id="<?php echo $tasa['OrigenID']; ?>"
                                            data-destino-id="<?php echo $tasa['DestinoID']; ?>"
                                            data-valor="<?php echo $tasa['ValorTasa']; ?>"
                                            data-min="<?php echo $tasa['MontoMinimo']; ?>"
                                            data-max="<?php echo $tasa['MontoMaximo']; ?>" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-rate-btn"
                                            data-tasa-id="<?php echo $tasa['TasaID']; ?>" title="Eliminar Tasa">
                                            <i class="bi bi-trash-fill"></i>
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

<script>
    const ratesMap = {};
    <?php foreach ($tasasExistentes as $tasa): ?>
        if (!ratesMap[<?php echo $tasa['OrigenID']; ?>]) ratesMap[<?php echo $tasa['OrigenID']; ?>] = {};
        if (!ratesMap[<?php echo $tasa['OrigenID']; ?>][<?php echo $tasa['DestinoID']; ?>]) ratesMap[<?php echo $tasa['OrigenID']; ?>][<?php echo $tasa['DestinoID']; ?>] = [];

        ratesMap[<?php echo $tasa['OrigenID']; ?>][<?php echo $tasa['DestinoID']; ?>].push({
            tasaId: '<?php echo $tasa['TasaID']; ?>',
            valor: '<?php echo $tasa['ValorTasa']; ?>',
            min: '<?php echo $tasa['MontoMinimo']; ?>',
            max: '<?php echo $tasa['MontoMaximo']; ?>'
        });
    <?php endforeach; ?>
</script>

<?php
require_once __DIR__ . '/../../remesas_private/src/templates/footer.php';
?>