<?php

require_once __DIR__ . '/../config.php';

use PHPUnit\Framework\TestCase;
use App\Database\Database;
use App\Services\LogService;

/**
 * Test de INTEGRACIÓN contra la BD real (mismo criterio que RateLimiterServiceTest).
 *
 * Lo que se prueba acá vive en el motor de MySQL, no en PHP: la FK
 * `logs_ibfk_1` (logs.UserID -> usuarios.UserID) y el hecho de que un error de
 * FK revierte SOLO la sentencia que falló, no la transacción en curso. Un mock
 * de Database no puede reproducir ninguna de las dos cosas — probaría que se
 * llamó a prepare() con un string, que es exactamente el tipo de test que dejó
 * pasar este bug.
 *
 * Todo lo que crea (usuario de prueba + filas de bitácora) se borra en tearDown.
 */
class LogServiceTest extends TestCase
{
    /** Marca única para poder borrar SOLO lo que crea este test. */
    private const ACCION = '__TEST_LogServiceTest__';

    private ?Database $db = null;
    private \mysqli $conn;
    private LogService $service;
    private int $testUserId;
    private string $testEmail;

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin BD disponible: ' . $e->getMessage());
        }

        $this->conn = $this->db->getConnection();
        $this->service = new LogService($this->db);

        $this->limpiarLogs();
        $this->testEmail = '__logservicetest__' . uniqid() . '@test.invalid';
        $this->testUserId = $this->crearUsuarioDePrueba($this->testEmail);
    }

    protected function tearDown(): void
    {
        if ($this->db === null) {
            return;
        }

        // Si un test dejó una transacción abierta, cerrarla antes de limpiar:
        // la conexión es un singleton y se reusa entre tests.
        @$this->conn->rollback();
        @$this->conn->autocommit(true);

        $this->limpiarLogs();
        $this->borrarUsuarioDePrueba();
    }

    // ---------------------------------------------------------------- helpers

    private function limpiarLogs(): void
    {
        $stmt = $this->conn->prepare("DELETE FROM logs WHERE Accion = ?");
        $accion = self::ACCION;
        $stmt->bind_param("s", $accion);
        $stmt->execute();
        $stmt->close();
    }

    private function crearUsuarioDePrueba(string $email): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios (PrimerNombre, PrimerApellido, Email, PasswordHash, NumeroDocumento)
             VALUES ('LogServiceTest', 'Borrable', ?, 'x', ?)"
        );
        $doc = 'LST-' . substr($email, 18, 13);
        $stmt->bind_param("ss", $email, $doc);
        $stmt->execute();
        $id = (int) $this->conn->insert_id;
        $stmt->close();

        return $id;
    }

    private function borrarUsuarioDePrueba(): void
    {
        $stmt = $this->conn->prepare("DELETE FROM usuarios WHERE Email = ?");
        $stmt->bind_param("s", $this->testEmail);
        $stmt->execute();
        $stmt->close();
    }

    private function eliminarUsuarioDePruebaDeLaTabla(): void
    {
        $stmt = $this->conn->prepare("DELETE FROM usuarios WHERE UserID = ?");
        $stmt->bind_param("i", $this->testUserId);
        $stmt->execute();
        $stmt->close();
    }

    /** @return array<int, array<string, mixed>> */
    private function filasDeBitacora(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT UserID, Detalles FROM logs WHERE Accion = ? ORDER BY LogID ASC"
        );
        $accion = self::ACCION;
        $stmt->bind_param("s", $accion);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $filas;
    }

    // ------------------------------------------------------------------ tests

    public function testEscribeLaBitacoraConElUserIdCuandoElUsuarioExiste(): void
    {
        $this->service->logAction($this->testUserId, self::ACCION, 'usuario vivo');

        $filas = $this->filasDeBitacora();
        $this->assertCount(1, $filas);
        $this->assertSame($this->testUserId, (int) $filas[0]['UserID']);
        $this->assertSame('usuario vivo', $filas[0]['Detalles']);
    }

    public function testAceptaUserIdNulo(): void
    {
        $this->service->logAction(null, self::ACCION, 'accion del sistema');

        $filas = $this->filasDeBitacora();
        $this->assertCount(1, $filas);
        $this->assertNull($filas[0]['UserID']);
    }

    /**
     * El bug: un admin eliminado con la sesión todavía abierta manda su UserID,
     * el INSERT viola `logs_ibfk_1` (errno 1452) y — desde PHP 8.1, con mysqli
     * lanzando excepciones — eso reventaba la operación de negocio entera.
     */
    public function testNoLanzaCuandoElUsuarioDeLaSesionYaNoExiste(): void
    {
        $idFantasma = $this->testUserId;
        $this->eliminarUsuarioDePruebaDeLaTabla();

        $this->service->logAction($idFantasma, self::ACCION, 'liquidación ajustada');

        $this->addToAssertionCount(1); // no lanzó: eso es lo que se prueba
    }

    public function testGuardaElRegistroConUserIdNullYConservaElUserIdEnElDetalle(): void
    {
        $idFantasma = $this->testUserId;
        $this->eliminarUsuarioDePruebaDeLaTabla();

        $this->service->logAction($idFantasma, self::ACCION, 'liquidación ajustada');

        $filas = $this->filasDeBitacora();
        $this->assertCount(1, $filas, 'La auditoría no se debe perder, solo degradar a UserID NULL.');
        $this->assertNull($filas[0]['UserID']);
        $this->assertStringContainsString((string) $idFantasma, $filas[0]['Detalles']);
        $this->assertStringContainsString('liquidación ajustada', $filas[0]['Detalles']);
    }

    /**
     * Hay operaciones que loguean DENTRO de una transacción a propósito. Un log
     * que falla no puede dejar esa transacción en un estado inservible.
     */
    public function testUnLogFallidoNoRompeLaTransaccionEnCurso(): void
    {
        $idFantasma = $this->testUserId;
        $this->eliminarUsuarioDePruebaDeLaTabla();

        $this->conn->begin_transaction();
        $this->service->logAction($idFantasma, self::ACCION, 'dentro de transacción');

        // La transacción tiene que seguir usable después del fallo de FK.
        $stmt = $this->conn->prepare("INSERT INTO logs (UserID, Accion, Detalles) VALUES (NULL, ?, 'posterior')");
        $accion = self::ACCION;
        $stmt->bind_param("s", $accion);
        $stmt->execute();
        $stmt->close();

        $this->conn->commit();

        $detalles = array_column($this->filasDeBitacora(), 'Detalles');
        $this->assertContains('posterior', $detalles);
    }

    /**
     * La otra mitad de la propiedad: si la operación de negocio hace rollback,
     * el log escrito dentro de esa transacción se revierte con ella.
     */
    public function testElLogEscritoDentroDeUnaTransaccionSeRevierteConElRollback(): void
    {
        $this->conn->begin_transaction();
        $this->service->logAction($this->testUserId, self::ACCION, 'esto se revierte');
        $this->conn->rollback();

        $this->assertCount(0, $this->filasDeBitacora());
    }
}
