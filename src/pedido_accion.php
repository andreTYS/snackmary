<?php
/*
 * Acciones sobre un pedido:
 *   estado        => preparando, listo, en camino, entregado, pagado (cobrar) o anulado
 *   cocina        => preparando / listo (pantalla de cocina)
 *   repartidor    => asignar repartidor a un delivery
 */
require_once "includes/app.php";
require_permiso('pedidos', 'mesas', 'cocina', 'repartidor');
if (!es_post()) {
    redirigir('pedidos.php');
}
csrf_check();

$paginas = ['pedidos.php', 'mesas.php', 'lista_ventas.php', 'cocina.php', 'repartidor.php'];
$volver = in_array($_POST['volver'] ?? '', $paginas, true) ? $_POST['volver'] : 'pedidos.php';
$id = entero($_POST['id'] ?? 0);
$pedido = $id ? db_one('SELECT id, estado, estado_cocina, tipo, id_repartidor FROM ventas WHERE id = ?', [$id]) : null;

function terminar(string $tipo, string $msg, string $volver): void
{
    if (es_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => $tipo === 'success', 'msg' => $msg]));
    }
    flash($tipo, $msg);
    redirigir($volver);
}

if (!$pedido) {
    terminar('danger', 'Pedido no encontrado', $volver);
}
if ($pedido['estado'] === 'anulado') {
    terminar('warning', 'El pedido #' . $id . ' ya está anulado', $volver);
}

$gestiona = tiene_permiso('pedidos') || tiene_permiso('mesas');
$esRepartidorDelPedido = tiene_permiso('repartidor') && (int)$pedido['id_repartidor'] === usuario_id();

/* ---------- Cocina ---------- */
if (isset($_POST['cocina'])) {
    if (!tiene_permiso('cocina') && !$gestiona) {
        terminar('danger', 'No tienes permiso', $volver);
    }
    $nuevo = $_POST['cocina'] === 'listo' ? 'listo' : 'preparando';
    db_exec('UPDATE ventas SET estado_cocina = ? WHERE id = ?', [$nuevo, $id]);
    // El pedido avanza junto con la cocina (si aún no fue cobrado o entregado)
    if ($nuevo === 'preparando' && $pedido['estado'] === 'pendiente') {
        db_exec("UPDATE ventas SET estado = 'preparando' WHERE id = ?", [$id]);
    } elseif ($nuevo === 'listo' && in_array($pedido['estado'], ['pendiente', 'preparando'], true)) {
        db_exec("UPDATE ventas SET estado = 'listo' WHERE id = ?", [$id]);
    }
    terminar('success', 'Pedido #' . $id . ': ' . ($nuevo === 'listo' ? 'listo' : 'en preparación'), $volver);
}

/* ---------- Asignar repartidor ---------- */
if (isset($_POST['repartidor'])) {
    if (!$gestiona) {
        terminar('danger', 'No tienes permiso', $volver);
    }
    $idRep = entero($_POST['repartidor']);
    if ($idRep && !db_one(
        "SELECT u.idusuario FROM usuario u WHERE u.idusuario = ? AND u.estado = 1 AND (u.idusuario = 1 OR EXISTS (
            SELECT 1 FROM detalle_permisos d INNER JOIN permisos p ON p.id = d.id_permiso WHERE d.id_usuario = u.idusuario AND p.nombre = 'repartidor'))",
        [$idRep]
    )) {
        terminar('danger', 'Repartidor no válido', $volver);
    }
    db_exec('UPDATE ventas SET id_repartidor = ? WHERE id = ?', [$idRep ?: null, $id]);
    terminar('success', $idRep ? 'Repartidor asignado al pedido #' . $id : 'Pedido #' . $id . ' sin repartidor', $volver);
}

/* ---------- Estado del pedido ---------- */
$estado = $_POST['estado'] ?? '';
if (!array_key_exists($estado, ESTADOS_PEDIDO)) {
    terminar('danger', 'Estado no válido', $volver);
}
// El repartidor solo puede marcar "en camino", "entregado" o cobrar sus propios pedidos
if (!$gestiona && !($esRepartidorDelPedido && in_array($estado, ['en_camino', 'entregado', 'pagado'], true))) {
    terminar('danger', 'No tienes permiso para esta acción', $volver);
}

if ($estado === 'anulado') {
    if (!tiene_permiso('pedidos') && !tiene_permiso('ventas')) {
        terminar('danger', 'No tienes permiso para anular pedidos', $volver);
    }
    $conexion->begin_transaction();
    anular_pedido($id);
    $conexion->commit();
    terminar('success', 'Pedido #' . $id . ' anulado y stock devuelto', $volver);
}

if ($estado === 'pagado') {
    if ($pedido['estado'] === 'pagado') {
        terminar('warning', 'El pedido #' . $id . ' ya estaba cobrado', $volver);
    }
    cobrar_pedido($id, $_POST['metodo_pago'] ?? null);
    $aviso = caja_abierta() ? '' : ' (ojo: no hay caja abierta)';
    terminar('success', 'Pedido #' . $id . ' cobrado' . ($pedido['tipo'] === 'mesa' ? ' y mesa liberada' : '') . $aviso, $volver);
}

db_exec('UPDATE ventas SET estado = ? WHERE id = ?', [$estado, $id]);
// Mantener la pantalla de cocina coherente con lo que se marca en Pedidos
if ($pedido['estado_cocina'] !== 'no') {
    $cocina = $estado === 'pendiente' ? 'pendiente' : ($estado === 'preparando' ? 'preparando' : 'listo');
    db_exec('UPDATE ventas SET estado_cocina = ? WHERE id = ?', [$cocina, $id]);
}
terminar('success', 'Pedido #' . $id . ': ' . ESTADOS_PEDIDO[$estado][0], $volver);
