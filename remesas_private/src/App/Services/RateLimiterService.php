<?php
namespace App\Services;

use App\Database\Database;

class RateLimiterService
{
    private Database $db;

    // Límites por acción: [max_hits, ventana_en_segundos]
    private const LIMITS = [
        'loginUser'            => [10,  60],   // 10 intentos/min por IP
        'requestPasswordReset' => [5,  300],   // 5 solicitudes/5min
        'send2faCode'          => [6,  300],   // 6 códigos/5min (evita Twilio drain)
        'resend2faCode'        => [6,  300],
        'registerUser'         => [5,  300],
        'submitContactForm'    => [10, 300],
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Verifica y registra un intento. Lanza excepción si se supera el límite.
     */
    public function check(string $accion, ?string $ip = null): void
    {
        if (!isset(self::LIMITS[$accion])) {
            return;
        }

        [$maxHits, $ventanaSegundos] = self::LIMITS[$accion];
        $ip = $ip ?? $this->getClientIp();
        $ahora = time();
        $ventanaFin = date('Y-m-d H:i:s', $ahora + $ventanaSegundos);

        $conn = $this->db->getConnection();

        // INSERT o incrementa hits atómicamente dentro de la ventana activa
        $sql = "INSERT INTO rate_limit (ip, accion, hits, ventana_fin)
                VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE
                    hits = IF(ventana_fin > NOW(), hits + 1, 1),
                    ventana_fin = IF(ventana_fin > NOW(), ventana_fin, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $ip, $accion, $ventanaFin, $ventanaFin);
        $stmt->execute();
        $stmt->close();

        // Leer el estado actual
        $stmt2 = $conn->prepare(
            "SELECT hits, ventana_fin FROM rate_limit
             WHERE ip = ? AND accion = ? AND ventana_fin > NOW()
             ORDER BY ventana_fin DESC LIMIT 1"
        );
        $stmt2->bind_param("ss", $ip, $accion);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($row && (int)$row['hits'] > $maxHits) {
            $segundosRestantes = max(0, strtotime($row['ventana_fin']) - $ahora);
            $minutosRestantes  = ceil($segundosRestantes / 60);
            throw new \Exception(
                "Demasiados intentos. Inténtalo nuevamente en $minutosRestantes minuto(s).",
                429
            );
        }
    }

    private function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
