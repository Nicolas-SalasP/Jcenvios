<?php
namespace App\Repositories;

use App\Database\Database;

/**
 * CRUD de las cuentas bancarias propias de cada revendedor (tabla revendedor_cuentas),
 * más el límite configurable de cuántas puede registrar (usuarios.MaxCuentasRevendedor).
 */
class ResellerAccountsRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAllByUser(int $userId, bool $onlyActive = false): array
    {
        $sql = "SELECT CuentaID, UserID, Banco, TipoCuenta, NumeroCuenta, TitularNombre, TitularDocumento, Instrucciones, Activo, FechaCreacion
                FROM revendedor_cuentas WHERE UserID = ?" . ($onlyActive ? " AND Activo = 1" : "") . " ORDER BY FechaCreacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as c FROM revendedor_cuentas WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }

    public function findById(int $cuentaId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM revendedor_cuentas WHERE CuentaID = ?");
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function create(int $userId, string $banco, string $tipoCuenta, string $numeroCuenta, string $titularNombre, string $titularDocumento, ?string $instrucciones): int
    {
        $sql = "INSERT INTO revendedor_cuentas (UserID, Banco, TipoCuenta, NumeroCuenta, TitularNombre, TitularDocumento, Instrucciones, Activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("issssss", $userId, $banco, $tipoCuenta, $numeroCuenta, $titularNombre, $titularDocumento, $instrucciones);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function update(int $cuentaId, int $userId, string $banco, string $tipoCuenta, string $numeroCuenta, string $titularNombre, string $titularDocumento, ?string $instrucciones): bool
    {
        $sql = "UPDATE revendedor_cuentas SET Banco = ?, TipoCuenta = ?, NumeroCuenta = ?, TitularNombre = ?, TitularDocumento = ?, Instrucciones = ?
                WHERE CuentaID = ? AND UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssssii", $banco, $tipoCuenta, $numeroCuenta, $titularNombre, $titularDocumento, $instrucciones, $cuentaId, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function setActive(int $cuentaId, int $userId, bool $activo): bool
    {
        $stmt = $this->db->prepare("UPDATE revendedor_cuentas SET Activo = ? WHERE CuentaID = ? AND UserID = ?");
        $activoInt = $activo ? 1 : 0;
        $stmt->bind_param("iii", $activoInt, $cuentaId, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $cuentaId, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM revendedor_cuentas WHERE CuentaID = ? AND UserID = ?");
        $stmt->bind_param("ii", $cuentaId, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function getMaxCuentas(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT MaxCuentasRevendedor FROM usuarios WHERE UserID = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['MaxCuentasRevendedor'] ?? 6);
    }

    public function updateMaxCuentas(int $userId, int $max): bool
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET MaxCuentasRevendedor = ? WHERE UserID = ? AND RolID = 4");
        $stmt->bind_param("ii", $max, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
