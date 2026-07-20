-- ============================================================================
-- Migración 011 — Tasas Visuales (imagen)
-- ============================================================================
-- Permite al Admin publicar una imagen de tasas (captura/gráfico) por cada
-- fuente (WhatsApp, Web) para mostrarla en páginas públicas sin login.
-- Es un módulo independiente del editor numérico de tasas existente
-- (public_html/admin/tasas.php) — no lo reemplaza ni lo modifica.
-- Patrón "singleton por tipo": una sola fila por TipoFuente, actualizada
-- con UPDATE (o INSERT ... ON DUPLICATE KEY UPDATE) en lugar de crear
-- múltiples registros históricos.
-- ============================================================================

CREATE TABLE IF NOT EXISTS tasas_imagen (
    Id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    TipoFuente ENUM('whatsapp', 'web') NOT NULL,
    RutaImagen VARCHAR(255) NULL,
    FechaActualizacion DATETIME NULL,
    ActualizadoPor INT UNSIGNED NULL,
    UNIQUE KEY uk_tasas_imagen_tipo (TipoFuente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tasas_imagen (TipoFuente, RutaImagen, FechaActualizacion, ActualizadoPor)
VALUES ('whatsapp', NULL, NULL, NULL), ('web', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE TipoFuente = TipoFuente;
