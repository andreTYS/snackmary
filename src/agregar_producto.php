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
    $precio = decimal_positivo($_POST['precio'] ?? '');
    $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($precio === null || $cantidad === false) {
        $alert = alerta('danger', 'Ingresa un precio válido y una cantidad mayor a 0');
    } else {
        db_exec('UPDATE producto SET existencia = existencia + ?, precio = ? WHERE codproducto = ?', [(int)$cantidad, $precio, $id]);
        auditar('stock_agregado', $producto['descripcion'] . ': +' . (int)$cantidad . ((float)$producto['precio'] !== (float)$precio ? ', precio S/ ' . dinero($producto['precio']) . ' -> S/ ' . dinero($precio) : ''));
        flash('success', 'Stock actualizado');
        redirigir('productos.php');
    }
}
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-6 m-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Agregar stock: <?php echo e($producto['descripcion']); ?></div>
            <div class="card-body">
                <form action="" method="post">
                    <?php echo csrf_field(); ?>
                    <?php echo $alert; ?>
                    <div class="form-group">
                        <label>Precio actual</label>
                        <input type="text" class="form-control" value="<?php echo dinero($producto['precio']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Stock disponible</label>
                        <input type="number" class="form-control" value="<?php echo (int)$producto['existencia']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="precio">Nuevo precio (S/)</label>
                        <input type="number" step="0.01" min="0" name="precio" id="precio" class="form-control" value="<?php echo e($producto['precio']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Cantidad a agregar</label>
                        <input type="number" min="1" placeholder="Ingrese cantidad" name="cantidad" id="cantidad" class="form-control" required>
                    </div>
                    <input type="submit" value="Actualizar" class="btn btn-primary">
                    <a href="productos.php" class="btn btn-danger">Regresar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
