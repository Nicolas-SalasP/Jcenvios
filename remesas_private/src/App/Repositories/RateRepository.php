<?php
namespace App\Repositories;

use App\Database\Database;
use Exception;

class RateRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * NUEVO: Actualiza todas las tasas referenciales masivamente (Usado por el Cron)
     */
    public function updateReferentialRates(float $nuevoValor, float $porcentaje): bool
    {
        $sql = "UPDATE tasas 
                SET ValorTasa = ?, 
                    PorcentajeAjuste = ?, 
                    FechaEfectiva = NOW() 
                WHERE EsReferencial = 1 AND Activa = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("dd", $nuevoValor, $porcentaje);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function findAllReferentialRates(): array
    {
        $sql = "SELECT TasaID, PaisOrigenID, PaisDestinoID, ValorTasa, MontoMinimo, MontoMaximo, PorcentajeAjuste 
                FROM tasas 
                WHERE EsReferencial = 1 AND Activa = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }

    public function findCurrentRate(int $origenID, int $destinoID, float $montoOrigen = 0): ?array
    {
        $sql = "SELECT TasaID, ValorTasa, EsReferencial, PorcentajeAjuste, MontoMinimo, MontoMaximo 
                FROM tasas 
                WHERE PaisOrigenID = ? AND PaisDestinoID = ? 
                AND Activa = 1 ";

        if ($montoOrigen > 0) {
            $sql .= " AND ? >= MontoMinimo AND ? <= MontoMaximo ";
            $sql .= " ORDER BY EsReferencial DESC, FechaEfectiva DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("iidd", $origenID, $destinoID, $montoOrigen, $montoOrigen);
        } else {
            $sql .= " ORDER BY EsReferencial DESC, MontoMinimo ASC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $origenID, $destinoID);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    }

    public function getRouteLimits(int $origenID, int $destinoID): array
    {
        $sql = "SELECT MIN(MontoMinimo) as min_monto, MAX(MontoMaximo) as max_monto 
                FROM tasas 
                WHERE PaisOrigenID = ? AND PaisDestinoID = ? AND Activa = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $origenID, $destinoID);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return [
            'min' => (float) ($res['min_monto'] ?? 0),
            'max' => (float) ($res['max_monto'] ?? 0)
        ];
    }

    public function findReferentialRate(int $origenID, int $destinoID): ?array
    {
        $sql = "SELECT TasaID, ValorTasa FROM tasas 
                WHERE PaisOrigenID = ? AND PaisDestinoID = ? 
                AND EsReferencial = 1 AND Activa = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $origenID, $destinoID);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function getRatesByRoute(int $origenID, int $destinoID): array
    {
        $sql = "SELECT * FROM tasas WHERE PaisOrigenID = ? AND PaisDestinoID = ? AND Activa = 1 ORDER BY EsReferencial DESC, MontoMinimo ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $origenID, $destinoID);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }

    public function clearReferentialFlag(int $origenID, int $destinoID): void
    {
        $sql = "UPDATE tasas SET EsReferencial = 0 WHERE PaisOrigenID = ? AND PaisDestinoID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $origenID, $destinoID);
        $stmt->execute();
        $stmt->close();
    }

    public function updateRateValue(int $tasaId, float $nuevoValor, float $montoMin, float $montoMax, int $esRef, float $porcentaje): bool
    {
        $sql = "UPDATE tasas SET ValorTasa = ?, MontoMinimo = ?, MontoMaximo = ?, EsReferencial = ?, PorcentajeAjuste = ?, FechaEfectiva = NOW() WHERE TasaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("dddidi", $nuevoValor, $montoMin, $montoMax, $esRef, $porcentaje, $tasaId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createRate(int $origenId, int $destinoId, float $valor, float $montoMin, float $montoMax, int $esRef, float $porcentaje): int
    {
        $sql = "INSERT INTO tasas (PaisOrigenID, PaisDestinoID, ValorTasa, MontoMinimo, MontoMaximo, EsReferencial, PorcentajeAjuste, FechaEfectiva, Activa) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiddidd", $origenId, $destinoId, $valor, $montoMin, $montoMax, $esRef, $porcentaje);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function logRateChange(int $tasaId, int $origenId, int $destinoId, float $valor, float $montoMin, float $montoMax): bool
    {
        $sql = "INSERT INTO tasas_historico (TasaID_Referencia, PaisOrigenID, PaisDestinoID, ValorTasa, MontoMinimo, MontoMaximo, FechaCambio) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iidddd", $tasaId, $origenId, $destinoId, $valor, $montoMin, $montoMax);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function delete(int $tasaId): bool
    {
        $sql = "UPDATE tasas SET Activa = 0 WHERE TasaID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $tasaId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function checkOverlap(int $origenId, int $destinoId, float $min, float $max, int $excludeTasaId = 0): bool
    {
        $sql = "SELECT TasaID FROM tasas 
                WHERE PaisOrigenID = ? AND PaisDestinoID = ? 
                AND TasaID != ? AND Activa = 1
                AND (MontoMinimo < ? AND MontoMaximo > ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiidd", $origenId, $destinoId, $excludeTasaId, $max, $min);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getMinMaxRates(int $origenID, int $destinoID): ?array
    {
        $sql = "SELECT MIN(ValorTasa) as MinTasa, MAX(ValorTasa) as MaxTasa 
                FROM tasas 
                WHERE PaisOrigenID = ? AND PaisDestinoID = ? AND Activa = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $origenID, $destinoID);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }
}