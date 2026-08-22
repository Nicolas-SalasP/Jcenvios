-- ============================================================================
-- Migración 020 — Timestamps explícitos de completado/cancelado
-- ============================================================================
-- La tabla admin de órdenes solo mostraba una fecha genérica (FechaTransaccion,
-- que en realidad es la fecha de EMISIÓN). No había forma de saber cuándo se
-- completó o canceló una orden sin ir a la bitácora. Se agregan estas dos
-- columnas y se setean en cada transición de estado real (ver
-- TransactionRepository::uploadAdminProof/updateStatus/cancel/autoCancelExpired).
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'FechaCompletado'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE transacciones
        ADD COLUMN FechaCompletado DATETIME NULL DEFAULT NULL AFTER FechaPago,
        ADD COLUMN FechaCancelacion DATETIME NULL DEFAULT NULL AFTER FechaCompletado',
    'SELECT "FechaCompletado ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
