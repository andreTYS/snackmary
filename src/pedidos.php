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
include_once "includes/header.php";
?>
<span id="auto-refresh"></span>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2">Pedidos activos <small class="text-muted">(se actualiza cada 30 s)</small></h1>
    <div class="btn-group mb-2">
        <a href="pedidos.php" class="btn btn-sm btn-<?php echo $filtro === '' ? 'dark' : 'outline-dark'; ?>">Todos</a>
        <?php foreach (TIPOS_PEDIDO as $valor => $texto) { ?>
            <a href="pedidos.php?tipo=<?php echo $valor; ?>" class="btn btn-sm btn-<?php echo $filtro === $valor ? 'dark' : 'outline-dark'; ?>"><?php echo $texto; ?></a>
        <?php } ?>
    </div>
    <?php if (tiene_permiso('nueva_venta')) { ?>
        <div class="mb-2">
            <a href="ventas.php?tipo=delivery" class="btn btn-primary btn-sm"><i class="fas fa-motorcycle"></i> Nuevo delivery</a>
            <a href="ventas.php?tipo=mesa" class="btn btn-info btn-sm"><i class="fas fa-chair"></i> Pedido en mesa</a>
        </div>
    <?php } ?>
</div>
<?php if (!$pedidos) { ?>
    <div class="alert alert-info">No hay pedidos pendientes.</div>
<?php } ?>
<div class="row">
    <?php foreach ($pedidos as $p) { ?>
        <div class="col-xl-4 col-md-6">
            <?php include "includes/pedido_card.php"; ?>
        </div>
    <?php } ?>
</div>
<?php include_once "includes/footer.php"; ?>
