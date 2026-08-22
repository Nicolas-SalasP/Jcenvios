<?php
namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\LiquidacionRepository;
use Exception;

/**
 * Liquidación de comisiones de revendedor, multi-moneda.
 *
 * CONTEXTO DEL PROBLEMA
 * ---------------------
 * La comisión se calcula en TransactionService como
 * ($montoOrigen * $porcentaje) / 100, así que queda expresada en la moneda de
 * ORIGEN de cada orden (transacciones.MonedaOrigen). En la BD conviven órdenes
 * en CLP, COP, PEN y USD. Antes se hacía un SUM(ComisionRevendedor) sin agrupar
 * y el resultado se pagaba como pesos chilenos: 5.000 CLP + 20.000 COP se
 * pagaban como 25.000 CLP, cuando 20.000 COP son ~5.800 CLP.
 *
 * DOS MODOS
 * ---------
 *  - MODO_POR_MONEDA:     una liquidación separada por cada moneda. Sin
 *                         conversión, sin riesgo cambiario. Cada liquidación
 *                         marca SOLO las órdenes de su moneda.
 *  - MODO_CONSOLIDADO_CLP: una sola liquidación en CLP. Requiere una tasa por
 *                         cada moneda distinta de CLP. Las tasas se proponen
 *                         automáticamente (PricingService::getFactorConversionACLP)
 *                         pero el admin las ve y las puede corregir antes de
 *                         confirmar; si falta alguna, se RECHAZA la operación
 *                         entera. Nunca se asume 1:1.
 *
 * CONCURRENCIA
 * ------------
 * El cálculo del monto y la asignación de las transacciones ocurren dentro de
 * la MISMA transacción SQL, y las transacciones candidatas se bloquean con
 * SELECT ... FOR UPDATE antes de calcular. Antes el SELECT iba afuera y dos
 * admins liquidando a la vez podían pagar dos veces las mismas comisiones.
 * Además se verifica que la cantidad de filas afectadas por el UPDATE coincida
 * con lo previsto; si no coincide, rollback.
 */
class LiquidacionService
{
    public const MODO_POR_MONEDA      = 'por_moneda';
    public const MODO_CONSOLIDADO_CLP = 'consolidado_clp';

    public const MODOS_VALIDOS = [self::MODO_POR_MONEDA, self::MODO_CONSOLIDADO_CLP];

    /** Tope de cordura para la tasa de conversión (atajar dedazos con un 0 de más). */
    public const MAX_TASA_CONVERSION = 1000000.0;

    private TransactionRepository $txRepository;
    private LiquidacionRepository $liquidacionRepo;
    private PricingService $pricingService;

    public function __construct(
        TransactionRepository $txRepository,
        LiquidacionRepository $liquidacionRepo,
        PricingService $pricingService
    ) {
        $this->txRepository    = $txRepository;
        $this->liquidacionRepo = $liquidacionRepo;
        $this->pricingService  = $pricingService;
    }

    /**
     * Previsualización: desglose por moneda + tasa sugerida para cada una.
     *
     * La tasa sugerida puede venir null (no hay ninguna ruta activa para esa
     * moneda, ej. USD hoy). En ese caso el admin la tiene que escribir a mano
     * si elige el modo consolidado.
     *
     * @return array{desglose: array, cantidadTotal: int, totalCLPSugerido: ?float, faltanTasas: array<int,string>}
     */
    public function previsualizar(int $userId, string $desde, string $hasta): array
    {
        $porMoneda = $this->txRepository->getResellerCommissionInRange($userId, $desde, $hasta);
        return $this->armarPreview($porMoneda);
    }

    /**
     * @param array<int, array{Moneda: string, Total: float, Cantidad: int}> $porMoneda
     */
    private function armarPreview(array $porMoneda): array
    {
        $desglose      = [];
        $cantidadTotal = 0;
        $totalCLP      = 0.0;
        $faltanTasas   = [];

        foreach ($porMoneda as $fila) {
            $moneda   = strtoupper((string) $fila['Moneda']);
            $total    = (float) $fila['Total'];
            $cantidad = (int) $fila['Cantidad'];
            $cantidadTotal += $cantidad;

            $info   = $this->pricingService->getFactorConversionACLP($moneda);
            $factor = $info['factor'] ?? null;

            if ($factor === null) {
                $faltanTasas[] = $moneda;
            } else {
                $totalCLP += $total * $factor;
            }

            $desglose[] = [
                'moneda'          => $moneda,
                'total'           => round($total, 2),
                'cantidad'        => $cantidad,
                // "CLP por 1 unidad de $moneda": montoCLP = total * tasaSugerida
                'tasaSugerida'    => $factor !== null ? round($factor, 8) : null,
                'sentidoTasa'     => $info['sentido']   ?? null,
                'tasaIdOrigen'    => $info['tasaId']    ?? null,
                'valorTasaRuta'   => isset($info['valorTasa']) ? (float) $info['valorTasa'] : null,
                'totalCLPSugerido' => $factor !== null ? round($total * $factor, 2) : null,
            ];
        }

        return [
            'desglose'         => $desglose,
            'cantidadTotal'    => $cantidadTotal,
            'totalCLPSugerido' => empty($faltanTasas) ? round($totalCLP, 2) : null,
            'faltanTasas'      => $faltanTasas,
        ];
    }

    /**
     * Crea la(s) liquidación(es). Todo o nada.
     *
     * @param array<string, mixed> $tasas Mapa moneda => tasa (CLP por 1 unidad).
     *                                    Solo se usa en modo consolidado.
     * @return array{modo: string, liquidaciones: array<int, array>}
     * @throws Exception con código HTTP en getCode() (400 / 422 / 409 / 500)
     */
    public function crear(int $userId, string $desde, string $hasta, string $modo, array $tasas = [], ?string $notas = null): array
    {
        if (!in_array($modo, self::MODOS_VALIDOS, true)) {
            throw new Exception(
                "Modo de liquidación desconocido: '{$modo}'. Los modos válidos son 'por_moneda' y 'consolidado_clp'.",
                400
            );
        }

        $conn = $this->liquidacionRepo->getConnection();
        $conn->begin_transaction();

        try {
            // Lock + cálculo DENTRO de la transacción: sin esto, dos admins
            // liquidando a la vez pagan dos veces la misma comisión.
            $porMoneda = $this->txRepository->lockResellerCommissionInRange($userId, $desde, $hasta);

            if (empty($porMoneda)) {
                throw new Exception('No hay comisiones pendientes en ese período.', 422);
            }

            $resultado = ($modo === self::MODO_POR_MONEDA)
                ? $this->crearPorMoneda($userId, $desde, $hasta, $porMoneda, $notas)
                : $this->crearConsolidado($userId, $desde, $hasta, $porMoneda, $tasas, $notas);

            $conn->commit();
            return ['modo' => $modo, 'liquidaciones' => $resultado];

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $conn->rollback();
            error_log('LiquidacionService::crear — ' . $e->getMessage());
            throw new Exception('Error interno al crear la liquidación. No se creó ninguna.', 500);
        }
    }

    /**
     * Una liquidación por moneda. Cada una marca SOLO sus propias órdenes.
     */
    private function crearPorMoneda(int $userId, string $desde, string $hasta, array $porMoneda, ?string $notas): array
    {
        $creadas = [];

        foreach ($porMoneda as $fila) {
            $moneda   = strtoupper((string) $fila['Moneda']);
            $monto    = round((float) $fila['Total'], 2);
            $cantidad = (int) $fila['Cantidad'];

            if ($monto <= 0) {
                continue;
            }

            $liqId = $this->liquidacionRepo->create(
                $userId, $monto, $desde, $hasta, $cantidad, $notas, $moneda, self::MODO_POR_MONEDA
            );

            // Tasa 1: no hay conversión, se paga en la moneda original.
            $this->liquidacionRepo->addDetalleMoneda($liqId, $moneda, $monto, 1.0, $monto, $cantidad);

            // El filtro por moneda es lo que evita que la liquidación de COP
            // se lleve puestas las órdenes en CLP.
            $afectadas = $this->txRepository->assignLiquidacionToTransactions($userId, $desde, $hasta, $liqId, $moneda);
            if ($afectadas !== $cantidad) {
                throw new Exception(
                    "Inconsistencia al asignar las órdenes en {$moneda}: se esperaban {$cantidad} y se marcaron {$afectadas}. No se creó ninguna liquidación.",
                    409
                );
            }

            $creadas[] = [
                'liquidacionId' => $liqId,
                'moneda'        => $moneda,
                'monto'         => $monto,
                'cantidad'      => $cantidad,
            ];
        }

        if (empty($creadas)) {
            throw new Exception('No hay comisiones pendientes en ese período.', 422);
        }

        return $creadas;
    }

    /**
     * Una sola liquidación en CLP, convirtiendo el resto de monedas.
     */
    private function crearConsolidado(int $userId, string $desde, string $hasta, array $porMoneda, array $tasas, ?string $notas): array
    {
        // Normalizar el mapa de tasas que mandó el admin (claves en mayúscula).
        $tasasNorm = [];
        foreach ($tasas as $k => $v) {
            $tasasNorm[strtoupper(trim((string) $k))] = $v;
        }

        $detalles      = [];
        $totalCLP      = 0.0;
        $cantidadTotal = 0;

        foreach ($porMoneda as $fila) {
            $moneda   = strtoupper((string) $fila['Moneda']);
            $monto    = round((float) $fila['Total'], 2);
            $cantidad = (int) $fila['Cantidad'];
            $cantidadTotal += $cantidad;

            if ($moneda === 'CLP') {
                // CLP a CLP es 1 por definición; no se acepta que el admin lo cambie.
                $factor = 1.0;
            } else {
                $factor = $this->resolverTasa($moneda, $tasasNorm);
            }

            $convertido = round($monto * $factor, 2);
            $totalCLP  += $convertido;

            $detalles[] = [
                'moneda'     => $moneda,
                'original'   => $monto,
                'factor'     => $factor,
                'convertido' => $convertido,
                'cantidad'   => $cantidad,
            ];
        }

        $totalCLP = round($totalCLP, 2);
        if ($totalCLP <= 0) {
            throw new Exception('El monto consolidado resultó cero o negativo. Revisá las tasas ingresadas.', 422);
        }

        $liqId = $this->liquidacionRepo->create(
            $userId, $totalCLP, $desde, $hasta, $cantidadTotal, $notas, 'CLP', self::MODO_CONSOLIDADO_CLP
        );

        foreach ($detalles as $d) {
            $this->liquidacionRepo->addDetalleMoneda(
                $liqId, $d['moneda'], $d['original'], $d['factor'], $d['convertido'], $d['cantidad']
            );
        }

        // Sin filtro de moneda a propósito: esta única liquidación cubre todas.
        $afectadas = $this->txRepository->assignLiquidacionToTransactions($userId, $desde, $hasta, $liqId, null);
        if ($afectadas !== $cantidadTotal) {
            throw new Exception(
                "Inconsistencia al asignar las órdenes: se esperaban {$cantidadTotal} y se marcaron {$afectadas}. No se creó la liquidación.",
                409
            );
        }

        return [[
            'liquidacionId' => $liqId,
            'moneda'        => 'CLP',
            'monto'         => $totalCLP,
            'cantidad'      => $cantidadTotal,
            'detalle'       => $detalles,
        ]];
    }

    /**
     * Tasa a usar para una moneda en modo consolidado.
     *
     * Prioridad: lo que escribió el admin (es el control humano que evita que
     * un error de tasa se pague en silencio). Si no mandó nada, se usa la
     * sugerida por PricingService. Si tampoco hay, se RECHAZA: no se inventa
     * ninguna tasa ni se asume 1:1.
     */
    private function resolverTasa(string $moneda, array $tasasNorm): float
    {
        if (array_key_exists($moneda, $tasasNorm)) {
            $raw = $tasasNorm[$moneda];

            if ($raw === null || $raw === '' || !is_numeric($raw)) {
                throw new Exception(
                    "La tasa de conversión para {$moneda} no es un número válido. Corregila antes de confirmar.",
                    422
                );
            }

            $factor = (float) $raw;
            if (!is_finite($factor) || $factor <= 0) {
                throw new Exception(
                    "La tasa de conversión para {$moneda} debe ser mayor que cero. Recibido: {$raw}.",
                    422
                );
            }
            if ($factor > self::MAX_TASA_CONVERSION) {
                throw new Exception(
                    "La tasa de conversión para {$moneda} es absurdamente alta (" . $factor . "). Revisala: se esperan CLP por 1 {$moneda}.",
                    422
                );
            }
            return $factor;
        }

        $info = $this->pricingService->getFactorConversionACLP($moneda);
        if ($info === null || !isset($info['factor']) || $info['factor'] <= 0) {
            throw new Exception(
                "No se puede determinar la tasa de conversión de {$moneda} a CLP: no hay ninguna ruta de tasas activa para esa moneda. Ingresá la tasa a mano o liquidá por moneda. No se creó ninguna liquidación.",
                422
            );
        }
        return (float) $info['factor'];
    }
}
