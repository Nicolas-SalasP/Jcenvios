-- ============================================================================
-- Migración 015 — Mensaje editable del aviso de horario laboral
-- ============================================================================
-- Hasta ahora el texto que ve el cliente al crear una orden fuera de horario
-- ("Tu orden será procesada en horario laboral. ¿Deseas continuar?") estaba
-- hardcodeado en dashboard.js. Se agrega a horario_override para que el admin
-- pueda editarlo desde el panel, independiente de si el override (Activo)
-- está vigente o no — el mensaje no expira con ExpiraEn.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'horario_override' AND COLUMN_NAME = 'Mensaje'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE horario_override ADD COLUMN Mensaje VARCHAR(500) NULL AFTER ExpiraEn',
    'SELECT "Mensaje ya existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE horario_override
SET Mensaje = 'Tu orden será procesada en horario laboral. ¿Deseas continuar?'
WHERE Id = 1 AND Mensaje IS NULL;
