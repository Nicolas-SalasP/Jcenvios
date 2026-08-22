-- ============================================================================
-- Migración 023 — Liquidaciones de revendedor multi-moneda
-- ============================================================================
-- BUG DE DINERO REAL: la comisión del revendedor se calcula en TransactionService
-- como ($montoOrigen * $porcentaje) / 100, o sea que queda expresada en la
-- MONEDA DE ORIGEN de cada orden (transacciones.MonedaOrigen). En la BD conviven
-- órdenes en CLP, COP, PEN y USD. Sin embargo `liquidaciones_revendedor.Monto`
-- era un decimal pelado, sin moneda, y el cálculo hacía SUM(ComisionRevendedor)
-- sin agrupar: 5.000 CLP + 20.000 COP se pagaban como "25.000" pesos chilenos.
--
-- Se agregan dos columnas a la liquidación y una tabla de detalle:
--
--   * Moneda           — moneda EN LA QUE SE PAGA esta liquidación.
--   * ModoLiquidacion  — 'por_moneda'     : una liquidación por cada moneda,
--                                           sin conversión ni riesgo cambiario.
--                        'consolidado_clp': una sola liquidación en CLP,
--                                           convirtiendo el resto de monedas.
--
--   * liquidacion_detalle_moneda — deja auditable QUÉ se convirtió y A QUÉ
--     CAMBIO. Sin esto, dentro de seis meses nadie puede reconstruir el pago.
--     TasaConversion se guarda SIEMPRE como "CLP por 1 unidad de Moneda"
--     (factor multiplicativo): MontoConvertido = MontoOriginal * TasaConversion.
--     En modo 'por_moneda' va una sola fila con TasaConversion = 1.
--
-- BACKFILL: se verificó contra la BD real antes de escribir esta migración —
-- las 2 liquidaciones existentes son ambas del UserID 100, único revendedor con
-- comisiones, cuyas órdenes son todas CLP. Por eso 'CLP' es el valor correcto
-- para lo existente y no un default ciego. La sentencia de backfill de abajo
-- es defensiva: sólo toca filas que hayan quedado con Moneda vacía.
--
-- Idempotente: checks contra INFORMATION_SCHEMA + CREATE TABLE IF NOT EXISTS.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'liquidaciones_revendedor'
      AND COLUMN_NAME = 'Moneda'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE liquidaciones_revendedor
        ADD COLUMN Moneda VARCHAR(3) NOT NULL DEFAULT ''CLP'' AFTER Monto,
        ADD COLUMN ModoLiquidacion VARCHAR(20) NOT NULL DEFAULT ''por_moneda'' AFTER Moneda',
    'SELECT "liquidaciones_revendedor.Moneda ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill defensivo (no pisa nada que ya tenga moneda asignada).
UPDATE liquidaciones_revendedor
   SET Moneda = 'CLP'
 WHERE Moneda IS NULL OR Moneda = '';

UPDATE liquidaciones_revendedor
   SET ModoLiquidacion = 'por_moneda'
 WHERE ModoLiquidacion IS NULL OR ModoLiquidacion = '';

CREATE TABLE IF NOT EXISTS liquidacion_detalle_moneda (
    DetalleID       INT(11)        NOT NULL AUTO_INCREMENT,
    LiquidacionID   INT(11)        NOT NULL,
    Moneda          VARCHAR(3)     NOT NULL,
    MontoOriginal   DECIMAL(15,2)  NOT NULL,
    TasaConversion  DECIMAL(18,8)  NULL DEFAULT NULL COMMENT 'CLP por 1 unidad de Moneda. NULL = no aplica.',
    MontoConvertido DECIMAL(15,2)  NOT NULL,
    Cantidad        INT(11)        NOT NULL DEFAULT 0 COMMENT 'Nro de transacciones de esta moneda',
    PRIMARY KEY (DetalleID),
    UNIQUE KEY uk_liq_moneda (LiquidacionID, Moneda),
    KEY idx_liquidacion (LiquidacionID),
    CONSTRAINT fk_liq_detalle_liquidacion
        FOREIGN KEY (LiquidacionID) REFERENCES liquidaciones_revendedor (LiquidacionID)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill del detalle para las liquidaciones históricas que no lo tengan.
-- Todas son CLP (verificado arriba), así que TasaConversion = 1 y
-- MontoConvertido = Monto. INSERT ... SELECT con NOT EXISTS = idempotente.
INSERT INTO liquidacion_detalle_moneda
    (LiquidacionID, Moneda, MontoOriginal, TasaConversion, MontoConvertido, Cantidad)
SELECT L.LiquidacionID, L.Moneda, L.Monto, 1.00000000, L.Monto,
       COALESCE(L.CantidadTransacciones, 0)
  FROM liquidaciones_revendedor L
 WHERE NOT EXISTS (
       SELECT 1 FROM liquidacion_detalle_moneda D
        WHERE D.LiquidacionID = L.LiquidacionID
 );
