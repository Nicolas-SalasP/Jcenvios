<?php ?>
</main> <?php ?>

<footer class="main-footer bg-dark text-white pt-3 pb-1 mt-auto">
  <div class="container text-center text-md-start">
    <div class="row">
      <div class="col-md-3 col-lg-4 col-xl-3 mx-auto mb-4">
        <img src="<?php echo BASE_URL; ?>/assets/img/LogoBlancoSinFondo.png" alt="Logo JC Envios Blanco" height="80">
        <p class="mt-2 small">
          La forma más rápida, segura y confiable de enviar dinero a tus seres queridos.
        </p>
      </div>

      <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mb-4">
        <h6 class="text-uppercase fw-bold mb-3">Enlaces Rápidos</h6>
        <p class="mb-2"><a href="<?php echo BASE_URL; ?>/quienes-somos.php" class="text-reset">Quiénes Somos</a></p>
        <p class="mb-2"><a href="<?php echo BASE_URL; ?>/contacto.php" class="text-reset">Contacto</a></p>
        <p class="mb-2"><a href="#!" class="text-reset">Preguntas Frecuentes</a></p>
      </div>

      <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mb-md-0 mb-4">
        <h6 class="text-uppercase fw-bold mb-3">Contacto</h6>
        <p class="mb-2"><i class="bi bi-geo-alt-fill me-2"></i>Agustinas 681, Santiago, Chile</p>
        <p class="mb-2"><i class="bi bi-envelope-fill me-2"></i>multiserviciosjcspachile@gmail.com</p>
        <p class="mb-2"><i class="bi bi-telephone-fill me-2"></i>+56 9 2382 6018</p>
      </div>
    </div>
  </div>

  <div class="text-center p-2 border-top border-secondary mt-2">
    <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center">
      <span class="text-muted mb-2 mb-sm-0">&copy; <?php echo date('Y'); ?> JC Envios. Todos los derechos
        reservados.</span>
      <div>
        <a href="#!" class="btn btn-outline-light btn-floating m-1" role="button" aria-label="Facebook"><i
            class="bi bi-facebook"></i></a>
        <a href="#!" class="btn btn-outline-light btn-floating m-1" role="button" aria-label="Twitter"><i
            class="bi bi-twitter-x"></i></a>
        <a href="https://www.instagram.com/enviosjc?utm_source=qr&igsh=MTQybWcwdmRobGxybw==" target="_blank"
          rel="noopener noreferrer" class="btn btn-outline-light btn-floating m-1" role="button"
          aria-label="Instagram"><i class="bi bi-instagram"></i></a>
      </div>
    </div>
  </div>
</footer>

<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" id="infoModalHeader">
        <h5 class="modal-title" id="infoModalTitle"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="infoModalBody"> </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal" id="infoModalCloseBtn">Aceptar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="confirmModalBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="confirmModalCancelBtn"
          data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmModalConfirmBtn">Confirmar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-labelledby="viewComprobanteModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewComprobanteModalLabel">Visor de Comprobantes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div id="comprobante-navigation" class="mb-2 d-flex justify-content-center align-items-center d-none">
          <button class="btn btn-outline-secondary btn-sm me-2" id="prev-comprobante" aria-label="Anterior">
            <i class="bi bi-arrow-left"></i>
          </button>
          <span id="comprobante-indicator" class="text-muted small"></span>
          <button class="btn btn-outline-secondary btn-sm ms-2" id="next-comprobante" aria-label="Siguiente">
            <i class="bi bi-arrow-right"></i>
          </button>
        </div>
        <div id="comprobante-content" style="min-height: 400px; background-color: #f8f9fa;">
          <p id="comprobante-placeholder" class="text-muted p-5">Cargando comprobante...</p>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <span id="comprobante-filename" class="text-muted small text-truncate" style="max-width: 50%;"></span>
        <div>
          <a href="#" id="download-comprobante" class="btn btn-primary" download>Descargar</a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userDetailsModalLabel">Ficha del Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h4 id="modalUserNombreCompleto">Cargando...</h4>
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                <strong class="me-2">Email:</strong>
                <span id="modalUserEmail" class="text-muted text-truncate">...</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Teléfono:</strong>
                <span id="modalUserTelefono" class="text-muted">...</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Miembro desde:</strong>
                <span id="modalUserFechaRegistro" class="text-muted">...</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Estado Verificación:</strong>
                <span id="modalUserVerificacion" class="badge">...</span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Seguridad 2FA:</strong>
                <span id="modalUser2FA" class="badge">...</span>
              </li>
            </ul>
          </div>
          <div class="col-md-6">
            <h5>Documentos de Verificación</h5>
            <div class="row">
              <div class="col-6 text-center">
                <strong>Frente:</strong>
                <div id="modalUserDocFrenteContainer" class="mt-2 border rounded p-2" style="min-height: 100px;">
                </div>
              </div>
              <div class="col-6 text-center">
                <strong>Reverso:</strong>
                <div id="modalUserDocReversoContainer" class="mt-2 border rounded p-2" style="min-height: 100px;">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editPaisModal" tabindex="-1" aria-labelledby="editPaisModalLabel" aria-hidden="true"
  data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editPaisModalLabel">Editar País</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="edit-pais-form">
        <div class="modal-body">
          <input type="hidden" id="edit-pais-id" name="paisId">

          <div class="mb-3">
            <label for="edit-nombrePais" class="form-label">Nombre del País</label>
            <input type="text" class="form-control" id="edit-nombrePais" name="nombrePais" required>
          </div>

          <div class="mb-3">
            <label for="edit-codigoMoneda" class="form-label">Código de Moneda (3 letras)</label>
            <input type="text" class="form-control" id="edit-codigoMoneda" name="codigoMoneda" required maxlength="3"
              style="text-transform:uppercase">
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
$jsUtilsPath = '/assets/js/utils/modalUtils.js';
$jsUtilsFilePath = __DIR__ . '/../../../public_html' . $jsUtilsPath;
$jsUtilsVersion = file_exists($jsUtilsFilePath) ? hash_file('md5', $jsUtilsFilePath) : '1.0.0';
?>
<script src="<?php echo BASE_URL; ?>/assets/js/system-lock.js"></script>
<script src="<?php echo BASE_URL . $jsUtilsPath; ?>?v=<?php echo $jsUtilsVersion; ?>" charset="UTF-8"></script>
<?php ?>

<script>
  const baseUrlJs = <?php echo defined('BASE_URL') ? json_encode(rtrim(BASE_URL, '/')) : '""'; ?>;
  const CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';
</script>
<?php ?>

<?php

$baseUrlPhp = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

if (!empty($baseUrlPhp)) {

  function get_js_version($scriptPath)
  {
    $physicalPath = __DIR__ . '/../../../public_html' . $scriptPath;
    if (file_exists($physicalPath)) {
      return hash_file('md5', $physicalPath);
    }
    return '1.0.0';
  }

  if (isset($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $script) {
      if (!empty($script) && pathinfo($script, PATHINFO_EXTENSION) === 'js') {
        $scriptPath = ltrim($script, '/');
        if (strpos($scriptPath, 'pages/') === 0 || strpos($scriptPath, 'components/') === 0) {
          $filePath = '/assets/js/' . htmlspecialchars($scriptPath);
        } else {
          $filePath = '/assets/js/pages/' . htmlspecialchars($scriptPath);
        }
        $jsVersion = get_js_version($filePath);
        echo '<script src="' . $baseUrlPhp . $filePath . '?v=' . $jsVersion . '" charset="UTF-8"></script>' . "\n";
      }
    }
  } elseif (isset($pageScript) && !empty($pageScript) && pathinfo($pageScript, PATHINFO_EXTENSION) === 'js') {
    $filePath = '/assets/js/pages/' . htmlspecialchars($pageScript);
    $jsVersion = get_js_version($filePath);
    echo '<script src="' . $baseUrlPhp . $filePath . '?v=' . $jsVersion . '" charset="UTF-8"></script>' . "\n";
  }
} else {
  error_log("Advertencia: La constante BASE_URL no está definida. No se incluirán scripts JS específicos.");
}
?>

<script>
  document.addEventListener('contextmenu', function (e) {
    if (e.target.tagName === 'IMG' &&
      (e.target.closest('#viewComprobanteModal') || e.target.closest('#userDetailsModal'))) {
      e.preventDefault();
    }
  });

  document.addEventListener('click', function (e) {
    const toggleBtn = e.target.closest('.toggle-password');
    if (!toggleBtn) return;
    const input = toggleBtn.previousElementSibling;
    const icon = toggleBtn.querySelector('i');

    if (input && input.tagName === 'INPUT' && icon) {
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash-fill');
        icon.classList.add('bi-eye-fill');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-fill');
        icon.classList.add('bi-eye-slash-fill');
      }
    }
  });
</script>
</body>

</html>