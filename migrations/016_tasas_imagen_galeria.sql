-- ============================================================================
-- Migración 016 — Galería de Tasas Visuales (múltiples imágenes por tipo)
-- ============================================================================
-- El módulo Tasas Visuales (migración 011) era singleton por tipo (una sola
-- imagen para 'whatsapp' y otra para 'web'). Se cambia a galería: el admin
-- puede subir varias imágenes por tipo, cada una con Título y Descripción
-- opcionales, y eliminarlas individualmente.
-- Se quita el UNIQUE KEY sobre TipoFuente (permite N filas por tipo) y se
-- agregan las columnas Titulo/Descripcion. Se eliminan las 2 filas placeholder
-- (RutaImagen NULL) sembradas por la migración 011 — ya no tienen sentido
-- en el modelo de galería.
-- ============================================================================

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasas_imagen' AND INDEX_NAME = 'uk_tasas_imagen_tipo'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE tasas_imagen DROP INDEX uk_tasas_imagen_tipo, ADD INDEX idx_tasas_imagen_tipo (TipoFuente)',
    'SELECT "uk_tasas_imagen_tipo ya no existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasas_imagen' AND COLUMN_NAME = 'Titulo'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tasas_imagen
        ADD COLUMN Titulo VARCHAR(150) NULL AFTER RutaImagen,
        ADD COLUMN Descripcion TEXT NULL AFTER Titulo',
    'SELECT "Titulo ya existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DELETE FROM tasas_imagen WHERE RutaImagen IS NULL;
