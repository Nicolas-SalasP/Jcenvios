(function () {
  const CSRF_TOKEN = document.body.dataset.csrfToken || '';

  // Interceptor global: agrega X-CSRF-Token a todos los fetch POST automáticamente
  const _fetch = window.fetch;
  window.fetch = function (input, init) {
    init = init || {};
    const method = (init.method || 'GET').toUpperCase();
    if (method === 'POST' && CSRF_TOKEN) {
      init.headers = Object.assign({ 'X-CSRF-Token': CSRF_TOKEN }, init.headers || {});
    }
    return _fetch.call(this, input, init);
  };
})();
