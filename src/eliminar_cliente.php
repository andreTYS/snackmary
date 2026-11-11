<?php
require_once "includes/app.php";
require_permiso('clientes');
if (!es_post()) {
    redirigir('clientes.php');
}
csrf_check();
$id = entero($_POST['id'] ?? 0);
$activar = ($_POST['accion'] ?? '') === 'activar';
if ($id === 1 && !$activar) {
    flash('warning', 'El cliente "Público en General" no se puede desactivar');
} elseif ($id > 0) {
    db_exec('UPDATE cliente SET estado = ? WHERE idcliente = ?', [$activar ? 1 : 0, $id]);
    flash('success', $activar ? 'Cliente reactivado' : 'Cliente desactivado');
}
redirigir('clientes.php');
