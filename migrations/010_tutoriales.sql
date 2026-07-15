-- ============================================================================
-- Migración 010 — Módulo de Tutoriales (video)
-- ============================================================================
-- Permite al Admin publicar tutoriales en video para los clientes, ya sea
-- subiendo un archivo de video al servidor o enlazando un video externo
-- (YouTube/Vimeo). Se prefiere URL externa quan sea posible para no
-- consumir espacio en disco del hosting compartido.
-- ============================================================================

CREATE TABLE IF NOT EXISTS tutoriales (
    TutorialID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Titulo VARCHAR(150) NOT NULL,
    Descripcion TEXT NULL,
    TipoFuente ENUM('archivo', 'url') NOT NULL DEFAULT 'url',
    RutaArchivo VARCHAR(255) NULL,
    URLExterna VARCHAR(500) NULL,
    Orden INT NOT NULL DEFAULT 0,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    FechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CreadoPor INT UNSIGNED NULL,
    INDEX idx_tutoriales_activo_orden (Activo, Orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
