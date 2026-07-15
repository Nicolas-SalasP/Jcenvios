<?php
require_once __DIR__ . '/../../remesas_private/src/core/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit();
}
if (!isset($_SESSION['user_rol_name']) || $_SESSION['user_rol_name'] !== 'Admin') {
    http_response_code(403);
    die("Acceso denegado.");
}

$pageTitle = 'Normas';
$pageScript = 'admin-normas.js';
require_once __DIR__ . '/../../remesas_private/src/templates/header.php';
?>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Normas</h1>
            <p class="text-muted mb-0 mt-1">Edita el contenido de la página pública de Normas. Los cambios se publican de inmediato.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/normas.php" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right me-1"></i> Ver página pública
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <div class="alert alert-light border d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
        <div class="small text-muted">
            Puedes usar HTML básico (párrafos, negritas, cursivas, listas, títulos y enlaces).
            El contenido se filtra automáticamente en el servidor por seguridad: cualquier etiqueta no permitida
            (scripts, estilos, formularios, etc.) será eliminada al guardar.
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Contenido</h5>
            <p class="small text-muted mb-0" id="fechaActualizacion">Cargando...</p>
        </div>
        <div class="card-body p-4">
            <form id="formNormas">
                <div class="mb-3">
                    <label for="contenidoNormas" class="form-label fw-semibold">Texto de las Normas (HTML permitido)</label>
                    <textarea class="form-control" id="contenidoNormas" name="contenido" rows="18" placeholder="Escribe aquí el contenido de las Normas..." required></textarea>
                    <div class="form-text">
                        Tags permitidos: &lt;p&gt; &lt;br&gt; &lt;strong&gt; &lt;b&gt; &lt;em&gt; &lt;i&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;h1&gt;-&lt;h4&gt; &lt;a href="..."&gt; &lt;blockquote&gt; &lt;span&gt;
                    </div>
                </div>
                <div class="alert alert-danger d-none" id="errorNormas"></div>
                <div class="alert alert-success d-none" id="successNormas"></div>
                <button type="submit" class="btn btn-primary" id="btnGuardarNormas">
                    <i class="bi bi-save me-1"></i> Guardar cambios
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../remesas_private/src/templates/footer.php'; ?>
