-- ============================================================================
-- Migración 009 — Override manual de horario laboral
-- ============================================================================
-- Permite al Admin forzar manualmente el aviso de "fuera de horario" que el
-- frontend muestra en checkBusinessHours() (dashboard.js), o suprimirlo
-- temporalmente aunque el sistema esté técnicamente fuera de horario.
--
-- Fila única (Id = 1):
--   Activo          -> 1 = forzar mostrar aviso, 0 = suprimir aviso, NULL/sin
--                       fila = sin override (se usa la lógica normal del front).
--   ForzadoPor      -> UserID del admin que hizo el cambio.
--   FechaActivacion -> Momento en que se aplicó el override.
--   ExpiraEn        -> Momento en que el override deja de tener efecto
--                       (fin del horario laboral normal del día actual).
--                       El sistema debe re-evaluar automáticamente pasada
--                       esta fecha, sin necesitar acción del admin.
-- ============================================================================

CREATE TABLE IF NOT EXISTS horario_override (
    Id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    Activo TINYINT(1) NOT NULL DEFAULT 0,
    ForzadoPor INT UNSIGNED NULL,
    FechaActivacion DATETIME NULL,
    ExpiraEn DATETIME NULL
);

INSERT IGNORE INTO horario_override (Id, Activo, ForzadoPor, FechaActivacion, ExpiraEn)
VALUES (1, 0, NULL, NULL, NULL);
