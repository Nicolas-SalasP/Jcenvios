<?php
namespace App\Repositories;

use App\Database\Database;

class TasasImagenRepository
{
    private Database $db;

    private const TIPOS_VALIDOS = ['whatsapp', 'web'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function isTipoValido(string $tipoFuente): bool
    {
        return in_array($tipoFuente, self::TIPOS_VALIDOS, true);
    }

    public function findByTipo(string $tipoFuente): ?array
    {
        $sql = "SELECT * FROM tasas_imagen WHERE TipoFuente = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $tipoFuente);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function getAll(): array
    {
        $sql = "SELECT * FROM tasas_imagen ORDER BY TipoFuente ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function upsertImagen(string $tipoFuente, string $rutaImagen, int $actualizadoPor): bool
    {
        $sql = "INSERT INTO tasas_imagen (TipoFuente, RutaImagen, FechaActualizacion, ActualizadoPor)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE RutaImagen = VALUES(RutaImagen),
                                        FechaActualizacion = VALUES(FechaActualizacion),
                                        ActualizadoPor = VALUES(ActualizadoPor)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssi", $tipoFuente, $rutaImagen, $actualizadoPor);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
}
