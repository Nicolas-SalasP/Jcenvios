<?php

use PHPUnit\Framework\TestCase;
use App\Services\LiquidacionService;
use App\Services\PricingService;
use App\Repositories\TransactionRepository;
use App\Repositories\LiquidacionRepository;
use App\Services\NotificationService;

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
    private $notif;

    protected function setUp(): void
    {
        $this->txRepo  = $this->createMock(TransactionRepository::class);
        $this->liqRepo = $this->createMock(LiquidacionRepository::class);
        $this->pricing = $this->createMock(PricingService::class);
        $this->notif   = $this->createMock(NotificationService::class);
        $this->mysqli  = $this->createMock(mysqli::class);
        $this->liqRepo->method('getConnection')->willReturn($this->mysqli);
    }

    private function service(): LiquidacionService
    {
        return new LiquidacionService($this->txRepo, $this->liqRepo, $this->pricing, $this->notif);
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

    // ─── Ajuste manual con motivo ───────────────────────────────────────────
    //
    // Cada test de acá cubre una forma en la que el ajuste podría hacer que se
    // le pague al revendedor un monto distinto sin dejar rastro de por qué.

    /**
     * Captura los argumentos de create() para poder afirmar sobre
     * Monto / MontoBase / MontoAjuste / MotivoAjuste.
     */
    private function capturarCreate(array &$capturas, int $idBase = 500): void
    {
        $this->liqRepo->method('create')->willReturnCallback(
            function ($userId, $monto, $desde, $hasta, $cantidad, $notas, $moneda, $modo, $montoBase = null, $montoAjuste = 0.0, $motivoAjuste = null) use (&$capturas, $idBase) {
                $capturas[$moneda] = [
                    'monto'  => $monto,
                    'base'   => $montoBase,
                    'ajuste' => $montoAjuste,
                    'motivo' => $motivoAjuste,
                ];
                return $idBase + count($capturas);
            }
        );
    }

    public function testAjustePositivoSeSumaAlMontoYGuardaBaseDeltaYMotivo()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $capturas = [];
        $this->capturarCreate($capturas);

        $r = $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 1500, 'motivo' => 'Bono acordado por volumen de mayo']],
            9
        );

        // Monto = lo efectivamente pagado. Base = lo calculado. El delta aparte.
        $this->assertSame(6500.0, $capturas['CLP']['monto']);
        $this->assertSame(5000.0, $capturas['CLP']['base']);
        $this->assertSame(1500.0, $capturas['CLP']['ajuste']);
        $this->assertSame('Bono acordado por volumen de mayo', $capturas['CLP']['motivo']);
        $this->assertSame(6500.0, $r['liquidaciones'][0]['monto']);
        $this->assertSame(5000.0, $r['liquidaciones'][0]['montoBase']);
    }

    public function testAjusteNegativoRestaDelMonto()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $capturas = [];
        $this->capturarCreate($capturas);

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => -2000.50, 'motivo' => 'Anticipo entregado en efectivo el 03/05']],
            9
        );

        $this->assertSame(2999.50, $capturas['CLP']['monto']);
        $this->assertSame(5000.0,  $capturas['CLP']['base']);
        $this->assertSame(-2000.50, $capturas['CLP']['ajuste']);
    }

    public function testAjusteCeroSinMotivoEsPerfectamenteValidoYNoLoguea()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $capturas = [];
        $this->capturarCreate($capturas);

        // Sin ajuste no hay nada que justificar: es el camino del 99%.
        $this->notif->expects($this->never())->method('logAdminAction');

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 0, 'motivo' => '']],
            9
        );

        $this->assertSame(5000.0, $capturas['CLP']['monto']);
        $this->assertSame(0.0,    $capturas['CLP']['ajuste']);
        $this->assertNull($capturas['CLP']['motivo']);
    }

    public function testAjusteCeroDescartaElMotivoQueVengaIgual()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $capturas = [];
        $this->capturarCreate($capturas);

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 0, 'motivo' => 'motivo que no corresponde a ningún ajuste']],
            9
        );

        $this->assertNull($capturas['CLP']['motivo'], 'Un motivo sin ajuste no se guarda.');
    }

    /**
     * @dataProvider motivosVaciosProvider
     */
    public function testAjusteDistintoDeCeroSinMotivoSeRechazaCon422($motivo)
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');
        $this->mysqli->expects($this->once())->method('rollback');
        $this->mysqli->expects($this->never())->method('commit');

        try {
            $this->service()->crear(
                100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
                ['CLP' => ['monto' => 1500, 'motivo' => $motivo]],
                9
            );
            $this->fail('Un ajuste sin motivo es el agujero de auditoría que esto viene a cerrar.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('motivo', mb_strtolower($e->getMessage()));
        }
    }

    public static function motivosVaciosProvider(): array
    {
        return [
            'null'            => [null],
            'vacio'           => [''],
            'solo espacios'   => ['     '],
        ];
    }

    public function testMotivoDeSoloEspaciosNoCuentaComoMotivo()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');

        $this->expectExceptionCode(422);
        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => -100, 'motivo' => "  \t \n  "]],
            9
        );
    }

    public function testMotivoDemasiadoCortoSeRechaza()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');

        $this->expectExceptionCode(422);
        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 100, 'motivo' => 'ok']],
            9
        );
    }

    public function testMotivoMasLargoQueLaColumnaSeRechazaEnVezDeTruncarse()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');

        $this->expectExceptionCode(422);
        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 100, 'motivo' => str_repeat('x', 256)]],
            9
        );
    }

    /**
     * @dataProvider finalNoPositivoProvider
     */
    public function testAjusteQueDejaElMontoFinalEnCeroONegativoSeRechaza($delta)
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');
        $this->mysqli->expects($this->once())->method('rollback');

        try {
            $this->service()->crear(
                100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
                ['CLP' => ['monto' => $delta, 'motivo' => 'Descuento total acordado con el revendedor']],
                9
            );
            $this->fail('No se puede pagar una liquidación de cero o negativa.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
        }
    }

    public static function finalNoPositivoProvider(): array
    {
        return [
            'deja en cero'     => [-5000],
            'deja en negativo' => [-5000.01],
            'muy negativo'     => [-90000],
        ];
    }

    public function testAjusteNoNumericoSeRechaza()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->liqRepo->expects($this->never())->method('create');

        $this->expectExceptionCode(422);
        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 'mil quinientos', 'motivo' => 'Anticipo entregado en mayo']],
            9
        );
    }

    // ─── Aislamiento entre monedas ──────────────────────────────────────────

    public function testEnModoPorMonedaCadaLiquidacionLlevaSuPropioAjusteEnSuMoneda()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);
        $this->txRepo->method('assignLiquidacionToTransactions')
            ->willReturnCallback(fn($u, $d, $h, $id, $m) => $m === 'CLP' ? 2 : 3);
        $capturas = [];
        $this->capturarCreate($capturas);

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            [
                'CLP' => ['monto' => -1000, 'motivo' => 'Anticipo en pesos ya entregado'],
                'COP' => ['monto' => 5000,  'motivo' => 'Redondeo acordado en pesos colombianos'],
            ],
            9
        );

        $this->assertSame(4000.0,  $capturas['CLP']['monto']);
        $this->assertSame(-1000.0, $capturas['CLP']['ajuste']);
        $this->assertSame(25000.0, $capturas['COP']['monto']);
        $this->assertSame(5000.0,  $capturas['COP']['ajuste']);
        $this->assertSame('Anticipo en pesos ya entregado', $capturas['CLP']['motivo']);
        $this->assertSame('Redondeo acordado en pesos colombianos', $capturas['COP']['motivo']);
    }

    public function testElAjusteDeUnaMonedaNoTocaLaLiquidacionDeOtra()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);
        $this->txRepo->method('assignLiquidacionToTransactions')
            ->willReturnCallback(fn($u, $d, $h, $id, $m) => $m === 'CLP' ? 2 : 3);
        $capturas = [];
        $this->capturarCreate($capturas);

        // Sólo se ajusta COP: la liquidación en CLP tiene que quedar intacta.
        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['COP' => ['monto' => 5000, 'motivo' => 'Redondeo acordado en pesos colombianos']],
            9
        );

        $this->assertSame(5000.0, $capturas['CLP']['monto'], 'La liquidación en CLP no lleva el ajuste de COP.');
        $this->assertSame(0.0,    $capturas['CLP']['ajuste']);
        $this->assertNull($capturas['CLP']['motivo']);
        $this->assertSame(25000.0, $capturas['COP']['monto']);
    }

    public function testUnAjusteEnUnaMonedaQueNoSeLiquidoSeRechazaEnVezDeDescartarseEnSilencio()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $this->liqRepo->method('create')->willReturn(600);

        $this->mysqli->expects($this->once())->method('rollback');
        $this->mysqli->expects($this->never())->method('commit');

        try {
            $this->service()->crear(
                100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
                ['COP' => ['monto' => 5000, 'motivo' => 'Redondeo que no corresponde a ninguna liquidación']],
                9
            );
            $this->fail('Un ajuste que no se aplica a nada tiene que rechazarse, no perderse.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('COP', $e->getMessage());
        }
    }

    public function testEnModoConsolidadoUnAjusteEnCOPSeRechazaPorqueLaLiquidacionEsEnCLP()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(5);
        $this->liqRepo->method('create')->willReturn(700);

        $this->mysqli->expects($this->once())->method('rollback');

        try {
            $this->service()->crear(
                100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['COP' => 0.25], null,
                ['COP' => ['monto' => 500, 'motivo' => 'Ajuste mal dirigido a una moneda que no se paga']],
                9
            );
            $this->fail('En consolidado la única liquidación es en CLP; un ajuste en COP no se puede aplicar.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
        }
    }

    // ─── Consolidado: el ajuste va sobre el total ya convertido ─────────────

    public function testEnModoConsolidadoElAjusteVaEnCLPSobreElTotalConvertido()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([
            $this->fila('CLP', 5000.0, 2),
            $this->fila('COP', 20000.0, 3),
        ]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(5);
        $capturas = [];
        $this->capturarCreate($capturas);

        $r = $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['COP' => 0.25], null,
            ['CLP' => ['monto' => -3000, 'motivo' => 'Anticipo de 3.000 entregado el 10/05']],
            9
        );

        // Base = 5000 + 20000*0.25 = 10000. Final = 10000 - 3000 = 7000.
        $this->assertSame(10000.0, $capturas['CLP']['base']);
        $this->assertSame(-3000.0, $capturas['CLP']['ajuste']);
        $this->assertSame(7000.0,  $capturas['CLP']['monto']);
        $this->assertSame(7000.0,  $r['liquidaciones'][0]['monto']);
        $this->assertSame(10000.0, $r['liquidaciones'][0]['montoBase']);
    }

    public function testElDetalleDeMonedaSigueReflejandoSoloLaConversionNoElAjuste()
    {
        // El ajuste NO se disfraza de tasa: el detalle tiene que seguir sumando
        // el MontoBase, no el monto final. Si no, el detalle afirmaría una
        // conversión cambiaria que nunca ocurrió.
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('COP', 20000.0, 3)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(3);
        $this->liqRepo->method('create')->willReturn(800);

        $this->liqRepo->expects($this->once())
            ->method('addDetalleMoneda')
            ->with(800, 'COP', 20000.0, 0.25, 5000.0, 3);

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'consolidado_clp', ['COP' => 0.25], null,
            ['CLP' => ['monto' => -1000, 'motivo' => 'Anticipo ya entregado en efectivo']],
            9
        );
    }

    // ─── Bitácora ───────────────────────────────────────────────────────────

    public function testUnAjusteDistintoDeCeroDejaRastroEnLaBitacoraConQuienCuantoYPorQue()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $this->liqRepo->method('create')->willReturn(901);

        $this->notif->expects($this->once())
            ->method('logAdminAction')
            ->willReturnCallback(function ($adminId, $accion, $detalle) {
                $this->assertSame(9, $adminId, 'Tiene que quedar QUIÉN hizo el ajuste.');
                $this->assertStringContainsString('ajust', mb_strtolower($accion));
                $this->assertStringContainsString('901', $detalle);
                $this->assertStringContainsString('5.000,00', $detalle);  // base
                $this->assertStringContainsString('-1.500,00', $detalle); // delta
                $this->assertStringContainsString('3.500,00', $detalle);  // final
                $this->assertStringContainsString('Anticipo entregado en efectivo', $detalle);
            });

        $this->service()->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => -1500, 'motivo' => 'Anticipo entregado en efectivo']],
            9
        );
    }

    public function testSinAjusteNoSeEnsuciaLaBitacora()
    {
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $this->liqRepo->method('create')->willReturn(902);

        $this->notif->expects($this->never())->method('logAdminAction');

        $this->service()->crear(100, '2026-01-01', '2026-01-31', 'por_moneda');
    }

    public function testElServicioSigueFuncionandoSinNotificationService()
    {
        // El 4º parámetro es opcional a propósito (los crons y el init.php legacy
        // arman servicios a mano). Un ajuste no puede reventar por eso.
        $svc = new LiquidacionService($this->txRepo, $this->liqRepo, $this->pricing);
        $this->txRepo->method('lockResellerCommissionInRange')->willReturn([$this->fila('CLP', 5000.0, 2)]);
        $this->txRepo->method('assignLiquidacionToTransactions')->willReturn(2);
        $this->liqRepo->method('create')->willReturn(903);

        $r = $svc->crear(
            100, '2026-01-01', '2026-01-31', 'por_moneda', [], null,
            ['CLP' => ['monto' => 100, 'motivo' => 'Redondeo hacia arriba acordado']],
            9
        );

        $this->assertSame(5100.0, $r['liquidaciones'][0]['monto']);
    }
}
