-- =====================================================================
-- Snack Mary - Instalación desde cero
-- Importar en phpMyAdmin sobre una base de datos VACÍA.
-- Si ya tienes datos, usa migracion_v2.sql en su lugar.
--
-- Usuario inicial: admin / admin  (cámbiala apenas ingreses)
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
