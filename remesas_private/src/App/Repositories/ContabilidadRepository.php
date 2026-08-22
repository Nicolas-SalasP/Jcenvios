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

    /**
     * Igual que getSaldoPorPais(), pero bloqueando la fila del saldo hasta el fin
     * de la transacción en curso. Debe usarse DENTRO de una transacción siempre
     * que se vaya a leer SaldoActual para después escribirlo: actualizarSaldo()
     * guarda un valor absoluto precalculado, así que sin el lock dos operaciones
     * simultáneas sobre el mismo país leen el mismo saldo y la segunda pisa el
     * movimiento de la primera.
     *
     * Se bloquea solo `contabilidad_saldos` (no el JOIN con `paises`, que es
     * catálogo de lectura y bloquearlo sería contención inútil).
     */
    public function getSaldoPorPaisForUpdate(int $paisId): ?array
    {
        $sql = "SELECT SaldoID FROM contabilidad_saldos WHERE PaisID = ? LIMIT 1 FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $paisId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return $this->getSaldoPorPais($paisId);
    }

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
        $sql = "SELECT 
                c.CuentaAdminID, 
                c.Banco, 
                c.Titular, 
                c.SaldoActual, 
                p.CodigoMoneda, 
                p.NombrePais, 
                r.NombreRol as Rol,
                c.RolCuentaID
            FROM cuentas_bancarias_admin c
            JOIN paises p ON c.PaisID = p.PaisID
            JOIN roles_cuenta_admin r ON c.RolCuentaID = r.RolID
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
        // Tipos de bind: iiisdsdd (la 's' es para la descripción)
        $stmt->bind_param("iiisdsdd", $saldoId, $adminId, $txId, $tipoCodigo, $monto, $descripcion, $saldoAnterior, $saldoNuevo);
        return $stmt->execute();
    }

    public function registrarMovimientoBanco(int $cuentaAdminId, int $adminId, ?int $txId, string $tipoCodigo, float $monto, float $saldoAnterior, float $saldoNuevo, ?string $descripcion = null): bool
    {
        $sql = "INSERT INTO contabilidad_movimientos 
                (CuentaAdminID, AdminUserID, TransaccionID, TipoMovimientoID, Monto, Descripcion, SaldoAnterior, SaldoNuevo)
                VALUES (?, ?, ?, (SELECT TipoMovimientoID FROM tipos_movimiento WHERE Codigo = ? LIMIT 1), ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        // Tipos de bind: iiisdsdd (la 's' es para la descripción)
        $stmt->bind_param("iiisdsdd", $cuentaAdminId, $adminId, $txId, $tipoCodigo, $monto, $descripcion, $saldoAnterior, $saldoNuevo);
        return $stmt->execute();
    }

    /**
     * Devuelve, por cuenta bancaria admin, cuánto ingreso de venta de esta
     * transacción sigue SIN revertir: SUM(INGRESO_VENTA) - SUM(REVERSA_VENTA).
     *
     * Es la base de que la reversión sea idempotente: si ya se revirtió todo,
     * el neto da 0, el HAVING lo filtra y no devuelve nada (no-op). También
     * cubre el caso de múltiples INGRESO_VENTA sobre la misma orden (pasa si
     * un soft-reject la devuelve a Pendiente de Pago y se vuelve a confirmar).
     *
     * Se lee del libro mayor y no de transacciones.CuentaAdminID a propósito:
     * registrarIngresoVenta() puede terminar sin registrar nada (rollback
     * silencioso si la cuenta no existe), así que asumir que siempre hubo
     * ingreso restaría plata que nunca se sumó.
     *
     * @return array<int, array{CuentaAdminID:int, Neto:string}>
     */
    /**
     * Bloquea los movimientos de esta transacción hasta el fin de la transacción
     * SQL en curso. Debe llamarse DENTRO de una transacción y ANTES de leer el
     * neto con getNetoIngresoVentaPorCuenta().
     *
     * Sin esto, dos reversas concurrentes de la misma transacción (por ejemplo el
     * cliente cancelando y el admin rechazando a la vez) leen ambas Neto > 0 y
     * descuentan las dos: doble descuento de plata real. Con el lock, la segunda
     * espera a que la primera comitee su REVERSA_VENTA y entonces lee neto 0.
     */
    public function lockMovimientosDeTransaccion(int $txId): void
    {
        $sql = "SELECT MovimientoID FROM contabilidad_movimientos
                WHERE TransaccionID = ? FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $txId);
        $stmt->execute();
        $stmt->get_result();
        $stmt->close();
    }

    public function getNetoIngresoVentaPorCuenta(int $txId): array
    {
        $sql = "SELECT m.CuentaAdminID,
                       SUM(CASE
                             WHEN tm.Codigo = 'INGRESO_VENTA' THEN m.Monto
                             WHEN tm.Codigo = 'REVERSA_VENTA' THEN -m.Monto
                             ELSE 0
                           END) AS Neto
                FROM contabilidad_movimientos m
                JOIN tipos_movimiento tm ON m.TipoMovimientoID = tm.TipoMovimientoID
                WHERE m.TransaccionID = ?
                  AND m.CuentaAdminID IS NOT NULL
                  AND tm.Codigo IN ('INGRESO_VENTA', 'REVERSA_VENTA')
                GROUP BY m.CuentaAdminID
                HAVING Neto > 0";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $txId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
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