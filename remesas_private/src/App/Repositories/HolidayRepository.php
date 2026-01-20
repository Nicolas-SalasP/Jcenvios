<?php
namespace App\Repositories;

use App\Database\Database;

class HolidayRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $inicio, string $fin, string $motivo, int $adminId, int $bloqueo = 1): bool
    {
        $sql = "INSERT INTO system_holidays (FechaInicio, FechaFin, Motivo, CreatedBy, BloqueoSistema) VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error DB en HolidayRepository::create - Posiblemente la tabla no existe.");
            return false;
        }

        $stmt->bind_param("sssii", $inicio, $fin, $motivo, $adminId, $bloqueo);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM system_holidays WHERE HolidayID = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            return false;

        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getAllFutureAndCurrent(): array
    {
        $sql = "SELECT * FROM system_holidays WHERE FechaFin >= NOW() ORDER BY FechaInicio ASC";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    public function getActiveHoliday(): ?array
    {
        $sql = "SELECT * FROM system_holidays 
                WHERE NOW() BETWEEN FechaInicio AND FechaFin 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            return $result->fetch_assoc();
        }
        return null;
    }
}