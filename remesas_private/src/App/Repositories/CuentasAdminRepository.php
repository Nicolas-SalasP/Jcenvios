<?php
namespace App\Repositories;

use App\Database\Database;

class CuentasAdminRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $sql = "SELECT c.*, 
                       COALESCE(f.Nombre, 'Sin Forma Pago') as FormaPagoNombre, 
                       COALESCE(p.NombrePais, 'Sin País') as NombrePais 
                FROM cuentas_bancarias_admin c
                LEFT JOIN formas_pago f ON c.FormaPagoID = f.FormaPagoID
                LEFT JOIN paises p ON c.PaisID = p.PaisID
                ORDER BY p.NombrePais, f.Nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function findActiveByFormaPagoAndPais(int $formaPagoId, int $paisId): ?array
    {
        $sql = "SELECT * FROM cuentas_bancarias_admin 
                WHERE FormaPagoID = ? AND PaisID = ? AND Activo = 1 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $formaPagoId, $paisId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM cuentas_bancarias_admin WHERE CuentaAdminID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO cuentas_bancarias_admin (FormaPagoID, PaisID, Banco, Titular, TipoCuenta, NumeroCuenta, RUT, Email, Instrucciones, ColorHex, SaldoActual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        $saldoInicial = isset($data['saldoInicial']) ? (float) $data['saldoInicial'] : 0.00;

        $stmt->bind_param(
            "iissssssssd",
            $data['formaPagoId'],
            $data['paisId'],
            $data['banco'],
            $data['titular'],
            $data['tipoCuenta'],
            $data['numeroCuenta'],
            $data['rut'],
            $data['email'],
            $data['instrucciones'],
            $data['colorHex'],
            $saldoInicial
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE cuentas_bancarias_admin SET FormaPagoID=?, PaisID=?, Banco=?, Titular=?, TipoCuenta=?, NumeroCuenta=?, RUT=?, Email=?, Instrucciones=?, ColorHex=?, Activo=? WHERE CuentaAdminID=?";
        $stmt = $this->db->prepare($sql);
        $activoInt = (int) $data['activo'];
        $stmt->bind_param(
            "iissssssssii",
            $data['formaPagoId'],
            $data['paisId'],
            $data['banco'],
            $data['titular'],
            $data['tipoCuenta'],
            $data['numeroCuenta'],
            $data['rut'],
            $data['email'],
            $data['instrucciones'],
            $data['colorHex'],
            $activoInt,
            $id
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateSaldo(int $id, float $nuevoSaldo): bool
    {
        $sql = "UPDATE cuentas_bancarias_admin SET SaldoActual = ? WHERE CuentaAdminID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $nuevoSaldo, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM cuentas_bancarias_admin WHERE CuentaAdminID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}