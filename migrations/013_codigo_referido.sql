-- ============================================================================
-- Migración 013 — Código de referido de revendedores
-- ============================================================================
-- Portado del concepto de la V2 (Laravel): cada revendedor puede tener un
-- código único de 8 caracteres. Un cliente nuevo puede quedar vinculado a un
-- revendedor de 2 formas (activables independientemente por el admin):
--   Forma 1 (manual): el cliente ingresa el código a mano al registrarse.
--   Forma 2 (link):   el cliente llega con ?ref=CODIGO en la URL de registro.
-- ============================================================================

ALTER TABLE `usuarios`
    ADD COLUMN `CodigoReferido` VARCHAR(10) NULL UNIQUE AFTER `MaxCuentasRevendedor`,
    ADD COLUMN `ReferidoPor` INT(11) NULL AFTER `CodigoReferido`,
    ADD CONSTRAINT `fk_usuarios_referido_por` FOREIGN KEY (`ReferidoPor`) REFERENCES `usuarios` (`UserID`) ON DELETE SET NULL;

-- Config global (singleton, Id=1) para activar/desactivar cada forma de referido.
CREATE TABLE IF NOT EXISTS `referido_config` (
    `Id`               INT(11) NOT NULL DEFAULT 1,
    `FormaManualActiva` TINYINT(1) NOT NULL DEFAULT 1,
    `FormaLinkActiva`   TINYINT(1) NOT NULL DEFAULT 1,
    `ActualizadoPor`    INT(11) NULL,
    `FechaActualizacion` DATETIME NULL,
    PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `referido_config` (`Id`, `FormaManualActiva`, `FormaLinkActiva`) VALUES (1, 1, 1);
