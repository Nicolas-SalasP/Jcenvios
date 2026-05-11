-- ============================================================================
-- Migración 001 — F3.3: flag RutaActiva en tabla tasas
-- ============================================================================
-- Permite desactivar una ruta (Origen → Destino) sin borrar las tasas.
-- Los clientes seguirán viendo la tasa pero el backend bloqueará la creación
-- de transacciones cuando RutaActiva = 0.
--
-- El flag vive solo en la tasa REFERENCIAL de cada ruta (EsReferencial=1).
-- Las bandas comerciales heredan el estado por herencia lógica.
--
-- Ejecutar en phpMyAdmin → seleccionar BD → pestaña SQL → pegar y "Continuar".
-- Idempotente: se puede correr varias veces sin romper (INFORMATION_SCHEMA check).
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasas'
      AND COLUMN_NAME = 'RutaActiva'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tasas
        ADD COLUMN RutaActiva TINYINT(1) NOT NULL DEFAULT 1 AFTER EsRiesgoso,
        ADD INDEX idx_ruta_activa (RutaActiva)',
    'SELECT "RutaActiva ya existe — skip" AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Asegurar que todas las tasas existentes queden activas por default
UPDATE tasas SET RutaActiva = 1 WHERE RutaActiva IS NULL;
