-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: jcenvios
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `_migrations_applied`
--

DROP TABLE IF EXISTS `_migrations_applied`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_migrations_applied` (
  `Filename` varchar(255) NOT NULL,
  `AppliedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `beneficiarios_auditoria`
--

DROP TABLE IF EXISTS `beneficiarios_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beneficiarios_auditoria` (
  `AuditoriaID` int(11) NOT NULL AUTO_INCREMENT,
  `CuentaID` int(11) NOT NULL,
  `ModificadoPorID` int(11) NOT NULL COMMENT 'ID de quien ejecut├│ la acci├│n final',
  `SolicitudID` int(11) DEFAULT NULL COMMENT 'NULL si fue creado/modificado por el mismo cliente',
  `TipoEvento` enum('Creacion','Modificacion','Eliminacion') NOT NULL,
  `EstadoAnterior` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot de los datos antes del cambio' CHECK (json_valid(`EstadoAnterior`)),
  `EstadoNuevo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot de los datos despu├®s del cambio' CHECK (json_valid(`EstadoNuevo`)),
  `FechaEvento` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`AuditoriaID`),
  KEY `fk_auditoria_cuenta` (`CuentaID`),
  KEY `fk_auditoria_modificador` (`ModificadoPorID`),
  KEY `fk_auditoria_solicitud` (`SolicitudID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `beneficiarios_solicitudes_cambio`
--

DROP TABLE IF EXISTS `beneficiarios_solicitudes_cambio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beneficiarios_solicitudes_cambio` (
  `SolicitudID` int(11) NOT NULL AUTO_INCREMENT,
  `CuentaID` int(11) NOT NULL COMMENT 'ID del beneficiario a editar',
  `UserID` int(11) NOT NULL COMMENT 'Cliente due├▒o del beneficiario',
  `AdminSolicitanteID` int(11) NOT NULL COMMENT 'ID del administrador que pide el cambio',
  `CamposSolicitados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array de campos que se desean cambiar' CHECK (json_valid(`CamposSolicitados`)),
  `Motivo` text NOT NULL COMMENT 'Explicaci├│n para el cliente',
  `Estado` enum('Pendiente','Aprobada','Rechazada','Ejecutada') NOT NULL DEFAULT 'Pendiente',
  `FechaSolicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaRespuesta` datetime DEFAULT NULL,
  PRIMARY KEY (`SolicitudID`),
  KEY `fk_solicitud_cuenta` (`CuentaID`),
  KEY `fk_solicitud_user` (`UserID`),
  KEY `fk_solicitud_admin` (`AdminSolicitanteID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bot_sessions`
--

DROP TABLE IF EXISTS `bot_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bot_sessions` (
  `telefono` varchar(50) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `data_temporal` text DEFAULT NULL,
  PRIMARY KEY (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contabilidad_movimientos`
--

DROP TABLE IF EXISTS `contabilidad_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_movimientos` (
  `MovimientoID` int(11) NOT NULL AUTO_INCREMENT,
  `SaldoID` int(11) DEFAULT NULL,
  `CuentaAdminID` int(11) DEFAULT NULL,
  `AdminUserID` int(11) DEFAULT NULL,
  `TransaccionID` int(11) DEFAULT NULL,
  `TipoMovimientoID` int(11) NOT NULL,
  `Monto` decimal(15,2) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  `SaldoAnterior` decimal(15,2) NOT NULL,
  `SaldoNuevo` decimal(15,2) NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MovimientoID`),
  KEY `idx_saldo` (`SaldoID`),
  KEY `idx_tx` (`TransaccionID`),
  KEY `idx_admin` (`AdminUserID`),
  KEY `fk_mov_cuenta_admin` (`CuentaAdminID`),
  KEY `fk_movimientos_tipo` (`TipoMovimientoID`),
  CONSTRAINT `fk_mov_cuenta_admin` FOREIGN KEY (`CuentaAdminID`) REFERENCES `cuentas_bancarias_admin` (`CuentaAdminID`),
  CONSTRAINT `fk_movimientos_admin` FOREIGN KEY (`AdminUserID`) REFERENCES `usuarios` (`UserID`) ON DELETE SET NULL,
  CONSTRAINT `fk_movimientos_saldo` FOREIGN KEY (`SaldoID`) REFERENCES `contabilidad_saldos` (`SaldoID`),
  CONSTRAINT `fk_movimientos_tipo` FOREIGN KEY (`TipoMovimientoID`) REFERENCES `tipos_movimiento` (`TipoMovimientoID`),
  CONSTRAINT `fk_movimientos_tx` FOREIGN KEY (`TransaccionID`) REFERENCES `transacciones` (`TransaccionID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contabilidad_saldos`
--

DROP TABLE IF EXISTS `contabilidad_saldos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contabilidad_saldos` (
  `SaldoID` int(11) NOT NULL AUTO_INCREMENT,
  `PaisID` int(11) NOT NULL,
  `MonedaCodigo` varchar(5) NOT NULL,
  `SaldoActual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `UmbralAlerta` decimal(15,2) NOT NULL DEFAULT 50000.00,
  `UltimaActualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SaldoID`),
  UNIQUE KEY `idx_pais_unico` (`PaisID`),
  CONSTRAINT `fk_saldos_pais` FOREIGN KEY (`PaisID`) REFERENCES `paises` (`PaisID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuentas_bancarias_admin`
--

DROP TABLE IF EXISTS `cuentas_bancarias_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuentas_bancarias_admin` (
  `CuentaAdminID` int(11) NOT NULL AUTO_INCREMENT,
  `FormaPagoID` int(11) DEFAULT NULL,
  `PaisID` int(11) NOT NULL DEFAULT 1,
  `RolCuentaID` int(11) NOT NULL DEFAULT 1,
  `Banco` varchar(100) NOT NULL,
  `Titular` varchar(100) NOT NULL,
  `TipoCuenta` varchar(50) NOT NULL,
  `NumeroCuenta` varchar(50) NOT NULL,
  `RUT` varchar(20) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Instrucciones` text DEFAULT NULL,
  `ColorHex` varchar(7) DEFAULT '#000000',
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `SaldoActual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `QrCodeURL` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`CuentaAdminID`),
  KEY `fk_cuentas_admin_formapago` (`FormaPagoID`),
  KEY `fk_cuentas_admin_pais` (`PaisID`),
  KEY `fk_cuentas_admin_roles` (`RolCuentaID`),
  CONSTRAINT `fk_cuentas_admin_formapago` FOREIGN KEY (`FormaPagoID`) REFERENCES `formas_pago` (`FormaPagoID`) ON DELETE CASCADE,
  CONSTRAINT `fk_cuentas_admin_pais` FOREIGN KEY (`PaisID`) REFERENCES `paises` (`PaisID`) ON DELETE CASCADE,
  CONSTRAINT `fk_cuentas_admin_roles` FOREIGN KEY (`RolCuentaID`) REFERENCES `roles_cuenta_admin` (`RolID`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuentas_beneficiarias`
--

DROP TABLE IF EXISTS `cuentas_beneficiarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuentas_beneficiarias` (
  `CuentaID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `PaisID` int(11) NOT NULL,
  `Alias` varchar(100) NOT NULL,
  `TipoBeneficiarioID` int(11) DEFAULT NULL,
  `TitularPrimerNombre` varchar(50) NOT NULL,
  `TitularSegundoNombre` varchar(50) DEFAULT NULL,
  `TitularPrimerApellido` varchar(50) NOT NULL,
  `TitularSegundoApellido` varchar(50) DEFAULT NULL,
  `TitularTipoDocumentoID` int(11) DEFAULT NULL,
  `TitularNumeroDocumento` varchar(30) NOT NULL,
  `NombreBanco` varchar(100) NOT NULL,
  `NumeroCuenta` varchar(50) DEFAULT NULL,
  `CCI` varchar(50) DEFAULT NULL,
  `NumeroTelefono` varchar(20) DEFAULT NULL,
  `FechaCreacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `PermitirEdicion` tinyint(1) NOT NULL DEFAULT 0,
  `SolicitudEdicion` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`CuentaID`),
  KEY `UserID` (`UserID`),
  KEY `PaisID` (`PaisID`),
  KEY `fk_cuentasbeneficiarias_tipos` (`TipoBeneficiarioID`),
  KEY `fk_beneficiarios_tipos_documento` (`TitularTipoDocumentoID`),
  CONSTRAINT `cuentas_beneficiarias_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE,
  CONSTRAINT `cuentas_beneficiarias_ibfk_2` FOREIGN KEY (`PaisID`) REFERENCES `paises` (`PaisID`) ON DELETE CASCADE,
  CONSTRAINT `fk_beneficiarios_tipos_documento` FOREIGN KEY (`TitularTipoDocumentoID`) REFERENCES `tipos_documento` (`TipoDocumentoID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cuentasbeneficiarias_tipos` FOREIGN KEY (`TipoBeneficiarioID`) REFERENCES `tipos_beneficiario` (`TipoBeneficiarioID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=627 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estados_transaccion`
--

DROP TABLE IF EXISTS `estados_transaccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_transaccion` (
  `EstadoID` int(11) NOT NULL AUTO_INCREMENT,
  `NombreEstado` varchar(50) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  PRIMARY KEY (`EstadoID`),
  UNIQUE KEY `NombreEstado` (`NombreEstado`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estados_verificacion`
--

DROP TABLE IF EXISTS `estados_verificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_verificacion` (
  `EstadoID` int(11) NOT NULL AUTO_INCREMENT,
  `NombreEstado` varchar(50) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  PRIMARY KEY (`EstadoID`),
  UNIQUE KEY `NombreEstado` (`NombreEstado`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `formas_pago`
--

DROP TABLE IF EXISTS `formas_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `formas_pago` (
  `FormaPagoID` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`FormaPagoID`),
  UNIQUE KEY `Nombre` (`Nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historial_documentos_usuarios`
--

DROP TABLE IF EXISTS `historial_documentos_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_documentos_usuarios` (
  `HistorialID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `TipoArchivo` enum('frente','reverso','perfil') NOT NULL,
  `RutaArchivo` varchar(255) NOT NULL,
  `AdminID` int(11) DEFAULT NULL,
  `FechaCambio` timestamp NULL DEFAULT current_timestamp(),
  `Motivo` text DEFAULT NULL,
  PRIMARY KEY (`HistorialID`),
  KEY `UserID` (`UserID`),
  KEY `AdminID` (`AdminID`),
  CONSTRAINT `historial_documentos_usuarios_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE,
  CONSTRAINT `historial_documentos_usuarios_ibfk_2` FOREIGN KEY (`AdminID`) REFERENCES `usuarios` (`UserID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=233 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `horario_override`
--

DROP TABLE IF EXISTS `horario_override`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horario_override` (
  `Id` tinyint(3) unsigned NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 0,
  `ForzadoPor` int(10) unsigned DEFAULT NULL,
  `FechaActivacion` datetime DEFAULT NULL,
  `ExpiraEn` datetime DEFAULT NULL,
  `Mensaje` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `liquidaciones_revendedor`
--

DROP TABLE IF EXISTS `liquidaciones_revendedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `liquidaciones_revendedor` (
  `LiquidacionID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `Monto` decimal(15,2) NOT NULL,
  `PeriodoDesde` date NOT NULL,
  `PeriodoHasta` date NOT NULL,
  `CantidadTransacciones` int(11) DEFAULT 0,
  `Estado` enum('pendiente','pagada') DEFAULT 'pendiente',
  `FechaCreacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `FechaPago` datetime DEFAULT NULL,
  `ComprobanteURL` varchar(255) DEFAULT NULL,
  `Notas` text DEFAULT NULL,
  `AdminUserID` int(11) DEFAULT NULL,
  PRIMARY KEY (`LiquidacionID`),
  KEY `UserID` (`UserID`),
  KEY `AdminUserID` (`AdminUserID`),
  CONSTRAINT `liquidaciones_revendedor_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`),
  CONSTRAINT `liquidaciones_revendedor_ibfk_2` FOREIGN KEY (`AdminUserID`) REFERENCES `usuarios` (`UserID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `LogID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) DEFAULT NULL,
  `Accion` varchar(255) NOT NULL,
  `Detalles` text DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`LogID`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19900 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `normas`
--

DROP TABLE IF EXISTS `normas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `normas` (
  `Id` int(11) NOT NULL DEFAULT 1,
  `Contenido` mediumtext DEFAULT NULL,
  `ActualizadoPor` int(11) DEFAULT NULL,
  `FechaActualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paises`
--

DROP TABLE IF EXISTS `paises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paises` (
  `PaisID` int(11) NOT NULL AUTO_INCREMENT,
  `NombrePais` varchar(100) NOT NULL,
  `CodigoMoneda` varchar(5) NOT NULL,
  `Rol` enum('Origen','Destino','Ambos') NOT NULL DEFAULT 'Ambos',
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`PaisID`),
  UNIQUE KEY `NombrePais_UNIQUE` (`NombrePais`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `passwordresets`
--

DROP TABLE IF EXISTS `passwordresets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `passwordresets` (
  `ResetID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `Token` varchar(255) NOT NULL,
  `ExpiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ResetID`),
  UNIQUE KEY `Token` (`Token`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `passwordresets_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rate_limit`
--

DROP TABLE IF EXISTS `rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `accion` varchar(64) NOT NULL,
  `hits` smallint(5) unsigned NOT NULL DEFAULT 1,
  `ventana_fin` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ip_accion` (`ip`,`accion`),
  KEY `idx_ventana_fin` (`ventana_fin`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `referido_config`
--

DROP TABLE IF EXISTS `referido_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referido_config` (
  `Id` int(11) NOT NULL DEFAULT 1,
  `FormaManualActiva` tinyint(1) NOT NULL DEFAULT 1,
  `FormaLinkActiva` tinyint(1) NOT NULL DEFAULT 1,
  `ActualizadoPor` int(11) DEFAULT NULL,
  `FechaActualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `revendedor_cuentas`
--

DROP TABLE IF EXISTS `revendedor_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revendedor_cuentas` (
  `CuentaID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `Banco` varchar(100) NOT NULL,
  `TipoCuenta` varchar(50) NOT NULL,
  `NumeroCuenta` varchar(100) NOT NULL,
  `TitularNombre` varchar(150) NOT NULL,
  `TitularDocumento` varchar(50) NOT NULL,
  `Instrucciones` text DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CuentaID`),
  KEY `idx_revendedor_cuentas_user` (`UserID`),
  CONSTRAINT `fk_revendedor_cuentas_user` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `revendedor_paises`
--

DROP TABLE IF EXISTS `revendedor_paises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revendedor_paises` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `PaisDestinoID` int(11) NOT NULL,
  `PorcentajeComision` decimal(5,2) NOT NULL DEFAULT 0.00,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_user_pais` (`UserID`,`PaisDestinoID`),
  KEY `PaisDestinoID` (`PaisDestinoID`),
  CONSTRAINT `revendedor_paises_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`),
  CONSTRAINT `revendedor_paises_ibfk_2` FOREIGN KEY (`PaisDestinoID`) REFERENCES `paises` (`PaisID`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `RolID` int(11) NOT NULL AUTO_INCREMENT,
  `NombreRol` varchar(50) NOT NULL COMMENT 'Ej: Admin, Empresa, Persona Natural, Proveedor',
  `Descripcion` text DEFAULT NULL COMMENT 'Descripci├│n opcional del rol',
  PRIMARY KEY (`RolID`),
  UNIQUE KEY `NombreRol` (`NombreRol`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles_cuenta_admin`
--

DROP TABLE IF EXISTS `roles_cuenta_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles_cuenta_admin` (
  `RolID` int(11) NOT NULL AUTO_INCREMENT,
  `NombreRol` varchar(50) NOT NULL,
  PRIMARY KEY (`RolID`),
  UNIQUE KEY `NombreRol` (`NombreRol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_holidays`
--

DROP TABLE IF EXISTS `system_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_holidays` (
  `HolidayID` int(11) NOT NULL AUTO_INCREMENT,
  `FechaInicio` datetime NOT NULL,
  `FechaFin` datetime NOT NULL,
  `Motivo` varchar(255) NOT NULL,
  `CreatedBy` int(11) NOT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `BloqueoSistema` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`HolidayID`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasas`
--

DROP TABLE IF EXISTS `tasas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasas` (
  `TasaID` int(11) NOT NULL AUTO_INCREMENT,
  `PaisOrigenID` int(11) NOT NULL,
  `PaisDestinoID` int(11) NOT NULL,
  `ValorTasa` decimal(15,5) NOT NULL,
  `EsReferencial` tinyint(1) DEFAULT 0,
  `PorcentajeAjuste` decimal(5,2) DEFAULT 0.00,
  `MontoMinimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `MontoMaximo` decimal(12,2) NOT NULL DEFAULT 9999999999.99,
  `FechaEfectiva` date NOT NULL,
  `Activa` tinyint(1) NOT NULL DEFAULT 1,
  `EsRiesgoso` tinyint(1) DEFAULT 0,
  `RutaActiva` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`TasaID`),
  KEY `PaisOrigenID` (`PaisOrigenID`),
  KEY `PaisDestinoID` (`PaisDestinoID`),
  KEY `idx_ruta_activa` (`RutaActiva`),
  CONSTRAINT `tasas_ibfk_1` FOREIGN KEY (`PaisOrigenID`) REFERENCES `paises` (`PaisID`),
  CONSTRAINT `tasas_ibfk_2` FOREIGN KEY (`PaisDestinoID`) REFERENCES `paises` (`PaisID`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasas_historico`
--

DROP TABLE IF EXISTS `tasas_historico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasas_historico` (
  `HistoricoID` int(11) NOT NULL AUTO_INCREMENT,
  `TasaID_Referencia` int(11) NOT NULL COMMENT 'El TasaID de la tabla principal que se actualiz├│',
  `PaisOrigenID` int(11) NOT NULL,
  `PaisDestinoID` int(11) NOT NULL,
  `ValorTasa` decimal(15,5) NOT NULL,
  `MontoMinimo` decimal(12,2) NOT NULL,
  `MontoMaximo` decimal(12,2) NOT NULL,
  `FechaCambio` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`HistoricoID`),
  KEY `idx_ruta_fecha` (`PaisOrigenID`,`PaisDestinoID`,`FechaCambio`),
  KEY `idx_tasa_id_ref` (`TasaID_Referencia`)
) ENGINE=InnoDB AUTO_INCREMENT=1685 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasas_imagen`
--

DROP TABLE IF EXISTS `tasas_imagen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasas_imagen` (
  `Id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `TipoFuente` enum('whatsapp','web') NOT NULL,
  `RutaImagen` varchar(255) DEFAULT NULL,
  `FechaActualizacion` datetime DEFAULT NULL,
  `ActualizadoPor` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `uk_tasas_imagen_tipo` (`TipoFuente`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_beneficiario`
--

DROP TABLE IF EXISTS `tipos_beneficiario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_beneficiario` (
  `TipoBeneficiarioID` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`TipoBeneficiarioID`),
  UNIQUE KEY `Nombre` (`Nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_documento`
--

DROP TABLE IF EXISTS `tipos_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_documento` (
  `TipoDocumentoID` int(11) NOT NULL AUTO_INCREMENT,
  `NombreDocumento` varchar(100) NOT NULL COMMENT 'Ej: RUT, C├®dula Venezolana, Pasaporte',
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `Orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`TipoDocumentoID`),
  UNIQUE KEY `NombreDocumento` (`NombreDocumento`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_movimiento`
--

DROP TABLE IF EXISTS `tipos_movimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_movimiento` (
  `TipoMovimientoID` int(11) NOT NULL AUTO_INCREMENT,
  `Codigo` varchar(50) NOT NULL,
  `NombreVisible` varchar(100) NOT NULL,
  `EsIngreso` tinyint(1) NOT NULL DEFAULT 0,
  `Color` varchar(20) DEFAULT 'secondary',
  PRIMARY KEY (`TipoMovimientoID`),
  UNIQUE KEY `idx_codigo` (`Codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transacciones`
--

DROP TABLE IF EXISTS `transacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transacciones` (
  `TransaccionID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `CuentaBeneficiariaID` int(11) NOT NULL,
  `TasaID_Al_Momento` int(11) NOT NULL,
  `TasaCapturada` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `MontoOrigen` decimal(12,2) NOT NULL,
  `MonedaOrigen` varchar(5) NOT NULL,
  `MontoDestino` decimal(12,2) NOT NULL,
  `ComisionDestino` decimal(10,2) DEFAULT 0.00,
  `GananciaRevendedor` decimal(15,2) DEFAULT 0.00,
  `MonedaDestino` varchar(10) DEFAULT NULL,
  `FechaTransaccion` timestamp NOT NULL DEFAULT current_timestamp(),
  `PlazoExtendidoHasta` datetime DEFAULT NULL,
  `ExtensionesPlazoUsadas` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `EstadoID` int(11) NOT NULL DEFAULT 1,
  `ConfirmacionRecepcion` enum('pendiente','recibido','no_recibido') NOT NULL DEFAULT 'pendiente',
  `FechaConfirmacionRecepcion` datetime DEFAULT NULL,
  `AutoCancelado` tinyint(1) NOT NULL DEFAULT 0,
  `FechaPago` datetime DEFAULT NULL,
  `FormaPagoID` int(11) DEFAULT NULL,
  `ComisionRevendedor` decimal(15,2) DEFAULT 0.00,
  `ComprobanteURL` varchar(255) DEFAULT NULL,
  `ComprobanteHash` varchar(64) DEFAULT NULL,
  `RutTitularOrigen` varchar(20) DEFAULT NULL,
  `NombreTitularOrigen` varchar(100) DEFAULT NULL,
  `ComprobanteEnvioURL` varchar(255) DEFAULT NULL,
  `ComprobanteEnvioHash` varchar(64) DEFAULT NULL,
  `MotivoPausa` text DEFAULT NULL,
  `MensajeReanudacion` text DEFAULT NULL,
  `BeneficiarioNombre` varchar(200) DEFAULT NULL,
  `BeneficiarioDocumento` varchar(30) DEFAULT NULL,
  `BeneficiarioBanco` varchar(100) DEFAULT NULL,
  `BeneficiarioNumeroCuenta` varchar(50) DEFAULT NULL,
  `BeneficiarioCCI` varchar(50) DEFAULT NULL,
  `BeneficiarioTelefono` varchar(20) DEFAULT NULL,
  `FechaSubidaComprobante` timestamp NULL DEFAULT NULL,
  `EmailMessageID` varchar(255) DEFAULT NULL,
  `ComprobanteBancoURL` varchar(255) DEFAULT NULL,
  `CuentaAdminID` int(11) DEFAULT NULL,
  `CuentaAdminSalidaID` int(11) DEFAULT NULL,
  `PermitirEdicionMonto` tinyint(1) DEFAULT 0,
  `LiquidacionID` int(11) DEFAULT NULL,
  `CuentaRevendedorID` int(11) DEFAULT NULL,
  PRIMARY KEY (`TransaccionID`),
  UNIQUE KEY `idx_comprobante_hash_unique` (`ComprobanteHash`),
  KEY `UserID` (`UserID`),
  KEY `CuentaBeneficiariaID` (`CuentaBeneficiariaID`),
  KEY `TasaID_Al_Momento` (`TasaID_Al_Momento`),
  KEY `fk_transacciones_formas_pago` (`FormaPagoID`),
  KEY `fk_transacciones_estados` (`EstadoID`),
  KEY `idx_email_message_id` (`EmailMessageID`),
  KEY `fk_tx_cuenta_admin` (`CuentaAdminID`),
  KEY `idx_comprobante_envio_hash` (`ComprobanteEnvioHash`),
  KEY `fk_transacciones_cuenta_admin_salida` (`CuentaAdminSalidaID`),
  KEY `idx_confirmacion_recepcion` (`ConfirmacionRecepcion`),
  KEY `idx_auto_cancelado` (`AutoCancelado`),
  KEY `idx_fecha_pago` (`FechaPago`),
  KEY `idx_plazo_extendido` (`PlazoExtendidoHasta`),
  KEY `fk_transacciones_cuenta_revendedor` (`CuentaRevendedorID`),
  CONSTRAINT `fk_transacciones_cuenta_admin_salida` FOREIGN KEY (`CuentaAdminSalidaID`) REFERENCES `cuentas_bancarias_admin` (`CuentaAdminID`),
  CONSTRAINT `fk_transacciones_cuenta_revendedor` FOREIGN KEY (`CuentaRevendedorID`) REFERENCES `revendedor_cuentas` (`CuentaID`) ON DELETE SET NULL,
  CONSTRAINT `fk_transacciones_estados` FOREIGN KEY (`EstadoID`) REFERENCES `estados_transaccion` (`EstadoID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transacciones_formas_pago` FOREIGN KEY (`FormaPagoID`) REFERENCES `formas_pago` (`FormaPagoID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tx_cuenta_admin` FOREIGN KEY (`CuentaAdminID`) REFERENCES `cuentas_bancarias_admin` (`CuentaAdminID`),
  CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`),
  CONSTRAINT `transacciones_ibfk_2` FOREIGN KEY (`CuentaBeneficiariaID`) REFERENCES `cuentas_beneficiarias` (`CuentaID`),
  CONSTRAINT `transacciones_ibfk_3` FOREIGN KEY (`TasaID_Al_Momento`) REFERENCES `tasas` (`TasaID`)
) ENGINE=InnoDB AUTO_INCREMENT=2037 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transacciones_auditoria_montos`
--

DROP TABLE IF EXISTS `transacciones_auditoria_montos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transacciones_auditoria_montos` (
  `AuditoriaID` int(11) NOT NULL AUTO_INCREMENT,
  `TransaccionID` int(11) NOT NULL,
  `AdminAutorizadorID` int(11) NOT NULL,
  `UsuarioModificadorID` int(11) NOT NULL,
  `MontoOrigenAnterior` decimal(15,2) NOT NULL,
  `MontoOrigenNuevo` decimal(15,2) NOT NULL,
  `MontoDestinoAnterior` decimal(15,2) NOT NULL,
  `MontoDestinoNuevo` decimal(15,2) NOT NULL,
  `ComisionAnterior` decimal(15,2) NOT NULL,
  `ComisionNueva` decimal(15,2) NOT NULL,
  `FechaModificacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`AuditoriaID`),
  KEY `TransaccionID` (`TransaccionID`),
  CONSTRAINT `transacciones_auditoria_montos_ibfk_1` FOREIGN KEY (`TransaccionID`) REFERENCES `transacciones` (`TransaccionID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transaction_proofs`
--

DROP TABLE IF EXISTS `transaction_proofs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_proofs` (
  `ProofID` int(11) NOT NULL AUTO_INCREMENT,
  `TransaccionID` int(11) NOT NULL,
  `Tipo` enum('client','admin') NOT NULL DEFAULT 'client',
  `FilePath` varchar(500) NOT NULL,
  `FileHash` varchar(64) DEFAULT NULL,
  `SubidoPor` int(11) DEFAULT NULL,
  `FechaSubida` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ProofID`),
  KEY `idx_tx_tipo` (`TransaccionID`,`Tipo`),
  KEY `idx_file_hash` (`FileHash`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tutoriales`
--

DROP TABLE IF EXISTS `tutoriales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tutoriales` (
  `TutorialID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Titulo` varchar(150) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  `TipoFuente` enum('archivo','url') NOT NULL DEFAULT 'url',
  `RutaArchivo` varchar(255) DEFAULT NULL,
  `URLExterna` varchar(500) DEFAULT NULL,
  `Orden` int(11) NOT NULL DEFAULT 0,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `CreadoPor` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`TutorialID`),
  KEY `idx_tutoriales_activo_orden` (`Activo`,`Orden`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_2fa_codes`
--

DROP TABLE IF EXISTS `user_2fa_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_2fa_codes` (
  `CodeID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` int(11) NOT NULL,
  `Code` varchar(10) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `CreatedAt` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CodeID`),
  KEY `UserID` (`UserID`),
  KEY `ExpiresAt` (`ExpiresAt`),
  CONSTRAINT `user_2fa_codes_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `usuarios` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `UserID` int(11) NOT NULL AUTO_INCREMENT,
  `PrimerNombre` varchar(50) NOT NULL,
  `SegundoNombre` varchar(50) DEFAULT NULL,
  `PrimerApellido` varchar(50) NOT NULL,
  `SegundoApellido` varchar(50) DEFAULT NULL,
  `Email` varchar(255) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Telefono` varchar(20) DEFAULT NULL,
  `TipoDocumentoID` int(11) DEFAULT NULL,
  `NumeroDocumento` varchar(30) NOT NULL,
  `FotoPerfilURL` varchar(255) DEFAULT NULL,
  `DocumentoImagenURL_Frente` varchar(255) DEFAULT NULL,
  `DocumentoImagenURL_Reverso` varchar(255) DEFAULT NULL,
  `VerificacionEstadoID` int(11) NOT NULL DEFAULT 1,
  `FailedLoginAttempts` int(11) NOT NULL DEFAULT 0,
  `LockoutUntil` datetime DEFAULT NULL,
  `RolID` int(11) DEFAULT NULL,
  `twofa_secret` varchar(255) DEFAULT NULL COMMENT 'Secreto TOTP encriptado',
  `twofa_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si el 2FA est├í activo',
  `twofa_backup_codes` text DEFAULT NULL COMMENT 'C├│digos de respaldo encriptados',
  `FechaRegistro` timestamp NOT NULL DEFAULT current_timestamp(),
  `Eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `PorcentajeComision` decimal(5,2) DEFAULT 0.00,
  `MaxCuentasRevendedor` int(11) NOT NULL DEFAULT 6,
  `CodigoReferido` varchar(10) DEFAULT NULL,
  `ReferidoPor` int(11) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `twofa_method` varchar(20) DEFAULT 'email',
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `Email_UNIQUE` (`Email`),
  UNIQUE KEY `NumeroDocumento_UNIQUE` (`NumeroDocumento`),
  UNIQUE KEY `CodigoReferido` (`CodigoReferido`),
  KEY `fk_usuarios_roles` (`RolID`),
  KEY `fk_usuarios_tipos_documento` (`TipoDocumentoID`),
  KEY `fk_usuarios_estados_verificacion` (`VerificacionEstadoID`),
  KEY `fk_usuarios_referido_por` (`ReferidoPor`),
  CONSTRAINT `fk_usuarios_estados_verificacion` FOREIGN KEY (`VerificacionEstadoID`) REFERENCES `estados_verificacion` (`EstadoID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_referido_por` FOREIGN KEY (`ReferidoPor`) REFERENCES `usuarios` (`UserID`) ON DELETE SET NULL,
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`RolID`) REFERENCES `roles` (`RolID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_tipos_documento` FOREIGN KEY (`TipoDocumentoID`) REFERENCES `tipos_documento` (`TipoDocumentoID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=513 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'jcenvios'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-18 22:26:04
