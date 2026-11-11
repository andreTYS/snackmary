<?php
require_once "includes/app.php";
require_permiso('mesas');

$alert = '';
if (es_post()) {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = texto($_POST['nombre'] ?? '', 50);
        $capacidad = max(1, entero($_POST['capacidad'] ?? 4));
        if ($nombre === '') {
            $alert = alerta('danger', 'Ingresa el nombre de la mesa');
        } elseif (db_one('SELECT id FROM mesas WHERE nombre = ? AND estado = 1', [$nombre])) {
            $alert = alerta('warning', 'Ya existe una mesa con ese nombre');
        } else {
            db_exec('INSERT INTO mesas (nombre, capacidad) VALUES (?, ?)', [$nombre, $capacidad]);
            flash('success', 'Mesa creada');
            redirigir('mesas.php');
        }
    } elseif ($accion === 'eliminar') {
        $id = entero($_POST['id'] ?? 0);
        $ocupada = db_val("SELECT COUNT(*) FROM ventas WHERE tipo = 'mesa' AND id_mesa = ? AND estado NOT IN ('pagado', 'anulado')", [$id]);
        if ($ocupada) {
            flash('warning', 'No se puede quitar una mesa ocupada. Cobra o anula su pedido primero.');
        } else {
            db_exec('UPDATE mesas SET estado = 0 WHERE id = ?', [$id]);
            flash('success', 'Mesa eliminada');
        }
        redirigir('mesas.php');
    }
}

$mesas = db_all(
    "SELECT m.*,
            (SELECT v.id FROM ventas v WHERE v.tipo = 'mesa' AND v.id_mesa = m.id AND v.estado NOT IN ('pagado', 'anulado') ORDER BY v.id DESC LIMIT 1) AS pedido_abierto
     FROM mesas m WHERE m.estado = 1 ORDER BY m.id"
);
$volver = 'mesas.php';
$repartidores = [];
include_once "includes/header.php";
?>
<span id="auto-refresh"></span>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2">Mesas</h1>
    <form method="post" class="form-inline mb-2">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" class="form-control form-control-sm mr-1" placeholder="Ej: Mesa 5" maxlength="50" required>
        <input type="number" name="capacidad" class="form-control form-control-sm mr-1" style="width:80px" min="1" value="4" title="Capacidad (personas)">
        <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Agregar mesa</button>
    </form>
</div>
<?php echo $alert; ?>
<?php if (!$mesas) { ?>
    <div class="alert alert-info">Aún no hay mesas. Crea la primera con el formulario de arriba.</div>
<?php } ?>
<div class="row">
    <?php foreach ($mesas as $m) {
        $abierto = (int)$m['pedido_abierto']; ?>
        <?php if ($abierto) {
            $p = obtener_pedido($abierto); ?>
            <div class="col-xl-4 col-lg-6">
                <?php include "includes/pedido_card.php"; ?>
            </div>
        <?php } else { ?>
            <div class="col-xl-3 col-md-4 col-6 mb-3">
                <div class="card mesa-card mesa-libre text-center h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="fas fa-chair fa-2x text-success mb-2"></i>
                        <h5 class="mb-0"><?php echo e($m['nombre']); ?></h5>
                        <small class="text-muted"><?php echo (int)$m['capacidad']; ?> personas · Libre</small>
                    </div>
                    <div class="card-footer p-1">
                        <?php if (tiene_permiso('nueva_venta')) { ?>
                            <a href="ventas.php?tipo=mesa&amp;mesa=<?php echo (int)$m['id']; ?>" class="btn btn-success">Tomar pedido</a>
                        <?php } ?>
                        <form method="post" class="confirmar d-inline" data-titulo="¿Quitar <?php echo e($m['nombre']); ?>?" data-boton="Sí, quitar">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                            <button class="btn btn-outline-danger btn-sm" title="Quitar mesa"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>
<?php include_once "includes/footer.php"; ?>
