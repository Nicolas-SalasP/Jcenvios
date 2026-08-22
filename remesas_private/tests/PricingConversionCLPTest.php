<?php

use PHPUnit\Framework\TestCase;
use App\Services\PricingService;
use App\Services\SystemSettingsService;
use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Repositories\TasasImagenRepository;
use App\Services\NotificationService;
use App\Services\FileHandlerService;

/**
 * PricingService::getFactorConversionACLP — de dónde sale la tasa que se le
 * propone al admin para consolidar comisiones de revendedor en CLP.
 *
 * El factor devuelto es SIEMPRE "CLP por 1 unidad de la moneda":
 *     montoCLP = montoMoneda * factor
 */
class PricingConversionCLPTest extends TestCase
{
    private $rateRepo;
    private $countryRepo;

    private function service(): PricingService
    {
        $this->rateRepo    = $this->rateRepo    ?? $this->createMock(RateRepository::class);
        $this->countryRepo = $this->countryRepo ?? $this->createMock(CountryRepository::class);
        return new PricingService(
            $this->rateRepo,
            $this->countryRepo,
            $this->createMock(SystemSettingsRepository::class),
            $this->createMock(NotificationService::class),
            $this->createMock(SystemSettingsService::class),
            $this->createMock(TasasImagenRepository::class),
            $this->createMock(FileHandlerService::class)
        );
    }

    protected function setUp(): void
    {
        $this->rateRepo    = $this->createMock(RateRepository::class);
        $this->countryRepo = $this->createMock(CountryRepository::class);
    }

    public function testCLPEsSiempreUnoYNoConsultaTasas()
    {
        $this->rateRepo->expects($this->never())->method('findReferentialRate');
        $r = $this->service()->getFactorConversionACLP('CLP');
        $this->assertSame(1.0, $r['factor']);
    }

    public function testMonedaDesconocidaDevuelveNullYNoAsumeUnoAUno()
    {
        $this->countryRepo->method('findIdByMoneda')->willReturn(null);
        $this->assertNull($this->service()->getFactorConversionACLP('XYZ'));
    }

    public function testUsaLaRutaDirectaHaciaChileCuandoExiste()
    {
        // Ruta ficticia PEN→CLP activa con valor 300 y modo 'multiply'
        // (Perú→Chile es 4-1, que getCalculationMode marca como 'divide',
        // así que usamos Ecuador (10) que no está en la lista de inversas).
        $this->countryRepo->method('findIdByMoneda')->willReturn(10);
        $this->rateRepo->method('findReferentialRate')->willReturnCallback(
            fn($o, $d) => ($o === 10 && $d === 1)
                ? ['TasaID' => 99, 'ValorTasa' => 300.0, 'RutaActiva' => 1]
                : null
        );

        $r = $this->service()->getFactorConversionACLP('ECU');
        $this->assertSame('directa', $r['sentido']);
        $this->assertSame(300.0, $r['factor']);
        $this->assertSame(99, $r['tasaId']);
    }

    public function testRutaDirectaConModoDivideInvierteElValor()
    {
        // 4-1 (Perú→Chile) está en la lista de rutas 'divide' de
        // getCalculationMode: CLP = PEN / ValorTasa.
        $this->countryRepo->method('findIdByMoneda')->willReturn(4);
        $this->rateRepo->method('findReferentialRate')->willReturnCallback(
            fn($o, $d) => ($o === 4 && $d === 1)
                ? ['TasaID' => 39, 'ValorTasa' => 0.004, 'RutaActiva' => 1]
                : null
        );

        $r = $this->service()->getFactorConversionACLP('PEN');
        $this->assertSame('directa', $r['sentido']);
        $this->assertEqualsWithDelta(250.0, $r['factor'], 0.0000001);
    }

    public function testSinRutaDirectaInvierteLaRutaCLPHaciaLaMoneda()
    {
        // Es el caso real de COP hoy: no hay ninguna ruta activa COP→CLP,
        // sí existe CLP→COP referencial = 3.42 (multiply).
        // Por lo tanto CLP = COP / 3.42.
        $this->countryRepo->method('findIdByMoneda')->willReturn(2);
        $this->rateRepo->method('findReferentialRate')->willReturnCallback(
            fn($o, $d) => ($o === 1 && $d === 2)
                ? ['TasaID' => 22, 'ValorTasa' => 3.42, 'RutaActiva' => 1]
                : null
        );

        $r = $this->service()->getFactorConversionACLP('COP');
        $this->assertSame('inversa', $r['sentido']);
        $this->assertSame(22, $r['tasaId']);
        $this->assertEqualsWithDelta(1 / 3.42, $r['factor'], 0.0000001);
        // 20.000 COP ≈ 5.848 CLP, no 20.000.
        $this->assertEqualsWithDelta(5847.95, round(20000 * $r['factor'], 2), 0.01);
    }

    public function testIgnoraLaRutaSiEstaDesactivada()
    {
        $this->countryRepo->method('findIdByMoneda')->willReturn(2);
        $this->rateRepo->method('findReferentialRate')->willReturn(
            ['TasaID' => 22, 'ValorTasa' => 3.42, 'RutaActiva' => 0]
        );
        $this->assertNull($this->service()->getFactorConversionACLP('COP'));
    }

    public function testIgnoraValorTasaCeroONegativo()
    {
        $this->countryRepo->method('findIdByMoneda')->willReturn(2);
        $this->rateRepo->method('findReferentialRate')->willReturn(
            ['TasaID' => 22, 'ValorTasa' => 0, 'RutaActiva' => 1]
        );
        // Sin esto se haría una división por cero y saldría INF.
        $this->assertNull($this->service()->getFactorConversionACLP('COP'));
    }

    public function testSinNingunaRutaDevuelveNull()
    {
        // Caso USD hoy: todas las rutas hacia y desde Chile están con Activa = 0,
        // así que findReferentialRate no devuelve nada.
        $this->countryRepo->method('findIdByMoneda')->willReturn(5);
        $this->rateRepo->method('findReferentialRate')->willReturn(null);
        $this->assertNull($this->service()->getFactorConversionACLP('USD'));
    }
}
