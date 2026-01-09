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
        $sql = "INSERT INTO cuentas_bancarias_admin 
                (FormaPagoID, PaisID, RolCuentaID, Banco, Titular, TipoCuenta, NumeroCuenta, RUT, Email, Instrucciones, ColorHex, SaldoActual, Activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $formaPagoId = $data['formaPagoId'] ?? 1;
        $paisId = $data['paisId'] ?? 0;
        $rolId = $data['rolCuentaId'] ?? 1;
        $banco = $data['banco'] ?? '';
        $titular = $data['titular'] ?? '';
        $tipoCuenta = $data['tipoCuenta'] ?? '';
        $numeroCuenta = $data['numeroCuenta'] ?? '';
        $rut = $data['rut'] ?? '';
        $email = $data['email'] ?? '';
        $instrucciones = $data['instrucciones'] ?? ''; 
        $colorHex = $data['colorHex'] ?? '#000000';
        $saldoInicial = isset($data['saldoInicial']) ? (float)$data['saldoInicial'] : 0.00;
        $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

        $stmt->bind_param(
            "iiissssssssdi",
            $formaPagoId,
            $paisId,
            $rolId,
            $banco,
            $titular,
            $tipoCuenta,
            $numeroCuenta,
            $rut,
            $email,
            $instrucciones,
            $colorHex,
            $saldoInicial,
            $activo
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE cuentas_bancarias_admin 
                SET FormaPagoID=?, PaisID=?, RolCuentaID=?, Banco=?, Titular=?, TipoCuenta=?, NumeroCuenta=?, RUT=?, Email=?, Instrucciones=?, ColorHex=?, Activo=? 
                WHERE CuentaAdminID=?";
        
        $stmt = $this->db->prepare($sql);
        
        $formaPagoId = $data['formaPagoId'] ?? 1;
        $paisId = $data['paisId'] ?? 0;
        $rolId = $data['rolCuentaId'] ?? 1;
        $banco = $data['banco'] ?? '';
        $titular = $data['titular'] ?? '';
        $tipoCuenta = $data['tipoCuenta'] ?? '';
        $numeroCuenta = $data['numeroCuenta'] ?? '';
        $rut = $data['rut'] ?? '';
        $email = $data['email'] ?? '';
        $instrucciones = $data['instrucciones'] ?? '';
        $colorHex = $data['colorHex'] ?? '#000000';
        $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

        $stmt->bind_param(
            "iiissssssssii",
            $formaPagoId,
            $paisId,
            $rolId,
            $banco,
            $titular,
            $tipoCuenta,
            $numeroCuenta,
            $rut,
            $email,
            $instrucciones,
            $colorHex,
            $activo,
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