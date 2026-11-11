<?php
require_once "includes/app.php";
require_permiso('productos');

$alert = '';
if (es_post()) {
    csrf_check();
    $codigo = texto($_POST['codigo'] ?? '', 20);
    $producto = texto($_POST['producto'] ?? '', 200);
    $precio = decimal_positivo($_POST['precio'] ?? '');
    $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($codigo === '' || $producto === '' || $precio === null || $cantidad === false) {
        $alert = alerta('danger', 'Todos los campos son obligatorios (precio y cantidad deben ser números positivos)');
    } elseif (db_one('SELECT codproducto FROM producto WHERE codigo = ?', [$codigo])) {
        $alert = alerta('warning', 'El código ya existe');
    } else {
        db_exec(
            'INSERT INTO producto (codigo, descripcion, precio, existencia, usuario_id) VALUES (?, ?, ?, ?, ?)',
            [$codigo, $producto, $precio, (int)$cantidad, usuario_id()]
        );
        flash('success', 'Producto registrado');
        redirigir('productos.php');
    }
}
$productos = db_all('SELECT * FROM producto ORDER BY estado DESC, descripcion');
include_once "includes/header.php";
?>
<button class="btn btn-primary mb-2" type="button" data-toggle="modal" data-target="#nuevo_producto"><i class="fas fa-plus"></i> Nuevo producto</button>
<?php echo $alert; ?>
<div class="table-responsive">
    <table class="table table-striped table-bordered" id="tbl">
        <thead class="thead-dark">
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $data) { ?>
                <tr>
                    <td><?php echo e($data['codigo']); ?></td>
                    <td><?php echo e($data['descripcion']); ?></td>
                    <td><?php echo dinero($data['precio']); ?></td>
                    <td class="<?php echo $data['existencia'] <= 10 ? 'text-danger font-weight-bold' : ''; ?>"><?php echo (int)$data['existencia']; ?></td>
                    <td><?php echo $data['estado'] == 1 ? '<span class="badge badge-pill badge-success">Activo</span>' : '<span class="badge badge-pill badge-danger">Inactivo</span>'; ?></td>
                    <td>
                        <?php if ($data['estado'] == 1) { ?>
                            <a href="agregar_producto.php?id=<?php echo (int)$data['codproducto']; ?>" class="btn btn-primary btn-sm" title="Agregar stock"><i class='fas fa-plus-square'></i></a>
                            <a href="editar_producto.php?id=<?php echo (int)$data['codproducto']; ?>" class="btn btn-success btn-sm" title="Editar"><i class='fas fa-edit'></i></a>
                            <form action="eliminar_producto.php" method="post" class="confirmar d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$data['codproducto']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit" title="Desactivar"><i class='fas fa-trash-alt'></i></button>
                            </form>
                        <?php } else { ?>
                            <form action="eliminar_producto.php" method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$data['codproducto']; ?>">
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
<div id="nuevo_producto" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="titulo-producto" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="titulo-producto">Nuevo Producto</h5>
                <button class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form action="" method="post" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" placeholder="Código de barras o código interno" name="codigo" id="codigo" class="form-control" maxlength="20" required>
                    </div>
                    <div class="form-group">
                        <label for="producto">Producto</label>
                        <input type="text" placeholder="Nombre del producto" name="producto" id="producto" class="form-control" maxlength="200" required>
                    </div>
                    <div class="form-group">
                        <label for="precio">Precio (S/)</label>
                        <input type="number" step="0.01" min="0" placeholder="0.00" class="form-control" name="precio" id="precio" required>
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Stock inicial</label>
                        <input type="number" min="0" placeholder="0" class="form-control" name="cantidad" id="cantidad" required>
                    </div>
                    <input type="submit" value="Guardar producto" class="btn btn-primary">
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
