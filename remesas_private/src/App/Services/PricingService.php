<?php
namespace App\Services;

use App\Repositories\RateRepository;
use App\Repositories\CountryRepository;
use App\Repositories\SystemSettingsRepository;
use App\Services\NotificationService;
use Exception;

class PricingService
{
    private RateRepository $rateRepository;
    private CountryRepository $countryRepository;
    private SystemSettingsRepository $settingsRepository;
    private NotificationService $notificationService;

    public function __construct(
        RateRepository $rateRepository,
        CountryRepository $countryRepository,
        SystemSettingsRepository $settingsRepository,
        NotificationService $notificationService
    ) {
        $this->rateRepository = $rateRepository;
        $this->countryRepository = $countryRepository;
        $this->settingsRepository = $settingsRepository;
        $this->notificationService = $notificationService;
    }

    public function getCountriesByRole(string $role): array
    {
        return $this->countryRepository->findByRoleAndStatus($role, true);
    }

    public function getCurrentRate(int $origenID, int $destinoID, float $montoOrigen = 0): array
    {
        if ($origenID === $destinoID) {
            throw new Exception("El país de origen y destino no pueden ser iguales.", 400);
        }
 
        if ($montoOrigen == 0) {
            $tasaInfo = $this->rateRepository->findReferentialRate($origenID, $destinoID);
            if ($tasaInfo)
                return $tasaInfo;
            throw new Exception("Esta ruta no tiene una Tasa Referencial configurada.", 404);
        }

        $tasaInfo = $this->rateRepository->findCurrentRate($origenID, $destinoID, $montoOrigen);

        if (!$tasaInfo) {
            $limits = $this->rateRepository->getRouteLimits($origenID, $destinoID);
            if ($montoOrigen > 0) {
                if ($limits['min'] > 0 && $montoOrigen < $limits['min']) {
                    throw new Exception("Monto inferior al mínimo permitido (" . number_format($limits['min'], 2, ',', '.') . ").", 400);
                }
                if ($limits['max'] > 0 && $montoOrigen > $limits['max']) {
                    throw new Exception("Monto excede el máximo permitido (" . number_format($limits['max'], 2, ',', '.') . ").", 400);
                }
            }
            throw new Exception("No existe una tasa configurada para esta ruta.", 404);
        }

        return $tasaInfo;
    }

    public function adminUpsertRate(int $adminId, array $data): array
    {
        $tasaId = ($data['tasaId'] === 'new') ? 0 : (int)$data['tasaId'];
        $origenId = (int)$data['origenId'];
        $destinoId = (int)$data['destinoId'];
        $esReferencial = (int)($data['esReferencial'] ?? 0);
        $porcentaje = (float)($data['porcentaje'] ?? 0);
        $valorEntrada = (float)($data['nuevoValor'] ?? 0);
        $montoMin = (float)($data['montoMin'] ?? 0);
        $montoMax = (float)($data['montoMax'] ?? 9999999999.99);

        if ($this->rateRepository->checkOverlap($origenId, $destinoId, $montoMin, $montoMax, $tasaId)) {
            throw new Exception("El rango de montos colisiona con otra tasa activa.", 409);
        }

        if ($esReferencial === 1) {
            $this->rateRepository->clearReferentialFlag($origenId, $destinoId);
            $valorFinal = $valorEntrada;
            $porcentaje = 0;
        } else {
            $ref = $this->rateRepository->findReferentialRate($origenId, $destinoId);
            if (!$ref) throw new Exception("Cree una Tasa Referencial para esta ruta primero.", 400);
            $valorFinal = $ref['ValorTasa'] * (1 + ($porcentaje / 100));
        }

        if ($tasaId === 0) {
            $tasaId = $this->rateRepository->createRate($origenId, $destinoId, $valorFinal, $montoMin, $montoMax, $esReferencial, $porcentaje);
        } else {
            $this->rateRepository->updateRateValue($tasaId, $valorFinal, $montoMin, $montoMax, $esReferencial, $porcentaje);
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

    private function recalculateRouteRates(int $origenId, int $destinoId, float $valorBase): void
    {
        $tasas = $this->rateRepository->getRatesByRoute($origenId, $destinoId);
        foreach ($tasas as $t) {
            if ($t['EsReferencial'] == 1)
                continue;
            $nuevoValor = $valorBase * (1 + ($t['PorcentajeAjuste'] / 100));
            $this->rateRepository->updateRateValue((int) $t['TasaID'], $nuevoValor, (float) $t['MontoMinimo'], (float) $t['MontoMaximo'], 0, (float) $t['PorcentajeAjuste']);
        }
    }

    public function getBcvRate(): float
    {
        $val = $this->settingsRepository->getValue('tasa_dolar_bcv');
        return $val ? (float) $val : 0.00;
    }

    public function updateBcvRate(int $adminId, float $newValue): bool
    {
        $success = $this->settingsRepository->updateValue('tasa_dolar_bcv', (string) $newValue);
        if ($success)
            $this->notificationService->logAdminAction($adminId, 'Actualización Tasa BCV', "Nuevo: " . $newValue);
        return $success;
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