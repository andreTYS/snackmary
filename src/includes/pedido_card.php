<?php
/*
 * Tarjeta de un pedido activo. Variables esperadas:
 *   $p            => pedido de obtener_pedido()
 *   $volver       => página a la que regresar tras una acción
 *   $repartidores => (opcional) lista de repartidores [idusuario, nombre]
 */
$minutos = (int)floor((time() - strtotime($p['fecha'])) / 60);
$tel = telefono_pedido($p);
$repartidores = $repartidores ?? [];
$siguientes = [
    'mostrador' => ['preparando', 'listo', 'entregado'],
    'mesa'      => ['preparando', 'listo', 'entregado'],
    'delivery'  => ['preparando', 'listo', 'en_camino', 'entregado'],
][$p['tipo']] ?? [];
$esWeb = $p['origen'] === 'web';
$cocinaTxt = ['pendiente' => 'Cocina: en cola', 'preparando' => 'Cocina: preparando', 'listo' => 'Cocina: listo'][$p['estado_cocina']] ?? '';
?>
<div class="card pedido-card mb-3 border-<?php echo ESTADOS_PEDIDO[$p['estado']][1] ?? 'secondary'; ?><?php echo $esWeb && $p['estado'] === 'pendiente' ? ' pedido-web nuevo' : ''; ?>">
    <div class="card-header d-flex justify-content-between align-items-center bg-white">
        <strong class="h5 mb-0">
            #<?php echo (int)$p['id']; ?> ·
            <?php if ($p['tipo'] === 'mesa') { ?>
                <i class="fas fa-chair"></i> <?php echo e($p['mesa']); ?>
            <?php } elseif ($p['tipo'] === 'delivery') { ?>
                <i class="fas fa-motorcycle"></i> Delivery
            <?php } else { ?>
                <i class="fas fa-store"></i> Mostrador
            <?php } ?>
        </strong>
        <span class="text-right">
            <?php if ($esWeb) { ?><span class="badge badge-success"><i class="fas fa-globe"></i> Web</span><?php } ?>
            <?php echo badge_estado($p['estado']); ?>
            <small class="text-muted d-block"><?php echo $minutos; ?> min<?php echo $cocinaTxt ? ' · ' . $cocinaTxt : ''; ?></small>
        </span>
    </div>
    <div class="card-body p-3">
        <div class="mb-1"><i class="fas fa-user text-muted"></i> <?php echo e($p['cliente']); ?><?php echo $tel ? ' · <a href="tel:' . e(preg_replace('/[^\d+]/', '', $tel)) . '">' . e($tel) . '</a>' : ''; ?></div>
        <?php if ($p['tipo'] === 'delivery') { ?>
            <div class="mb-2">
                <i class="fas fa-map-marker-alt text-danger"></i> <?php echo e($p['direccion_entrega']); ?>
                <a href="<?php echo e(enlace_mapa($p['direccion_entrega'], $p['ubicacion'])); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary ml-1"><i class="fas fa-map"></i> Mapa</a>
            </div>
        <?php } ?>
        <ul class="list-unstyled mb-1">
            <?php foreach ($p['items'] as $it) { ?>
                <li><b><?php echo (int)$it['cantidad']; ?>×</b> <?php echo e($it['descripcion']); ?><?php echo $p['ultima_ronda'] > 1 && (int)$it['ronda'] === $p['ultima_ronda'] ? ' <span class="badge badge-success">nuevo</span>' : ''; ?> <span class="float-right"><?php echo dinero($it['cantidad'] * $it['precio']); ?></span></li>
            <?php } ?>
            <?php if ((float)$p['costo_envio'] > 0) { ?>
                <li class="text-muted">Envío <span class="float-right"><?php echo dinero($p['costo_envio']); ?></span></li>
            <?php } ?>
        </ul>
        <?php if ($p['nota']) { ?>
            <div class="alert alert-warning p-2 mb-1"><i class="fas fa-sticky-note"></i> <?php echo e($p['nota']); ?></div>
        <?php } ?>
        <div class="font-weight-bold text-right h5 mb-0">S/ <?php echo dinero($p['total']); ?> <small class="text-muted">(<?php echo e(METODOS_PAGO[$p['metodo_pago']] ?? $p['metodo_pago']); ?>)</small></div>
    </div>
    <div class="card-footer bg-white p-2">
        <?php if ($p['tipo'] === 'delivery' && $repartidores) { ?>
            <form method="post" action="pedido_accion.php" class="mb-2">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <input type="hidden" name="volver" value="<?php echo e($volver); ?>">
                <select name="repartidor" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Repartidor">
                    <option value="0">— Sin repartidor —</option>
                    <?php foreach ($repartidores as $r) { ?>
                        <option value="<?php echo (int)$r['idusuario']; ?>" <?php echo (int)$p['id_repartidor'] === (int)$r['idusuario'] ? 'selected' : ''; ?>>Repartidor: <?php echo e($r['nombre']); ?></option>
                    <?php } ?>
                </select>
            </form>
        <?php } ?>
        <div class="mb-1">
            <?php foreach ($siguientes as $est) {
                if ($est === $p['estado']) continue; ?>
                <form method="post" action="pedido_accion.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <input type="hidden" name="estado" value="<?php echo $est; ?>">
                    <input type="hidden" name="volver" value="<?php echo e($volver); ?>">
                    <button class="btn btn-sm btn-outline-<?php echo ESTADOS_PEDIDO[$est][1]; ?>"><?php echo ESTADOS_PEDIDO[$est][0]; ?></button>
                </form>
            <?php } ?>
        </div>
        <form method="post" action="pedido_accion.php" class="d-flex mb-1">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
            <input type="hidden" name="estado" value="pagado">
            <input type="hidden" name="volver" value="<?php echo e($volver); ?>">
            <select name="metodo_pago" class="form-control form-control-sm mr-1" aria-label="Método de pago">
                <?php foreach (METODOS_PAGO as $valor => $texto) { ?>
                    <option value="<?php echo $valor; ?>" <?php echo $valor === $p['metodo_pago'] ? 'selected' : ''; ?>><?php echo $texto; ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-sm btn-success text-nowrap"><i class="fas fa-cash-register"></i> Cobrar</button>
        </form>
        <div class="mt-1">
            <?php if ($p['tipo'] === 'mesa' && tiene_permiso('nueva_venta')) { ?>
                <a href="ventas.php?tipo=mesa&amp;mesa=<?php echo (int)$p['id_mesa']; ?>" class="btn btn-sm btn-primary" title="Agregar productos"><i class="fas fa-plus"></i> Agregar</a>
            <?php } ?>
            <a href="pdf/generar.php?v=<?php echo (int)$p['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" title="Ticket"><i class="fas fa-receipt"></i></a>
            <a href="pdf/comanda.php?v=<?php echo (int)$p['id']; ?>" target="_blank" class="btn btn-sm btn-warning" title="Comanda para cocina"><i class="fas fa-fire"></i></a>
            <?php if ($tel) { ?>
                <a href="<?php echo e(enlace_whatsapp($tel, mensaje_pedido($p))); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-whatsapp" title="Enviar por WhatsApp"><i class="fab fa-whatsapp"></i></a>
            <?php } ?>
            <?php if (tiene_permiso('pedidos') || tiene_permiso('ventas')) { ?>
                <form method="post" action="pedido_accion.php" class="confirmar d-inline" data-titulo="¿Anular el pedido #<?php echo (int)$p['id']; ?>?" data-texto="Se devolverá el stock de los productos." data-boton="Sí, anular">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                    <input type="hidden" name="estado" value="anulado">
                    <input type="hidden" name="volver" value="<?php echo e($volver); ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="fas fa-ban"></i></button>
                </form>
            <?php } ?>
        </div>
    </div>
</div>
