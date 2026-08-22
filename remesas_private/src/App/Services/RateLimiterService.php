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
     *
     * Depende de la UNIQUE KEY uq_ip_accion (ip, accion) — UNA fila por
     * IP+acción, con `ventana_fin` como estado. Ver migrations/022. Con la
     * clave vieja (ip, accion, ventana_fin) cada intento generaba una clave
     * distinta y el upsert no incrementaba nunca: el limitador era decorativo.
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

        // Un solo statement atómico: inserta la fila si es el primer intento;
        // si ya existe, incrementa dentro de la ventana activa o la reinicia
        // (hits = 1 + ventana nueva) si venció. El reinicio va acá adentro a
        // propósito — un SELECT-then-UPDATE dejaría una ventana de carrera en
        // la que dos requests simultáneos reinician el contador.
        //
        // hits queda además en LAST_INSERT_ID() para poder leer el valor
        // resultante sin un SELECT extra: en la rama UPDATE, LAST_INSERT_ID(x)
        // devuelve x. En la rama INSERT devuelve el AUTO_INCREMENT, por eso se
        // distingue con affected_rows (1 = insert nuevo, 2 = update).
        $sql = "INSERT INTO rate_limit (ip, accion, hits, ventana_fin)
                VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE
                    hits        = LAST_INSERT_ID(IF(ventana_fin > NOW(), hits + 1, 1)),
                    ventana_fin = IF(ventana_fin > NOW(), ventana_fin, VALUES(ventana_fin))";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $ip, $accion, $ventanaFin);
        $stmt->execute();
        $esFilaNueva = ($stmt->affected_rows === 1);
        $hits = $esFilaNueva ? 1 : (int)$conn->insert_id;
        $stmt->close();

        if ($hits <= $maxHits) {
            return;
        }

        // `>` y no `>=`: hits ya incluye el intento actual, así que con
        // maxHits = 10 los intentos 1..10 pasan y el 11 es el que corta.
        // Eso es exactamente lo que dicen los comentarios de LIMITS
        // ("10 intentos/min"): se permiten maxHits, no maxHits + 1.

        // La ventana efectiva puede no ser la que acabamos de calcular (si la
        // fila ya venía con una ventana activa se conservó la vieja), así que
        // se lee para dar el tiempo restante real.
        $stmt2 = $conn->prepare(
            "SELECT ventana_fin FROM rate_limit WHERE ip = ? AND accion = ?"
        );
        $stmt2->bind_param("ss", $ip, $accion);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        $finTs = $row ? strtotime($row['ventana_fin']) : ($ahora + $ventanaSegundos);
        $segundosRestantes = max(0, $finTs - $ahora);
        $minutosRestantes  = max(1, (int)ceil($segundosRestantes / 60));

        throw new \Exception(
            "Demasiados intentos. Inténtalo nuevamente en $minutosRestantes minuto(s).",
            429
        );
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
