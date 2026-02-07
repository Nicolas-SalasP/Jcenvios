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
        $sqlTasa = "SELECT EsRiesgoso FROM tasas WHERE TasaID = ?";
        $stmtTasa = $this->db->prepare($sqlTasa);
        $stmtTasa->bind_param("i", $data['tasaID']);
        $stmtTasa->execute();
        $resTasa = $stmtTasa->get_result()->fetch_assoc();
        $esRiesgoso = $resTasa['EsRiesgoso'] ?? 0;
        $stmtTasa->close();
        $estadoInicialID = ($esRiesgoso == 1) ? 7 : 1;
        $sql = "INSERT INTO transacciones (
                UserID, CuentaBeneficiariaID, TasaID_Al_Momento, 
                MontoOrigen, MonedaOrigen, MontoDestino, MonedaDestino, 
                EstadoID, FormaPagoID, 
                BeneficiarioNombre, BeneficiarioDocumento, BeneficiarioBanco, 
                BeneficiarioNumeroCuenta, BeneficiarioCCI, BeneficiarioTelefono
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $beneficiarioCCI = $data['beneficiarioCCI'] ?? null;

        $stmt->bind_param(
            "iiidsdsiissssss",
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
            $beneficiarioCCI,
            $data['beneficiarioTelefono']
        );

        if (!$stmt->execute()) {
            error_log("Error al crear la transacción: " . $stmt->error);
            throw new Exception("No se pudo registrar la orden.");
        }

        $newId = $stmt->insert_id;
        $stmt->close();

        return $newId;
    }

    public function getStatus($txId)
    {
        $stmt = $this->db->prepare("SELECT EstadoID FROM transacciones WHERE TransaccionID = ?");
        $stmt->bind_param("i", $txId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res['EstadoID'] ?? 1;
    }

    public function getById(int $txId): ?array
    {
        $sql = "SELECT * FROM transacciones WHERE TransaccionID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $txId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getAllByUser(int $userId): array
    {
        $sql = "SELECT
                    T.TransaccionID, T.FechaTransaccion, T.MontoOrigen, T.MonedaOrigen,
                    T.MontoDestino, T.MonedaDestino, T.ComprobanteURL, T.ComprobanteEnvioURL,
                    
                    -- DATOS DE LA CUENTA (SNAPSHOT) --
                    T.BeneficiarioNombre, 
                    T.BeneficiarioNombre AS BeneficiarioAlias,
                    T.BeneficiarioDocumento, 
                    T.BeneficiarioBanco, 
                    T.BeneficiarioNumeroCuenta, 
                    T.BeneficiarioTelefono,
                    
                    T.FormaPagoID, 
                    T.EstadoID,
                    T.CuentaBeneficiariaID, 
                    P.NombrePais AS PaisDestino,
                    ET.NombreEstado AS EstadoNombre,
                    T.MotivoPausa, T.MensajeReanudacion
                FROM transacciones AS T
                JOIN cuentas_beneficiarias AS C ON T.CuentaBeneficiariaID = C.CuentaID
                JOIN paises AS P ON C.PaisID = P.PaisID
                LEFT JOIN estados_transaccion AS ET ON T.EstadoID = ET.EstadoID
                WHERE T.UserID = ?
                ORDER BY T.FechaTransaccion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getFullTransactionDetails(int $transactionId): ?array
    {
        $sql = "SELECT
            T.TransaccionID, T.UserID, T.CuentaBeneficiariaID, T.TasaID_Al_Momento,
            T.MontoOrigen, T.MonedaOrigen, T.MontoDestino, T.ComisionDestino, T.MonedaDestino,
            T.FechaTransaccion, T.ComprobanteURL, T.ComprobanteEnvioURL,
            T.RutTitularOrigen, 
            T.NombreTitularOrigen,
            U.PrimerNombre, U.PrimerApellido, U.Email, U.NumeroDocumento, U.Telefono, U.FotoPerfilURL,
            TD_U.NombreDocumento AS UsuarioTipoDocumentoNombre,
            R.NombreRol AS UsuarioRolNombre,
            EV.NombreEstado AS UsuarioVerificacionEstadoNombre,
            
            T.BeneficiarioNombre,
            T.BeneficiarioDocumento,
            T.BeneficiarioBanco,
            T.BeneficiarioNumeroCuenta,
            T.BeneficiarioCCI,
            T.BeneficiarioTelefono,

            TS.ValorTasa,
            TS.PaisOrigenID,
            
            ET.EstadoID, ET.NombreEstado AS Estado,
            FP.FormaPagoID, FP.Nombre AS FormaDePago,

            CB.PaisID AS PaisDestinoID,
            TD_B.NombreDocumento AS BeneficiarioTipoDocumentoNombre,
            TB.Nombre AS BeneficiarioTipoNombre,
            
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

    public function updateBeneficiarySnapshot(int $txId, array $data): bool
    {
        $sql = "UPDATE transacciones SET 
                    BeneficiarioNombre = ?,
                    BeneficiarioDocumento = ?,
                    BeneficiarioBanco = ?,
                    BeneficiarioNumeroCuenta = ?,
                    BeneficiarioTelefono = ?
                WHERE TransaccionID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "sssssi",
            $data['nombre'],
            $data['documento'],
            $data['banco'],
            $data['cuenta'],
            $data['telefono'],
            $txId
        );

        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function uploadUserReceipt(int $transactionId, int $userId, string $dbPath, string $fileHash, int $estadoEnVerificacionID, int $estadoPendienteID, string $rutTitular, string $nombreTitular): int
    {
        $sql = "UPDATE transacciones SET ComprobanteURL = ?, ComprobanteHash = ?, EstadoID = ?, FechaSubidaComprobante = NOW(), RutTitularOrigen = ?, NombreTitularOrigen = ?
            WHERE TransaccionID = ? AND UserID = ? AND EstadoID IN (?, ?)";

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param(
            "ssissiiii",
            $dbPath,
            $fileHash,
            $estadoEnVerificacionID,
            $rutTitular,
            $nombreTitular,
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

    public function findByAdminProofHash(string $hash): ?array
    {
        $sql = "SELECT TransaccionID FROM transacciones WHERE ComprobanteEnvioHash = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function uploadAdminProof(int $transactionId, string $dbPath, string $fileHash, int $estadoPagadoID, int $estadoEnProcesoID, float $comisionDestino): int
    {
        $sql = "UPDATE transacciones 
            SET ComprobanteEnvioURL = ?, ComprobanteEnvioHash = ?, EstadoID = ?, ComisionDestino = ?
            WHERE TransaccionID = ? AND EstadoID = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssidii", $dbPath, $fileHash, $estadoPagadoID, $comisionDestino, $transactionId, $estadoEnProcesoID);

        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    public function updateStatus(int $id, int $newStatusID, $requiredStatusID = null): int
    {
        $sql = "UPDATE transacciones SET EstadoID = ? WHERE TransaccionID = ?";
        $types = "ii";
        $params = [$newStatusID, $id];

        if ($requiredStatusID !== null) {
            if (is_array($requiredStatusID)) {
                $placeholders = implode(',', array_fill(0, count($requiredStatusID), '?'));
                $sql .= " AND EstadoID IN ($placeholders)";
                $types .= str_repeat("i", count($requiredStatusID));
                $params = array_merge($params, $requiredStatusID);
            } else {
                $sql .= " AND EstadoID = ?";
                $types .= "i";
                $params[] = $requiredStatusID;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
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

    public function cancel(int $transactionId, int $userId, int $estadoCanceladoID, array $allowedStatusIds): int
    {
        if (empty($allowedStatusIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($allowedStatusIds), '?'));

        $sql = "UPDATE transacciones SET EstadoID = ?
                WHERE TransaccionID = ? AND UserID = ? AND EstadoID IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $types = "iii" . str_repeat("i", count($allowedStatusIds));
        $params = array_merge([$estadoCanceladoID, $transactionId, $userId], $allowedStatusIds);
        $stmt->bind_param($types, ...$params);

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

    public function getExportData(?string $startDate = null, ?string $endDate = null, ?int $originId = null, ?int $destId = null): array
    {
        $sql = "SELECT
                    T.TransaccionID,
                    T.FechaTransaccion,
                    CONCAT(U.PrimerNombre, ' ', U.PrimerApellido) AS ClienteNombre,
                    U.NumeroDocumento AS ClienteDocumento,
                    T.MontoOrigen,
                    P_Orig.NombrePais AS PaisOrigen,
                    TS.ValorTasa,
                    T.MontoDestino,
                    P_Dest.NombrePais AS PaisDestino,
                    T.ComisionDestino,
                    (SELECT Timestamp FROM logs 
                    WHERE Detalles LIKE CONCAT('%TX ID: ', T.TransaccionID, '%') 
                    AND Accion LIKE 'Admin complet%' 
                    ORDER BY LogID DESC LIMIT 1) as FechaCompletado,
                    COALESCE(CBA_IN.Banco, FP.Nombre) AS BancoOrigenCliente,
                    CBA_OUT.Banco AS BancoSalidaAdmin,
                    T.BeneficiarioNombre,
                    T.BeneficiarioBanco,
                    T.BeneficiarioNumeroCuenta
                FROM transacciones T
                JOIN usuarios U ON T.UserID = U.UserID
                JOIN tasas TS ON T.TasaID_Al_Momento = TS.TasaID
                JOIN paises P_Orig ON TS.PaisOrigenID = P_Orig.PaisID
                JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
                JOIN paises P_Dest ON CB.PaisID = P_Dest.PaisID
                LEFT JOIN formas_pago FP ON T.FormaPagoID = FP.FormaPagoID
                LEFT JOIN cuentas_bancarias_admin CBA_IN ON T.FormaPagoID = CBA_IN.FormaPagoID AND TS.PaisOrigenID = CBA_IN.PaisID AND CBA_IN.Activo = 1
                LEFT JOIN cuentas_bancarias_admin CBA_OUT ON T.CuentaAdminSalidaID = CBA_OUT.CuentaAdminID
                
                WHERE T.EstadoID = 4";

        $params = [];
        $types = "";

        if ($startDate && $endDate) {
            $sql .= " AND DATE(T.FechaTransaccion) BETWEEN ? AND ?";
            $types .= "ss";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        if ($originId) {
            $sql .= " AND TS.PaisOrigenID = ?";
            $types .= "i";
            $params[] = $originId;
        }

        if ($destId) {
            $sql .= " AND CB.PaisID = ?";
            $types .= "i";
            $params[] = $destId;
        }

        $sql .= " ORDER BY T.FechaTransaccion DESC";

        $stmt = $this->db->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

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
    public function isAccountUsedInCompletedOrders(int $cuentaId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM transacciones 
                WHERE CuentaBeneficiariaID = ? AND EstadoID IN (4, 5)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res['total'] > 0;
    }

    public function migratePendingOrdersToNewAccount(int $oldCuentaId, int $newCuentaId): void
    {
        $sql = "UPDATE transacciones 
                SET CuentaBeneficiariaID = ? 
                WHERE CuentaBeneficiariaID = ? AND EstadoID IN (1, 2, 3, 6, 7)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $newCuentaId, $oldCuentaId);
        $stmt->execute();
    }

    public function updateCuentaSalida(int $txId, int $cuentaId): bool
    {
        $sql = "UPDATE transacciones SET CuentaAdminSalidaID = ? WHERE TransaccionID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $cuentaId, $txId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getPendingTransactionsByAccountId(int $cuentaBeneficiariaId): array
    {
        $sql = "SELECT TransaccionID FROM transacciones 
            WHERE CuentaBeneficiariaID = ? 
            AND EstadoID IN (1, 6, 7)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaBeneficiariaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getResellerStats(int $userId, string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT 
                T.MonedaOrigen, 
                P.NombrePais as PaisDestino,
                SUM(T.ComisionRevendedor) as TotalGanado,
                COUNT(T.TransaccionID) as CantidadEnvios
            FROM transacciones T
            JOIN cuentas_beneficiarias CB ON T.CuentaBeneficiariaID = CB.CuentaID
            JOIN paises P ON CB.PaisID = P.PaisID
            WHERE T.UserID = ? 
            AND T.EstadoID IN (4, 5)
            AND T.FechaTransaccion BETWEEN ? AND ?
            GROUP BY T.MonedaOrigen, P.NombrePais";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $userId, $fechaInicio, $fechaFin);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $result;
    }
    public function updatePaymentDetails(int $id, string $imgName, string $rut, string $nombre): bool
    {
        $sql = "UPDATE transacciones 
                SET ComprobanteURL = ?, 
                    RutTitularOrigen = ?, 
                    NombreTitularOrigen = ?,
                    EstadoID = 2,
                    FechaSubidaComprobante = NOW()
                WHERE TransaccionID = ?";

        $stmt = $this->db->prepare($sql);

        $stmt->bind_param("sssi", $imgName, $rut, $nombre, $id);

        $result = $stmt->execute();
        if (!$result) {
            error_log("Error DB updatePaymentDetails: " . $stmt->error);
        }
        $stmt->close();

        return $result;
    }
}