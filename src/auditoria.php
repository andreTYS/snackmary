<?php
require_once "includes/app.php";
require_login();
if (!es_admin()) {
    redirigir('permisos.php');
}
$fechaValida = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : null;
$desde = $fechaValida($_GET['desde'] ?? null) ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $fechaValida($_GET['hasta'] ?? null) ?? date('Y-m-d');
$accion = preg_replace('/[^a-z_]/', '', (string)($_GET['accion'] ?? ''));

$sql = 'SELECT a.*, u.nombre FROM auditoria a LEFT JOIN usuario u ON u.idusuario = a.id_usuario WHERE DATE(a.fecha) BETWEEN ? AND ?';
$params = [$desde, $hasta];
if ($accion !== '') {
    $sql .= ' AND a.accion = ?';
    $params[] = $accion;
}
$registros = db_all($sql . ' ORDER BY a.id DESC LIMIT 1000', $params);
$acciones = array_column(db_all('SELECT DISTINCT accion FROM auditoria ORDER BY accion'), 'accion');
$fallidos = (int)db_val("SELECT COUNT(*) FROM auditoria WHERE accion IN ('login_fallido', 'login_bloqueado') AND fecha > NOW() - INTERVAL 24 HOUR");
$nombres = [
    'login' => 'Ingresó', 'logout' => 'Salió', 'login_fallido' => 'Contraseña incorrecta', 'login_bloqueado' => 'Bloqueado por intentos',
    'clave_cambiada' => 'Cambió su contraseña', 'clave_cambio_fallido' => 'Falló cambio de contraseña', 'clave_restablecida' => 'Restableció contraseña de',
    'usuario_creado' => 'Creó usuario', 'usuario_editado' => 'Editó usuario', 'usuario_desactivado' => 'Desactivó usuario', 'usuario_reactivado' => 'Reactivó usuario',
    'permisos_cambiados' => 'Cambió permisos', 'pedido_anulado' => 'Anuló pedido', 'precio_cambiado' => 'Cambió precio', 'stock_agregado' => 'Agregó stock',
    'producto_desactivado' => 'Desactivó producto', 'producto_reactivado' => 'Reactivó producto', 'caja_abierta' => 'Abrió caja', 'caja_cerrada' => 'Cerró caja',
    'caja_egreso' => 'Gasto de caja', 'caja_ingreso' => 'Ingreso de caja', 'configuracion_cambiada' => 'Cambió configuración', 'csrf_rechazado' => 'Solicitud rechazada (token)',
];
$alerta = ['login_fallido', 'login_bloqueado', 'pedido_anulado', 'precio_cambiado', 'csrf_rechazado', 'permisos_cambiados', 'clave_restablecida'];
include_once "includes/header.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-2"><i class="fas fa-shield-alt"></i> Auditoría</h1>
    <form class="form-inline mb-2" method="get">
        <input type="date" name="desde" class="form-control mr-2 mb-1" value="<?php echo e($desde); ?>" aria-label="Desde">
        <input type="date" name="hasta" class="form-control mr-2 mb-1" value="<?php echo e($hasta); ?>" aria-label="Hasta">
        <select name="accion" class="form-control mr-2 mb-1" aria-label="Acción">
            <option value="">Todas las acciones</option>
            <?php foreach ($acciones as $a) { ?><option value="<?php echo e($a); ?>" <?php echo $a === $accion ? 'selected' : ''; ?>><?php echo e($nombres[$a] ?? $a); ?></option><?php } ?>
        </select>
        <button class="btn btn-primary mb-1"><i class="fas fa-filter"></i> Ver</button>
    </form>
</div>
<?php if ($fallidos >= 5) { ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $fallidos; ?> intentos de ingreso fallidos en las últimas 24 horas. Si no fueron de tu personal, revisa las IP.</div>
<?php } ?>
<p class="text-muted">Registro de quién hizo qué: ingresos, anulaciones, cambios de precio, caja, usuarios y permisos. No se puede editar ni borrar desde el sistema.</p>
<div class="table-responsive">
    <table class="table table-sm table-striped" id="tbl">
        <thead class="thead-dark"><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
            <?php foreach ($registros as $r) { ?>
                <tr class="<?php echo in_array($r['accion'], $alerta, true) ? 'table-warning' : ''; ?>">
                    <td class="text-nowrap"><?php echo e(date('d/m/Y H:i:s', strtotime($r['fecha']))); ?></td>
                    <td><?php echo e($r['nombre'] ?? '—'); ?></td>
                    <td><?php echo e($nombres[$r['accion']] ?? $r['accion']); ?></td>
                    <td><?php echo e($r['detalle']); ?></td>
                    <td class="text-muted small"><?php echo e($r['ip']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php include_once "includes/footer.php"; ?>
