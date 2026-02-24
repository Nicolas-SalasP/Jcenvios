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
                    cb.PermitirEdicion, 
                    cb.SolicitudEdicion,  
                    p.NombrePais,
                    tb.TipoBeneficiarioID, tb.Nombre AS TipoBeneficiarioNombre,
                    cb.TitularPrimerNombre, cb.TitularSegundoNombre,
                    cb.TitularPrimerApellido, cb.TitularSegundoApellido,
                    td.TipoDocumentoID AS TitularTipoDocumentoID, td.NombreDocumento AS TitularTipoDocumentoNombre,
                    cb.TitularNumeroDocumento, cb.NombreBanco, cb.NumeroCuenta,
                    cb.NumeroTelefono, cb.FechaCreacion, cb.CCI
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

    public function setSolicitudEdicion(int $cuentaId, int $userId, int $estado): bool
    {
        $sql = "UPDATE cuentas_beneficiarias SET SolicitudEdicion = ? WHERE CuentaID = ? AND UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $estado, $cuentaId, $userId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function toggleAdminPermission(int $cuentaId, int $userId, int $newState): bool
    {
        $sql = "UPDATE cuentas_beneficiarias 
                SET PermitirEdicion = ?, 
                    SolicitudEdicion = CASE WHEN ? = 1 THEN 0 ELSE SolicitudEdicion END
                WHERE CuentaID = ? AND UserID = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiii", $newState, $newState, $cuentaId, $userId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function findAllByUserId(int $userId): array
    {
        $sql = "SELECT 
                    cb.CuentaID, cb.Alias, cb.UserID, cb.PaisID,
                    CONCAT_WS(' ', cb.TitularPrimerNombre, cb.TitularSegundoNombre, cb.TitularPrimerApellido, cb.TitularSegundoApellido) as BeneficiarioNombre,
                    cb.TitularNumeroDocumento,
                    cb.NombreBanco, 
                    cb.NumeroCuenta, 
                    cb.CCI, 
                    cb.NumeroTelefono,
                    cb.PermitirEdicion,
                    cb.SolicitudEdicion,
                    p.NombrePais, 
                    p.CodigoMoneda,
                    tb.Nombre as TipoBeneficiarioNombre
                FROM cuentas_beneficiarias cb 
                JOIN paises p ON cb.PaisID = p.PaisID
                LEFT JOIN tipos_beneficiario tb ON cb.TipoBeneficiarioID = tb.TipoBeneficiarioID
                WHERE cb.UserID = ? AND cb.Activo = 1
                ORDER BY cb.FechaCreacion DESC";

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

    public function create(int $userId, int $paisId, string $alias, string $nombre, ?string $segundoNombre, string $apellido, ?string $segundoApellido, int $tipoDocId, string $numDoc, string $banco, ?string $numCuenta, ?string $cci, ?string $telefono, int $tipoBeneficiarioId): int
    {
        $sql = "INSERT INTO cuentas_beneficiarias (
                UserID, PaisID, Alias, 
                TitularPrimerNombre, TitularSegundoNombre, TitularPrimerApellido, TitularSegundoApellido, 
                TitularTipoDocumentoID, TitularNumeroDocumento, 
                NombreBanco, NumeroCuenta, CCI, NumeroTelefono, 
                TipoBeneficiarioID, FechaCreacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param(
            "iisssssisssssi",
            $userId,
            $paisId,
            $alias,
            $nombre,
            $segundoNombre,
            $apellido,
            $segundoApellido,
            $tipoDocId,
            $numDoc,
            $banco,
            $numCuenta,
            $cci,
            $telefono,
            $tipoBeneficiarioId
        );

        if (!$stmt->execute()) {
            error_log("Error crear cuenta beneficiaria: " . $stmt->error);
            throw new Exception("No se pudo guardar el beneficiario.");
        }

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
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

    public function adminUpdateBeneficiary(int $cuentaId, array $data): bool
    {
        $partes = array_values(array_filter(explode(' ', trim($data['nombre']))));
        $primerNombre = $partes[0] ?? '';
        $segundoNombre = '';
        $primerApellido = '';
        $segundoApellido = '';

        if (count($partes) == 2) {
            $primerApellido = $partes[1];
        } elseif (count($partes) == 3) {
            $primerApellido = $partes[1];
            $segundoApellido = $partes[2];
        } elseif (count($partes) >= 4) {
            $segundoNombre = $partes[1];
            $primerApellido = $partes[2];
            $segundoApellido = implode(' ', array_slice($partes, 3));
        }

        $documento = trim($data['documento'] ?? '');
        $banco = trim($data['banco'] ?? '');
        $cuenta = trim($data['cuenta'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $cci = trim($data['cci'] ?? '');

        $sql = "UPDATE cuentas_beneficiarias SET 
                TitularPrimerNombre = ?, 
                TitularSegundoNombre = ?, 
                TitularPrimerApellido = ?, 
                TitularSegundoApellido = ?, 
                TitularNumeroDocumento = ?, 
                NombreBanco = ?, 
                NumeroCuenta = ?,
                NumeroTelefono = ?,
                CCI = ?
                WHERE CuentaID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "sssssssssi",
            $primerNombre,
            $segundoNombre,
            $primerApellido,
            $segundoApellido,
            $documento,
            $banco,
            $cuenta,
            $telefono,
            $cci,
            $cuentaId
        );

        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function findById(int $cuentaId): ?array
    {
        return $this->getById($cuentaId);
    }

    public function updatePermission(int $cuentaId, int $newState): bool
    {
        $sql = "UPDATE cuentas_beneficiarias SET PermitirEdicion = ? WHERE CuentaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $newState, $cuentaId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
}