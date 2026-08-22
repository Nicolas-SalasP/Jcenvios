-- ============================================================================
-- Migración 022 — Arreglo del rate limiter (estaba funcionalmente muerto)
-- ============================================================================
-- La migración 006 creó la tabla con UNIQUE KEY (ip, accion, ventana_fin).
-- RateLimiterService::check() calcula `ventana_fin = NOW() + ventana` en PHP
-- en CADA llamada, así que cada intento generaba una clave única distinta:
-- el ON DUPLICATE KEY UPDATE no disparaba NUNCA y `hits` quedaba siempre en 1.
-- Resultado: los 6 límites (loginUser, requestPasswordReset, send2faCode,
-- resend2faCode, registerUser, submitContactForm) jamás se alcanzaban.
-- Confirmado en la BD de desarrollo: 73 filas de 'loginUser' para la misma IP,
-- todas con hits = 1.
--
-- El fix es que la clave única sea (ip, accion) — una sola fila por IP+acción,
-- con `ventana_fin` como columna de estado que el upsert reinicia cuando la
-- ventana venció. Ver RateLimiterService::check().
--
-- Antes de crear la UNIQUE nueva hay que colapsar los duplicados heredados o
-- el ALTER falla con "Duplicate entry". Los datos de rate limiting son
-- efímeros por naturaleza (ventanas de 1 a 5 minutos), no se preservan:
--   1. Se borran las filas con la ventana ya vencida (basura pura).
--   2. De las que sobreviven se conserva solo la de mayor `id` por (ip, accion),
--      que es también la de mayor `ventana_fin` — `ventana_fin` crece de forma
--      monótona para una misma acción, porque siempre es NOW() + ventana.
--
-- Idempotente: los DELETE son no-ops si ya no hay duplicados, y los ALTER
-- están guardados por checks contra INFORMATION_SCHEMA.STATISTICS.
-- ============================================================================

-- 1. Purgar ventanas vencidas.
DELETE FROM rate_limit WHERE ventana_fin <= NOW();

-- 2. Deduplicar: dejar una sola fila por (ip, accion), la más reciente.
--    La tabla derivada se materializa, así que MySQL permite el DELETE sobre
--    rate_limit leyendo de rate_limit en el mismo statement.
DELETE r FROM rate_limit r
INNER JOIN (
    SELECT ip, accion, MAX(id) AS keep_id
    FROM rate_limit
    GROUP BY ip, accion
) k ON r.ip = k.ip AND r.accion = k.accion AND r.id <> k.keep_id;

-- 3. Sacar la UNIQUE vieja que incluía ventana_fin.
SET @old_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rate_limit'
      AND INDEX_NAME = 'uq_ip_accion_ventana'
);

SET @ddl := IF(@old_idx > 0,
    'ALTER TABLE rate_limit DROP INDEX uq_ip_accion_ventana',
    'SELECT "uq_ip_accion_ventana ya no existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Crear la UNIQUE nueva (ip, accion) — la que hace funcionar el upsert.
SET @new_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rate_limit'
      AND INDEX_NAME = 'uq_ip_accion'
);

SET @ddl := IF(@new_idx = 0,
    'ALTER TABLE rate_limit ADD UNIQUE KEY uq_ip_accion (ip, accion)',
    'SELECT "uq_ip_accion ya existe — skip" AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
