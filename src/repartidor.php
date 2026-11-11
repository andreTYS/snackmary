<?php
require_once "includes/app.php";
require_permiso('repartidor');

// El repartidor ve sus entregas activas; quien gestiona pedidos ve todos los delivery
$todos = tiene_permiso('pedidos') && ($_GET['ver'] ?? '') === 'todos';
$sql = "SELECT id FROM ventas WHERE tipo = 'delivery' AND estado NOT IN ('pagado', 'anulado')";
$params = [];
if (!$todos) {
    $sql .= ' AND id_repartidor = ?';
    $params[] = usuario_id();
}
$entregas = array_map(fn($r) => obtener_pedido((int)$r['id']), db_all($sql . ' ORDER BY fecha ASC', $params));
$hoy = db_one(
    "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM ventas
     WHERE tipo = 'delivery' AND id_repartidor = ? AND estado = 'pagado' AND DATE(fecha_pago) = CURDATE()",
    [usuario_id()]
);
include_once "includes/header.php";
?>
<span id="auto-refresh" data-sonido="2"></span>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2"><i class="fas fa-motorcycle"></i> <?php echo $todos ? 'Todos los delivery' : 'Mis entregas'; ?></h1>
    <div class="mb-2">
        <span class="badge badge-light border p-2">Hoy: <?php echo (int)$hoy['n']; ?> entregas · S/ <?php echo dinero($hoy['total']); ?></span>
        <?php if (tiene_permiso('pedidos')) { ?>
            <a href="repartidor.php<?php echo $todos ? '' : '?ver=todos'; ?>" class="btn btn-outline-dark btn-sm ml-1"><?php echo $todos ? 'Solo las mías' : 'Ver todos'; ?></a>
        <?php } ?>
    </div>
</div>
<?php if (!$entregas) { ?>
    <div class="alert alert-info">No tienes entregas asignadas. Esta pantalla se actualiza sola y suena cuando te asignan una.</div>
<?php } ?>
<div class="row">
    <?php foreach ($entregas as $p) {
        $tel = telefono_pedido($p); ?>
        <div class="col-lg-6 mb-3">
            <div class="card entrega-card">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <strong class="h5 mb-0">#<?php echo (int)$p['id']; ?> · <?php echo e($p['cliente']); ?></strong>
                    <?php echo badge_estado($p['estado']); ?>
                </div>
                <div class="card-body">
                    <p class="h5"><i class="fas fa-map-marker-alt text-danger"></i> <?php echo e($p['direccion_entrega']); ?></p>
                    <?php if ($p['nota']) { ?><div class="alert alert-warning p-2"><?php echo e($p['nota']); ?></div><?php } ?>
                    <ul class="list-unstyled mb-2">
                        <?php foreach ($p['items'] as $it) { ?><li><b><?php echo (int)$it['cantidad']; ?>×</b> <?php echo e($it['descripcion']); ?></li><?php } ?>
                    </ul>
                    <p class="h4 mb-0">Cobrar: S/ <?php echo dinero($p['total']); ?> <small class="text-muted">(<?php echo e(METODOS_PAGO[$p['metodo_pago']] ?? $p['metodo_pago']); ?>)</small></p>
                    <?php if ($todos) { ?><small class="text-muted">Repartidor: <?php echo e($p['repartidor'] ?? 'sin asignar'); ?></small><?php } ?>
                </div>
                <div class="card-footer bg-white">
                    <div class="form-row mb-2">
                        <div class="col"><a href="<?php echo e(enlace_mapa($p['direccion_entrega'], $p['ubicacion'])); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-block"><i class="fas fa-map"></i> Mapa</a></div>
                        <?php if ($tel) { ?>
                            <div class="col"><a href="tel:<?php echo e(preg_replace('/[^\d+]/', '', $tel)); ?>" class="btn btn-outline-dark btn-block"><i class="fas fa-phone"></i> Llamar</a></div>
                            <div class="col"><a href="<?php echo e(enlace_whatsapp($tel, 'Hola, soy el repartidor de ' . (config_negocio()['nombre'] ?? 'Snack Mary') . '. Estoy en camino con tu pedido #' . (int)$p['id'] . '.')); ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-block"><i class="fab fa-whatsapp"></i></a></div>
                        <?php } ?>
                    </div>
                    <?php if ($p['estado'] !== 'en_camino') { ?>
                        <form method="post" action="pedido_accion.php" class="mb-2">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                            <input type="hidden" name="estado" value="en_camino">
                            <input type="hidden" name="volver" value="repartidor.php">
                            <button class="btn btn-info btn-block"><i class="fas fa-motorcycle"></i> Salí a entregar</button>
                        </form>
                    <?php } ?>
                    <form method="post" action="pedido_accion.php" class="confirmar" data-titulo="¿Entregado y cobrado?" data-texto="Se registrará el cobro de S/ <?php echo dinero($p['total']); ?>." data-boton="Sí, entregado">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="estado" value="pagado">
                        <input type="hidden" name="volver" value="repartidor.php">
                        <div class="form-row">
                            <div class="col-5">
                                <select name="metodo_pago" class="form-control" aria-label="Método de pago">
                                    <?php foreach (METODOS_PAGO as $valor => $texto) { ?>
                                        <option value="<?php echo $valor; ?>" <?php echo $valor === $p['metodo_pago'] ? 'selected' : ''; ?>><?php echo $texto; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-7"><button class="btn btn-success btn-block"><i class="fas fa-check"></i> Entregado y cobrado</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
<?php include_once "includes/footer.php"; ?>
