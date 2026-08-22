(function () {
    const appDataEl = document.getElementById('app-data');
    if (appDataEl && appDataEl.dataset.cuentasDestino) {
        try {
            window.cuentasDestino = JSON.parse(appDataEl.dataset.cuentasDestino);
        } catch (e) {
            window.cuentasDestino = [];
        }
    }
})();

document.addEventListener('DOMContentLoaded', () => {

    // Escapa texto libre del usuario antes de insertarlo como HTML (XSS
    // almacenado, ver auditoría 2026-08-21). Vive en utils/domUtils.js, que
    // footer.php carga antes de cualquier script de página.
    const escapeHtml = window.escapeHtml;

    window.refreshAdminTable = async () => {
        const tbody = document.getElementById('transactionsTableBody');
        if (tbody) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('ajax', '1');
            try {
                const response = await fetch(currentUrl);
                if (response.ok) {
                    tbody.innerHTML = await response.text();
                }
            } catch (e) { console.warn(e); }
        } else if (!document.querySelector('.modal.show:not(#infoModal)')) {
            // Solo forzar recarga completa si no hay un modal de trabajo abierto
            // (ej. "Subir Comprobante"). Recargar con un modal abierto le hace
            // perder al usuario el archivo/datos que estaba ingresando.
            window.location.reload();
        }
    };

    // =================================================
    // 0. UTILIDADES GLOBALES & HELPERS
    // =================================================
    // showConfirmModal/showInfoModal ya vienen globales desde
    // assets/js/utils/modalUtils.js (cargado en toda página vía footer.php).

    window.showPromptModal = (title, message, placeholder = '', requireText = true) => {
        return new Promise((resolve) => {
            const id = 'js-global-prompt-modal';
            const existing = document.getElementById(id);
            if (existing) existing.remove();

            const modalEl = document.createElement('div');
            modalEl.id = id;
            modalEl.className = 'modal fade';
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>${title}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 fs-6">
                            <p class="mb-3 text-dark">${message}</p>
                            <textarea id="js-global-prompt-input" class="form-control" rows="3" placeholder="${placeholder}"></textarea>
                            ${!requireText ? '<div class="form-text text-muted small">Este campo es opcional.</div>' : ''}
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary px-4" id="js-global-prompt-btn">Confirmar y Guardar</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modalEl);

            const modal = new bootstrap.Modal(modalEl);
            const confirmBtn = document.getElementById('js-global-prompt-btn');
            const inputEl = document.getElementById('js-global-prompt-input');

            let isConfirmed = false;

            confirmBtn.onclick = () => {
                const val = inputEl.value.trim();
                // La validación del motivo es opcional según parámetro requireText.
                // Antes era obligatorio (mín 5 chars) en TODOS los casos.
                if (requireText && val.length < 5) {
                    window.showInfoModal('Faltan Datos', 'El motivo es obligatorio y debe tener al menos 5 caracteres', false);
                    return;
                }
                isConfirmed = true;
                modal.hide();
                resolve(val); // si no es requerido y queda vacío, devuelve string vacío
            };

            modalEl.addEventListener('hidden.bs.modal', () => {
                if (!isConfirmed) resolve(null);
                modalEl.remove();
                setTimeout(() => {
                    if (!document.querySelector('.modal.show')) {
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    }
                }, 100);
            });

            modal.show();
        });
    };

    window.copyToClipboard = (elementId, btnElement) => {
        const input = document.getElementById(elementId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);

        navigator.clipboard.writeText(input.value).then(() => {
            showFeedback(btnElement);
        }).catch(() => {
            document.execCommand('copy');
            showFeedback(btnElement);
        });
    };

    function showFeedback(btn) {
        const originalHtml = btn.innerHTML;
        const originalClass = btn.className;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.classList.remove('btn-outline-secondary', 'btn-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.className = originalClass;
        }, 2000);
    }

    // =========================================================
    // MOTOR DE ALERTAS EN TIEMPO REAL (SONIDO Y BADGES)
    // =========================================================
    const initAdminAlerts = () => {
        const audioEl = document.getElementById('admin-alert-sound');
        const btnToggleSound = document.getElementById('btn-toggle-sound');
        const iconSound = document.getElementById('icon-sound-status');
        let soundEnabled = localStorage.getItem('adminSoundEnabled') !== 'false';

        const updateSoundUI = () => {
            if (iconSound) {
                iconSound.className = soundEnabled ? 'bi bi-bell-fill text-warning' : 'bi bi-bell-slash text-muted';
            }
        };
        updateSoundUI();

        if (btnToggleSound) {
            btnToggleSound.addEventListener('click', () => {
                soundEnabled = !soundEnabled;
                localStorage.setItem('adminSoundEnabled', soundEnabled);
                updateSoundUI();
                if (soundEnabled && audioEl) audioEl.play().catch(e => console.warn(e));
            });
        }

        const updateBadge = (id, count, isDanger = false) => {
            const badge = document.getElementById(id);
            if (!badge) return;
            badge.textContent = count;
            if (count > 0) {
                badge.classList.remove('d-none');
                if (isDanger) {
                    badge.classList.add('bg-danger');
                    badge.classList.add('animate-pulse');
                }
            } else {
                badge.classList.add('d-none');
            }
        };

        const fetchAlerts = async () => {
            try {
                const res = await fetch('../api/?accion=getAdminAlerts');
                if (!res.ok) return;
                const json = await res.json();

                if (json.success && json.data) {
                    const data = json.data;
                    updateBadge('badge-verificacion', data.en_verificacion, true); // Pulso rojo porque requiere revisar pago
                    updateBadge('badge-proceso', data.en_proceso, false);          // Azul, requiere enviar el dinero
                    updateBadge('badge-pausadas', data.pausadas, false);           // Naranja
                    updateBadge('badge-riesgo', data.riesgo, true);                // Pulso oscuro
                    
                    const currentMaxId = data.ultimo_id_relevante;
                    const lastSeenId = parseInt(localStorage.getItem('lastSeenTxId') || '0');

                    if (currentMaxId > lastSeenId) {
                        // No interrumpir si el operador tiene un modal de trabajo abierto
                        // (ej. pago en curso o confirmación). Se difiere el aviso al
                        // próximo ciclo de polling (10s) sin marcarlo como "visto".
                        const hayModalActivo = document.querySelector('.modal.show:not(#infoModal)');
                        if (hayModalActivo) {
                            // IMPORTANTE: No llamar a window.refreshAdminTable() aquí. En páginas sin
                            // #transactionsTableBody (admin/orden.php y operador/pendientes.php),
                            // refreshAdminTable() cae a window.location.reload(), lo que recargaba
                            // la página completa mientras el usuario tenía el modal de "Subir
                            // Comprobante" abierto, perdiendo el archivo seleccionado. Nos limitamos
                            // a actualizar los badges (ya hecho arriba) y diferimos todo lo demás.
                            return;
                        }

                        localStorage.setItem('lastSeenTxId', currentMaxId);

                        if (soundEnabled && audioEl) {
                            audioEl.play().catch(err => console.warn("Auto-play bloqueado:", err));
                        }

                        window.showInfoModal('¡Atención Requerida!', `Una orden requiere tu atención (ID #${currentMaxId}).`, true);

                        if (typeof window.refreshAdminTable === 'function') {
                            window.refreshAdminTable();
                        }
                    }
                }
            } catch (error) {
                console.warn('Error fetching alerts:', error);
            }
        };

        fetchAlerts();
        setInterval(fetchAlerts, 10000);
    };

    initAdminAlerts();

    // =================================================
    // 1. OPERADORES: LÓGICA DE COPIADO DE DATOS
    // =================================================
    const copyModalElement = document.getElementById('copyDataModal');
    if (copyModalElement) {
        const copyModal = new bootstrap.Modal(copyModalElement);
        const fields = {
            banco: document.getElementById('copy-banco'),
            doc: document.getElementById('copy-doc'),
            cuenta: document.getElementById('copy-cuenta'),
            telefono: document.getElementById('copy-telefono'),
            divCuenta: document.getElementById('container-cuenta'),
            divTelefono: document.getElementById('container-telefono'),
            nombre: document.getElementById('copy-nombre'),
            montoDisplay: document.getElementById('copy-monto-display'),
            montoValue: document.getElementById('copy-monto-value'),
            txId: document.getElementById('copy-tx-id'),
            btnFinalizar: document.getElementById('btn-ir-a-finalizar')
        };

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.copy-data-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();

                try {
                    const data = JSON.parse(btn.dataset.datos);

                    if (fields.txId) fields.txId.textContent = data.id;
                    if (fields.banco) fields.banco.value = data.banco;
                    if (fields.doc) fields.doc.value = data.doc;
                    if (fields.nombre) fields.nombre.value = data.nombre;
                    if (fields.montoDisplay) fields.montoDisplay.textContent = data.monto;
                    if (fields.montoValue) fields.montoValue.value = data.monto;
                    if (fields.divCuenta && fields.cuenta) {
                        if (data.hasCuenta) {
                            fields.cuenta.value = data.cuenta;
                            fields.divCuenta.style.display = 'block';
                        } else {
                            fields.divCuenta.style.display = 'none';
                        }
                    }
                    if (fields.divTelefono && fields.telefono) {
                        if (data.hasTelefono) {
                            fields.telefono.value = data.telefono;
                            fields.divTelefono.style.display = 'block';
                        } else {
                            fields.divTelefono.style.display = 'none';
                        }
                    }

                    if (fields.btnFinalizar) {
                        fields.btnFinalizar.onclick = () => {
                            copyModal.hide();
                            const uploadBtn = document.querySelector(`button[data-bs-target="#adminUploadModal"][data-tx-id="${data.id}"]`);
                            if (uploadBtn) {
                                uploadBtn.click();
                            } else {
                                const adminModal = new bootstrap.Modal(document.getElementById('adminUploadModal'));
                                const adminTxLabel = document.getElementById('modal-admin-tx-id');
                                const adminTxField = document.getElementById('adminTransactionIdField');
                                if (adminTxLabel) adminTxLabel.textContent = data.id;
                                if (adminTxField) adminTxField.value = data.id;
                                adminModal.show();
                            }
                        };
                    }
                    copyModal.show();
                } catch (e) { console.error("Error procesando datos del modal:", e); }
            }
        });
    }
});
