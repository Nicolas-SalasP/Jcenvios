<?php
namespace App\Database;

use mysqli;
use Exception;

class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión: " . $this->connection->connect_error);
            }

            // Fijar utf8mb4: sin esto la conexión queda en latin1 (default) y los datos
            // acentuados (ej. "Perú") se devuelven como bytes latin1 inválidos en UTF-8,
            // lo que hace fallar json_encode en el API (error "Malformed UTF-8").
            $this->connection->set_charset('utf8mb4');
        } catch (\Throwable $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            throw new Exception("Error interno de conexión a base de datos.");
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    public function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public function prepare(string $sql): \mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $this->connection->error);
        }
        return $stmt;
    }
}