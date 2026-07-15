<?php
namespace App\Repositories;

use App\Database\Database;

class HorarioOverrideRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getStatus(): ?array
    {
        $sql = "SELECT Activo, ForzadoPor, FechaActivacion, ExpiraEn FROM horario_override WHERE Id = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error DB en HorarioOverrideRepository::getStatus - Posiblemente la tabla no existe.");
            return null;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function setOverride(bool $activo, int $adminId, string $expiraEn): bool
    {
        $sql = "INSERT INTO horario_override (Id, Activo, ForzadoPor, FechaActivacion, ExpiraEn)
                VALUES (1, ?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    Activo = VALUES(Activo),
                    ForzadoPor = VALUES(ForzadoPor),
                    FechaActivacion = VALUES(FechaActivacion),
                    ExpiraEn = VALUES(ExpiraEn)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $activoInt = $activo ? 1 : 0;
        $stmt->bind_param("iis", $activoInt, $adminId, $expiraEn);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function clear(): bool
    {
        $sql = "UPDATE horario_override SET Activo = 0, ForzadoPor = NULL, FechaActivacion = NULL, ExpiraEn = NULL WHERE Id = 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
}
