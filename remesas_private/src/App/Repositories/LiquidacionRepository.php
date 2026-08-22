<?php
namespace App\Repositories;

use App\Database\Database;

class LiquidacionRepository
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db->getConnection();
    }

    /**
     * Creates a new liquidacion record and returns its ID.
     *
     * $moneda es la moneda EN LA QUE SE PAGA la liquidación (la comisión se
     * genera en la moneda de origen de cada orden, ver
     * TransactionRepository::getResellerCommissionInRange). $modo es
     * 'por_moneda' (una liquidación por moneda, sin conversión) o
     * 'consolidado_clp' (una sola liquidación en CLP con conversión).
     */
    public function create(int $userId, float $monto, string $desde, string $hasta, int $cantidad, ?string $notas = null, string $moneda = 'CLP', string $modo = 'por_moneda'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO liquidaciones_revendedor (UserID, Monto, Moneda, ModoLiquidacion, PeriodoDesde, PeriodoHasta, CantidadTransacciones, Notas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idssssis", $userId, $monto, $moneda, $modo, $desde, $hasta, $cantidad, $notas);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Guarda una fila del desglose por moneda de una liquidación.
     *
     * $tasaConversion se expresa SIEMPRE como "CLP por 1 unidad de $moneda",
     * de modo que $montoConvertido = $montoOriginal * $tasaConversion. En modo
     * 'por_moneda' la tasa es 1 y el convertido es igual al original.
     * Esto es lo que deja auditable a qué cambio se pagó.
     */
    public function addDetalleMoneda(int $liquidacionId, string $moneda, float $montoOriginal, ?float $tasaConversion, float $montoConvertido, int $cantidad): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO liquidacion_detalle_moneda
                (LiquidacionID, Moneda, MontoOriginal, TasaConversion, MontoConvertido, Cantidad)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isdddi", $liquidacionId, $moneda, $montoOriginal, $tasaConversion, $montoConvertido, $cantidad);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Desglose por moneda de una liquidación (vacío si no tiene detalle).
     */
    public function getDetalleMoneda(int $liquidacionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT Moneda, MontoOriginal, TasaConversion, MontoConvertido, Cantidad
             FROM liquidacion_detalle_moneda WHERE LiquidacionID = ? ORDER BY Moneda"
        );
        $stmt->bind_param("i", $liquidacionId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Adjunta el desglose por moneda a una lista de liquidaciones, en una sola
     * consulta (evita el N+1 de llamar getDetalleMoneda por fila).
     */
    public function attachDetalles(array $liquidaciones): array
    {
        if (empty($liquidaciones)) {
            return $liquidaciones;
        }
        $ids = array_map(fn($l) => (int) $l['LiquidacionID'], $liquidaciones);
        $in  = implode(',', $ids); // ints casteados, no hay input de usuario acá
        $rows = $this->db->query(
            "SELECT LiquidacionID, Moneda, MontoOriginal, TasaConversion, MontoConvertido, Cantidad
             FROM liquidacion_detalle_moneda WHERE LiquidacionID IN ($in) ORDER BY Moneda"
        )->fetch_all(MYSQLI_ASSOC);

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['LiquidacionID']][] = $r;
        }
        foreach ($liquidaciones as &$l) {
            $l['Detalle'] = $byId[(int) $l['LiquidacionID']] ?? [];
        }
        unset($l);
        return $liquidaciones;
    }

    public function markPaid(int $liquidacionId, ?string $comprobanteUrl, int $adminId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE liquidaciones_revendedor SET Estado='pagada', FechaPago=NOW(), ComprobanteURL=?, AdminUserID=? WHERE LiquidacionID=?"
        );
        $stmt->bind_param("sii", $comprobanteUrl, $adminId, $liquidacionId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT L.*, CONCAT(U.PrimerNombre,' ',U.PrimerApellido) as AdminNombre
             FROM liquidaciones_revendedor L
             LEFT JOIN usuarios U ON L.AdminUserID = U.UserID
             WHERE L.UserID = ?
             ORDER BY L.FechaCreacion DESC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getAll(): array
    {
        $sql = "SELECT L.*, CONCAT(U.PrimerNombre,' ',U.PrimerApellido) as RevendedorNombre,
                       U.Email as RevendedorEmail
                FROM liquidaciones_revendedor L
                JOIN usuarios U ON L.UserID = U.UserID
                ORDER BY L.FechaCreacion DESC";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM liquidaciones_revendedor WHERE LiquidacionID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getConnection(): \mysqli
    {
        return $this->db;
    }
}
