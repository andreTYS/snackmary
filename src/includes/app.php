<?php
/*
 * Arranque común de todas las páginas del panel:
 * sesión segura, conexión, helpers de BD, permisos, CSRF y escape HTML.
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/../../conexion.php';

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
        if (es_ajax()) {
            http_response_code(401);
            exit(json_encode(['ok' => false, 'msg' => 'Sesión expirada']));
        }
        redirigir(base_src() . '../');
    }
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
        'SELECT d.cantidad, d.precio, d.ronda, p.descripcion
         FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto
         WHERE d.id_venta = ? ORDER BY d.ronda, d.id',
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

/* ---------- Pedidos ---------- */

/**
 * Crea un pedido, o agrega una ronda a la cuenta abierta de la mesa.
 * $items: lista de ['id_producto' => int, 'cantidad' => int]. El precio siempre sale de la BD.
 * Debe llamarse dentro de una transacción. Lanza RuntimeException con un mensaje para el usuario.
 * Devuelve ['id' => int, 'agregado' => bool].
 */
function registrar_pedido(array $items, array $d): array
{
    $cantidades = [];
    foreach ($items as $it) {
        $idp = (int)($it['id_producto'] ?? 0);
        $cant = (int)($it['cantidad'] ?? 0);
        if ($idp > 0 && $cant > 0) {
            $cantidades[$idp] = ($cantidades[$idp] ?? 0) + $cant;
        }
    }
    if (!$cantidades) {
        throw new RuntimeException('No hay productos en el pedido');
    }
    $marcadores = implode(',', array_fill(0, count($cantidades), '?'));
    $productos = [];
    foreach (db_all("SELECT codproducto, descripcion, precio, existencia, controla_stock, estado FROM producto WHERE codproducto IN ($marcadores) FOR UPDATE", array_keys($cantidades)) as $p) {
        $productos[(int)$p['codproducto']] = $p;
    }
    foreach ($cantidades as $idp => $cant) {
        $p = $productos[$idp] ?? null;
        if (!$p || (int)$p['estado'] !== 1) {
            throw new RuntimeException('Un producto del pedido ya no está disponible');
        }
        if ((int)$p['controla_stock'] === 1 && $cant > (int)$p['existencia']) {
            throw new RuntimeException('Stock insuficiente de ' . $p['descripcion'] . ' (quedan ' . (int)$p['existencia'] . ')');
        }
    }

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

    foreach ($cantidades as $idp => $cant) {
        $p = $productos[$idp];
        db_exec(
            'INSERT INTO detalle_venta (id_producto, id_venta, cantidad, precio, ronda) VALUES (?, ?, ?, ?, ?)',
            [$idp, $idVenta, $cant, (float)$p['precio'], $ronda]
        );
        if ((int)$p['controla_stock'] === 1) {
            db_exec('UPDATE producto SET existencia = existencia - ? WHERE codproducto = ?', [$cant, $idp]);
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
 * Anula un pedido devolviendo el stock. Debe llamarse dentro de una transacción.
 */
function anular_pedido(int $id): bool
{
    $pedido = db_one('SELECT estado FROM ventas WHERE id = ? FOR UPDATE', [$id]);
    if (!$pedido || $pedido['estado'] === 'anulado') {
        return false;
    }
    foreach (db_all('SELECT d.id_producto, d.cantidad FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto WHERE d.id_venta = ? AND p.controla_stock = 1', [$id]) as $d) {
        db_exec('UPDATE producto SET existencia = existencia + ? WHERE codproducto = ?', [(int)$d['cantidad'], (int)$d['id_producto']]);
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
