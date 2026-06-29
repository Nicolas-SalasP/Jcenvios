-- ============================================================================
-- Migración 003 — Cancelación automática por tiempo expirado
-- ============================================================================
-- Agrega columna AutoCancelado a transacciones para identificar órdenes
-- que fueron canceladas automáticamente por el cron (no por el cliente).
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'AutoCancelado'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE transacciones
        ADD COLUMN AutoCancelado TINYINT(1) NOT NULL DEFAULT 0 AFTER FechaConfirmacionRecepcion,
        ADD INDEX idx_auto_cancelado (AutoCancelado)',
    'SELECT "AutoCancelado ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
