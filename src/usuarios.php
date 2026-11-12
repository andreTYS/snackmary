<?php
require_once "includes/app.php";
require_permiso('usuarios');

$alert = '';
$abrirModal = false;
if (es_post()) {
    csrf_check();
    $nombre = texto($_POST['nombre'] ?? '', 100);
    $email = texto($_POST['correo'] ?? '', 100);
    $user = texto($_POST['usuario'] ?? '', 20);
    $clave = (string)($_POST['clave'] ?? '');
    if ($nombre === '' || $email === '' || $user === '' || $clave === '') {
        $alert = alerta('danger', 'Todos los campos son obligatorios');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = alerta('danger', 'El correo no es válido');
    } elseif ($errorClave = validar_clave_nueva($clave, $user)) {
        $alert = alerta('danger', $errorClave);
    } elseif (db_one('SELECT idusuario FROM usuario WHERE correo = ? OR usuario = ?', [$email, $user])) {
        $alert = alerta('warning', 'El correo o el nombre de usuario ya existe');
    } else {
        db_exec(
            'INSERT INTO usuario (nombre, correo, usuario, clave) VALUES (?, ?, ?, ?)',
            [$nombre, $email, $user, password_hash($clave, PASSWORD_DEFAULT)]
        );
        auditar('usuario_creado', $user);
        flash('success', 'Usuario registrado. Ahora asígnale permisos con el botón de la llave.');
        redirigir('usuarios.php');
    }
    $abrirModal = true;
}
$usuarios = db_all('SELECT idusuario, nombre, correo, usuario, estado FROM usuario ORDER BY estado DESC, nombre');
include_once "includes/header.php";
?>
<button class="btn btn-primary" type="button" data-toggle="modal" data-target="#nuevo_usuario"><i class="fas fa-plus"></i> Nuevo usuario</button>
<?php echo $abrirModal ? '' : $alert; ?>
<div id="nuevo_usuario" class="modal fade<?php echo $abrirModal ? ' abrir-al-cargar' : ''; ?>" tabindex="-1" role="dialog" aria-labelledby="titulo-usuario" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="titulo-usuario">Nuevo Usuario</h5>
                <button class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form action="" method="post" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <?php echo $alert; ?>
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" placeholder="Ingrese nombre" name="nombre" id="nombre" maxlength="100" value="<?php echo e($_POST['nombre'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo</label>
                        <input type="email" class="form-control" placeholder="Ingrese correo electrónico" name="correo" id="correo" maxlength="100" value="<?php echo e($_POST['correo'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <input type="text" class="form-control" placeholder="Ingrese usuario" name="usuario" id="usuario" maxlength="20" value="<?php echo e($_POST['usuario'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="clave">Contraseña</label>
                        <input type="password" class="form-control" placeholder="Mínimo 8 caracteres" name="clave" id="clave" minlength="8" autocomplete="new-password" required>
                    </div>
                    <input type="submit" value="Registrar" class="btn btn-primary">
                </form>
            </div>
        </div>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mt-2" id="tbl">
        <thead class="thead-dark">
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Usuario</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $data) {
                $id = (int)$data['idusuario']; ?>
                <tr>
                    <td><?php echo e($data['nombre']); ?></td>
                    <td><?php echo e($data['correo']); ?></td>
                    <td><?php echo e($data['usuario']); ?></td>
                    <td><?php echo $data['estado'] == 1 ? '<span class="badge badge-pill badge-success">Activo</span>' : '<span class="badge badge-pill badge-danger">Inactivo</span>'; ?></td>
                    <td>
                        <?php if ($data['estado'] == 1) { ?>
                            <?php if ($id !== 1) { ?>
                                <a href="rol.php?id=<?php echo $id; ?>" class="btn btn-warning btn-sm" title="Permisos"><i class='fas fa-key'></i></a>
                            <?php } ?>
                            <a href="editar_usuario.php?id=<?php echo $id; ?>" class="btn btn-success btn-sm" title="Editar"><i class='fas fa-edit'></i></a>
                            <?php if ($id !== 1 && $id !== usuario_id()) { ?>
                                <form action="eliminar_usuario.php" method="post" class="confirmar d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button class="btn btn-danger btn-sm" type="submit" title="Desactivar"><i class='fas fa-trash-alt'></i></button>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <form action="eliminar_usuario.php" method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="hidden" name="accion" value="activar">
                                <button class="btn btn-outline-success btn-sm" type="submit">Reactivar</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php include_once "includes/footer.php"; ?>
