<?php
require_once "includes/app.php";
require_permiso('productos');

$id = entero($_REQUEST['id'] ?? 0);
$producto = $id ? db_one('SELECT * FROM producto WHERE codproducto = ?', [$id]) : null;
if (!$producto) {
    redirigir('productos.php');
}
$alert = '';
if (es_post()) {
    csrf_check();
    $codigo = texto($_POST['codigo'] ?? '', 20);
    $descripcion = texto($_POST['producto'] ?? '', 200);
    $precio = decimal_positivo($_POST['precio'] ?? '');
    if ($codigo === '' || $descripcion === '' || $precio === null) {
        $alert = alerta('danger', 'Todos los campos son requeridos');
    } elseif (db_one('SELECT codproducto FROM producto WHERE codigo = ? AND codproducto <> ?', [$codigo, $id])) {
        $alert = alerta('warning', 'Ese código ya lo usa otro producto');
    } else {
        db_exec('UPDATE producto SET codigo = ?, descripcion = ?, precio = ? WHERE codproducto = ?', [$codigo, $descripcion, $precio, $id]);
        flash('success', 'Producto modificado');
        redirigir('productos.php');
    }
    $producto = array_merge($producto, ['codigo' => $codigo, 'descripcion' => $descripcion, 'precio' => $_POST['precio'] ?? '']);
}
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-6 m-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Modificar producto</div>
            <div class="card-body">
                <form action="" method="post">
                    <?php echo csrf_field(); ?>
                    <?php echo $alert; ?>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20" value="<?php echo e($producto['codigo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="producto">Producto</label>
                        <input type="text" class="form-control" name="producto" id="producto" maxlength="200" value="<?php echo e($producto['descripcion']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="precio">Precio (S/)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="precio" id="precio" value="<?php echo e($producto['precio']); ?>" required>
                    </div>
                    <input type="submit" value="Actualizar producto" class="btn btn-primary">
                    <a href="productos.php" class="btn btn-danger">Atrás</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
