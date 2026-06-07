-- Migración 003: eliminar columnas duplicadas/sin uso de `usuarios`
--
-- Contexto: el control de bloqueo de login usa `FailedLoginAttempts` y `LockoutUntil`.
-- Las columnas `IntentosFallidos` y `BloqueadoHasta` eran duplicados de un modelo anterior,
-- sin ninguna referencia en el código y completamente vacías (0 filas con datos).
-- (NOTA: `Activo` SÍ se usa en UserRepository -SELECT e INSERT-, por lo que NO se elimina.)
--
-- Idempotente: usa DROP COLUMN IF EXISTS (soportado por MariaDB 10.x).

ALTER TABLE usuarios DROP COLUMN IF EXISTS IntentosFallidos;
ALTER TABLE usuarios DROP COLUMN IF EXISTS BloqueadoHasta;
