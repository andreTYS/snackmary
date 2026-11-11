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
    $categoria = entero($_POST['id_categoria'] ?? 0) ?: null;
    $controla = isset($_POST['controla_stock']) ? 1 : 0;
    if ($codigo === '' || $descripcion === '' || $precio === null) {
        $alert = alerta('danger', 'Todos los campos son requeridos');
    } elseif (db_one('SELECT codproducto FROM producto WHERE codigo = ? AND codproducto <> ?', [$codigo, $id])) {
        $alert = alerta('warning', 'Ese código ya lo usa otro producto');
    } else {
        try {
            $imagen = $producto['imagen'];
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = guardar_imagen_producto($_FILES['imagen']);
                borrar_imagen_producto($producto['imagen']);
            } elseif (isset($_POST['quitar_imagen'])) {
                borrar_imagen_producto($producto['imagen']);
                $imagen = null;
            }
            db_exec(
                'UPDATE producto SET codigo = ?, descripcion = ?, precio = ?, id_categoria = ?, controla_stock = ?, imagen = ? WHERE codproducto = ?',
                [$codigo, $descripcion, $precio, $categoria, $controla, $imagen, $id]
            );
            flash('success', 'Producto modificado');
            redirigir('productos.php');
        } catch (RuntimeException $ex) {
            $alert = alerta('danger', $ex->getMessage());
        }
    }
    $producto = array_merge($producto, ['codigo' => $codigo, 'descripcion' => $descripcion, 'precio' => $_POST['precio'] ?? '', 'id_categoria' => $categoria, 'controla_stock' => $controla]);
}
$categorias = db_all('SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY orden, nombre');
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-6 m-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">Modificar producto</div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
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
                    <div class="form-group">
                        <label for="id_categoria">Categoría</label>
                        <select name="id_categoria" id="id_categoria" class="form-control">
                            <option value="">Otros</option>
                            <?php foreach ($categorias as $c) { ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$producto['id_categoria'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['nombre']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="imagen">Foto</label>
                        <?php if ($img = imagen_producto($producto['imagen'])) { ?>
                            <div class="mb-2 d-flex align-items-center">
                                <img src="../<?php echo e($img); ?>" alt="" width="96" height="96" class="rounded mr-3" style="object-fit:cover">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="quitar_imagen" name="quitar_imagen">
                                    <label class="custom-control-label" for="quitar_imagen">Quitar foto</label>
                                </div>
                            </div>
                        <?php } ?>
                        <input type="file" name="imagen" id="imagen" class="form-control-file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="controla_stock" name="controla_stock" <?php echo (int)$producto['controla_stock'] === 1 ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="controla_stock">Controlar stock</label>
                        <small class="form-text text-muted">Apágalo para platos que se preparan al momento.</small>
                    </div>
                    <input type="submit" value="Actualizar producto" class="btn btn-primary">
                    <a href="opciones.php?id=<?php echo $id; ?>" class="btn btn-info"><i class="fas fa-list-ul"></i> Opciones del plato</a>
                    <a href="productos.php" class="btn btn-danger">Atrás</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
