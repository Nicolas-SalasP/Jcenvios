<?php
namespace App\Repositories;

use App\Database\Database;

class TutorialRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAllForAdmin(): array
    {
        $sql = "SELECT * FROM tutoriales ORDER BY Orden ASC, TutorialID ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function getActiveForClient(): array
    {
        $sql = "SELECT TutorialID, Titulo, Descripcion, TipoFuente, RutaArchivo, URLExterna, Orden
                FROM tutoriales WHERE Activo = 1 ORDER BY Orden ASC, TutorialID ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM tutoriales WHERE TutorialID = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function create(string $titulo, ?string $descripcion, string $tipoFuente, ?string $rutaArchivo, ?string $urlExterna, int $orden, int $creadoPor): int
    {
        $sql = "INSERT INTO tutoriales (Titulo, Descripcion, TipoFuente, RutaArchivo, URLExterna, Orden, Activo, CreadoPor)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssii", $titulo, $descripcion, $tipoFuente, $rutaArchivo, $urlExterna, $orden, $creadoPor);
        $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function update(int $id, string $titulo, ?string $descripcion, string $tipoFuente, ?string $rutaArchivo, ?string $urlExterna, int $orden): bool
    {
        $sql = "UPDATE tutoriales SET Titulo = ?, Descripcion = ?, TipoFuente = ?, RutaArchivo = ?, URLExterna = ?, Orden = ? WHERE TutorialID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssii", $titulo, $descripcion, $tipoFuente, $rutaArchivo, $urlExterna, $orden, $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM tutoriales WHERE TutorialID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function toggleActivo(int $id): bool
    {
        $sql = "UPDATE tutoriales SET Activo = IF(Activo = 1, 0, 1) WHERE TutorialID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function updateOrden(int $id, int $orden): bool
    {
        $sql = "UPDATE tutoriales SET Orden = ? WHERE TutorialID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $orden, $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getMaxOrden(): int
    {
        $sql = "SELECT COALESCE(MAX(Orden), 0) AS maxOrden FROM tutoriales";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['maxOrden' => 0];
        $stmt->close();
        return (int)($row['maxOrden'] ?? 0);
    }
}
