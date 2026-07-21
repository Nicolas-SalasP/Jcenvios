-- ============================================================================
-- Migración 017 — Fecha de cambio de estado de verificación
-- ============================================================================
-- Bug reportado: en admin/verificaciones.php los usuarios recién Rechazados
-- no se veían "arriba" de la lista. Causa: la tabla usuarios no registraba
-- CUÁNDO cambió VerificacionEstadoID, solo FechaRegistro (fecha de alta de
-- la cuenta). El listado ordenaba el grupo de Rechazados por FechaRegistro
-- ASC (cuentas más viejas primero), no por recencia del rechazo — un usuario
-- rechazado hoy pero registrado hace meses quedaba enterrado bajo cuentas
-- viejas rechazadas hace tiempo.
-- Se agrega FechaVerificacion, actualizada cada vez que un admin aprueba o
-- rechaza (UserRepository::updateVerificationStatus). El listado ahora ordena
-- el grupo Rechazado por esta fecha DESC.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'FechaVerificacion'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN FechaVerificacion DATETIME NULL AFTER VerificacionEstadoID',
    'SELECT "FechaVerificacion ya existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
