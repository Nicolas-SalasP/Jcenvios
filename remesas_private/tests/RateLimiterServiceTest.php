<?php

require_once __DIR__ . '/../config.php';

use PHPUnit\Framework\TestCase;
use App\Database\Database;
use App\Services\RateLimiterService;

/**
 * Test de INTEGRACIÓN contra la BD real.
 *
 * RateLimiterService no es mockeable de forma útil: toda su lógica vive en un
 * INSERT ... ON DUPLICATE KEY UPDATE, o sea en el motor de MySQL, no en PHP.
 * Mockear Database::getConnection() probaría que se llamó a prepare() con un
 * string — exactamente el tipo de test que dejó pasar el bug original (la
 * UNIQUE KEY incluía ventana_fin, así que el upsert nunca incrementaba y los
 * 6 límites estaban inertes). Por eso se ejecuta contra MySQL de verdad.
 *
 * Usa una IP de documentación (RFC 5737, bloque TEST-NET-3) que jamás va a
 * pertenecer a un cliente real, y borra sus filas antes y después de cada test.
 */
class RateLimiterServiceTest extends TestCase
{
    private const IP = '203.0.113.99';

    private ?Database $db = null;
    private \mysqli $conn;

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin BD disponible: ' . $e->getMessage());
        }
        $this->conn = $this->db->getConnection();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->limpiar();
        }
    }

    private function limpiar(): void
    {
        $stmt = $this->conn->prepare("DELETE FROM rate_limit WHERE ip = ?");
        $ip = self::IP;
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    }

    private function hitsActuales(string $accion): ?int
    {
        $stmt = $this->conn->prepare("SELECT hits FROM rate_limit WHERE ip = ? AND accion = ?");
        $ip = self::IP;
        $stmt->bind_param("ss", $ip, $accion);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['hits'] : null;
    }

    private function filasParaIp(): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM rate_limit WHERE ip = ?");
        $ip = self::IP;
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['c'];
    }

    public function testAccionSinLimiteDefinidoNoHaceNada()
    {
        $service = new RateLimiterService($this->db);

        for ($i = 0; $i < 50; $i++) {
            $service->check('accionQueNoExisteEnLIMITS', self::IP);
        }

        $this->assertSame(0, $this->filasParaIp(), 'No debería escribir filas para acciones no limitadas.');
    }

    public function testHitsIncrementaEnLugarDeCrearFilasNuevas()
    {
        // Este es el test que reproduce el bug original: con la UNIQUE vieja
        // (ip, accion, ventana_fin) quedaban 5 filas con hits = 1 cada una.
        $service = new RateLimiterService($this->db);

        for ($i = 0; $i < 5; $i++) {
            $service->check('loginUser', self::IP);
        }

        $this->assertSame(1, $this->filasParaIp(), 'Debe haber una sola fila por (ip, accion).');
        $this->assertSame(5, $this->hitsActuales('loginUser'));
    }

    public function testPermiteExactamenteMaxHitsIntentos()
    {
        // loginUser => [10, 60]. Los intentos 1..10 pasan.
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('loginUser', self::IP);
        }

        $this->assertSame(10, $this->hitsActuales('loginUser'));
    }

    public function testIntentoNumeroMaxHitsMasUnoLanza429()
    {
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('loginUser', self::IP);
        }

        try {
            $service->check('loginUser', self::IP);
            $this->fail('El intento 11 debía ser bloqueado.');
        } catch (\Exception $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertStringContainsString('Demasiados intentos', $e->getMessage());
            $this->assertStringContainsString('minuto(s)', $e->getMessage());
        }
    }

    public function testSigueBloqueandoEnIntentosPosteriores()
    {
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('loginUser', self::IP);
        }

        $bloqueados = 0;
        for ($i = 11; $i <= 15; $i++) {
            try {
                $service->check('loginUser', self::IP);
            } catch (\Exception $e) {
                $bloqueados++;
            }
        }

        $this->assertSame(5, $bloqueados);
    }

    public function testVencerLaVentanaReiniciaElContador()
    {
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('loginUser', self::IP);
        }

        // Forzar el vencimiento de la ventana sin esperar 60s reales.
        $stmt = $this->conn->prepare(
            "UPDATE rate_limit SET ventana_fin = DATE_SUB(NOW(), INTERVAL 5 SECOND) WHERE ip = ?"
        );
        $ip = self::IP;
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();

        $service->check('loginUser', self::IP); // no debe lanzar

        $this->assertSame(1, $this->hitsActuales('loginUser'), 'La ventana vencida debe reiniciar hits a 1.');
        $this->assertSame(1, $this->filasParaIp(), 'El reinicio reusa la fila, no crea una nueva.');
    }

    public function testCadaAccionLlevaSuPropioContador()
    {
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('loginUser', self::IP);
        }

        // registerUser => [5, 300]; su contador arranca de cero.
        $service->check('registerUser', self::IP);

        $this->assertSame(10, $this->hitsActuales('loginUser'));
        $this->assertSame(1, $this->hitsActuales('registerUser'));
        $this->assertSame(2, $this->filasParaIp());
    }

    public function testLimiteDistintoPorAccion()
    {
        // registerUser => 5 intentos / 5 min. El 6º corta.
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 5; $i++) {
            $service->check('registerUser', self::IP);
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(429);
        $service->check('registerUser', self::IP);
    }

    public function testMensajeIndicaMinutosRestantesDeLaVentanaLarga()
    {
        // submitContactForm => [10, 300] = 5 minutos.
        $service = new RateLimiterService($this->db);

        for ($i = 1; $i <= 10; $i++) {
            $service->check('submitContactForm', self::IP);
        }

        try {
            $service->check('submitContactForm', self::IP);
            $this->fail('Debía bloquear.');
        } catch (\Exception $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertSame(
                'Demasiados intentos. Inténtalo nuevamente en 5 minuto(s).',
                $e->getMessage()
            );
        }
    }
}
