<?php

use PHPUnit\Framework\TestCase;
use App\Services\ContabilidadService;
use App\Repositories\ContabilidadRepository;
use App\Repositories\CountryRepository;
use App\Services\LogService;
use App\Database\Database;

class ContabilidadTest extends TestCase
{
    public function testRegistrarGastoDescuentaSaldoCorrectamente()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);

        $contabRepo->expects($this->once())
            ->method('actualizarSaldo')
            ->with(
                $this->equalTo(1),
                $this->equalTo(799950.00)
            );

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $service->registrarGasto(3, 200000, 50, 1, 100);
    }

    public function testRegistrarGastoNoHaceNadaSiMontoEsCero()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        // montoTx <= 0 corta antes de tocar la BD, ni siquiera abre transacción.
        $contabRepo->expects($this->never())->method('getSaldoPorPais');
        $contabRepo->expects($this->never())->method('actualizarSaldo');

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $resultado = $service->registrarGasto(3, 0, 50, 1, 100);

        $this->assertTrue($resultado);
    }

    public function testRegistrarGastoRetornaFalseSiFallaBd()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->method('registrarMovimiento')->willThrowException(new Exception("Error simulado de BD"));

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $resultado = $service->registrarGasto(3, 200000, 50, 1, 100);

        $this->assertFalse($resultado);
    }

    public function testRegistrarGastoDescuentaSoloMontoSiNoHayComision()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);

        // Sin comisión: solo debe registrarse el movimiento GASTO_TX, nunca GASTO_COMISION.
        $contabRepo->expects($this->once())
            ->method('registrarMovimiento')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->equalTo('GASTO_TX'));

        $contabRepo->expects($this->once())
            ->method('actualizarSaldo')
            ->with($this->equalTo(1), $this->equalTo(800000.00));

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $service->registrarGasto(3, 200000, 0, 1, 100);
    }

    public function testAgregarFondosBancoFallaSiMontoNoPositivo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El monto debe ser positivo");

        $service->agregarFondosBanco(1, 0, 1);
    }

    public function testRegistrarRetiroBancoFallaSiMontoNoPositivo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $countryRepo = $this->createMock(CountryRepository::class);
        $logService = $this->createMock(LogService::class);
        $db = $this->createMock(Database::class);
        $mysqli = $this->createMock(mysqli::class);
        $db->method('getConnection')->willReturn($mysqli);

        $service = new ContabilidadService($contabRepo, $countryRepo, $logService, $db);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El monto debe ser positivo");

        $service->registrarRetiroBanco(1, -50, 'motivo', 1);
    }
}