-- =====================================================================
-- Snack Mary - Migración a la versión 5: seguridad
-- Límite de intentos de inicio de sesión y registro de auditoría.
--
-- Requiere haber aplicado antes migracion_v4.sql.
-- Se puede ejecutar más de una vez sin problema (MariaDB).
-- =====================================================================

-- Intentos de inicio de sesión (para bloquear fuerza bruta por usuario e IP)
CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_fecha` (`usuario`, `fecha`),
  KEY `idx_ip_fecha` (`ip`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;

-- Registro de acciones sensibles (quién hizo qué y cuándo)
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` varchar(255) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;
