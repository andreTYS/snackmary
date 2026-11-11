<?php
require_once "includes/app.php";
require_permiso('productos');
if (!es_post()) {
    redirigir('productos.php');
}
csrf_check();
$id = entero($_POST['id'] ?? 0);
$activar = ($_POST['accion'] ?? '') === 'activar';
if ($id > 0) {
    db_exec('UPDATE producto SET estado = ? WHERE codproducto = ?', [$activar ? 1 : 0, $id]);
    flash('success', $activar ? 'Producto reactivado' : 'Producto desactivado');
}
redirigir('productos.php');
