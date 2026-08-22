<?php

use PHPUnit\Framework\TestCase;
use App\Support\ErrorPresenter;

/**
 * Cubre la regla de exposicion de mensajes de error al cliente.
 *
 * Contexto: antes se devolvia `$e->getMessage()` crudo siempre. Renombrando la
 * tabla `rate_limit` la API contestaba al cliente
 * "Table 'jcenvios.rate_limit' doesn't exist".
 */
final class ErrorPresenterTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorPresenter::resetLogCache();
    }

    // ------------------------------------------------------------------
    // Compuerta 2: codigo HTTP
    // ------------------------------------------------------------------

    /**
     * @dataProvider proveedorCodigos4xx
     */
    public function testExcepcionDeNegocio4xxMuestraElMensajeReal(int $codigo): void
    {
        $e = new Exception('Credenciales incorrectas.', $codigo);

        $this->assertTrue(ErrorPresenter::isUserFacing($e));
        $this->assertSame('Credenciales incorrectas.', ErrorPresenter::publicMessage($e));
    }

    public static function proveedorCodigos4xx(): array
    {
        return [
            '400' => [400],
            '401' => [401],
            '403' => [403],
            '404' => [404],
            '409' => [409],
            '422' => [422],
            '429' => [429],
            'limite inferior' => [400],
            'limite superior' => [499],
        ];
    }

    /**
     * @dataProvider proveedorCodigosNoExpuestos
     */
    public function testExcepcionInesperadaDevuelveElGenerico(int $codigo): void
    {
        $e = new Exception("Table 'jcenvios.rate_limit' doesn't exist", $codigo);

        $this->assertFalse(ErrorPresenter::isUserFacing($e));
        $this->assertSame(ErrorPresenter::GENERIC_MESSAGE, ErrorPresenter::publicMessage($e));
    }

    public static function proveedorCodigosNoExpuestos(): array
    {
        return [
            'sin codigo (0)' => [0],
            '500' => [500],
            '503' => [503],
            'limite 599' => [599],
            'fuera de rango bajo' => [399],
            'errno de mysql' => [1146],
            'negativo' => [-1],
        ];
    }

    public function testMensajeGenericoNoContieneNingunDetalleInterno(): void
    {
        $generico = ErrorPresenter::GENERIC_MESSAGE;

        $this->assertStringNotContainsStringIgnoringCase('sql', $generico);
        $this->assertStringNotContainsStringIgnoringCase('table', $generico);
        $this->assertStringNotContainsStringIgnoringCase('mysql', $generico);
        $this->assertStringNotContainsStringIgnoringCase('.php', $generico);
    }

    // ------------------------------------------------------------------
    // Compuerta 1: clase exacta. Es la que cierra el agujero de verdad.
    // ------------------------------------------------------------------

    public function testSubclaseDeExceptionNoSeExponeAunqueTraigaCodigo4xx(): void
    {
        // RuntimeException es la clase padre de mysqli_sql_exception. Un errno
        // que cayera por casualidad en 400-499 pasaria la compuerta de codigo,
        // pero no la de clase.
        $e = new RuntimeException("Table 'jcenvios.rate_limit' doesn't exist", 404);

        $this->assertFalse(ErrorPresenter::isUserFacing($e));
        $this->assertSame(ErrorPresenter::GENERIC_MESSAGE, ErrorPresenter::publicMessage($e));
    }

    public function testMysqliSqlExceptionNoSeExpone(): void
    {
        $e = new mysqli_sql_exception("Table 'jcenvios.rate_limit' doesn't exist", 1146);

        $this->assertFalse(ErrorPresenter::isUserFacing($e));
        $this->assertSame(ErrorPresenter::GENERIC_MESSAGE, ErrorPresenter::publicMessage($e));
    }

    public function testErrorDePhpNoSeExpone(): void
    {
        $e = new TypeError('Argument #1 ($x) must be of type int, string given', 400);

        $this->assertFalse(ErrorPresenter::isUserFacing($e));
        $this->assertSame(ErrorPresenter::GENERIC_MESSAGE, ErrorPresenter::publicMessage($e));
    }

    public function testExcepcionPropiaQueHeredaDeExceptionTampocoSeExpone(): void
    {
        $e = new class ('detalle interno', 400) extends Exception {
        };

        $this->assertFalse(ErrorPresenter::isUserFacing($e));
    }

    // ------------------------------------------------------------------
    // normalizeCode: getCode() puede devolver strings
    // ------------------------------------------------------------------

    public function testCodigoStringNumericoSeNormaliza(): void
    {
        $e = new Exception('msg');
        $ref = new ReflectionProperty(Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($e, '404');

        $this->assertSame(404, ErrorPresenter::normalizeCode($e));
        $this->assertTrue(ErrorPresenter::isUserFacing($e));
    }

    public function testCodigoStringNoNumericoSeTrataComoCero(): void
    {
        // PDOException usa SQLSTATE ("42S02") como codigo.
        $e = new Exception('msg');
        $ref = new ReflectionProperty(Exception::class, 'code');
        $ref->setAccessible(true);
        $ref->setValue($e, '42S02');

        $this->assertSame(0, ErrorPresenter::normalizeCode($e));
        $this->assertFalse(ErrorPresenter::isUserFacing($e));
    }

    // ------------------------------------------------------------------
    // httpStatus
    // ------------------------------------------------------------------

    public function testHttpStatusUsaElCodigoDeLaExcepcionSiEsHttpValido(): void
    {
        $this->assertSame(404, ErrorPresenter::httpStatus(new Exception('x', 404)));
        $this->assertSame(503, ErrorPresenter::httpStatus(new Exception('x', 503)));
    }

    public function testHttpStatusCaeAlFallbackSiElCodigoNoEsHttp(): void
    {
        $this->assertSame(500, ErrorPresenter::httpStatus(new Exception('x', 0)));
        $this->assertSame(500, ErrorPresenter::httpStatus(new Exception('x', 1146)));
        $this->assertSame(400, ErrorPresenter::httpStatus(new Exception('x', 0), 400));
        $this->assertSame(500, ErrorPresenter::httpStatus(new Exception('x', 600)));
    }

    // ------------------------------------------------------------------
    // Modo desarrollo
    // ------------------------------------------------------------------

    public function testEnDesarrolloElFlagEstaApagadoPorDefectoEnLosTests(): void
    {
        // Si esto falla es que config de test define IS_DEV_ENVIRONMENT y los
        // asserts de arriba estarian pasando por la puerta equivocada.
        $this->assertFalse(ErrorPresenter::isDevEnvironment());
    }

    // ------------------------------------------------------------------
    // El detalle nunca se pierde: siempre va al log
    // ------------------------------------------------------------------

    public function testDescribeIncluyeClaseCodigoMensajeArchivoYLinea(): void
    {
        $e = new mysqli_sql_exception("Table 'jcenvios.rate_limit' doesn't exist", 1146);
        $linea = ErrorPresenter::describe($e, 'ctx');

        $this->assertStringContainsString('[ctx]', $linea);
        $this->assertStringContainsString('mysqli_sql_exception', $linea);
        $this->assertStringContainsString('1146', $linea);
        $this->assertStringContainsString("Table 'jcenvios.rate_limit' doesn't exist", $linea);
        $this->assertStringContainsString(basename(__FILE__), $linea);
        $this->assertStringContainsString((string) $e->getLine(), $linea);
    }

    public function testDescribeSigueLaCadenaDePrevious(): void
    {
        $raiz = new mysqli_sql_exception('detalle del motor', 1146);
        $e = new Exception('envoltorio', 500, $raiz);

        $linea = ErrorPresenter::describe($e);

        $this->assertStringContainsString('envoltorio', $linea);
        $this->assertStringContainsString('previous', $linea);
        $this->assertStringContainsString('detalle del motor', $linea);
    }

    public function testPublicMessageEscribeElDetalleAlLogCuandoOculta(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'errlog');
        $anterior = ini_get('error_log');
        ini_set('error_log', $tmp);

        try {
            $e = new mysqli_sql_exception("Table 'jcenvios.rate_limit' doesn't exist", 1146);
            $devuelto = ErrorPresenter::publicMessage($e, 'prueba');

            $contenido = file_get_contents($tmp);

            $this->assertSame(ErrorPresenter::GENERIC_MESSAGE, $devuelto);
            $this->assertStringContainsString("Table 'jcenvios.rate_limit' doesn't exist", $contenido);
            $this->assertStringContainsString('[prueba]', $contenido);
        } finally {
            ini_set('error_log', $anterior === false ? '' : $anterior);
            @unlink($tmp);
        }
    }

    public function testPublicMessageNoLogueaCuandoElMensajeEsDeNegocio(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'errlog');
        $anterior = ini_get('error_log');
        ini_set('error_log', $tmp);

        try {
            $e = new Exception('El monto máximo permitido por orden es 50.000.', 400);
            $devuelto = ErrorPresenter::publicMessage($e, 'prueba');

            $this->assertSame('El monto máximo permitido por orden es 50.000.', $devuelto);
            $this->assertSame('', trim(file_get_contents($tmp)));
        } finally {
            ini_set('error_log', $anterior === false ? '' : $anterior);
            @unlink($tmp);
        }
    }

    public function testElMismoObjetoNoSeLogueaDosVecesEnElMismoContexto(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'errlog');
        $anterior = ini_get('error_log');
        ini_set('error_log', $tmp);

        try {
            $e = new mysqli_sql_exception('detalle', 1146);
            ErrorPresenter::logException($e, 'ctx');
            ErrorPresenter::logException($e, 'ctx');
            ErrorPresenter::publicMessage($e, 'ctx');

            $this->assertSame(1, substr_count(file_get_contents($tmp), 'detalle'));
        } finally {
            ini_set('error_log', $anterior === false ? '' : $anterior);
            @unlink($tmp);
        }
    }

    // ------------------------------------------------------------------
    // Regresion: los mensajes reales del proyecto que el frontend muestra
    // ------------------------------------------------------------------

    /**
     * @dataProvider proveedorMensajesDeUsuarioReales
     */
    public function testMensajesDeUsuarioRealesDelProyectoSiguenLlegandoAlCliente(
        string $mensaje,
        int $codigo
    ): void {
        $this->assertSame(
            $mensaje,
            ErrorPresenter::publicMessage(new Exception($mensaje, $codigo))
        );
    }

    public static function proveedorMensajesDeUsuarioReales(): array
    {
        return [
            ['Credenciales incorrectas.', 401],
            ['Demasiados intentos. Inténtalo nuevamente en 3 minuto(s).', 429],
            ['El monto máximo permitido por orden es 50.000.', 400],
            ['Transacción no encontrada.', 404],
            ['El código es obligatorio.', 400],
            ['Los ajustes automáticos de tasa están deshabilitados los domingos por política comercial.', 403],
            ['No se pudo pausar. Verifique que la orden esté \'En Proceso\' (ID 3).', 409],
        ];
    }

    /**
     * @dataProvider proveedorMensajesQueFiltraban
     */
    public function testMensajesQueAntesFiltrabanInternosYaNoSalen(string $mensaje, int $codigo): void
    {
        $this->assertSame(
            ErrorPresenter::GENERIC_MESSAGE,
            ErrorPresenter::publicMessage(new Exception($mensaje, $codigo))
        );
    }

    public static function proveedorMensajesQueFiltraban(): array
    {
        return [
            'tabla inexistente' => ["Table 'jcenvios.rate_limit' doesn't exist", 0],
            'prepare fallido' => ['Error al preparar la consulta: Unknown column \'Foo\' in \'field list\'', 500],
            'conexion' => ['Error de conexión: Access denied for user \'root\'@\'localhost\'', 500],
            'sql en controller' => ['Error en preparación SQL: You have an error in your SQL syntax', 500],
            'ruta de archivo' => ['failed to open stream: C:\\xampp\\htdocs\\Jcenvios\\remesas_private\\uploads\\x.pdf', 500],
        ];
    }
}
