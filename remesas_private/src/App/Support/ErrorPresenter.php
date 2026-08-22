<?php

namespace App\Support;

/**
 * Decide QUE mensaje de error puede ver el cliente y cual solo va al error_log.
 *
 * Problema que resuelve:
 * antes, tanto el handler global (`App\Core\exception_handler`) como los ~65
 * `catch` de los controladores devolvian `$e->getMessage()` crudo. Un fallo
 * interno filtraba detalles del motor al cliente. Ejemplo real reproducido:
 * renombrando la tabla `rate_limit` la API contestaba
 * `{"success":false,"error":"Table 'jcenvios.rate_limit' doesn't exist"}`.
 *
 * No se puede ocultar todo: el 74% de los `throw` del proyecto llevan mensajes
 * en español pensados para el usuario final, y el frontend los muestra tal cual
 * ("Credenciales incorrectas.", "El monto maximo permitido por orden es 50.000.").
 *
 * REGLA (dos compuertas, ambas obligatorias):
 *
 *   1. Compuerta de CLASE (deny-by-default).
 *      El codigo de la aplicacion lanza SIEMPRE `\Exception` pelada: 252 de 252
 *      `throw new` en `src/` usan esa clase exacta. Por lo tanto cualquier cosa
 *      que llegue como *subclase* (`mysqli_sql_exception`, `PDOException`,
 *      `JsonException`, `RuntimeException` de vendor...) o como `\Error`
 *      (`TypeError`, `ValueError`, `DivisionByZeroError`) es, por construccion,
 *      un fallo inesperado y jamas un mensaje curado. Se compara la clase EXACTA,
 *      no `instanceof`, porque `mysqli_sql_exception` ES un `\Exception`.
 *
 *      Esta compuerta es la que cierra el agujero de verdad: sin ella, un errno
 *      de MySQL que cayera por casualidad en el rango 400-499 pasaria el filtro
 *      del punto 2.
 *
 *   2. Compuerta de CODIGO HTTP.
 *      4xx  -> error de negocio/validacion, mensaje pensado para el usuario.
 *      5xx, 0, o cualquier valor fuera del rango -> fallo inesperado, generico.
 *
 * En `IS_DEV_ENVIRONMENT` se muestra siempre el mensaje real (ademas del trace
 * y el file que ya agregaba el handler) para no depurar a ciegas.
 *
 * El detalle completo (clase, codigo, mensaje real, archivo, linea y cadena de
 * `previous`) SIEMPRE va al `error_log`, incluso cuando se oculta al cliente.
 */
final class ErrorPresenter
{
    /**
     * Mensaje que ve el cliente cuando la excepcion no es de negocio.
     * Deliberadamente no dice nada del fallo real.
     */
    public const GENERIC_MESSAGE = 'Ocurrio un error inesperado al procesar la solicitud. Intentalo nuevamente en unos minutos.';

    /** Evita inundar el log con la misma excepcion si se presenta dos veces. */
    private static array $loggedHashes = [];

    private function __construct()
    {
    }

    public static function isDevEnvironment(): bool
    {
        return defined('IS_DEV_ENVIRONMENT') && IS_DEV_ENVIRONMENT;
    }

    /**
     * Normaliza `getCode()`, que en PHP puede devolver int, string o cualquier
     * cosa que haya pasado una subclase (PDOException usa strings tipo "42S02").
     */
    public static function normalizeCode(\Throwable $e): int
    {
        $code = $e->getCode();

        if (is_int($code)) {
            return $code;
        }

        // "42S02" o "" -> 0. Un "404" string si se respeta.
        if (is_string($code) && preg_match('/^-?\d+$/', $code) === 1) {
            return (int) $code;
        }

        return 0;
    }

    /**
     * Compuerta 1 + compuerta 2: ¿el mensaje esta pensado para el usuario final?
     */
    public static function isUserFacing(\Throwable $e): bool
    {
        // Compuerta 1: clase exacta \Exception (ver docblock de la clase).
        if (get_class($e) !== \Exception::class) {
            return false;
        }

        // Compuerta 2: codigo HTTP 4xx.
        $code = self::normalizeCode($e);

        return $code >= 400 && $code <= 499;
    }

    /**
     * Codigo HTTP a devolver. Usa el de la excepcion si es un HTTP valido,
     * si no cae al fallback que decida el llamador.
     */
    public static function httpStatus(\Throwable $e, int $fallback = 500): int
    {
        $code = self::normalizeCode($e);

        return ($code >= 400 && $code <= 599) ? $code : $fallback;
    }

    /**
     * Mensaje seguro para mandarle al cliente.
     *
     * OJO: tiene efecto de borde deliberado. Cuando decide OCULTAR el mensaje
     * real, lo escribe antes al `error_log`. Es a proposito: los ~65 `catch` de
     * los controladores no loggeaban nada, asi que si solo se cambiara el texto
     * devuelto el detalle del fallo se perderia por completo. Haciendolo aca se
     * garantiza que ninguna ruta pierda la traza, con un cambio de una linea en
     * cada sitio de llamada.
     */
    public static function publicMessage(\Throwable $e, string $context = ''): string
    {
        if (self::isDevEnvironment() || self::isUserFacing($e)) {
            return $e->getMessage();
        }

        self::logException($e, $context);

        return self::GENERIC_MESSAGE;
    }

    /**
     * Vuelca el detalle completo al error_log. Idempotente por excepcion+contexto.
     */
    public static function logException(\Throwable $e, string $context = ''): void
    {
        $hash = spl_object_id($e) . '|' . $context;
        if (isset(self::$loggedHashes[$hash])) {
            return;
        }
        self::$loggedHashes[$hash] = true;

        error_log(self::describe($e, $context));
    }

    /**
     * Linea de log con todo el detalle, incluida la cadena de `previous`.
     */
    public static function describe(\Throwable $e, string $context = ''): string
    {
        $parts = [];
        $prefix = $context !== '' ? "[$context] " : '';

        $current = $e;
        $depth = 0;
        while ($current !== null && $depth < 5) {
            $parts[] = sprintf(
                '%s%s(code=%s): %s en %s:%d',
                $depth === 0 ? '' : ' <- previous: ',
                get_class($current),
                var_export($current->getCode(), true),
                $current->getMessage(),
                $current->getFile(),
                $current->getLine()
            );
            $current = $current->getPrevious();
            $depth++;
        }

        return $prefix . 'Excepcion no expuesta al cliente: ' . implode('', $parts);
    }

    /**
     * Solo para tests: limpia el deduplicador del log.
     */
    public static function resetLogCache(): void
    {
        self::$loggedHashes = [];
    }
}
