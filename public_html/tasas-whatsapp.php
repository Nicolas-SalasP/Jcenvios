<?php
require_once __DIR__ . '/../remesas_private/src/core/init.php';

use App\Database\Database;

// Si falta correr las migraciones (tabla inexistente) u otro error de BD,
// no debe explotar en blanco antes de imprimir el HTML: se degrada a
// "Próximamente" en vez de dejar la página vacía.
$imagenes = [];
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT Id, Titulo, Descripcion, FechaActualizacion FROM tasas_imagen WHERE TipoFuente = 'whatsapp' ORDER BY Id ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $imagenes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} catch (\Throwable $e) {
    error_log('tasas-whatsapp.php: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasas WhatsApp | JC Envíos</title>
    <link rel="icon" href="<?php echo BASE_URL; ?>/assets/img/SoloLogoNegroSinFondo.png">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 0; background: #f4f6f9; color: #1a1a2e; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1rem 3rem; text-align: center; }
        .logo { max-height: 56px; margin-bottom: 1.25rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        .subtitle { color: #666; margin: 0 0 1.75rem; font-size: .95rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,.08); padding: 1.25rem; }
        .card img { max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 0 auto; }
        .card + .card { margin-top: 1.25rem; }
        .card-titulo { font-weight: 600; margin: .75rem 0 .25rem; }
        .card-descripcion { color: #555; font-size: .9rem; margin: 0 0 .5rem; }
        .empty { padding: 3rem 1rem; color: #888; }
        .empty i { display: block; font-size: 2rem; margin-bottom: .5rem; }
        .fecha { margin-top: 1rem; font-size: .8rem; color: #999; }
        .btn-volver { display: inline-block; margin-top: .5rem; padding: .5rem 1.25rem; background: #0d6efd; color: #fff; border-radius: 8px; text-decoration: none; font-size: .9rem; font-weight: 600; }
        .btn-volver:hover { background: #0b5ed7; }
        .back-top { margin-bottom: 1.25rem; }
        .back-bottom { margin-top: 2rem; }
    </style>
</head>
<body>
<div class="wrap">
    <p class="back-top"><a class="btn-volver" href="<?php echo BASE_URL; ?>/index.php">&larr; Volver a JC Envíos</a></p>
    <img class="logo" src="<?php echo BASE_URL; ?>/assets/img/LogoNegroSinFondo.png" alt="JC Envíos">
    <h1>Tasas de Cambio — WhatsApp</h1>
    <p class="subtitle">Tasa vigente publicada por nuestro equipo para envíos vía WhatsApp.</p>

    <div id="galeriaTasas" data-tipo="whatsapp" data-alt="Tasas WhatsApp">
        <?php if (count($imagenes) > 0): ?>
            <?php foreach ($imagenes as $img): ?>
                <?php
                    $version = strtotime($img['FechaActualizacion']);
                    $imagenUrl = rtrim(BASE_URL, '/') . '/tasas_imagen_stream.php?id=' . (int) $img['Id'] . '&v=' . $version;
                ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($imagenUrl); ?>" alt="Tasas WhatsApp">
                    <?php if (!empty($img['Titulo'])): ?>
                        <p class="card-titulo"><?php echo htmlspecialchars($img['Titulo']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($img['Descripcion'])): ?>
                        <p class="card-descripcion"><?php echo nl2br(htmlspecialchars($img['Descripcion'])); ?></p>
                    <?php endif; ?>
                    <p class="fecha">Actualizado: <?php echo htmlspecialchars(date('d/m/Y H:i', $version)); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="empty">
                    <span>📷</span>
                    Próximamente
                </div>
            </div>
        <?php endif; ?>
    </div>

    <p class="back-bottom"><a class="btn-volver" href="<?php echo BASE_URL; ?>/index.php">&larr; Volver a JC Envíos</a></p>
</div>
<script src="<?php echo BASE_URL; ?>/assets/js/pages/tasas-galeria.js"></script>
</body>
</html>
