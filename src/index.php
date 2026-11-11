<?php
require_once "includes/app.php";
require_login();
$totalU = db_val('SELECT COUNT(*) FROM usuario WHERE estado = 1');
$totalC = db_val('SELECT COUNT(*) FROM cliente WHERE estado = 1');
$totalP = db_val('SELECT COUNT(*) FROM producto WHERE estado = 1');
$ventasHoy = db_one("SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS monto FROM ventas WHERE DATE(fecha) = CURDATE() AND estado <> 'anulado'");
$pedidosActivos = db_val("SELECT COUNT(*) FROM ventas WHERE estado NOT IN ('pagado', 'anulado')");
$mesasOcupadas = db_val("SELECT COUNT(DISTINCT id_mesa) FROM ventas WHERE tipo = 'mesa' AND estado NOT IN ('pagado', 'anulado')");
$mesasTotal = db_val('SELECT COUNT(*) FROM mesas WHERE estado = 1');
$stockBajo = db_val('SELECT COUNT(*) FROM producto WHERE estado = 1 AND existencia <= 10');

$tarjetas = [
    ['lista_ventas.php', 'ventas', 'bg-danger', 'Ventas de hoy', 'S/ ' . dinero($ventasHoy['monto']) . ' <small>(' . (int)$ventasHoy['cantidad'] . ')</small>', 'fas fa-dollar-sign'],
    ['pedidos.php', 'pedidos', 'bg-warning', 'Pedidos activos', (int)$pedidosActivos, 'fas fa-concierge-bell'],
    ['mesas.php', 'mesas', 'bg-info', 'Mesas ocupadas', (int)$mesasOcupadas . ' / ' . (int)$mesasTotal, 'fas fa-chair'],
    ['productos.php', 'productos', 'bg-primary', 'Productos (stock bajo: ' . (int)$stockBajo . ')', (int)$totalP, 'fas fa-hamburger'],
    ['clientes.php', 'clientes', 'bg-success', 'Clientes', (int)$totalC, 'fas fa-users'],
    ['usuarios.php', 'usuarios', 'bg-secondary', 'Usuarios', (int)$totalU, 'fas fa-user'],
];
include_once "includes/header.php";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Panel - <?php echo e(config_negocio()['nombre'] ?? 'Snack Mary'); ?></h1>
    <?php if (tiene_permiso('nueva_venta')) { ?>
        <a href="ventas.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> Nueva venta</a>
    <?php } ?>
</div>

<div class="row">
    <?php foreach ($tarjetas as [$url, $permiso, $color, $titulo, $valor, $icono]) {
        if (!tiene_permiso($permiso)) continue; ?>
        <a class="col-xl-4 col-md-6 mb-4 text-decoration-none" href="<?php echo $url; ?>">
            <div class="card shadow h-100 py-2 <?php echo $color; ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-white text-uppercase mb-1"><?php echo e($titulo); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-white"><?php echo $valor; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="<?php echo $icono; ?> fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    <?php } ?>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">Productos con stock mínimo</div>
            <div class="card-body"><canvas id="sales-chart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">Productos más vendidos</div>
            <div class="card-body"><canvas id="polarChart"></canvas></div>
        </div>
    </div>
</div>

<?php include_once "includes/footer.php"; ?>
