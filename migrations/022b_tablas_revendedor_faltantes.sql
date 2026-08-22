-- ============================================================================
-- Migración 022b — Tablas del módulo Revendedores que ninguna migración creaba
-- ============================================================================
-- POR QUÉ EXISTE ESTA MIGRACIÓN:
--
-- El deploy del 2026-08-22 murió aplicando la 023 con
-- "Table 'liquidaciones_revendedor' doesn't exist". La causa: estas 4 tablas
-- solo existían en database/schema.sql, que se usa para instalaciones nuevas y
-- para el CI, pero NUNCA corre contra producción. Producción solo recibe lo que
-- traen las migraciones, así que cualquier tabla agregada al schema sin su
-- migración correspondiente simplemente no llega.
--
-- Eso explica por qué el módulo Revendedores venía tirando 500 en producción:
-- no le faltaba una columna, le faltaban las tablas enteras.
--
-- Se numera 022b para que corra ANTES de la 023, que hace ALTER TABLE sobre
-- liquidaciones_revendedor y da por sentado que ya existe. El orden del runner
-- es alfabético: 022_ < 022b_ < 023_.
--
-- Las definiciones son las BASE, sin las columnas que agregan la 023 (Moneda,
-- ModoLiquidacion) y la 024 (MontoBase, MontoAjuste, MotivoAjuste): cada
-- migración sigue siendo dueña de lo suyo. Si la tabla ya existe (cualquier
-- entorno que se haya armado desde schema.sql), todo esto es un no-op.
--
-- REGLA PARA EL FUTURO: si agregás una tabla a database/schema.sql, agregá
-- también su migración. Si no, existe en desarrollo y en el CI, y no existe en
-- producción — y el CI pasa igual, porque valida contra el schema.
-- ============================================================================

CREATE TABLE IF NOT EXISTS liquidaciones_revendedor (
    LiquidacionID         INT(11)       NOT NULL AUTO_INCREMENT,
    UserID                INT(11)       NOT NULL,
    Monto                 DECIMAL(15,2) NOT NULL,
    PeriodoDesde          DATE          NOT NULL,
    PeriodoHasta          DATE          NOT NULL,
    CantidadTransacciones INT(11)       DEFAULT 0,
    Estado                ENUM('pendiente','pagada') DEFAULT 'pendiente',
    FechaCreacion         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaPago             DATETIME      DEFAULT NULL,
    ComprobanteURL        VARCHAR(255)  DEFAULT NULL,
    Notas                 TEXT          DEFAULT NULL,
    AdminUserID           INT(11)       DEFAULT NULL,
    PRIMARY KEY (LiquidacionID),
    KEY UserID (UserID),
    KEY AdminUserID (AdminUserID),
    CONSTRAINT liquidaciones_revendedor_ibfk_1
        FOREIGN KEY (UserID) REFERENCES usuarios (UserID),
    CONSTRAINT liquidaciones_revendedor_ibfk_2
        FOREIGN KEY (AdminUserID) REFERENCES usuarios (UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revendedor_cuentas (
    CuentaID         INT(11)      NOT NULL AUTO_INCREMENT,
    UserID           INT(11)      NOT NULL,
    Banco            VARCHAR(100) NOT NULL,
    TipoCuenta       VARCHAR(50)  NOT NULL,
    NumeroCuenta     VARCHAR(100) NOT NULL,
    TitularNombre    VARCHAR(150) NOT NULL,
    TitularDocumento VARCHAR(50)  NOT NULL,
    Instrucciones    TEXT         DEFAULT NULL,
    Activo           TINYINT(1)   NOT NULL DEFAULT 1,
    FechaCreacion    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (CuentaID),
    KEY idx_revendedor_cuentas_user (UserID),
    CONSTRAINT fk_revendedor_cuentas_user
        FOREIGN KEY (UserID) REFERENCES usuarios (UserID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revendedor_paises (
    ID                 INT(11)      NOT NULL AUTO_INCREMENT,
    UserID             INT(11)      NOT NULL,
    PaisDestinoID      INT(11)      NOT NULL,
    PorcentajeComision DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    Activo             TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (ID),
    UNIQUE KEY uq_user_pais (UserID, PaisDestinoID),
    KEY PaisDestinoID (PaisDestinoID),
    CONSTRAINT revendedor_paises_ibfk_1
        FOREIGN KEY (UserID) REFERENCES usuarios (UserID),
    CONSTRAINT revendedor_paises_ibfk_2
        FOREIGN KEY (PaisDestinoID) REFERENCES paises (PaisID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referido_config (
    Id                 INT(11)    NOT NULL DEFAULT 1,
    FormaManualActiva  TINYINT(1) NOT NULL DEFAULT 1,
    FormaLinkActiva    TINYINT(1) NOT NULL DEFAULT 1,
    ActualizadoPor     INT(11)    DEFAULT NULL,
    FechaActualizacion DATETIME   DEFAULT NULL,
    PRIMARY KEY (Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- referido_config es una tabla de fila única (Id = 1). El código la lee esperando
-- que exista; sin la fila, la configuración de referidos queda sin valores.
INSERT INTO referido_config (Id, FormaManualActiva, FormaLinkActiva)
SELECT 1, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM referido_config WHERE Id = 1);
