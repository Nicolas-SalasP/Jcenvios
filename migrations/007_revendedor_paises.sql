-- ============================================================================
-- Migración 007 — Tabla revendedor_paises
-- ============================================================================
-- Comisión por país, override del % global de cada revendedor.
-- Usada en TransactionRepository::getResellerCommissionRate /
-- getResellerPaisesConfig / upsertResellerPaises.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `revendedor_paises` (
    `id`                  INT(11) NOT NULL AUTO_INCREMENT,
    `UserID`              INT(11) NOT NULL,
    `PaisDestinoID`       INT(11) NOT NULL,
    `PorcentajeComision`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Activo`              TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_pais` (`UserID`, `PaisDestinoID`),
    KEY `idx_pais_destino` (`PaisDestinoID`),
    CONSTRAINT `fk_revendedor_paises_user` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT `fk_revendedor_paises_pais` FOREIGN KEY (`PaisDestinoID`) REFERENCES `paises` (`PaisID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
