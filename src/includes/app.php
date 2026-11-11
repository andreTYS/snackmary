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
        http_response_code(419);
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
                m.nombre AS mesa, u.nombre AS vendedor
         FROM ventas v
         LEFT JOIN cliente c ON c.idcliente = v.id_cliente
         LEFT JOIN mesas m ON m.id = v.id_mesa
         LEFT JOIN usuario u ON u.idusuario = v.id_usuario
         WHERE v.id = ?',
        [$id]
    );
    if (!$pedido) {
        return null;
    }
    $pedido['items'] = db_all(
        'SELECT d.cantidad, d.precio, p.descripcion
         FROM detalle_venta d INNER JOIN producto p ON p.codproducto = d.id_producto
         WHERE d.id_venta = ? ORDER BY d.id',
        [$id]
    );
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

/**
 * Anula un pedido devolviendo el stock. Debe llamarse dentro de una transacción.
 */
function anular_pedido(int $id): bool
{
    $pedido = db_one('SELECT estado FROM ventas WHERE id = ? FOR UPDATE', [$id]);
    if (!$pedido || $pedido['estado'] === 'anulado') {
        return false;
    }
    foreach (db_all('SELECT id_producto, cantidad FROM detalle_venta WHERE id_venta = ?', [$id]) as $d) {
        db_exec('UPDATE producto SET existencia = existencia + ? WHERE codproducto = ?', [(int)$d['cantidad'], (int)$d['id_producto']]);
    }
    db_exec("UPDATE ventas SET estado = 'anulado' WHERE id = ?", [$id]);
    return true;
}
