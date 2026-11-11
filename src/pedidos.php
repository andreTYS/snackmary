<?php
require_once "includes/app.php";
require_permiso('pedidos');

$filtro = array_key_exists($_GET['tipo'] ?? '', TIPOS_PEDIDO) ? $_GET['tipo'] : '';
$sql = "SELECT id FROM ventas WHERE estado NOT IN ('pagado', 'anulado')";
$params = [];
if ($filtro) {
    $sql .= ' AND tipo = ?';
    $params[] = $filtro;
}
$sql .= ' ORDER BY fecha ASC';
$pedidos = array_map(fn($r) => obtener_pedido((int)$r['id']), db_all($sql, $params));
$volver = 'pedidos.php';
$repartidores = lista_repartidores();
$webPendientes = (int)db_val("SELECT COUNT(*) FROM ventas WHERE origen = 'web' AND estado = 'pendiente'");
include_once "includes/header.php";
?>
<span id="auto-refresh" data-sonido="1"></span>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2">Pedidos activos
        <?php if ($webPendientes) { ?><span class="badge badge-success"><i class="fas fa-globe"></i> <?php echo $webPendientes; ?> nuevo<?php echo $webPendientes > 1 ? 's' : ''; ?> de la carta</span><?php } ?>
    </h1>
    <div class="btn-group mb-2">
        <a href="pedidos.php" class="btn btn-<?php echo $filtro === '' ? 'dark' : 'outline-dark'; ?>">Todos</a>
        <?php foreach (TIPOS_PEDIDO as $valor => $texto) { ?>
            <a href="pedidos.php?tipo=<?php echo $valor; ?>" class="btn btn-<?php echo $filtro === $valor ? 'dark' : 'outline-dark'; ?>"><?php echo $texto; ?></a>
        <?php } ?>
    </div>
    <?php if (tiene_permiso('nueva_venta')) { ?>
        <div class="mb-2">
            <a href="ventas.php?tipo=delivery" class="btn btn-primary"><i class="fas fa-motorcycle"></i> Nuevo delivery</a>
            <a href="ventas.php?tipo=mesa" class="btn btn-info"><i class="fas fa-chair"></i> Pedido en mesa</a>
        </div>
    <?php } ?>
</div>
<?php if (!$pedidos) { ?>
    <div class="alert alert-info">No hay pedidos pendientes. Esta pantalla avisa con sonido cuando llega uno nuevo (toca la pantalla una vez para activar el sonido).</div>
<?php } ?>
<div class="row">
    <?php foreach ($pedidos as $p) { ?>
        <div class="col-xl-4 col-lg-6">
            <?php include "includes/pedido_card.php"; ?>
        </div>
    <?php } ?>
</div>
<?php include_once "includes/footer.php"; ?>
