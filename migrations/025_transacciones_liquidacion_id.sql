-- ============================================================================
-- Migración 025 — transacciones.LiquidacionID faltante en producción
-- ============================================================================
-- BUG REAL EN PRODUCCIÓN: TransactionRepository filtra y actualiza
-- `transacciones.LiquidacionID` (marca qué orden ya fue pagada en qué
-- liquidación de revendedor) desde antes de la 022b/023/024. La columna está
-- en database/schema.sql (usado solo para instalaciones nuevas y CI) pero
-- NINGUNA migración la agregaba a una base ya existente — el mismo defecto que
-- describe la 022b, pero de una COLUMNA en vez de una tabla entera.
--
-- Evidencia (error_log de prod, repetida desde julio):
--   Unknown column 'T.LiquidacionID' in 'SELECT'
--     en TransactionRepository.php (getResellersSummary)
--   Unknown column 'LiquidacionID' in 'WHERE'
--     en las consultas de comisiones pendientes/liquidables
-- Rompe admin-revendedores.js (panel de Revendedores) con 500 cada vez que
-- se pisa esa pantalla.
--
-- Sin FK ni índice: schema.sql tampoco los declara para esta columna.
-- Idempotente: check contra INFORMATION_SCHEMA antes del ALTER.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacciones' AND COLUMN_NAME = 'LiquidacionID'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE transacciones ADD COLUMN LiquidacionID INT(11) DEFAULT NULL AFTER PermitirEdicionMonto',
    'SELECT "transacciones.LiquidacionID ya existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
