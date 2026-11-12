<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/../conexion.php';
iniciar_sesion();
// Cerrar sesión exige el token de la sesión (un enlace de otra página no puede hacerlo)
if (!empty($_SESSION['active']) && hash_equals($_SESSION['csrf'] ?? '', (string)($_GET['t'] ?? ''))) {
    auditar('logout');
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
}
header('Location: ../');
exit;
