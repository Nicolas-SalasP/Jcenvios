-- ============================================================================
-- Migración 008 — Extensión de plazo de pago
-- ============================================================================
-- Permite que el cliente confirme "sí, voy a pagar" antes de que se cumplan
-- las 4 horas de plazo en "Pendiente de Pago", otorgando 4 horas adicionales.
-- La extensión es limitada (máximo N usos, ver TransactionService::MAX_EXTENSIONES_PLAZO)
-- para evitar que la orden quede pendiente indefinidamente.
--
-- PlazoExtendidoHasta: fecha/hora hasta la cual el cron de auto-cancelación
--                       debe respetar el plazo extendido (NULL = sin extensión).
-- ExtensionesPlazoUsadas: contador de veces que el cliente ha solicitado
--                       una extensión, para aplicar el tope máximo.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'PlazoExtendidoHasta'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE transacciones
        ADD COLUMN PlazoExtendidoHasta DATETIME NULL DEFAULT NULL AFTER FechaTransaccion,
        ADD COLUMN ExtensionesPlazoUsadas TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER PlazoExtendidoHasta,
        ADD INDEX idx_plazo_extendido (PlazoExtendidoHasta)',
    'SELECT "PlazoExtendidoHasta ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
