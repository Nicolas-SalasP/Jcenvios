<?php

namespace App\Services;

use App\Database\Database;

class LogService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function logAction(?int $userId, string $action, string $details): void
    {
        $sql = "INSERT INTO logs (UserID, Accion, Detalles) VALUES (?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param("iss", $userId, $action, $details);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Purga (DELETE) registros de la bitácora con más de $months meses de antigüedad.
     * Es tabla de auditoría de acciones administrativas, no dato con retención legal
     * obligatoria (eso está cubierto aparte para AML/KYC), por eso se borra sin archivar.
     *
     * @return int Cantidad de filas eliminadas.
     */
    public function purgeOldLogs(int $months = 12): int
    {
        $sql = "DELETE FROM logs WHERE Timestamp < (NOW() - INTERVAL ? MONTH)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $months);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}