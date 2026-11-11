<?php
require_once "includes/app.php";
require_permiso('clientes');

$idcliente = entero($_REQUEST['id'] ?? 0);
$cliente = $idcliente ? db_one('SELECT * FROM cliente WHERE idcliente = ?', [$idcliente]) : null;
if (!$cliente) {
    redirigir('clientes.php');
}
$alert = '';
if (es_post()) {
    csrf_check();
    $nombre = texto($_POST['nombre'] ?? '', 100);
    $telefono = texto($_POST['telefono'] ?? '', 15);
    $direccion = texto($_POST['direccion'] ?? '', 200);
    if ($nombre === '' || $telefono === '' || $direccion === '') {
        $alert = alerta('danger', 'Todos los campos son requeridos');
    } elseif (db_one('SELECT idcliente FROM cliente WHERE nombre = ? AND idcliente <> ?', [$nombre, $idcliente])) {
        $alert = alerta('danger', 'Ya existe otro cliente con ese nombre');
    } else {
        db_exec('UPDATE cliente SET nombre = ?, telefono = ?, direccion = ? WHERE idcliente = ?', [$nombre, $telefono, $direccion, $idcliente]);
        flash('success', 'Cliente actualizado correctamente');
        redirigir('clientes.php');
    }
    $cliente = array_merge($cliente, ['nombre' => $nombre, 'telefono' => $telefono, 'direccion' => $direccion]);
}
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-6 m-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Modificar Cliente</div>
            <div class="card-body">
                <form action="" method="post">
                    <?php echo csrf_field(); ?>
                    <?php echo $alert; ?>
                    <input type="hidden" name="id" value="<?php echo $idcliente; ?>">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" name="nombre" class="form-control" id="nombre" maxlength="100" value="<?php echo e($cliente['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono / WhatsApp</label>
                        <input type="tel" name="telefono" class="form-control" id="telefono" maxlength="15" value="<?php echo e($cliente['telefono']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" name="direccion" class="form-control" id="direccion" maxlength="200" value="<?php echo e($cliente['direccion']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-edit"></i> Guardar</button>
                    <a href="clientes.php" class="btn btn-danger">Atrás</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
