<?php

use PHPUnit\Framework\TestCase;
use App\Services\LiquidacionService;
use App\Services\PricingService;
use App\Repositories\TransactionRepository;
use App\Repositories\LiquidacionRepository;

/**
 * Liquidación de comisiones de revendedor, multi-moneda.
 *
 * Acá hay dinero real: cada test de este archivo cubre una forma concreta en la
 * que el sistema le podría pagar de más o de menos a una persona.
 */
class LiquidacionServiceTest extends TestCase
{
    private $txRepo;
    private $liqRepo;
    private $pricing;
    private $mysqli;

    protected function setUp(): void
    {
        $this->txRepo  = $this->createMock(TransactionRepository::class);
        $this->liqRepo = $this->createMock(LiquidacionRepository::class);
        $this->pricing = $this->createMock(PricingService::class);
        $this->mysqli  = $this->createMock(mysqli::class);
        $this->liqRepo->method('getConnection')->willReturn($this->mysqli);
    }

    private function service(): LiquidacionService
    {
        return new LiquidacionService($this->txRepo, $this->liqRepo, $this->pricing);
    }

    private function fila(string $moneda, float $total, int $cantidad): array
    {
        return ['Moneda' => $moneda, 'Total' => $total, 'Cantidad' => $cantidad];
    }

    // ─── Desglose / previsualización ────────────────────────────────────────

    public function testPrevisualizaDesglosePorMonedaSinSumarMonedasDistintas()
    {
        $this->txRepo->method('getResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);
        $this->pricing->method('getFactorConversionACLP')->willReturnCallback(
            fn($m) => $m === 'CLP'
                ? ['factor' => 1.0, 'sentido' => 'identidad', 'tasaId' => 0, 'valorTasa' => 1.0]
                : ['factor' => 1 / 3.42, 'sentido' => 'inversa', 'tasaId' => 22, 'valorTasa' => 3.42]
        );

        $p = $this->service()->previsualizar(100, '2026-01-01', '2026-01-31');

        $this->assertCount(2, $p['desglose']);
        $this->assertSame('CLP', $p['desglose'][0]['moneda']);
        $this->assertSame(5000.0, $p['desglose'][0]['total']);
        $this->assertSame('COP', $p['desglose'][1]['moneda']);
        $this->assertSame(20000.0, $p['desglose'][1]['total']);
        $this->assertSame(5, $p['cantidadTotal']);
        $this->assertSame([], $p['faltanTasas']);
        // 5000 + 20000/3.42 = 5000 + 5847.95 = 10847.95 (NO 25000)
        $this->assertSame(10847.95, $p['totalCLPSugerido']);
    }

    public function testPrevisualizaMarcaLasMonedasSinTasaYNoInventaNinguna()
    {
        $this->txRepo->method('getResellerCommissionInRange')->willReturn([
            $this->fila('USD', 40.0, 1),
        ]);
        $this->pricing->method('getFactorConversionACLP')->willReturn(null);

        $p = $this->service()->previsualizar(100, '2026-01-01', '2026-01-31');

        $this->assertSame(['USD'], $p['faltanTasas']);
        $this->assertNull($p['desglose'][0]['tasaSugerida']);
        $this->assertNull($p['desglose'][0]['totalCLPSugerido']);
        // Sin la tasa NO se propone ningún total: nada de asumir 1:1.
        $this->assertNull($p['totalCLPSugerido']);
    }

    // ─── Modo por_moneda ────────────────────────────────────────────────────

    public function testModoPorMonedaCreaUnaLiquidacionPorCadaMoneda()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);

        $this->liqRepo->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function ($userId, $monto, $desde, $hasta, $cantidad, $notas, $moneda, $modo) {
                $this->assertSame(LiquidacionService::MODO_POR_MONEDA, $modo);
                if ($moneda === 'CLP') {
                    $this->assertSame(5000.0, $monto);
                    return 11;
                }
                $this->assertSame('COP', $moneda);
                $this->assertSame(20000.0, $monto);
                return 12;
            });

        // Cada liquidación asigna SOLO las órdenes de su propia moneda.
        $this->txRepo->expects($this->exactly(2))
            ->method('assignLiquidacionToTransactions')
            ->willReturnCallback(function ($userId, $desde, $hasta, $liqId, $moneda) {
                $this->assertNotNull($moneda, 'En modo por_moneda el filtro de moneda es obligatorio.');
                return $moneda === 'CLP' ? 2 : 3;
            });

        $this->mysqli->expects($this->once())->method('commit');
        $this->mysqli->expects($this->never())->method('rollback');

        $r = $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');

        $this->assertCount(2, $r['liquidaciones']);
        $this->assertSame('CLP', $r['liquidaciones'][0]['moneda']);
        $this->assertSame(11, $r['liquidaciones'][0]['liquidacionId']);
        $this->assertSame('COP', $r['liquidaciones'][1]['moneda']);
        $this->assertSame(20000.0, $r['liquidaciones'][1]['monto']);
    }

    public function testModoPorMonedaGuardaDetalleConTasaUno()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('COP', 20000.0, 3)]);
        $this->liqRepo->method('create')->willReturn(50);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(3);

        $this->liqRepo->expects($this->once())
            ->method('addDetalleMoneda')
            ->with(50, 'COP', 20000.0, 1.0, 20000.0, 3);

        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');
    }

    public function testModoPorMonedaHaceRollbackSiElAssignNoMarcaLasFilasEsperadas()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('COP', 20000.0, 3)]);
        $this->liqRepo->method('create')->willReturn(50);
        // Se esperaban 3 y se marcaron 2: alguien más se llevó una orden.
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);

        $this->mysqli->expects($this->once())->method('rollback');
        $this->mysqli->expects($this->never())->method('commit');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);
        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');
    }

    // ─── Modo consolidado_clp ───────────────────────────────────────────────

    public function testModoConsolidadoCreaUnaSolaLiquidacionEnCLPConLasTasasDelAdmin()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);

        $this->liqRepo->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($userId, $monto, $desde, $hasta, $cantidad, $notas, $moneda, $modo) {
                $this->assertSame('CLP', $moneda);
                $this->assertSame(LiquidacionService::MODO_CONSOLIDADO_CLP, $modo);
                // 5000 + 20000 * 0.25 = 10000
                $this->assertSame(10000.0, $monto);
                $this->assertSame(5, $cantidad);
                return 77;
            });

        // Una fila de detalle por moneda, con la tasa usada persistida.
        $detalles = [];
        $this->liqRepo->method('addDetalleMoneda')
            ->willReturnCallback(function ($liqId, $moneda, $orig, $tasa, $conv, $cant) use (&$detalles) {
                $detalles[$moneda] = ['orig' => $orig, 'tasa' => $tasa, 'conv' => $conv, 'cant' => $cant];
                return 1;
            });

        // Una sola liquidación cubre todas las monedas: sin filtro.
        $this->txRepo->expects($this->once())
            ->method('assignLiquidacionToTransactions')
            ->with(100, '2026-01-01', '2026-01-31', 77, null)
            ->willReturn(5);

        $r = $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['COP' => 0.25]
        );

        $this->assertCount(1, $r['liquidaciones']);
        $this->assertSame(10000.0, $r['liquidaciones'][0]['monto']);
        $this->assertSame(1.0,  $detalles['CLP']['tasa']);
        $this->assertSame(0.25, $detalles['COP']['tasa']);
        $this->assertSame(5000.0, $detalles['COP']['conv']);
    }

    public function testModoConsolidadoUsaLaTasaSugeridaSiElAdminNoMandaNinguna()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('COP', 20000.0, 3)]);
        $this->pricing->method('getFactorConversionACLP')->willReturn(['factor' => 0.5, 'sentido' => 'inversa']);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(3);

        $this->liqRepo->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($u, $monto) {
                $this->assertSame(10000.0, $monto);
                return 78;
            });

        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_clp');
    }

    public function testModoConsolidadoRechazaSiFaltaLaTasaDeUnaMoneda()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('USD', 40.0, 1),
        ]);
        // USD no tiene ninguna ruta activa hoy.
        $this->pricing->method('getFactorConversionACLP')->willReturn(null);

        $this->liqRepo->expects($this->never())->method('create');
        $this->mysqli->expects($this->once())->method('rollback');

        try {
            $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_clp');
            $this->fail('Debía rechazar: no se puede inventar la tasa de USD.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('USD', $e->getMessage());
        }
    }

    /**
     * @dataProvider tasasNoPositivasProvider
     */
    public function testModoConsolidadoRechazaTasaNoPositiva($tasa)
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('COP', 20000.0, 3)]);
        $this->liqRepo->expects($this->never())->method('create');

        try {
            $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['COP' => $tasa]);
            $this->fail('Debía rechazar la tasa ' . var_export($tasa, true));
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('COP', $e->getMessage());
        }
    }

    public static function tasasNoPositivasProvider(): array
    {
        return [
            'cero'      => [0],
            'negativa'  => [-3.42],
            'vacia'     => [''],
            'no numero' => ['tres'],
            'null'      => [null],
            'absurda'   => [999999999],
        ];
    }

    public function testModoConsolidadoIgnoraUnaTasaFalsaParaCLP()
    {
        // CLP a CLP es 1 por definición; que el admin mande 5 no puede
        // multiplicar por 5 lo que se le paga.
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);

        $this->liqRepo->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($u, $monto) {
                $this->assertSame(5000.0, $monto);
                return 79;
            });

        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['CLP' => 5]);
    }

    public function testModoConsolidadoHaceRollbackSiElAssignNoCoincide()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->method('create')->willReturn(80);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(1);

        $this->mysqli->expects($this->once())->method('rollback');
        $this->expectExceptionCode(409);
        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_clp');
    }

    // ─── Validaciones generales ─────────────────────────────────────────────

    public function testModoDesconocidoSeRechazaConCodigo400()
    {
        $this->liqRepo->expects($this->never())->method('create');
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'consolidado_usd');
    }

    public function testSinComisionesPendientesNoCreaNada()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([]);
        $this->liqRepo->expects($this->never())->method('create');
        $this->mysqli->expects($this->once())->method('rollback');

        $this->expectExceptionCode(422);
        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');
    }

    public function testElCalculoSeHaceConLockDentroDeLaTransaccionNoConElSelectDePreview()
    {
        // Si el monto se leyera fuera de la transacción, dos admins liquidando
        // a la vez pagarían dos veces lo mismo.
        $this->txRepo->expects($this->once())->method('lockResellerCommissionInRange');
        $this->txRepo->expects($this->never())->method('getResellerCommissionInRange');
        $this->mysqli->expects($this->once())->method('begin_transaction');

        try {
            $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');
        } catch (Exception $e) {
            // sin filas → 422, no importa acá
        }
    }
}
