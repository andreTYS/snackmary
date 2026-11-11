-- =====================================================================
-- Snack Mary - Migración a la versión 3
-- Categorías y fotos de productos, productos sin control de stock,
-- pedidos desde la carta, caja (apertura/cierre/arqueo), cocina por rondas,
-- repartidores y ubicación de delivery.
--
-- Requiere haber aplicado antes migracion_v2.sql.
-- Se puede ejecutar más de una vez sin problema (MariaDB).
-- =====================================================================

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
