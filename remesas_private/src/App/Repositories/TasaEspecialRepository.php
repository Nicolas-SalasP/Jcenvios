<?php
namespace App\Repositories;

use App\Database\Database;

class TasaEspecialRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Tasa especial activa del cliente para esta ruta exacta, si existe.
     * Uso único: si hay más de una activa para la misma ruta (no debería
     * pasar en flujo normal), se toma la más antigua (FIFO).
     */
    public function findActiveForUserAndRoute(int $userId, int $paisOrigenId, int $paisDestinoId): ?array
    {
        $sql = "SELECT * FROM tasas_especiales_cliente
                WHERE UserID = ? AND PaisOrigenID = ? AND PaisDestinoID = ? AND Activa = 1
                ORDER BY FechaCreacion ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $userId, $paisOrigenId, $paisDestinoId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null;
    }

    public function create(int $userId, int $paisOrigenId, int $paisDestinoId, float $valor, int $adminId): int
    {
        $sql = "INSERT INTO tasas_especiales_cliente (UserID, PaisOrigenID, PaisDestinoID, ValorTasa, AdminID)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiidi", $userId, $paisOrigenId, $paisDestinoId, $valor, $adminId);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Reclama atómicamente la tasa especial (uso único). Devuelve false si
     * otra request ya la reclamó/desactivó en la ventana entre el SELECT de
     * findActiveForUserAndRoute() y este UPDATE — el caller debe entonces
     * seguir con la tasa pública en vez de la especial (no aplicarla sin
     * haberla podido reclamar). Sin el "AND Activa = 1" acá, dos requests
     * concurrentes del mismo cliente podían usar la misma tasa especial 2
     * veces (hallado en auditoría 2026-08-21).
     */
    public function claim(int $tasaEspecialId): bool
    {
        $sql = "UPDATE tasas_especiales_cliente SET Activa = 0, FechaUso = NOW() WHERE TasaEspecialID = ? AND Activa = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $tasaEspecialId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function attachTransaccion(int $tasaEspecialId, int $transaccionId): void
    {
        $sql = "UPDATE tasas_especiales_cliente SET TransaccionID = ? WHERE TasaEspecialID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $transaccionId, $tasaEspecialId);
        $stmt->execute();
        $stmt->close();
    }

    public function deactivate(int $tasaEspecialId): bool
    {
        $sql = "UPDATE tasas_especiales_cliente SET Activa = 0 WHERE TasaEspecialID = ? AND Activa = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $tasaEspecialId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function findAllByUser(int $userId): array
    {
        $sql = "SELECT tec.*, po.NombrePais AS PaisOrigenNombre, pd.NombrePais AS PaisDestinoNombre,
                    a.PrimerNombre AS AdminNombre, a.PrimerApellido AS AdminApellido
                FROM tasas_especiales_cliente tec
                JOIN paises po ON po.PaisID = tec.PaisOrigenID
                JOIN paises pd ON pd.PaisID = tec.PaisDestinoID
                LEFT JOIN usuarios a ON a.UserID = tec.AdminID
                WHERE tec.UserID = ?
                ORDER BY tec.FechaCreacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}
