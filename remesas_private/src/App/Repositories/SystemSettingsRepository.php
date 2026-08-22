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

    /**
     * Reclama de forma ATÓMICA la ejecución diaria de una tarea.
     *
     * Escribe $timestamp ('Y-m-d H:i:s') en $key sólo si el valor que ya había
     * NO es del mismo día. Devuelve true si este proceso ganó el claim, false si
     * otro proceso ya lo había reclamado hoy.
     *
     * Todo pasa en una sola sentencia: no hay ventana entre leer y escribir, que
     * es lo que permitía que dos crons solapados aplicaran el ajuste dos veces.
     */
    public function claimDailyRun(string $key, string $timestamp): bool
    {
        $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = IF(
                    DATE(setting_value) = DATE(VALUES(setting_value)),
                    setting_value,
                    VALUES(setting_value)
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $key, $timestamp);
        $stmt->execute();
        // affected_rows: 1 = INSERT nuevo, 2 = UPDATE que cambió el valor
        // (ganamos el claim), 0 = el valor no cambió (ya era de hoy).
        $afectadas = $stmt->affected_rows;
        $stmt->close();
        return $afectadas !== 0;
    }
}