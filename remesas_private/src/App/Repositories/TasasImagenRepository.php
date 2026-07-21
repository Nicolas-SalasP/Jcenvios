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

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM tasas_imagen WHERE Id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function getAllByTipo(string $tipoFuente): array
    {
        $sql = "SELECT * FROM tasas_imagen WHERE TipoFuente = ? ORDER BY FechaActualizacion DESC, Id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $tipoFuente);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $sql = "SELECT * FROM tasas_imagen ORDER BY TipoFuente ASC, FechaActualizacion DESC, Id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function insertImagen(string $tipoFuente, string $rutaImagen, ?string $titulo, ?string $descripcion, int $actualizadoPor): int
    {
        $sql = "INSERT INTO tasas_imagen (TipoFuente, RutaImagen, Titulo, Descripcion, FechaActualizacion, ActualizadoPor)
                VALUES (?, ?, ?, ?, NOW(), ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssi", $tipoFuente, $rutaImagen, $titulo, $descripcion, $actualizadoPor);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function deleteById(int $id): bool
    {
        $sql = "DELETE FROM tasas_imagen WHERE Id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function deleteAll(): array
    {
        $rutas = array_column($this->getAll(), 'RutaImagen');
        $this->db->prepare("DELETE FROM tasas_imagen")->execute();
        return array_values(array_filter($rutas));
    }
}
