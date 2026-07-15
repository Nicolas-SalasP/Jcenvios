<?php
namespace App\Repositories;

use App\Database\Database;

/**
 * Config global singleton (Id=1) para activar/desactivar las 2 formas de
 * vincular un cliente nuevo a un revendedor: código manual y link ?ref=.
 * Sigue el mismo patrón que HorarioOverrideRepository.
 */
class ReferralConfigRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getConfig(): array
    {
        $sql = "SELECT FormaManualActiva, FormaLinkActiva FROM referido_config WHERE Id = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error DB en ReferralConfigRepository::getConfig - Posiblemente la tabla no existe.");
            return ['FormaManualActiva' => 1, 'FormaLinkActiva' => 1];
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: ['FormaManualActiva' => 1, 'FormaLinkActiva' => 1];
    }

    public function setConfig(bool $manualActiva, bool $linkActiva, int $adminId): bool
    {
        $sql = "INSERT INTO referido_config (Id, FormaManualActiva, FormaLinkActiva, ActualizadoPor, FechaActualizacion)
                VALUES (1, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    FormaManualActiva = VALUES(FormaManualActiva),
                    FormaLinkActiva = VALUES(FormaLinkActiva),
                    ActualizadoPor = VALUES(ActualizadoPor),
                    FechaActualizacion = VALUES(FechaActualizacion)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $manualInt = $manualActiva ? 1 : 0;
        $linkInt = $linkActiva ? 1 : 0;
        $stmt->bind_param("iii", $manualInt, $linkInt, $adminId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
}
