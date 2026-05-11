-- ============================================================================
-- Migración 002 — F3.1: confirmación de recepción del cliente
-- ============================================================================
-- Agrega dos columnas a transacciones:
--   ConfirmacionRecepcion: estado de la confirmación del cliente
--     - 'pendiente'    → no marcó nada todavía (default)
--     - 'recibido'     → confirmó recepción (NO se puede cambiar)
--     - 'no_recibido'  → reportó no haber recibido (sí se puede cambiar a recibido)
--   FechaConfirmacionRecepcion: cuándo el cliente marcó por última vez
--
-- Ejecutar en phpMyAdmin → seleccionar BD → pestaña SQL → pegar y "Continuar".
-- Idempotente.
-- ============================================================================

-- Columna 1: ConfirmacionRecepcion
SET @col1_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'ConfirmacionRecepcion'
);

SET @ddl1 := IF(@col1_exists = 0,
    "ALTER TABLE transacciones
        ADD COLUMN ConfirmacionRecepcion ENUM('pendiente','recibido','no_recibido')
            NOT NULL DEFAULT 'pendiente' AFTER EstadoID,
        ADD INDEX idx_confirmacion_recepcion (ConfirmacionRecepcion)",
    'SELECT "ConfirmacionRecepcion ya existe — skip" AS info'
);
PREPARE stmt1 FROM @ddl1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- Columna 2: FechaConfirmacionRecepcion
SET @col2_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transacciones'
      AND COLUMN_NAME = 'FechaConfirmacionRecepcion'
);

SET @ddl2 := IF(@col2_exists = 0,
    'ALTER TABLE transacciones
        ADD COLUMN FechaConfirmacionRecepcion DATETIME NULL AFTER ConfirmacionRecepcion',
    'SELECT "FechaConfirmacionRecepcion ya existe — skip" AS info'
);
PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
