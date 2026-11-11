<?php
require_once "includes/app.php";
require_permiso('reportes');

$fechaValida = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : null;
$desde = $fechaValida($_GET['desde'] ?? null) ?? date('Y-m-01');
$hasta = $fechaValida($_GET['hasta'] ?? null) ?? date('Y-m-d');
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}
// Se cuentan solo ventas cobradas, por la fecha en que se cobraron
$base = "FROM ventas v WHERE v.estado = 'pagado' AND DATE(COALESCE(v.fecha_pago, v.fecha)) BETWEEN ? AND ?";
$p = [$desde, $hasta];

$kpi = db_one("SELECT COUNT(*) AS pedidos, COALESCE(SUM(v.total), 0) AS total, COALESCE(SUM(v.costo_envio), 0) AS envios $base", $p);
$anulados = (int)db_val("SELECT COUNT(*) FROM ventas WHERE estado = 'anulado' AND DATE(fecha) BETWEEN ? AND ?", $p);
$porDia = db_all("SELECT DATE(COALESCE(v.fecha_pago, v.fecha)) AS dia, COUNT(*) AS pedidos, SUM(v.total) AS total $base GROUP BY dia ORDER BY dia", $p);
$porHora = db_all("SELECT HOUR(COALESCE(v.fecha_pago, v.fecha)) AS hora, COUNT(*) AS pedidos, SUM(v.total) AS total $base GROUP BY hora ORDER BY hora", $p);
$porTipo = db_all("SELECT v.tipo, COUNT(*) AS pedidos, SUM(v.total) AS total $base GROUP BY v.tipo ORDER BY total DESC", $p);
$porMetodo = db_all("SELECT v.metodo_pago, COUNT(*) AS pedidos, SUM(v.total) AS total $base GROUP BY v.metodo_pago ORDER BY total DESC", $p);
$porOrigen = db_all("SELECT v.origen, COUNT(*) AS pedidos, SUM(v.total) AS total $base GROUP BY v.origen", $p);
$porUsuario = db_all(
    "SELECT COALESCE(u.nombre, 'Carta web') AS nombre, COUNT(*) AS pedidos, SUM(v.total) AS total
     FROM ventas v LEFT JOIN usuario u ON u.idusuario = v.id_usuario
     WHERE v.estado = 'pagado' AND DATE(COALESCE(v.fecha_pago, v.fecha)) BETWEEN ? AND ?
     GROUP BY nombre ORDER BY total DESC",
    $p
);
$productos = db_all(
    "SELECT pr.descripcion, COALESCE(c.nombre, 'Otros') AS categoria, SUM(d.cantidad) AS cantidad, SUM(d.cantidad * d.precio) AS total
     FROM detalle_venta d
     INNER JOIN ventas v ON v.id = d.id_venta
     INNER JOIN producto pr ON pr.codproducto = d.id_producto
     LEFT JOIN categorias c ON c.id = pr.id_categoria
     WHERE v.estado = 'pagado' AND DATE(COALESCE(v.fecha_pago, v.fecha)) BETWEEN ? AND ?
     GROUP BY d.id_producto, pr.descripcion, categoria
     ORDER BY total DESC",
    $p
);
$porCategoria = [];
foreach ($productos as $pr) {
    $porCategoria[$pr['categoria']] = ($porCategoria[$pr['categoria']] ?? 0) + (float)$pr['total'];
}
arsort($porCategoria);

/* ---------- Exportar a Excel (CSV con ; y BOM, lo abre Excel en español) ---------- */
if (($_GET['exportar'] ?? '') !== '') {
    $tipo = $_GET['exportar'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="snackmary_' . preg_replace('/\W/', '', $tipo) . "_{$desde}_{$hasta}.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $num = fn($v) => str_replace('.', ',', number_format((float)$v, 2, '.', ''));
    if ($tipo === 'ventas') {
        fputcsv($out, ['N°', 'Fecha cobro', 'Tipo', 'Origen', 'Cliente', 'Mesa', 'Atendió', 'Método', 'Envío', 'Total'], ';');
        foreach (db_all(
            "SELECT v.id, COALESCE(v.fecha_pago, v.fecha) AS f, v.tipo, v.origen, c.nombre AS cliente, m.nombre AS mesa, u.nombre AS usuario, v.metodo_pago, v.costo_envio, v.total
             FROM ventas v LEFT JOIN cliente c ON c.idcliente = v.id_cliente LEFT JOIN mesas m ON m.id = v.id_mesa LEFT JOIN usuario u ON u.idusuario = v.id_usuario
             WHERE v.estado = 'pagado' AND DATE(COALESCE(v.fecha_pago, v.fecha)) BETWEEN ? AND ? ORDER BY f",
            $p
        ) as $r) {
            fputcsv($out, [$r['id'], $r['f'], TIPOS_PEDIDO[$r['tipo']] ?? $r['tipo'], $r['origen'], $r['cliente'], $r['mesa'], $r['usuario'], METODOS_PAGO[$r['metodo_pago']] ?? $r['metodo_pago'], $num($r['costo_envio']), $num($r['total'])], ';');
        }
    } elseif ($tipo === 'productos') {
        fputcsv($out, ['Producto', 'Categoría', 'Cantidad', 'Total S/'], ';');
        foreach ($productos as $r) {
            fputcsv($out, [$r['descripcion'], $r['categoria'], (int)$r['cantidad'], $num($r['total'])], ';');
        }
    } else {
        fputcsv($out, ['Día', 'Pedidos', 'Total S/'], ';');
        foreach ($porDia as $r) {
            fputcsv($out, [$r['dia'], (int)$r['pedidos'], $num($r['total'])], ';');
        }
    }
    fclose($out);
    exit;
}

$dias = max(1, (int)((strtotime($hasta) - strtotime($desde)) / 86400) + 1);
$ticketPromedio = $kpi['pedidos'] ? $kpi['total'] / $kpi['pedidos'] : 0;
$qs = 'desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta);
include_once "includes/header.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2"><i class="fas fa-chart-bar"></i> Reportes</h1>
    <form class="form-inline mb-2" method="get">
        <input type="date" name="desde" class="form-control mr-2 mb-1" value="<?php echo e($desde); ?>" aria-label="Desde">
        <input type="date" name="hasta" class="form-control mr-2 mb-1" value="<?php echo e($hasta); ?>" aria-label="Hasta">
        <button class="btn btn-primary mb-1"><i class="fas fa-filter"></i> Ver</button>
    </form>
</div>
<div class="mb-3">
    <?php
    $atajos = [
        'Hoy' => [date('Y-m-d'), date('Y-m-d')],
        'Ayer' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
        'Últimos 7 días' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
        'Este mes' => [date('Y-m-01'), date('Y-m-d')],
        'Mes pasado' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    ];
    foreach ($atajos as $txt => [$a, $b]) { ?>
        <a href="?desde=<?php echo $a; ?>&amp;hasta=<?php echo $b; ?>" class="btn btn-sm mb-1 btn-<?php echo $a === $desde && $b === $hasta ? 'dark' : 'outline-dark'; ?>"><?php echo $txt; ?></a>
    <?php } ?>
    <div class="btn-group float-md-right mb-1">
        <a href="?<?php echo $qs; ?>&amp;exportar=ventas" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Ventas</a>
        <a href="?<?php echo $qs; ?>&amp;exportar=productos" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Productos</a>
        <a href="?<?php echo $qs; ?>&amp;exportar=dias" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Por día</a>
    </div>
</div>

<div class="row">
    <div class="col-6 col-lg-3 mb-3"><div class="kpi"><div class="titulo">Total vendido</div><div class="valor text-success">S/ <?php echo dinero($kpi['total']); ?></div></div></div>
    <div class="col-6 col-lg-3 mb-3"><div class="kpi"><div class="titulo">Pedidos cobrados</div><div class="valor"><?php echo (int)$kpi['pedidos']; ?></div><small class="text-muted"><?php echo $anulados; ?> anulados</small></div></div>
    <div class="col-6 col-lg-3 mb-3"><div class="kpi"><div class="titulo">Ticket promedio</div><div class="valor">S/ <?php echo dinero($ticketPromedio); ?></div></div></div>
    <div class="col-6 col-lg-3 mb-3"><div class="kpi"><div class="titulo">Promedio por día</div><div class="valor">S/ <?php echo dinero($kpi['total'] / $dias); ?></div></div></div>
</div>

<div class="row">
    <div class="col-lg-8 mb-3"><div class="card h-100"><div class="card-header">Ventas por día</div><div class="card-body"><canvas id="gDias" height="120"></canvas></div></div></div>
    <div class="col-lg-4 mb-3"><div class="card h-100"><div class="card-header">Por categoría</div><div class="card-body"><canvas id="gCategorias" height="220"></canvas></div></div></div>
    <div class="col-lg-8 mb-3"><div class="card h-100"><div class="card-header">Horas con más ventas</div><div class="card-body"><canvas id="gHoras" height="120"></canvas></div></div></div>
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header">Resumen</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr class="table-light"><th colspan="3">Tipo de pedido</th></tr>
                    <?php foreach ($porTipo as $r) { ?><tr><td><?php echo e(TIPOS_PEDIDO[$r['tipo']] ?? $r['tipo']); ?></td><td class="text-right"><?php echo (int)$r['pedidos']; ?></td><td class="text-right">S/ <?php echo dinero($r['total']); ?></td></tr><?php } ?>
                    <tr class="table-light"><th colspan="3">Método de pago</th></tr>
                    <?php foreach ($porMetodo as $r) { ?><tr><td><?php echo e(METODOS_PAGO[$r['metodo_pago']] ?? $r['metodo_pago']); ?></td><td class="text-right"><?php echo (int)$r['pedidos']; ?></td><td class="text-right">S/ <?php echo dinero($r['total']); ?></td></tr><?php } ?>
                    <tr class="table-light"><th colspan="3">Origen</th></tr>
                    <?php foreach ($porOrigen as $r) { ?><tr><td><?php echo $r['origen'] === 'web' ? 'Carta web' : 'Local'; ?></td><td class="text-right"><?php echo (int)$r['pedidos']; ?></td><td class="text-right">S/ <?php echo dinero($r['total']); ?></td></tr><?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header">Productos más vendidos</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>#</th><th>Producto</th><th>Categoría</th><th class="text-right">Cantidad</th><th class="text-right">Total</th><th class="text-right">%</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($productos, 0, 20) as $i => $r) { ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo e($r['descripcion']); ?></td>
                                <td class="text-muted"><?php echo e($r['categoria']); ?></td>
                                <td class="text-right"><?php echo (int)$r['cantidad']; ?></td>
                                <td class="text-right">S/ <?php echo dinero($r['total']); ?></td>
                                <td class="text-right"><?php echo $kpi['total'] > 0 ? number_format($r['total'] * 100 / $kpi['total'], 1) : '0'; ?>%</td>
                            </tr>
                        <?php } ?>
                        <?php if (!$productos) { ?><tr><td colspan="6" class="text-center text-muted p-3">Sin ventas en estas fechas</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header">Por trabajador</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <?php foreach ($porUsuario as $r) { ?><tr><td><?php echo e($r['nombre']); ?></td><td class="text-right"><?php echo (int)$r['pedidos']; ?></td><td class="text-right">S/ <?php echo dinero($r['total']); ?></td></tr><?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
<script>
(function () {
    const dias = <?php echo json_encode($porDia, JSON_HEX_TAG); ?>;
    const horas = <?php echo json_encode($porHora, JSON_HEX_TAG); ?>;
    const cats = <?php echo json_encode($porCategoria, JSON_HEX_TAG); ?>;
    const rojo = 'rgba(226,30,36,0.8)';
    const formato = { yAxes: [{ ticks: { beginAtZero: true, callback: v => 'S/ ' + v } }] };
    new Chart(document.getElementById('gDias'), {
        type: 'bar',
        data: { labels: dias.map(d => d.dia.slice(8, 10) + '/' + d.dia.slice(5, 7)), datasets: [{ label: 'Ventas S/', data: dias.map(d => d.total), backgroundColor: rojo }] },
        options: { legend: { display: false }, scales: formato }
    });
    const todas = Array.from({ length: 24 }, (_, h) => h).filter(h => h >= 7 || horas.some(x => +x.hora === h));
    new Chart(document.getElementById('gHoras'), {
        type: 'bar',
        data: {
            labels: todas.map(h => h + ':00'),
            datasets: [{ label: 'Ventas S/', data: todas.map(h => (horas.find(x => +x.hora === h) || { total: 0 }).total), backgroundColor: 'rgba(253,126,20,0.8)' }]
        },
        options: { legend: { display: false }, scales: formato }
    });
    new Chart(document.getElementById('gCategorias'), {
        type: 'doughnut',
        data: { labels: Object.keys(cats), datasets: [{ data: Object.values(cats), backgroundColor: ['#e21e24', '#fd7e14', '#ffc107', '#28a745', '#17a2b8', '#6f42c1', '#6c757d', '#343a40'] }] },
        options: { legend: { position: 'bottom' } }
    });
})();
</script>
