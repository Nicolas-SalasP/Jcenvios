-- ============================================================================
-- Migración 012 — Cuentas bancarias propias del revendedor + límite configurable
-- ============================================================================
-- Permite a cada revendedor registrar sus propias cuentas bancarias (hasta
-- MaxCuentasRevendedor, configurable por el admin por revendedor) para que
-- sus clientes referidos puedan depositar directamente en ellas.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `revendedor_cuentas` (
    `CuentaID`         INT(11) NOT NULL AUTO_INCREMENT,
    `UserID`           INT(11) NOT NULL,
    `Banco`            VARCHAR(100) NOT NULL,
    `TipoCuenta`       VARCHAR(50) NOT NULL,
    `NumeroCuenta`     VARCHAR(100) NOT NULL,
    `TitularNombre`    VARCHAR(150) NOT NULL,
    `TitularDocumento` VARCHAR(50) NOT NULL,
    `Instrucciones`    TEXT NULL,
    `Activo`           TINYINT(1) NOT NULL DEFAULT 1,
    `FechaCreacion`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`CuentaID`),
    KEY `idx_revendedor_cuentas_user` (`UserID`),
    CONSTRAINT `fk_revendedor_cuentas_user` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Límite de cuentas que puede registrar cada revendedor (el admin puede bajarlo
-- para revendedores en los que confía menos). Default 6.
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'MaxCuentasRevendedor'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN MaxCuentasRevendedor INT(11) NOT NULL DEFAULT 6 AFTER PorcentajeComision',
    'SELECT "MaxCuentasRevendedor ya existe — skip" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Registro de a cuál cuenta del revendedor se le indicó pagar al cliente (si aplica).
SET @col_exists2 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacciones' AND COLUMN_NAME = 'CuentaRevendedorID'
);
SET @ddl2 := IF(@col_exists2 = 0,
    'ALTER TABLE transacciones
        ADD COLUMN CuentaRevendedorID INT(11) NULL,
        ADD CONSTRAINT fk_transacciones_cuenta_revendedor FOREIGN KEY (CuentaRevendedorID) REFERENCES revendedor_cuentas (CuentaID) ON DELETE SET NULL',
    'SELECT "CuentaRevendedorID ya existe — skip" AS info');
PREPARE stmt2 FROM @ddl2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
