<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class ContabilidadRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // --- SALDOS ---
    public function getSaldoPorPais(int $paisId): ?array
    {
        $sql = "SELECT s.*, p.NombrePais 
                FROM contabilidad_saldos s
                JOIN paises p ON s.PaisID = p.PaisID
                WHERE s.PaisID = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $paisId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getSaldosDashboard(): array
    {
        $sql = "SELECT p.PaisID, p.NombrePais, p.CodigoMoneda, 
                       s.SaldoID, s.SaldoActual, s.UmbralAlerta
                FROM paises p
                LEFT JOIN contabilidad_saldos s ON p.PaisID = s.PaisID
                WHERE p.Activo = TRUE AND (p.Rol = 'Destino' OR p.Rol = 'Ambos')
                ORDER BY p.NombrePais";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getSaldosBancos(): array
    {
        $sql = "SELECT c.CuentaAdminID, c.Banco, c.Titular, c.SaldoActual, 
                   p.CodigoMoneda, p.NombrePais, p.Rol 
            FROM cuentas_bancarias_admin c
            JOIN paises p ON c.PaisID = p.PaisID
            WHERE c.Activo = 1
            ORDER BY p.NombrePais, c.Banco";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    // --- REGISTRO DE MOVIMIENTOS ---

    public function registrarMovimiento(int $saldoId, ?int $adminId, ?int $txId, string $tipoCodigo, float $monto, float $saldoAnterior, float $saldoNuevo, ?string $descripcion = null): bool
    {
        $sql = "INSERT INTO contabilidad_movimientos 
                (SaldoID, AdminUserID, TransaccionID, TipoMovimientoID, Monto, Descripcion, SaldoAnterior, SaldoNuevo)
                VALUES (?, ?, ?, (SELECT TipoMovimientoID FROM tipos_movimiento WHERE Codigo = ? LIMIT 1), ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        // CORREGIDO: iiisdsdd (La 's' es para la descripción)
        $stmt->bind_param("iiisdsdd", $saldoId, $adminId, $txId, $tipoCodigo, $monto, $descripcion, $saldoAnterior, $saldoNuevo);
        return $stmt->execute();
    }

    public function registrarMovimientoBanco(int $cuentaAdminId, int $adminId, ?int $txId, string $tipoCodigo, float $monto, float $saldoAnterior, float $saldoNuevo, ?string $descripcion = null): bool
    {
        $sql = "INSERT INTO contabilidad_movimientos 
                (CuentaAdminID, AdminUserID, TransaccionID, TipoMovimientoID, Monto, Descripcion, SaldoAnterior, SaldoNuevo)
                VALUES (?, ?, ?, (SELECT TipoMovimientoID FROM tipos_movimiento WHERE Codigo = ? LIMIT 1), ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        // CORREGIDO: iiisdsdd (La 's' es para la descripción)
        $stmt->bind_param("iiisdsdd", $cuentaAdminId, $adminId, $txId, $tipoCodigo, $monto, $descripcion, $saldoAnterior, $saldoNuevo);
        return $stmt->execute();
    }

    public function actualizarSaldo(int $saldoId, float $nuevoSaldo): bool
    {
        $sql = "UPDATE contabilidad_saldos SET SaldoActual = ? WHERE SaldoID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $nuevoSaldo, $saldoId);
        return $stmt->execute();
    }

    public function crearRegistroSaldo(int $paisId, string $moneda): int
    {
        $sql = "INSERT INTO contabilidad_saldos (PaisID, MonedaCodigo, SaldoActual, UmbralAlerta) 
                VALUES (?, ?, 0.00, 50000.00)
                ON DUPLICATE KEY UPDATE PaisID=PaisID";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $paisId, $moneda);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    // --- CONSULTAS PARA HISTORIAL ---

    public function getGastosMensuales(int $saldoId, string $mes, string $anio): float
    {
        $sql = "SELECT SUM(m.Monto) as TotalGastado 
                FROM contabilidad_movimientos m
                JOIN tipos_movimiento tm ON m.TipoMovimientoID = tm.TipoMovimientoID
                WHERE m.SaldoID = ? 
                  AND (tm.Codigo = 'GASTO_TX' OR tm.Codigo = 'GASTO_COMISION' OR tm.Codigo = 'GASTO_VARIO')
                  AND YEAR(m.Timestamp) = ? 
                  AND MONTH(m.Timestamp) = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $saldoId, $anio, $mes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float) ($result['TotalGastado'] ?? 0.0);
    }

    // Historial para PAÍSES (Cajas Destino)
    public function getMovimientosDelMes(int $saldoId, string $mes, string $anio): array
    {
        $sql = "SELECT 
                    m.Timestamp, 
                    tm.Codigo AS TipoMovimiento,
                    tm.NombreVisible,
                    tm.Color, 
                    m.Monto,
                    m.Descripcion,
                    m.TransaccionID,
                    CONCAT(cb.TitularPrimerNombre, ' ', cb.TitularPrimerApellido) AS BeneficiarioNombre,
                    u.PrimerNombre AS AdminNombre,
                    u.PrimerApellido AS AdminApellido,
                    u.Email AS AdminEmail
                FROM contabilidad_movimientos m
                JOIN tipos_movimiento tm ON m.TipoMovimientoID = tm.TipoMovimientoID
                LEFT JOIN transacciones t ON m.TransaccionID = t.TransaccionID
                LEFT JOIN cuentas_beneficiarias cb ON t.CuentaBeneficiariaID = cb.CuentaID
                LEFT JOIN usuarios u ON m.AdminUserID = u.UserID
                WHERE m.SaldoID = ? 
                  AND YEAR(m.Timestamp) = ? 
                  AND MONTH(m.Timestamp) = ?
                ORDER BY m.Timestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $saldoId, $anio, $mes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    // Historial para BANCOS (Cajas Origen)
    public function getMovimientosBancoDelMes(int $cuentaAdminId, string $mes, string $anio): array
    {
        $sql = "SELECT 
                    m.Timestamp, 
                    tm.Codigo AS TipoMovimiento,
                    tm.NombreVisible,
                    tm.Color, 
                    m.Monto,
                    m.Descripcion,
                    m.TransaccionID,
                    u.PrimerNombre AS AdminNombre,
                    u.PrimerApellido AS AdminApellido,
                    u.Email AS AdminEmail
                FROM contabilidad_movimientos m
                JOIN tipos_movimiento tm ON m.TipoMovimientoID = tm.TipoMovimientoID
                LEFT JOIN usuarios u ON m.AdminUserID = u.UserID
                WHERE m.CuentaAdminID = ? 
                  AND YEAR(m.Timestamp) = ? 
                  AND MONTH(m.Timestamp) = ?
                ORDER BY m.Timestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iss", $cuentaAdminId, $anio, $mes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}