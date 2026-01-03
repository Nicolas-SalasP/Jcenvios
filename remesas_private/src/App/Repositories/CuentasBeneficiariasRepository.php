<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class CuentasBeneficiariasRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByUserId(int $userId): array
    {
        $sql = "SELECT
                    cb.CuentaID, cb.Alias, cb.UserID, cb.PaisID,
                    p.NombrePais,
                    tb.TipoBeneficiarioID, tb.Nombre AS TipoBeneficiarioNombre,
                    cb.TitularPrimerNombre, cb.TitularSegundoNombre,
                    cb.TitularPrimerApellido, cb.TitularSegundoApellido,
                    td.TipoDocumentoID AS TitularTipoDocumentoID, td.NombreDocumento AS TitularTipoDocumentoNombre,
                    cb.TitularNumeroDocumento, cb.NombreBanco, cb.NumeroCuenta,
                    cb.NumeroTelefono, cb.FechaCreacion
                FROM cuentas_beneficiarias cb 
                JOIN paises p ON cb.PaisID = p.PaisID
                LEFT JOIN tipos_beneficiario tb ON cb.TipoBeneficiarioID = tb.TipoBeneficiarioID
                LEFT JOIN tipos_documento td ON cb.TitularTipoDocumentoID = td.TipoDocumentoID
                WHERE cb.UserID = ? AND cb.Activo = 1"; 

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getById(int $cuentaId): ?array
    {
        $sql = "SELECT * FROM cuentas_beneficiarias WHERE CuentaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function findByIdAndUserId(int $cuentaId, int $userId): ?array
    {
        $sql = "SELECT cb.*, 
                        td.NombreDocumento AS TitularTipoDocumentoNombre, 
                        tb.Nombre AS TipoBeneficiarioNombre
                  FROM cuentas_beneficiarias cb 
                  LEFT JOIN tipos_documento td ON cb.TitularTipoDocumentoID = td.TipoDocumentoID
                  LEFT JOIN tipos_beneficiario tb ON cb.TipoBeneficiarioID = tb.TipoBeneficiarioID
                  WHERE cb.CuentaID = ? AND cb.UserID = ? AND cb.Activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $cuentaId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO cuentas_beneficiarias 
                (UserID, PaisID, Alias, TipoBeneficiarioID, TitularPrimerNombre, TitularSegundoNombre, 
                 TitularPrimerApellido, TitularSegundoApellido, TitularTipoDocumentoID, TitularNumeroDocumento, 
                 NombreBanco, NumeroCuenta, NumeroTelefono, Activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "iissssssissss",
            $data['UserID'],
            $data['paisID'],
            $data['alias'],
            $data['tipoBeneficiarioID'],
            $data['primerNombre'],
            $data['segundoNombre'],
            $data['primerApellido'],
            $data['segundoApellido'],
            $data['titularTipoDocumentoID'],
            $data['numeroDocumento'],
            $data['nombreBanco'],
            $data['numeroCuenta'],
            $data['numeroTelefono']
        );

        if (!$stmt->execute()) {
            error_log("Error crear cuenta: " . $stmt->error);
            throw new Exception("Error al registrar cuenta.");
        }
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function update(int $cuentaId, array $data): bool
    {
        $sql = "UPDATE cuentas_beneficiarias SET
                    Alias = ?, TipoBeneficiarioID = ?, 
                    TitularPrimerNombre = ?, TitularSegundoNombre = ?, 
                    TitularPrimerApellido = ?, TitularSegundoApellido = ?, 
                    TitularTipoDocumentoID = ?, TitularNumeroDocumento = ?, 
                    NombreBanco = ?, NumeroCuenta = ?, NumeroTelefono = ?
                WHERE CuentaID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "sisssssssssi",
            $data['alias'],
            $data['tipoBeneficiarioID'],
            $data['primerNombre'],
            $data['segundoNombre'],
            $data['primerApellido'],
            $data['segundoApellido'],
            $data['titularTipoDocumentoID'],
            $data['numeroDocumento'],
            $data['nombreBanco'],
            $data['numeroCuenta'],
            $data['numeroTelefono'],
            $cuentaId
        );

        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function softDelete(int $cuentaId): bool
    {
        $sql = "UPDATE cuentas_beneficiarias SET Activo = 0 WHERE CuentaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function delete(int $cuentaId, int $userId): bool
    {
        return $this->softDelete($cuentaId);
    }
}