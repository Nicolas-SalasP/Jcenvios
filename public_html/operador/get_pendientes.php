<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || 
    ($_SESSION['user_rol_name'] !== 'Admin' && $_SESSION['user_rol_name'] !== 'Operador')) {
    http_response_code(403);
    exit('Acceso denegado');
}

$isOperator = ($_SESSION['user_rol_name'] === 'Operador');
$estadosSQL = $isOperator ? "3" : "2, 3";

$sql = "
    SELECT T.*, 
        U.PrimerNombre, U.PrimerApellido, U.Email,
        ET.NombreEstado AS EstadoNombre,
        T.BeneficiarioNombre, T.BeneficiarioDocumento, T.BeneficiarioBanco, 
        T.BeneficiarioNumeroCuenta, T.BeneficiarioTelefono,
        T.MontoDestino, T.MonedaDestino,
        T.ComprobanteURL, T.ComprobanteEnvioURL
    FROM transacciones T
    JOIN usuarios U ON T.UserID = U.UserID
    LEFT JOIN estados_transaccion ET ON T.EstadoID = ET.EstadoID
    WHERE T.EstadoID IN ($estadosSQL)
    ORDER BY T.FechaTransaccion ASC
";

$stmt = $conexion->prepare($sql);
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
        default => 'bg-secondary'
    };

    $esPagoMovil = ($tx['BeneficiarioNumeroCuenta'] === 'PAGO MOVIL');
    $cuentaMostrar = $esPagoMovil ? $tx['BeneficiarioTelefono'] : $tx['BeneficiarioNumeroCuenta'];
    
    $textoCopiado = "ORDEN #{$tx['TransaccionID']}\n";
    $textoCopiado .= "Banco: {$tx['BeneficiarioBanco']}\n";
    $textoCopiado .= "Beneficiario: {$tx['BeneficiarioNombre']}\n";
    $textoCopiado .= ($esPagoMovil ? "Teléfono" : "Cuenta") . ": {$cuentaMostrar}\n";
    $textoCopiado .= "Doc: {$tx['BeneficiarioDocumento']}\n";
    $textoCopiado .= "Monto: " . number_format($tx['MontoDestino'], 2, ',', '.') . " {$tx['MonedaDestino']}";
    
    $textoBase64 = base64_encode($textoCopiado);

    $jsonData = htmlspecialchars(json_encode([
        'id' => $tx['TransaccionID'],
        'banco' => $tx['BeneficiarioBanco'],
        'nombre' => $tx['BeneficiarioNombre'],
        'doc' => $tx['BeneficiarioDocumento'],
        'cuenta' => $cuentaMostrar,
        'tipo' => $esPagoMovil ? 'Pago Móvil' : 'Cuenta Bancaria',
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
        </td>
        <td class="fw-bold text-success">
            <?php echo number_format($tx['MontoDestino'], 2, ',', '.') . ' ' . $tx['MonedaDestino']; ?>
        </td>
        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $tx['EstadoNombre']; ?></span></td>

        <td class="text-center">
            <div class="d-flex justify-content-center gap-1">
                <button class="btn btn-sm btn-primary d-flex align-items-center gap-1" 
                        onclick="copiarDatosDirecto(this, '<?php echo $textoBase64; ?>')" 
                        title="Copiar todos los datos al portapapeles">
                    <i class="bi bi-clipboard-check"></i> <span>Copiar</span>
                </button>
                
                <button class="btn btn-sm btn-outline-secondary copy-data-btn ms-1"
                    data-datos="<?php echo $jsonData; ?>" title="Ver Detalles Visualmente">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </td>

        <td class="text-end">
            <div class="d-flex gap-1 justify-content-end">
                <a href="<?php echo BASE_URL; ?>/generar-factura.php?id=<?php echo $tx['TransaccionID']; ?>"
                    target="_blank" class="btn btn-sm btn-outline-danger" title="Ver Orden PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>

                <?php if (!empty($tx['ComprobanteURL'])): ?>
                    <button class="btn btn-sm btn-info text-white view-comprobante-btn-admin"
                        data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-comprobante-url="<?php echo BASE_URL . htmlspecialchars($tx['ComprobanteURL']); ?>"
                        title="Ver Comprobante Cliente">
                        <i class="bi bi-eye"></i>
                    </button>
                <?php endif; ?>

                <?php if ($tx['EstadoNombre'] === 'En Proceso'): ?>
                    <button class="btn btn-sm btn-success admin-upload-btn" 
                        data-bs-toggle="modal"
                        data-bs-target="#adminUploadModal"
                        data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                        data-monto-destino="<?php echo $tx['MontoDestino']; ?>"
                        title="Finalizar y Pagar">
                        <i class="bi bi-upload"></i>
                    </button>
                <?php endif; ?>

                <?php if (!$isOperator && $tx['EstadoNombre'] === 'En Verificación'): ?>
                    <button class="btn btn-sm btn-success process-btn" data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectionModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>