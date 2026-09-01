<?php

use PHPUnit\Framework\TestCase;
use App\Services\SystemSettingsService;
use App\Repositories\SystemSettingsRepository;
use App\Repositories\HolidayRepository;
use App\Repositories\HorarioOverrideRepository;
use App\Services\LogService;

class SystemSettingsServiceTest extends TestCase
{
    private function buildService(array $overrides = []): SystemSettingsService
    {
        $defaults = [
            'settingsRepo' => $this->createMock(SystemSettingsRepository::class),
            'holidayRepo' => $this->createMock(HolidayRepository::class),
            'horarioOverrideRepo' => $this->createMock(HorarioOverrideRepository::class),
            'logService' => $this->createMock(LogService::class),
        ];
        $deps = array_merge($defaults, $overrides);

        return new SystemSettingsService(
            $deps['settingsRepo'],
            $deps['holidayRepo'],
            $deps['horarioOverrideRepo'],
            $deps['logService']
        );
    }

    // --- addHoliday ---

    public function testAddHolidayFallaSiFaltanCampos()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("obligatorios");

        $service->addHoliday(1, '', '2026-12-25', 'Navidad');
    }

    public function testAddHolidayFallaSiFechaInvalida()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Formato de fecha inválido");

        $service->addHoliday(1, 'fecha-mala', '2026-12-25', 'Navidad');
    }

    public function testAddHolidayFallaSiInicioEsDespuesDeFin()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("anterior a la fecha de fin");

        $service->addHoliday(1, '2026-12-26', '2026-12-25', 'Navidad');
    }

    public function testAddHolidayFallaSiYaTermino()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("ya terminó");

        $service->addHoliday(1, '2020-01-01', '2020-01-02', 'Feriado viejo');
    }

    public function testAddHolidayFallaSiRepoNoGuarda()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('create')->willReturn(false);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al guardar el feriado");

        $mananaMasUnDia = date('Y-m-d', strtotime('+2 days'));
        $mananaMasDosDias = date('Y-m-d', strtotime('+3 days'));
        $service->addHoliday(1, $mananaMasUnDia, $mananaMasDosDias, 'Feriado test');
    }

    public function testAddHolidayExitoso()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->expects($this->once())->method('create')->willReturn(true);

        $logService = $this->createMock(LogService::class);
        $logService->expects($this->once())->method('logAction');

        $service = $this->buildService(['holidayRepo' => $holidayRepo, 'logService' => $logService]);

        $inicio = date('Y-m-d', strtotime('+2 days'));
        $fin = date('Y-m-d', strtotime('+3 days'));
        $service->addHoliday(1, $inicio, $fin, 'Feriado test', 1);
        $this->assertTrue(true); // si no lanzó excepción, es éxito
    }

    // --- deleteHoliday ---

    public function testDeleteHolidayFallaSiRepoNoElimina()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('delete')->willReturn(false);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al eliminar");

        $service->deleteHoliday(5, 1);
    }

    // --- checkSystemAvailability ---

    public function testCheckSystemAvailabilitySinFeriadoActivo()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getActiveHoliday')->willReturn(null);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $result = $service->checkSystemAvailability();

        $this->assertTrue($result['available']);
    }

    public function testCheckSystemAvailabilityConFeriadoBloqueante()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getActiveHoliday')->willReturn([
            'BloqueoSistema' => 1,
            'Motivo' => 'Mantenimiento programado',
            'FechaFin' => '2026-12-31 23:59:59',
        ]);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $result = $service->checkSystemAvailability();

        $this->assertFalse($result['available']);
        $this->assertEquals('holiday', $result['reason']);
        $this->assertEquals('Mantenimiento programado', $result['message']);
    }

    public function testCheckSystemAvailabilityConFeriadoNoBloqueante()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getActiveHoliday')->willReturn([
            'BloqueoSistema' => 0,
            'Motivo' => 'Aviso informativo',
            'FechaFin' => '2026-12-31 23:59:59',
        ]);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $result = $service->checkSystemAvailability();

        $this->assertTrue($result['available']);
    }

    // --- toggleHorarioOverride / clearHorarioOverride ---

    public function testToggleHorarioOverrideFallaSiRepoNoGuarda()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('setOverride')->willReturn(false);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al guardar el override");

        $service->toggleHorarioOverride(1, true);
    }

    public function testToggleHorarioOverrideExitoso()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('setOverride')->willReturn(true);
        $horarioRepo->method('getStatus')->willReturn([
            'Activo' => 1,
            'ForzadoPor' => 1,
            'FechaActivacion' => date('Y-m-d H:i:s'),
            'ExpiraEn' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'Mensaje' => null,
        ]);

        $logService = $this->createMock(LogService::class);
        $logService->expects($this->once())->method('logAction');

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo, 'logService' => $logService]);

        $result = $service->toggleHorarioOverride(1, true);

        $this->assertTrue($result['active']);
    }

    /**
     * Regresión del bug reportado el 2026-08-29: fuera del horario laboral (y
     * los domingos) el override nacía con ExpiraEn = "ahora", así que el
     * getHorarioOverrideStatus() inmediatamente posterior ya lo daba por
     * vencido y el switch del panel "se destildaba solo" al hacerle click.
     *
     * La invariante que se prueba no depende de la hora en que corra la suite:
     * la expiración SIEMPRE tiene que quedar por delante del instante actual.
     */
    public function testToggleHorarioOverrideGuardaUnaExpiracionFutura()
    {
        $capturado = null;

        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('setOverride')->willReturnCallback(
            function ($activo, $adminId, $expiraEn) use (&$capturado) {
                $capturado = $expiraEn;
                return true;
            }
        );
        $horarioRepo->method('getStatus')->willReturn(null);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        foreach ([true, false] as $activo) {
            $capturado = null;
            $service->toggleHorarioOverride(1, $activo);

            $this->assertNotNull($capturado, 'No se guardó ninguna expiración.');

            $tz    = new DateTimeZone('America/Santiago');
            $ahora = new DateTime('now', $tz);
            $exp   = DateTime::createFromFormat('Y-m-d H:i:s', $capturado, $tz);

            $this->assertInstanceOf(DateTime::class, $exp, "ExpiraEn con formato inválido: {$capturado}");
            $this->assertGreaterThan(
                $ahora,
                $exp,
                'El override nació vencido: con activo=' . var_export($activo, true) .
                " la expiración quedó en {$capturado}, que ya pasó."
            );
        }
    }

    public function testClearHorarioOverrideFallaSiRepoNoLimpia()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('clear')->willReturn(false);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al limpiar");

        $service->clearHorarioOverride(1);
    }

    // --- getHorarioOverrideStatus ---

    public function testGetHorarioOverrideStatusSinFilaEnBd()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('getStatus')->willReturn(null);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $result = $service->getHorarioOverrideStatus();

        $this->assertNull($result['active']);
        $this->assertStringContainsString('horario laboral', $result['mensaje']);
    }

    public function testGetHorarioOverrideStatusExpirado()
    {
        // Ojo: el servicio compara contra America/Santiago explícito, no el
        // timezone por defecto del proceso PHP (pueden diferir) — se arman
        // las fechas con la misma zona para que la comparación sea correcta.
        $tz = new DateTimeZone('America/Santiago');
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('getStatus')->willReturn([
            'Activo' => 1,
            'ForzadoPor' => 1,
            'FechaActivacion' => (new DateTime('-3 hours', $tz))->format('Y-m-d H:i:s'),
            'ExpiraEn' => (new DateTime('-1 hour', $tz))->format('Y-m-d H:i:s'), // ya pasó
            'Mensaje' => 'Mensaje custom',
        ]);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $result = $service->getHorarioOverrideStatus();

        $this->assertNull($result['active']); // vencido -> vuelve a automático
        $this->assertEquals('Mensaje custom', $result['mensaje']); // el mensaje no expira
    }

    public function testGetHorarioOverrideStatusVigente()
    {
        $tz = new DateTimeZone('America/Santiago');
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('getStatus')->willReturn([
            'Activo' => 1,
            'ForzadoPor' => 7,
            'FechaActivacion' => (new DateTime('now', $tz))->format('Y-m-d H:i:s'),
            'ExpiraEn' => (new DateTime('+2 hours', $tz))->format('Y-m-d H:i:s'),
            'Mensaje' => 'Aviso personalizado',
        ]);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $result = $service->getHorarioOverrideStatus();

        $this->assertTrue($result['active']);
        $this->assertEquals(7, $result['forzado_por']);
        $this->assertEquals('Aviso personalizado', $result['mensaje']);
    }

    // --- updateMensajeHorario ---

    public function testUpdateMensajeHorarioFallaSiVacio()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no puede estar vacío");

        $service->updateMensajeHorario(1, '   ');
    }

    public function testUpdateMensajeHorarioFallaSiMuyLargo()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("500 caracteres");

        $service->updateMensajeHorario(1, str_repeat('a', 501));
    }

    public function testUpdateMensajeHorarioFallaSiRepoNoGuarda()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('updateMensaje')->willReturn(false);

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error al guardar el mensaje");

        $service->updateMensajeHorario(1, 'Mensaje nuevo');
    }

    public function testUpdateMensajeHorarioExitoso()
    {
        $horarioRepo = $this->createMock(HorarioOverrideRepository::class);
        $horarioRepo->method('updateMensaje')->willReturn(true);
        $horarioRepo->method('getStatus')->willReturn([
            'Activo' => 0,
            'ForzadoPor' => null,
            'FechaActivacion' => null,
            'ExpiraEn' => null,
            'Mensaje' => 'Mensaje actualizado',
        ]);

        $logService = $this->createMock(LogService::class);
        $logService->expects($this->once())->method('logAction');

        $service = $this->buildService(['horarioOverrideRepo' => $horarioRepo, 'logService' => $logService]);

        $result = $service->updateMensajeHorario(1, '  Mensaje actualizado  ');

        $this->assertEquals('Mensaje actualizado', $result['mensaje']);
    }

    // --- getHolidays / getActiveHoliday (passthrough simples) ---

    public function testGetHolidaysRetornaLoDelRepo()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getAllFutureAndCurrent')->willReturn([['HolidayID' => 1], ['HolidayID' => 2]]);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $this->assertCount(2, $service->getHolidays());
    }

    public function testGetActiveHolidayRetornaElFeriadoActivo()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getActiveHoliday')->willReturn(['HolidayID' => 5, 'Motivo' => 'Vacaciones']);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $result = $service->getActiveHoliday();

        $this->assertEquals(5, $result['HolidayID']);
    }

    public function testGetActiveHolidayRetornaNullSiNoHayNinguno()
    {
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $holidayRepo->method('getActiveHoliday')->willReturn(null);

        $service = $this->buildService(['holidayRepo' => $holidayRepo]);

        $this->assertNull($service->getActiveHoliday());
    }
}
