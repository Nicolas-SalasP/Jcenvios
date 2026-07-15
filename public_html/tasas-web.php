<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

$db = Database::getInstance();
$stmt = $db->prepare("SELECT RutaImagen, FechaActualizacion FROM tasas_imagen WHERE TipoFuente = 'web' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

$imagenDisponible = $row && !empty($row['RutaImagen']);
$version = $imagenDisponible ? strtotime($row['FechaActualizacion']) : time();
$imagenUrl = $imagenDisponible ? (rtrim(BASE_URL, '/') . '/tasas_imagen_stream.php?tipo=web&v=' . $version) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasas Web | JC Envíos</title>
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 0; background: #f4f6f9; color: #1a1a2e; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1rem 3rem; text-align: center; }
        .logo { max-height: 56px; margin-bottom: 1.25rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        .subtitle { color: #666; margin: 0 0 1.75rem; font-size: .95rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,.08); padding: 1.25rem; }
        .card img { max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 0 auto; }
        .empty { padding: 3rem 1rem; color: #888; }
        .empty i { display: block; font-size: 2rem; margin-bottom: .5rem; }
        .fecha { margin-top: 1rem; font-size: .8rem; color: #999; }
        .footer-link { margin-top: 2rem; font-size: .85rem; }
        .footer-link a { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <img class="logo" src="<?php echo BASE_URL; ?>/assets/img/LogoNegroSinFondo.png" alt="JC Envíos">
    <h1>Tasas de Cambio — Web</h1>
    <p class="subtitle">Tasa vigente publicada por nuestro equipo para la web.</p>

    <div class="card">
        <?php if ($imagenDisponible): ?>
            <img src="<?php echo htmlspecialchars($imagenUrl); ?>" alt="Tasas Web">
            <p class="fecha">Actualizado: <?php echo htmlspecialchars(date('d/m/Y H:i', $version)); ?></p>
        <?php else: ?>
            <div class="empty">
                <span>📷</span>
                Próximamente
            </div>
        <?php endif; ?>
    </div>

    <p class="footer-link"><a href="<?php echo BASE_URL; ?>/normas.php">Ver Normas de Uso</a> · <a href="<?php echo BASE_URL; ?>/index.php">Volver a JC Envíos</a></p>
</div>
</body>
</html>
