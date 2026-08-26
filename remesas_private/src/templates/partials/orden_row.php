<?php
/**
 * Una fila <tr> de la tabla de órdenes. Fuente única para Admin y Operador,
 * tanto en el render inicial como en la respuesta a ?ajax=1.
 *
 * Se incluye DENTRO del foreach. Espera en scope:
 *
 *   $tx            array  Fila de la consulta de transacciones.
 *   $ordenRowCtx   array  Contexto de la página (definido UNA vez antes del foreach):
 *                         - puedePagar          bool    muestra .admin-upload-btn en 'En Proceso'
 *                         - puedeEditarComision bool    muestra .edit-commission-btn
 *                         - secureFileBase      string  URL absoluta de view_secure_file.php
 *                         - ordenUrl            string  URL absoluta de orden.php
 *                         - facturaUrl          string  URL absoluta de generar-factura.php
 *
 * Requiere orden_helpers.php ya cargado (require_once) por la página.
 *
 * Este partial NO lee $_SESSION ni consulta la base: el rol se traduce a los
 * booleanos de $ordenRowCtx en la página. La autorización real vive en el
 * backend (BaseController::ensureAdmin / ensureAdminOrOperator).
 */

$ctx = $ordenRowCtx ?? [];
$puedePagar          = !empty($ctx['puedePagar']);
$puedeEditarComision = !empty($ctx['puedeEditarComision']);
$secureFileBase      = $ctx['secureFileBase'] ?? '';
$ordenUrl            = $ctx['ordenUrl'] ?? '';
$facturaUrl          = $ctx['facturaUrl'] ?? '';

$estadoNombre = $tx['EstadoNombre'] ?? '';
$nombreCliente = trim(($tx['PrimerNombre'] ?? '') . ' ' . ($tx['PrimerApellido'] ?? ''));

// Titular que realizó la transferencia (cae al cliente si no se declaró uno distinto).
$nombreTitular = !empty($tx['NombreTitularOrigen']) ? $tx['NombreTitularOrigen'] : $nombreCliente;
$rutTitular    = !empty($tx['RutTitularOrigen']) ? $tx['RutTitularOrigen'] : ($tx['UsuarioDocumento'] ?? 'N/A');

// Bloque de datos del beneficiario, precomputado para los dos botones de copiado.
$hasCuenta   = !empty(trim($tx['BeneficiarioNumeroCuenta'] ?? ''));
$hasTelefono = !empty(trim($tx['BeneficiarioTelefono'] ?? ''));
$fechaGen    = !empty($tx['FechaTransaccion']) ? date('d/m/Y H:i', strtotime($tx['FechaTransaccion'])) : '';
$montoDestinoFmt = number_format($tx['MontoDestino'] ?? 0, 2, ',', '.') . ' ' . ($tx['MonedaDestino'] ?? '');

$textoCopiado  = "ORDEN #{$tx['TransaccionID']}\n";
if ($fechaGen) $textoCopiado .= "Fecha: {$fechaGen}\n";
$textoCopiado .= "Banco: " . ($tx['BeneficiarioBanco'] ?? '') . "\n";
$textoCopiado .= "Beneficiario: " . ($tx['BeneficiarioNombre'] ?? '') . "\n";
if ($hasCuenta)   $textoCopiado .= "Cuenta: {$tx['BeneficiarioNumeroCuenta']}\n";
if ($hasTelefono) $textoCopiado .= "Teléfono: {$tx['BeneficiarioTelefono']}\n";
$textoCopiado .= "Doc: " . ($tx['BeneficiarioDocumento'] ?? '') . "\n";
$textoCopiado .= "Monto: " . $montoDestinoFmt;

$textoBase64 = base64_encode($textoCopiado);

$jsonData = htmlspecialchars(json_encode([
    'id'          => $tx['TransaccionID'],
    'banco'       => $tx['BeneficiarioBanco'] ?? '',
    'nombre'      => $tx['BeneficiarioNombre'] ?? '',
    'doc'         => $tx['BeneficiarioDocumento'] ?? '',
    'cuenta'      => $tx['BeneficiarioNumeroCuenta'] ?? '',
    'telefono'    => $tx['BeneficiarioTelefono'] ?? '',
    'hasCuenta'   => $hasCuenta,
    'hasTelefono' => $hasTelefono,
    'monto'       => $montoDestinoFmt
]), ENT_QUOTES, 'UTF-8');

// Atributos comunes de los dos botones que abren la ficha del cliente.
// ENT_QUOTES explícito: estos valores se concatenan dentro de atributos, así que no
// se depende del flag por defecto de htmlspecialchars (que cambió en PHP 8.1).
$clienteInfoAttrs = 'data-tx-id="' . (int) $tx['TransaccionID'] . '"'
    . ' data-nombre="' . htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8') . '"'
    . ' data-telefono="' . htmlspecialchars($tx['ClienteTelefono'] ?? '', ENT_QUOTES, 'UTF-8') . '"'
    . ' data-doc="' . htmlspecialchars($tx['UsuarioDocumento'] ?? '', ENT_QUOTES, 'UTF-8') . '"';

$urlComprobanteCliente = !empty($tx['ComprobanteURL'])
    ? $secureFileBase . '?file=' . urlencode($tx['ComprobanteURL'])
    : '';
$urlComprobanteEnvio = !empty($tx['ComprobanteEnvioURL'])
    ? $secureFileBase . '?file=' . urlencode($tx['ComprobanteEnvioURL'])
    : '';
?>
<tr>
    <td>
        <button type="button" class="btn btn-link btn-sm p-0 btn-cliente-info" <?php echo $clienteInfoAttrs; ?>>
            #<?php echo $tx['TransaccionID']; ?>
        </button>
    </td>
    <td class="search-user">
        <button type="button" class="btn btn-link p-0 text-start btn-cliente-info fw-bold fs-6" <?php echo $clienteInfoAttrs; ?>>
            <?php echo htmlspecialchars($nombreCliente); ?>
        </button>
    </td>
    <td class="search-beneficiary">
        <?php echo htmlspecialchars($tx['BeneficiarioNombreCompleto'] ?? ''); ?>
        <?php
            $previos = (int)($tx['EnviosPreviosMismaCuenta'] ?? 0);
            if ($previos > 0):
                $color = $previos >= 5 ? 'bg-warning text-dark' : 'bg-info text-white';
        ?>
            <button type="button"
                    class="badge <?php echo $color; ?> border-0 view-prev-sends-btn ms-1"
                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                    title="Ver envíos previos exitosos a esta misma cuenta"
                    style="cursor:pointer;font-size:0.7rem;">
                <i class="bi bi-arrow-repeat"></i> Envío #<?php echo $previos + 1; ?>
            </button>
        <?php endif; ?>
    </td>
    <td><?php echo date("d/m/y H:i", strtotime($tx['FechaTransaccion'])); ?></td>
    <td><?php echo number_format($tx['MontoOrigen'] ?? 0, 2, ',', '.'); ?> <span class="text-muted small"><?php echo htmlspecialchars($tx['MonedaOrigen'] ?? ''); ?></span></td>
    <td><?php echo number_format($tx['MontoDestino'] ?? 0, 2, ',', '.'); ?> <span class="text-muted small"><?php echo htmlspecialchars($tx['MonedaDestino'] ?? ''); ?></span></td>
    <td><?php echo !empty($tx['FechaSubidaComprobante']) ? date("d/m/y H:i", strtotime($tx['FechaSubidaComprobante'])) : '—'; ?></td>
    <td>
        <?php if (!empty($tx['FechaCompletado'])): ?>
            <span class="text-success small">Completada<br><?php echo date("d/m/y H:i", strtotime($tx['FechaCompletado'])); ?></span>
        <?php elseif (!empty($tx['FechaCancelacion'])): ?>
            <span class="text-danger small">Cancelada<br><?php echo date("d/m/y H:i", strtotime($tx['FechaCancelacion'])); ?></span>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td>
        <span class="badge <?php echo getStatusBadgeClass($estadoNombre); ?>">
            <?php echo htmlspecialchars($estadoNombre !== '' ? $estadoNombre : 'Desconocido'); ?>
        </span>
        <?php if ($estadoNombre === 'Pausado' && !empty($tx['MotivoPausa'])): ?>
            <div class="mt-1">
                <button type="button" class="btn btn-sm py-0 px-2 rounded-pill view-pause-reason-btn"
                    data-reason="<?php echo htmlspecialchars($tx['MotivoPausa']); ?>"
                    style="font-size: 0.7rem; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                    <i class="bi bi-eye-fill me-1"></i> Ver Motivo
                </button>
            </div>
        <?php endif; ?>
        <?php
            if ($estadoNombre === 'Exitoso'):
                $conf = $tx['ConfirmacionRecepcion'] ?? 'pendiente';
                $fechaConf = !empty($tx['FechaConfirmacionRecepcion'])
                    ? date('d/m/Y H:i', strtotime($tx['FechaConfirmacionRecepcion']))
                    : '';
                if ($conf === 'recibido'):
        ?>
            <div class="mt-1">
                <span class="badge bg-success" title="Cliente confirmó recepción<?php echo $fechaConf ? ' el ' . $fechaConf : ''; ?>">
                    <i class="bi bi-check2-all"></i> Cliente recibió
                </span>
            </div>
        <?php elseif ($conf === 'no_recibido'): ?>
            <div class="mt-1">
                <span class="badge bg-danger" title="¡Atención! Cliente reportó no recibir<?php echo $fechaConf ? ' el ' . $fechaConf : ''; ?>">
                    <i class="bi bi-exclamation-triangle-fill"></i> Cliente NO recibió
                </span>
            </div>
        <?php else: ?>
            <div class="mt-1">
                <span class="badge bg-secondary opacity-75" title="El cliente aún no ha confirmado la recepción">
                    <i class="bi bi-hourglass-split"></i> Sin confirmar
                </span>
            </div>
        <?php
                endif;
            endif;
        ?>
    </td>
    <td>
        <div class="d-flex align-items-center justify-content-between">
            <span><?php echo number_format($tx['ComisionDestino'] ?? 0, 2); ?></span>
            <?php if ($puedeEditarComision && ordenEstadoPermiteEditarComision($estadoNombre)): ?>
                <button class="btn btn-sm btn-outline-primary edit-commission-btn ms-2 border-0"
                    data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                    data-current-val="<?php echo $tx['ComisionDestino'] ?? 0; ?>" title="Editar">
                    <i class="bi bi-pencil-square"></i>
                </button>
            <?php endif; ?>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex gap-1 justify-content-center align-items-center">
            <!-- Primario: Abrir orden -->
            <a href="<?php echo $ordenUrl; ?>?id=<?php echo $tx['TransaccionID']; ?>" class="btn btn-sm btn-dark" title="Abrir orden (pantalla dividida)">
                <i class="bi bi-window-split"></i>
            </a>

            <!-- Primario contextual: Pagar (solo En Proceso) -->
            <?php if ($puedePagar && $estadoNombre === 'En Proceso'): ?>
                <button class="btn btn-sm btn-primary admin-upload-btn" data-bs-toggle="modal" data-bs-target="#adminUploadModal" data-tx-id="<?php echo $tx['TransaccionID']; ?>" data-monto-destino="<?php echo $tx['MontoDestino']; ?>" data-pais-id="<?php echo $tx['PaisDestinoID'] ?? ''; ?>" data-moneda-destino="<?php echo htmlspecialchars($tx['MonedaDestino'] ?? ''); ?>" title="Pagar">
                    <i class="bi bi-currency-dollar"></i>
                </button>
            <?php endif; ?>

            <!-- Menú con el resto -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Más acciones">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <button class="dropdown-item js-copy-b64-btn" type="button" data-copy-b64="<?php echo $textoBase64; ?>">
                            <i class="bi bi-clipboard-check me-2"></i> Copiar datos
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item copy-data-btn" type="button" data-datos="<?php echo $jsonData; ?>">
                            <i class="bi bi-eye me-2"></i> Copiar por partes
                        </button>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo $facturaUrl; ?>?id=<?php echo $tx['TransaccionID']; ?>" target="_blank">
                            <i class="bi bi-file-earmark-pdf me-2"></i> Descargar PDF
                        </a>
                    </li>
                    <?php if ($urlComprobanteCliente !== ''): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                            data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                            data-nombre-titular="<?php echo htmlspecialchars($nombreTitular); ?>"
                            data-rut-titular="<?php echo htmlspecialchars($rutTitular); ?>"
                            data-comprobante-url="<?php echo htmlspecialchars($urlComprobanteCliente); ?>"
                            data-envio-url="<?php echo htmlspecialchars($urlComprobanteEnvio); ?>"
                            data-start-type="user">
                            <i class="bi bi-eye me-2"></i> Ver comprobante cliente
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($urlComprobanteEnvio !== ''): ?>
                    <li>
                        <button class="dropdown-item view-comprobante-btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#viewComprobanteModal"
                            data-tx-id="<?php echo $tx['TransaccionID']; ?>"
                            data-nombre-titular="<?php echo htmlspecialchars($nombreTitular); ?>"
                            data-rut-titular="<?php echo htmlspecialchars($rutTitular); ?>"
                            data-comprobante-url="<?php echo htmlspecialchars($urlComprobanteCliente); ?>"
                            data-envio-url="<?php echo htmlspecialchars($urlComprobanteEnvio); ?>"
                            data-start-type="admin">
                            <i class="bi bi-receipt me-2"></i> Ver comprobante envío
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </td>
</tr>
