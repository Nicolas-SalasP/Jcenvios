-- Rate limiting por IP para endpoints sensibles
CREATE TABLE IF NOT EXISTS `rate_limit` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(45)     NOT NULL,
    `accion`     VARCHAR(64)     NOT NULL,
    `hits`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `ventana_fin` DATETIME       NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ip_accion_ventana` (`ip`, `accion`, `ventana_fin`),
    INDEX `idx_ventana_fin` (`ventana_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
