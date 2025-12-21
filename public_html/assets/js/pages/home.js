document.addEventListener('DOMContentLoaded', () => {
    const selectOrigen = document.getElementById('calc-pais-origen');
    const selectDestino = document.getElementById('calc-pais-destino');
    const inputOrigen = document.getElementById('calc-monto-origen');
    const inputDestino = document.getElementById('calc-monto-destino');
    const inputUsd = document.getElementById('calc-monto-usd');
    const tasaInfo = document.getElementById('calc-tasa-info');
    const bcvAlertBox = document.getElementById('bcv-alert-box');
    const bcvRateDisplayCalc = document.getElementById('bcv-rate-display-calc');
    const colMontoDestino = document.getElementById('col-monto-destino');
    const colMontoUsd = document.getElementById('col-monto-usd');
    const labelMonedaOrigen = document.getElementById('label-moneda-origen');
    const labelMonedaDestino = document.getElementById('label-moneda-destino');

    let commercialRate = 0;
    let bcvRate = 0;
    let routeMin = 0;
    let routeMax = 0;
    let fetchTimer = null;
    let activeInputId = 'calc-monto-origen';
    let isVenezuelaDest = false;

    const parseInput = (val, isUsd = false) => {
        if (!val) return 0;
        let s = val.toString().trim();
        if (isUsd) {
            s = s.replace(',', '.');
        } else {
            s = s.replace(/\./g, '');
            s = s.replace(',', '.');
        }
        return parseFloat(s) || 0;
    };

    const formatDisplay = (num, decimals = 2) => {
        if (isNaN(num) || num === 0) return '';
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    };

    const applyLiveFormat = (input) => {
        let cursorPosition = input.selectionStart;
        let originalLength = input.value.length;
        const isUsdField = input.id.includes('usd');
        if (isUsdField) {
            input.value = input.value.replace(/[^0-9.,]/g, '');
            let parts = input.value.split(/[.,]/);
            if (parts.length > 2) {
                let firstSeparator = input.value.match(/[.,]/)[0];
                input.value = parts[0] + firstSeparator + parts.slice(1).join('').replace(/[.,]/g, '');
            }
        } else {
            let value = input.value.replace(/[^0-9,]/g, '');
            let parts = value.split(',');
            if (parts[0].length > 0) {
                parts[0] = new Intl.NumberFormat('de-DE').format(parseInt(parts[0].replace(/\./g, '')));
            }
            input.value = parts.join(parts.length > 1 ? ',' : '');
        }
        let newLength = input.value.length;
        input.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
    };

    const updateUiForCountry = () => {
        const selectedOption = selectDestino.options[selectDestino.selectedIndex];
        if (!selectedOption) return;

        const paisNombre = selectedOption.text.toLowerCase();

        // Verificación de seguridad para evitar el error de textContent null
        if (labelMonedaDestino) {
            labelMonedaDestino.textContent = selectedOption.dataset.currency || '...';
        }

        const selectedOrigen = selectOrigen.options[selectOrigen.selectedIndex];
        if (selectedOrigen && labelMonedaOrigen) {
            labelMonedaOrigen.textContent = selectedOrigen.dataset.currency || '...';
        }

        isVenezuelaDest = paisNombre.includes('venezuela');
        if (isVenezuelaDest) {
            bcvAlertBox?.classList.remove('d-none');
            colMontoUsd?.classList.remove('d-none');
            // Mantenemos las columnas como están en el HTML para evitar que se achiquen
        } else {
            bcvAlertBox?.classList.add('d-none');
            colMontoUsd?.classList.add('d-none');
            if (inputUsd) inputUsd.value = '';
            bcvRate = 0;
        }
    };

    const filterDestinations = () => {
        const selectedOrigenValue = selectOrigen.value;
        Array.from(selectDestino.options).forEach(opt => {
            if (opt.value === selectedOrigenValue) {
                opt.style.display = 'none';
                if (opt.selected) selectDestino.value = '';
            } else {
                opt.style.display = 'block';
            }
        });
        if (!selectDestino.value && selectDestino.options.length > 0) {
            for (let i = 0; i < selectDestino.options.length; i++) {
                if (selectDestino.options[i].style.display !== 'none') {
                    selectDestino.selectedIndex = i;
                    break;
                }
            }
        }
    };

    const ctx = document.getElementById('rate-history-chart');
    const valorActualEl = document.getElementById('rate-valor-actual');
    const descEl = document.getElementById('rate-description');
    let chartInstance = null;

    const renderChart = async (origenId, destinoId) => {
        if (!ctx) return;
        try {
            const respChart = await fetch(`api/?accion=getDolarBcv&origenId=${origenId}&destinoId=${destinoId}&days=30`);
            const dataChart = await respChart.json();
            if (!dataChart.success) return;
            if (valorActualEl) valorActualEl.textContent = dataChart.textoTasa || (formatDisplay(dataChart.valorActual) + ` ${dataChart.monedaDestino}`);
            if (descEl) descEl.textContent = dataChart.textoTasa ? `Rango Tasa Promedio (${dataChart.monedaDestino})` : `1 ${dataChart.monedaOrigen} = ${formatDisplay(dataChart.valorActual)} ${dataChart.monedaDestino}`;

            if (chartInstance) chartInstance.destroy();
            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dataChart.labels,
                    datasets: [{
                        label: 'Tasa', data: dataChart.data, borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)', borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        } catch (e) { console.warn("Gráfico error"); }
    };

    const fetchRates = async () => {
        const oID = selectOrigen.value;
        const dID = selectDestino.value;
        if (!oID || !dID) return;

        updateUiForCountry();

        if (isVenezuelaDest) {
            try {
                const responseBcv = await fetch('api/?accion=getBcvRate');
                const dataBcv = await responseBcv.json();
                bcvRate = dataBcv.success ? parseFloat(dataBcv.rate) : 0;
                if (bcvRateDisplayCalc) bcvRateDisplayCalc.textContent = `1 USD = ${formatDisplay(bcvRate)} VES`;
            } catch (e) { bcvRate = 0; }
        }

        let montoParaTasa = 0;
        const isColombia = selectOrigen.options[selectOrigen.selectedIndex]?.text === 'Colombia';

        if (activeInputId === 'calc-monto-origen') {
            montoParaTasa = parseInput(inputOrigen.value, false);
        } else if (activeInputId === 'calc-monto-destino') {
            let ves = parseInput(inputDestino.value, false);
            // Si es Colombia, el monto origen se calcula multiplicando VES * Tasa (porque la tasa es 0.085 COP/VES)
            montoParaTasa = commercialRate > 0 ? (isColombia ? ves * commercialRate : ves / commercialRate) : 0;
        } else if (activeInputId === 'calc-monto-usd') {
            let usd = parseInput(inputUsd.value, true);
            montoParaTasa = (bcvRate > 0 && commercialRate > 0) ? (isColombia ? (usd * bcvRate * commercialRate) : (usd * bcvRate / commercialRate)) : 0;
        }

        if (routeMin > 0 && montoParaTasa > 0 && montoParaTasa < routeMin) {
            if (tasaInfo) {
                tasaInfo.textContent = `Monto inferior al mínimo permitido (${formatDisplay(routeMin)})`;
                tasaInfo.classList.replace('text-primary', 'text-danger');
            }
            performCalculation();
            return;
        }

        if (routeMax > 0 && montoParaTasa > routeMax) {
            if (tasaInfo) {
                tasaInfo.textContent = `Monto excede el máximo permitido (${formatDisplay(routeMax)})`;
                tasaInfo.classList.replace('text-primary', 'text-danger');
            }
            performCalculation();
            return;
        }

        if (tasaInfo) tasaInfo.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const respRate = await fetch(`api/?accion=getCurrentRate&origen=${oID}&destino=${dID}&monto=${montoParaTasa}`);
            const dataRate = await respRate.json();
            if (dataRate.success && dataRate.tasa) {
                commercialRate = parseFloat(dataRate.tasa.ValorTasa);
                if (tasaInfo) {
                    tasaInfo.textContent = `Tasa: 1 ${labelMonedaOrigen.textContent} = ${commercialRate.toFixed(5)} ${labelMonedaDestino.textContent}`;
                    tasaInfo.classList.replace('text-danger', 'text-primary');
                }
                if (dataRate.route_min) routeMin = parseFloat(dataRate.route_min);
                if (dataRate.route_max) routeMax = parseFloat(dataRate.route_max);
            } else {
                commercialRate = 0;
                if (tasaInfo) {
                    tasaInfo.textContent = dataRate.error || 'Sin tasa disponible';
                    tasaInfo.classList.replace('text-primary', 'text-danger');
                }
            }
        } catch (e) {
            commercialRate = 0;
            if (tasaInfo) {
                tasaInfo.textContent = 'Monto fuera de rango';
                tasaInfo.classList.replace('text-primary', 'text-danger');
            }
        }
        performCalculation();
    };

    const performCalculation = () => {
        if (commercialRate <= 0) return;

        let clp = 0, ves = 0, usd = 0;
        const isColombia = selectOrigen.options[selectOrigen.selectedIndex]?.text === 'Colombia';

        if (activeInputId === 'calc-monto-origen') {
            clp = parseInput(inputOrigen.value, false);
            // LÓGICA COLOMBIA: DIVIDIR (Pesos / 0.085 = VES)
            ves = isColombia ? (clp / commercialRate) : (clp * commercialRate);
            if (isVenezuelaDest && bcvRate > 0) usd = ves / bcvRate;

            inputDestino.value = formatDisplay(ves);
            if (isVenezuelaDest && inputUsd) inputUsd.value = formatDisplay(usd);
        }
        else if (activeInputId === 'calc-monto-destino') {
            ves = parseInput(inputDestino.value, false);
            // LÓGICA INVERSA COLOMBIA: MULTIPLICAR (VES * 0.085 = Pesos)
            clp = isColombia ? (ves * commercialRate) : Math.ceil(ves / commercialRate);
            if (isVenezuelaDest && bcvRate > 0) usd = ves / bcvRate;

            inputOrigen.value = formatDisplay(clp);
            if (isVenezuelaDest && inputUsd) inputUsd.value = formatDisplay(usd);
        }
        else if (activeInputId === 'calc-monto-usd' && isVenezuelaDest) {
            usd = parseInput(inputUsd.value, true);
            ves = usd * bcvRate;
            // LÓGICA INVERSA COLOMBIA
            clp = isColombia ? (ves * commercialRate) : Math.ceil(ves / commercialRate);

            inputOrigen.value = formatDisplay(clp);
            inputDestino.value = formatDisplay(ves);
        }
    };

    const handleInput = (e) => {
        activeInputId = e.target.id;
        applyLiveFormat(e.target);
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchRates, 600);
        performCalculation();
    };

    const init = async () => {
        if (!selectOrigen || !selectDestino) return;

        inputOrigen.addEventListener('input', handleInput);
        inputDestino.addEventListener('input', handleInput);
        if (inputUsd) inputUsd.addEventListener('input', handleInput);

        selectOrigen.addEventListener('change', () => {
            filterDestinations();
            fetchRates();
            renderChart(selectOrigen.value, selectDestino.value);
        });

        selectDestino.addEventListener('change', () => {
            fetchRates();
            renderChart(selectOrigen.value, selectDestino.value);
        });

        try {
            const rO = await fetch('api/?accion=getPaises&rol=Origen');
            const dataO = await rO.json();
            selectOrigen.innerHTML = dataO.map(p => `<option value="${p.PaisID}" data-currency="${p.CodigoMoneda}">${p.NombrePais}</option>`).join('');

            const rD = await fetch('api/?accion=getPaises&rol=Destino');
            const dataD = await rD.json();
            selectDestino.innerHTML = dataD.map(p => {
                const isV = p.NombrePais.toLowerCase().includes('venezuela');
                return `<option value="${p.PaisID}" data-currency="${p.CodigoMoneda}" ${isV ? 'selected' : ''}>${p.NombrePais}</option>`;
            }).join('');

            filterDestinations();
            fetchRates();
            renderChart(selectOrigen.value, selectDestino.value);
        } catch (e) { console.error("Init Error"); }
    };

    init();
});