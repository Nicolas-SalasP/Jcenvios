<?php
namespace App\Services;

use App\Repositories\SystemSettingsRepository;
use App\Repositories\HolidayRepository;
use App\Services\LogService;
use Exception;

class SystemSettingsService
{
    private SystemSettingsRepository $settingsRepo;
    private HolidayRepository $holidayRepo;
    private LogService $logService;

    public function __construct(
        SystemSettingsRepository $settingsRepo,
        HolidayRepository $holidayRepo,
        LogService $logService
    ) {
        $this->settingsRepo = $settingsRepo;
        $this->holidayRepo = $holidayRepo;
        $this->logService = $logService;
    }

    // --- GESTIÓN DE VACACIONES (ADMIN) ---
    public function addHoliday(int $adminId, string $inicio, string $fin, string $motivo, int $bloqueo = 1): void
    {
        if (empty($inicio) || empty($fin) || empty($motivo)) {
            throw new Exception("Todos los campos son obligatorios.");
        }

        $startTs = strtotime($inicio);
        $endTs = strtotime($fin);

        if ($startTs === false || $endTs === false) {
            throw new Exception("Formato de fecha inválido.");
        }

        if ($startTs >= $endTs) {
            throw new Exception("La fecha de inicio debe ser anterior a la fecha de fin.");
        }

        if ($endTs < time()) {
            throw new Exception("No puedes crear un feriado que ya terminó.");
        }

        $sqlInicio = date('Y-m-d H:i:s', $startTs);
        $sqlFin = date('Y-m-d H:i:s', $endTs);

        if (!$this->holidayRepo->create($sqlInicio, $sqlFin, $motivo, $adminId, $bloqueo)) {
            throw new Exception("Error al guardar el feriado en la base de datos.");
        }

        $tipoBloqueo = $bloqueo ? "BLOQUEANTE" : "INFORMATIVO";
        $this->logService->logAction(
            $adminId,
            "Programó Feriado",
            "Motivo: $motivo ($tipoBloqueo) | Inicio: $sqlInicio | Fin: $sqlFin"
        );
    }

    public function getHolidays(): array
    {
        return $this->holidayRepo->getAllFutureAndCurrent();
    }

    public function deleteHoliday(int $id, int $adminId): void
    {
        $info = "ID #$id";
        if (!$this->holidayRepo->delete($id)) {
            throw new Exception("Error al eliminar el feriado.");
        }

        $this->logService->logAction(
            $adminId,
            "Eliminó Feriado",
            "Se eliminó el bloqueo: $info"
        );
    }
    public function getActiveHoliday(): ?array
    {
        return $this->holidayRepo->getActiveHoliday();
    }
    public function checkSystemAvailability(): array
    {
        try {
            $activeHoliday = $this->holidayRepo->getActiveHoliday();

            if ($activeHoliday) {
                if ($activeHoliday['BloqueoSistema'] == 1) {
                    return [
                        'available' => false,
                        'reason' => 'holiday',
                        'message' => $activeHoliday['Motivo'],
                        'ends_at' => $activeHoliday['FechaFin']
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error checkSystemAvailability: " . $e->getMessage());
        }

        return ['available' => true];
    }
}