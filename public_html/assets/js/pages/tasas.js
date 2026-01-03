document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('rate-editor-form');
    const editorCardBody = document.getElementById('rate-editor-card-body');
    const paisOrigenSelect = document.getElementById('pais-origen');
    const paisDestinoSelect = document.getElementById('pais-destino');
    const rateValueInput = document.getElementById('rate-value');
    const ratePercentInput = document.getElementById('rate-percent');
    const isRefCheckbox = document.getElementById('rate-is-ref');
    const isRiskyCheckbox = document.getElementById('rate-is-risky');
    const montoMinInput = document.getElementById('rate-monto-min');
    const montoMaxInput = document.getElementById('rate-monto-max');
    const saveButton = document.getElementById('save-rate-btn');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const currentTasaIdInput = document.getElementById('current-tasa-id');
    const feedbackMessage = document.getElementById('feedback-message');
    const btnSaveGlobal = document.getElementById('btn-save-global-settings');
    const btnApplyGlobalNow = document.getElementById('btn-apply-global-now');

    let referentialRateValue = 0;

    // --- UTILIDADES DE FORMATO ---
    const parseInput = (val) => {
        if (!val) return 0;
        let s = val.toString().trim();
        if (s.includes(',') && s.includes('.')) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            s = s.replace(',', '.');
        }
        return parseFloat(s) || 0;
    };

    const formatNumber = (num, decimals = 2) => {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    };

    // --- AJUSTE GLOBAL ---
    btnSaveGlobal?.addEventListener('click', async () => {
        const payload = {
            percent: document.getElementById('global-adj-percent').value,
            time: document.getElementById('global-adj-time').value
        };

        try {
            const res = await fetch('../api/?accion=saveGlobalAdjustmentSettings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            if (result.success) window.showInfoModal('Éxito', 'Configuración de ajuste global guardada.', true);
        } catch (e) { alert("Error al guardar"); }
    });

    btnApplyGlobalNow?.addEventListener('click', async () => {
        const percent = document.getElementById('global-adj-percent').value;
        const confirm = await window.showConfirmModal(
            '¡Atención!',
            `¿Estás seguro de modificar TODAS las tasas referenciales un ${percent}%? Esta acción es inmediata e irreversible.`
        );

        if (confirm) {
            btnApplyGlobalNow.disabled = true;
            btnApplyGlobalNow.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const res = await fetch('../api/?accion=applyGlobalAdjustment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ percent })
                });
                const result = await res.json();
                if (result.success) {
                    window.showInfoModal('Éxito', result.message, true, () => location.reload());
                } else {
                    window.showInfoModal('Error', result.error, false);
                }
            } catch (e) { alert("Error de red"); }
            finally { btnApplyGlobalNow.disabled = false; btnApplyGlobalNow.innerHTML = '<i class="bi bi-lightning-fill"></i> Aplicar Ya'; }
        }
    });

    // --- LÓGICA DE ACTUALIZACIÓN DINÁMICA DE TABLA ---
    const updateRouteTable = (routeKey, items) => {
        const accordionContent = document.getElementById(`collapse-${routeKey}`);
        if (!accordionContent) return;

        const tbody = accordionContent.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        items.forEach(item => {
            const tr = document.createElement('tr');
            if (parseInt(item.EsReferencial) === 1) tr.className = 'table-primary';

            tr.id = `tasa-row-${item.TasaID}`;

            const isRef = parseInt(item.EsReferencial) === 1;
            const isRisky = parseInt(item.EsRiesgoso) === 1;
            const percentLabel = isRef ? '-' : (parseFloat(item.PorcentajeAjuste) >= 0 ? '+' : '') + formatNumber(item.PorcentajeAjuste, 2) + '%';
            const typeBadge = isRef ? '<span class="badge bg-primary">Referencial</span>' : '<span class="badge bg-secondary">Ajustada</span>';
            const riskyBadge = isRisky ? '<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle-fill"></i> Riesgo</span>' : '';

            tr.innerHTML = `
                <td>[${formatNumber(item.MontoMinimo, 2)} - ${formatNumber(item.MontoMaximo, 0)}]</td>
                <td class="text-center">${typeBadge} ${riskyBadge}</td>
                <td class="text-center">${percentLabel}</td>
                <td class="text-center fw-bold">${formatNumber(item.ValorTasa, 5)}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary edit-rate-btn" 
                        data-tasa-id="${item.TasaID}" 
                        data-origen-id="${item.PaisOrigenID}" 
                        data-destino-id="${item.PaisDestinoID}" 
                        data-valor="${item.ValorTasa}" 
                        data-min="${item.MontoMinimo}" 
                        data-max="${item.MontoMaximo}" 
                        data-is-ref="${item.EsReferencial}" 
                        data-is-risky="${item.EsRiesgoso}"
                        data-percent="${item.PorcentajeAjuste}"><i class="bi bi-pencil-fill"></i></button>
                    <button class="btn btn-sm btn-outline-danger delete-rate-btn" data-tasa-id="${item.TasaID}"><i class="bi bi-trash-fill"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    };

    const resetEditor = () => {
        currentTasaIdInput.value = 'new';
        rateValueInput.value = '';
        ratePercentInput.value = '0,00';
        isRefCheckbox.checked = false;
        if (isRiskyCheckbox) isRiskyCheckbox.checked = false;
        referentialRateValue = 0;
        montoMinInput.value = '0,00';
        montoMaxInput.value = formatNumber(9999999999.99, 2);
        cancelEditBtn.classList.add('d-none');
        toggleInputsByRef();
        validateForm();
    };

    const fetchReferentialValue = async () => {
        const origen = paisOrigenSelect.value;
        const destino = paisDestinoSelect.value;
        if (!origen || !destino || origen === destino) return;

        try {
            const response = await fetch(`../api/?accion=getCurrentRate&origen=${origen}&destino=${destino}&monto=0`);
            const data = await response.json();

            if (data.success && data.tasa) {
                referentialRateValue = parseFloat(data.tasa.ValorTasa);
                if (!isRefCheckbox.checked) calculateValueFromPercent();
            } else {
                referentialRateValue = 0;
                if (!isRefCheckbox.checked) rateValueInput.value = 'Falta Referencia';
            }
        } catch (e) {
            referentialRateValue = 0;
            if (!isRefCheckbox.checked) rateValueInput.value = 'Falta Referencia';
        }
    };

    const calculateValueFromPercent = () => {
        if (isRefCheckbox.checked) return;
        if (referentialRateValue <= 0) {
            rateValueInput.value = 'Falta Referencia';
            return;
        }
        const percent = parseInput(ratePercentInput.value);
        const calculatedValue = referentialRateValue * (1 + (percent / 100));
        rateValueInput.value = formatNumber(calculatedValue, 6);
    };

    const calculatePercentFromValue = () => {
        if (isRefCheckbox.checked) return;
        if (referentialRateValue <= 0) return;

        const val = parseInput(rateValueInput.value);
        if (val <= 0) return;
        const calculatedPercent = ((val / referentialRateValue) - 1) * 100;

        ratePercentInput.value = formatNumber(calculatedPercent, 2);
    };

    const toggleInputsByRef = () => {
        if (isRefCheckbox.checked) {
            ratePercentInput.value = "0,00";
            ratePercentInput.disabled = true;
            rateValueInput.disabled = false;
            rateValueInput.focus();
        } else {
            rateValueInput.disabled = false;
            ratePercentInput.disabled = false;
            fetchReferentialValue();
        }
    };

    const validateForm = () => {
        const o = paisOrigenSelect.value;
        const d = paisDestinoSelect.value;
        if (!o || !d || o === d) {
            saveButton.disabled = true;
            if (o && d && o === d) {
                paisDestinoSelect.classList.add('is-invalid');
                feedbackMessage.innerHTML = '<div class="alert alert-danger py-1 small">Origen y destino deben ser distintos.</div>';
            }
        } else {
            saveButton.disabled = false;
            paisDestinoSelect.classList.remove('is-invalid');
            feedbackMessage.innerHTML = '';
        }
    };

    const handleSave = async (e) => {
        e.preventDefault();
        saveButton.disabled = true;
        saveButton.innerHTML = 'Guardando...';

        const payload = {
            tasaId: currentTasaIdInput.value,
            origenId: paisOrigenSelect.value,
            destinoId: paisDestinoSelect.value,
            nuevoValor: parseInput(rateValueInput.value),
            esReferencial: isRefCheckbox.checked ? 1 : 0,
            esRiesgoso: isRiskyCheckbox && isRiskyCheckbox.checked ? 1 : 0,
            porcentaje: parseInput(ratePercentInput.value),
            montoMin: parseInput(montoMinInput.value),
            montoMax: parseInput(montoMaxInput.value)
        };

        try {
            const response = await fetch('../api/?accion=updateRate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                updateRouteTable(result.data.routeKey, result.data.items);
                resetEditor();
                window.showInfoModal('Éxito', 'Guardado correctamente.', true);
            } else {
                window.showInfoModal('Error', result.error, false);
                saveButton.disabled = false;
            }
        } catch (error) {
            console.error(error);
            window.showInfoModal('Error', 'Error de conexión con el servidor.', false);
            saveButton.disabled = false;
        } finally {
            saveButton.innerHTML = 'Guardar Tasa';
        }
    };

    // --- TASA BCV ---
    const bcvForm = document.getElementById('bcv-rate-form');
    const bcvRateInput = document.getElementById('bcv-rate');
    const bcvFeedback = document.getElementById('bcv-feedback');

    if (bcvForm) {
        bcvForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnSave = document.getElementById('btn-save-bcv');
            btnSave.disabled = true; btnSave.innerHTML = '...';

            try {
                const response = await fetch('../api/?accion=updateBcvRate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rate: parseInput(bcvRateInput.value) })
                });
                const result = await response.json();
                if (result.success) {
                    bcvFeedback.innerHTML = '<div class="alert alert-success py-1 small">Actualizada.</div>';
                    setTimeout(() => bcvFeedback.innerHTML = '', 3000);
                }
            } catch (error) { console.error(error); }
            finally { btnSave.disabled = false; btnSave.innerHTML = 'Actualizar'; }
        });
    }

    // --- EVENTOS GENERALES ---
    paisOrigenSelect.addEventListener('change', () => { validateForm(); fetchReferentialValue(); });
    paisDestinoSelect.addEventListener('change', () => { validateForm(); fetchReferentialValue(); });
    isRefCheckbox.addEventListener('change', toggleInputsByRef);
    ratePercentInput.addEventListener('input', calculateValueFromPercent);
    rateValueInput.addEventListener('input', calculatePercentFromValue);

    form.addEventListener('submit', handleSave);
    cancelEditBtn.addEventListener('click', resetEditor);

    document.getElementById('accordionTasas').addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.edit-rate-btn');
        const delBtn = e.target.closest('.delete-rate-btn');

        if (editBtn) {
            const d = editBtn.dataset;
            currentTasaIdInput.value = d.tasaId;
            paisOrigenSelect.value = d.origenId;
            paisDestinoSelect.value = d.destinoId;
            rateValueInput.value = formatNumber(d.valor, 6);
            montoMinInput.value = formatNumber(d.min, 2);
            montoMaxInput.value = formatNumber(d.max, 2);
            isRefCheckbox.checked = parseInt(d.isRef) === 1;
            if (isRiskyCheckbox) isRiskyCheckbox.checked = parseInt(d.isRisky) === 1;
            ratePercentInput.value = formatNumber(d.percent, 2);

            cancelEditBtn.classList.remove('d-none');
            toggleInputsByRef();
            validateForm();
            form.scrollIntoView({ behavior: 'smooth' });
            editorCardBody.style.backgroundColor = '#e3f2fd';
            setTimeout(() => { editorCardBody.style.backgroundColor = ''; }, 1500);
        }

        if (delBtn) {
            const confirmed = await window.showConfirmModal('Borrar', '¿Eliminar esta tasa?');
            if (confirmed) {
                const res = await fetch('../api/?accion=deleteRate', {
                    method: 'POST',
                    body: JSON.stringify({ tasaId: delBtn.dataset.tasaId })
                });
                const result = await res.json();
                if (result.success) {
                    const row = document.getElementById(`tasa-row-${delBtn.dataset.tasaId}`);
                    if (row) row.remove();
                    window.showInfoModal('Éxito', 'Tasa eliminada.', true);
                }
            }
        }
    });

    const bcvInput = document.getElementById('bcv-rate');
    if (bcvInput) {
        fetch('../api/?accion=getBcvRate').then(r => r.json()).then(d => { if (d.success) bcvInput.value = formatNumber(d.rate, 2); });
    }

    resetEditor();
});