document.addEventListener('DOMContentLoaded', () => {
    const rateContainer = document.getElementById('rate-container');
    const valorActualEl = document.getElementById('rate-valor-actual');
    const descriptionEl = document.getElementById('rate-description');
    const ultimaActualizacionEl = document.getElementById('rate-ultima-actualizacion');
    const ctx = document.getElementById('rate-history-chart');
    const countrySelect = document.getElementById('country-select-dropdown');

    if (!rateContainer || !ctx || !countrySelect) {
        console.warn('Elementos del gráfico de tasa no encontrados en esta página.');
        return;
    }

    let rateChartInstance = null;
    let defaultOrigenId = 1;
    const defaultOrigenMoneda = 'CLP';

    const renderizarGraficoTasa = async (origenId, destinoId, destinoMoneda) => {
        valorActualEl.innerHTML = `<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>`;
        descriptionEl.textContent = 'Cargando tasa...';
        ultimaActualizacionEl.textContent = '';

        if (rateChartInstance) {
            rateChartInstance.destroy();
        }

        try {
            const response = await fetch(`api/?accion=getDolarBcv&origenId=${origenId}&destinoId=${destinoId}`);

            if (!response.ok) {
                let errorMsg = `No se pudo obtener la data del gráfico. Código: ${response.status}`;
                try { const errorData = await response.json(); errorMsg = errorData.error || errorMsg; } catch (e) { }
                throw new Error(errorMsg);
            }

            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'La API devolvió datos incompletos.');

            const monedaDestino = data.monedaDestino || 'VES';
            const monedaOrigen = data.monedaOrigen || 'CLP';

            if (data.textoTasa) {
                valorActualEl.textContent = `${data.textoTasa} ${monedaDestino}`;
                descriptionEl.textContent = `Dependiendo del monto enviado.`;
            } else {
                const valorActual = parseFloat(data.valorActual) || 0;
                const valorFormateado = new Intl.NumberFormat('es-ES', {
                    style: 'currency',
                    currency: monedaDestino,
                    minimumFractionDigits: 5,
                    maximumFractionDigits: 5
                }).format(valorActual);

                valorActualEl.textContent = valorFormateado;
                descriptionEl.textContent = `1 ${monedaOrigen} = ${valorFormateado}`;
            }

            ultimaActualizacionEl.textContent = `Tasa de referencia actual.`;

            rateChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: `Tasa Promedio (${monedaDestino})`,
                        data: data.data,
                        fill: true,
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        tension: 0.3,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        x: { display: true, grid: { display: false } },
                        y: { display: true, position: 'right', grid: { drawOnChartArea: false } }
                    }
                }
            });

        } catch (error) {
            console.error("Error al obtener el valor de la tasa:", error);
            if (rateContainer) {
                rateContainer.innerHTML = `<h3 class="card-title text-center mb-3">Tasa de Referencia</h3><p class="text-center text-danger p-3">${error.message}</p>`;
            }
        }
    };

    const populateDropdown = async () => {
        try {
            const response = await fetch('api/?accion=getActiveDestinationCountries');
            const paises = await response.json();

            if (!paises || paises.length === 0) {
                countrySelect.innerHTML = '<option value="">No hay países</option>';
                return;
            }

            countrySelect.innerHTML = '';
            let defaultDestinoId = null;
            let defaultDestinoMoneda = null;

            paises.forEach(pais => {
                const option = document.createElement('option');
                option.value = pais.PaisID;
                option.textContent = pais.NombrePais;
                option.dataset.moneda = pais.CodigoMoneda;
                countrySelect.appendChild(option);

                if (pais.NombrePais.toLowerCase() === 'venezuela') {
                    defaultDestinoId = pais.PaisID;
                    defaultDestinoMoneda = pais.CodigoMoneda;
                    option.selected = true;
                }
            });

            if (!defaultDestinoId) {
                defaultDestinoId = paises[0].PaisID;
                defaultDestinoMoneda = paises[0].CodigoMoneda;
            }

            renderizarGraficoTasa(defaultOrigenId, defaultDestinoId, defaultDestinoMoneda);

        } catch (error) {
            console.error("Error al cargar países:", error);
            countrySelect.innerHTML = '<option value="">Error al cargar</option>';
        }
    };

    countrySelect.addEventListener('change', () => {
        const selectedOption = countrySelect.options[countrySelect.selectedIndex];
        const destinoId = selectedOption.value;
        const destinoMoneda = selectedOption.dataset.moneda;

        renderizarGraficoTasa(defaultOrigenId, destinoId, destinoMoneda);
    });

    populateDropdown();
});