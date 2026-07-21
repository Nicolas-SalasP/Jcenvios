<?php
namespace App\Repositories;

use App\Database\Database;

class RolRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findAllAssignable(): array
    {
        $sql = "SELECT RolID, NombreRol FROM roles WHERE NombreRol != 'SuperAdmin' ORDER BY NombreRol";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
    
    public function findAssignableUserRoles(): array
    {
        $sql = "SELECT RolID, NombreRol FROM roles 
                WHERE NombreRol IN ('Persona Natural', 'Empresa') 
                ORDER BY NombreRol";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function findIdByName(string $nombreRol): ?int
    {
         $sql = "SELECT RolID FROM roles WHERE NombreRol = ? LIMIT 1";
         $stmt = $this->db->prepare($sql);
         $stmt->bind_param("s", $nombreRol);
         $stmt->execute();
         $result = $stmt->get_result()->fetch_assoc();
         $stmt->close();
         return $result['RolID'] ?? null;
    }

    public function findNameById(int $rolId): ?string
    {
         $sql = "SELECT NombreRol FROM roles WHERE RolID = ? LIMIT 1";
         $stmt = $this->db->prepare($sql);
         $stmt->bind_param("i", $rolId);
         $stmt->execute();
         $result = $stmt->get_result()->fetch_assoc();
         $stmt->close();
         return $result['NombreRol'] ?? null;
    }
}