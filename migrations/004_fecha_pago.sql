-- ============================================================================
-- Migración 004 — FechaPago en transacciones
-- ============================================================================
-- Agrega FechaPago: timestamp que se registra cuando el admin sube el
-- comprobante de envío y la orden pasa a estado "Exitoso".
-- Se usa para disparar el modal de confirmación de recepción al cliente
-- 2 horas después del pago.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'FechaPago'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE transacciones
        ADD COLUMN FechaPago DATETIME NULL AFTER AutoCancelado,
        ADD INDEX idx_fecha_pago (FechaPago)',
    'SELECT "FechaPago ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
