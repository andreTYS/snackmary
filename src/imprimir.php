<?php
/*
 * Devuelve un ticket o comanda en formato ESC/POS (base64) para que la tablet
 * lo envíe a la impresora Bluetooth (directo o con la app RawBT).
 *   ?doc=ticket|comanda|prueba  &v=<id pedido>  &ancho=32|48  &acentos=1|0
 */
require_once "includes/app.php";
require_once "includes/escpos.php";
require_login();
header('Content-Type: application/json; charset=utf-8');

$doc = in_array($_GET['doc'] ?? '', ['ticket', 'comanda', 'prueba'], true) ? $_GET['doc'] : 'ticket';
$cols = in_array((int)($_GET['ancho'] ?? 32), [32, 42, 48], true) ? (int)$_GET['ancho'] : 32;
$acentos = ($_GET['acentos'] ?? '1') === '1';

if ($doc === 'prueba') {
    echo json_encode(['ok' => true, 'datos' => base64_encode(escpos_prueba($cols, $acentos))]);
    exit;
}
require_permiso('ventas', 'nueva_venta', 'pedidos', 'mesas', 'cocina', 'repartidor');
$id = entero($_GET['v'] ?? 0);
$pedido = $id ? obtener_pedido($id) : null;
if (!$pedido) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'msg' => 'Pedido no encontrado']));
}
echo json_encode(['ok' => true, 'datos' => base64_encode(escpos_pedido($pedido, $doc, $cols, $acentos))]);
