<?php
namespace App\Repositories;

use App\Database\Database;

class ContactMessageRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $nombre, string $email, string $asunto, string $mensaje): int
    {
        $sql = "INSERT INTO mensajes_contacto (Nombre, Email, Asunto, Mensaje) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssss", $nombre, $email, $asunto, $mensaje);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function markEmailEnviado(int $id): void
    {
        $sql = "UPDATE mensajes_contacto SET EmailEnviado = 1 WHERE Id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}
