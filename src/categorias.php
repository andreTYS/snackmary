<?php
require_once "includes/app.php";
require_permiso('productos');

$alert = '';
if (es_post()) {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $id = entero($_POST['id'] ?? 0);
    if ($accion === 'guardar') {
        $nombre = texto($_POST['nombre'] ?? '', 60);
        $orden = entero($_POST['orden'] ?? 0);
        $cocina = isset($_POST['cocina']) ? 1 : 0;
        if ($nombre === '') {
            $alert = alerta('danger', 'Escribe el nombre de la categoría');
        } elseif (db_one('SELECT id FROM categorias WHERE nombre = ? AND id <> ? AND estado = 1', [$nombre, $id])) {
            $alert = alerta('warning', 'Ya existe una categoría con ese nombre');
        } else {
            if ($id) {
                db_exec('UPDATE categorias SET nombre = ?, orden = ?, cocina = ? WHERE id = ?', [$nombre, $orden, $cocina, $id]);
            } else {
                db_exec('INSERT INTO categorias (nombre, orden, cocina) VALUES (?, ?, ?)', [$nombre, $orden, $cocina]);
            }
            flash('success', 'Categoría guardada');
            redirigir('categorias.php');
        }
    } elseif ($accion === 'eliminar' && $id) {
        db_exec('UPDATE categorias SET estado = 0 WHERE id = ?', [$id]);
        db_exec('UPDATE producto SET id_categoria = NULL WHERE id_categoria = ?', [$id]);
        flash('success', 'Categoría eliminada. Sus productos quedaron en "Otros".');
        redirigir('categorias.php');
    }
}
$categorias = db_all(
    'SELECT c.*, (SELECT COUNT(*) FROM producto p WHERE p.id_categoria = c.id AND p.estado = 1) AS productos
     FROM categorias c WHERE c.estado = 1 ORDER BY c.orden, c.nombre'
);
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-tags"></i> Nueva categoría</div>
            <div class="card-body">
                <?php echo $alert; ?>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="accion" value="guardar">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="60" placeholder="Ej: Hamburguesas" required>
                    </div>
                    <div class="form-group">
                        <label for="orden">Orden en la carta</label>
                        <input type="number" name="orden" id="orden" class="form-control" value="<?php echo count($categorias) + 1; ?>">
                    </div>
                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="cocina" name="cocina" checked>
                        <label class="custom-control-label" for="cocina">Se prepara en cocina</label>
                        <small class="form-text text-muted">Apágalo para bebidas o productos envasados: no aparecerán en la pantalla de cocina.</small>
                    </div>
                    <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="thead-light">
                        <tr><th>Categoría</th><th class="text-center">Orden</th><th class="text-center">Cocina</th><th class="text-center">Productos</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $c) {
                            $f = 'cat' . (int)$c['id']; ?>
                            <tr>
                                <td><input type="text" name="nombre" form="<?php echo $f; ?>" class="form-control" maxlength="60" value="<?php echo e($c['nombre']); ?>" aria-label="Nombre" required></td>
                                <td style="width:90px"><input type="number" name="orden" form="<?php echo $f; ?>" class="form-control text-center" value="<?php echo (int)$c['orden']; ?>" aria-label="Orden"></td>
                                <td class="text-center">
                                    <div class="custom-control custom-switch d-inline-block">
                                        <input type="checkbox" class="custom-control-input" id="cocina<?php echo (int)$c['id']; ?>" name="cocina" form="<?php echo $f; ?>" <?php echo $c['cocina'] ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="cocina<?php echo (int)$c['id']; ?>"><span class="sr-only">Cocina</span></label>
                                    </div>
                                </td>
                                <td class="text-center"><?php echo (int)$c['productos']; ?></td>
                                <td class="text-nowrap">
                                    <form method="post" id="<?php echo $f; ?>" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="accion" value="guardar">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <button class="btn btn-success btn-sm" title="Guardar"><i class="fas fa-save"></i></button>
                                    </form>
                                    <form method="post" class="confirmar d-inline" data-titulo="¿Eliminar la categoría <?php echo e($c['nombre']); ?>?" data-texto="Sus productos quedarán en 'Otros'." data-boton="Sí, eliminar">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (!$categorias) { ?>
                            <tr><td colspan="5" class="text-muted text-center p-4">Aún no hay categorías.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
