<?php
namespace App\Repositories;

use App\Database\Database;

class TipoDocumentoRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $sql = "SELECT TipoDocumentoID, NombreDocumento 
                FROM tipos_documento 
                WHERE NombreDocumento != 'RIF'
                ORDER BY FIELD(NombreDocumento, 'RUT', 'Cedula', 'DNI', 'Pasaporte', 'E-RUT', 'Otros')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $result;
    }

    public function findIdByName(string $name): ?int
    {
        $sql = "SELECT TipoDocumentoID FROM tipos_documento WHERE NombreDocumento = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ? (int)$res['TipoDocumentoID'] : null;
    }
}