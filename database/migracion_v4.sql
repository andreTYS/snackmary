-- =====================================================================
-- Snack Mary - Migración a la versión 4: opciones por plato
-- (tipo de plato, extras y bebida del combo). Las opciones pueden
-- vincularse a una bebida para descontar su stock.
--
-- Requiere haber aplicado antes migracion_v3.sql.
-- Se puede ejecutar más de una vez sin problema (MariaDB).
-- =====================================================================

-- Grupos de opciones de un producto (ej: "Tipo", "Extras", "Bebida del combo")
-- minimo/maximo: cuántas opciones se pueden elegir del grupo
--   minimo 1, maximo 1 = obligatorio, elige una
--   minimo 0, maximo 1 = opcional, elige una
--   minimo 0, maximo N = opcional, varias
CREATE TABLE IF NOT EXISTS `opcion_grupos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `minimo` int(11) NOT NULL DEFAULT 0,
  `maximo` int(11) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Opciones de cada grupo. id_producto_stock: bebida (u otro producto con stock)
-- que se descuenta cuando se elige esta opción.
CREATE TABLE IF NOT EXISTS `opciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_grupo` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_producto_stock` int(11) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_grupo` (`id_grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Carrito y detalle de venta guardan lo elegido
ALTER TABLE `detalle_temp`
  ADD COLUMN IF NOT EXISTS `opciones` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `opciones_txt` varchar(255) NOT NULL DEFAULT '';

-- consumo: stock extra que consume CADA unidad (JSON {"id_producto": unidades}),
-- para devolverlo si se anula el pedido
ALTER TABLE `detalle_venta`
  ADD COLUMN IF NOT EXISTS `opciones` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `opciones_txt` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `consumo` varchar(255) NOT NULL DEFAULT '';
