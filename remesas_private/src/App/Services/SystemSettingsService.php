<?php
namespace App\Services;

use App\Repositories\SystemSettingsRepository;
use App\Repositories\HolidayRepository;
use App\Repositories\HorarioOverrideRepository;
use App\Services\LogService;
use DateTime;
use DateTimeZone;
use Exception;

class SystemSettingsService
{
    private SystemSettingsRepository $settingsRepo;
    private HolidayRepository $holidayRepo;
    private HorarioOverrideRepository $horarioOverrideRepo;
    private LogService $logService;

    private const TZ = 'America/Santiago';

    public function __construct(
        SystemSettingsRepository $settingsRepo,
        HolidayRepository $holidayRepo,
        HorarioOverrideRepository $horarioOverrideRepo,
        LogService $logService
    ) {
        $this->settingsRepo = $settingsRepo;
        $this->holidayRepo = $holidayRepo;
        $this->horarioOverrideRepo = $horarioOverrideRepo;
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

    // --- OVERRIDE MANUAL DE HORARIO LABORAL (ADMIN) ---

    /**
     * Calcula el fin del horario laboral normal para "ahora" (America/Santiago).
     * Lunes-Viernes 19:30, Sábado 16:00. Domingo no tiene horario laboral,
     * por lo que el fin "efectivo" es el propio instante actual (el override
     * de supresión no tiene sentido más allá de ahora mismo ese día).
     * Si "ahora" ya pasó el fin de horario del día, retorna "ahora" (ya vencido).
     */
    private function getBusinessHoursEndForToday(DateTime $now): DateTime
    {
        $dayOfWeek = (int)$now->format('N'); // 1 (Lunes) .. 7 (Domingo)
        $end = clone $now;

        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $end->setTime(19, 30, 0);
        } elseif ($dayOfWeek === 6) {
            $end->setTime(16, 0, 0);
        } else {
            // Domingo: sin horario laboral, el override expira de inmediato.
            return $now;
        }

        if ($end < $now) {
            return $now;
        }

        return $end;
    }

    /**
     * Activa (fuerza aviso) o desactiva (suprime aviso) manualmente el
     * override de "fuera de horario". El override queda vigente solo hasta
     * el fin del horario laboral normal del día actual (ExpiraEn); pasada
     * esa hora se considera vencido automáticamente sin acción del admin.
     */
    public function toggleHorarioOverride(int $adminId, bool $activo): array
    {
        $now = new DateTime('now', new DateTimeZone(self::TZ));
        $expiraEn = $this->getBusinessHoursEndForToday($now)->format('Y-m-d H:i:s');

        if (!$this->horarioOverrideRepo->setOverride($activo, $adminId, $expiraEn)) {
            throw new Exception("Error al guardar el override de horario en la base de datos.");
        }

        $this->logService->logAction(
            $adminId,
            "Modificó Override de Horario",
            $activo
                ? "Forzó manualmente el AVISO de fuera de horario (expira: $expiraEn)"
                : "Suprimió manualmente el aviso de fuera de horario (expira: $expiraEn)"
        );

        return $this->getHorarioOverrideStatus();
    }

    /**
     * Elimina el override manual vigente, volviendo de inmediato a la lógica
     * automática normal de checkBusinessHours() en el frontend.
     */
    public function clearHorarioOverride(int $adminId): void
    {
        if (!$this->horarioOverrideRepo->clear()) {
            throw new Exception("Error al limpiar el override de horario.");
        }

        $this->logService->logAction(
            $adminId,
            "Modificó Override de Horario",
            "Volvió al modo automático (sin override)"
        );
    }

    /**
     * Retorna el estado efectivo del override, considerando su expiración.
     * 'active' => true  : forzar mostrar el aviso de fuera de horario.
     * 'active' => false : suprimir el aviso (solo hasta ExpiraEn).
     * 'active' => null  : sin override vigente, usar la lógica normal del front.
     */
    public function getHorarioOverrideStatus(): array
    {
        $row = $this->horarioOverrideRepo->getStatus();

        if (!$row || $row['ExpiraEn'] === null) {
            return ['active' => null];
        }

        $now = new DateTime('now', new DateTimeZone(self::TZ));
        $expiraEn = DateTime::createFromFormat('Y-m-d H:i:s', $row['ExpiraEn'], new DateTimeZone(self::TZ));

        if (!$expiraEn || $now >= $expiraEn) {
            return ['active' => null];
        }

        return [
            'active' => (bool)$row['Activo'],
            'forzado_por' => $row['ForzadoPor'] !== null ? (int)$row['ForzadoPor'] : null,
            'fecha_activacion' => $row['FechaActivacion'],
            'expira_en' => $row['ExpiraEn'],
        ];
    }
}