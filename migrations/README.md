# Migraciones SQL — Fix Pack 2

Estas migraciones son **obligatorias** antes de desplegar el código del Fix Pack 2.
Sin correrlas, el sistema va a tirar errores SQL al consultar tasas o transacciones.

## Cómo correrlas

### Opción A — phpMyAdmin (recomendado)

1. Entrar a `http://localhost/phpmyadmin`.
2. Seleccionar la base `jcenvios` (o el nombre que uses).
3. Click en la pestaña **SQL**.
4. Copiar todo el contenido de `001_route_active.sql` y pegarlo.
5. Click en **Continuar**.
6. Repetir con `002_confirmacion_recepcion.sql`.

### Opción B — Línea de comandos

```bash
cd D:\xampp\htdocs\Jcenvios\migrations
D:\xampp\mysql\bin\mysql.exe -u root jcenvios < 001_route_active.sql
D:\xampp\mysql\bin\mysql.exe -u root jcenvios < 002_confirmacion_recepcion.sql
```

## ¿Qué hace cada una?

### `001_route_active.sql` (F3.3)

Agrega `RutaActiva TINYINT(1) DEFAULT 1` a `tasas` + índice. Permite desactivar
una ruta (Chile→Venezuela, por ejemplo) sin borrar las tasas. El cliente sigue
viendo la tasa pero el backend bloquea la creación de transacciones.

### `002_confirmacion_recepcion.sql` (F3.1)

Agrega a `transacciones`:
- `ConfirmacionRecepcion ENUM('pendiente','recibido','no_recibido')` + índice
- `FechaConfirmacionRecepcion DATETIME NULL`

Permite que el cliente marque si recibió o no el dinero, con el flujo descrito
en el CHANGELOG.

## Son idempotentes

Las dos están escritas con `INFORMATION_SCHEMA` checks. Si las corres dos veces,
la segunda solo imprime "ya existe — skip" sin romper nada.

## En producción

Antes de aplicar:
1. **Hacé backup**: `mysqldump -u USER -p NOMBRE_BD > backup_pre_fp2.sql`
2. Correr las migraciones en orden (001, después 002)
3. Desplegar el código nuevo
4. Verificar logs de Apache/PHP por errores las primeras horas

Si algo falla y necesitás rollback (poco probable, son ADD COLUMN no destructivos):
```sql
ALTER TABLE tasas
    DROP INDEX idx_ruta_activa,
    DROP COLUMN RutaActiva;

ALTER TABLE transacciones
    DROP INDEX idx_confirmacion_recepcion,
    DROP COLUMN ConfirmacionRecepcion,
    DROP COLUMN FechaConfirmacionRecepcion;
```
