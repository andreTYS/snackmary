<?php
/*
 * Recibe un pedido desde la carta pública y lo registra en el sistema como
 * pedido "web" pendiente. No requiere login, por eso valida todo:
 * token de sesión, campo trampa anti-bots, límite de pedidos por IP,
 * precios y stock tomados de la base de datos.
 */
require_once __DIR__ . '/src/includes/app.php';
header('Content-Type: application/json; charset=utf-8');

function salir(array $r, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($r);
    exit;
}

if (!es_post()) {
    salir(['ok' => false, 'msg' => 'Método no permitido'], 405);
}
if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    salir(['ok' => false, 'msg' => 'La página expiró. Recárgala e intenta de nuevo.'], 403);
}
if (!empty($_POST['web'])) { // campo trampa: solo los bots lo llenan
    salir(['ok' => false, 'msg' => 'Pedido rechazado'], 400);
}
$negocio = config_negocio();
if (empty($negocio['carta_activa'])) {
    salir(['ok' => false, 'msg' => 'En este momento no estamos recibiendo pedidos por la carta.']);
}

$ip = substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$ip = trim(explode(',', $ip)[0]);
$recientes = (int)db_val("SELECT COUNT(*) FROM ventas WHERE origen = 'web' AND ip = ? AND fecha > NOW() - INTERVAL 10 MINUTE", [$ip]);
if ($recientes >= 3 || ($_SESSION['pedidos_web'] ?? 0) >= 5) {
    salir(['ok' => false, 'msg' => 'Ya enviaste varios pedidos. Si necesitas algo más, escríbenos por WhatsApp.'], 429);
}

$nombre = texto($_POST['nombre'] ?? '', 80);
$telefono = substr(preg_replace('/\D+/', '', (string)($_POST['telefono'] ?? '')), 0, 15);
$entrega = ($_POST['entrega'] ?? '') === 'recojo' ? 'recojo' : 'delivery';
$direccion = texto($_POST['direccion'] ?? '', 200);
$ubicacion = preg_match('/^-?\d{1,3}\.\d{3,8},-?\d{1,3}\.\d{3,8}$/', (string)($_POST['ubicacion'] ?? '')) ? $_POST['ubicacion'] : null;
$metodo = array_key_exists($_POST['pago'] ?? '', METODOS_PAGO) ? $_POST['pago'] : 'efectivo';
$nota = texto($_POST['nota'] ?? '', 200);
$items = json_decode((string)($_POST['items'] ?? '[]'), true);

if (mb_strlen($nombre) < 2) {
    salir(['ok' => false, 'msg' => 'Escribe tu nombre']);
}
if (strlen($telefono) < 9) {
    salir(['ok' => false, 'msg' => 'Escribe un número de celular válido (9 dígitos)']);
}
if ($entrega === 'delivery' && $direccion === '' && !$ubicacion) {
    salir(['ok' => false, 'msg' => 'Escribe tu dirección o comparte tu ubicación']);
}
if (!is_array($items) || !$items || count($items) > 30) {
    salir(['ok' => false, 'msg' => 'Tu pedido está vacío']);
}
$lista = [];
foreach ($items as $it) {
    $cant = (int)($it['cant'] ?? 0);
    if ($cant < 1 || $cant > 50) {
        salir(['ok' => false, 'msg' => 'Cantidad no válida']);
    }
    $lista[] = ['id_producto' => (int)($it['id'] ?? 0), 'cantidad' => $cant];
}

$conexion->begin_transaction();
try {
    // Cliente: se reconoce por su celular
    $cliente = db_one('SELECT idcliente FROM cliente WHERE telefono = ? AND estado = 1 ORDER BY idcliente DESC LIMIT 1', [$telefono]);
    if ($cliente) {
        $idCliente = (int)$cliente['idcliente'];
        if ($entrega === 'delivery' && $direccion !== '') {
            db_exec('UPDATE cliente SET direccion = ? WHERE idcliente = ?', [$direccion, $idCliente]);
        }
    } else {
        db_exec('INSERT INTO cliente (nombre, telefono, direccion, usuario_id) VALUES (?, ?, ?, 1)', [$nombre, $telefono, $direccion ?: 'S/D']);
        $idCliente = db_insert_id();
    }
    $resultado = registrar_pedido($lista, [
        'tipo' => $entrega === 'delivery' ? 'delivery' : 'mostrador',
        'id_cliente' => $idCliente,
        'id_usuario' => 0,
        'estado' => 'pendiente',
        'metodo_pago' => $metodo,
        'costo_envio' => $entrega === 'delivery' ? (float)($negocio['costo_envio'] ?? 0) : 0,
        'direccion_entrega' => $entrega === 'delivery' ? ($direccion ?: 'Ubicación compartida') : null,
        'telefono_entrega' => $telefono,
        'nota' => trim(($entrega === 'recojo' ? 'RECOJO EN TIENDA. ' : '') . $nota . ' (' . $nombre . ')'),
        'origen' => 'web',
        'ubicacion' => $ubicacion,
        'ip' => $ip,
    ]);
    $conexion->commit();
} catch (RuntimeException $ex) {
    $conexion->rollback();
    salir(['ok' => false, 'msg' => $ex->getMessage()]);
} catch (Throwable $ex) {
    $conexion->rollback();
    error_log('Pedido web: ' . $ex->getMessage());
    salir(['ok' => false, 'msg' => 'No pudimos registrar tu pedido. Intenta de nuevo.'], 500);
}

$_SESSION['pedidos_web'] = ($_SESSION['pedidos_web'] ?? 0) + 1;
$pedido = obtener_pedido($resultado['id']);
$texto = "Hola, acabo de hacer el pedido *#{$pedido['id']}* desde la carta:\n\n";
foreach ($pedido['items'] as $it) {
    $texto .= "{$it['cantidad']} x {$it['descripcion']}\n";
}
$texto .= "\n*Total: S/ " . dinero($pedido['total']) . "*\n";
$texto .= 'Nombre: ' . $nombre . "\n" . ($entrega === 'delivery' ? 'Delivery a: ' . ($direccion ?: 'mi ubicación') : 'Recojo en tienda') . "\n";
$texto .= 'Pago: ' . METODOS_PAGO[$metodo];
if ($ubicacion) {
    $texto .= "\nUbicación: " . enlace_mapa(null, $ubicacion);
}
$numero = preg_replace('/\D+/', '', (string)($negocio['whatsapp'] ?: ($negocio['telefono'] ?? '')));

salir([
    'ok' => true,
    'id' => (int)$pedido['id'],
    'total' => dinero($pedido['total']),
    'whatsapp' => $numero !== '' ? enlace_whatsapp($numero, $texto) : null,
]);
