-- ============================================================================
-- Migración 014 — Normas de Uso (página pública fija)
-- ============================================================================
-- Tabla singleton (Id = 1) que almacena el contenido HTML de la página
-- pública de Normas, editable libremente por el Admin desde su panel.
-- El contenido se sanitiza en el backend (whitelist de tags) antes de
-- persistirse, por lo que puede insertarse tal cual en la página pública.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `normas` (
    `Id`                 INT(11) NOT NULL DEFAULT 1,
    `Contenido`          MEDIUMTEXT NULL,
    `ActualizadoPor`     INT(11) NULL,
    `FechaActualizacion` DATETIME NULL,
    PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `normas` (`Id`, `Contenido`, `ActualizadoPor`, `FechaActualizacion`)
VALUES (1, '<p>Próximamente publicaremos aquí nuestras normas de uso.</p>', NULL, NULL);
