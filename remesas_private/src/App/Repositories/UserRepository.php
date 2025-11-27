<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class UserRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): ?array
    {
        // Solo buscamos usuarios activos (no eliminados)
        $sql = "SELECT
                    U.UserID, U.PasswordHash, U.PrimerNombre, U.FailedLoginAttempts, U.LockoutUntil,
                    R.RolID, R.NombreRol AS Rol,
                    EV.EstadoID AS VerificacionEstadoID, EV.NombreEstado AS VerificacionEstado,
                    U.twofa_enabled, U.FotoPerfilURL
                FROM usuarios U
                LEFT JOIN roles R ON U.RolID = R.RolID
                LEFT JOIN estados_verificacion EV ON U.VerificacionEstadoID = EV.EstadoID
                WHERE U.Email = ? AND U.Eliminado = 0 LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    public function countAdmins(): int
    {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE RolID = 1 AND Eliminado = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($result['total'] ?? 0);
    }

    public function create(array $data): int
    {
        $estadoVerificacionInicialID = $data['verificacionEstadoID'] ?? 1;
        $rolUsuarioID = $data['rolID'] ?? 3;

        $sql = "INSERT INTO usuarios (PrimerNombre, SegundoNombre, PrimerApellido, SegundoApellido, Email, PasswordHash, Telefono, TipoDocumentoID, NumeroDocumento, VerificacionEstadoID, RolID)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $primerNombre = $data['primerNombre'];
        $segundoNombre = $data['segundoNombre'] ?? null;
        $primerApellido = $data['primerApellido'];
        $segundoApellido = $data['segundoApellido'] ?? null;
        $email = $data['email'];
        $passwordHash = $data['passwordHash'];
        $telefono = $data['telefono'] ?? null;
        $tipoDocumentoID = $data['tipoDocumentoID'];
        $numeroDocumento = $data['numeroDocumento'];

        $stmt->bind_param(
            "sssssssiisi",
            $primerNombre,
            $segundoNombre,
            $primerApellido,
            $segundoApellido,
            $email,
            $passwordHash,
            $telefono,
            $tipoDocumentoID,
            $numeroDocumento,
            $estadoVerificacionInicialID,
            $rolUsuarioID
        );

        if (!$stmt->execute()) {
            error_log("Error al insertar usuario: " . $stmt->error . " - Data: " . print_r($data, true));
            if ($stmt->errno == 1062) {
                if (strpos($stmt->error, 'Email_UNIQUE') !== false) {
                    throw new Exception("El correo electrónico ya está registrado.", 409);
                } elseif (strpos($stmt->error, 'NumeroDocumento_UNIQUE') !== false) {
                    throw new Exception("El número de documento ya está registrado.", 409);
                }
            }
            throw new Exception("Error al registrar el usuario.", 500);
        }
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function findUserById(int $userId): ?array
    {
        $sql = "SELECT
                    U.UserID, U.PrimerNombre, U.SegundoNombre, U.PrimerApellido, U.SegundoApellido,
                    U.Email, U.Telefono, U.NumeroDocumento, U.FotoPerfilURL,
                    U.DocumentoImagenURL_Frente, U.DocumentoImagenURL_Reverso,
                    U.FailedLoginAttempts, U.LockoutUntil, U.FechaRegistro,
                    U.twofa_enabled, 
                    R.RolID, R.NombreRol AS Rol,
                    EV.EstadoID AS VerificacionEstadoID, EV.NombreEstado AS VerificacionEstado,
                    TD.TipoDocumentoID, TD.NombreDocumento AS TipoDocumento,
                    U.PorcentajeComision
                FROM usuarios U
                LEFT JOIN roles R ON U.RolID = R.RolID
                LEFT JOIN estados_verificacion EV ON U.VerificacionEstadoID = EV.EstadoID
                LEFT JOIN tipos_documento TD ON U.TipoDocumentoID = TD.TipoDocumentoID
                WHERE U.UserID = ? AND U.Eliminado = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    public function updateLoginAttempts(int $userId, int $attempts, ?string $lockoutUntil): bool
    {
        $sql = "UPDATE usuarios SET FailedLoginAttempts = ?, LockoutUntil = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("isi", $attempts, $lockoutUntil, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $this->invalidatePreviousTokens($userId);

        $sql = "INSERT INTO passwordresets (UserID, Token, ExpiresAt) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $userId, $token, $expiresAt);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function findValidResetToken(string $token): ?array
    {
        $sql = "SELECT ResetID, UserID FROM passwordresets WHERE Token = ? AND Used = FALSE AND ExpiresAt > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $sql = "UPDATE usuarios SET PasswordHash = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $passwordHash, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateProfileInfo(int $userId, string $telefono, ?string $fotoPerfilUrl): bool
    {
        if ($fotoPerfilUrl !== null) {
            $sql = "UPDATE usuarios SET Telefono = ?, FotoPerfilURL = ? WHERE UserID = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ssi", $telefono, $fotoPerfilUrl, $userId);
        } else {
            $sql = "UPDATE usuarios SET Telefono = ? WHERE UserID = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("si", $telefono, $userId);
        }
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function markTokenAsUsed(int $resetId): bool
    {
        $sql = "UPDATE passwordresets SET Used = TRUE WHERE ResetID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $resetId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    private function invalidatePreviousTokens(int $userId): void
    {
        $sql = "UPDATE passwordresets SET Used = TRUE WHERE UserID = ? AND Used = FALSE";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function updateVerificationDocuments(int $userId, string $pathFrente, string $pathReverso, int $estadoPendienteID): bool
    {
        $sql = "UPDATE usuarios SET DocumentoImagenURL_Frente = ?, DocumentoImagenURL_Reverso = ?, VerificacionEstadoID = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssii", $pathFrente, $pathReverso, $estadoPendienteID, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateVerificationStatus(int $userId, int $newStatusID): bool
    {
        $sql = "UPDATE usuarios SET VerificacionEstadoID = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $newStatusID, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateRole(int $userId, int $rolId): bool
    {
        $sql = "UPDATE usuarios SET RolID = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $rolId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addGanancia(int $userId, float $monto): bool
    {
        $sql = "UPDATE usuarios SET SaldoGanancias = SaldoGanancias + ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $monto, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function delete(int $userId): bool
    {
        $sql = "UPDATE usuarios SET Eliminado = 1, Email = CONCAT(Email, '_deleted_', UNIX_TIMESTAMP()) WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function countAll(): int
    {
        $adminRolID = 1;
        $sql = "SELECT COUNT(UserID) as total FROM usuarios WHERE RolID != ? AND Eliminado = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $adminRolID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($result['total'] ?? 0);
    }

    public function findEstadoVerificacionIdByName(string $nombreEstado): ?int
    {
        $sql = "SELECT EstadoID FROM estados_verificacion WHERE NombreEstado = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $nombreEstado);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['EstadoID'] ?? null;
    }

    public function findRolIdByName(string $nombreRol): ?int
    {
        $sql = "SELECT RolID FROM roles WHERE NombreRol = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $nombreRol);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['RolID'] ?? null;
    }
    public function findTipoDocumentoIdByName(string $nombreDocumento): ?int
    {
        $sql = "SELECT TipoDocumentoID FROM tipos_documento WHERE NombreDocumento = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $nombreDocumento);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['TipoDocumentoID'] ?? null;
    }

    // --- MÉTODOS PARA 2FA ---
    public function get2FASecret(int $userId): ?string
    {
        $sql = "SELECT twofa_secret FROM usuarios WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['twofa_secret'] ?? null;
    }

    public function update2FASecret(int $userId, string $encryptedSecret): bool
    {
        $sql = "UPDATE usuarios SET twofa_secret = ?, twofa_enabled = FALSE WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $encryptedSecret, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function enable2FA(int $userId, string $encryptedBackupCodes): bool
    {
        $sql = "UPDATE usuarios SET twofa_enabled = TRUE, twofa_backup_codes = ? WHERE UserID = ? AND twofa_secret IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $encryptedBackupCodes, $userId);
        $success = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $success && $affected > 0;
    }

    public function disable2FA(int $userId): bool
    {
        $sql = "UPDATE usuarios SET twofa_enabled = FALSE, twofa_secret = NULL, twofa_backup_codes = NULL WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getBackupCodes(int $userId): ?string
    {
        $sql = "SELECT twofa_backup_codes FROM usuarios WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);

        if (!$stmt->execute()) {
            error_log("Error al ejecutar getBackupCodes para UserID {$userId}: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        if ($result === false) {
            error_log("Error al obtener resultado getBackupCodes para UserID {$userId}: " . $stmt->error);
            $stmt->close();
            return null;
        }

        $data = $result->fetch_assoc();
        $stmt->close();

        return $data['twofa_backup_codes'] ?? null;
    }

    public function updateBackupCodes(int $userId, string $newEncryptedBackupCodes): bool
    {
        $sql = "UPDATE usuarios SET twofa_backup_codes = ? WHERE UserID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $newEncryptedBackupCodes, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateGeneralData(int $userId, string $nom1, ?string $nom2, string $ape1, ?string $ape2, string $tel, ?string $doc): bool
    {
        $sql = "UPDATE usuarios SET 
                    PrimerNombre = ?, 
                    SegundoNombre = ?, 
                    PrimerApellido = ?, 
                    SegundoApellido = ?, 
                    Telefono = ?,
                    NumeroDocumento = ?
                WHERE UserID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssssi", $nom1, $nom2, $ape1, $ape2, $tel, $doc, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}