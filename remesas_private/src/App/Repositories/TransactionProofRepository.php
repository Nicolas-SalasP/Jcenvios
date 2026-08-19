<?php
namespace App\Repositories;

use App\Database\Database;

class TransactionProofRepository
{
    private $db;

    public const MAX_PROOFS = 4;
    public const TIPO_CLIENT = 'client';
    public const TIPO_ADMIN  = 'admin';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function countProofs(int $txId, string $tipo): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total FROM transaction_proofs WHERE TransaccionID = ? AND Tipo = ?"
        );
        $stmt->bind_param("is", $txId, $tipo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['total'] ?? 0);
    }

    public function addProof(int $txId, string $tipo, string $filePath, ?string $fileHash, int $subidoPor): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transaction_proofs (TransaccionID, Tipo, FilePath, FileHash, SubidoPor)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi", $txId, $tipo, $filePath, $fileHash, $subidoPor);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function getProofs(int $txId, string $tipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT ProofID, FilePath, FileHash, SubidoPor, FechaSubida
             FROM transaction_proofs
             WHERE TransaccionID = ? AND Tipo = ?
             ORDER BY FechaSubida ASC"
        );
        $stmt->bind_param("is", $txId, $tipo);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function findByHash(string $hash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT tp.ProofID, tp.TransaccionID, tp.Tipo
             FROM transaction_proofs tp
             JOIN transacciones t ON t.TransaccionID = tp.TransaccionID
             WHERE tp.FileHash = ? AND t.EstadoID != 5 LIMIT 1"
        );
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
