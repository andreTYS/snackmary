<?php
require_once "includes/app.php";
require_permiso('usuarios');

$id = entero($_REQUEST['id'] ?? 0);
$usuario = $id ? db_one('SELECT idusuario, nombre, correo, usuario FROM usuario WHERE idusuario = ?', [$id]) : null;
if (!$usuario || ($id === 1 && !es_admin())) {
    redirigir('usuarios.php');
}
$alert = '';
if (es_post()) {
    csrf_check();
    $nombre = texto($_POST['nombre'] ?? '', 100);
    $correo = texto($_POST['correo'] ?? '', 100);
    $user = texto($_POST['usuario'] ?? '', 20);
    $clave = (string)($_POST['clave'] ?? '');
    if ($nombre === '' || $correo === '' || $user === '') {
        $alert = alerta('danger', 'Todos los campos son requeridos');
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $alert = alerta('danger', 'El correo no es válido');
    } elseif ($clave !== '' && strlen($clave) < 6) {
        $alert = alerta('danger', 'La nueva contraseña debe tener al menos 6 caracteres');
    } elseif (db_one('SELECT idusuario FROM usuario WHERE (correo = ? OR usuario = ?) AND idusuario <> ?', [$correo, $user, $id])) {
        $alert = alerta('warning', 'El correo o el usuario ya lo usa otra persona');
    } else {
        db_exec('UPDATE usuario SET nombre = ?, correo = ?, usuario = ? WHERE idusuario = ?', [$nombre, $correo, $user, $id]);
        if ($clave !== '') {
            db_exec('UPDATE usuario SET clave = ? WHERE idusuario = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
        }
        flash('success', 'Usuario actualizado');
        redirigir('usuarios.php');
    }
    $usuario = array_merge($usuario, ['nombre' => $nombre, 'correo' => $correo, 'usuario' => $user]);
}
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Modificar Usuario</div>
            <div class="card-body">
                <form action="" method="post" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <?php echo $alert; ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" maxlength="100" value="<?php echo e($usuario['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo</label>
                        <input type="email" class="form-control" name="correo" id="correo" maxlength="100" value="<?php echo e($usuario['correo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <input type="text" class="form-control" name="usuario" id="usuario" maxlength="20" value="<?php echo e($usuario['usuario']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="clave">Nueva contraseña <small class="text-muted">(dejar vacío para no cambiarla)</small></label>
                        <input type="password" class="form-control" name="clave" id="clave" minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-edit"></i> Guardar</button>
                    <a href="usuarios.php" class="btn btn-danger">Atrás</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
