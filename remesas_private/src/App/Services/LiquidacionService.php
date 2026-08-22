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
 *
 * AJUSTE MANUAL CON MOTIVO
 * ------------------------
 * A veces se le paga al revendedor un monto distinto al calculado: un anticipo
 * ya entregado, un descuento acordado, un redondeo. Eso se registra como un
 * DELTA sobre el monto calculado (MontoBase + MontoAjuste = Monto), NUNCA
 * deformando la tasa de conversión: un ajuste no es un tipo de cambio, y si se
 * guardara como tal el detalle por moneda afirmaría una conversión cambiaria
 * que nunca ocurrió.
 *
 *  - El ajuste va en la moneda de la PROPIA liquidación. En 'por_moneda' hay N
 *    liquidaciones y cada una lleva su propio ajuste con su propio motivo; el
 *    ajuste de COP no puede tocar la liquidación en CLP.
 *  - Ajuste distinto de cero SIN motivo se rechaza (422). Un ajuste sin motivo
 *    es exactamente el agujero de auditoría que esto viene a cerrar.
 *  - El monto final tiene que quedar mayor que cero.
 *  - Todo ajuste distinto de cero deja rastro en la bitácora (logs), con quién,
 *    cuánto y por qué, dentro de la misma transacción que crea la liquidación.
 */
class LiquidacionService
{
    public const MODO_POR_MONEDA      = 'por_moneda';
    public const MODO_CONSOLIDADO_CLP = 'consolidado_clp';

    public const MODOS_VALIDOS = [self::MODO_POR_MONEDA, self::MODO_CONSOLIDADO_CLP];

    /** Tope de cordura para la tasa de conversión (atajar dedazos con un 0 de más). */
    public const MAX_TASA_CONVERSION = 1000000.0;

    /** Mínimo de caracteres del motivo. "ok" no explica nada. */
    public const MIN_MOTIVO_AJUSTE = 5;

    /** Máximo: la columna MotivoAjuste es VARCHAR(255). Se rechaza, no se trunca. */
    public const MAX_MOTIVO_AJUSTE = 255;

    /** Tope de cordura del ajuste: DECIMAL(15,2) no aguanta más que esto. */
    public const MAX_AJUSTE_ABS = 9999999999999.99;

    private TransactionRepository $txRepository;
    private LiquidacionRepository $liquidacionRepo;
    private PricingService $pricingService;
    private ?NotificationService $notificationService;

    public function __construct(
        TransactionRepository $txRepository,
        LiquidacionRepository $liquidacionRepo,
        PricingService $pricingService,
        ?NotificationService $notificationService = null
    ) {
        $this->txRepository        = $txRepository;
        $this->liquidacionRepo     = $liquidacionRepo;
        $this->pricingService      = $pricingService;
        $this->notificationService = $notificationService;
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
     * @param array<string, mixed> $ajustes Mapa moneda => ['monto' => delta, 'motivo' => texto].
     *                                    La moneda es la de la LIQUIDACIÓN: en
     *                                    modo consolidado la única clave válida
     *                                    es CLP; en por_moneda, una por moneda.
     * @param int|null $adminId Quién hace el ajuste (para la bitácora).
     * @return array{modo: string, liquidaciones: array<int, array>}
     * @throws Exception con código HTTP en getCode() (400 / 422 / 409 / 500)
     */
    public function crear(
        int $userId,
        string $desde,
        string $hasta,
        string $modo,
        array $tasas = [],
        ?string $notas = null,
        array $ajustes = [],
        ?int $adminId = null
    ): array {
        if (!in_array($modo, self::MODOS_VALIDOS, true)) {
            throw new Exception(
                "Modo de liquidación desconocido: '{$modo}'. Los modos válidos son 'por_moneda' y 'consolidado_clp'.",
                400
            );
        }

        $ajustesNorm = $this->normalizarAjustes($ajustes);

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
                ? $this->crearPorMoneda($userId, $desde, $hasta, $porMoneda, $notas, $ajustesNorm, $adminId)
                : $this->crearConsolidado($userId, $desde, $hasta, $porMoneda, $tasas, $notas, $ajustesNorm, $adminId);

            // Un ajuste dirigido a una moneda que no terminó siendo una
            // liquidación (típico: modo consolidado con un ajuste en COP, o un
            // dedazo en el código de moneda) se estaría descartando en silencio.
            // Es plata: se rechaza y no se crea nada.
            $this->verificarAjustesAplicados($ajustesNorm, $resultado, $modo);

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
     *
     * Cada liquidación lleva su PROPIO ajuste, tomado de $ajustesNorm por su
     * moneda. Un ajuste en COP jamás toca la liquidación en CLP.
     */
    private function crearPorMoneda(int $userId, string $desde, string $hasta, array $porMoneda, ?string $notas, array $ajustesNorm, ?int $adminId): array
    {
        $creadas = [];

        foreach ($porMoneda as $fila) {
            $moneda   = strtoupper((string) $fila['Moneda']);
            $base     = round((float) $fila['Total'], 2);
            $cantidad = (int) $fila['Cantidad'];

            if ($base <= 0) {
                continue;
            }

            // El ajuste se resuelve POR MONEDA: la clave es la moneda de esta
            // liquidación, no una global.
            $ajuste = $this->resolverAjuste($moneda, $ajustesNorm, $base);
            $monto  = $ajuste['montoFinal'];

            $liqId = $this->liquidacionRepo->create(
                $userId, $monto, $desde, $hasta, $cantidad, $notas, $moneda, self::MODO_POR_MONEDA,
                $base, $ajuste['delta'], $ajuste['motivo']
            );

            // Tasa 1: no hay conversión, se paga en la moneda original.
            // El detalle guarda la comisión BASE, no el ajuste: esta tabla es el
            // rastro de la conversión cambiaria, y el ajuste no es una conversión.
            $this->liquidacionRepo->addDetalleMoneda($liqId, $moneda, $base, 1.0, $base, $cantidad);

            $this->logAjuste($adminId, $userId, $liqId, $moneda, $base, $ajuste);

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
                'montoBase'     => $base,
                'montoAjuste'   => $ajuste['delta'],
                'motivoAjuste'  => $ajuste['motivo'],
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
    private function crearConsolidado(int $userId, string $desde, string $hasta, array $porMoneda, array $tasas, ?string $notas, array $ajustesNorm, ?int $adminId): array
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

        // La liquidación es una sola y está en CLP: el ajuste también, y va
        // sobre el total ya convertido.
        $ajuste = $this->resolverAjuste('CLP', $ajustesNorm, $totalCLP);
        $monto  = $ajuste['montoFinal'];

        $liqId = $this->liquidacionRepo->create(
            $userId, $monto, $desde, $hasta, $cantidadTotal, $notas, 'CLP', self::MODO_CONSOLIDADO_CLP,
            $totalCLP, $ajuste['delta'], $ajuste['motivo']
        );

        // El detalle refleja SOLO la conversión cambiaria (suma = MontoBase).
        // El ajuste manual no se disfraza de tasa: vive en sus propias columnas.
        foreach ($detalles as $d) {
            $this->liquidacionRepo->addDetalleMoneda(
                $liqId, $d['moneda'], $d['original'], $d['factor'], $d['convertido'], $d['cantidad']
            );
        }

        $this->logAjuste($adminId, $userId, $liqId, 'CLP', $totalCLP, $ajuste);

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
            'monto'         => $monto,
            'montoBase'     => $totalCLP,
            'montoAjuste'   => $ajuste['delta'],
            'motivoAjuste'  => $ajuste['motivo'],
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

    // ─── Ajuste manual ──────────────────────────────────────────────────────

    /**
     * Pasa el mapa de ajustes crudo del request a
     * MONEDA => ['monto' => mixed, 'motivo' => mixed].
     *
     * Acepta también la forma corta MONEDA => número (ajuste sin motivo), que
     * después resolverAjuste rechaza si el número no es cero. No se valida nada
     * acá: sólo se normaliza la forma.
     */
    private function normalizarAjustes(array $ajustes): array
    {
        $norm = [];
        foreach ($ajustes as $moneda => $valor) {
            $clave = strtoupper(trim((string) $moneda));
            if ($clave === '') {
                continue;
            }
            if (is_array($valor)) {
                $norm[$clave] = [
                    'monto'  => $valor['monto']  ?? ($valor['ajuste'] ?? null),
                    'motivo' => $valor['motivo'] ?? null,
                ];
            } else {
                $norm[$clave] = ['monto' => $valor, 'motivo' => null];
            }
        }
        return $norm;
    }

    /**
     * Valida y resuelve el ajuste de UNA liquidación, en SU moneda.
     *
     * @param float $base Monto calculado por el sistema para esta liquidación.
     * @return array{delta: float, motivo: ?string, montoFinal: float}
     * @throws Exception 422 con mensaje en español para el admin.
     */
    private function resolverAjuste(string $moneda, array $ajustesNorm, float $base): array
    {
        $sinAjuste = ['delta' => 0.0, 'motivo' => null, 'montoFinal' => round($base, 2)];

        if (!array_key_exists($moneda, $ajustesNorm)) {
            return $sinAjuste;
        }

        $rawMonto  = $ajustesNorm[$moneda]['monto'];
        $rawMotivo = $ajustesNorm[$moneda]['motivo'];

        // Vacío = sin ajuste. Un input de formulario que el admin no tocó llega así.
        if ($rawMonto === null || $rawMonto === '' || $rawMonto === false) {
            $delta = 0.0;
        } elseif (!is_numeric($rawMonto)) {
            throw new Exception(
                "El ajuste manual de la liquidación en {$moneda} no es un número válido. Corregilo antes de confirmar.",
                422
            );
        } else {
            $delta = (float) $rawMonto;
        }

        if (!is_finite($delta)) {
            throw new Exception(
                "El ajuste manual de la liquidación en {$moneda} no es un número válido. Corregilo antes de confirmar.",
                422
            );
        }
        if (abs($delta) > self::MAX_AJUSTE_ABS) {
            throw new Exception(
                "El ajuste manual de la liquidación en {$moneda} es absurdamente grande. Revisalo antes de confirmar.",
                422
            );
        }

        $delta = round($delta, 2);

        // Ajuste cero: no hay nada que justificar y el motivo se descarta. Es el
        // camino del 99% de las liquidaciones.
        if ($delta === 0.0) {
            return $sinAjuste;
        }

        // Un motivo de puro espacios no es un motivo.
        $motivo = trim((string) ($rawMotivo ?? ''));

        if ($motivo === '') {
            throw new Exception(
                "El motivo del ajuste es obligatorio: ingresaste un ajuste de {$delta} en la liquidación en {$moneda} sin explicar por qué. No se creó ninguna liquidación.",
                422
            );
        }
        if (mb_strlen($motivo) < self::MIN_MOTIVO_AJUSTE) {
            throw new Exception(
                "El motivo del ajuste en {$moneda} es demasiado corto (mínimo " . self::MIN_MOTIVO_AJUSTE . " caracteres). Explicá de dónde sale el ajuste.",
                422
            );
        }
        if (mb_strlen($motivo) > self::MAX_MOTIVO_AJUSTE) {
            throw new Exception(
                "El motivo del ajuste en {$moneda} es demasiado largo (máximo " . self::MAX_MOTIVO_AJUSTE . " caracteres).",
                422
            );
        }

        $montoFinal = round($base + $delta, 2);

        if ($montoFinal <= 0) {
            throw new Exception(
                "El ajuste deja la liquidación en {$moneda} en un monto final de {$montoFinal}. El monto a pagar tiene que ser mayor que cero. No se creó ninguna liquidación.",
                422
            );
        }

        return ['delta' => $delta, 'motivo' => $motivo, 'montoFinal' => $montoFinal];
    }

    /**
     * Ningún ajuste distinto de cero puede quedar sin aplicarse.
     *
     * @param array<int, array> $creadas Liquidaciones creadas (cada una con 'moneda').
     */
    private function verificarAjustesAplicados(array $ajustesNorm, array $creadas, string $modo): void
    {
        $monedasCreadas = array_map(fn($l) => strtoupper((string) $l['moneda']), $creadas);

        foreach ($ajustesNorm as $moneda => $a) {
            $raw = $a['monto'];
            if ($raw === null || $raw === '' || $raw === false || !is_numeric($raw) || round((float) $raw, 2) === 0.0) {
                continue;
            }
            if (!in_array($moneda, $monedasCreadas, true)) {
                $lista = $monedasCreadas ? implode(', ', $monedasCreadas) : 'ninguna';
                throw new Exception(
                    "Enviaste un ajuste manual en {$moneda}, pero en modo '{$modo}' no se creó ninguna liquidación en esa moneda (se crearon en: {$lista}). El ajuste va en la moneda de la liquidación. No se creó ninguna liquidación.",
                    422
                );
            }
        }
    }

    /**
     * Rastro en bitácora del ajuste manual. Sin ajuste no se loguea nada: la
     * liquidación normal no es una acción que haga falta justificar.
     *
     * Va DENTRO de la transacción de crear(): si la creación se revierte, el
     * log se revierte con ella y no queda un rastro de algo que nunca pasó.
     *
     * @param array{delta: float, motivo: ?string, montoFinal: float} $ajuste
     */
    private function logAjuste(?int $adminId, int $userId, int $liqId, string $moneda, float $base, array $ajuste): void
    {
        if ($ajuste['delta'] === 0.0 || $this->notificationService === null) {
            return;
        }

        $fmt = fn(float $n) => number_format($n, 2, ',', '.');

        $this->notificationService->logAdminAction(
            $adminId,
            'Admin ajustó monto de liquidación',
            sprintf(
                'Liquidación #%d (revendedor UserID %d) en %s — Base: %s · Ajuste: %s%s · Final: %s · Motivo: %s',
                $liqId,
                $userId,
                $moneda,
                $fmt($base),
                $ajuste['delta'] > 0 ? '+' : '-',
                $fmt(abs($ajuste['delta'])),
                $fmt($ajuste['montoFinal']),
                (string) $ajuste['motivo']
            )
        );
    }
}
