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

class PricingRangeTest extends TestCase
{
    public function testSeleccionaTasaCorrectaPorRango()
    {
        $rateRepo = $this->createMock(RateRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $settingsRepo = $this->createMock(SystemSettingsRepository::class);
        $notifService = $this->createMock(NotificationService::class);
        $systemService = $this->createMock(SystemSettingsService::class);
        $tasasImagenRepo = $this->createMock(TasasImagenRepository::class);
        $fileHandler = $this->createMock(FileHandlerService::class);

        $rateRepo->method('findCurrentRate')
            ->will($this->returnCallback(function($origen, $destino, $monto) {
                if ($monto <= 100) {
                    return ['TasaID' => 1, 'ValorTasa' => 1.5];
                } elseif ($monto > 100) {
                    return ['TasaID' => 2, 'ValorTasa' => 1.8];
                }
                return null;
            }));

        $service = new PricingService($rateRepo, $countryRepo, $settingsRepo, $notifService, $systemService, $tasasImagenRepo, $fileHandler);

        $tasaBaja = $service->getCurrentRate(1, 2, 50);
        $this->assertEquals(1.5, $tasaBaja['ValorTasa']);

        $tasaAlta = $service->getCurrentRate(1, 2, 500);
        $this->assertEquals(1.8, $tasaAlta['ValorTasa']);
    }
}