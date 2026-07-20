<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

// Si falta correr las migraciones (tabla inexistente) u otro error de BD,
// no debe explotar en blanco antes de imprimir el HTML: se degrada a
// "Próximamente" en vez de dejar la página vacía.
$row = null;
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT Contenido, FechaActualizacion FROM normas WHERE Id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
} catch (\Throwable $e) {
    error_log('normas.php: ' . $e->getMessage());
}

$contenido = $row['Contenido'] ?? '';
$fechaActualizacion = $row['FechaActualizacion'] ?? null;

$pageTitle = 'Normas de Uso';
require_once __DIR__ . '/../remesas_private/src/templates/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="text-center mb-4">
                <h1 class="display-6 fw-bold">Normas de Uso</h1>
                <p class="lead text-muted">Normas y condiciones operativas de JC Envíos.</p>
            </div>

            <div class="card shadow-sm border-0 p-4 p-md-5">
                <?php if (trim((string) $contenido) !== ''): ?>
                    <?php echo $contenido; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-file-earmark-text display-4 d-block mb-3"></i>
                        Próximamente
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($fechaActualizacion): ?>
                <p class="text-center text-muted small mt-3">
                    Actualizado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($fechaActualizacion))); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../remesas_private/src/templates/footer.php';
?>
