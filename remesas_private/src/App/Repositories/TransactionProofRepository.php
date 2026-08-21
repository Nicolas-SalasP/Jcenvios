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

    /**
     * Máximo de órdenes distintas que pueden usar el mismo hash de archivo,
     * contando canceladas. 2 = el intento original + 1 reintento legítimo
     * tras cancelación. Sin este tope, cancelar repetidamente libera el
     * hash cada vez y el mismo comprobante se puede reciclar sin límite.
     */
    private const MAX_USOS_POR_HASH = 2;

    public function findByHash(string $hash): ?array
    {
        // 1) ¿Hay una orden activa (no cancelada) usando este hash ahora mismo?
        $stmt = $this->db->prepare(
            "SELECT tp.ProofID, tp.TransaccionID, tp.Tipo
             FROM transaction_proofs tp
             JOIN transacciones t ON t.TransaccionID = tp.TransaccionID
             WHERE tp.FileHash = ? AND t.EstadoID != 5 LIMIT 1"
        );
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $active = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($active) {
            return $active;
        }

        // 2) Sin orden activa: ¿ya se usó este hash el máximo de veces permitido
        // en total (contando canceladas)? Si sí, sigue bloqueado aunque todas
        // las órdenes previas estén canceladas.
        $stmtCount = $this->db->prepare(
            "SELECT COUNT(DISTINCT TransaccionID) AS total FROM transaction_proofs WHERE FileHash = ?"
        );
        $stmtCount->bind_param("s", $hash);
        $stmtCount->execute();
        $total = (int) ($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtCount->close();

        if ($total < self::MAX_USOS_POR_HASH) {
            return null;
        }

        $stmtLast = $this->db->prepare(
            "SELECT ProofID, TransaccionID, Tipo FROM transaction_proofs WHERE FileHash = ? ORDER BY ProofID DESC LIMIT 1"
        );
        $stmtLast->bind_param("s", $hash);
        $stmtLast->execute();
        $last = $stmtLast->get_result()->fetch_assoc();
        $stmtLast->close();
        return $last ?: null;
    }
}
