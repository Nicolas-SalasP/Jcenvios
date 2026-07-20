<?php
namespace App\Repositories;

use App\Database\Database;

class NormasRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(): ?array
    {
        $sql = "SELECT * FROM normas WHERE Id = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function save(string $contenido, int $actualizadoPor): bool
    {
        $sql = "INSERT INTO normas (Id, Contenido, ActualizadoPor, FechaActualizacion)
                VALUES (1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE Contenido = VALUES(Contenido),
                                        ActualizadoPor = VALUES(ActualizadoPor),
                                        FechaActualizacion = VALUES(FechaActualizacion)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $contenido, $actualizadoPor);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
}
