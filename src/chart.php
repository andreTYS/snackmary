<?php
require_once "includes/app.php";
require_login();
header('Content-Type: application/json; charset=utf-8');
$accion = $_POST['action'] ?? '';
if ($accion === 'sales') {
    echo json_encode(db_all('SELECT descripcion, existencia FROM producto WHERE estado = 1 AND existencia <= 10 ORDER BY existencia ASC LIMIT 10'));
    exit;
}
if ($accion === 'polarChart') {
    echo json_encode(db_all(
        "SELECT p.descripcion, SUM(d.cantidad) AS cantidad
         FROM detalle_venta d
         INNER JOIN producto p ON p.codproducto = d.id_producto
         INNER JOIN ventas v ON v.id = d.id_venta
         WHERE v.estado <> 'anulado'
         GROUP BY d.id_producto, p.descripcion
         ORDER BY cantidad DESC
         LIMIT 5"
    ));
    exit;
}
echo json_encode([]);
