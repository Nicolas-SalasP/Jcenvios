<?php
/**
 * Visor de comprobantes para el personal (admin y operador).
 *
 * Fuente única del modal #viewComprobanteModal en las páginas de staff. Lo consume
 * el handler de `show.bs.modal` de assets/js/pages/admin-transacciones.js, que
 * espera estos ids: visor-nombre-titular, visor-rut-titular, comprobante-img-full,
 * comprobante-pdf-full, comprobante-placeholder, tab-btn-user, tab-btn-admin y
 * download-comprobante-btn.
 *
 * IMPORTANTE: incluir SIEMPRE antes de footer.php, que define su propio visor para
 * el cliente. La bandera de abajo evita que se emitan los dos y quede el id duplicado.
 *
 * Antes existían tres copias de este modal (admin/pendientes.php, operador/pendientes.php
 * y orden_modals.php) y las dos primeras no traían ni los tabs para alternar entre el
 * comprobante del cliente y el del envío, ni el botón de descarga.
 */

$GLOBALS['visorComprobanteYaRenderizado'] = true;
?>
<div class="modal fade" id="viewComprobanteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" id="modal-content-visor">
            <div class="modal-header py-2 bg-dark text-white">
                <h5 class="modal-title fs-6"><i class="bi bi-eye"></i> Revisión de Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0 d-flex flex-column flex-lg-row">
                <div class="bg-light p-3 border-bottom border-lg-bottom-0 border-lg-end overflow-auto sidebar-datos">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Datos del Titular (Origen)</h6>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Nombre Titular</label>
                        <div class="fs-6 text-dark text-break" id="visor-nombre-titular">Cargando...</div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">RUT / Documento</label>
                        <div class="fs-6 text-dark" id="visor-rut-titular">Cargando...</div>
                    </div>

                    <hr>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary active" id="tab-btn-user">Pago Cliente</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-btn-admin">Envío Admin</button>
                    </div>

                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle-fill"></i> Verifique que estos datos coincidan con la imagen del
                        comprobante.
                    </div>
                </div>

                <div class="flex-grow-1 bg-dark d-flex align-items-center justify-content-center position-relative visor-container">
                    <div id="comprobante-placeholder" class="spinner-border text-light"></div>
                    <div id="comprobante-content" class="w-100 h-100 d-flex align-items-center justify-content-center p-2">
                        <img id="comprobante-img-full" class="d-none shadow rounded"
                            style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Comprobante">
                        <iframe id="comprobante-pdf-full" class="w-100 h-100 d-none rounded border-0"
                            style="background:white;" loading="lazy"></iframe>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-center py-1 bg-light">
                <a href="#" id="download-comprobante-btn" class="btn btn-sm btn-dark" download target="_blank">
                    <i class="bi bi-download me-2"></i>Descargar
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    #modal-content-visor {
        height: auto;
        min-height: 80vh;
    }

    #modal-content-visor .sidebar-datos {
        width: 100%;
        max-height: 300px;
    }

    #modal-content-visor .visor-container {
        min-height: 50vh;
        background-color: #333;
    }

    @media (min-width: 992px) {
        #modal-content-visor {
            height: 90vh;
        }

        /* Acotado a este modal a propósito: antes la regla era `.modal-body` a secas
           y le imponía height:100% y overflow:hidden a los otros modales de la
           página (pausa, rechazo, pago, copiar datos), recortándoles el contenido. */
        #modal-content-visor .modal-body {
            height: 100%;
            overflow: hidden;
        }

        #modal-content-visor .sidebar-datos {
            width: 320px;
            min-width: 320px;
            height: 100%;
            max-height: none;
        }

        #modal-content-visor .visor-container {
            height: 100%;
        }
    }
</style>
