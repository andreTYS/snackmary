<?php
/*
 * Snack Mary - datos de demostración.
 *
 * BORRA TODA LA BASE DE DATOS y la vuelve a crear con datos de prueba:
 * carta con opciones, bebidas con stock, mesas, clientes, usuarios por rol,
 * 14 días de ventas con sus cajas cerradas y algunos pedidos en curso.
 *
 * Solo funciona en la instancia demo (APP_DEMO=1) o con --forzar.
 *   docker exec snackmary-demo-app php /var/www/html/database/demo_datos.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('APP_DEMO') !== '1' && !in_array('--forzar', $argv, true)) {
    fwrite(STDERR, "Este script BORRA la base de datos. Solo se ejecuta con APP_DEMO=1 (instancia demo) o con --forzar.\n");
    exit(1);
}
$_SERVER['SCRIPT_NAME'] = '/database/demo_datos.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__ . '/../src/includes/app.php';
mt_srand(2026);

/* ---------- 1. Base limpia ---------- */
$conexion->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($conexion->query('SHOW TABLES')->fetch_all() as [$tabla]) {
    $conexion->query('DROP TABLE `' . str_replace('`', '', $tabla) . '`');
}
$conexion->query('SET FOREIGN_KEY_CHECKS = 1');
$conexion->multi_query(file_get_contents(__DIR__ . '/instalacion.sql'));
do {
    if ($r = $conexion->store_result()) {
        $r->free();
    }
} while ($conexion->more_results() && $conexion->next_result());

/* ---------- 2. Negocio, usuarios, mesas ---------- */
db_exec("UPDATE configuracion SET nombre = 'Snack Mary (Demo)', telefono = '053-000000', email = 'demo@ejemplo.com',
         direccion = 'Calle Moquegua 123, Moquegua', whatsapp = NULL, codigo_pais = '51', costo_envio = 3.00, carta_activa = 1 WHERE id = 1");
$usuarios = [
    // [id, nombre, correo, usuario, clave, permisos]
    [1, 'Administrador Demo', 'admin@ejemplo.com', 'admin', 'Demo2026', []],
    [2, 'Carla (cajera)', 'cajero@ejemplo.com', 'cajero', 'Cajero2026', ['nueva_venta', 'mesas', 'pedidos', 'caja', 'clientes', 'ventas']],
    [3, 'Luis (cocina)', 'cocina@ejemplo.com', 'cocina', 'Cocina2026', ['cocina']],
    [4, 'Jorge (delivery)', 'moto@ejemplo.com', 'moto', 'Moto2026', ['repartidor']],
];
db_exec('DELETE FROM usuario');
foreach ($usuarios as [$id, $nombre, $correo, $usuario, $clave, $permisos]) {
    db_exec('INSERT INTO usuario (idusuario, nombre, correo, usuario, clave) VALUES (?, ?, ?, ?, ?)', [$id, $nombre, $correo, $usuario, password_hash($clave, PASSWORD_DEFAULT)]);
    foreach ($permisos as $perm) {
        db_exec('INSERT INTO detalle_permisos (id_usuario, id_permiso) SELECT ?, id FROM permisos WHERE nombre = ?', [$id, $perm]);
    }
}
db_exec('DELETE FROM mesas');
foreach (['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5', 'Mesa 6', 'Barra'] as $i => $m) {
    db_exec('INSERT INTO mesas (nombre, capacidad) VALUES (?, ?)', [$m, $m === 'Barra' ? 3 : ($i < 4 ? 4 : 6)]);
}
$clientes = [
    ['María Quispe', '999000101', 'Jr. Ayacucho 245, ref. frente al mercado'],
    ['José Mamani', '999000102', 'Av. Balta 512'],
    ['Rosa Flores', '999000103', 'Calle Lima 88, 2do piso'],
    ['Carlos Ticona', '999000104', 'Urb. San Antonio Mz B Lt 7'],
    ['Lucía Huanca', '999000105', 'Jr. Ancash 310'],
    ['Pedro Condori', '999000106', 'Av. 25 de Noviembre 1020'],
    ['Ana Vargas', '999000107', 'Calle Tacna 145'],
    ['Miguel Chura', '999000108', 'Jr. Junín 77'],
];
foreach ($clientes as [$n, $t, $d]) {
    db_exec('INSERT INTO cliente (nombre, telefono, direccion, usuario_id) VALUES (?, ?, ?, 1)', [$n, $t, $d]);
}
$idsClientes = array_map('intval', array_column(db_all('SELECT idcliente FROM cliente WHERE idcliente > 1'), 'idcliente'));

/* ---------- 3. Carta ---------- */
db_exec('DELETE FROM categorias');
$cat = [];
foreach ([['Salchipapas', 1, 1], ['Hamburguesas', 2, 1], ['Pollo', 3, 1], ['Combos', 4, 1], ['Bebidas', 5, 0], ['Postres', 6, 0]] as [$n, $o, $c]) {
    db_exec('INSERT INTO categorias (nombre, orden, cocina) VALUES (?, ?, ?)', [$n, $o, $c]);
    $cat[$n] = db_insert_id();
}
$prod = [];
function producto(string $codigo, string $nombre, float $precio, int $idCat, ?int $stock = null): int
{
    db_exec(
        'INSERT INTO producto (codigo, descripcion, precio, existencia, usuario_id, id_categoria, controla_stock) VALUES (?, ?, ?, ?, 1, ?, ?)',
        [$codigo, $nombre, $precio, $stock ?? 0, $idCat, $stock === null ? 0 : 1]
    );
    return db_insert_id();
}
// Stock alto para la historia; al final se deja en cantidades realistas
foreach ([
    ['INCA-500', 'Inca Kola 500 ml', 3.50], ['COCA-500', 'Coca Cola 500 ml', 3.50], ['INCA-1.5', 'Inca Kola 1.5 L', 8.00],
    ['COCA-1.5', 'Coca Cola 1.5 L', 8.00], ['AGUA-625', 'Agua San Luis 625 ml', 2.00],
] as [$c, $n, $p]) {
    $prod[$c] = producto($c, $n, $p, $cat['Bebidas'], 5000);
}
$prod['CHICHA'] = producto('CHICHA', 'Chicha morada (vaso)', 3.00, $cat['Bebidas']);
$prod['LIMON'] = producto('LIMON', 'Limonada (vaso)', 3.00, $cat['Bebidas']);
$prod['GELA'] = producto('GELA', 'Gelatina', 2.50, $cat['Postres'], 5000);
$prod['TORTA'] = producto('TORTA', 'Torta de chocolate (tajada)', 6.00, $cat['Postres'], 5000);
$prod['SALCHI'] = producto('SALCHI', 'Salchipapa', 10.00, $cat['Salchipapas']);
$prod['SALCHI-ESP'] = producto('SALCHI-ESP', 'Salchipapa especial', 14.00, $cat['Salchipapas']);
$prod['MOLLE'] = producto('MOLLE', 'Mollejitas fritas', 12.00, $cat['Salchipapas']);
$prod['HAMB'] = producto('HAMB', 'Hamburguesa clásica', 8.00, $cat['Hamburguesas']);
$prod['HAMB-ROYAL'] = producto('HAMB-ROYAL', 'Hamburguesa royal', 10.00, $cat['Hamburguesas']);
$prod['HAMB-POBRE'] = producto('HAMB-POBRE', 'Hamburguesa a lo pobre', 13.00, $cat['Hamburguesas']);
$prod['ALITAS'] = producto('ALITAS', 'Alitas BBQ (6 und.)', 15.00, $cat['Pollo']);
$prod['BROASTER'] = producto('BROASTER', 'Pollo broaster 1/4', 14.00, $cat['Pollo']);
$prod['COMBO-SALCHI'] = producto('COMBO-SALCHI', 'Combo salchipapa + gaseosa', 13.00, $cat['Combos']);
$prod['COMBO-HAMB'] = producto('COMBO-HAMB', 'Combo hamburguesa + papas + gaseosa', 15.00, $cat['Combos']);
$prod['COMBO-FAM'] = producto('COMBO-FAM', 'Combo familiar (2 salchipapas + broaster + 1.5 L)', 45.00, $cat['Combos']);

function grupo(int $idProducto, string $nombre, int $min, int $max, array $opciones): void
{
    static $orden = [];
    $orden[$idProducto] = ($orden[$idProducto] ?? 0) + 1;
    db_exec('INSERT INTO opcion_grupos (id_producto, nombre, minimo, maximo, orden) VALUES (?, ?, ?, ?, ?)', [$idProducto, $nombre, $min, $max, $orden[$idProducto]]);
    $g = db_insert_id();
    foreach ($opciones as $i => [$n, $precio, $vinculo]) {
        db_exec('INSERT INTO opciones (id_grupo, nombre, precio, id_producto_stock, orden) VALUES (?, ?, ?, ?, ?)', [$g, $n, $precio, $vinculo, $i + 1]);
    }
}
$tipoSalchi = [['Clásica', 0, null], ['Pura papa', 0, null], ['Con mollejitas', 3, null], ['Con huevo', 1.5, null]];
$extras = [['+ Porción de salchicha', 3, null], ['+ Porción de papas', 4, null], ['+ Queso', 2, null], ['+ Huevo frito', 1.5, null]];
$cremas = [['Mayonesa', 0, null], ['Ketchup', 0, null], ['Ají', 0, null], ['Mostaza', 0, null], ['Tártara', 0, null]];
$bebida500 = [['Inca Kola 500 ml', 0, $prod['INCA-500']], ['Coca Cola 500 ml', 0, $prod['COCA-500']], ['Chicha morada', 0, null], ['Agua San Luis', 0, $prod['AGUA-625']]];
$bebida15 = [['Inca Kola 1.5 L', 0, $prod['INCA-1.5']], ['Coca Cola 1.5 L', 0, $prod['COCA-1.5']]];
foreach (['SALCHI', 'SALCHI-ESP'] as $p) {
    grupo($prod[$p], 'Tipo', 1, 1, $tipoSalchi);
    grupo($prod[$p], 'Extras', 0, 3, $extras);
    grupo($prod[$p], 'Cremas', 0, 5, $cremas);
}
foreach (['HAMB', 'HAMB-ROYAL', 'HAMB-POBRE'] as $p) {
    grupo($prod[$p], 'Extras', 0, 3, $extras);
    grupo($prod[$p], 'Cremas', 0, 5, $cremas);
}
grupo($prod['ALITAS'], 'Salsa', 1, 1, [['BBQ', 0, null], ['Picante', 0, null], ['Acevichada', 1, null]]);
grupo($prod['BROASTER'], 'Presa', 1, 1, [['Pecho', 1, null], ['Pierna', 0, null], ['Ala', 0, null]]);
grupo($prod['BROASTER'], 'Cremas', 0, 5, $cremas);
grupo($prod['COMBO-SALCHI'], 'Tipo', 1, 1, $tipoSalchi);
grupo($prod['COMBO-SALCHI'], 'Bebida del combo', 1, 1, $bebida500);
grupo($prod['COMBO-SALCHI'], 'Cremas', 0, 5, $cremas);
grupo($prod['COMBO-HAMB'], 'Hamburguesa', 1, 1, [['Clásica', 0, null], ['Royal', 2, null], ['A lo pobre', 4, null]]);
grupo($prod['COMBO-HAMB'], 'Bebida del combo', 1, 1, $bebida500);
grupo($prod['COMBO-FAM'], 'Bebida del combo', 1, 1, $bebida15);
grupo($prod['COMBO-FAM'], 'Cremas', 0, 5, $cremas);

/* ---------- 4. Ventas de los últimos 14 días ---------- */
/** Elige opciones válidas al azar para un producto. */
function opciones_al_azar(int $idProducto): array
{
    $ids = [];
    foreach (grupos_opciones($idProducto) as $g) {
        $disp = array_values(array_filter($g['opciones'], fn($o) => !$o['agotado']));
        $n = $g['minimo'] >= 1 ? 1 : (mt_rand(1, 100) <= 35 ? mt_rand(1, min(2, $g['maximo'])) : 0);
        shuffle($disp);
        foreach (array_slice($disp, 0, $n) as $o) {
            $ids[] = $o['id'];
        }
    }
    return $ids;
}
$populares = [
    'SALCHI' => 30, 'COMBO-SALCHI' => 22, 'HAMB' => 14, 'HAMB-ROYAL' => 12, 'SALCHI-ESP' => 10, 'BROASTER' => 9,
    'ALITAS' => 8, 'MOLLE' => 7, 'COMBO-HAMB' => 9, 'HAMB-POBRE' => 6, 'COMBO-FAM' => 3,
    'INCA-500' => 16, 'COCA-500' => 12, 'CHICHA' => 9, 'LIMON' => 6, 'AGUA-625' => 4, 'INCA-1.5' => 3, 'GELA' => 4, 'TORTA' => 3,
];
function elegir(array $pesos): string
{
    $r = mt_rand(1, array_sum($pesos));
    foreach ($pesos as $k => $p) {
        if (($r -= $p) <= 0) {
            return $k;
        }
    }
    return array_key_first($pesos);
}
function items_al_azar(array $prod, array $populares): array
{
    $items = [];
    $platos = mt_rand(1, 100) <= 70 ? mt_rand(1, 2) : mt_rand(3, 4);
    for ($i = 0; $i < $platos; $i++) {
        $c = elegir($populares);
        $items[] = ['id_producto' => $prod[$c], 'cantidad' => mt_rand(1, 100) <= 80 ? 1 : 2, 'opciones' => opciones_al_azar($prod[$c])];
    }
    return $items;
}
$metodos = ['efectivo' => 55, 'yape' => 30, 'plin' => 8, 'tarjeta' => 7];
$mesas = array_map('intval', array_column(db_all('SELECT id FROM mesas'), 'id'));
$hoy = strtotime(date('Y-m-d'));

$conexion->begin_transaction();
for ($dia = 14; $dia >= 1; $dia--) {
    $base = $hoy - $dia * 86400;
    $finDeSemana = in_array((int)date('N', $base), [5, 6, 7], true);
    $apertura = date('Y-m-d H:i:s', $base + 17 * 3600 + mt_rand(0, 20) * 60);
    db_exec('INSERT INTO cajas (id_usuario_apertura, fecha_apertura, monto_inicial) VALUES (2, ?, 50)', [$apertura]);
    $idCaja = db_insert_id();
    $pedidos = mt_rand($finDeSemana ? 22 : 12, $finDeSemana ? 34 : 22);
    for ($k = 0; $k < $pedidos; $k++) {
        $t = $base + 17 * 3600 + 1800 + (int)(($k + mt_rand(0, 60) / 100) / $pedidos * 5.5 * 3600);
        $r = mt_rand(1, 100);
        $tipo = $r <= 40 ? 'mostrador' : ($r <= 75 ? 'mesa' : 'delivery');
        $web = $tipo !== 'mesa' && mt_rand(1, 100) <= 30;
        $cliente = ($tipo === 'delivery' || $web) ? $idsClientes[array_rand($idsClientes)] : 1;
        $datosCli = db_one('SELECT telefono, direccion FROM cliente WHERE idcliente = ?', [$cliente]);
        $metodo = elegir($metodos);
        $res = registrar_pedido(items_al_azar($prod, $populares), [
            'tipo' => $tipo,
            'id_cliente' => $cliente,
            'id_usuario' => $web ? 0 : 2,
            'id_mesa' => $tipo === 'mesa' ? $mesas[array_rand($mesas)] : null,
            'estado' => 'pendiente',
            'metodo_pago' => $metodo,
            'costo_envio' => $tipo === 'delivery' ? 3 : 0,
            'direccion_entrega' => $tipo === 'delivery' ? $datosCli['direccion'] : null,
            'telefono_entrega' => $tipo === 'delivery' ? $datosCli['telefono'] : null,
            'origen' => $web ? 'web' : 'local',
        ]);
        $id = $res['id'];
        $fecha = date('Y-m-d H:i:s', $t);
        if (mt_rand(1, 100) <= 4) {
            anular_pedido($id);
            db_exec("UPDATE ventas SET fecha = ?, estado_cocina = 'listo' WHERE id = ?", [$fecha, $id]);
            continue;
        }
        db_exec(
            "UPDATE ventas SET fecha = ?, estado = 'pagado', estado_cocina = IF(estado_cocina = 'no', 'no', 'listo'), fecha_pago = ?, id_caja = ?,
                    id_repartidor = IF(tipo = 'delivery', 4, NULL) WHERE id = ?",
            [$fecha, date('Y-m-d H:i:s', $t + mt_rand(10, 45) * 60), $idCaja, $id]
        );
    }
    // Gastos del día y cierre
    db_exec("INSERT INTO caja_movimientos (id_caja, tipo, concepto, monto, metodo_pago, id_usuario, fecha) VALUES (?, 'egreso', 'Compra de pan', ?, 'efectivo', 2, ?)", [$idCaja, mt_rand(12, 20), date('Y-m-d H:i:s', $base + 18 * 3600)]);
    if (mt_rand(1, 100) <= 40) {
        db_exec("INSERT INTO caja_movimientos (id_caja, tipo, concepto, monto, metodo_pago, id_usuario, fecha) VALUES (?, 'egreso', 'Balón de gas', 45, 'efectivo', 2, ?)", [$idCaja, date('Y-m-d H:i:s', $base + 19 * 3600)]);
    }
    $caja = db_one('SELECT * FROM cajas WHERE id = ?', [$idCaja]);
    $resumen = resumen_caja($caja);
    $diferencia = [0, 0, 0, 0, 0, -1, 0.5, -2][mt_rand(0, 7)];
    db_exec(
        "UPDATE cajas SET estado = 'cerrada', id_usuario_cierre = 2, fecha_cierre = ?, efectivo_esperado = ?, efectivo_contado = ?, resumen = ?, observacion = ? WHERE id = ?",
        [
            date('Y-m-d H:i:s', $base + 23 * 3600 + mt_rand(20, 50) * 60), $resumen['efectivo_esperado'], $resumen['efectivo_esperado'] + $diferencia,
            json_encode($resumen, JSON_UNESCAPED_UNICODE), $diferencia < 0 ? 'Vuelto mal dado' : '', $idCaja,
        ]
    );
}

/* ---------- 5. Hoy: caja abierta y pedidos en curso ---------- */
db_exec('INSERT INTO cajas (id_usuario_apertura, fecha_apertura, monto_inicial) VALUES (2, ?, 50)', [date('Y-m-d H:i:s', time() - 3 * 3600)]);
$ahora = time();
$enCurso = [
    ['mesa', 1, 'local', null, 'preparando', 18],
    ['mesa', 1, 'local', null, 'pendiente', 6],
    ['delivery', $idsClientes[0], 'web', null, 'pendiente', 3],
    ['delivery', $idsClientes[3], 'local', 4, 'en_camino', 25],
    ['mostrador', 1, 'local', null, 'pendiente', 9],
];
foreach ($enCurso as $i => [$tipo, $cliente, $origen, $repartidor, $estado, $minutos]) {
    $datosCli = db_one('SELECT telefono, direccion FROM cliente WHERE idcliente = ?', [$cliente]);
    $res = registrar_pedido(items_al_azar($prod, $populares), [
        'tipo' => $tipo,
        'id_cliente' => $cliente,
        'id_usuario' => $origen === 'web' ? 0 : 2,
        'id_mesa' => $tipo === 'mesa' ? $mesas[$i] : null,
        'estado' => 'pendiente',
        'metodo_pago' => $tipo === 'delivery' ? 'yape' : 'efectivo',
        'costo_envio' => $tipo === 'delivery' ? 3 : 0,
        'direccion_entrega' => $tipo === 'delivery' ? $datosCli['direccion'] : null,
        'telefono_entrega' => $tipo === 'delivery' ? $datosCli['telefono'] : null,
        'nota' => $i === 0 ? 'Una sin cebolla' : null,
        'origen' => $origen,
    ]);
    db_exec(
        "UPDATE ventas SET fecha = ?, estado = ?, id_repartidor = ?,
                estado_cocina = IF(estado_cocina = 'no', 'no', IF(? IN ('pendiente', 'preparando'), ?, 'listo')) WHERE id = ?",
        [date('Y-m-d H:i:s', $ahora - $minutos * 60), $estado, $repartidor, $estado, $estado, $res['id']]
    );
}
// Stock final realista (con una bebida casi agotada para ver los avisos)
foreach (['INCA-500' => 34, 'COCA-500' => 28, 'INCA-1.5' => 9, 'COCA-1.5' => 2, 'AGUA-625' => 18, 'GELA' => 12, 'TORTA' => 5] as $c => $n) {
    db_exec('UPDATE producto SET existencia = ? WHERE codproducto = ?', [$n, $prod[$c]]);
}
db_exec('DELETE FROM auditoria');
db_exec('DELETE FROM login_intentos');
$conexion->commit();

printf(
    "Datos de demostración listos: %d productos, %d ventas, %d cajas, %d pedidos en curso.\n",
    db_val('SELECT COUNT(*) FROM producto'),
    db_val('SELECT COUNT(*) FROM ventas'),
    db_val('SELECT COUNT(*) FROM cajas'),
    db_val("SELECT COUNT(*) FROM ventas WHERE estado NOT IN ('pagado', 'anulado')")
);
