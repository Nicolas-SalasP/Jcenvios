<?php
namespace App\Services;

use App\Repositories\SystemSettingsRepository;
use App\Repositories\HolidayRepository;
use Exception;

class SystemSettingsService
{
    private SystemSettingsRepository $settingsRepo;
    private HolidayRepository $holidayRepo;

    public function __construct(
        SystemSettingsRepository $settingsRepo,
        HolidayRepository $holidayRepo
    ) {
        $this->settingsRepo = $settingsRepo;
        $this->holidayRepo = $holidayRepo;
    }

    // --- GESTIÓN DE VACACIONES (ADMIN) ---

    public function addHoliday(int $adminId, string $inicio, string $fin, string $motivo): void
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

        if (!$this->holidayRepo->create($inicio, $fin, $motivo, $adminId)) {
            throw new Exception("Error al guardar el feriado en la base de datos.");
        }
    }

    public function getHolidays(): array
    {
        return $this->holidayRepo->getAllFutureAndCurrent();
    }

    public function deleteHoliday(int $id): void
    {
        if (!$this->holidayRepo->delete($id)) {
            throw new Exception("Error al eliminar el feriado.");
        }
    }

    // --- VALIDACIÓN GLOBAL (CLIENTE) ---

    public function checkSystemAvailability(): array
    {
        $activeHoliday = $this->holidayRepo->getActiveHoliday();

        if ($activeHoliday) {
            return [
                'available' => false,
                'reason' => 'holiday',
                'message' => $activeHoliday['Motivo'],
                'ends_at' => $activeHoliday['FechaFin']
            ];
        }

        return ['available' => true];
    }

}