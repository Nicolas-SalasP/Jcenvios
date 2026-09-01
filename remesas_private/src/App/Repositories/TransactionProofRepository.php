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
     * ¿Este comprobante ya está en uso por una orden VIVA?
     *
     * Solo bloquea mientras exista una orden no cancelada (EstadoID != 5) con
     * el mismo archivo: eso es lo que impide pagar dos órdenes con un único
     * comprobante, que es el fraude real a evitar.
     *
     * NO hay tope de reusos históricos. Antes existía uno (máximo 2 órdenes
     * distintas por hash, contando canceladas): la idea era frenar el reciclaje
     * por cancelaciones repetidas, pero en la práctica castigaba al cliente
     * honesto —el que se equivoca de orden, cancela y quiere volver a subir el
     * mismo comprobante en la correcta— que es el caso frecuente. Se retiró por
     * decisión del usuario el 2026-09-01. Una orden cancelada libera su
     * comprobante sin límite de veces.
     */
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
        $active = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $active ?: null;
    }
}
