<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    die("Acceso denegado.");
}

$pageTitle = 'Manuales de Usuario';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';

$manuales = [
    [
        'titulo'      => 'Manual del Cliente',
        'descripcion' => 'Guía completa para clientes: registro, verificación KYC, envío de remesas, historial y perfil.',
        'icono'       => 'bi-person-circle',
        'color'       => 'primary',
        'archivo'     => 'Manual_Cliente_JCEnvios_v1.pdf',
        'capitulos'   => 11,
    ],
    [
        'titulo'      => 'Manual del Administrador',
        'descripcion' => 'Panel completo de administración: órdenes, KYC, usuarios, tasas, contabilidad, logs y más.',
        'icono'       => 'bi-shield-lock-fill',
        'color'       => 'danger',
        'archivo'     => 'Manual_Admin_JCEnvios_v1.pdf',
        'capitulos'   => 13,
    ],
    [
        'titulo'      => 'Manual del Operador',
        'descripcion' => 'Flujo operativo para procesar pagos: cola de pendientes, verificar comprobantes, pausar y rechazar.',
        'icono'       => 'bi-gear-fill',
        'color'       => 'warning',
        'archivo'     => 'Manual_Operador_JCEnvios_v1.pdf',
        'capitulos'   => 6,
    ],
    [
        'titulo'      => 'Manual del Revendedor',
        'descripcion' => 'Panel de revendedor: comisiones, liquidaciones, historial de transacciones y perfil de cuenta.',
        'icono'       => 'bi-shop',
        'color'       => 'success',
        'archivo'     => 'Manual_Revendedor_JCEnvios_v1.pdf',
        'capitulos'   => 5,
    ],
];
?>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0 fw-bold"><i class="bi bi-book me-2 text-primary"></i>Manuales de Usuario</h1>
            <p class="text-muted mb-0 mt-1">Documentación oficial de JC Envíos — Versión 1</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver al panel
        </a>
    </div>

    <div class="row g-4">
        <?php foreach ($manuales as $m): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="rounded-circle bg-<?php echo $m['color']; ?> bg-opacity-10 p-3 flex-shrink-0">
                            <i class="bi <?php echo $m['icono']; ?> fs-4 text-<?php echo $m['color']; ?>"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($m['titulo']); ?></h5>
                            <span class="badge bg-<?php echo $m['color']; ?> bg-opacity-10 text-<?php echo $m['color']; ?> border border-<?php echo $m['color']; ?> border-opacity-25">
                                <?php echo $m['capitulos']; ?> capítulos
                            </span>
                        </div>
                    </div>
                    <p class="text-muted mb-4" style="font-size:.95rem;">
                        <?php echo htmlspecialchars($m['descripcion']); ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>/docs/manuales/<?php echo rawurlencode($m['archivo']); ?>"
                       class="btn btn-<?php echo $m['color']; ?> w-100"
                       target="_blank" download>
                        <i class="bi bi-file-earmark-pdf me-2"></i> Descargar PDF
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-light border mt-5 d-flex align-items-center gap-3" role="alert">
        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
        <div class="small text-muted">
            Los manuales se generan a partir del sistema en producción. Ante cualquier cambio significativo en la plataforma, regenerar los documentos para mantenerlos actualizados.
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>