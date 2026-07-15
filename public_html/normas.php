<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

$db = Database::getInstance();
$stmt = $db->prepare("SELECT Contenido, FechaActualizacion FROM normas WHERE Id = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

$contenido = $row['Contenido'] ?? '';
$fechaActualizacion = $row['FechaActualizacion'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Normas | JC Envíos</title>
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 0; background: #f4f6f9; color: #1a1a2e; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 2rem 1rem 3rem; }
        .header-area { text-align: center; }
        .logo { max-height: 56px; margin-bottom: 1.25rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        .subtitle { color: #666; margin: 0 0 1.75rem; font-size: .95rem; }
        .card-normas { background: #fff; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,.08); padding: 1.75rem; text-align: left; line-height: 1.65; }
        .card-normas h1, .card-normas h2, .card-normas h3, .card-normas h4 { margin-top: 1.25rem; }
        .card-normas h1:first-child, .card-normas h2:first-child, .card-normas h3:first-child, .card-normas h4:first-child, .card-normas p:first-child { margin-top: 0; }
        .card-normas a { color: #0d6efd; }
        .empty { padding: 3rem 1rem; color: #888; text-align: center; }
        .empty i { display: block; font-size: 2rem; margin-bottom: .5rem; }
        .fecha { margin-top: 1.25rem; font-size: .8rem; color: #999; text-align: center; }
        .footer-link { margin-top: 2rem; font-size: .85rem; text-align: center; }
        .footer-link a { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header-area">
        <img class="logo" src="<?php echo BASE_URL; ?>/assets/img/LogoNegroSinFondo.png" alt="JC Envíos">
        <h1>Normas de Uso</h1>
        <p class="subtitle">Normas y condiciones operativas de JC Envíos.</p>
    </div>

    <div class="card-normas">
        <?php if (trim((string)$contenido) !== ''): ?>
            <?php echo $contenido; ?>
        <?php else: ?>
            <div class="empty">
                <span>📄</span>
                Próximamente
            </div>
        <?php endif; ?>
    </div>

    <?php if ($fechaActualizacion): ?>
        <p class="fecha">Actualizado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($fechaActualizacion))); ?></p>
    <?php endif; ?>

    <p class="footer-link"><a href="<?php echo BASE_URL; ?>/index.php">Volver a JC Envíos</a></p>
</div>
</body>
</html>
