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

if (isset($_GET['grilla'])) {
    // Productos para la grilla táctil de Nueva venta
    require_permiso('nueva_venta');
    $activas = array_flip(array_map('intval', array_column(db_all('SELECT id FROM categorias WHERE estado = 1'), 'id')));
    $conOpciones = productos_con_opciones();
    $datos = [];
    foreach (db_all(
        'SELECT p.codproducto, p.codigo, p.descripcion, p.precio, p.existencia, p.controla_stock, p.imagen, p.id_categoria
         FROM producto p LEFT JOIN categorias c ON c.id = p.id_categoria
         WHERE p.estado = 1
         ORDER BY COALESCE(c.orden, 999), p.descripcion'
    ) as $row) {
        $datos[] = [
            'id' => (int)$row['codproducto'],
            'codigo' => $row['codigo'],
            'nombre' => $row['descripcion'],
            'precio' => dinero($row['precio']),
            'stock' => (int)$row['controla_stock'] === 1 ? (int)$row['existencia'] : null,
            'imagen' => imagen_producto($row['imagen']) ? '../' . imagen_producto($row['imagen']) : null,
            'categoria' => $row['id_categoria'] !== null && isset($activas[(int)$row['id_categoria']]) ? (int)$row['id_categoria'] : 0,
            'opciones' => isset($conOpciones[(int)$row['codproducto']]),
        ];
    }
    responder($datos);
}

if (isset($_GET['sondeo'])) {
    // Aviso de pedidos nuevos (pantallas de Pedidos y Cocina)
    require_permiso('pedidos', 'cocina', 'mesas', 'repartidor');
    responder([
        'ultimo' => (int)db_val('SELECT COALESCE(MAX(id), 0) FROM ventas'),
        'web_pendientes' => (int)db_val("SELECT COUNT(*) FROM ventas WHERE origen = 'web' AND estado = 'pendiente'"),
        'cocina' => (int)db_val("SELECT COUNT(*) FROM ventas WHERE estado <> 'anulado' AND estado_cocina = 'pendiente'"),
        'cambio' => (string)db_val(
            "SELECT CONCAT(COUNT(*), '-', COALESCE(SUM(id * CRC32(CONCAT(estado, ':', estado_cocina, ':', COALESCE(id_repartidor, 0)))), 0),
                           '-', (SELECT COALESCE(MAX(id), 0) FROM detalle_venta))
             FROM ventas WHERE estado NOT IN ('pagado', 'anulado') OR estado_cocina IN ('pendiente', 'preparando')"
        ),
    ]);
}

if (isset($_GET['cocina'])) {
    // Pedidos a preparar, sin precios. Solo productos que van a cocina.
    require_permiso('cocina', 'pedidos');
    $lista = [];
    foreach (db_all("SELECT id FROM ventas WHERE estado <> 'anulado' AND estado_cocina IN ('pendiente', 'preparando') ORDER BY fecha ASC") as $r) {
        $p = obtener_pedido((int)$r['id']);
        $items = [];
        foreach (db_all(
            'SELECT d.cantidad, d.ronda, d.opciones_txt, p.descripcion FROM detalle_venta d
             INNER JOIN producto p ON p.codproducto = d.id_producto
             LEFT JOIN categorias c ON c.id = p.id_categoria
             WHERE d.id_venta = ? AND COALESCE(c.cocina, 1) = 1 ORDER BY d.ronda, d.id',
            [(int)$p['id']]
        ) as $it) {
            $items[] = [
                'cantidad' => (int)$it['cantidad'],
                'nombre' => $it['descripcion'],
                'opciones' => $it['opciones_txt'],
                'nuevo' => $p['ultima_ronda'] > 1 && (int)$it['ronda'] === $p['ultima_ronda'],
                'anterior' => $p['ultima_ronda'] > 1 && (int)$it['ronda'] < $p['ultima_ronda'],
            ];
        }
        $lista[] = [
            'id' => (int)$p['id'],
            'tipo' => $p['tipo'],
            'tipo_texto' => TIPOS_PEDIDO[$p['tipo']] ?? $p['tipo'],
            'mesa' => $p['mesa'],
            'cliente' => (int)$p['id_cliente'] === 1 ? '' : $p['cliente'],
            'origen' => $p['origen'],
            'estado' => $p['estado_cocina'],
            'nota' => $p['nota'],
            'ronda' => $p['ultima_ronda'],
            'minutos' => (int)floor((time() - strtotime($p['fecha'])) / 60),
            'items' => $items,
        ];
    }
    responder($lista);
}

if (isset($_GET['opciones'])) {
    require_permiso('nueva_venta');
    $idp = entero($_GET['opciones']);
    $p = db_one('SELECT codproducto, descripcion, precio FROM producto WHERE codproducto = ? AND estado = 1', [$idp]);
    responder($p ? ['ok' => true, 'id' => (int)$p['codproducto'], 'nombre' => $p['descripcion'], 'precio' => (float)$p['precio'], 'grupos' => grupos_opciones($idp)] : ['ok' => false, 'msg' => 'Producto no encontrado']);
}

if (isset($_GET['detalle'])) {
    require_permiso('nueva_venta');
    $filas = db_all(
        'SELECT d.id, d.id_producto, d.cantidad, d.precio_venta, d.opciones_txt, p.descripcion
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
            'opciones' => $row['opciones_txt'],
            'cantidad' => (int)$row['cantidad'],
            'precio_venta' => dinero($row['precio_venta']),
            'sub_total' => round($row['precio_venta'] * $row['cantidad'], 2),
        ];
    }
    responder($datos);
}

/**
 * Unidades de stock que ocupa el carrito de un usuario, como lista plana
 * [['id_producto' => X, 'cantidad' => unidades], ...] lista para verificar_stock().
 * Cuenta el producto (si controla stock) y las bebidas vinculadas a sus opciones.
 */
function unidades_carrito(int $uid): array
{
    $plana = [];
    foreach (db_all('SELECT d.id_producto, d.cantidad, d.opciones, p.controla_stock FROM detalle_temp d INNER JOIN producto p ON p.codproducto = d.id_producto WHERE d.id_usuario = ?', [(string)$uid]) as $r) {
        if ((int)$r['controla_stock'] === 1) {
            $plana[] = ['id_producto' => (int)$r['id_producto'], 'cantidad' => (int)$r['cantidad']];
        }
        foreach (array_filter(explode(',', $r['opciones'])) as $idOp) {
            $vinc = db_val('SELECT id_producto_stock FROM opciones WHERE id = ?', [(int)$idOp]);
            if ($vinc) {
                $plana[] = ['id_producto' => (int)$vinc, 'cantidad' => (int)$r['cantidad']];
            }
        }
    }
    return $plana;
}

/* ---------- Escrituras (solo POST con token CSRF) ---------- */
if (!es_post()) {
    responder(['ok' => false, 'msg' => 'Acción no válida']);
}
csrf_check();
$accion = $_POST['action'] ?? '';

if ($accion === 'agregar' || $accion === 'sumar') {
    require_permiso('nueva_venta');
    if ($accion === 'sumar') {
        // "+" en una línea del carrito: repite el mismo producto con las mismas opciones
        $linea = db_one('SELECT id_producto, opciones FROM detalle_temp WHERE id = ? AND id_usuario = ?', [entero($_POST['id'] ?? 0), (string)$uid]);
        if (!$linea) {
            responder(['ok' => false, 'msg' => 'No encontrado']);
        }
        $idProducto = (int)$linea['id_producto'];
        $elegidas = array_filter(explode(',', $linea['opciones']));
        $cant = 1;
    } else {
        $idProducto = entero($_POST['id'] ?? 0);
        $elegidas = (array)($_POST['opciones'] ?? []);
        $cant = max(1, min(50, entero($_POST['cant'] ?? 1)));
    }
    $producto = db_one('SELECT codproducto, precio, existencia, descripcion, controla_stock FROM producto WHERE codproducto = ? AND estado = 1', [$idProducto]);
    if (!$producto) {
        responder(['ok' => false, 'msg' => 'Producto no encontrado']);
    }
    try {
        $op = resolver_opciones($idProducto, $elegidas, $producto['descripcion']);
        // Stock: lo que ya hay en el carrito + lo nuevo (producto y bebidas vinculadas)
        $planas = unidades_carrito($uid);
        if ((int)$producto['controla_stock'] === 1) {
            $planas[] = ['id_producto' => $idProducto, 'cantidad' => $cant];
        }
        foreach ($op['consumo'] as $idp => $u) {
            $planas[] = ['id_producto' => $idp, 'cantidad' => $u * $cant];
        }
        verificar_stock($planas);
    } catch (RuntimeException $ex) {
        responder(['ok' => false, 'msg' => $ex->getMessage()]);
    }
    $precio = round((float)$producto['precio'] + $op['extra'], 2);
    $enCarrito = db_one('SELECT id, cantidad FROM detalle_temp WHERE id_producto = ? AND opciones = ? AND id_usuario = ?', [$idProducto, $op['clave'], (string)$uid]);
    if ($enCarrito) {
        $nueva = (int)$enCarrito['cantidad'] + $cant;
        db_exec('UPDATE detalle_temp SET cantidad = ?, precio_venta = ?, total = ? WHERE id = ?', [$nueva, $precio, round($nueva * $precio, 2), (int)$enCarrito['id']]);
        responder(['ok' => true, 'msg' => 'Producto actualizado']);
    }
    db_exec(
        'INSERT INTO detalle_temp (id_usuario, id_producto, cantidad, precio_venta, total, opciones, opciones_txt) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [(string)$uid, $idProducto, $cant, $precio, round($cant * $precio, 2), $op['clave'], $op['txt']]
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
        $carrito = db_all('SELECT id_producto, cantidad, opciones FROM detalle_temp WHERE id_usuario = ? FOR UPDATE', [(string)$uid]);
        if (!$carrito) {
            throw new RuntimeException('No hay productos para generar la venta');
        }
        $resultado = registrar_pedido($carrito, [
            'tipo' => $tipo,
            'id_cliente' => $idCliente,
            'id_usuario' => $uid,
            'id_mesa' => $idMesa,
            'estado' => $tipo === 'mostrador' && ($_POST['cobrar'] ?? '1') === '1' ? 'pagado' : 'pendiente',
            'metodo_pago' => $metodo,
            'costo_envio' => $costoEnvio,
            'direccion_entrega' => $direccion,
            'telefono_entrega' => $telefono,
            'nota' => $nota,
            'origen' => 'local',
        ]);
        $idVenta = $resultado['id'];
        $agregado = $resultado['agregado'];
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
        'cocina' => $pedido['estado_cocina'] === 'pendiente',
        'whatsapp' => $tel !== '' ? enlace_whatsapp($tel, mensaje_pedido($pedido)) : null,
    ]);
}

if ($accion === 'cambiar_clave') {
    // Se mantiene por compatibilidad; el cambio de contraseña ahora está en perfil.php
    responder(['ok' => false, 'msg' => 'Usa la página Cambiar contraseña del menú de usuario']);
}

responder(['ok' => false, 'msg' => 'Acción no válida']);
