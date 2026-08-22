-- ============================================================================
-- Migración 024 — Ajuste manual con motivo sobre la liquidación de revendedor
-- ============================================================================
-- PROBLEMA: a veces el admin le paga al revendedor un monto distinto al
-- calculado (un anticipo ya entregado, un descuento acordado, un redondeo).
-- Hasta ahora no había forma de registrarlo: la única cifra editable era la
-- TASA de conversión del modo consolidado.
--
-- POR QUÉ NO SE USA LA TASA PARA ESTO (decisión explícita, no olvido):
-- un ajuste NO es un tipo de cambio. Si un anticipo se registra deformando la
-- tasa, `liquidacion_detalle_moneda` termina afirmando que hubo una conversión
-- cambiaria que nunca ocurrió, y el pago deja de ser reconstruible seis meses
-- después. El ajuste tiene que quedar guardado como lo que es.
--
-- MODELO: el ajuste es un DELTA, no un monto final que pisa el cálculo.
--
--   * MontoBase   — lo que calculó el sistema ANTES del ajuste.
--   * MontoAjuste — el delta que puso el admin a mano (positivo o negativo).
--   * MotivoAjuste— por qué. Obligatorio a nivel aplicación cuando el ajuste
--                   es distinto de cero (LiquidacionService lo rechaza con 422).
--                   Queda NULL a nivel SQL porque la enorme mayoría de las
--                   liquidaciones no llevan ajuste.
--
--   Invariante:  Monto = MontoBase + MontoAjuste
--
-- `Monto` NO cambia de significado en el sentido que importa: sigue siendo el
-- monto EFECTIVAMENTE PAGADO al revendedor, que es lo que leen el panel del
-- revendedor (ClientController::getResellerSummary) y el listado del admin.
-- Lo que se agrega es la trazabilidad de cómo se llegó a esa cifra.
--
-- El ajuste va SIEMPRE en la moneda de la propia liquidación
-- (liquidaciones_revendedor.Moneda). En modo 'por_moneda' hay N liquidaciones
-- y cada una lleva su propio ajuste en su propia moneda; en 'consolidado_clp'
-- hay una sola y el ajuste es en CLP. No hay ninguna suma entre monedas acá.
--
-- BACKFILL: todo lo histórico se creó sin ajuste, así que MontoBase = Monto,
-- MontoAjuste = 0 y MotivoAjuste = NULL es exacto, no un default ciego.
--
-- Idempotente: check contra INFORMATION_SCHEMA + backfill que sólo toca filas
-- que todavía no tienen MontoBase asignado.
-- ============================================================================

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'liquidaciones_revendedor'
      AND COLUMN_NAME = 'MontoAjuste'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE liquidaciones_revendedor
        ADD COLUMN MontoBase DECIMAL(15,2) NULL DEFAULT NULL
            COMMENT ''Monto calculado por el sistema, antes del ajuste manual'' AFTER Monto,
        ADD COLUMN MontoAjuste DECIMAL(15,2) NOT NULL DEFAULT 0
            COMMENT ''Delta manual del admin, en la Moneda de esta liquidacion. Monto = MontoBase + MontoAjuste'' AFTER MontoBase,
        ADD COLUMN MotivoAjuste VARCHAR(255) NULL DEFAULT NULL
            COMMENT ''Obligatorio (app) si MontoAjuste <> 0'' AFTER MontoAjuste',
    'SELECT "liquidaciones_revendedor.MontoAjuste ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: lo histórico no tiene ajuste, así que la base ES el monto pagado.
-- Sólo toca lo que todavía está sin base; reejecutar esto no pisa nada.
UPDATE liquidaciones_revendedor
   SET MontoBase = Monto
 WHERE MontoBase IS NULL;

UPDATE liquidaciones_revendedor
   SET MontoAjuste = 0
 WHERE MontoAjuste IS NULL;
