<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Gestionar Tasas de Cambio';
$pageScript = 'tasas.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

// El $container se inicializa en init.php
$pricingService = $container->get(\App\Services\PricingService::class);

$paisesActivos = $conexion->query("SELECT PaisID, NombrePais, CodigoMoneda, Rol FROM paises WHERE Activo = TRUE ORDER BY NombrePais ASC")->fetch_all(MYSQLI_ASSOC);

$queryTasas = $conexion->query("
    SELECT T.*, PO.NombrePais AS PaisOrigen, PD.NombrePais AS PaisDestino, PO.CodigoMoneda as MonedaOrigen
    FROM tasas T
    JOIN paises PO ON T.PaisOrigenID = PO.PaisID
    JOIN paises PD ON T.PaisDestinoID = PD.PaisID
    WHERE T.Activa = 1
    ORDER BY PO.NombrePais, PD.NombrePais, T.EsReferencial DESC, T.MontoMinimo ASC
")->fetch_all(MYSQLI_ASSOC);

$tasasPorRuta = [];
foreach ($queryTasas as $tasa) {
    $rutaId = $tasa['PaisOrigenID'] . '-' . $tasa['PaisDestinoID'];
    if (!isset($tasasPorRuta[$rutaId])) {
        $tasasPorRuta[$rutaId] = ['origen' => $tasa['PaisOrigen'], 'destino' => $tasa['PaisDestino'], 'moneda' => $tasa['MonedaOrigen'], 'items' => []];
    }
    $tasasPorRuta[$rutaId]['items'][] = $tasa;
}

$paisesOrigen = array_filter($paisesActivos, fn($p) => in_array($p['Rol'], ['Origen', 'Ambos']));
$paisesDestino = array_filter($paisesActivos, fn($p) => in_array($p['Rol'], ['Destino', 'Ambos']));

$settings = $pricingService->getGlobalAdjustmentSettings();
?>

<div class="container mt-4">
    <h1 class="mb-4">Gestionar Tasas de Cambio</h1>

    <div class="row mb-4">
        <div class="col-md-5">
            <div class="card shadow-sm border-info h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Tasa Dólar BCV (Referencia)</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <form id="bcv-rate-form" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <div class="input-group">
                                <span class="input-group-text fw-bold">1 USD =</span>
                                <input type="text" class="form-control" id="bcv-rate" required>
                                <span class="input-group-text">VES</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary" id="btn-save-bcv">Actualizar</button>
                        </div>
                    </form>
                    <div id="bcv-feedback" class="mt-2"></div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-warning h-100">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2"></i>Ajuste Global de Tasas</h5>
                    <span class="badge bg-dark">Referenciales</span>
                </div>
                <div class="card-body">
                    <form id="global-adjustment-form" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Ajuste (%):</label>
                            <input type="number" step="0.01" class="form-control" id="global-adj-percent"
                                value="<?= $settings['percent'] ?>" placeholder="Ej: -2.5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Hora de actualización:</label>
                            <input type="time" class="form-control" id="global-adj-time"
                                value="<?= $settings['time'] ?>">
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" id="btn-save-global-settings"
                                class="btn btn-outline-primary btn-sm mb-1 w-100">Guardar Config</button>
                            <button type="button" id="btn-apply-global-now" class="btn btn-danger btn-sm w-100">
                                <i class="bi bi-lightning-fill"></i> Aplicar Ya
                            </button>
                        </div>
                    </form>
                    <?php if ($settings['last_run']): ?>
                        <div class="mt-2 small text-muted"><i class="bi bi-clock-history"></i> Última ejecución:
                            <strong><?= $settings['last_run'] ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Editor de Tasas Comerciales</h5>
        </div>
        <div class="card-body" id="rate-editor-card-body">
            <form id="rate-editor-form">
                <input type="hidden" id="current-tasa-id" value="new">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Origen:</label>
                        <select id="pais-origen" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($paisesOrigen as $p): ?>
                                <option value="<?= $p['PaisID'] ?>"><?= htmlspecialchars($p['NombrePais']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Destino:</label>
                        <select id="pais-destino" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($paisesDestino as $p): ?>
                                <option value="<?= $p['PaisID'] ?>"><?= htmlspecialchars($p['NombrePais']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" id="rate-is-ref">
                            <label class="form-check-label fw-bold small" for="rate-is-ref">Referencial</label>
                        </div>
                        <div class="form-check form-switch pt-1">
                            <input class="form-check-input bg-danger border-danger" type="checkbox" id="rate-is-risky">
                            <label class="form-check-label fw-bold text-danger small" for="rate-is-risky">Ruta
                                Riesgosa</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Ajuste (%):</label>
                        <input type="text" class="form-control" id="rate-percent" value="0,00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Valor Tasa:</label>
                        <input type="text" class="form-control" id="rate-value" required>
                    </div>
                </div>
                <div class="row g-3 mt-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Monto Mínimo:</label>
                        <input type="text" class="form-control" id="rate-monto-min" value="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Monto Máximo:</label>
                        <input type="text" class="form-control" id="rate-monto-max" value="9.999.999.999,99">
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" id="cancel-edit-btn"
                            class="btn btn-outline-secondary d-none">Cancelar</button>
                        <button type="submit" id="save-rate-btn" class="btn btn-primary" disabled>Guardar Tasa</button>
                    </div>
                </div>
            </form>
            <div id="feedback-message" class="mt-3"></div>
        </div>
    </div>

    <div class="accordion" id="accordionTasas">
        <?php foreach ($tasasPorRuta as $rutaKey => $ruta): ?>
            <div class="accordion-item shadow-sm mb-2">
                <h2 class="accordion-header" id="heading-<?= $rutaKey ?>">
                    <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse-<?= $rutaKey ?>">
                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                        <?= $ruta['origen'] ?> <i class="bi bi-arrow-right mx-2"></i> <?= $ruta['destino'] ?>
                    </button>
                </h2>
                <div id="collapse-<?= $rutaKey ?>" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Rango</th>
                                    <th class="text-center">Tipo</th>
                                    <th class="text-center">Ajuste</th>
                                    <th class="text-center">Valor</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ruta['items'] as $item): ?>
                                    <tr class="<?= $item['EsReferencial'] ? 'table-primary' : '' ?>">
                                        <td>[<?= number_format($item['MontoMinimo'], 2, ',', '.') ?> -
                                            <?= number_format($item['MontoMaximo'], 0, '', '') ?>]
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            if ($item['EsReferencial'])
                                                echo '<span class="badge bg-primary">Referencial</span> ';
                                            else
                                                echo '<span class="badge bg-secondary">Ajustada</span> ';

                                            if ($item['EsRiesgoso'])
                                                echo '<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle-fill"></i> Riesgo</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?= $item['EsReferencial'] ? '-' : ($item['PorcentajeAjuste'] >= 0 ? '+' : '') . number_format($item['PorcentajeAjuste'], 2, ',', '.') . '%' ?>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format($item['ValorTasa'], 5, ',', '.') ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary edit-rate-btn"
                                                data-tasa-id="<?= $item['TasaID'] ?>"
                                                data-origen-id="<?= $item['PaisOrigenID'] ?>"
                                                data-destino-id="<?= $item['PaisDestinoID'] ?>"
                                                data-valor="<?= $item['ValorTasa'] ?>" data-min="<?= $item['MontoMinimo'] ?>"
                                                data-max="<?= $item['MontoMaximo'] ?>"
                                                data-is-ref="<?= $item['EsReferencial'] ?>"
                                                data-is-risky="<?= $item['EsRiesgoso'] ?>"
                                                data-percent="<?= $item['PorcentajeAjuste'] ?>"><i
                                                    class="bi bi-pencil-fill"></i></button>
                                            <button class="btn btn-sm btn-outline-danger delete-rate-btn"
                                                data-tasa-id="<?= $item['TasaID'] ?>"><i class="bi bi-trash-fill"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>