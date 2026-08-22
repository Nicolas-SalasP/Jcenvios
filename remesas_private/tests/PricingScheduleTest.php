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
 * Cobertura del ajuste global automático:
 *  - ventana horaria (antes se exigía el minuto exacto)
 *  - hora leída desde 'global_adjustment_time' (antes hardcodeada)
 *  - guard de last_run vía claim atómico
 *  - validación de rango del porcentaje
 */
class PricingScheduleTest extends TestCase
{
    private $rateRepo;
    private $countryRepo;
    private $settingsRepo;
    private $notifService;
    private $systemService;
    private $tasasImagenRepo;
    private $fileHandler;

    protected function setUp(): void
    {
        $this->rateRepo        = $this->createMock(RateRepository::class);
        $this->countryRepo     = $this->createMock(CountryRepository::class);
        $this->settingsRepo    = $this->createMock(SystemSettingsRepository::class);
        $this->notifService    = $this->createMock(NotificationService::class);
        $this->systemService   = $this->createMock(SystemSettingsService::class);
        $this->tasasImagenRepo = $this->createMock(TasasImagenRepository::class);
        $this->fileHandler     = $this->createMock(FileHandlerService::class);
    }

    private function service(): PricingService
    {
        return new PricingService(
            $this->rateRepo,
            $this->countryRepo,
            $this->settingsRepo,
            $this->notifService,
            $this->systemService,
            $this->tasasImagenRepo,
            $this->fileHandler
        );
    }

    /**
     * Configura el mock de settings devolviendo valores por clave.
     */
    private function settings(string $percent, string $time, ?string $lastRun): void
    {
        $this->settingsRepo->method('getValue')->willReturnCallback(
            function (string $key) use ($percent, $time, $lastRun) {
                switch ($key) {
                    case 'global_adjustment_percent': return $percent;
                    case 'global_adjustment_time':    return $time;
                    case 'global_adjustment_last_run': return $lastRun;
                }
                return null;
            }
        );
    }

    // ---------------------------------------------------------------
    // Decisión de horario (determinista: fechas fijas, no depende del reloj)
    // 2026-08-17 = lunes ... 2026-08-22 = sábado, 2026-08-23 = domingo.
    // ---------------------------------------------------------------

    public function testNoCorreSiTodaviaNoLlegoLaHoraObjetivo()
    {
        $s = $this->service();
        $this->assertFalse($s->shouldRunScheduledAdjustment('2026-08-17 19:29:59', '19:30', '2026-08-16 19:30:00'));
    }

    public function testCorreEnLaHoraExacta()
    {
        $s = $this->service();
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 19:30:00', '19:30', '2026-08-16 19:30:00'));
    }

    /** El bug original: el cron encolado un minuto tarde se comía el ajuste del día. */
    public function testCorreAunqueElCronLlegueTardeUnMinuto()
    {
        $s = $this->service();
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 19:31:00', '19:30', '2026-08-16 19:30:00'));
    }

    public function testCorreAunqueElCronLlegueHorasTarde()
    {
        $s = $this->service();
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 23:45:00', '19:30', '2026-08-16 19:30:00'));
    }

    public function testUsaLaHoraConfiguradaEnElPanelYNoLaHardcodeada()
    {
        $s = $this->service();
        // Configurado a las 21:00: a las 19:35 (la vieja hardcodeada) NO debe correr.
        $this->assertFalse($s->shouldRunScheduledAdjustment('2026-08-17 19:35:00', '21:00', '2026-08-16 19:30:00'));
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 21:05:00', '21:00', '2026-08-16 19:30:00'));
    }

    public function testHoraInvalidaCaeAlFallback1930()
    {
        $s = $this->service();
        foreach (['', 'basura', '25:99', '7:5', null] as $mala) {
            $this->assertFalse(
                $s->shouldRunScheduledAdjustment('2026-08-17 19:29:00', (string) $mala, '2026-08-16 00:00:00'),
                "Hora inválida '{$mala}' debería caer al fallback 19:30"
            );
            $this->assertTrue(
                $s->shouldRunScheduledAdjustment('2026-08-17 19:31:00', (string) $mala, '2026-08-16 00:00:00'),
                "Hora inválida '{$mala}' debería caer al fallback 19:30"
            );
        }
    }

    public function testSabadoUsaLas1600YIgnoraLaHoraConfigurada()
    {
        $s = $this->service();
        // 2026-08-22 es sábado. La hora configurada (21:00) no aplica el sábado.
        $this->assertFalse($s->shouldRunScheduledAdjustment('2026-08-22 15:59:00', '21:00', '2026-08-21 19:30:00'));
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-22 16:01:00', '21:00', '2026-08-21 19:30:00'));
    }

    public function testDomingoNuncaAjusta()
    {
        $s = $this->service();
        $this->assertFalse($s->shouldRunScheduledAdjustment('2026-08-23 23:59:00', '00:00', null));
    }

    // ---------------------------------------------------------------
    // Guard de last_run
    // ---------------------------------------------------------------

    public function testNoCorreSiYaCorrioHoy()
    {
        $s = $this->service();
        $this->assertFalse($s->shouldRunScheduledAdjustment('2026-08-17 23:00:00', '19:30', '2026-08-17 19:30:00'));
    }

    public function testCorreSiLastRunEsDeAyer()
    {
        $s = $this->service();
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 19:30:00', '19:30', '2026-08-16 19:30:00'));
    }

    public function testCorreSiNuncaCorrio()
    {
        $s = $this->service();
        $this->assertTrue($s->shouldRunScheduledAdjustment('2026-08-17 19:30:00', '19:30', null));
    }

    public function testRunScheduledNoTocaTasasSiOtraInstanciaGanoElClaimAtomico()
    {
        // last_run de ayer + hora pasada: pasa el guard de lectura, pero el claim
        // atómico lo pierde contra la otra instancia -> no debe tocar ninguna tasa.
        $this->settings('2', '00:00', date('Y-m-d', strtotime('-1 day')) . ' 19:30:00');
        $this->settingsRepo->method('claimDailyRun')->willReturn(false);
        $this->rateRepo->expects($this->never())->method('findAllReferentialRates');

        // Se fuerza la ventana abierta para que el test no dependa del día/hora
        // real en que corre la suite.
        $service = $this->getMockBuilder(PricingService::class)
            ->setConstructorArgs([
                $this->rateRepo, $this->countryRepo, $this->settingsRepo,
                $this->notifService, $this->systemService,
                $this->tasasImagenRepo, $this->fileHandler,
            ])
            ->onlyMethods(['shouldRunScheduledAdjustment'])
            ->getMock();
        $service->method('shouldRunScheduledAdjustment')->willReturn(true);

        $this->assertFalse($service->runScheduledAdjustment());
    }

    // ---------------------------------------------------------------
    // Validación de rango
    // ---------------------------------------------------------------

    public function testRechazaPorcentajeMenosCien()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe estar entre');
        $this->service()->applyGlobalAdjustment(1, -100);
    }

    public function testRechazaPorcentajeFueraDeRangoPositivo()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe estar entre');
        $this->service()->applyGlobalAdjustment(1, PricingService::MAX_AJUSTE_PORCENTUAL + 0.01);
    }

    public function testRechazaPorcentajeCero()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no puede ser 0');
        $this->service()->applyGlobalAdjustment(1, 0);
    }

    public function testNoEscribeTasaCeroNiNegativa()
    {
        if ((int) date('N') === 7) {
            $this->markTestSkipped('Domingo: applyGlobalAdjustment está bloqueado por política comercial.');
        }
        $this->systemService->method('checkSystemAvailability')->willReturn(['available' => true]);
        // Valor original 0 en la BD: cualquier porcentaje lo deja en 0.
        $this->rateRepo->method('findAllReferentialRates')->willReturn([
            ['TasaID' => 7, 'ValorTasa' => 0.0, 'PaisOrigenID' => 1, 'PaisDestinoID' => 2, 'MontoMinimo' => 0, 'MontoMaximo' => 0, 'EsRiesgoso' => 0],
        ]);
        $this->rateRepo->expects($this->never())->method('updateRateValue');

        $this->assertEquals(0, $this->service()->applyGlobalAdjustment(1, 2));
    }

    // ---------------------------------------------------------------
    // Tasa BCV
    // ---------------------------------------------------------------

    public function testBcvRechazaCero()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mayor que 0');
        $this->service()->updateBcvRate(1, 0);
    }

    public function testBcvRechazaNegativo()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mayor que 0');
        $this->service()->updateBcvRate(1, -35.5);
    }

    public function testBcvRechazaValorAbsurdo()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('supera el máximo');
        $this->service()->updateBcvRate(1, PricingService::MAX_TASA_BCV + 1);
    }

    public function testBcvAceptaValorValido()
    {
        $this->systemService->method('checkSystemAvailability')->willReturn(['available' => true]);
        $this->settingsRepo->method('updateValue')->willReturn(true);
        $this->assertTrue($this->service()->updateBcvRate(1, 36.75));
    }

    // ---------------------------------------------------------------
    // Guardado de configuración
    // ---------------------------------------------------------------

    public function testGuardarConfigRechazaHoraInvalida()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('formato HH:MM');
        $this->service()->saveGlobalAdjustmentSettings(1, 2, '25:99');
    }

    public function testGuardarConfigRechazaPorcentajeFueraDeRango()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe estar entre');
        $this->service()->saveGlobalAdjustmentSettings(1, -100, '19:30');
    }

    public function testGuardarConfigAceptaValoresValidos()
    {
        $this->settingsRepo->method('updateValue')->willReturn(true);
        $this->assertTrue($this->service()->saveGlobalAdjustmentSettings(1, 2.5, '19:30'));
    }
}
