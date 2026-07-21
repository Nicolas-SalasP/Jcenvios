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

class PricingServiceTest extends TestCase
{
    private function buildService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService)
    {
        $tasasImagenRepo = $this->createMock(TasasImagenRepository::class);
        $fileHandler = $this->createMock(FileHandlerService::class);
        return new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService, $tasasImagenRepo, $fileHandler);
    }

    public function testGetCurrentRateCalculaCorrectamente()
    {
        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);

        $rateRepo->method('findCurrentRate')
            ->willReturn(['TasaID' => 1, 'ValorTasa' => 3.81]);

        $service = $this->buildService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService);

        $resultado = $service->getCurrentRate(1, 2, 10000);

        $this->assertIsArray($resultado);
        $this->assertEquals(3.81, $resultado['ValorTasa']);
    }

    public function testErrorSiOrigenYDestinoSonIguales()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no pueden ser iguales");

        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);

        $service = $this->buildService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService);

        $service->getCurrentRate(1, 1, 10000);
    }

    public function testClearTasasImagenGaleriaBorraFilasYArchivos()
    {
        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);
        $tasasImagenRepo = $this->createMock(TasasImagenRepository::class);
        $fileHandler = $this->createMock(FileHandlerService::class);

        $tasasImagenRepo->method('deleteAll')
            ->willReturn(['tasas_imagen/a.jpg', 'tasas_imagen/b.jpg']);
        $fileHandler->expects($this->exactly(2))->method('deleteTasaImagen');

        $service = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService, $tasasImagenRepo, $fileHandler);

        $method = new ReflectionMethod(PricingService::class, 'clearTasasImagenGaleria');
        $method->setAccessible(true);
        $method->invoke($service);
    }
}