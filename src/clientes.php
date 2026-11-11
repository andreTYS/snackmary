<?php
require_once "includes/app.php";
require_permiso('clientes');

$alert = '';
if (es_post()) {
    csrf_check();
    $nombre = texto($_POST['nombre'] ?? '', 100);
    $telefono = texto($_POST['telefono'] ?? '', 15);
    $direccion = texto($_POST['direccion'] ?? '', 200);
    if ($nombre === '' || $telefono === '' || $direccion === '') {
        $alert = alerta('danger', 'Todos los campos son obligatorios');
    } elseif (db_one('SELECT idcliente FROM cliente WHERE nombre = ?', [$nombre])) {
        $alert = alerta('danger', 'El cliente ya existe');
    } else {
        db_exec('INSERT INTO cliente (nombre, telefono, direccion, usuario_id) VALUES (?, ?, ?, ?)', [$nombre, $telefono, $direccion, usuario_id()]);
        flash('success', 'Cliente registrado');
        redirigir('clientes.php');
    }
}
$clientes = db_all('SELECT * FROM cliente ORDER BY estado DESC, nombre');
include_once "includes/header.php";
?>
<button class="btn btn-primary mb-2" type="button" data-toggle="modal" data-target="#nuevo_cliente"><i class="fas fa-plus"></i> Nuevo cliente</button>
<?php echo $alert; ?>
<div class="table-responsive">
    <table class="table table-striped table-bordered" id="tbl">
        <thead class="thead-dark">
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Dirección</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $data) { ?>
                <tr>
                    <td><?php echo e($data['nombre']); ?></td>
                    <td><?php echo e($data['telefono']); ?></td>
                    <td><?php echo e($data['direccion']); ?></td>
                    <td><?php echo $data['estado'] == 1 ? '<span class="badge badge-pill badge-success">Activo</span>' : '<span class="badge badge-pill badge-danger">Inactivo</span>'; ?></td>
                    <td>
                        <?php if ($data['estado'] == 1) { ?>
                            <a href="editar_cliente.php?id=<?php echo (int)$data['idcliente']; ?>" class="btn btn-success btn-sm"><i class='fas fa-edit'></i></a>
                            <?php if ((int)$data['idcliente'] !== 1) { ?>
                                <form action="eliminar_cliente.php" method="post" class="confirmar d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$data['idcliente']; ?>">
                                    <button class="btn btn-danger btn-sm" type="submit" title="Desactivar"><i class='fas fa-trash-alt'></i></button>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <form action="eliminar_cliente.php" method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$data['idcliente']; ?>">
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
<div id="nuevo_cliente" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="titulo-cliente" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="titulo-cliente">Nuevo Cliente</h5>
                <button class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form action="" method="post" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" placeholder="Ingrese nombre" name="nombre" id="nombre" class="form-control" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono / WhatsApp</label>
                        <input type="tel" placeholder="Ingrese teléfono" name="telefono" id="telefono" class="form-control" maxlength="15" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" placeholder="Ingrese dirección" name="direccion" id="direccion" class="form-control" maxlength="200" required>
                    </div>
                    <input type="submit" value="Guardar cliente" class="btn btn-primary">
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
