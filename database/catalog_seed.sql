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
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `paises`
--

LOCK TABLES `paises` WRITE;
/*!40000 ALTER TABLE `paises` DISABLE KEYS */;
INSERT INTO `paises` VALUES (1,'Chile','CLP','Ambos',1),(2,'Colombia','COP','Ambos',1),(3,'Venezuela','VES','Destino',1),(4,'Perú','PEN','Ambos',1),(5,'EE.UU','USD','Origen',0),(7,'Argentina','ARS','Destino',0),(8,'Nueva Zelanda','NZL','Destino',0),(10,'Ecuador','ECU','Ambos',1);
/*!40000 ALTER TABLE `paises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','Administrador del sistema'),(2,'Persona Natural','Cliente tipo persona natural'),(3,'Empresa','Cliente tipo empresa'),(4,'Revendedor','Cliente mayorista con comision'),(5,'Operador','Rol para gestionar transacciones pendientes y completadas.');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tipos_documento`
--

LOCK TABLES `tipos_documento` WRITE;
/*!40000 ALTER TABLE `tipos_documento` DISABLE KEYS */;
INSERT INTO `tipos_documento` VALUES (1,'RUT',1,1),(2,'Cédula',1,2),(3,'RIF',1,5),(4,'Pasaporte',1,4),(5,'Otros',1,6),(6,'DNI',1,3),(7,'E-RUT',0,5),(8,'PPT',1,7),(9,'Carnet de Extranjería',1,8);
/*!40000 ALTER TABLE `tipos_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `formas_pago`
--

LOCK TABLES `formas_pago` WRITE;
/*!40000 ALTER TABLE `formas_pago` DISABLE KEYS */;
INSERT INTO `formas_pago` VALUES (1,'Transferencia Bancaria',NULL,1),(2,'Caja Vecina',NULL,1),(3,'Zelle',NULL,1),(4,'Nequi',NULL,1),(5,'Plin',NULL,1),(6,'Yape',NULL,1),(7,'Pago móvil',NULL,1);
/*!40000 ALTER TABLE `formas_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `estados_transaccion`
--

LOCK TABLES `estados_transaccion` WRITE;
/*!40000 ALTER TABLE `estados_transaccion` DISABLE KEYS */;
INSERT INTO `estados_transaccion` VALUES (1,'Pendiente de Pago',NULL),(2,'En Verificación',NULL),(3,'En Proceso',NULL),(4,'Exitoso',NULL),(5,'Cancelado',NULL),(6,'Pausado',NULL),(7,'Pendiente de Aprobación',NULL);
/*!40000 ALTER TABLE `estados_transaccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `estados_verificacion`
--

LOCK TABLES `estados_verificacion` WRITE;
/*!40000 ALTER TABLE `estados_verificacion` DISABLE KEYS */;
INSERT INTO `estados_verificacion` VALUES (1,'No Verificado',NULL),(2,'Pendiente',NULL),(3,'Verificado',NULL),(4,'Rechazado',NULL);
/*!40000 ALTER TABLE `estados_verificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tipos_beneficiario`
--

LOCK TABLES `tipos_beneficiario` WRITE;
/*!40000 ALTER TABLE `tipos_beneficiario` DISABLE KEYS */;
INSERT INTO `tipos_beneficiario` VALUES (1,'Cuenta Bancaria',1),(2,'Pago Móvil',1);
/*!40000 ALTER TABLE `tipos_beneficiario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tipos_movimiento`
--

LOCK TABLES `tipos_movimiento` WRITE;
/*!40000 ALTER TABLE `tipos_movimiento` DISABLE KEYS */;
INSERT INTO `tipos_movimiento` VALUES (1,'SALDO_INICIAL','Saldo Inicial / Ajuste',1,'primary'),(2,'RECARGA','Recarga de Fondos',1,'success'),(3,'GASTO_TX','Gasto por Envío',0,'danger'),(4,'GASTO_COMISION','Gasto por Comisión',0,'danger'),(5,'INGRESO_VENTA','Ingreso por Venta',1,'success'),(6,'RETIRO_DIVISAS','Retiro Compra Divisas',0,'warning text-dark'),(7,'COMPRA_DIVISA','Ingreso Compra Divisas',1,'info text-dark'),(8,'GASTO_VARIO','Gasto Operativo / Retiro',0,'warning');
/*!40000 ALTER TABLE `tipos_movimiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles_cuenta_admin`
--

LOCK TABLES `roles_cuenta_admin` WRITE;
/*!40000 ALTER TABLE `roles_cuenta_admin` DISABLE KEYS */;
INSERT INTO `roles_cuenta_admin` VALUES (3,'Ambos'),(2,'Destino'),(1,'Origen');
/*!40000 ALTER TABLE `roles_cuenta_admin` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-18 22:26:39
