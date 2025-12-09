document.addEventListener('DOMContentLoaded', () => {
    // Referencias Editor Tasas
    const form = document.getElementById('rate-editor-form');
    const paisOrigenSelect = document.getElementById('pais-origen');
    const paisDestinoSelect = document.getElementById('pais-destino');
    const rateValueInput = document.getElementById('rate-value');
    const montoMinInput = document.getElementById('rate-monto-min');
    const montoMaxInput = document.getElementById('rate-monto-max');
    const saveButton = document.getElementById('save-rate-btn');
    const currentTasaIdInput = document.getElementById('current-tasa-id');
    const feedbackMessage = document.getElementById('feedback-message');
    const ratesTableBody = document.querySelector('#existing-rates-table tbody');

    // Referencias Editor BCV
    const bcvForm = document.getElementById('bcv-rate-form');
    const bcvInput = document.getElementById('bcv-rate');
    const bcvBtn = document.getElementById('btn-save-bcv');
    const bcvFeedback = document.getElementById('bcv-feedback');

    // --- LÓGICA TASA BCV ---
    const loadBcvRate = async () => {
        if (!bcvInput) return;
        bcvInput.disabled = true;
        try {
            // Usamos ClientController.getBcvRate (es público para usuarios logueados)
            const response = await fetch('../api/?accion=getBcvRate');
            const data = await response.json();
            if (data.success) {
                bcvInput.value = data.rate > 0 ? data.rate : '';
            }
        } catch (e) {
            console.error("Error cargando BCV", e);
        } finally {
            bcvInput.disabled = false;
        }
    };

    if (bcvForm) {
        bcvForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const rate = parseFloat(bcvInput.value);
            if (isNaN(rate) || rate <= 0) {
                window.showInfoModal('Error', 'La tasa BCV debe ser mayor a 0.', false);
                return;
            }

            bcvBtn.disabled = true;
            bcvBtn.textContent = 'Guardando...';
            bcvFeedback.innerHTML = '';

            try {
                // Usamos AdminController.updateBcvRate
                const response = await fetch('../api/?accion=updateBcvRate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rate: rate })
                });
                const result = await response.json();

                if (result.success) {
                    bcvFeedback.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle"></i> Tasa BCV actualizada correctamente.</span>';
                    setTimeout(() => bcvFeedback.innerHTML = '', 3000);
                } else {
                    window.showInfoModal('Error', result.error || 'Error al guardar.', false);
                }
            } catch (error) {
                window.showInfoModal('Error', 'Error de conexión.', false);
            } finally {
                bcvBtn.disabled = false;
                bcvBtn.textContent = 'Actualizar Referencia';
            }
        });

        // Cargar tasa al iniciar
        loadBcvRate();
    }

    // --- LÓGICA EDITOR DE TASAS COMERCIALES (Existente) ---
    const resetEditor = (clearDropdowns = false) => {
        rateValueInput.disabled = true;
        montoMinInput.disabled = true;
        montoMaxInput.disabled = true;
        saveButton.disabled = true;

        rateValueInput.value = '';
        montoMinInput.value = '0.00';
        montoMaxInput.value = '9999999999.99';
        currentTasaIdInput.value = 'new';
        rateValueInput.classList.remove('is-invalid');

        if (clearDropdowns) {
            paisOrigenSelect.value = '';
            paisDestinoSelect.value = '';
        }
    };

    const enableEditor = () => {
        const origenId = paisOrigenSelect.value;
        const destinoId = paisDestinoSelect.value;

        if (!origenId || !destinoId) {
            resetEditor();
            return;
        }

        if (origenId === destinoId) {
            rateValueInput.value = 'N/A';
            rateValueInput.classList.add('is-invalid');
            montoMinInput.disabled = true;
            montoMaxInput.disabled = true;
            saveButton.disabled = true;
            return;
        }

        rateValueInput.disabled = false;
        montoMinInput.disabled = false;
        montoMaxInput.disabled = false;
        saveButton.disabled = false;
    };

    const handleSaveRate = async (e) => {
        e.preventDefault();

        const origenId = paisOrigenSelect.value;
        const destinoId = paisDestinoSelect.value;
        const nuevoValor = parseFloat(rateValueInput.value);
        const tasaId = currentTasaIdInput.value;
        const montoMin = parseFloat(montoMinInput.value);
        const montoMax = parseFloat(montoMaxInput.value);

        if (nuevoValor <= 0 || isNaN(nuevoValor) || isNaN(montoMin) || isNaN(montoMax)) {
            window.showInfoModal('Error', 'El valor de la tasa y los montos deben ser números positivos.', false);
            return;
        }

        if (montoMin >= montoMax) {
            window.showInfoModal('Error', 'El Monto Mínimo no puede ser mayor or igual al Monto Máximo.', false);
            return;
        }

        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        try {
            const response = await fetch('../api/?accion=updateRate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tasaId: tasaId,
                    nuevoValor: nuevoValor,
                    origenId: origenId,
                    destinoId: destinoId,
                    montoMin: montoMin,
                    montoMax: montoMax
                })
            });

            const result = await response.json();
            feedbackMessage.classList.remove('alert-danger', 'alert-success');

            if (response.ok && result.success) {
                feedbackMessage.classList.add('alert', 'alert-success');
                feedbackMessage.textContent = '¡Tasa guardada con éxito!';

                const newTasaId = result.newTasaId.toString();

                updateTable(origenId, destinoId, newTasaId, nuevoValor, montoMin, montoMax);
                resetEditor(true);

            } else {
                feedbackMessage.classList.add('alert', 'alert-danger');
                feedbackMessage.textContent = 'Error: ' + (result.error || 'Ocurrió un problema.');
            }
        } catch (error) {
            console.error("Error al guardar tasa:", error);
            feedbackMessage.classList.add('alert', 'alert-danger');
            feedbackMessage.textContent = 'Error de conexión con el servidor.';
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="bi bi-save"></i> Guardar Tasa';
            setTimeout(() => {
                feedbackMessage.textContent = '';
                feedbackMessage.classList.remove('alert-danger', 'alert-success');
            }, 4000);
        }
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
    };

    const updateTable = (origenId, destinoId, tasaId, valor, min, max) => {
        let row = document.getElementById(`tasa-row-${tasaId}`);

        if (row) {
            row.querySelector('.rate-value-cell').textContent = valor;
            row.querySelector('.rate-min-cell').textContent = formatCurrency(min);
            row.querySelector('.rate-max-cell').textContent = formatCurrency(max);
        } else {
            const noDataRow = ratesTableBody.querySelector('td[colspan="6"]');
            if (noDataRow) noDataRow.parentElement.remove();

            const origenText = paisOrigenSelect.options[paisOrigenSelect.selectedIndex].text;
            const destinoText = paisDestinoSelect.options[paisDestinoSelect.selectedIndex].text;

            const newRow = document.createElement('tr');
            newRow.id = `tasa-row-${tasaId}`;
            newRow.dataset.origenId = origenId;
            newRow.dataset.destinoId = destinoId;
            newRow.innerHTML = `
                <td>${origenText}</td>
                <td>${destinoText}</td>
                <td class="rate-min-cell">${formatCurrency(min)}</td>
                <td class="rate-max-cell">${formatCurrency(max)}</td>
                <td class="rate-value-cell">${valor}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary edit-rate-btn me-1"
                            data-tasa-id="${tasaId}"
                            data-origen-id="${origenId}"
                            data-destino-id="${destinoId}"
                            data-valor="${valor}"
                            data-min="${min}"
                            data-max="${max}"
                            title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-rate-btn"
                            data-tasa-id="${tasaId}"
                            title="Eliminar Tasa">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </td>
            `;
            ratesTableBody.appendChild(newRow);
        }
    };

    paisOrigenSelect.addEventListener('change', enableEditor);
    paisDestinoSelect.addEventListener('change', enableEditor);
    form.addEventListener('submit', handleSaveRate);

    ratesTableBody.addEventListener('click', async (e) => {
        const editButton = e.target.closest('.edit-rate-btn');
        const deleteButton = e.target.closest('.delete-rate-btn');

        if (editButton) {
            const tasaId = editButton.dataset.tasaId;
            const origenId = editButton.dataset.origenId;
            const destinoId = editButton.dataset.destinoId;
            const valor = editButton.dataset.valor;
            const min = editButton.dataset.min;
            const max = editButton.dataset.max;

            paisOrigenSelect.value = origenId;
            paisDestinoSelect.value = destinoId;

            rateValueInput.value = valor;
            montoMinInput.value = min;
            montoMaxInput.value = max;
            currentTasaIdInput.value = tasaId;

            enableEditor();

            form.scrollIntoView({ behavior: 'smooth' });

            const cardBody = form.closest('.card-body');
            cardBody.style.transition = 'background-color 0.5s ease-out';
            cardBody.style.backgroundColor = '#e6f7ff';
            setTimeout(() => {
                cardBody.style.backgroundColor = '';
            }, 1500);
        }

        if (deleteButton) {
            const tasaId = deleteButton.dataset.tasaId;

            const confirmed = await window.showConfirmModal(
                'Eliminar Tasa',
                '¿Estás seguro de que quieres eliminar esta configuración de tasa? Esto podría dejar a la ruta sin precio.'
            );

            if (confirmed) {
                try {
                    const response = await fetch('../api/?accion=deleteRate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tasaId: tasaId })
                    });
                    const result = await response.json();

                    if (result.success) {
                        const row = document.getElementById(`tasa-row-${tasaId}`);
                        if (row) row.remove();

                        if (ratesTableBody.children.length === 0) {
                            ratesTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No hay tasas configuradas.</td></tr>';
                        }

                        window.showInfoModal('Éxito', 'Tasa eliminada correctamente.', true);

                        if (currentTasaIdInput.value === tasaId) {
                            resetEditor(true);
                        }
                    } else {
                        window.showInfoModal('Error', result.error || 'No se pudo eliminar.', false);
                    }
                } catch (error) {
                    console.error(error);
                    window.showInfoModal('Error', 'Error de conexión.', false);
                }
            }
        }
    });

    resetEditor();
});