-- ============================================================================
-- Migración 005 — Tabla transaction_proofs (múltiples comprobantes por orden)
-- ============================================================================
-- Permite hasta 4 comprobantes por orden tanto para clientes (tipo='client')
-- como para admin/operadores (tipo='admin').
-- ============================================================================

CREATE TABLE IF NOT EXISTS transaction_proofs (
    ProofID       INT          NOT NULL AUTO_INCREMENT,
    TransaccionID INT          NOT NULL,
    Tipo          ENUM('client','admin') NOT NULL DEFAULT 'client',
    FilePath      VARCHAR(500) NOT NULL,
    FileHash      VARCHAR(64)  NULL,
    SubidoPor     INT          NULL,
    FechaSubida   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ProofID),
    KEY idx_tx_tipo (TransaccionID, Tipo),
    KEY idx_file_hash (FileHash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
