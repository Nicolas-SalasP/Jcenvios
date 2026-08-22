<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}

$pageTitle = 'Datos Bancarios';
$pageScript = 'datos-bancarios.js';

require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4 mb-5">
    <h1 class="h3 mb-3">Datos Bancarios por País</h1>
    <p class="text-muted">Selecciona el país desde el que vas a transferir para ver las cuentas disponibles.</p>

    <div class="mb-4" style="max-width: 350px;">
        <label for="selectPaisOrigen" class="form-label fw-bold">País de origen</label>
        <select id="selectPaisOrigen" class="form-select">
            <option value="">Selecciona un país...</option>
        </select>
    </div>

    <div id="cuentasContainer" class="row g-3"></div>
    <div id="cuentasEmpty" class="alert alert-light border d-none">
        No hay cuentas bancarias activas para el país seleccionado. Contáctanos si necesitas ayuda.
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
