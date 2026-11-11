<?php
/*
 * Cambios de estado de un pedido: preparar, listo, en camino, entregado,
 * cobrar (pagado) o anular (devuelve el stock).
 */
require_once "includes/app.php";
require_permiso('pedidos', 'mesas');
if (!es_post()) {
    redirigir('pedidos.php');
}
csrf_check();

$volver = in_array($_POST['volver'] ?? '', ['pedidos.php', 'mesas.php', 'lista_ventas.php'], true) ? $_POST['volver'] : 'pedidos.php';
$id = entero($_POST['id'] ?? 0);
$estado = $_POST['estado'] ?? '';
$pedido = $id ? db_one('SELECT id, estado, tipo FROM ventas WHERE id = ?', [$id]) : null;

if (!$pedido) {
    flash('danger', 'Pedido no encontrado');
    redirigir($volver);
}
if (!array_key_exists($estado, ESTADOS_PEDIDO)) {
    flash('danger', 'Estado no válido');
    redirigir($volver);
}
if ($pedido['estado'] === 'anulado') {
    flash('warning', 'El pedido #' . $id . ' ya está anulado');
    redirigir($volver);
}

if ($estado === 'anulado') {
    if (!tiene_permiso('pedidos') && !tiene_permiso('ventas')) {
        flash('danger', 'No tienes permiso para anular pedidos');
        redirigir($volver);
    }
    $conexion->begin_transaction();
    anular_pedido($id);
    $conexion->commit();
    flash('success', 'Pedido #' . $id . ' anulado y stock devuelto');
    redirigir($volver);
}

if ($estado === 'pagado') {
    $metodo = array_key_exists($_POST['metodo_pago'] ?? '', METODOS_PAGO) ? $_POST['metodo_pago'] : null;
    if ($metodo) {
        db_exec("UPDATE ventas SET estado = 'pagado', metodo_pago = ? WHERE id = ?", [$metodo, $id]);
    } else {
        db_exec("UPDATE ventas SET estado = 'pagado' WHERE id = ?", [$id]);
    }
    flash('success', 'Pedido #' . $id . ' cobrado' . ($pedido['tipo'] === 'mesa' ? ' y mesa liberada' : ''));
    redirigir($volver);
}

db_exec('UPDATE ventas SET estado = ? WHERE id = ?', [$estado, $id]);
flash('success', 'Pedido #' . $id . ': ' . ESTADOS_PEDIDO[$estado][0]);
redirigir($volver);
