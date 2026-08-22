<?php

use PHPUnit\Framework\TestCase;
use App\Services\FileHandlerService;

/**
 * deleteOrderPdf() / purgeOldOrderPdfs().
 *
 * El constructor de FileHandlerService necesita la constante BASE_URL y varios
 * directorios reales del proyecto, así que se instancia sin constructor
 * (newInstanceWithoutConstructor) y se inyecta un directorio temporal propio.
 * Mismo criterio que PDFServiceTest, que también usa Reflection.
 */
class FileHandlerOrderPdfTest extends TestCase
{
    private string $tmpDir;
    private FileHandlerService $fh;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jc_temp_orders_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);

        $ref = new ReflectionClass(FileHandlerService::class);
        $this->fh = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('publicTempDir');
        $prop->setAccessible(true);
        $prop->setValue($this->fh, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    private function crear(string $nombre, int $edadDias = 0): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $nombre;
        file_put_contents($path, 'pdf');
        if ($edadDias > 0) {
            touch($path, time() - ($edadDias * 86400));
        }
        return $path;
    }

    public function testBorraElPdfConTokenAleatorio()
    {
        // Nombre real que genera savePdfTemporarily: orden_{id}_{32 hex}.pdf
        $f = $this->crear('orden_99999_deadbeefdeadbeefdeadbeefdeadbeef.pdf');
        $this->assertFileExists($f);

        $this->assertSame(1, $this->fh->deleteOrderPdf(99999));
        $this->assertFileDoesNotExist($f);
    }

    public function testBorraTodosLosPdfDeLaMismaOrden()
    {
        $a = $this->crear('orden_2037_6d90a4206d90a4206d90a4206d90a420.pdf');
        $b = $this->crear('orden_2037_9d0fcf4a9d0fcf4a9d0fcf4a9d0fcf4a.pdf');

        $this->assertSame(2, $this->fh->deleteOrderPdf(2037));
        $this->assertFileDoesNotExist($a);
        $this->assertFileDoesNotExist($b);
    }

    public function testNoTocaPdfDeOtrasOrdenes()
    {
        $mio  = $this->crear('orden_20_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf');
        $otro = $this->crear('orden_203_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.pdf');

        $this->assertSame(1, $this->fh->deleteOrderPdf(20));
        $this->assertFileDoesNotExist($mio);
        $this->assertFileExists($otro);
    }

    public function testDevuelveCeroSiNoHayNadaQueBorrar()
    {
        $this->assertSame(0, $this->fh->deleteOrderPdf(123456));
    }

    public function testIdInvalidoNoBorraNada()
    {
        $f = $this->crear('orden_5_cccccccccccccccccccccccccccccccc.pdf');
        $this->assertSame(0, $this->fh->deleteOrderPdf(0));
        $this->assertSame(0, $this->fh->deleteOrderPdf(-1));
        $this->assertFileExists($f);
    }

    public function testPurgaSoloLosViejos()
    {
        $viejo   = $this->crear('orden_1_11111111111111111111111111111111.pdf', 10);
        $reciente = $this->crear('orden_2_22222222222222222222222222222222.pdf', 2);
        $otro    = $this->crear('comprobante_3.pdf', 30); // no matchea el patrón

        $r = $this->fh->purgeOldOrderPdfs(7);

        $this->assertSame(1, $r['borrados']);
        $this->assertSame(0, $r['fallidos']);
        $this->assertSame(2, $r['revisados']);
        $this->assertFileDoesNotExist($viejo);
        $this->assertFileExists($reciente);
        $this->assertFileExists($otro);
    }

    public function testPurgaEsIdempotente()
    {
        $this->crear('orden_1_11111111111111111111111111111111.pdf', 30);

        $primera = $this->fh->purgeOldOrderPdfs(7);
        $segunda = $this->fh->purgeOldOrderPdfs(7);

        $this->assertSame(1, $primera['borrados']);
        $this->assertSame(0, $segunda['borrados']);
        $this->assertSame(0, $segunda['revisados']);
    }
}
