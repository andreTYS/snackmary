<?php
require_once "includes/app.php";
require_permiso('usuarios');
if (!es_post()) {
    redirigir('usuarios.php');
}
csrf_check();
$id = entero($_POST['id'] ?? 0);
$activar = ($_POST['accion'] ?? '') === 'activar';
if (modo_demo() && $id <= 4) {
    flash('warning', 'En el modo demo no se pueden desactivar los usuarios de prueba');
} elseif (!$activar && ($id === 1 || $id === usuario_id())) {
    flash('warning', 'No puedes desactivar al administrador principal ni a tu propio usuario');
} elseif ($id > 0) {
    db_exec('UPDATE usuario SET estado = ? WHERE idusuario = ?', [$activar ? 1 : 0, $id]);
    auditar($activar ? 'usuario_reactivado' : 'usuario_desactivado', (string)db_val('SELECT usuario FROM usuario WHERE idusuario = ?', [$id]));
    flash('success', $activar ? 'Usuario reactivado' : 'Usuario desactivado');
}
redirigir('usuarios.php');
