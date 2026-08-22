-- ============================================================================
-- Migración 019 — Tasas especiales por cliente (uso único)
-- ============================================================================
-- Permite a un admin asignarle a un cliente puntual una tasa preferencial
-- para una ruta específica, distinta de la tasa pública vigente. Se aplica
-- automáticamente en la siguiente orden que el cliente cree para esa ruta
-- y queda inactiva después de usarse (uso único, no recurrente).
--
-- Tabla aparte (no se sobrescribe nada en `usuarios`) para tener historial
-- de quién asignó qué tasa, cuándo, y en qué orden se terminó usando.
-- ============================================================================

CREATE TABLE IF NOT EXISTS tasas_especiales_cliente (
    TasaEspecialID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    PaisOrigenID INT NOT NULL,
    PaisDestinoID INT NOT NULL,
    ValorTasa DECIMAL(15,5) NOT NULL,
    Activa TINYINT(1) NOT NULL DEFAULT 1,
    AdminID INT NOT NULL,
    FechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaUso DATETIME NULL DEFAULT NULL,
    TransaccionID INT NULL DEFAULT NULL,
    KEY idx_user_activa_ruta (UserID, Activa, PaisOrigenID, PaisDestinoID),
    CONSTRAINT fk_tec_user FOREIGN KEY (UserID) REFERENCES usuarios (UserID) ON DELETE CASCADE,
    CONSTRAINT fk_tec_admin FOREIGN KEY (AdminID) REFERENCES usuarios (UserID),
    CONSTRAINT fk_tec_pais_origen FOREIGN KEY (PaisOrigenID) REFERENCES paises (PaisID),
    CONSTRAINT fk_tec_pais_destino FOREIGN KEY (PaisDestinoID) REFERENCES paises (PaisID),
    CONSTRAINT fk_tec_transaccion FOREIGN KEY (TransaccionID) REFERENCES transacciones (TransaccionID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
