<?php
namespace App\Services;

use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Repositories\TasasImagenRepository;
use App\Services\NotificationService;
use App\Services\SystemSettingsService;
use App\Services\FileHandlerService;
use Exception;
use Throwable;

class PricingService
{
    /**
     * Cota máxima (en valor absoluto) para el ajuste global porcentual.
     *
     * Un ajuste de tasa de cambio realista es de pocos puntos porcentuales;
     * 20% es holgado de sobra y bloquea los casos catastróficos: percent = -100
     * dejaba TODAS las tasas en 0 y percent < -100 las dejaba negativas, sin
     * forma automática de revertirlo (es dinero real).
     */
    public const MAX_AJUSTE_PORCENTUAL = 20.0;

    /**
     * Cota superior para la tasa BCV (Bs/USD). Es un valor que crece con la
     * inflación, así que el techo es deliberadamente muy alto: sólo está para
     * atajar dedazos (un 0 de más) y valores absurdos. El piso, en cambio, es
     * estricto: la tasa debe ser > 0.
     */
    public const MAX_TASA_BCV = 10000000.0;

    /** Hora por defecto del ajuste automático Lunes a Viernes (fallback). */
    private const HORA_AJUSTE_DEFAULT_LV = '19:30';

    /**
     * Hora del ajuste automático los sábados. Sigue hardcodeada porque NO existe
     * un setting separado para el sábado en system_settings (sólo hay
     * 'global_adjustment_time'), y no se inventa uno acá.
     */
    private const HORA_AJUSTE_SABADO = '16:00';

    private RateRepository $rateRepository;
    private CountryRepository $countryRepository;
    private SystemSettingsRepository $settingsRepository;
    private NotificationService $notificationService;
    private SystemSettingsService $systemService;
    private TasasImagenRepository $tasasImagenRepository;
    private FileHandlerService $fileHandler;

    public function __construct(
        RateRepository $rateRepository,
        CountryRepository $countryRepository,
        SystemSettingsRepository $settingsRepository,
        NotificationService $notificationService,
        SystemSettingsService $systemService,
        TasasImagenRepository $tasasImagenRepository,
        FileHandlerService $fileHandler
    ) {
        $this->rateRepository = $rateRepository;
        $this->countryRepository = $countryRepository;
        $this->settingsRepository = $settingsRepository;
        $this->notificationService = $notificationService;
        $this->systemService = $systemService;
        $this->tasasImagenRepository = $tasasImagenRepository;
        $this->fileHandler = $fileHandler;
    }

    /**
     * ¿Corresponde ejecutar hoy el ajuste global? Función pura: recibe el
     * "ahora" y la configuración, no consulta nada. Está separada de
     * runScheduledAdjustment() justamente para poder testear la decisión con
     * cualquier día/hora sin depender del reloj del que corre los tests.
     *
     * @param string      $fechaHoraActual 'Y-m-d H:i:s' (o cualquier cosa parseable por strtotime)
     * @param string      $horaConfigurada Hora objetivo Lun-Vie ('HH:MM'), desde el panel
     * @param string|null $lastRun         Último ajuste aplicado ('Y-m-d H:i:s') o null
     */
    public function shouldRunScheduledAdjustment(string $fechaHoraActual, string $horaConfigurada, ?string $lastRun): bool
    {
        $ts = strtotime($fechaHoraActual);
        if ($ts === false) {
            return false;
        }
        $horaActual = date('H:i', $ts);
        $diaSemana  = (int) date('N', $ts);
        $hoy        = date('Y-m-d', $ts);

        // === LÓGICA DE HORARIO DINÁMICO SEGÚN EL DÍA ===
        if ($diaSemana >= 1 && $diaSemana <= 5) {
            // Hora configurable desde el panel ('global_adjustment_time'). Antes
            // estaba hardcodeada y el setting era decorativo: se guardaba pero
            // nunca se leía.
            $horaTarget = $this->normalizeAdjustmentTime($horaConfigurada, self::HORA_AJUSTE_DEFAULT_LV);
        } elseif ($diaSemana === 6) {
            $horaTarget = self::HORA_AJUSTE_SABADO;
        } else {
            return false; // Domingo.
        }

        // VENTANA, no minuto exacto. El cron corre cada 15 minutos; comparar
        // date('H:i') !== $horaTarget hacía que un atraso de un minuto por carga
        // del servidor se comiera el ajuste del día entero, en silencio.
        // Con la ventana basta con que ya haya pasado la hora objetivo de hoy;
        // el claim atómico de last_run es lo que impide que se repita.
        if ($horaActual < $horaTarget) {
            return false;
        }

        $ultimaEjecucion = $lastRun ? date('Y-m-d', strtotime($lastRun)) : '';
        return $ultimaEjecucion !== $hoy;
    }

    public function runScheduledAdjustment(): bool
    {
        $settings = $this->getGlobalAdjustmentSettings();

        if (!$this->shouldRunScheduledAdjustment(date('Y-m-d H:i:s'), (string) $settings['time'], $settings['last_run'])) {
            return false;
        }

        // Claim ATÓMICO del día. Antes esto era un read-then-write: se leía
        // last_run acá y recién se escribía al final de applyGlobalAdjustment.
        // Dos instancias solapadas leían ambas la fecha de ayer, ambas pasaban
        // el guard, y el ajuste se aplicaba DOS VECES en cascada sobre ValorTasa
        // (un 2% se volvía 4,04% sobre todas las rutas). El cron ya tiene lock
        // de archivo, pero esto lo blinda también entre servidores/procesos que
        // no compartan el filesystem.
        if (!$this->settingsRepository->claimDailyRun('global_adjustment_last_run', date('Y-m-d H:i:s'))) {
            return false;
        }

        try {
            // El freeze por feriado/sistema bloqueado aplica SOLO al cron: el
            // admin puede seguir editando tasas a mano (BCV, individuales o
            // ajuste global manual) aunque el sistema esté bloqueado — es su
            // propia acción, no la automática. Por eso el chequeo vive acá y
            // no dentro de applyGlobalAdjustment(), que también usa el botón
            // "Aplicar ahora" del panel.
            $status = $this->systemService->checkSystemAvailability();
            if (!$status['available']) {
                throw new Exception("Operación Bloqueada: El sistema está en modo '{$status['reason']}' ({$status['message']}). Las tasas están congeladas.", 403);
            }

            $aplicado = $this->applyGlobalAdjustment(1, $settings['percent']) > 0;
        } catch (Throwable $e) {
            // Falló de entrada (feriado, sistema bloqueado, porcentaje inválido):
            // devolvemos last_run a su valor anterior para que el próximo tick
            // del cron pueda reintentar hoy mismo.
            $this->settingsRepository->updateValue('global_adjustment_last_run', (string) ($settings['last_run'] ?? ''));
            throw $e;
        }

        if ($aplicado) {
            $this->clearTasasImagenGaleria();
        } else {
            // No se ajustó ninguna ruta: liberamos el día para poder reintentar.
            $this->settingsRepository->updateValue('global_adjustment_last_run', (string) ($settings['last_run'] ?? ''));
        }

        return $aplicado;
    }

    /**
     * Valida y normaliza una hora 'HH:MM' de 24 horas. Si el setting no existe o
     * está corrupto, devuelve el fallback en vez de romper el cron.
     */
    private function normalizeAdjustmentTime(?string $time, string $fallback): string
    {
        $time = trim((string) $time);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            return $time;
        }
        if ($time !== '') {
            error_log("PricingService: 'global_adjustment_time' inválido ('{$time}'), usando {$fallback}.");
        }
        return $fallback;
    }

    // El Ajuste Global automático corre fuera de horario laboral (19:30 Lun-Vie,
    // 16:00 Sáb). Las imágenes de Tasas Visuales quedan obsoletas apenas cambian
    // las tasas, por eso se limpian junto con el ajuste automático (no en el
    // ajuste manual desde el panel, que usa applyGlobalAdjustment() directo).
    private function clearTasasImagenGaleria(): void
    {
        $rutas = $this->tasasImagenRepository->deleteAll();
        foreach ($rutas as $ruta) {
            $this->fileHandler->deleteTasaImagen($ruta);
        }
    }

    public function applyGlobalAdjustment(int $adminId, float $percentage): int
    {
        // Blindaje extra contra domingos.
        // runScheduledAdjustment() ya filtra día semana, pero applyGlobalAdjustment
        // puede llamarse desde cualquier punto (cron mal configurado, llamada manual).
        // Política negocio: ajustes solo Lun-Sáb. Domingo (date('N') === 7) bloqueado.
        $diaSemana = (int) date('N');
        if ($diaSemana === 7) {
            throw new Exception("Los ajustes automáticos de tasa están deshabilitados los domingos por política comercial.", 403);
        }

        $this->validateAdjustmentPercentage($percentage);

        $tasasRef = $this->rateRepository->findAllReferentialRates();
        $count = 0;
        $fallidas = 0;

        foreach ($tasasRef as $t) {
            try {
                $valorOriginal = (float) $t['ValorTasa'];
                $origen = (int) $t['PaisOrigenID'];
                $destino = (int) $t['PaisDestinoID'];
                $modo = $this->getCalculationMode($origen, $destino);
                $porcentajeAplicar = ($modo === 'divide') ? ($percentage * -1) : $percentage;
                $nuevoValor = $valorOriginal * (1 + ($porcentajeAplicar / 100));

                // Blindaje final: nunca escribir una tasa 0 o negativa, pase lo
                // que pase con el porcentaje o con el valor que había en la BD.
                if (!is_finite($nuevoValor) || $nuevoValor <= 0) {
                    throw new Exception(
                        "El ajuste dejaría la tasa en un valor inválido (" . $nuevoValor . "). Operación cancelada para esta ruta.",
                        422
                    );
                }

                $this->rateRepository->updateRateValue(
                    (int) $t['TasaID'],
                    $nuevoValor,
                    (float) $t['MontoMinimo'],
                    (float) $t['MontoMaximo'],
                    1,
                    (int) ($t['EsRiesgoso'] ?? 0),
                    0
                );

                $this->recalculateRouteRates($origen, $destino, $nuevoValor);

                $nombreOrigen = $this->countryRepository->findNameById($origen) ?? "País ID $origen";
                $nombreDestino = $this->countryRepository->findNameById($destino) ?? "País ID $destino";
                $detalleLog = "Ajuste Global ({$percentage}%): Ruta {$nombreOrigen} → {$nombreDestino} cambió de " .
                    number_format($valorOriginal, 4, ',', '.') . " a " . number_format($nuevoValor, 4, ',', '.');

                $this->notificationService->logAdminAction($adminId, 'Ajuste Automático de Tasa', $detalleLog);

                $this->rateRepository->logRateChange(
                    (int) $t['TasaID'],
                    $origen,
                    $destino,
                    $nuevoValor,
                    (float) $t['MontoMinimo'],
                    (float) $t['MontoMaximo']
                );
                $count++;

            } catch (Throwable $e) {
                // Throwable y no Exception: un Error de PHP 8 acá abortaba el
                // ajuste entero sin que este catch lo viera.
                $fallidas++;
                error_log("Error en Cron Ajuste Global (Tasa ID {$t['TasaID']}): " . $e->getMessage());
                continue;
            }
        }

        // NO se envuelve el foreach en una transacción a propósito:
        // PricingService no recibe la conexión (sólo repositorios), así que
        // meterla implicaría cambiar el constructor y, con él, los DOS
        // contenedores de DI del proyecto (api/index.php y src/core/init.php),
        // que ya causaron varios bugs al desincronizarse. El riesgo grave
        // (aplicar el ajuste dos veces) queda cubierto por el lock del cron y
        // por claimDailyRun(). Lo que sí se corrige es el silencio: un fallo
        // parcial ahora queda registrado en la bitácora para que un admin lo
        // revise a mano. No se reintenta automáticamente porque reintentar
        // volvería a ajustar las rutas que SÍ se aplicaron (doble ajuste).
        if ($fallidas > 0) {
            $this->notificationService->logAdminAction(
                $adminId,
                'Ajuste Global PARCIAL',
                "Ajuste del {$percentage}%: {$count} ruta(s) actualizada(s), {$fallidas} fallaron. Revisar manualmente: las tasas quedaron inconsistentes entre sí."
            );
            error_log("AJUSTE GLOBAL PARCIAL: {$count} ok, {$fallidas} fallidas. Revisión manual requerida.");
        }

        $this->settingsRepository->updateValue('global_adjustment_last_run', date('Y-m-d H:i:s'));

        return $count;
    }

    /**
     * Valida que el porcentaje de ajuste global esté dentro de un rango sensato.
     */
    private function validateAdjustmentPercentage(float $percentage): void
    {
        if (!is_finite($percentage)) {
            throw new Exception("El porcentaje de ajuste no es un número válido.", 400);
        }
        if ($percentage == 0.0) {
            throw new Exception("El porcentaje de ajuste no puede ser 0: no habría nada que ajustar.", 400);
        }
        if (abs($percentage) > self::MAX_AJUSTE_PORCENTUAL) {
            throw new Exception(
                "El porcentaje de ajuste debe estar entre -" . self::MAX_AJUSTE_PORCENTUAL .
                "% y " . self::MAX_AJUSTE_PORCENTUAL . "%. Recibido: {$percentage}%.",
                400
            );
        }
    }

    private function recalculateRouteRates(int $origenId, int $destinoId, float $valorBase): void
    {
        $tasas = $this->rateRepository->getRatesByRoute($origenId, $destinoId);
        foreach ($tasas as $t) {
            if ($t['EsReferencial'] == 1)
                continue;

            $nuevoValorComercial = $valorBase * (1 + ($t['PorcentajeAjuste'] / 100));

            $this->rateRepository->updateRateValue(
                (int) $t['TasaID'],
                $nuevoValorComercial,
                (float) $t['MontoMinimo'],
                (float) $t['MontoMaximo'],
                0,
                (int) ($t['EsRiesgoso'] ?? 0),
                (float) $t['PorcentajeAjuste']
            );
        }
    }

    public function getGlobalAdjustmentSettings(): array
    {
        return [
            'percent' => (float) $this->settingsRepository->getValue('global_adjustment_percent'),
            // Fallback = la hora que el cron realmente usa. Antes decía '20:30'
            // mientras el cron ajustaba a las 19:30 hardcodeadas: el panel
            // mostraba una hora que no era la real.
            'time' => $this->settingsRepository->getValue('global_adjustment_time') ?: self::HORA_AJUSTE_DEFAULT_LV,
            'last_run' => $this->settingsRepository->getValue('global_adjustment_last_run')
        ];
    }

    public function saveGlobalAdjustmentSettings(int $adminId, float $percent, string $time): bool
    {
        // El porcentaje guardado acá es el que usa el cron sin intervención
        // humana: validarlo al guardar evita dejar armada una bomba de tiempo.
        $this->validateAdjustmentPercentage($percent);

        $time = trim($time);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new Exception("La hora del ajuste global debe tener el formato HH:MM (24 horas). Recibido: '{$time}'.", 400);
        }

        $this->settingsRepository->updateValue('global_adjustment_percent', (string) $percent);
        $this->settingsRepository->updateValue('global_adjustment_time', $time);
        $this->notificationService->logAdminAction($adminId, 'Configuración Ajuste Global', "Porcentaje: {$percent}%, Hora: {$time}");
        return true;
    }

    private function getCalculationMode(int $origenId, int $destinoId): string
    {
        $inverseRoutes = [
            '2-3', // Col -> Ven
            '4-1', // Peru -> Chile
            '2-1', // Col -> Chile
            '3-1', // Ven -> Chile
            '3-4',  // Ven -> Peru
        ];

        $routeKey = "{$origenId}-{$destinoId}";
        return in_array($routeKey, $inverseRoutes) ? 'divide' : 'multiply';
    }

    public function getCurrentRate(int $origenID, int $destinoID, float $montoOrigen = 0): array
    {
        if ($origenID === $destinoID) {
            throw new Exception("El país de origen y destino no pueden ser iguales.", 400);
        }

        if ($montoOrigen == 0) {
            $tasaInfo = $this->rateRepository->findReferentialRate($origenID, $destinoID);
            if (!$tasaInfo) {
                throw new Exception("Esta ruta no tiene una Tasa Referencial configurada.", 404);
            }
        } else {
            $tasaInfo = $this->rateRepository->findCurrentRate($origenID, $destinoID, $montoOrigen);
            if (!$tasaInfo) {
                $limits = $this->rateRepository->getRouteLimits($origenID, $destinoID);
                throw new Exception("No existe una tasa configurada para esta ruta.", 404);
            }
        }
        $tasaInfo['operation'] = $this->getCalculationMode($origenID, $destinoID);

        return $tasaInfo;
    }

    /**
     * Factor de conversión "CLP por 1 unidad de $moneda", para liquidar
     * comisiones de revendedor en modo consolidado.
     *
     * Devuelve null si NO se puede determinar una tasa. Nunca inventa una tasa
     * ni asume 1:1: si esto devuelve null, el llamador tiene que exigirle al
     * admin que la escriba a mano, o rechazar la operación. Es dinero real.
     *
     * CRITERIO DE SELECCIÓN cuando la ruta tiene varias tasas activas
     * (ej. Chile→Colombia tiene 3.42 para montos < 300.000 y 3.43778 para el
     * resto, escalonadas por MontoMinimo/MontoMaximo):
     *   se usa la TASA REFERENCIAL de la ruta (EsReferencial = 1 AND Activa = 1),
     *   que es exactamente lo que devuelve getCurrentRate($o, $d, 0) vía
     *   findReferentialRate(), y es única por ruta por construcción
     *   (adminUpsertRate llama a clearReferentialFlag antes de marcar una nueva).
     * Razones para no usar las tasas escalonadas:
     *   1. Los rangos MontoMinimo/MontoMaximo están expresados en la moneda de
     *      ORIGEN DE LA RUTA. Para una ruta invertida (ver abajo) el monto que
     *      queremos convertir NO está en esa moneda, así que el escalón elegido
     *      sería arbitrario.
     *   2. La referencial es la tasa base sin el margen comercial
     *      (PorcentajeAjuste), que es lo correcto para pagarle a un revendedor:
     *      el margen es ganancia del negocio, no parte del tipo de cambio.
     *
     * RUTA DIRECTA vs INVERTIDA: hoy no existe ninguna ruta activa hacia Chile
     * (COP→CLP y PEN→CLP no existen o están con Activa = 0). Por eso, si no hay
     * ruta directa Moneda→CLP, se busca la inversa CLP→Moneda y se invierte la
     * operación: si la ruta directa multiplica, la inversa divide y viceversa
     * (getCalculationMode ya codifica qué rutas se dividen).
     *
     * @return array{factor: float, tasaId: int, valorTasa: float, sentido: string, paisId: int}|null
     */
    public function getFactorConversionACLP(string $moneda): ?array
    {
        $moneda = strtoupper(trim($moneda));
        if ($moneda === '') {
            return null;
        }
        if ($moneda === 'CLP') {
            return ['factor' => 1.0, 'tasaId' => 0, 'valorTasa' => 1.0, 'sentido' => 'identidad', 'paisId' => 1];
        }

        $paisId = $this->countryRepository->findIdByMoneda($moneda);
        if ($paisId === null || $paisId === 1) {
            return null;
        }

        // 1) Ruta directa Moneda → CLP.
        $directa = $this->rateRepository->findReferentialRate($paisId, 1);
        if ($directa && (int) ($directa['RutaActiva'] ?? 1) === 1 && (float) $directa['ValorTasa'] > 0) {
            $valor = (float) $directa['ValorTasa'];
            $factor = ($this->getCalculationMode($paisId, 1) === 'divide') ? (1 / $valor) : $valor;
            return [
                'factor'    => $factor,
                'tasaId'    => (int) $directa['TasaID'],
                'valorTasa' => $valor,
                'sentido'   => 'directa',
                'paisId'    => $paisId,
            ];
        }

        // 2) Ruta inversa CLP → Moneda, con la operación dada vuelta.
        $inversa = $this->rateRepository->findReferentialRate(1, $paisId);
        if ($inversa && (int) ($inversa['RutaActiva'] ?? 1) === 1 && (float) $inversa['ValorTasa'] > 0) {
            $valor = (float) $inversa['ValorTasa'];
            // Ruta CLP→Moneda 'multiply' significa monto_moneda = monto_clp * valor,
            // por lo tanto monto_clp = monto_moneda / valor.
            $factor = ($this->getCalculationMode(1, $paisId) === 'divide') ? $valor : (1 / $valor);
            return [
                'factor'    => $factor,
                'tasaId'    => (int) $inversa['TasaID'],
                'valorTasa' => $valor,
                'sentido'   => 'inversa',
                'paisId'    => $paisId,
            ];
        }

        return null;
    }

    public function adminUpsertRate(int $adminId, array $data): array
    {
        $tasaId = ($data['tasaId'] === 'new') ? 0 : (int) $data['tasaId'];
        $origenId = (int) $data['origenId'];
        $destinoId = (int) $data['destinoId'];
        $esReferencial = (int) ($data['esReferencial'] ?? 0);
        $esRiesgoso = (int) ($data['esRiesgoso'] ?? 0);
        $porcentaje = (float) ($data['porcentaje'] ?? 0);
        $valorEntrada = (float) ($data['nuevoValor'] ?? 0);
        $montoMin = (float) ($data['montoMin'] ?? 0);
        $montoMax = (float) ($data['montoMax'] ?? 9999999999.99);

        if ($this->rateRepository->checkOverlap($origenId, $destinoId, $montoMin, $montoMax, $tasaId)) {
            throw new Exception("El rango de montos colisiona con otra tasa activa.", 409);
        }

        if ($esReferencial === 1) {
            $this->rateRepository->clearReferentialFlag($origenId, $destinoId);
            $valorFinal = $valorEntrada;
            $porcentaje = 0;
        } else {
            $ref = $this->rateRepository->findReferentialRate($origenId, $destinoId);
            if (!$ref)
                throw new Exception("Cree una Tasa Referencial para esta ruta primero.", 400);
            $valorFinal = $ref['ValorTasa'] * (1 + ($porcentaje / 100));
        }

        $esNueva = ($tasaId === 0);
        if ($esNueva) {
            $tasaId = $this->rateRepository->createRate($origenId, $destinoId, $valorFinal, $montoMin, $montoMax, $esReferencial, $esRiesgoso, $porcentaje);
        } else {
            $this->rateRepository->updateRateValue($tasaId, $valorFinal, $montoMin, $montoMax, $esReferencial, $esRiesgoso, $porcentaje);
        }

        if ($esReferencial === 1) {
            $this->recalculateRouteRates($origenId, $destinoId, $valorFinal);
        }

        $this->rateRepository->logRateChange($tasaId, $origenId, $destinoId, $valorFinal, $montoMin, $montoMax);

        $nombreOrigen = $this->countryRepository->findNameById($origenId) ?? "País ID $origenId";
        $nombreDestino = $this->countryRepository->findNameById($destinoId) ?? "País ID $destinoId";
        $tipoTasa = $esReferencial === 1 ? 'Referencial' : 'Comercial';
        $accion = $esNueva ? 'Admin creó tasa' : 'Admin editó tasa';
        $this->notificationService->logAdminAction($adminId, $accion, "Ruta {$nombreOrigen} → {$nombreDestino} ({$tipoTasa}): nuevo valor " . number_format($valorFinal, 4, ',', '.'));

        return [
            'TasaID' => $tasaId,
            'routeKey' => $origenId . '-' . $destinoId,
            'items' => $this->rateRepository->getRatesByRoute($origenId, $destinoId)
        ];
    }

    public function getBcvRate(): float
    {
        $val = $this->settingsRepository->getValue('tasa_dolar_bcv');
        return $val ? (float) $val : 0.00;
    }

    public function updateBcvRate(int $adminId, float $newValue): bool
    {
        if (!is_finite($newValue) || $newValue <= 0) {
            throw new Exception("La tasa BCV debe ser un número mayor que 0.", 400);
        }
        if ($newValue > self::MAX_TASA_BCV) {
            throw new Exception(
                "La tasa BCV supera el máximo permitido (" . number_format(self::MAX_TASA_BCV, 0, ',', '.') . "). Verifica el valor ingresado.",
                400
            );
        }

        // Sin chequeo de feriado/bloqueo a propósito: el admin puede seguir
        // ajustando la tasa BCV aunque haya bloqueado el sistema — es su
        // propia acción, no queda congelada como el ajuste automático.
        $success = $this->settingsRepository->updateValue('tasa_dolar_bcv', (string) $newValue);
        if ($success)
            $this->notificationService->logAdminAction($adminId, 'Actualización Tasa BCV', "Nuevo: " . $newValue);
        return $success;
    }

    public function getCountriesByRole(string $role): array
    {
        return $this->countryRepository->findByRoleAndStatus($role, true);
    }

    public function adminAddCountry(int $adminId, string $nombrePais, string $codigoMoneda, string $rol): bool
    {
        $creado = $this->countryRepository->create($nombrePais, strtoupper($codigoMoneda), $rol) > 0;
        if ($creado) {
            $this->notificationService->logAdminAction($adminId, 'Admin agregó país', "{$nombrePais} ({$codigoMoneda}), Rol: {$rol}");
        }
        return $creado;
    }

    public function adminUpdateCountry(int $adminId, int $paisId, string $nombrePais, string $codigoMoneda): bool
    {
        $nombreAnterior = $this->countryRepository->findNameById($paisId) ?? "País ID $paisId";
        $actualizado = $this->countryRepository->update($paisId, $nombrePais, strtoupper($codigoMoneda));
        if ($actualizado) {
            $this->notificationService->logAdminAction($adminId, 'Admin editó país', "{$nombreAnterior} → {$nombrePais} ({$codigoMoneda})");
        }
        return $actualizado;
    }

    public function adminUpdateCountryRole(int $adminId, int $paisId, string $newRole): bool
    {
        $nombrePais = $this->countryRepository->findNameById($paisId) ?? "País ID $paisId";
        $actualizado = $this->countryRepository->updateRole($paisId, $newRole);
        if ($actualizado) {
            $this->notificationService->logAdminAction($adminId, 'Admin cambió rol de país', "{$nombrePais}, Nuevo Rol: {$newRole}");
        }
        return $actualizado;
    }

    public function adminToggleCountryStatus(int $adminId, int $paisId, bool $newStatus): bool
    {
        $nombrePais = $this->countryRepository->findNameById($paisId) ?? "País ID $paisId";
        $actualizado = $this->countryRepository->updateStatus($paisId, $newStatus);
        if ($actualizado) {
            $estadoTexto = $newStatus ? 'Activado' : 'Desactivado';
            $this->notificationService->logAdminAction($adminId, 'Admin cambió estado de país', "{$nombrePais}: {$estadoTexto}");
        }
        return $actualizado;
    }

    public function adminDeleteRate(int $adminId, int $tasaId): void
    {
        $tasa = $this->rateRepository->findById($tasaId);
        $this->rateRepository->delete($tasaId);
        if ($tasa) {
            $nombreOrigen = $this->countryRepository->findNameById((int) $tasa['PaisOrigenID']) ?? "País ID {$tasa['PaisOrigenID']}";
            $nombreDestino = $this->countryRepository->findNameById((int) $tasa['PaisDestinoID']) ?? "País ID {$tasa['PaisDestinoID']}";
            $this->notificationService->logAdminAction($adminId, 'Admin eliminó tasa', "Ruta {$nombreOrigen} → {$nombreDestino} (valor {$tasa['ValorTasa']})");
        }
    }

    public function adminToggleRouteActive(int $adminId, int $origenId, int $destinoId, bool $active): bool
    {
        if ($origenId <= 0 || $destinoId <= 0) {
            throw new Exception("Origen/Destino inválidos.", 400);
        }
        if ($origenId === $destinoId) {
            throw new Exception("Origen y destino no pueden ser iguales.", 400);
        }
        $affected = $this->rateRepository->toggleRouteActive($origenId, $destinoId, $active ? 1 : 0);
        if ($affected === 0) {
            throw new Exception("No existe una tasa referencial para esta ruta. Crearla primero.", 404);
        }
        $nombreOrigen = $this->countryRepository->findNameById($origenId) ?? "País ID $origenId";
        $nombreDestino = $this->countryRepository->findNameById($destinoId) ?? "País ID $destinoId";
        $estadoTexto = $active ? 'Activada' : 'Desactivada';
        $this->notificationService->logAdminAction($adminId, 'Admin cambió estado de ruta', "Ruta {$nombreOrigen} → {$nombreDestino}: {$estadoTexto}");
        return true;
    }
}