(function () {
  const baseUrl = document.body.dataset.baseUrl || '';

  // Botones data-driven que reemplazan onclick inline (requerido por CSP sin unsafe-inline)
  document.addEventListener('click', function (e) {
    const reloadBtn = e.target.closest('.js-reload-btn');
    if (reloadBtn) {
      location.reload();
      return;
    }

    const copyBtn = e.target.closest('.js-copy-btn');
    if (copyBtn && typeof window.copyToClipboard === 'function') {
      window.copyToClipboard(copyBtn.dataset.copyTarget, copyBtn);
      return;
    }

    const copyB64Btn = e.target.closest('.js-copy-b64-btn');
    if (copyB64Btn) {
      window.copiarDatosDirecto(copyB64Btn, copyB64Btn.dataset.copyB64);
      return;
    }
  });

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

  // --- VIGILANTE DE SESIÓN (AUTO-LOGOUT) ---
  setInterval(async () => {
    if (window.location.pathname.includes('login.php')) return;

    try {
      const response = await fetch(baseUrl + '/api/?accion=checkSessionStatus');
      const data = await response.json();

      if (!data.logged_in) {
        window.location.href = baseUrl + '/login.php?session_expired=1';
      }
    } catch (error) {
    }
  }, 60000);

  // Helper global para copiar datos de orden al portapapeles.
  // Antes se llamaba en operador y admin via onclick="copiarDatosDirecto(...)"
  // pero la función NUNCA se definía → el botón no hacía nada (bug latente).
  window.copiarDatosDirecto = function (btn, textoB64) {
    let texto = '';
    try {
      // Soporta base64 con caracteres no-ASCII (acentos, ñ, etc.)
      texto = decodeURIComponent(escape(atob(textoB64)));
    } catch (err) {
      console.error('Error decodificando texto a copiar:', err);
      return;
    }

    const showFeedback = () => {
      if (!btn) return;
      const original = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
      btn.classList.add('btn-success');
      btn.classList.remove('btn-primary', 'btn-outline-primary', 'btn-outline-secondary');
      setTimeout(() => {
        btn.innerHTML = original;
        btn.classList.remove('btn-success');
        // Restauramos clase original — best-effort. Si la clase exacta importa,
        // el llamador puede pasar data-original-class.
        btn.classList.add('btn-primary');
      }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(showFeedback).catch(() => {
        // Fallback para navegadores viejos / contextos sin permisos
        fallbackCopy(texto);
        showFeedback();
      });
    } else {
      fallbackCopy(texto);
      showFeedback();
    }

    function fallbackCopy(t) {
      const ta = document.createElement('textarea');
      ta.value = t;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (_) { }
      document.body.removeChild(ta);
    }
  };

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.view-prev-sends-btn');
    if (!btn) return;

    const txId = btn.dataset.txId;
    if (!txId) return;

    if (btn.dataset.loading === '1') return;
    btn.dataset.loading = '1';

    try {
      const res = await fetch(baseUrl + '/api/?accion=getPreviousSendsToSameAccount&txId=' + encodeURIComponent(txId));
      const data = await res.json();

      if (!data.success) {
        alert(data.error || 'No se pudo cargar la información');
        return;
      }

      const sends = data.sends || [];
      const rows = sends.length === 0
        ? '<tr><td colspan="5" class="text-center text-muted py-3">Sin envíos previos.</td></tr>'
        : sends.map(s => {
          const fecha = s.FechaTransaccion
            ? new Date(s.FechaTransaccion.replace(' ', 'T')).toLocaleString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : '—';
          const monto = (s.MontoDestino !== null && s.MontoDestino !== undefined)
            ? Number(s.MontoDestino).toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';
          return `<tr>
              <td><strong>#${s.TransaccionID}</strong></td>
              <td>${fecha}</td>
              <td>${escapeHtml(s.BeneficiarioNombre || '—')}</td>
              <td>${escapeHtml(s.BeneficiarioBanco || '—')}</td>
              <td class="text-end fw-bold">${monto} ${escapeHtml(s.MonedaDestino || '')}</td>
            </tr>`;
        }).join('');

      const modalId = 'prev-sends-modal-' + txId;
      const existing = document.getElementById(modalId);
      if (existing) existing.remove();

      const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                  <i class="bi bi-arrow-repeat me-2"></i>
                  Envíos previos exitosos (${data.total})
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Beneficiario</th>
                        <th>Banco</th>
                        <th class="text-end">Monto</th>
                      </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>`;

      document.body.insertAdjacentHTML('beforeend', modalHtml);
      const modalEl = document.getElementById(modalId);
      const modal = new bootstrap.Modal(modalEl);
      modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());
      modal.show();
    } catch (err) {
      console.error('Error cargando envíos previos:', err);
      alert('Error de red al cargar los envíos previos.');
    } finally {
      delete btn.dataset.loading;
    }
  });
})();
