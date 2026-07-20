<?php

use PHPUnit\Framework\TestCase;
use App\Services\PricingService;
use App\Services\SystemSettingsService;
use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Services\NotificationService;

class PricingRangeTest extends TestCase
{
    public function testSeleccionaTasaCorrectaPorRango()
    {
        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);

        $rateRepo->method('findCurrentRate')
            ->will($this->returnCallback(function($origen, $destino, $monto) {
                if ($monto <= 100) {
                    return ['TasaID' => 1, 'ValorTasa' => 1.5];
                } elseif ($monto > 100) {
                    return ['TasaID' => 2, 'ValorTasa' => 1.8];
                }
                return null;
            }));

        $service = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService);

        $tasaBaja = $service->getCurrentRate(1, 2, 50);
        $this->assertEquals(1.5, $tasaBaja['ValorTasa']);

        $tasaAlta = $service->getCurrentRate(1, 2, 500);
        $this->assertEquals(1.8, $tasaAlta['ValorTasa']);
    }
}