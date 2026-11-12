<?php
/*
 * Seguridad común: sesión, IP real del cliente detrás de Traefik,
 * límite de intentos de inicio de sesión, auditoría y modo demo.
 * Lo usan el login (index.php), el panel (app.php) y la carta.
 */

const SESION_INACTIVA_MAX = 12 * 3600; // la sesión se cierra tras 12 h sin uso
const CLAVE_MINIMA = 8;
const LOGIN_MAX_USUARIO = 5;           // intentos fallidos por usuario ...
const LOGIN_MAX_IP = 20;               // ... o por IP ...
const LOGIN_VENTANA_MIN = 15;          // ... en estos minutos

/** ¿La petición viene de una red interna (Traefik/Docker)? */
function es_red_interna(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/** HTTPS real, también cuando Traefik termina el TLS y reenvía por http. */
function es_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return es_red_interna((string)($_SERVER['REMOTE_ADDR'] ?? ''))
        && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * IP del cliente. Solo se cree a X-Forwarded-For si quien conecta es el proxy
 * (red interna), y se toma la ÚLTIMA IP de la lista: la que añadió Traefik.
 * Las anteriores las puede inventar el propio cliente.
 */
function ip_cliente(): string
{
    $remota = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (es_red_interna($remota) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $lista = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        $ultima = end($lista);
        if (filter_var($ultima, FILTER_VALIDATE_IP)) {
            return substr($ultima, 0, 45);
        }
    }
    return substr($remota, 0, 45);
}

function modo_demo(): bool
{
    return getenv('APP_DEMO') === '1';
}

/** Inicia la sesión con cookies seguras y cierre por inactividad. */
function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SMSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => es_https(),
    ]);
    session_start();
    $ahora = time();
    if (!empty($_SESSION['ultimo_uso']) && $ahora - $_SESSION['ultimo_uso'] > SESION_INACTIVA_MAX) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultimo_uso'] = $ahora;
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

/** Registra una acción sensible. Nunca debe romper la operación principal. */
function auditar(string $accion, string $detalle = '', ?int $idUsuario = null): void
{
    global $conexion;
    try {
        $stmt = $conexion->prepare('INSERT INTO auditoria (id_usuario, accion, detalle, ip) VALUES (?, ?, ?, ?)');
        $uid = $idUsuario ?? (isset($_SESSION['idUser']) ? (int)$_SESSION['idUser'] : null);
        $acc = mb_substr($accion, 0, 50);
        $det = mb_substr($detalle, 0, 255);
        $ip = ip_cliente();
        $stmt->bind_param('isss', $uid, $acc, $det, $ip);
        $stmt->execute();
    } catch (Throwable $ex) {
        error_log('Auditoría: ' . $ex->getMessage());
    }
}

/** Minutos que faltan para poder volver a intentar (0 = puede intentar). */
function login_bloqueado(string $usuario): int
{
    global $conexion;
    $ip = ip_cliente();
    $stmt = $conexion->prepare(
        'SELECT
            (SELECT COUNT(*) FROM login_intentos WHERE usuario = ? AND exito = 0 AND fecha > NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE) AS por_usuario,
            (SELECT COUNT(*) FROM login_intentos WHERE ip = ? AND exito = 0 AND fecha > NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE) AS por_ip'
    );
    $stmt->bind_param('ss', $usuario, $ip);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return ((int)$r['por_usuario'] >= LOGIN_MAX_USUARIO || (int)$r['por_ip'] >= LOGIN_MAX_IP) ? LOGIN_VENTANA_MIN : 0;
}

function registrar_intento(string $usuario, bool $exito): void
{
    global $conexion;
    $ip = ip_cliente();
    $ok = $exito ? 1 : 0;
    $u = mb_substr($usuario, 0, 50);
    $stmt = $conexion->prepare('INSERT INTO login_intentos (usuario, ip, exito) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $u, $ip, $ok);
    $stmt->execute();
    if ($exito) {
        // Un ingreso correcto limpia los fallos de ese usuario
        $stmt = $conexion->prepare('DELETE FROM login_intentos WHERE usuario = ? AND exito = 0');
        $stmt->bind_param('s', $u);
        $stmt->execute();
    }
    // Limpieza de registros viejos (1 de cada 50 veces)
    if (random_int(1, 50) === 1) {
        $conexion->query('DELETE FROM login_intentos WHERE fecha < NOW() - INTERVAL 7 DAY');
    }
}

/** ¿Es una contraseña que hay que cambiar sí o sí? */
function clave_debil(string $clave, string $usuario): bool
{
    $comunes = ['admin', 'administrador', '123456', '12345678', '123456789', 'password', 'contraseña', 'snackmary', 'qwerty', 'vendedor', 'cajero'];
    return strlen($clave) < CLAVE_MINIMA || in_array(mb_strtolower($clave), $comunes, true) || mb_strtolower($clave) === mb_strtolower($usuario);
}

/** Mensaje de error si la nueva contraseña no sirve, o null si está bien. */
function validar_clave_nueva(string $clave, string $usuario): ?string
{
    if (strlen($clave) < CLAVE_MINIMA) {
        return 'La contraseña debe tener al menos ' . CLAVE_MINIMA . ' caracteres';
    }
    if (clave_debil($clave, $usuario)) {
        return 'Esa contraseña es muy fácil de adivinar. Usa otra';
    }
    return null;
}

/** Usuarios de la demostración (solo se muestran si APP_DEMO=1). */
const DEMO_USUARIOS = [
    ['admin', 'Demo2026', 'Administrador (todo)'],
    ['cajero', 'Cajero2026', 'Cajero / mozo'],
    ['cocina', 'Cocina2026', 'Cocina'],
    ['moto', 'Moto2026', 'Repartidor'],
];
