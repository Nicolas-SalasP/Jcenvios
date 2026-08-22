/**
 * Guard del paso 1 del wizard: impide avanzar si no hay una tasa vigente
 * seleccionada, mostrando el modal "tasa no disponible".
 *
 * Extraído de dashboard.js (1599 líneas), donde ya vivía en su propio
 * DOMContentLoaded separado del closure principal — no compartía ninguna
 * variable con el resto, así que sacarlo es un corte limpio.
 *
 * Usa capture (tercer parámetro `true` del listener) para correr ANTES que el
 * handler de navegación del stepper y poder frenarlo con stopPropagation.
 */
document.addEventListener('DOMContentLoaded', () => {
    const nextBtn = document.getElementById('next-btn');
    const tasaInput = document.getElementById('selected-tasa-id');
    const modalTasaEl = document.getElementById('modalTasaNoDisponible');
    if (!nextBtn || !tasaInput || !modalTasaEl) return;
    const modalTasa = new bootstrap.Modal(modalTasaEl);
    nextBtn.addEventListener('click', (e) => {
        const pasoActual = document.querySelector('.form-step.active');
        if (pasoActual && pasoActual.id === 'step-1') {
            const tasaId = tasaInput.value;
            if (!tasaId || tasaId == '0' || tasaId === '') {
                e.preventDefault();
                e.stopPropagation();
                modalTasa.show();
            }
        }
    }, true);
});
