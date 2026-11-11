<?php
require_once "includes/app.php";
require_permiso('ventas');

$fechaValida = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : null;
$desde = $fechaValida($_GET['desde'] ?? null) ?? date('Y-m-01');
$hasta = $fechaValida($_GET['hasta'] ?? null) ?? date('Y-m-d');

$ventas = db_all(
    'SELECT v.*, c.nombre, c.telefono AS cliente_telefono, m.nombre AS mesa
     FROM ventas v
     LEFT JOIN cliente c ON v.id_cliente = c.idcliente
     LEFT JOIN mesas m ON m.id = v.id_mesa
     WHERE DATE(v.fecha) BETWEEN ? AND ?
     ORDER BY v.id DESC',
    [$desde, $hasta]
);
$resumen = db_all(
    "SELECT metodo_pago, COUNT(*) AS cantidad, SUM(total) AS monto
     FROM ventas WHERE estado = 'pagado' AND DATE(fecha) BETWEEN ? AND ?
     GROUP BY metodo_pago ORDER BY monto DESC",
    [$desde, $hasta]
);
$totalCobrado = array_sum(array_column($resumen, 'monto'));
include_once "includes/header.php";
?>
<form class="form-inline mb-3" method="get">
    <label class="mr-2" for="desde">Desde</label>
    <input type="date" id="desde" name="desde" class="form-control mr-2" value="<?php echo e($desde); ?>">
    <label class="mr-2" for="hasta">Hasta</label>
    <input type="date" id="hasta" name="hasta" class="form-control mr-2" value="<?php echo e($hasta); ?>">
    <button class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
</form>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-success text-white mb-2">
            <div class="card-body py-2">
                <div class="small text-uppercase">Total cobrado</div>
                <div class="h4 mb-0">S/ <?php echo dinero($totalCobrado); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php foreach ($resumen as $r) { ?>
            <span class="badge badge-light border p-2 mb-1"><?php echo e(METODOS_PAGO[$r['metodo_pago']] ?? $r['metodo_pago']); ?>: S/ <?php echo dinero($r['monto']); ?> (<?php echo (int)$r['cantidad']; ?>)</span>
        <?php } ?>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-light table-sm" id="tbl">
        <thead class="thead-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cliente / Mesa</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventas as $row) {
                $tel = telefono_pedido($row); ?>
                <tr class="<?php echo $row['estado'] === 'anulado' ? 'text-muted' : ''; ?>">
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo e(date('d/m/Y H:i', strtotime($row['fecha']))); ?></td>
                    <td><?php echo e(TIPOS_PEDIDO[$row['tipo']] ?? $row['tipo']); ?></td>
                    <td><?php echo e($row['nombre'] ?? '-'); ?><?php echo $row['mesa'] ? ' · ' . e($row['mesa']) : ''; ?></td>
                    <td><?php echo dinero($row['total']); ?></td>
                    <td><?php echo e(METODOS_PAGO[$row['metodo_pago']] ?? $row['metodo_pago']); ?></td>
                    <td><?php echo badge_estado($row['estado']); ?></td>
                    <td class="text-nowrap">
                        <a href="pdf/generar.php?v=<?php echo (int)$row['id']; ?>" target="_blank" class="btn btn-danger btn-sm" title="Ticket"><i class="fas fa-file-pdf"></i></a>
                        <?php if ($tel && $row['estado'] !== 'anulado') {
                            $p = obtener_pedido((int)$row['id']); ?>
                            <a href="<?php echo e(enlace_whatsapp($tel, mensaje_pedido($p))); ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm" title="Enviar por WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <?php } ?>
                        <?php if ($row['estado'] !== 'anulado') { ?>
                            <form method="post" action="pedido_accion.php" class="confirmar d-inline" data-titulo="¿Anular la venta #<?php echo (int)$row['id']; ?>?" data-texto="Se devolverá el stock de los productos." data-boton="Sí, anular">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <input type="hidden" name="estado" value="anulado">
                                <input type="hidden" name="volver" value="lista_ventas.php">
                                <button class="btn btn-outline-danger btn-sm" title="Anular"><i class="fas fa-ban"></i></button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php include_once "includes/footer.php"; ?>
