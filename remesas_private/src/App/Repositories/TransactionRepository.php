<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class TransactionRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO transacciones (
                    UserID, CuentaBeneficiariaID, TasaID_Al_Momento, 
                    MontoOrigen, MonedaOrigen, MontoDestino, MonedaDestino, 
                    EstadoID, FormaPagoID,
                    BeneficiarioNombre, BeneficiarioDocumento, BeneficiarioBanco, BeneficiarioNumeroCuenta, BeneficiarioTelefono
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $estadoInicialID = $data['estadoID'] ?? 1;

        $stmt->bind_param(
            "iiidsdsiisssss",
            $data['userID'],
            $data['cuentaID'],
            $data['tasaID'],
            $data['montoOrigen'],
            $data['monedaOrigen'],
            $data['montoDestino'],
            $data['monedaDestino'],
            $estadoInicialID,
            $data['formaPagoID'],
            $data['beneficiarioNombre'],
            $data['beneficiarioDocumento'],
            $data['beneficiarioBanco'],
            $data['beneficiarioNumeroCuenta'],
            $data['beneficiarioTelefono']
        );

        if (!$stmt->execute()) {
            error_log("Error al crear la transacción: " . $stmt->error . " - Data: " . print_r($data, true));
            throw new Exception("No se pudo registrar la orden. Inténtalo de nuevo.");
        }
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function getFullTransactionDetails(int $transactionId): ?array
    {
        $sql = "SELECT
            T.TransaccionID, T.UserID, T.CuentaBeneficiariaID, T.TasaID_Al_Momento,
            T.MontoOrigen, T.MonedaOrigen, T.MontoDestino, T.ComisionDestino, T.MonedaDestino,
            T.FechaTransaccion, T.ComprobanteURL, T.ComprobanteEnvioURL,
            U.PrimerNombre, U.PrimerApellido, U.Email, U.NumeroDocumento, U.Telefono, U.FotoPerfilURL,
            TD_U.NombreDocumento AS UsuarioTipoDocumentoNombre,
            R.NombreRol AS UsuarioRolNombre,
            EV.NombreEstado AS UsuarioVerificacionEstadoNombre,
            
            T.BeneficiarioNombre,
            T.BeneficiarioDocumento,
            T.BeneficiarioBanco,
            T.BeneficiarioNumeroCuenta,
            T.BeneficiarioTelefono,

            TS.ValorTasa,
            TS.PaisOrigenID,
            
            ET.EstadoID, ET.NombreEstado AS Estado,
            FP.FormaPagoID, FP.Nombre AS FormaDePago,

            CB.PaisID AS PaisDestinoID,
            TD_B.NombreDocumento AS BeneficiarioTipoDocumentoNombre,
            TB.Nombre AS BeneficiarioTipoNombre,
            
            -- Campos para el sistema de Pausa
            T.MotivoPausa, T.MensajeReanudacion
            
        FROM transacciones AS T
        JOIN usuarios AS U ON T.UserID = U.UserID
        JOIN tasas AS TS ON T.TasaID_Al_Momento = TS.TasaID
        LEFT JOIN estados_transaccion AS ET ON T.EstadoID = ET.EstadoID
        LEFT JOIN formas_pago AS FP ON T.FormaPagoID = FP.FormaPagoID
        LEFT JOIN tipos_documento AS TD_U ON U.TipoDocumentoID = TD_U.TipoDocumentoID
        LEFT JOIN roles AS R ON U.RolID = R.RolID
        LEFT JOIN estados_verificacion AS EV ON U.VerificacionEstadoID = EV.EstadoID
        LEFT JOIN cuentas_beneficiarias AS CB ON T.CuentaBeneficiariaID = CB.CuentaID
        LEFT JOIN tipos_documento AS TD_B ON CB.TitularTipoDocumentoID = TD_B.TipoDocumentoID
        LEFT JOIN tipos_beneficiario AS TB ON CB.TipoBeneficiarioID = TB.TipoBeneficiarioID
        WHERE T.TransaccionID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function uploadUserReceipt(int $transactionId, int $userId, string $dbPath, string $fileHash, int $estadoEnVerificacionID, int $estadoPendienteID): int
    {
        $sql = "UPDATE transacciones SET ComprobanteURL = ?, ComprobanteHash = ?, EstadoID = ?, FechaSubidaComprobante = NOW()
                WHERE TransaccionID = ? AND UserID = ? AND EstadoID IN (?, ?)";

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param(
            "ssiiiii",
            $dbPath,
            $fileHash,
            $estadoEnVerificacionID,
            $transactionId,
            $userId,
            $estadoPendienteID,
            $estadoEnVerificacionID
        );

        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    public function uploadAdminProof(int $transactionId, string $dbPath, int $estadoPagadoID, int $estadoEnProcesoID, float $comisionDestino): int
    {
        $sql = "UPDATE transacciones SET ComprobanteEnvioURL = ?, EstadoID = ?, ComisionDestino = ?
                WHERE TransaccionID = ? AND EstadoID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sdiii", $dbPath, $estadoPagadoID, $comisionDestino, $transactionId, $estadoEnProcesoID);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    public function updateStatus(int $id, int $newStatusID, ?int $requiredStatusID = null): int
    {
        $sql = "UPDATE transacciones SET EstadoID = ? WHERE TransaccionID = ?";
        $paramTypes = "ii";
        $params = [$newStatusID, $id];

        if ($requiredStatusID !== null) {
            $sql .= " AND EstadoID = ?";
            $paramTypes .= "i";
            $params[] = $requiredStatusID;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    public function updateCommission(int $txId, float $newCommission): bool
    {
        $sql = "UPDATE transacciones SET ComisionDestino = ? WHERE TransaccionID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $newCommission, $txId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function cancel(int $transactionId, int $userId, int $estadoCanceladoID, int $estadoPendienteID): int
    {
        $sql = "UPDATE transacciones SET EstadoID = ?
                WHERE TransaccionID = ? AND UserID = ? AND EstadoID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiii", $estadoCanceladoID, $transactionId, $userId, $estadoPendienteID);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    public function findByHash(string $fileHash): ?array
    {
        $sql = "SELECT TransaccionID FROM transacciones WHERE ComprobanteHash = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $fileHash);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function pauseTransaction(int $txId, string $motivo, int $estadoPausadoID): bool
    {
        $sql = "UPDATE transacciones SET EstadoID = ?, MotivoPausa = ?, MensajeReanudacion = NULL WHERE TransaccionID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("isi", $estadoPausadoID, $motivo, $txId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function requestResume(int $txId, int $userId, string $mensaje, int $estadoEnProcesoID): bool
    {
        $sql = "UPDATE transacciones SET EstadoID = ?, MensajeReanudacion = ? 
            WHERE TransaccionID = ? AND UserID = ? AND EstadoID = (SELECT EstadoID FROM estados_transaccion WHERE NombreEstado = 'Pausado')";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("isii", $estadoEnProcesoID, $mensaje, $txId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    // --- MÉTODOS PARA ESTADÍSTICAS ---

    public function countByStatus(array $statusIDs): int
    {
        if (empty($statusIDs))
            return 0;
        $placeholders = implode(',', array_fill(0, count($statusIDs), '?'));
        $sql = "SELECT COUNT(TransaccionID) as total FROM transacciones WHERE EstadoID IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($statusIDs)), ...$statusIDs);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($result['total'] ?? 0);
    }

    public function countCompletedToday(int $estadoPagadoID): int
    {
        $sql = "SELECT COUNT(TransaccionID) as total FROM transacciones WHERE EstadoID = ? AND DATE(FechaTransaccion) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $estadoPagadoID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($result['total'] ?? 0);
    }

    public function getTotalVolume(int $estadoPagadoID): float
    {
        $sql = "SELECT SUM(MontoOrigen) as total_volumen FROM transacciones WHERE EstadoID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $estadoPagadoID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float) ($result['total_volumen'] ?? 0.0);
    }

    public function findEstadoTransaccionIdByName(string $nombreEstado): ?int
    {
        $sql = "SELECT EstadoID FROM estados_transaccion WHERE NombreEstado = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $nombreEstado);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['EstadoID'] ?? null;
    }

    // --- MÉTODOS PARA DASHBOARD ---

    public function getTopCountries(string $direction = 'Destino', int $limit = 5): array
    {
        $sql = "";
        if ($direction === 'Destino') {
            $sql = "SELECT P.NombrePais, COUNT(T.TransaccionID) AS Total
                    FROM transacciones T
                    JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
                    JOIN paises P ON CB.PaisID = P.PaisID
                    GROUP BY P.NombrePais
                    ORDER BY Total DESC
                    LIMIT ?";
        } else {
            $sql = "SELECT P.NombrePais, COUNT(T.TransaccionID) AS Total
                    FROM transacciones T
                    JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
                    JOIN paises P ON TS.PaisOrigenID = P.PaisID
                    GROUP BY P.NombrePais
                    ORDER BY Total DESC
                    LIMIT ?";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getTransactionStats(): array
    {
        $sql = "SELECT
                    (COUNT(TransaccionID) / (DATEDIFF(MAX(DATE(FechaTransaccion)), MIN(DATE(FechaTransaccion))) + 1)) AS PromedioDiario,
                    DATE_FORMAT(FechaTransaccion, '%Y-%m') AS Mes,
                    COUNT(TransaccionID) AS TotalMes
                FROM transacciones
                GROUP BY Mes
                ORDER BY TotalMes DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return ['PromedioDiario' => 0, 'MesMasConcurrido' => 'N/A', 'TotalMesMasConcurrido' => 0];
        }

        return [
            'PromedioDiario' => (float) ($result['PromedioDiario'] ?? 0),
            'MesMasConcurrido' => $result['Mes'] ?? 'N/A',
            'TotalMesMasConcurrido' => (int) ($result['TotalMes'] ?? 0)
        ];
    }

    public function getTopUsers(int $limit = 5): array
    {
        $sql = "SELECT
                    U.UserID,
                    CONCAT(U.PrimerNombre, ' ', U.PrimerApellido) AS NombreCompleto,
                    U.Email,
                    COUNT(T.TransaccionID) AS TotalTransacciones
                FROM transacciones T
                JOIN usuarios U ON T.UserID = U.UserID
                GROUP BY U.UserID, NombreCompleto, U.Email
                ORDER BY TotalTransacciones DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getExportData(): array
    {
        $sql = "SELECT
                    T.TransaccionID,
                    CONCAT(U.PrimerNombre, ' ', U.PrimerApellido) AS ClienteNombre,
                    T.MontoOrigen,
                    TS.ValorTasa,
                    T.MontoDestino,
                    T.ComisionDestino,
                    
                    (SELECT Timestamp FROM logs 
                    WHERE Detalles LIKE CONCAT('%TX ID: ', T.TransaccionID, '%') 
                    AND Accion LIKE 'Admin complet%' 
                    ORDER BY LogID DESC LIMIT 1) as FechaCompletado,
                    
                    COALESCE(CBA.Banco, FP.Nombre) AS BancoOrigenReal,
                    
                    T.BeneficiarioBanco,
                    T.BeneficiarioNumeroCuenta
                FROM transacciones T
                JOIN usuarios U ON T.UserID = U.UserID
                JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
                LEFT JOIN formas_pago FP ON T.FormaPagoID = FP.FormaPagoID
                LEFT JOIN cuentas_bancarias_admin CBA ON T.FormaPagoID = CBA.FormaPagoID AND TS.PaisOrigenID = CBA.PaisID AND CBA.Activo = 1
                ORDER BY T.FechaTransaccion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    // --- MÉTODOS PARA BOT DE CORREOS ---

    public function findPendingByAmount(float $monto, int $horasTolerancia): array
    {
        $sql = "SELECT TransaccionID, UserID, MontoOrigen, Email, PrimerNombre, Telefono 
                FROM transacciones t
                JOIN usuarios u ON t.UserID = u.UserID
                WHERE t.MontoOrigen = ? 
                AND t.EstadoID IN (1, 2) 
                AND t.FechaTransaccion >= DATE_SUB(NOW(), INTERVAL ? HOUR)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $monto, $horasTolerancia);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function isEmailProcessed(string $messageId): bool
    {
        $sql = "SELECT TransaccionID FROM transacciones WHERE EmailMessageID = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $messageId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function updateStatusToProcessingWithProof(int $txId, int $newStatusId, string $proofPath, string $messageId): bool
    {
        $sql = "UPDATE transacciones 
                SET EstadoID = ?, 
                    ComprobanteBancoURL = ?, 
                    EmailMessageID = ?,
                    ComprobanteURL = IF(ComprobanteURL IS NULL OR ComprobanteURL = '', ?, ComprobanteURL),
                    FechaSubidaComprobante = NOW()
                WHERE TransaccionID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("isssi", $newStatusId, $proofPath, $messageId, $proofPath, $txId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}