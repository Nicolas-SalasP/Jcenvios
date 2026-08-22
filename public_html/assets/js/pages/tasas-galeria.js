(function () {
    var contenedor = document.getElementById('galeriaTasas');
    if (!contenedor) return;
    var tipo = contenedor.dataset.tipo;
    var altText = contenedor.dataset.alt || 'Tasas';

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function formatFecha(fechaSql) {
        var d = new Date(fechaSql.replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function render(imagenes) {
        if (!imagenes.length) {
            contenedor.innerHTML = '<div class="card"><div class="empty"><span>📷</span>Próximamente</div></div>';
            return;
        }
        contenedor.innerHTML = imagenes.map(function (img) {
            var titulo = img.titulo ? '<p class="card-titulo">' + escapeHtml(img.titulo) + '</p>' : '';
            var descripcion = img.descripcion ? '<p class="card-descripcion">' + escapeHtml(img.descripcion).replace(/\n/g, '<br>') + '</p>' : '';
            return '<div class="card">' +
                '<img src="' + escapeHtml(img.url) + '" alt="' + escapeHtml(altText) + '">' +
                titulo + descripcion +
                '<p class="fecha">Actualizado: ' + formatFecha(img.fechaActualizacion) + '</p>' +
                '</div>';
        }).join('');
    }

    async function actualizar() {
        try {
            var res = await fetch('api/?accion=getTasaImagenPublica&tipo=' + tipo);
            var data = await res.json();
            if (data.success) render(data.imagenes || []);
        } catch (e) {
            // Silencioso: se reintenta en el siguiente ciclo, sin romper la vista actual.
        }
    }

    setInterval(actualizar, 15000);
})();
