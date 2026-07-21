-- ============================================================================
-- Migración 018 — Persistencia de mensajes del formulario de contacto
-- ============================================================================
-- Bug encontrado en QA: ClientController::handleContactForm() dependía 100%
-- de que el email se enviara con éxito. Si el envío fallaba (SMTP caído,
-- credenciales, timeout — cosas reales en producción), el visitante recibía
-- un 500 y su mensaje se perdía por completo, sin quedar guardado en
-- ningún lado. Ahora el mensaje se guarda ANTES de intentar el email, así
-- que un fallo de envío ya no pierde el mensaje del usuario.
-- ============================================================================

CREATE TABLE IF NOT EXISTS mensajes_contacto (
    Id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Email VARCHAR(255) NOT NULL,
    Asunto VARCHAR(200) NOT NULL,
    Mensaje TEXT NOT NULL,
    EmailEnviado TINYINT(1) NOT NULL DEFAULT 0,
    FechaEnvio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Leido TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
