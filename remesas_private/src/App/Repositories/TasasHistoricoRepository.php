<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class TasasHistoricoRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getRateHistoryByDate(int $origenId, int $destinoId, int $days = 30): array
    {
        $sql = "SELECT
                DATE(FechaCambio) AS Fecha,
                AVG(ValorTasa) AS TasaPromedio
            FROM tasas_historico
            WHERE
                PaisOrigenID = ?
                AND PaisDestinoID = ?
                AND FechaCambio >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY Fecha
            ORDER BY Fecha ASC
            LIMIT ?";

        $limit = $days + 5;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiii", $origenId, $destinoId, $days, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(\MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}