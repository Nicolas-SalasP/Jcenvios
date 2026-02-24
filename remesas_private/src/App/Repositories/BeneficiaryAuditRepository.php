<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class BeneficiaryAuditRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function createEditRequest(int $cuentaId, int $userId, int $adminId, array $campos, string $motivo): int
    {
        $camposJson = json_encode($campos);
        $sql = "INSERT INTO beneficiarios_solicitudes_cambio 
                (CuentaID, UserID, AdminSolicitanteID, CamposSolicitados, Motivo, Estado) 
                VALUES (?, ?, ?, ?, ?, 'Pendiente')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiiss", $cuentaId, $userId, $adminId, $camposJson, $motivo);
        $stmt->execute();
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    }

    public function getPendingRequestsForUser(int $userId): array
    {
        $sql = "SELECT s.*, c.Alias, c.NombreBanco, 
                    CONCAT_WS(' ', c.TitularPrimerNombre, c.TitularSegundoNombre, c.TitularPrimerApellido, c.TitularSegundoApellido) AS BeneficiarioNombre, 
                    a.PrimerNombre AS AdminNombre 
                FROM beneficiarios_solicitudes_cambio s
                JOIN cuentas_beneficiarias c ON s.CuentaID = c.CuentaID
                JOIN usuarios a ON s.AdminSolicitanteID = a.UserID
                WHERE s.UserID = ? AND s.Estado = 'Pendiente'
                ORDER BY s.FechaSolicitud DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $solicitudes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $solicitudes;
    }

    public function updateRequestStatus(int $solicitudId, int $userId, string $nuevoEstado): bool
    {
        $sql = "UPDATE beneficiarios_solicitudes_cambio 
                SET Estado = ?, FechaRespuesta = CURRENT_TIMESTAMP 
                WHERE SolicitudID = ? AND UserID = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sii", $nuevoEstado, $solicitudId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getApprovedRequest(int $cuentaId): ?array
    {
        $sql = "SELECT * FROM beneficiarios_solicitudes_cambio 
                WHERE CuentaID = ? AND Estado = 'Aprobada' 
                ORDER BY FechaRespuesta DESC LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ?: null;
    }

    public function logAudit(int $cuentaId, int $modificadorId, string $tipoEvento, ?array $estadoAnterior, ?array $estadoNuevo, ?int $solicitudId = null): void
    {
        $jsonAnterior = $estadoAnterior ? json_encode($estadoAnterior) : null;
        $jsonNuevo = $estadoNuevo ? json_encode($estadoNuevo) : null;

        $sql = "INSERT INTO beneficiarios_auditoria 
                (CuentaID, ModificadoPorID, SolicitudID, TipoEvento, EstadoAnterior, EstadoNuevo) 
                VALUES (?, ?, ?, ?, ?, ?)";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiisss", $cuentaId, $modificadorId, $solicitudId, $tipoEvento, $jsonAnterior, $jsonNuevo);
        $stmt->execute();
        $stmt->close();
    }

    public function getBeneficiarySnapshot(int $cuentaId): ?array
    {
        $sql = "SELECT * FROM cuentas_beneficiarias WHERE CuentaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $snapshot = $result->fetch_assoc();
        $stmt->close();
        
        return $snapshot ?: null;
    }

    public function getHistoryByAccount(int $cuentaId): array
    {
        $sql = "SELECT ba.*, u.PrimerNombre, u.PrimerApellido 
                FROM beneficiarios_auditoria ba
                LEFT JOIN usuarios u ON ba.ModificadoPorID = u.UserID
                WHERE ba.CuentaID = ?
                ORDER BY ba.FechaEvento DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function getRequestById(int $solicitudId): ?array
    {
        $sql = "SELECT * FROM beneficiarios_solicitudes_cambio WHERE SolicitudID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $solicitudId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $res ?: null;
    }
}