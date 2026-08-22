<?php
namespace App\Support;

/**
 * Lock de exclusión mutua para los crons.
 *
 * MECANISMO ELEGIDO: flock() sobre un archivo dedicado (uno por cron).
 *
 * Por qué flock y no GET_LOCK() de MySQL:
 *   - Funciona igual en Linux (cPanel, producción) y en Windows/XAMPP
 *     (desarrollo): PHP implementa flock() sobre LockFileEx en Windows.
 *   - No consume una conexión a la base de datos ni depende de que la BD esté
 *     arriba. Un cron que arranca cuando MySQL está caído igual debe poder
 *     decidir si ya hay otra instancia corriendo.
 *   - El sistema operativo libera el lock automáticamente cuando el proceso
 *     muere, sea por excepción, fatal error, kill o timeout. GET_LOCK() también
 *     se libera al cerrar la conexión, pero si el service reusa un singleton de
 *     conexión que sobrevive al error, el lock puede quedar tomado de más.
 *
 * Uso:
 *   if (!\App\Support\CronLock::acquire('tasas')) {
 *       echo "Ya hay una instancia corriendo. Saliendo.\n";
 *       exit(0);
 *   }
 *
 * El lock se libera solo al terminar el proceso (register_shutdown_function +
 * cierre del handle por el SO). No hace falta llamar a release() a mano.
 */
class CronLock
{
    /** @var array<string, resource> Handles abiertos, indexados por nombre de lock. */
    private static array $handles = [];

    private static bool $shutdownRegistered = false;

    /**
     * Intenta tomar el lock sin bloquear (LOCK_NB): si otra instancia lo tiene,
     * devuelve false de inmediato en vez de quedarse esperando.
     */
    public static function acquire(string $name): bool
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
        if ($name === '') {
            $name = 'cron';
        }

        if (isset(self::$handles[$name])) {
            return true; // Ya tomado por este mismo proceso.
        }

        $dir = self::lockDir();
        $path = $dir . DIRECTORY_SEPARATOR . 'cron_' . $name . '.lock';

        $handle = @fopen($path, 'c');
        if ($handle === false) {
            // No se pudo abrir el archivo de lock. Se deja correr el cron (peor
            // es no ejecutarlo nunca), pero queda registrado en el error_log.
            error_log("CRON LOCK: no se pudo abrir el archivo de lock {$path}. El cron '{$name}' corre sin protección de concurrencia.");
            return true;
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        // Dato informativo para diagnóstico; el lock lo da flock, no el contenido.
        @ftruncate($handle, 0);
        @fwrite($handle, (string) getmypid() . ' ' . date('Y-m-d H:i:s') . "\n");
        @fflush($handle);

        self::$handles[$name] = $handle;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'releaseAll']);
        }

        return true;
    }

    public static function release(string $name): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
        if (!isset(self::$handles[$name])) {
            return;
        }
        $handle = self::$handles[$name];
        unset(self::$handles[$name]);
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    public static function releaseAll(): void
    {
        foreach (array_keys(self::$handles) as $name) {
            self::release($name);
        }
    }

    /**
     * Directorio de locks. Preferimos remesas_private/locks/ (siempre existe y
     * es escribible por el usuario del hosting); si no se puede crear, caemos al
     * temp del sistema.
     *
     * Niveles: __DIR__ = remesas_private/src/App/Support
     *   /..       -> src/App
     *   /../..    -> src
     *   /../../.. -> remesas_private
     */
    private static function lockDir(): string
    {
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
        return sys_get_temp_dir();
    }
}
