<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (
    !isset($_SESSION['user_rol_name']) ||
    ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')
) {
    http_response_code(403);
    exit('Acceso denegado');
}

$isOperator = ($_SESSION['user_rol_name'] === 'Operador');
$estadosSQL = $isOperator ? "3, 6" : "2, 3, 6";

// === Filtros (server-side, prepared statements) ===
$f_id      = trim($_GET['f_id'] ?? '');
$f_user    = trim($_GET['f_user'] ?? '');
$f_estado  = trim($_GET['f_estado'] ?? '');
$f_origen  = trim($_GET['f_origen'] ?? '');
$f_destino = trim($_GET['f_destino'] ?? '');
$f_desde   = trim($_GET['f_desde'] ?? '');   // fecha inicio (YYYY-MM-DD)
$f_hasta   = trim($_GET['f_hasta'] ?? '');   // fecha fin (YYYY-MM-DD)

$conds  = [];
$params = [];
$types  = "";

if ($f_id !== '' && ctype_digit($f_id)) {
    $conds[]  = "T.TransaccionID = ?";
    $params[] = (int)$f_id;
    $types   .= "i";
}

if ($f_user !== '') {
    $conds[]  = "(U.PrimerNombre LIKE ? OR U.PrimerApellido LIKE ? OR CONCAT_WS(' ',U.PrimerNombre,U.PrimerApellido) LIKE ? OR T.BeneficiarioNombre LIKE ?)";
    $like     = '%' . $f_user . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "ssss";
}

if ($f_estado !== '' && ctype_digit($f_estado)) {
    $allowed = array_map('intval', explode(',', $estadosSQL));
    if (in_array((int)$f_estado, $allowed, true)) {
        $conds[]  = "T.EstadoID = ?";
        $params[] = (int)$f_estado;
        $types   .= "i";
    }
}

if ($f_origen !== '' && ctype_digit($f_origen)) {
    $conds[]  = "TS.PaisOrigenID = ?";
    $params[] = (int)$f_origen;
    $types   .= "i";
}

if ($f_destino !== '' && ctype_digit($f_destino)) {
    $conds[]  = "CB.PaisID = ?";
    $params[] = (int)$f_destino;
    $types   .= "i";
}

if ($f_desde !== '') {
    $conds[]  = "DATE(T.FechaTransaccion) >= ?";
    $params[] = $f_desde;
    $types   .= "s";
}

if ($f_hasta !== '') {
    $conds[]  = "DATE(T.FechaTransaccion) <= ?";
    $params[] = $f_hasta;
    $types   .= "s";
}

$whereSQL = "WHERE T.EstadoID IN ($estadosSQL)"
    . (count($conds) ? " AND " . implode(" AND ", $conds) : "");

$sql = "
    SELECT T.*,
        U.PrimerNombre, U.PrimerApellido, U.Email,
        ET.NombreEstado AS EstadoNombre,
        T.BeneficiarioNombre, T.BeneficiarioDocumento, T.BeneficiarioBanco, 
        T.BeneficiarioNumeroCuenta, T.BeneficiarioTelefono,
        T.MontoDestino, T.MonedaDestino,
        T.ComprobanteURL, T.ComprobanteEnvioURL,
        CB.PaisID AS PaisDestinoID,
        -- F3.2: contar envíos previos exitosos a la misma cuenta/teléfono
        (SELECT COUNT(*)
        FROM transacciones T2
        JOIN estados_transaccion ET2 ON T2.EstadoID = ET2.EstadoID
        WHERE T2.UserID = T.UserID
        AND T2.TransaccionID <> T.TransaccionID
        AND ET2.NombreEstado = 'Exitoso'
        AND (
            (COALESCE(T.BeneficiarioNumeroCuenta,'') <> '' AND T2.BeneficiarioNumeroCuenta = T.BeneficiarioNumeroCuenta)
        OR (COALESCE(T.BeneficiarioTelefono,'')     <> '' AND T2.BeneficiarioTelefono     = T.BeneficiarioTelefono)
        )
        ) AS EnviosPreviosMismaCuenta
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    LEFT JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
    LEFT JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
    $whereSQL
    ORDER BY T.FechaTransaccion ASC
";

$stmt = $conexion->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transacciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($transacciones)) {
    echo '<tr><td colspan="7" class="text-center text-muted py-5">¡Todo al día! No hay órdenes pendientes por procesar.</td></tr>';
    exit;
}

foreach ($transacciones as $tx):
    $badgeClass = match ($tx['EstadoNombre']) {
        'En Verificación' => 'bg-info text-dark',
        'En Proceso' => 'bg-primary',
        'Pausado' => 'bg-warning text-dark',
        default => 'bg-secondary'
    };

    $hasCuenta = !empty(trim($tx['BeneficiarioNumeroCuenta'] ?? ''));
    $hasTelefono = !empty(trim($tx['BeneficiarioTelefono'] ?? ''));

    $fechaGen = !empty($tx['FechaTransaccion'])
        ? date('d/m/Y H:i', strtotime($tx['FechaTransaccion']))
        : '';

    $textoCopiado  = "ORDEN #{$tx['TransaccionID']}\n";
    if ($fechaGen) {
        $textoCopiado .= "Fecha: {$fechaGen}\n";
    }
    $textoCopiado .= "Banco: {$tx['BeneficiarioBanco']}\n";
    $textoCopiado .= "Beneficiario: {$tx['BeneficiarioNombre']}\n";

    if ($hasCuenta) {
        $textoCopiado .= "Cuenta: {$tx['BeneficiarioNumeroCuenta']}\n";
    }
    if ($hasTelefono) {
        $textoCopiado .= "Teléfono: {$tx['BeneficiarioTelefono']}\n";
    }

    $textoCopiado .= "Doc: {$tx['BeneficiarioDocumento']}\n";
    $textoCopiado .= "Monto: " . number_format($tx['MontoDestino'], 2, ',', '.') . " {$tx['MonedaDestino']}";

    $textoBase64 = base64_encode($textoCopiado);

    $jsonData = htmlspecialchars(json_encode([
        'id' => $tx['TransaccionID'],
        'banco' => $tx['BeneficiarioBanco'],
        'nombre' => $tx['BeneficiarioNombre'],
        'doc' => $tx['BeneficiarioDocumento'],
        'cuenta' => $tx['BeneficiarioNumeroCuenta'],
        'telefono' => $tx['BeneficiarioTelefono'],
        'hasCuenta' => $hasCuenta,
        'hasTelefono' => $hasTelefono,
        'monto' => number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']
    ]), ENT_QUOTES, 'UTF-8');
    ?>
    <tr>
        <td><strong>#<?php echo $tx['TransaccionID']; ?></strong></td>
        <td><?php echo date("d/m H:i", strtotime($tx['FechaTransaccion'])); ?></td>
        <td>
            <div class="fw-bold">
                <?php echo htmlspecialchars($tx['PrimerNombre'] . ' ' . $tx['PrimerApellido']); ?>
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">
                <?php echo htmlspecialchars($tx['Email']); ?>
            </div>
            <?php
                $previos = (int)($tx['EnviosPreviosMismaCuenta'] ?? 0);
                if ($previos > 0):
                    $color = $previos >= 5 ? 'bg-warning text-dark' : 'bg-info text-white';
            ?>
                <button type="button"
                        class="badge <?php echo $color; ?> border-0 view-prev-sends-btn mt-1"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        title="Ver envíos previos exitosos a esta misma cuenta"
                        style="cursor:pointer;font-size:0.65rem;">
                    <i class="bi bi-arrow-repeat"></i> Envío #<?php echo $previos + 1; ?>
                </button>
            <?php endif; ?>
        </td>
        <td class="fw-bold text-success">
            <?php echo number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']; ?>
        </td>

        <td>
            <div class="d-flex flex-column align-items-center">
                <span class="badge <?php echo $badgeClass; ?>"><?php echo $tx['EstadoNombre']; ?></span>
            </div>
        </td>

        <td class="text-center">
            <div class="d-flex justify-content-center gap-1">
                <button class="btn btn-sm btn-primary d-flex align-items-center gap-1"
                    onclick="copiarDatosDirecto(this, '<?php echo $textoBase64; ?>')"
                    title="Copiar todos los datos al portapapeles">
                    <i class="bi bi-clipboard-check"></i> <span>Copiar</span>
                </button>

                <button class="btn btn-sm btn-outline-secondary copy-data-btn ms-1" data-datos="<?php echo $jsonData; ?>"
                    title="Ver Detalles Visualmente">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </td>

        <td class="text-end">
            <div class="d-flex gap-1 justify-content-end">
                <a href="../admin/orden.php?id=<?php echo $tx['TransaccionID']; ?>"
                    class="btn btn-sm btn-dark" title="Abrir orden (pantalla dividida)">
                    <i class="bi bi-window-split"></i> Abrir
                </a>
                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>" target="_blank"
                    class="btn btn-sm btn-outline-danger" title="Ver Orden PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>

                <?php if ($tx['EstadoNombre'] === 'Pausado' && !empty($tx['MotivoPausa'])): ?>
                    <button type="button" class="btn btn-sm btn-warning view-pause-reason-btn"
                        data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>" title="Ver Motivo de Pausa">
                        <i class="bi bi-info-circle-fill"></i>
                    </button>
                <?php endif; ?>

                <?php if (!empty($tx['ComprobanteURL'])): ?>
                    <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin" data-bs-toggle="modal"
                        data-bs-target="#viewComprobanteModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-comprobante-url="<?php echo BASE_URL . '/admin/view_secure_file.php?file=' . urlencode($tx['ComprobanteURL']); ?>"
                        title="Ver Comprobante Cliente">
                        <i class="bi bi-eye"></i>
                    </button>
                <?php endif; ?>

                <?php if ($tx['EstadoNombre'] === 'En Proceso'): ?>
                    <button class="btn btn-sm btn-success admin-upload-btn" data-bs-toggle="modal"
                        data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-monto-destino="<?php echo $tx['MontoDestino']; ?>"
                        data-pais-id="<?php echo $tx['PaisDestinoID']; ?>" title="Finalizar y Pagar">
                        <i class="bi bi-upload"></i>
                    </button>
                    <button class="btn btn-sm btn-warning pause-btn-modal" data-bs-toggle="modal" data-bs-target="#pauseModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Pausar Orden">
                        <i class="bi bi-pause-circle-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-danger reject-btn" data-bs-toggle="modal" data-bs-target="#rejectionModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-action-type="cancel"
                        title="Cancelar Orden"><i class="bi bi-x-circle"></i></button>
                <?php endif; ?>

                <?php if (!$isOperator && $tx['EstadoNombre'] === 'En Verificación'): ?>
                    <button class="btn btn-sm btn-success process-btn" data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        title="Aprobar"><i class="bi bi-check-lg"></i></button>
                    <button class="btn btn-sm btn-danger reject-btn" data-bs-toggle="modal" data-bs-target="#rejectionModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>