<?php
/*
 * Arranque común de todas las páginas del panel:
 * sesión segura, conexión, helpers de BD, permisos, CSRF y escape HTML.
 */
ob_start();

require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/../../conexion.php';
iniciar_sesion();

/* ---------- Constantes del negocio ---------- */
const TIPOS_PEDIDO = [
    'mostrador' => 'Mostrador',
    'mesa'      => 'Mesa',
    'delivery'  => 'Delivery',
];
const ESTADOS_PEDIDO = [
    'pendiente'  => ['Pendiente', 'secondary'],
    'preparando' => ['Preparando', 'warning'],
    'listo'      => ['Listo', 'info'],
    'en_camino'  => ['En camino', 'primary'],
    'entregado'  => ['Entregado', 'dark'],
    'pagado'     => ['Pagado', 'success'],
    'anulado'    => ['Anulado', 'danger'],
];
const ESTADOS_CERRADOS = ['pagado', 'anulado'];
const METODOS_PAGO = [
    'efectivo'      => 'Efectivo',
    'yape'          => 'Yape',
    'plin'          => 'Plin',
    'tarjeta'       => 'Tarjeta',
    'transferencia' => 'Transferencia',
];

/* ---------- Salida ---------- */
function e($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function dinero($valor): string
{
    return number_format((float)$valor, 2, '.', ',');
}

function alerta(string $tipo, string $mensaje): string
{
    return '<div class="alert alert-' . e($tipo) . '" role="alert">' . e($mensaje) . '</div>';
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $tipo = null, ?string $mensaje = null): string
{
    if ($tipo !== null) {
        $_SESSION['flash'] = [$tipo, $mensaje];
        return '';
    }
    if (empty($_SESSION['flash'])) {
        return '';
    }
    [$t, $m] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return alerta($t, $m);
}

function badge_estado(string $estado): string
{
    [$texto, $color] = ESTADOS_PEDIDO[$estado] ?? [$estado, 'secondary'];
    return '<span class="badge badge-' . $color . '">' . e($texto) . '</span>';
}

/* ---------- Base de datos (consultas preparadas) ---------- */
function db_query(string $sql, array $params = [])
{
    global $conexion;
    $stmt = $conexion->prepare($sql);
    if ($params) {
        $tipos = '';
        foreach ($params as $p) {
            $tipos .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($tipos, ...array_values($params));
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res === false) {
        $afectadas = $stmt->affected_rows;
        $stmt->close();
        return $afectadas;
    }
    return $res;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetch_all(MYSQLI_ASSOC);
}

function db_one(string $sql, array $params = []): ?array
{
    $fila = db_query($sql, $params)->fetch_assoc();
    return $fila ?: null;
}

function db_val(string $sql, array $params = [])
{
    $fila = db_query($sql, $params)->fetch_row();
    return $fila ? $fila[0] : null;
}

function db_exec(string $sql, array $params = []): int
{
    return (int)db_query($sql, $params);
}

function db_insert_id(): int
{
    global $conexion;
    return (int)$conexion->insert_id;
}

/* ---------- Entrada ---------- */
function entero($valor): int
{
    return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int)$valor : 0;
}

function decimal_positivo($valor): ?float
{
    $valor = str_replace(',', '.', trim((string)$valor));
    if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
        return null;
    }
    return round((float)$valor, 2);
}

function texto($valor, int $max = 200): string
{
    return mb_substr(trim((string)($valor ?? '')), 0, $max);
}

/* ---------- Sesión y permisos ---------- */
function usuario_id(): int
{
    return (int)($_SESSION['idUser'] ?? 0);
}

function es_admin(): bool
{
    return usuario_id() === 1;
}

/** Prefijo de ruta para páginas que están en subcarpetas de src/ (ej. pdf/). */
function base_src(): string
{
    return strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/src/pdf/') !== false ? '../' : '';
}

function require_login(): void
{
    if (empty($_SESSION['active']) || usuario_id() <= 0) {
        no_autenticado();
    }
    // El usuario debe seguir activo (si lo desactivan, su sesión abierta deja de servir)
    static $verificado = false;
    if (!$verificado) {
        $activo = db_val('SELECT estado FROM usuario WHERE idusuario = ?', [usuario_id()]);
        if ((int)$activo !== 1) {
            $_SESSION = [];
            session_regenerate_id(true);
            no_autenticado();
        }
        $verificado = true;
    }
    // Contraseña por defecto o débil: hay que cambiarla antes de seguir
    if (!empty($_SESSION['clave_insegura']) && !modo_demo()) {
        $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $permitida = in_array($pagina, ['perfil.php', 'salir.php'], true)
            || ($pagina === 'ajax.php' && ($_POST['action'] ?? '') === 'cambiar_clave');
        if (!$permitida) {
            if (es_ajax()) {
                http_response_code(403);
                exit(json_encode(['ok' => false, 'msg' => 'Primero cambia tu contraseña']));
            }
            redirigir(base_src() . 'perfil.php');
        }
    }
}

function no_autenticado(): void
{
    if (es_ajax()) {
        http_response_code(401);
        exit(json_encode(['ok' => false, 'msg' => 'Sesión expirada. Vuelve a ingresar.']));
    }
    redirigir(base_src() . '../');
}

function tiene_permiso(string $permiso): bool
{
    if (es_admin()) {
        return true;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = array_column(db_all(
            'SELECT p.nombre FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = ?',
            [usuario_id()]
        ), 'nombre');
    }
    return in_array($permiso, $cache, true);
}

function require_permiso(string ...$permisos): void
{
    require_login();
    foreach ($permisos as $p) {
        if (tiene_permiso($p)) {
            return;
        }
    }
    if (es_ajax()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'msg' => 'No tienes permiso']));
    }
    redirigir(base_src() . 'permisos.php');
}

function es_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        auditar('csrf_rechazado', basename($_SERVER['SCRIPT_NAME'] ?? ''));
        if (es_ajax()) {
            exit(json_encode(['ok' => false, 'msg' => 'Sesión inválida, recarga la página']));
        }
        exit('Solicitud inválida (token CSRF). Vuelve atrás y recarga la página.');
    }
}

function es_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ---------- Configuración y WhatsApp ---------- */
function config_negocio(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = db_one('SELECT * FROM configuracion ORDER BY id LIMIT 1') ?? [];
    }
    return $cfg;
}

/** Normaliza un teléfono para wa.me (solo dígitos, con código de país). */
function telefono_whatsapp(?string $telefono): string
{
    $digitos = preg_replace('/\D+/', '', (string)$telefono);
    if ($digitos === '') {
        return '';
    }
    $pais = preg_replace('/\D+/', '', config_negocio()['codigo_pais'] ?? '51') ?: '51';
    if (strlen($digitos) <= 9) {
        $digitos = $pais . ltrim($digitos, '0');
    }
    return $digitos;
}

function enlace_whatsapp(?string $telefono, string $mensaje): string
{
    $numero = telefono_whatsapp($telefono);
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
}

/** Datos completos de un pedido (cabecera + items). */
function obtener_pedido(int $id): ?array
{
    $pedido = db_one(
        'SELECT v.*, c.nombre AS cliente, c.telefono AS cliente_telefono, c.direccion AS cliente_direccion,
                m.nombre AS mesa, u.nombre AS vendedor, r.nombre AS repartidor
         FROM ventas v
         LEFT JOIN cliente c ON c.idcliente = v.id_cliente
         LEFT JOIN mesas m ON m.id = v.id_mesa
         LEFT JOIN usuario u ON u.idusuario = v.id_usuario
         LEFT JOIN usuario r ON r.idusuario = v.id_repartidor
         WHERE v.id = ?',
        [$id]
    );
    if (!$pedido) {
        return null;
    }
    $pedido['items'] = db_all(
        "SELECT d.cantidad, d.precio, d.ronda, d.opciones_txt,
                CONCAT(p.descripcion, IF(d.opciones_txt <> '', CONCAT(' (', d.opciones_txt, ')'), '')) AS descripcion
         FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto
         WHERE d.id_venta = ? ORDER BY d.ronda, d.id",
        [$id]
    );
    $pedido['ultima_ronda'] = $pedido['items'] ? max(array_map('intval', array_column($pedido['items'], 'ronda'))) : 1;
    return $pedido;
}

/** Texto del pedido para enviar por WhatsApp al cliente. */
function mensaje_pedido(array $p): string
{
    $negocio = config_negocio()['nombre'] ?? 'Snack Mary';
    $lineas = ["*{$negocio}*", "Pedido #{$p['id']} - " . (TIPOS_PEDIDO[$p['tipo']] ?? $p['tipo'])];
    if ($p['tipo'] === 'mesa' && $p['mesa']) {
        $lineas[] = 'Mesa: ' . $p['mesa'];
    }
    $lineas[] = 'Estado: ' . (ESTADOS_PEDIDO[$p['estado']][0] ?? $p['estado']);
    $lineas[] = '';
    foreach ($p['items'] as $it) {
        $lineas[] = "{$it['cantidad']} x {$it['descripcion']} = S/ " . dinero($it['cantidad'] * $it['precio']);
    }
    if ((float)$p['costo_envio'] > 0) {
        $lineas[] = 'Envío: S/ ' . dinero($p['costo_envio']);
    }
    $lineas[] = '*Total: S/ ' . dinero($p['total']) . '*';
    if ($p['tipo'] === 'delivery' && $p['direccion_entrega']) {
        $lineas[] = '';
        $lineas[] = 'Dirección: ' . $p['direccion_entrega'];
    }
    $lineas[] = 'Pago: ' . (METODOS_PAGO[$p['metodo_pago']] ?? $p['metodo_pago']);
    $lineas[] = '';
    $lineas[] = '¡Gracias por tu preferencia!';
    return implode("\n", $lineas);
}

function telefono_pedido(array $p): string
{
    if (!empty($p['telefono_entrega'])) {
        return $p['telefono_entrega'];
    }
    // "Público en General" (id 1) no tiene un teléfono real
    return (int)($p['id_cliente'] ?? 0) === 1 ? '' : (string)($p['cliente_telefono'] ?? '');
}

/** Enlace de Google Maps: usa las coordenadas si el cliente compartió su ubicación. */
function enlace_mapa(?string $direccion, ?string $ubicacion = null): string
{
    $consulta = ($ubicacion && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $ubicacion)) ? $ubicacion : (string)$direccion;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($consulta);
}

/** Ruta de la foto de un producto, relativa a la raíz del sitio (o null). */
function imagen_producto(?string $archivo): ?string
{
    return ($archivo && preg_match('/^[a-z0-9_]+\.(jpg|webp)$/', $archivo)) ? 'uploads/productos/' . $archivo : null;
}

/* ---------- Caja ---------- */
function caja_abierta(): ?array
{
    return db_one("SELECT * FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
}

/** Marca un pedido como cobrado y lo asocia a la caja abierta. */
function cobrar_pedido(int $id, ?string $metodo = null): void
{
    $caja = caja_abierta();
    $metodo = ($metodo && array_key_exists($metodo, METODOS_PAGO)) ? $metodo : null;
    db_exec(
        "UPDATE ventas SET estado = 'pagado', fecha_pago = NOW(), id_caja = ?, metodo_pago = COALESCE(?, metodo_pago) WHERE id = ?",
        [$caja ? (int)$caja['id'] : null, $metodo, $id]
    );
}

/**
 * Resumen de una caja: ventas cobradas por método, ingresos, egresos y efectivo esperado.
 */
function resumen_caja(array $caja): array
{
    $id = (int)$caja['id'];
    $porMetodo = [];
    foreach (METODOS_PAGO as $clave => $nombre) {
        $porMetodo[$clave] = ['nombre' => $nombre, 'ventas' => 0.0, 'cantidad' => 0, 'ingresos' => 0.0, 'egresos' => 0.0];
    }
    foreach (db_all("SELECT metodo_pago, COUNT(*) AS n, SUM(total) AS monto FROM ventas WHERE id_caja = ? AND estado = 'pagado' GROUP BY metodo_pago", [$id]) as $r) {
        if (isset($porMetodo[$r['metodo_pago']])) {
            $porMetodo[$r['metodo_pago']]['ventas'] = (float)$r['monto'];
            $porMetodo[$r['metodo_pago']]['cantidad'] = (int)$r['n'];
        }
    }
    foreach (db_all('SELECT tipo, metodo_pago, SUM(monto) AS monto FROM caja_movimientos WHERE id_caja = ? GROUP BY tipo, metodo_pago', [$id]) as $r) {
        if (isset($porMetodo[$r['metodo_pago']])) {
            $porMetodo[$r['metodo_pago']][$r['tipo'] === 'ingreso' ? 'ingresos' : 'egresos'] += (float)$r['monto'];
        }
    }
    $ef = $porMetodo['efectivo'];
    return [
        'metodos' => $porMetodo,
        'total_ventas' => array_sum(array_column($porMetodo, 'ventas')),
        'num_ventas' => array_sum(array_column($porMetodo, 'cantidad')),
        'efectivo_esperado' => round((float)$caja['monto_inicial'] + $ef['ventas'] + $ef['ingresos'] - $ef['egresos'], 2),
    ];
}

/* ---------- Opciones por plato ---------- */

/** Grupos de opciones activos de un producto, con sus opciones. */
function grupos_opciones(int $idProducto): array
{
    $grupos = db_all('SELECT id, nombre, minimo, maximo FROM opcion_grupos WHERE id_producto = ? AND estado = 1 ORDER BY orden, id', [$idProducto]);
    if (!$grupos) {
        return [];
    }
    $porGrupo = [];
    $marcas = implode(',', array_fill(0, count($grupos), '?'));
    foreach (db_all(
        "SELECT o.id, o.id_grupo, o.nombre, o.precio, o.id_producto_stock,
                ps.existencia AS stock, ps.controla_stock, ps.estado AS estado_stock
         FROM opciones o LEFT JOIN producto ps ON ps.codproducto = o.id_producto_stock
         WHERE o.estado = 1 AND o.id_grupo IN ($marcas) ORDER BY o.orden, o.id",
        array_map(fn($g) => (int)$g['id'], $grupos)
    ) as $o) {
        $agotado = $o['id_producto_stock'] !== null
            && ((int)$o['estado_stock'] !== 1 || ((int)$o['controla_stock'] === 1 && (int)$o['stock'] <= 0));
        $porGrupo[(int)$o['id_grupo']][] = [
            'id' => (int)$o['id'],
            'nombre' => $o['nombre'],
            'precio' => (float)$o['precio'],
            'agotado' => $agotado,
        ];
    }
    $lista = [];
    foreach ($grupos as $g) {
        if (empty($porGrupo[(int)$g['id']])) {
            continue;
        }
        $lista[] = [
            'id' => (int)$g['id'],
            'nombre' => $g['nombre'],
            'minimo' => (int)$g['minimo'],
            'maximo' => max(1, (int)$g['maximo']),
            'opciones' => $porGrupo[(int)$g['id']],
        ];
    }
    return $lista;
}

/** IDs de productos que tienen opciones activas. */
function productos_con_opciones(): array
{
    return array_flip(array_map('intval', array_column(db_all(
        'SELECT DISTINCT g.id_producto FROM opcion_grupos g INNER JOIN opciones o ON o.id_grupo = g.id AND o.estado = 1 WHERE g.estado = 1'
    ), 'id_producto')));
}

/**
 * Valida lo elegido para un producto y calcula precio extra, texto y consumo de stock.
 * Lanza RuntimeException con un mensaje para el usuario.
 * Devuelve ['clave' => '3,7', 'txt' => 'Pura papa, Inca Kola', 'extra' => 3.0, 'consumo' => [id_producto => unidades]].
 */
function resolver_opciones(int $idProducto, array $elegidas, string $nombreProducto = 'el producto'): array
{
    $elegidas = array_values(array_unique(array_filter(array_map('intval', $elegidas))));
    $grupos = db_all('SELECT id, nombre, minimo, maximo FROM opcion_grupos WHERE id_producto = ? AND estado = 1 ORDER BY orden, id', [$idProducto]);
    $porGrupo = [];
    $opciones = [];
    if ($elegidas) {
        $marcas = implode(',', array_fill(0, count($elegidas), '?'));
        foreach (db_all(
            "SELECT o.id, o.id_grupo, o.nombre, o.precio, o.id_producto_stock, g.orden AS orden_grupo, o.orden
             FROM opciones o INNER JOIN opcion_grupos g ON g.id = o.id_grupo
             WHERE o.id IN ($marcas) AND o.estado = 1 AND g.estado = 1 AND g.id_producto = ?
             ORDER BY g.orden, g.id, o.orden, o.id",
            array_merge($elegidas, [$idProducto])
        ) as $o) {
            $opciones[] = $o;
            $porGrupo[(int)$o['id_grupo']] = ($porGrupo[(int)$o['id_grupo']] ?? 0) + 1;
        }
        if (count($opciones) !== count($elegidas)) {
            throw new RuntimeException('Una opción elegida ya no está disponible. Vuelve a elegir.');
        }
    }
    foreach ($grupos as $g) {
        // Un grupo sin opciones activas no se exige
        if (!db_val('SELECT COUNT(*) FROM opciones WHERE id_grupo = ? AND estado = 1', [(int)$g['id']])) {
            continue;
        }
        $n = $porGrupo[(int)$g['id']] ?? 0;
        if ($n < (int)$g['minimo']) {
            throw new RuntimeException('Elige ' . $g['nombre'] . ' para ' . $nombreProducto);
        }
        if ($n > max(1, (int)$g['maximo'])) {
            throw new RuntimeException('En ' . $g['nombre'] . ' puedes elegir hasta ' . max(1, (int)$g['maximo']));
        }
    }
    $ids = array_map(fn($o) => (int)$o['id'], $opciones);
    sort($ids);
    $consumo = [];
    foreach ($opciones as $o) {
        if ($o['id_producto_stock'] !== null) {
            $consumo[(int)$o['id_producto_stock']] = ($consumo[(int)$o['id_producto_stock']] ?? 0) + 1;
        }
    }
    return [
        'clave' => implode(',', $ids),
        'txt' => mb_substr(implode(', ', array_map(fn($o) => $o['nombre'], $opciones)), 0, 255),
        'extra' => round(array_sum(array_map(fn($o) => (float)$o['precio'], $opciones)), 2),
        'consumo' => $consumo,
    ];
}

/**
 * Revisa que alcance el stock. $planas: [['id_producto' => X, 'cantidad' => unidades], ...]
 * (un producto puede repetirse: se suman). $bloquear = true dentro de una transacción.
 */
function verificar_stock(array $planas, bool $bloquear = false): void
{
    $necesario = [];
    foreach ($planas as $l) {
        $necesario[(int)$l['id_producto']] = ($necesario[(int)$l['id_producto']] ?? 0) + (int)$l['cantidad'];
    }
    if (!$necesario) {
        return;
    }
    $marcas = implode(',', array_fill(0, count($necesario), '?'));
    $prods = [];
    foreach (db_all("SELECT codproducto, descripcion, existencia, controla_stock, estado FROM producto WHERE codproducto IN ($marcas)" . ($bloquear ? ' FOR UPDATE' : ''), array_keys($necesario)) as $p) {
        $prods[(int)$p['codproducto']] = $p;
    }
    foreach ($necesario as $idp => $total) {
        $p = $prods[$idp] ?? null;
        if (!$p || (int)$p['estado'] !== 1) {
            throw new RuntimeException(($p['descripcion'] ?? 'Un producto') . ' ya no está disponible');
        }
        if ((int)$p['controla_stock'] === 1 && $total > (int)$p['existencia']) {
            throw new RuntimeException('Stock insuficiente de ' . $p['descripcion'] . ' (quedan ' . (int)$p['existencia'] . ')');
        }
    }
}

/* ---------- Pedidos ---------- */

/**
 * Crea un pedido, o agrega una ronda a la cuenta abierta de la mesa.
 * $items: lista de ['id_producto' => int, 'cantidad' => int, 'opciones' => [ids de opción]].
 * El precio y las opciones se validan y calculan SIEMPRE desde la BD.
 * Debe llamarse dentro de una transacción. Lanza RuntimeException con un mensaje para el usuario.
 * Devuelve ['id' => int, 'agregado' => bool].
 */
function registrar_pedido(array $items, array $d): array
{
    // Agrupar líneas iguales (mismo producto y mismas opciones)
    $lineas = [];
    foreach ($items as $it) {
        $idp = (int)($it['id_producto'] ?? 0);
        $cant = (int)($it['cantidad'] ?? 0);
        if ($idp <= 0 || $cant <= 0) {
            continue;
        }
        $ops = is_array($it['opciones'] ?? null) ? $it['opciones'] : array_filter(explode(',', (string)($it['opciones'] ?? '')));
        $ops = array_values(array_unique(array_map('intval', $ops)));
        sort($ops);
        $clave = $idp . '|' . implode(',', $ops);
        if (isset($lineas[$clave])) {
            $lineas[$clave]['cantidad'] += $cant;
        } else {
            $lineas[$clave] = ['id_producto' => $idp, 'cantidad' => $cant, 'opciones' => $ops];
        }
    }
    if (!$lineas) {
        throw new RuntimeException('No hay productos en el pedido');
    }
    $ids = array_values(array_unique(array_column($lineas, 'id_producto')));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $productos = [];
    foreach (db_all("SELECT codproducto, descripcion, precio, existencia, controla_stock, estado FROM producto WHERE codproducto IN ($marcadores) FOR UPDATE", $ids) as $p) {
        $productos[(int)$p['codproducto']] = $p;
    }
    foreach ($lineas as &$l) {
        $p = $productos[$l['id_producto']] ?? null;
        if (!$p || (int)$p['estado'] !== 1) {
            throw new RuntimeException('Un producto del pedido ya no está disponible');
        }
        $op = resolver_opciones($l['id_producto'], $l['opciones'], $p['descripcion']);
        $l['precio'] = round((float)$p['precio'] + $op['extra'], 2);
        $l['clave_op'] = $op['clave'];
        $l['txt'] = $op['txt'];
        $l['consumo'] = $op['consumo'];
        $l['controla'] = (int)$p['controla_stock'] === 1;
    }
    unset($l);
    // Stock: producto base (si lo controla) + bebidas vinculadas a las opciones
    $planas = [];
    foreach ($lineas as $l) {
        // Siempre se incluye el producto (para revisar que siga activo); sus unidades solo cuentan si controla stock
        $planas[] = ['id_producto' => $l['id_producto'], 'cantidad' => $l['controla'] ? $l['cantidad'] : 0];
        foreach ($l['consumo'] as $idp => $u) {
            $planas[] = ['id_producto' => $idp, 'cantidad' => $u * $l['cantidad']];
        }
    }
    verificar_stock($planas, true);

    $tipo = $d['tipo'];
    $idVenta = 0;
    $agregado = false;
    if ($tipo === 'mesa') {
        $idVenta = (int)db_val(
            "SELECT id FROM ventas WHERE tipo = 'mesa' AND id_mesa = ? AND estado NOT IN ('pagado', 'anulado') ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [(int)$d['id_mesa']]
        );
    }
    if ($idVenta) {
        $agregado = true;
        db_exec(
            "UPDATE ventas SET estado = 'pendiente', nota = LEFT(CONCAT_WS(' | ', NULLIF(nota, ''), NULLIF(?, '')), 255) WHERE id = ?",
            [(string)($d['nota'] ?? ''), $idVenta]
        );
        $ronda = (int)db_val('SELECT COALESCE(MAX(ronda), 0) + 1 FROM detalle_venta WHERE id_venta = ?', [$idVenta]);
    } else {
        db_exec(
            'INSERT INTO ventas (id_cliente, total, id_usuario, tipo, id_mesa, estado, metodo_pago, costo_envio,
                                 direccion_entrega, telefono_entrega, nota, origen, ubicacion, ip)
             VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int)$d['id_cliente'], (int)$d['id_usuario'], $tipo, $d['id_mesa'] ?? null, $d['estado'] ?? 'pendiente',
                $d['metodo_pago'] ?? 'efectivo', (float)($d['costo_envio'] ?? 0), $d['direccion_entrega'] ?? null,
                $d['telefono_entrega'] ?? null, $d['nota'] ?? null, $d['origen'] ?? 'local', $d['ubicacion'] ?? null, $d['ip'] ?? null,
            ]
        );
        $idVenta = db_insert_id();
        $ronda = 1;
    }

    foreach ($lineas as $l) {
        db_exec(
            'INSERT INTO detalle_venta (id_producto, id_venta, cantidad, precio, ronda, opciones, opciones_txt, consumo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$l['id_producto'], $idVenta, $l['cantidad'], $l['precio'], $ronda, $l['clave_op'], $l['txt'], $l['consumo'] ? json_encode($l['consumo']) : '']
        );
        if ($l['controla']) {
            db_exec('UPDATE producto SET existencia = existencia - ? WHERE codproducto = ?', [$l['cantidad'], $l['id_producto']]);
        }
        foreach ($l['consumo'] as $idp => $u) {
            db_exec('UPDATE producto SET existencia = existencia - ? WHERE codproducto = ? AND controla_stock = 1', [$l['cantidad'] * $u, $idp]);
        }
    }
    db_exec(
        'UPDATE ventas SET total = (SELECT COALESCE(SUM(cantidad * precio), 0) FROM detalle_venta WHERE id_venta = ?) + costo_envio WHERE id = ?',
        [$idVenta, $idVenta]
    );
    // ¿Algo de esta ronda se prepara en cocina? (las categorías marcadas "no va a cocina", como bebidas, no)
    $paraCocina = (int)db_val(
        'SELECT COUNT(*) FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto
         LEFT JOIN categorias c ON c.id = p.id_categoria
         WHERE d.id_venta = ? AND d.ronda = ? AND COALESCE(c.cocina, 1) = 1',
        [$idVenta, $ronda]
    );
    if ($paraCocina > 0) {
        db_exec("UPDATE ventas SET estado_cocina = 'pendiente' WHERE id = ?", [$idVenta]);
    }
    if (($d['estado'] ?? '') === 'pagado' && !$agregado) {
        cobrar_pedido($idVenta, $d['metodo_pago'] ?? null);
    }
    return ['id' => $idVenta, 'agregado' => $agregado];
}

/**
 * Anula un pedido devolviendo el stock (producto y bebidas de las opciones).
 * Debe llamarse dentro de una transacción.
 */
function anular_pedido(int $id): bool
{
    $pedido = db_one('SELECT estado FROM ventas WHERE id = ? FOR UPDATE', [$id]);
    if (!$pedido || $pedido['estado'] === 'anulado') {
        return false;
    }
    foreach (db_all('SELECT d.id_producto, d.cantidad, d.consumo, p.controla_stock FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto WHERE d.id_venta = ?', [$id]) as $d) {
        if ((int)$d['controla_stock'] === 1) {
            db_exec('UPDATE producto SET existencia = existencia + ? WHERE codproducto = ?', [(int)$d['cantidad'], (int)$d['id_producto']]);
        }
        foreach ((array)json_decode((string)$d['consumo'], true) as $idp => $u) {
            db_exec('UPDATE producto SET existencia = existencia + ? WHERE codproducto = ? AND controla_stock = 1', [(int)$d['cantidad'] * (int)$u, (int)$idp]);
        }
    }
    db_exec("UPDATE ventas SET estado = 'anulado' WHERE id = ?", [$id]);
    return true;
}

/** Usuarios activos con permiso de repartidor. */
function lista_repartidores(): array
{
    return db_all(
        "SELECT u.idusuario, u.nombre FROM usuario u
         WHERE u.estado = 1 AND EXISTS (
            SELECT 1 FROM detalle_permisos d INNER JOIN permisos p ON p.id = d.id_permiso
            WHERE d.id_usuario = u.idusuario AND p.nombre = 'repartidor')
         ORDER BY u.nombre"
    );
}

/**
 * Guarda la foto de un producto: valida que sea imagen, la reduce a 600 px y la
 * vuelve a codificar (así nunca se guarda el archivo original subido).
 * Devuelve el nombre del archivo o lanza RuntimeException.
 */
function guardar_imagen_producto(array $archivo): string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen');
    }
    if ($archivo['size'] > 6 * 1024 * 1024) {
        throw new RuntimeException('La imagen pesa más de 6 MB');
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('El servidor no tiene la extensión GD para procesar imágenes');
    }
    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('La imagen debe ser JPG, PNG o WEBP');
    }
    $origen = @imagecreatefromstring((string)file_get_contents($archivo['tmp_name']));
    if (!$origen) {
        throw new RuntimeException('La imagen está dañada');
    }
    [$w, $h] = [imagesx($origen), imagesy($origen)];
    $escala = min(1, 600 / max($w, $h));
    $nw = max(1, (int)round($w * $escala));
    $nh = max(1, (int)round($h * $escala));
    $destino = imagecreatetruecolor($nw, $nh);
    imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($origen);

    $dir = __DIR__ . '/../../uploads/productos';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes');
    }
    $usarWebp = function_exists('imagewebp');
    $nombre = bin2hex(random_bytes(8)) . ($usarWebp ? '.webp' : '.jpg');
    $ok = $usarWebp ? imagewebp($destino, "$dir/$nombre", 82) : imagejpeg($destino, "$dir/$nombre", 85);
    imagedestroy($destino);
    if (!$ok) {
        throw new RuntimeException('No se pudo guardar la imagen');
    }
    return $nombre;
}

function borrar_imagen_producto(?string $nombre): void
{
    if ($nombre && preg_match('/^[a-z0-9_]+\.(jpg|webp)$/', $nombre)) {
        @unlink(__DIR__ . '/../../uploads/productos/' . $nombre);
    }
}
