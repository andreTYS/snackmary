<?php
/*
 * Conexión a la base de datos.
 * Las credenciales NO van en este archivo: se leen de config.local.php
 * (que no se sube al repositorio). Copia config.local.example.php a
 * config.local.php y completa tus datos.
 */
date_default_timezone_set('America/Lima');

$archivoConfig = __DIR__ . '/config.local.php';
if (is_file($archivoConfig)) {
    $cfg = require $archivoConfig;
} elseif (getenv('DB_HOST') !== false) {
    // En Docker la configuración llega por variables de entorno
    $cfg = [
        'host'  => getenv('DB_HOST'),
        'user'  => getenv('DB_USER'),
        'pass'  => getenv('DB_PASS'),
        'db'    => getenv('DB_NAME'),
        'debug' => getenv('APP_DEBUG') === '1',
    ];
} else {
    http_response_code(500);
    exit('Falta el archivo config.local.php. Copia config.local.example.php y completa los datos de la base de datos.');
}

ini_set('display_errors', !empty($cfg['debug']) ? '1' : '0');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexion = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    $conexion->set_charset('utf8');
    $conexion->query("SET time_zone = '-05:00'");
} catch (mysqli_sql_exception $ex) {
    error_log('Error de conexión: ' . $ex->getMessage());
    http_response_code(500);
    exit('No se pudo conectar a la base de datos.');
}
