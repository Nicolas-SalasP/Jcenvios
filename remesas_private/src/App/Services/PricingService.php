<?php
namespace App\Services;

use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Services\NotificationService;
use App\Services\SystemSettingsService;
use Exception;
use Throwable;

class PricingService
{
    private RateRepository $rateRepository;
    private CountryRepository $countryRepository;
    private SystemSettingsRepository $settingsRepository;
    private NotificationService $notificationService;
    private SystemSettingsService $systemService;

    public function __construct(
        RateRepository $rateRepository,
        CountryRepository $countryRepository,
        SystemSettingsRepository $settingsRepository,
        NotificationService $notificationService,
        SystemSettingsService $systemService
    ) {
        $this->rateRepository = $rateRepository;
        $this->countryRepository = $countryRepository;
        $this->settingsRepository = $settingsRepository;
        $this->notificationService = $notificationService;
        $this->systemService = $systemService;
    }

    public function runScheduledAdjustment(): bool
    {
        $settings = $this->getGlobalAdjustmentSettings();
        $horaActual = date('H:i');
        $hoy = date('Y-m-d');

        if ($horaActual !== $settings['time']) {
            return false;
        }
        $ultimaEjecucion = $settings['last_run'] ? date('Y-m-d', strtotime($settings['last_run'])) : '';
        if ($ultimaEjecucion === $hoy) {
            return false;
        }
        return $this->applyGlobalAdjustment(0, $settings['percent']) > 0;
    }

    public function applyGlobalAdjustment(int $adminId, float $percentage): int
    {
        $status = $this->systemService->checkSystemAvailability();
        if (!$status['available']) {
            throw new Exception("Operación Bloqueada: El sistema está en modo '{$status['reason']}' ({$status['message']}). Las tasas están congeladas.");
        }

        $tasasRef = $this->rateRepository->findAllReferentialRates();
        $count = 0;

        foreach ($tasasRef as $t) {
            $valorOriginal = (float) $t['ValorTasa'];

            $nuevoValor = $valorOriginal * (1 + ($percentage / 100));

            $this->rateRepository->updateRateValue(
                (int) $t['TasaID'],
                $nuevoValor,
                (float) $t['MontoMinimo'],
                (float) $t['MontoMaximo'],
                1,
                (int) ($t['EsRiesgoso'] ?? 0),
                0
            );

            $this->recalculateRouteRates((int) $t['PaisOrigenID'], (int) $t['PaisDestinoID'], $nuevoValor);

            $origen = $t['PaisOrigenID'];
            $destino = $t['PaisDestinoID'];
            $detalleLog = "Ajuste Global ({$percentage}%): Tasa Ref ID {$t['TasaID']} (Ruta {$origen}->{$destino}) cambió de " .
                number_format($valorOriginal, 4, ',', '.') . " a " . number_format($nuevoValor, 4, ',', '.');

            $this->notificationService->logAdminAction($adminId, 'Ajuste Automático de Tasa', $detalleLog);

            $this->rateRepository->logRateChange(
                (int) $t['TasaID'],
                (int) $t['PaisOrigenID'],
                (int) $t['PaisDestinoID'],
                $nuevoValor,
                (float) $t['MontoMinimo'],
                (float) $t['MontoMaximo']
            );
            $count++;
        }

        $this->settingsRepository->updateValue('global_adjustment_last_run', date('Y-m-d H:i:s'));

        return $count;
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
            'time' => $this->settingsRepository->getValue('global_adjustment_time') ?: '20:30',
            'last_run' => $this->settingsRepository->getValue('global_adjustment_last_run')
        ];
    }

    public function saveGlobalAdjustmentSettings(int $adminId, float $percent, string $time): bool
    {
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

        if ($tasaId === 0) {
            $tasaId = $this->rateRepository->createRate($origenId, $destinoId, $valorFinal, $montoMin, $montoMax, $esReferencial, $esRiesgoso, $porcentaje);
        } else {
            $this->rateRepository->updateRateValue($tasaId, $valorFinal, $montoMin, $montoMax, $esReferencial, $esRiesgoso, $porcentaje);
        }

        if ($esReferencial === 1) {
            $this->recalculateRouteRates($origenId, $destinoId, $valorFinal);
        }

        $this->rateRepository->logRateChange($tasaId, $origenId, $destinoId, $valorFinal, $montoMin, $montoMax);
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
        $status = $this->systemService->checkSystemAvailability();
        if (!$status['available']) {
            throw new Exception("BLOQUEO AUTOMÁTICO: El sistema está en feriado ({$status['message']}). No se permite actualizar la tasa BCV.");
        }

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
        return $this->countryRepository->create($nombrePais, strtoupper($codigoMoneda), $rol) > 0;
    }

    public function adminUpdateCountry(int $adminId, int $paisId, string $nombrePais, string $codigoMoneda): bool
    {
        return $this->countryRepository->update($paisId, $nombrePais, strtoupper($codigoMoneda));
    }

    public function adminUpdateCountryRole(int $adminId, int $paisId, string $newRole): bool
    {
        return $this->countryRepository->updateRole($paisId, $newRole);
    }

    public function adminToggleCountryStatus(int $adminId, int $paisId, bool $newStatus): bool
    {
        return $this->countryRepository->updateStatus($paisId, $newStatus);
    }

    public function adminDeleteRate(int $adminId, int $tasaId): void
    {
        $this->rateRepository->delete($tasaId);
    }
}