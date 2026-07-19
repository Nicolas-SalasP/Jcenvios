-- ============================================================================
-- Seed de datos SOLO para el pipeline de CI (job e2e-smoke con Playwright).
-- No son datos reales: usuarios y tasa sintéticos con password/hash conocidos
-- para poder loguearse en los tests. No usar en local ni en producción.
-- ============================================================================

-- Cliente verificado (Persona Natural, RolID=2), password: Ci-TestPass123!
INSERT INTO usuarios
    (UserID, PrimerNombre, PrimerApellido, Email, PasswordHash, Telefono, TipoDocumentoID, NumeroDocumento, VerificacionEstadoID, RolID, twofa_enabled)
VALUES
    (900001, 'CI', 'Cliente', 'ci.cliente@example.test', '$2y$10$59t8zqgEuXROsz2gSHPvOujwMYS8hE0AEGEAtJ5n8NqN/eD6KRKuy', '56911110001', 2, 'CI900001', 3, 2, 0);

-- Admin, mismo password
INSERT INTO usuarios
    (UserID, PrimerNombre, PrimerApellido, Email, PasswordHash, Telefono, TipoDocumentoID, NumeroDocumento, VerificacionEstadoID, RolID, twofa_enabled)
VALUES
    (900002, 'CI', 'Admin', 'ci.admin@example.test', '$2y$10$59t8zqgEuXROsz2gSHPvOujwMYS8hE0AEGEAtJ5n8NqN/eD6KRKuy', '56911110002', 2, 'CI900002', 3, 1, 0);

-- Cuenta beneficiaria del cliente CI, en Venezuela (PaisID=3)
INSERT INTO cuentas_beneficiarias
    (CuentaID, UserID, PaisID, Alias, TitularPrimerNombre, TitularPrimerApellido, TitularTipoDocumentoID, TitularNumeroDocumento, NombreBanco, NumeroCuenta, NumeroTelefono)
VALUES
    (900001, 900001, 3, 'CI Beneficiario', 'Beneficiario', 'DePrueba', 2, 'V123456789', 'Banco de Venezuela', '01020123456789012345', '4121234567');

-- Tasa sintética Chile(1) -> Venezuela(3), valor de prueba (no es la tasa real de negocio)
INSERT INTO tasas
    (TasaID, PaisOrigenID, PaisDestinoID, ValorTasa, EsReferencial, MontoMinimo, MontoMaximo, FechaEfectiva, Activa, EsRiesgoso, RutaActiva)
VALUES
    (900001, 1, 3, 0.50000, 1, 0.00, 9999999999.99, CURDATE(), 1, 0, 1);
