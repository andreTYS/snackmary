-- =====================================================================
-- Snack Mary - Instalación desde cero
-- Importar en phpMyAdmin sobre una base de datos VACÍA.
-- Si ya tienes datos, usa migracion_v2.sql en su lugar.
--
-- Usuario inicial: admin / admin  (cámbiala apenas ingreses)
-- Al final se aplica la estructura de la versión 3 (migracion_v3).
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-05:00";
SET NAMES utf8;

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `direccion` text NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `codigo_pais` varchar(5) NOT NULL DEFAULT '51',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `configuracion` (`id`, `nombre`, `telefono`, `email`, `direccion`, `whatsapp`, `codigo_pais`) VALUES
(1, 'Snack Mary', '000000000', 'correo@ejemplo.com', 'Dirección del local', NULL, '51');

CREATE TABLE `usuario` (
  `idusuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `usuario` varchar(20) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idusuario`),
  UNIQUE KEY `uq_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `usuario` (`idusuario`, `nombre`, `correo`, `usuario`, `clave`, `estado`) VALUES
(1, 'Administrador', 'admin@ejemplo.com', 'admin', '$2y$10$am7acgRF63NNm2uVNnN4V.LUsu4lzGCXRZoackLqaS1h0YgdD1qQC', 1);

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permisos` (`id`, `nombre`) VALUES
(1, 'configuracion'),
(2, 'usuarios'),
(3, 'clientes'),
(4, 'productos'),
(5, 'ventas'),
(6, 'nueva_venta'),
(7, 'mesas'),
(8, 'pedidos');

CREATE TABLE `detalle_permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_permiso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cliente` (
  `idcliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(15) NOT NULL,
  `direccion` varchar(200) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`idcliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `cliente` (`idcliente`, `nombre`, `telefono`, `direccion`, `usuario_id`, `estado`) VALUES
(1, 'Público en General', '-', 'S/D', 1, 1);

CREATE TABLE `producto` (
  `codproducto` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `existencia` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`codproducto`),
  UNIQUE KEY `uq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `capacidad` int(11) NOT NULL DEFAULT 4,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

INSERT INTO `mesas` (`nombre`, `capacidad`) VALUES
('Mesa 1', 4), ('Mesa 2', 4), ('Mesa 3', 4), ('Mesa 4', 4);

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo` varchar(20) NOT NULL DEFAULT 'mostrador',
  `id_mesa` int(11) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pagado',
  `metodo_pago` varchar(20) NOT NULL DEFAULT 'efectivo',
  `costo_envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `direccion_entrega` varchar(200) DEFAULT NULL,
  `telefono_entrega` varchar(20) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_mesa` (`id_mesa`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venta` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detalle_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` varchar(50) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- ===================== Versión 3 =====================

-- Nuevos permisos
INSERT INTO `permisos` (`nombre`) SELECT 'caja' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'caja');
INSERT INTO `permisos` (`nombre`) SELECT 'cocina' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'cocina');
INSERT INTO `permisos` (`nombre`) SELECT 'reportes' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'reportes');
INSERT INTO `permisos` (`nombre`) SELECT 'repartidor' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'repartidor');

-- Configuración: costo de delivery para los pedidos desde la carta
ALTER TABLE `configuracion`
  ADD COLUMN IF NOT EXISTS `costo_envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `carta_activa` tinyint(1) NOT NULL DEFAULT 1;

-- Categorías de productos
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `cocina` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Productos: categoría, foto y si controla stock (los platos preparados no)
ALTER TABLE `producto`
  ADD COLUMN IF NOT EXISTS `id_categoria` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `imagen` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `controla_stock` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `producto` ADD INDEX IF NOT EXISTS `idx_categoria` (`id_categoria`);

-- Pedidos: origen (local / web), caja, fecha de cobro, repartidor, ubicación, IP
ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `origen` varchar(10) NOT NULL DEFAULT 'local',
  ADD COLUMN IF NOT EXISTS `id_caja` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_pago` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `id_repartidor` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ubicacion` varchar(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ip` varchar(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `estado_cocina` varchar(12) NOT NULL DEFAULT 'no';
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_caja` (`id_caja`);
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_fecha_pago` (`fecha_pago`);
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_repartidor` (`id_repartidor`);

-- Pedidos activos que aún no salieron de cocina
UPDATE `ventas` SET `estado_cocina` = `estado` WHERE `estado_cocina` = 'no' AND `estado` IN ('pendiente', 'preparando');

-- Las ventas ya cobradas toman su fecha de creación como fecha de cobro
UPDATE `ventas` SET `fecha_pago` = `fecha` WHERE `estado` = 'pagado' AND `fecha_pago` IS NULL;

-- Rondas: cuando una mesa pide más, cocina ve solo lo nuevo
ALTER TABLE `detalle_venta` ADD COLUMN IF NOT EXISTS `ronda` int(11) NOT NULL DEFAULT 1;

-- Caja
CREATE TABLE IF NOT EXISTS `cajas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario_apertura` int(11) NOT NULL,
  `fecha_apertura` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_usuario_cierre` int(11) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `efectivo_esperado` decimal(10,2) DEFAULT NULL,
  `efectivo_contado` decimal(10,2) DEFAULT NULL,
  `resumen` text DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'abierta',
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

CREATE TABLE IF NOT EXISTS `caja_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_caja` int(11) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(20) NOT NULL DEFAULT 'efectivo',
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caja` (`id_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Categorías iniciales
INSERT INTO `categorias` (`nombre`, `orden`, `cocina`) VALUES
('Hamburguesas', 1, 1), ('Salchipapas', 2, 1), ('Bebidas', 3, 0), ('Postres', 4, 1);
