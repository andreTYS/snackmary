-- =====================================================================
-- Snack Mary - Migración de la base de datos EXISTENTE a la versión 2
-- (mesas, pedidos, delivery, WhatsApp y contraseñas seguras).
--
-- 1. Haz una copia de seguridad (phpMyAdmin > Exportar).
-- 2. Abre tu base de datos en phpMyAdmin > pestaña SQL > pega todo y ejecuta.
-- Se puede ejecutar más de una vez sin problema (MariaDB).
-- =====================================================================

-- Contraseñas: espacio para password_hash (las MD5 antiguas se convierten
-- solas la próxima vez que cada usuario inicie sesión).
ALTER TABLE `usuario` MODIFY `clave` varchar(255) NOT NULL;

-- Corrige el permiso de configuración (tenía tilde y nunca coincidía)
UPDATE `permisos` SET `nombre` = 'configuracion' WHERE `nombre` IN ('configuración', 'configuracion');

-- Nuevos permisos
INSERT INTO `permisos` (`nombre`)
SELECT 'mesas' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'mesas');
INSERT INTO `permisos` (`nombre`)
SELECT 'pedidos' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permisos` WHERE `nombre` = 'pedidos');

-- WhatsApp del negocio
ALTER TABLE `configuracion`
  ADD COLUMN IF NOT EXISTS `whatsapp` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `codigo_pais` varchar(5) NOT NULL DEFAULT '51';

-- Mesas
CREATE TABLE IF NOT EXISTS `mesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `capacidad` int(11) NOT NULL DEFAULT 4,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Pedidos: tipo (mostrador / mesa / delivery), estado, pago y datos de entrega.
-- La fecha ya no cambia cuando se actualiza la venta (quitamos ON UPDATE).
ALTER TABLE `ventas`
  MODIFY `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  ADD COLUMN IF NOT EXISTS `tipo` varchar(20) NOT NULL DEFAULT 'mostrador',
  ADD COLUMN IF NOT EXISTS `id_mesa` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `estado` varchar(20) NOT NULL DEFAULT 'pagado',
  ADD COLUMN IF NOT EXISTS `metodo_pago` varchar(20) NOT NULL DEFAULT 'efectivo',
  ADD COLUMN IF NOT EXISTS `costo_envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `direccion_entrega` varchar(200) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `telefono_entrega` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `nota` varchar(255) DEFAULT NULL;

-- Índices para que las búsquedas sean rápidas
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_estado` (`estado`);
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_mesa` (`id_mesa`);
ALTER TABLE `ventas` ADD INDEX IF NOT EXISTS `idx_fecha` (`fecha`);
ALTER TABLE `detalle_venta` ADD INDEX IF NOT EXISTS `idx_venta` (`id_venta`);
ALTER TABLE `detalle_temp` ADD INDEX IF NOT EXISTS `idx_usuario` (`id_usuario`);
ALTER TABLE `detalle_permisos` ADD INDEX IF NOT EXISTS `idx_usuario` (`id_usuario`);

-- Productos con stock negativo por el error anterior: se dejan en 0
UPDATE `producto` SET `existencia` = 0 WHERE `existencia` < 0;

-- Carritos temporales viejos (con totales mal calculados)
DELETE FROM `detalle_temp`;
