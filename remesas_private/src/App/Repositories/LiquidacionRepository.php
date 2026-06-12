<?php
namespace App\Repositories;

use App\Database\Database;

class LiquidacionRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db->getConnection();
    }

    /**
     * Creates a new liquidacion record and returns its ID.
     * Bind types: i=userId(int), d=monto(double), s=desde, s=hasta, i=cantidad, s=notas
     */
    public function create(int $userId, float $monto, string $desde, string $hasta, int $cantidad, ?string $notas = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO liquidaciones_revendedor (UserID, Monto, PeriodoDesde, PeriodoHasta, CantidadTransacciones, Notas)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idssis", $userId, $monto, $desde, $hasta, $cantidad, $notas);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function markPaid(int $liquidacionId, ?string $comprobanteUrl, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE liquidaciones_revendedor SET Estado='pagada', FechaPago=NOW(), ComprobanteURL=?, AdminUserID=? WHERE LiquidacionID=?"
        );
        $stmt->bind_param("sii", $comprobanteUrl, $adminId, $liquidacionId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT L.*, CONCAT(U.PrimerNombre,' ',U.PrimerApellido) as AdminNombre
             FROM liquidaciones_revendedor L
             LEFT JOIN usuarios U ON L.AdminUserID = U.UserID
             WHERE L.UserID = ?
             ORDER BY L.FechaCreacion DESC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getAll(): array
    {
        $sql = "SELECT L.*, CONCAT(U.PrimerNombre,' ',U.PrimerApellido) as RevendedorNombre,
                       U.Email as RevendedorEmail
                FROM liquidaciones_revendedor L
                JOIN usuarios U ON L.UserID = U.UserID
                ORDER BY L.FechaCreacion DESC";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM liquidaciones_revendedor WHERE LiquidacionID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getConnection(): \mysqli
    {
        return $this->db;
    }
}
