<?php
require_once "includes/app.php";
require_permiso('productos');

$alert = '';
if (es_post()) {
    csrf_check();
    $codigo = texto($_POST['codigo'] ?? '', 20);
    $producto = texto($_POST['producto'] ?? '', 200);
    $precio = decimal_positivo($_POST['precio'] ?? '');
    $controla = isset($_POST['controla_stock']) ? 1 : 0;
    $cantidad = $controla ? filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : 0;
    $categoria = entero($_POST['id_categoria'] ?? 0) ?: null;
    if ($codigo === '' || $producto === '' || $precio === null || $cantidad === false) {
        $alert = alerta('danger', 'Completa código, nombre, precio y stock (números positivos)');
    } elseif (db_one('SELECT codproducto FROM producto WHERE codigo = ?', [$codigo])) {
        $alert = alerta('warning', 'El código ya existe');
    } else {
        try {
            $imagen = !empty($_FILES['imagen']['name']) ? guardar_imagen_producto($_FILES['imagen']) : null;
            db_exec(
                'INSERT INTO producto (codigo, descripcion, precio, existencia, usuario_id, id_categoria, imagen, controla_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$codigo, $producto, $precio, (int)$cantidad, usuario_id(), $categoria, $imagen, $controla]
            );
            flash('success', 'Producto registrado');
            redirigir('productos.php');
        } catch (RuntimeException $ex) {
            $alert = alerta('danger', $ex->getMessage());
        }
    }
}
$categorias = db_all('SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY orden, nombre');
$productos = db_all('SELECT p.*, c.nombre AS categoria FROM producto p LEFT JOIN categorias c ON c.id = p.id_categoria AND c.estado = 1 ORDER BY p.estado DESC, p.descripcion');
include_once "includes/header.php";
?>
<button class="btn btn-primary mb-2" type="button" data-toggle="modal" data-target="#nuevo_producto"><i class="fas fa-plus"></i> Nuevo producto</button>
<a href="categorias.php" class="btn btn-outline-dark mb-2"><i class="fas fa-tags"></i> Categorías</a>
<?php echo $alert; ?>
<div class="table-responsive">
    <table class="table table-striped table-bordered" id="tbl">
        <thead class="thead-dark">
            <tr>
                <th></th>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $data) { ?>
                <tr>
                    <td style="width:64px">
                        <?php if ($img = imagen_producto($data['imagen'])) { ?>
                            <img src="../<?php echo e($img); ?>" alt="" width="48" height="48" class="rounded" style="object-fit:cover">
                        <?php } else { ?>
                            <div class="rounded bg-light text-center text-muted" style="width:48px;height:48px;line-height:48px"><i class="fas fa-utensils"></i></div>
                        <?php } ?>
                    </td>
                    <td><?php echo e($data['codigo']); ?></td>
                    <td><?php echo e($data['descripcion']); ?></td>
                    <td><?php echo e($data['categoria'] ?? 'Otros'); ?></td>
                    <td><?php echo dinero($data['precio']); ?></td>
                    <?php if ((int)$data['controla_stock'] === 1) { ?>
                        <td class="<?php echo $data['existencia'] <= 10 ? 'text-danger font-weight-bold' : ''; ?>"><?php echo (int)$data['existencia']; ?></td>
                    <?php } else { ?>
                        <td class="text-muted" title="No controla stock (se prepara al momento)">—</td>
                    <?php } ?>
                    <td><?php echo $data['estado'] == 1 ? '<span class="badge badge-pill badge-success">Activo</span>' : '<span class="badge badge-pill badge-danger">Inactivo</span>'; ?></td>
                    <td>
                        <?php if ($data['estado'] == 1) { ?>
                            <?php if ((int)$data['controla_stock'] === 1) { ?>
                                <a href="agregar_producto.php?id=<?php echo (int)$data['codproducto']; ?>" class="btn btn-primary btn-sm" title="Agregar stock"><i class='fas fa-plus-square'></i></a>
                            <?php } ?>
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
                <form action="" method="post" autocomplete="off" enctype="multipart/form-data">
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
                        <label for="id_categoria">Categoría</label>
                        <select name="id_categoria" id="id_categoria" class="form-control">
                            <option value="">Otros</option>
                            <?php foreach ($categorias as $c) { ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['nombre']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="imagen">Foto (opcional)</label>
                        <input type="file" name="imagen" id="imagen" class="form-control-file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" class="custom-control-input" id="controla_stock" name="controla_stock" checked onchange="document.getElementById('grupoStock').classList.toggle('d-none', !this.checked)">
                        <label class="custom-control-label" for="controla_stock">Controlar stock</label>
                        <small class="form-text text-muted">Apágalo para platos que se preparan al momento (hamburguesas, salchipapas...).</small>
                    </div>
                    <div class="form-group" id="grupoStock">
                        <label for="cantidad">Stock inicial</label>
                        <input type="number" min="0" placeholder="0" class="form-control" name="cantidad" id="cantidad">
                    </div>
                    <input type="submit" value="Guardar producto" class="btn btn-primary">
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
