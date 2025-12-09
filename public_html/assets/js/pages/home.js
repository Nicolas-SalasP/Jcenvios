document.addEventListener('DOMContentLoaded', () => {
    // ==========================================
    // 1. VARIABLES Y SELECTORES (CALCULADORA)
    // ==========================================
    const selectOrigen = document.getElementById('calc-pais-origen');
    const selectDestino = document.getElementById('calc-pais-destino');

    const inputOrigen = document.getElementById('calc-monto-origen');
    const inputDestino = document.getElementById('calc-monto-destino');
    const inputUsd = document.getElementById('calc-monto-usd');
    const tasaInfo = document.getElementById('calc-tasa-info');

    // Elementos Dinámicos BCV
    const bcvAlertBox = document.getElementById('bcv-alert-box');
    const bcvRateDisplayCalc = document.getElementById('bcv-rate-display-calc');
    const colMontoDestino = document.getElementById('col-monto-destino');
    const colMontoUsd = document.getElementById('col-monto-usd');

    const labelMonedaOrigen = document.getElementById('label-moneda-origen');
    const labelMonedaDestino = document.getElementById('label-moneda-destino');

    // Variables de Estado
    let commercialRate = 0;
    let bcvRate = 0;
    let fetchTimer = null;
    let activeInputId = 'calc-monto-origen';
    let isVenezuelaDest = false; // Flag para controlar lógica BCV

    // Formateadores
    const numberFormatter = new Intl.NumberFormat('es-ES', { style: 'decimal', maximumFractionDigits: 2, minimumFractionDigits: 2 });

    const cleanNumber = (val) => {
        if (!val) return '';
        return val.replace(/\./g, '').replace(',', '.');
    };

    const formatMoney = (val) => {
        if (isNaN(val)) return '';
        return numberFormatter.format(val);
    };

    // ==========================================
    // 2. LÓGICA DE UI (VISIBILIDAD)
    // ==========================================

    const updateUiForCountry = () => {
        const selectedOption = selectDestino.options[selectDestino.selectedIndex];
        if (!selectedOption) return;

        const paisNombre = selectedOption.text.toLowerCase();
        const monedaDestino = selectedOption.dataset.currency || '...';

        labelMonedaDestino.textContent = monedaDestino;
        const selectedOrigen = selectOrigen.options[selectOrigen.selectedIndex];
        if (selectedOrigen) labelMonedaOrigen.textContent = selectedOrigen.dataset.currency;

        // Detectar si es Venezuela para activar BCV
        isVenezuelaDest = paisNombre.includes('venezuela');

        if (isVenezuelaDest) {
            // MOSTRAR MODO 3 COLUMNAS (Con USD)
            bcvAlertBox.classList.remove('d-none');
            colMontoUsd.classList.remove('d-none');
            colMontoDestino.classList.remove('col-12');
            colMontoDestino.classList.add('col-6');
        } else {
            // MOSTRAR MODO 2 COLUMNAS (Sin USD)
            bcvAlertBox.classList.add('d-none');
            colMontoUsd.classList.add('d-none');
            colMontoDestino.classList.remove('col-6');
            colMontoDestino.classList.add('col-12');

            // Limpiar campo USD para evitar confusiones
            inputUsd.value = '';
            bcvRate = 0;
        }
    };

    // ==========================================
    // 3. LÓGICA DE GRÁFICO
    // ==========================================
    const ctx = document.getElementById('rate-history-chart');
    const valorActualEl = document.getElementById('rate-valor-actual');
    const descEl = document.getElementById('rate-description');
    let chartInstance = null;

    const renderChart = async (origenId, destinoId) => {
        if (!ctx) return;
        try {
            // Solicitamos explícitamente 30 días
            const res = await fetch(`api/?accion=getDolarBcv&origenId=${origenId}&destinoId=${destinoId}&days=30`);
            const data = await res.json();

            if (!data.success) throw new Error("Datos incompletos");

            if (data.textoTasa) {
                valorActualEl.textContent = `${data.textoTasa}`;
                descEl.textContent = `Rango Tasa Promedio (${data.monedaDestino})`;
            } else {
                valorActualEl.textContent = formatMoney(data.valorActual) + ` ${data.monedaDestino}`;
                descEl.textContent = `1 ${data.monedaOrigen} = ${formatMoney(data.valorActual)} ${data.monedaDestino}`;
            }

            if (chartInstance) chartInstance.destroy();

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Tasa',
                        data: data.data,
                        borderColor: '#0d6efd',
                        backgroundColor: (context) => {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                            gradient.addColorStop(0, 'rgba(13, 110, 253, 0.4)');
                            gradient.addColorStop(1, 'rgba(13, 110, 253, 0.0)');
                            return gradient;
                        },
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, maxTicksLimit: 6 }
                        },
                        y: {
                            position: 'right',
                            grid: { borderDash: [5, 5] }
                        }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });

        } catch (e) {
            console.warn("No se pudo cargar el gráfico:", e);
            if (valorActualEl) valorActualEl.textContent = "Consultar";
        }
    };

    // ==========================================
    // 4. LÓGICA DE CALCULADORA (CORE)
    // ==========================================

    const fetchRates = async () => {
        const origenID = selectOrigen.value;
        const destinoID = selectDestino.value;

        if (!origenID || !destinoID) return;

        updateUiForCountry(); // Asegurar UI correcta antes de calcular

        // A) Obtener Tasa BCV (Solo si es Venezuela)
        if (isVenezuelaDest) {
            try {
                const res = await fetch('api/?accion=getBcvRate');
                const data = await res.json();
                bcvRate = (data.success && data.rate > 0) ? parseFloat(data.rate) : 0;
                if (bcvRate > 0 && bcvRateDisplayCalc) {
                    bcvRateDisplayCalc.textContent = `1 USD = ${formatMoney(bcvRate)} VES`;
                }
            } catch (e) { bcvRate = 0; }
        } else {
            bcvRate = 0;
        }

        // B) Obtener Tasa Comercial
        let baseMonto = parseFloat(cleanNumber(inputOrigen.value)) || 0;
        tasaInfo.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch(`api/?accion=getTasa&origenID=${origenID}&destinoID=${destinoID}&montoOrigen=${baseMonto}`);
            const data = await res.json();

            if (data && data.ValorTasa) {
                commercialRate = parseFloat(data.ValorTasa);
                tasaInfo.textContent = `Mejor Tasa: ${commercialRate.toFixed(5)}`;
                tasaInfo.classList.replace('text-danger', 'text-primary');
            } else {
                commercialRate = 0;
                tasaInfo.textContent = 'Sin tasa disponible';
                tasaInfo.classList.replace('text-primary', 'text-danger');
            }
        } catch (e) {
            commercialRate = 0;
            tasaInfo.textContent = 'Error';
        }

        performCalculation();
    };

    const performCalculation = () => {
        if (commercialRate <= 0) return;

        let clp = 0, ves = 0, usd = 0;
        const validatePositive = (val) => val < 0 ? 0 : val;

        // Lógica 1: Usuario escribe en Origen
        if (activeInputId === 'calc-monto-origen') {
            clp = validatePositive(parseFloat(cleanNumber(inputOrigen.value)) || 0);
            ves = clp * commercialRate;

            if (isVenezuelaDest && bcvRate > 0) usd = ves / bcvRate;

            if (document.activeElement !== inputDestino) inputDestino.value = ves > 0 ? formatMoney(ves) : '';
            if (isVenezuelaDest && document.activeElement !== inputUsd) inputUsd.value = usd > 0 ? formatMoney(usd) : '';
        }
        // Lógica 2: Usuario escribe en Destino
        else if (activeInputId === 'calc-monto-destino') {
            ves = validatePositive(parseFloat(cleanNumber(inputDestino.value)) || 0);
            clp = Math.ceil(ves / commercialRate); // Redondeo hacia arriba

            if (isVenezuelaDest && bcvRate > 0) usd = ves / bcvRate;

            if (document.activeElement !== inputOrigen) inputOrigen.value = clp > 0 ? formatMoney(clp) : '';
            if (isVenezuelaDest && document.activeElement !== inputUsd) inputUsd.value = usd > 0 ? formatMoney(usd) : '';
        }
        // Lógica 3: Usuario escribe en USD (Solo si es Venezuela)
        else if (activeInputId === 'calc-monto-usd' && isVenezuelaDest) {
            usd = validatePositive(parseFloat(cleanNumber(inputUsd.value)) || 0);
            if (bcvRate > 0) {
                ves = usd * bcvRate;
                clp = Math.ceil(ves / commercialRate);
            }
            if (document.activeElement !== inputOrigen) inputOrigen.value = clp > 0 ? formatMoney(clp) : '';
            if (document.activeElement !== inputDestino) inputDestino.value = ves > 0 ? formatMoney(ves) : '';
        }
    };

    const handleInput = (e) => {
        activeInputId = e.target.id;
        e.target.value = e.target.value.replace(/[^0-9.,]/g, '');

        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(() => {
            if (selectOrigen.value && selectDestino.value) {
                // Si el monto cambia drásticamente, refrescar tasa (por si hay tiered pricing)
                fetchRates();
            }
        }, 500);

        performCalculation();
    };

    // ==========================================
    // 5. INICIALIZACIÓN
    // ==========================================
    const init = async () => {
        if (!selectOrigen || !selectDestino) return;

        inputOrigen.addEventListener('input', handleInput);
        inputDestino.addEventListener('input', handleInput);
        if (inputUsd) inputUsd.addEventListener('input', handleInput);

        selectOrigen.addEventListener('change', () => fetchRates());

        selectDestino.addEventListener('change', () => {
            fetchRates();
            renderChart(selectOrigen.value, selectDestino.value);
        });

        // Cargar Países
        try {
            const resOrigen = await fetch('api/?accion=getPaises&rol=Origen');
            const dataOrigen = await resOrigen.json();
            selectOrigen.innerHTML = '';
            dataOrigen.forEach(p => selectOrigen.innerHTML += `<option value="${p.PaisID}" data-currency="${p.CodigoMoneda}">${p.NombrePais}</option>`);

            const resDestino = await fetch('api/?accion=getPaises&rol=Destino');
            const dataDestino = await resDestino.json();
            selectDestino.innerHTML = '';

            dataDestino.forEach(p => {
                const isVzla = p.NombrePais.toLowerCase().includes('venezuela');
                selectDestino.innerHTML += `<option value="${p.PaisID}" data-currency="${p.CodigoMoneda}" ${isVzla ? 'selected' : ''}>${p.NombrePais}</option>`;
            });

            // Trigger inicial
            if (selectOrigen.value && selectDestino.value) {
                fetchRates();
                renderChart(selectOrigen.value, selectDestino.value);
            }

        } catch (e) {
            console.error("Error init:", e);
        }
    };

    init();
});