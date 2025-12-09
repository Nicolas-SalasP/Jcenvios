<?php
namespace App\Repositories;

use App\Database\Database;

class SystemSettingsRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getValue(string $key): ?string
    {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['setting_value'] ?? null;
    }

    public function updateValue(string $key, string $value): bool
    {
        $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $key, $value);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}