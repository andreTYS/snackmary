<?php
require_once "includes/app.php";
require_login();
header('Content-Type: application/json; charset=utf-8');

function responder(array $datos): void
{
    echo json_encode($datos);
    exit;
}

$uid = usuario_id();

/* ---------- Lecturas (GET) ---------- */
if (isset($_GET['q'])) {
    require_permiso('nueva_venta', 'clientes');
    $termino = '%' . texto($_GET['q'], 100) . '%';
    $datos = [];
    foreach (db_all('SELECT idcliente, nombre, direccion, telefono FROM cliente WHERE nombre LIKE ? AND estado = 1 ORDER BY nombre LIMIT 20', [$termino]) as $row) {
        $datos[] = ['id' => (int)$row['idcliente'], 'label' => $row['nombre'], 'direccion' => $row['direccion'], 'telefono' => $row['telefono']];
    }
    responder($datos);
}

if (isset($_GET['pro'])) {
    require_permiso('nueva_venta');
    $termino = '%' . texto($_GET['pro'], 100) . '%';
    $datos = [];
    foreach (db_all('SELECT codproducto, codigo, descripcion, precio, existencia FROM producto WHERE (codigo LIKE ? OR descripcion LIKE ?) AND estado = 1 ORDER BY descripcion LIMIT 20', [$termino, $termino]) as $row) {
        $datos[] = [
            'id' => (int)$row['codproducto'],
            'label' => $row['codigo'] . ' - ' . $row['descripcion'] . ' (S/ ' . dinero($row['precio']) . ', stock ' . (int)$row['existencia'] . ')',
            'value' => $row['descripcion'],
            'precio' => $row['precio'],
            'existencia' => (int)$row['existencia'],
        ];
    }
    responder($datos);
}

if (isset($_GET['detalle'])) {
    require_permiso('nueva_venta');
    $filas = db_all(
        'SELECT d.id, d.id_producto, d.cantidad, d.precio_venta, p.descripcion
         FROM detalle_temp d INNER JOIN producto p ON d.id_producto = p.codproducto
         WHERE d.id_usuario = ? ORDER BY d.id',
        [$uid]
    );
    $datos = [];
    foreach ($filas as $row) {
        $datos[] = [
            'id' => (int)$row['id'],
            'id_producto' => (int)$row['id_producto'],
            'descripcion' => $row['descripcion'],
            'cantidad' => (int)$row['cantidad'],
            'precio_venta' => dinero($row['precio_venta']),
            'sub_total' => round($row['precio_venta'] * $row['cantidad'], 2),
        ];
    }
    responder($datos);
}

/* ---------- Escrituras (solo POST con token CSRF) ---------- */
if (!es_post()) {
    responder(['ok' => false, 'msg' => 'Acción no válida']);
}
csrf_check();
$accion = $_POST['action'] ?? '';

if ($accion === 'agregar') {
    require_permiso('nueva_venta');
    $idProducto = entero($_POST['id'] ?? 0);
    $cant = max(1, entero($_POST['cant'] ?? 1));
    $producto = db_one('SELECT codproducto, precio, existencia, descripcion FROM producto WHERE codproducto = ? AND estado = 1', [$idProducto]);
    if (!$producto) {
        responder(['ok' => false, 'msg' => 'Producto no encontrado']);
    }
    $enCarrito = db_one('SELECT id, cantidad FROM detalle_temp WHERE id_producto = ? AND id_usuario = ?', [$idProducto, $uid]);
    $nuevaCantidad = ($enCarrito ? (int)$enCarrito['cantidad'] : 0) + $cant;
    if ($nuevaCantidad > (int)$producto['existencia']) {
        responder(['ok' => false, 'msg' => 'Stock insuficiente de ' . $producto['descripcion'] . ' (quedan ' . (int)$producto['existencia'] . ')']);
    }
    $precio = (float)$producto['precio'];
    if ($enCarrito) {
        db_exec('UPDATE detalle_temp SET cantidad = ?, total = ? WHERE id = ?', [$nuevaCantidad, round($nuevaCantidad * $precio, 2), (int)$enCarrito['id']]);
        responder(['ok' => true, 'msg' => 'Producto actualizado']);
    }
    db_exec(
        'INSERT INTO detalle_temp (id_usuario, id_producto, cantidad, precio_venta, total) VALUES (?, ?, ?, ?, ?)',
        [(string)$uid, $idProducto, $cant, $precio, round($cant * $precio, 2)]
    );
    responder(['ok' => true, 'msg' => 'Producto agregado']);
}

if ($accion === 'restar' || $accion === 'quitar') {
    require_permiso('nueva_venta');
    $idDetalle = entero($_POST['id'] ?? 0);
    $fila = db_one('SELECT id, cantidad, precio_venta FROM detalle_temp WHERE id = ? AND id_usuario = ?', [$idDetalle, $uid]);
    if (!$fila) {
        responder(['ok' => false, 'msg' => 'No encontrado']);
    }
    if ($accion === 'restar' && $fila['cantidad'] > 1) {
        $cantidad = (int)$fila['cantidad'] - 1;
        db_exec('UPDATE detalle_temp SET cantidad = ?, total = ? WHERE id = ?', [$cantidad, round($cantidad * $fila['precio_venta'], 2), $idDetalle]);
        responder(['ok' => true, 'msg' => 'Cantidad descontada']);
    }
    db_exec('DELETE FROM detalle_temp WHERE id = ?', [$idDetalle]);
    responder(['ok' => true, 'msg' => 'Producto quitado']);
}

if ($accion === 'procesar') {
    require_permiso('nueva_venta');
    $tipo = $_POST['tipo'] ?? 'mostrador';
    if (!array_key_exists($tipo, TIPOS_PEDIDO)) {
        responder(['ok' => false, 'msg' => 'Tipo de pedido no válido']);
    }
    $metodo = array_key_exists($_POST['metodo_pago'] ?? '', METODOS_PAGO) ? $_POST['metodo_pago'] : 'efectivo';
    $nota = texto($_POST['nota'] ?? '', 255);
    $idCliente = entero($_POST['id_cliente'] ?? 1);
    $cliente = db_one('SELECT idcliente, telefono, direccion FROM cliente WHERE idcliente = ? AND estado = 1', [$idCliente]);
    if (!$cliente) {
        $idCliente = 1;
        $cliente = db_one('SELECT idcliente, telefono, direccion FROM cliente WHERE idcliente = 1');
    }

    $idMesa = null;
    $direccion = null;
    $telefono = null;
    $costoEnvio = 0.0;
    if ($tipo === 'mesa') {
        $idMesa = entero($_POST['id_mesa'] ?? 0);
        if (!$idMesa || !db_one('SELECT id FROM mesas WHERE id = ? AND estado = 1', [$idMesa])) {
            responder(['ok' => false, 'msg' => 'Selecciona una mesa']);
        }
    } elseif ($tipo === 'delivery') {
        $direccion = texto($_POST['direccion_entrega'] ?? '', 200) ?: ($idCliente !== 1 ? $cliente['direccion'] : '');
        $telefono = texto($_POST['telefono_entrega'] ?? '', 20) ?: ($idCliente !== 1 ? $cliente['telefono'] : '');
        $costoEnvio = decimal_positivo($_POST['costo_envio'] ?? 0) ?? 0.0;
        if ($direccion === '') {
            responder(['ok' => false, 'msg' => 'Ingresa la dirección de entrega']);
        }
    }

    $conexion->begin_transaction();
    try {
        $carrito = db_all(
            'SELECT t.id_producto, t.cantidad, t.precio_venta, p.existencia, p.descripcion
             FROM detalle_temp t INNER JOIN producto p ON p.codproducto = t.id_producto
             WHERE t.id_usuario = ? FOR UPDATE',
            [$uid]
        );
        if (!$carrito) {
            throw new RuntimeException('No hay productos para generar la venta');
        }
        foreach ($carrito as $item) {
            if ((int)$item['cantidad'] > (int)$item['existencia']) {
                throw new RuntimeException('Stock insuficiente de ' . $item['descripcion'] . ' (quedan ' . (int)$item['existencia'] . ')');
            }
        }

        $idVenta = 0;
        $agregado = false;
        if ($tipo === 'mesa') {
            $idVenta = (int)db_val(
                "SELECT id FROM ventas WHERE tipo = 'mesa' AND id_mesa = ? AND estado NOT IN ('pagado', 'anulado') ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$idMesa]
            );
        }
        if ($idVenta) {
            // La mesa ya tiene una cuenta abierta: se agregan los productos a esa cuenta
            $agregado = true;
            db_exec(
                "UPDATE ventas SET estado = 'pendiente', nota = LEFT(CONCAT_WS(' | ', NULLIF(nota, ''), NULLIF(?, '')), 255) WHERE id = ?",
                [$nota, $idVenta]
            );
        } else {
            $estado = $tipo === 'mostrador' ? 'pagado' : 'pendiente';
            db_exec(
                'INSERT INTO ventas (id_cliente, total, id_usuario, tipo, id_mesa, estado, metodo_pago, costo_envio, direccion_entrega, telefono_entrega, nota)
                 VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$idCliente, $uid, $tipo, $idMesa, $estado, $metodo, $costoEnvio, $direccion, $telefono, $nota]
            );
            $idVenta = db_insert_id();
        }

        foreach ($carrito as $item) {
            db_exec(
                'INSERT INTO detalle_venta (id_producto, id_venta, cantidad, precio) VALUES (?, ?, ?, ?)',
                [(int)$item['id_producto'], $idVenta, (int)$item['cantidad'], (float)$item['precio_venta']]
            );
            db_exec('UPDATE producto SET existencia = existencia - ? WHERE codproducto = ?', [(int)$item['cantidad'], (int)$item['id_producto']]);
        }
        db_exec(
            'UPDATE ventas SET total = (SELECT COALESCE(SUM(cantidad * precio), 0) FROM detalle_venta WHERE id_venta = ?) + costo_envio WHERE id = ?',
            [$idVenta, $idVenta]
        );
        db_exec('DELETE FROM detalle_temp WHERE id_usuario = ?', [$uid]);
        $conexion->commit();
    } catch (RuntimeException $ex) {
        $conexion->rollback();
        responder(['ok' => false, 'msg' => $ex->getMessage()]);
    } catch (Throwable $ex) {
        $conexion->rollback();
        error_log('Error al procesar venta: ' . $ex->getMessage());
        responder(['ok' => false, 'msg' => 'Error al generar la venta']);
    }

    $pedido = obtener_pedido($idVenta);
    $tel = telefono_pedido($pedido);
    responder([
        'ok' => true,
        'id_venta' => $idVenta,
        'agregado' => $agregado,
        'tipo' => $tipo,
        'whatsapp' => $tel !== '' ? enlace_whatsapp($tel, mensaje_pedido($pedido)) : null,
    ]);
}

if ($accion === 'cambiar_clave') {
    $actual = (string)($_POST['actual'] ?? '');
    $nueva = (string)($_POST['nueva'] ?? '');
    if ($actual === '' || $nueva === '') {
        responder(['ok' => false, 'msg' => 'Los campos están vacíos']);
    }
    if (strlen($nueva) < 6) {
        responder(['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 6 caracteres']);
    }
    $hash = (string)db_val('SELECT clave FROM usuario WHERE idusuario = ?', [$uid]);
    $correcta = password_verify($actual, $hash) || (strlen($hash) === 32 && hash_equals($hash, md5($actual)));
    if (!$correcta) {
        responder(['ok' => false, 'msg' => 'La contraseña actual es incorrecta']);
    }
    db_exec('UPDATE usuario SET clave = ? WHERE idusuario = ?', [password_hash($nueva, PASSWORD_DEFAULT), $uid]);
    unset($_SESSION['clave_insegura']);
    responder(['ok' => true, 'msg' => 'Contraseña modificada']);
}

responder(['ok' => false, 'msg' => 'Acción no válida']);
