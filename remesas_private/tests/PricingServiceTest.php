<?php

use PHPUnit\Framework\TestCase;
use App\Services\PricingService;
use App\Services\SystemSettingsService;
use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Services\NotificationService;

class PricingServiceTest extends TestCase
{
    public function testGetCurrentRateCalculaCorrectamente()
    {
        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);

        $rateRepo->method('findCurrentRate')
            ->willReturn(['TasaID' => 1, 'ValorTasa' => 3.81]);

        $service = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService);

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

        $service = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService);

        $service->getCurrentRate(1, 1, 10000);
    }
}