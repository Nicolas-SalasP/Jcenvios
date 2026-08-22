-- ============================================================================
-- Migración 021 — Tipo de movimiento para reversas de ingreso por venta
-- ============================================================================
-- Cuando un admin confirma el pago de una orden, ContabilidadService registra
-- un INGRESO_VENTA que suma al saldo de la cuenta bancaria admin. Si esa orden
-- después se cancela o se rechaza, ese ingreso quedaba sumado para siempre —
-- no existía ningún mecanismo de reversión (hallado en auditoría 2026-08-21).
--
-- Se agrega un tipo propio (no se reutiliza GASTO_VARIO) por tres razones:
--   1. getGastosMensuales() filtra por Codigo IN ('GASTO_TX','GASTO_COMISION',
--      'GASTO_VARIO') — usar GASTO_VARIO contaminaría el reporte de gastos con
--      plata que no es un gasto real, sino la anulación de un ingreso.
--   2. En el historial bancario aparecería como "Gasto Operativo / Retiro",
--      que le miente al que lee la contabilidad.
--   3. La reversión es idempotente calculando el neto
--      SUM(INGRESO_VENTA) - SUM(REVERSA_VENTA) por transacción; con un código
--      compartido no se podría distinguir una reversa de un gasto real.
--
-- EsIngreso = 0 → baja el saldo. El Monto se guarda positivo, igual que
-- GASTO_TX en registrarEgresoPago.
--
-- Idempotente vía el UNIQUE KEY idx_codigo ya existente en tipos_movimiento.
-- ============================================================================

INSERT INTO tipos_movimiento (Codigo, NombreVisible, EsIngreso, Color)
VALUES ('REVERSA_VENTA', 'Reversa por Cancelación', 0, 'warning')
ON DUPLICATE KEY UPDATE Codigo = Codigo;
