<?php

use PHPUnit\Framework\TestCase;
use App\Services\ContabilidadService;
use App\Repositories\ContabilidadRepository;
use App\Repositories\CountryRepository;
use App\Repositories\CuentasAdminRepository;
use App\Services\LogService;
use App\Database\Database;

class ContabilidadTest extends TestCase
{
    private function buildService(array $overrides = []): ContabilidadService
    {
        $mysqli = $this->createMock(mysqli::class);
        $db = $this->createMock(Database::class);
        $db->method('getConnection')->willReturn($mysqli);

        $defaults = [
            'contabRepo' => $this->createMock(ContabilidadRepository::class),
            'countryRepo' => $this->createMock(CountryRepository::class),
            'logService' => $this->createMock(LogService::class),
            'db' => $db,
            'cuentasAdminRepo' => null,
        ];
        $deps = array_merge($defaults, $overrides);

        return new ContabilidadService(
            $deps['contabRepo'],
            $deps['countryRepo'],
            $deps['logService'],
            $deps['db'],
            $deps['cuentasAdminRepo']
        );
    }

    // --- revertirIngresoVenta (reversa contable al cancelar) ---

    public function testRevertirIngresoVentaSinMovimientosNoTocaNada()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getNetoIngresoVentaPorCuenta')->willReturn([]);
        // Nunca hubo ingreso (o ya se revirtió): no debe registrar ni tocar saldo.
        $contabRepo->expects($this->never())->method('registrarMovimientoBanco');

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->expects($this->never())->method('updateSaldo');

        $service = $this->buildService([
            'contabRepo' => $contabRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
        ]);

        $this->assertTrue($service->revertirIngresoVenta(123, 1));
    }

    public function testRevertirIngresoVentaDescuentaElNetoDelSaldo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getNetoIngresoVentaPorCuenta')
            ->willReturn([['CuentaAdminID' => 7, 'Neto' => '100000.00']]);
        $contabRepo->expects($this->once())
            ->method('registrarMovimientoBanco')
            ->with(7, 1, 123, 'REVERSA_VENTA', 100000.00, 500000.00, 400000.00, $this->stringContains('123'))
            ->willReturn(true);

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->method('getByIdForUpdate')->willReturn(['SaldoActual' => '500000.00']);
        $cuentasAdminRepo->expects($this->once())->method('updateSaldo')->with(7, 400000.00);

        $service = $this->buildService([
            'contabRepo' => $contabRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
        ]);

        $this->assertTrue($service->revertirIngresoVenta(123, 1));
    }

    public function testRevertirIngresoVentaNoTocaSaldoSiFallaElMovimiento()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getNetoIngresoVentaPorCuenta')
            ->willReturn([['CuentaAdminID' => 7, 'Neto' => '100000.00']]);
        // El INSERT del movimiento falla: no debe actualizarse el saldo, o el
        // libro y el saldo quedarían desincronizados.
        $contabRepo->method('registrarMovimientoBanco')->willReturn(false);

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->method('getByIdForUpdate')->willReturn(['SaldoActual' => '500000.00']);
        $cuentasAdminRepo->expects($this->never())->method('updateSaldo');

        $service = $this->buildService([
            'contabRepo' => $contabRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
        ]);

        $this->assertFalse($service->revertirIngresoVenta(123, 1));
    }

    public function testRevertirIngresoVentaFallaSiLaCuentaYaNoExiste()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getNetoIngresoVentaPorCuenta')
            ->willReturn([['CuentaAdminID' => 7, 'Neto' => '100000.00']]);
        $contabRepo->expects($this->never())->method('registrarMovimientoBanco');

        $cuentasAdminRepo = $this->createMock(CuentasAdminRepository::class);
        $cuentasAdminRepo->method('getByIdForUpdate')->willReturn(null);
        $cuentasAdminRepo->expects($this->never())->method('updateSaldo');

        $service = $this->buildService([
            'contabRepo' => $contabRepo,
            'cuentasAdminRepo' => $cuentasAdminRepo,
        ]);

        $this->assertFalse($service->revertirIngresoVenta(123, 1));
    }

    public function testRegistrarGastoDescuentaSaldoCorrectamente()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->expects($this->once())
            ->method('actualizarSaldo')
            ->with($this->equalTo(1), $this->equalTo(799950.00));

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $service->registrarGasto(3, 200000, 50, 1, 100);
    }

    public function testRegistrarGastoNoHaceNadaSiMontoEsCero()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        // montoTx <= 0 corta antes de tocar la BD, ni siquiera abre transacción.
        $contabRepo->expects($this->never())->method('getSaldoPorPais');
        $contabRepo->expects($this->never())->method('getSaldoPorPaisForUpdate');
        $contabRepo->expects($this->never())->method('actualizarSaldo');

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $resultado = $service->registrarGasto(3, 0, 50, 1, 100);

        $this->assertTrue($resultado);
    }

    public function testRegistrarGastoRetornaFalseSiFallaBd()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->method('registrarMovimiento')->willThrowException(new Exception("Error simulado de BD"));

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $resultado = $service->registrarGasto(3, 200000, 50, 1, 100);

        $this->assertFalse($resultado);
    }

    public function testRegistrarGastoDescuentaSoloMontoSiNoHayComision()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn([
            'SaldoID' => 1,
            'SaldoActual' => 1000000.00,
            'PaisID' => 3
        ]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn([
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

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $service->registrarGasto(3, 200000, 0, 1, 100);
    }

    public function testAgregarFondosBancoFallaSiMontoNoPositivo()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El monto debe ser positivo");

        $service->agregarFondosBanco(1, 0, 1);
    }

    public function testRegistrarRetiroBancoFallaSiMontoNoPositivo()
    {
        $service = $this->buildService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El monto debe ser positivo");

        $service->registrarRetiroBanco(1, -50, 'motivo', 1);
    }

    // --- getSaldosDashboard / getSaldosBancosDashboard / getSaldosPaises / getSaldosBancos ---

    public function testGetSaldosDashboardRetornaLoDelRepo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldosDashboard')->willReturn([['PaisID' => 3, 'Saldo' => 1000]]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $this->assertCount(1, $service->getSaldosDashboard());
    }

    public function testGetSaldosBancosDashboardRetornaLoDelRepo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldosBancos')->willReturn([['CuentaAdminID' => 1]]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $this->assertCount(1, $service->getSaldosBancosDashboard());
    }

    public function testGetSaldosPaisesEsAliasDeGetSaldosDashboard()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldosDashboard')->willReturn([['PaisID' => 1], ['PaisID' => 2]]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $this->assertCount(2, $service->getSaldosPaises());
    }

    public function testGetSaldosBancosRetornaLoDelRepo()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldosBancos')->willReturn([['CuentaAdminID' => 1], ['CuentaAdminID' => 2], ['CuentaAdminID' => 3]]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $this->assertCount(3, $service->getSaldosBancos());
    }

    // --- getResumenMensual (rama "pais", no toca cuentasAdminRepo) ---

    public function testGetResumenMensualPaisCalculaTotalGastado()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getMovimientosDelMes')->willReturn([
            ['TipoMovimiento' => 'GASTO_TX', 'Monto' => 1000],
            ['TipoMovimiento' => 'GASTO_COMISION', 'Monto' => 50],
            ['TipoMovimiento' => 'INGRESO_VENTA', 'Monto' => 5000], // no debe sumarse
        ]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $result = $service->getResumenMensual('pais', 3, 7, 2026);

        $this->assertEquals(1050.0, $result['TotalGastado']);
        $this->assertCount(3, $result['Movimientos']);
    }

    public function testGetResumenMensualPaisSinMovimientosTotalEsCero()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getMovimientosDelMes')->willReturn([]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $result = $service->getResumenMensual('pais', 3, 7, 2026);

        $this->assertEquals(0.0, $result['TotalGastado']);
    }

    // --- procesarCompraDivisas (rama de fallo, no llega a tocar cuentasAdminRepo) ---

    public function testProcesarCompraDivisasFallaSiNoHayCuentaDestino()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldosBancos')->willReturn([
            ['PaisID' => 1, 'Rol' => 'Destino', 'CuentaAdminID' => 5],
        ]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No existe una cuenta bancaria de destino");

        $service->procesarCompraDivisas(1, 3, 10000, 3800, 1); // PaisID 3 no está en la lista
    }

    public function testGetResumenMensualPaisSoloCuentaTiposDeGasto()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getMovimientosDelMes')->willReturn([
            ['TipoMovimiento' => 'RETIRO_DIVISAS', 'Monto' => 300],
            ['TipoMovimiento' => 'COMPRA_DIVISA', 'Monto' => 9999], // no es un tipo de gasto, no debe sumarse
        ]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $result = $service->getResumenMensual('pais', 3, 1, 2026);

        $this->assertEquals(300.0, $result['TotalGastado']);
    }

    public function testGetResumenMensualPaisEntidadYMonedaFijas()
    {
        $contabRepo = $this->createMock(ContabilidadRepository::class);
        $contabRepo->method('getSaldoPorPais')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getSaldoPorPaisForUpdate')->willReturn(['SaldoID' => 1, 'SaldoActual' => 500]);
        $contabRepo->method('getMovimientosDelMes')->willReturn([]);

        $service = $this->buildService(['contabRepo' => $contabRepo]);

        $result = $service->getResumenMensual('pais', 3, 1, 2026);

        $this->assertEquals('Caja País/Destino', $result['Entidad']);
        $this->assertEquals('N/A', $result['Moneda']);
    }
}
