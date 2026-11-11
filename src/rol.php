<?php
require_once "includes/app.php";
require_permiso('usuarios');

$id = entero($_REQUEST['id'] ?? 0);
$usuario = $id ? db_one('SELECT idusuario, nombre FROM usuario WHERE idusuario = ?', [$id]) : null;
if (!$usuario || $id === 1) {
    // El administrador principal (id 1) siempre tiene todos los permisos
    redirigir('usuarios.php');
}
$permisos = db_all('SELECT id, nombre FROM permisos ORDER BY id');
if (es_post()) {
    csrf_check();
    $validos = array_column($permisos, 'id');
    $elegidos = array_unique(array_filter(array_map('intval', (array)($_POST['permisos'] ?? [])), fn($p) => in_array($p, array_map('intval', $validos), true)));
    $conexion->begin_transaction();
    db_exec('DELETE FROM detalle_permisos WHERE id_usuario = ?', [$id]);
    foreach ($elegidos as $permiso) {
        db_exec('INSERT INTO detalle_permisos (id_usuario, id_permiso) VALUES (?, ?)', [$id, $permiso]);
    }
    $conexion->commit();
    flash('success', 'Permisos actualizados');
    redirigir('rol.php?id=' . $id);
}
$asignados = array_map('intval', array_column(db_all('SELECT id_permiso FROM detalle_permisos WHERE id_usuario = ?', [$id]), 'id_permiso'));
$etiquetas = [
    'configuracion' => 'Configuración',
    'usuarios' => 'Usuarios',
    'clientes' => 'Clientes',
    'productos' => 'Productos',
    'ventas' => 'Ver ventas',
    'nueva_venta' => 'Nueva venta',
    'mesas' => 'Mesas',
    'pedidos' => 'Pedidos / Delivery',
    'caja' => 'Caja (abrir, cerrar, gastos)',
    'cocina' => 'Pantalla de cocina',
    'reportes' => 'Reportes',
    'repartidor' => 'Repartidor (Mis entregas)',
];
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header bg-warning text-white">Permisos de <?php echo e($usuario['nombre']); ?></div>
            <div class="card-body">
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <?php foreach ($permisos as $row) {
                        $pid = (int)$row['id']; ?>
                        <div class="custom-control custom-checkbox m-3">
                            <input id="permiso<?php echo $pid; ?>" class="custom-control-input" type="checkbox" name="permisos[]" value="<?php echo $pid; ?>" <?php echo in_array($pid, $asignados, true) ? 'checked' : ''; ?>>
                            <label for="permiso<?php echo $pid; ?>" class="custom-control-label"><?php echo e($etiquetas[$row['nombre']] ?? $row['nombre']); ?></label>
                        </div>
                    <?php } ?>
                    <button class="btn btn-primary btn-block" type="submit">Guardar permisos</button>
                    <a href="usuarios.php" class="btn btn-secondary btn-block">Volver</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
