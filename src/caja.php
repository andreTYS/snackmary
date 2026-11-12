<?php
require_once "includes/app.php";
require_permiso('caja');

$alert = '';
if (es_post()) {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $abierta = caja_abierta();

    if ($accion === 'abrir') {
        $monto = decimal_positivo($_POST['monto_inicial'] ?? '0');
        if ($abierta) {
            flash('warning', 'Ya hay una caja abierta');
        } elseif ($monto === null) {
            $alert = alerta('danger', 'Ingresa el monto inicial (puede ser 0)');
        } else {
            db_exec('INSERT INTO cajas (id_usuario_apertura, monto_inicial) VALUES (?, ?)', [usuario_id(), $monto]);
            auditar('caja_abierta', 'S/ ' . dinero($monto));
            flash('success', 'Caja abierta con S/ ' . dinero($monto));
        }
        if (!$alert) {
            redirigir('caja.php');
        }
    } elseif ($accion === 'movimiento' && $abierta) {
        $tipo = ($_POST['tipo'] ?? '') === 'ingreso' ? 'ingreso' : 'egreso';
        $concepto = texto($_POST['concepto'] ?? '', 150);
        $monto = decimal_positivo($_POST['monto'] ?? '');
        $metodo = array_key_exists($_POST['metodo_pago'] ?? '', METODOS_PAGO) ? $_POST['metodo_pago'] : 'efectivo';
        if ($concepto === '' || !$monto) {
            $alert = alerta('danger', 'Escribe el concepto y un monto mayor a 0');
        } else {
            db_exec(
                'INSERT INTO caja_movimientos (id_caja, tipo, concepto, monto, metodo_pago, id_usuario) VALUES (?, ?, ?, ?, ?, ?)',
                [(int)$abierta['id'], $tipo, $concepto, $monto, $metodo, usuario_id()]
            );
            auditar('caja_' . $tipo, $concepto . ' S/ ' . dinero($monto) . ' (' . $metodo . ')');
            flash('success', ($tipo === 'ingreso' ? 'Ingreso' : 'Gasto') . ' registrado');
            redirigir('caja.php');
        }
    } elseif ($accion === 'cerrar' && $abierta) {
        $contado = decimal_positivo($_POST['efectivo_contado'] ?? '');
        if ($contado === null) {
            $alert = alerta('danger', 'Ingresa el efectivo que contaste en caja');
        } else {
            $resumen = resumen_caja($abierta);
            $pendientes = (int)db_val("SELECT COUNT(*) FROM ventas WHERE estado NOT IN ('pagado', 'anulado')");
            db_exec(
                "UPDATE cajas SET estado = 'cerrada', id_usuario_cierre = ?, fecha_cierre = NOW(), efectivo_esperado = ?, efectivo_contado = ?, resumen = ?, observacion = ? WHERE id = ?",
                [usuario_id(), $resumen['efectivo_esperado'], $contado, json_encode($resumen, JSON_UNESCAPED_UNICODE), texto($_POST['observacion'] ?? '', 255), (int)$abierta['id']]
            );
            $dif = round($contado - $resumen['efectivo_esperado'], 2);
            auditar('caja_cerrada', '#' . (int)$abierta['id'] . ' esperado S/ ' . dinero($resumen['efectivo_esperado']) . ', contado S/ ' . dinero($contado) . ', diferencia S/ ' . dinero($dif));
            flash($dif == 0 ? 'success' : 'warning', 'Caja cerrada. Diferencia: S/ ' . dinero($dif) . ($pendientes ? " · Quedan $pendientes pedidos sin cobrar" : ''));
            redirigir('caja.php?ver=' . (int)$abierta['id']);
        }
    }
}

$caja = caja_abierta();
$verId = entero($_GET['ver'] ?? 0);
$vista = $verId ? db_one('SELECT * FROM cajas WHERE id = ?', [$verId]) : $caja;
$resumen = null;
$movimientos = [];
if ($vista) {
    $resumen = $vista['estado'] === 'cerrada' && $vista['resumen'] ? json_decode($vista['resumen'], true) : resumen_caja($vista);
    $movimientos = db_all('SELECT m.*, u.nombre AS usuario FROM caja_movimientos m LEFT JOIN usuario u ON u.idusuario = m.id_usuario WHERE m.id_caja = ? ORDER BY m.id', [(int)$vista['id']]);
    $vista['abrio'] = db_val('SELECT nombre FROM usuario WHERE idusuario = ?', [(int)$vista['id_usuario_apertura']]);
    $vista['cerro'] = $vista['id_usuario_cierre'] ? db_val('SELECT nombre FROM usuario WHERE idusuario = ?', [(int)$vista['id_usuario_cierre']]) : null;
}
$historial = db_all('SELECT c.*, u.nombre AS abrio FROM cajas c LEFT JOIN usuario u ON u.idusuario = c.id_usuario_apertura ORDER BY c.id DESC LIMIT 30');
include_once "includes/header.php";
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-cash-register"></i> Caja</h1>
    <?php if ($verId && $caja && $verId !== (int)$caja['id']) { ?>
        <a href="caja.php" class="btn btn-outline-dark">Volver a la caja abierta</a>
    <?php } ?>
</div>
<?php echo $alert; ?>

<?php if (!$vista) { ?>
    <div class="row">
        <div class="col-md-6 col-lg-5 mx-auto">
            <div class="card">
                <div class="card-header bg-success text-white"><i class="fas fa-lock-open"></i> Abrir caja</div>
                <div class="card-body">
                    <form method="post">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="abrir">
                        <div class="form-group">
                            <label for="monto_inicial">¿Con cuánto efectivo empiezas? (sencillo)</label>
                            <input type="number" step="0.10" min="0" name="monto_inicial" id="monto_inicial" class="form-control form-control-lg" value="0" required>
                        </div>
                        <button class="btn btn-success btn-lg btn-block"><i class="fas fa-lock-open"></i> Abrir caja</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } else {
    $abiertaVista = $vista['estado'] === 'abierta'; ?>
    <div class="mb-2 text-muted">
        Caja #<?php echo (int)$vista['id']; ?> · abierta por <b><?php echo e($vista['abrio']); ?></b> el <?php echo e(date('d/m/Y H:i', strtotime($vista['fecha_apertura']))); ?>
        <?php if (!$abiertaVista) { ?> · cerrada por <b><?php echo e($vista['cerro']); ?></b> el <?php echo e(date('d/m/Y H:i', strtotime($vista['fecha_cierre']))); ?><?php } ?>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-2 no-imprimir" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3"><div class="kpi"><div class="titulo">Monto inicial</div><div class="valor">S/ <?php echo dinero($vista['monto_inicial']); ?></div></div></div>
        <div class="col-md-4 mb-3"><div class="kpi"><div class="titulo">Ventas cobradas (<?php echo (int)$resumen['num_ventas']; ?>)</div><div class="valor text-success">S/ <?php echo dinero($resumen['total_ventas']); ?></div></div></div>
        <div class="col-md-4 mb-3"><div class="kpi"><div class="titulo">Efectivo que debe haber</div><div class="valor">S/ <?php echo dinero($resumen['efectivo_esperado']); ?></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card mb-3">
                <div class="card-header">Arqueo por método de pago</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead class="thead-light"><tr><th>Método</th><th class="text-right">Ventas</th><th class="text-right">Ingresos</th><th class="text-right">Gastos</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($resumen['metodos'] as $clave => $m) {
                                $tot = $m['ventas'] + $m['ingresos'] - $m['egresos'] + ($clave === 'efectivo' ? (float)$vista['monto_inicial'] : 0); ?>
                                <tr>
                                    <td><?php echo e($m['nombre']); ?> <small class="text-muted">(<?php echo (int)$m['cantidad']; ?>)</small></td>
                                    <td class="text-right"><?php echo dinero($m['ventas']); ?></td>
                                    <td class="text-right"><?php echo dinero($m['ingresos']); ?></td>
                                    <td class="text-right text-danger"><?php echo $m['egresos'] > 0 ? '-' . dinero($m['egresos']) : '0.00'; ?></td>
                                    <td class="text-right font-weight-bold"><?php echo dinero($tot); ?><?php echo $clave === 'efectivo' ? ' *' : ''; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <small class="text-muted d-block p-2">* El efectivo incluye el monto inicial.</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Ingresos y gastos</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody>
                            <?php foreach ($movimientos as $mv) { ?>
                                <tr>
                                    <td><?php echo e(date('H:i', strtotime($mv['fecha']))); ?></td>
                                    <td><?php echo e($mv['concepto']); ?> <small class="text-muted">· <?php echo e(METODOS_PAGO[$mv['metodo_pago']] ?? $mv['metodo_pago']); ?> · <?php echo e($mv['usuario']); ?></small></td>
                                    <td class="text-right font-weight-bold <?php echo $mv['tipo'] === 'ingreso' ? 'text-success' : 'text-danger'; ?>"><?php echo ($mv['tipo'] === 'ingreso' ? '+' : '-') . dinero($mv['monto']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!$movimientos) { ?><tr><td class="text-muted text-center">Sin movimientos</td></tr><?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5 no-imprimir">
            <?php if ($abiertaVista) { ?>
                <div class="card mb-3">
                    <div class="card-header">Registrar gasto o ingreso</div>
                    <div class="card-body">
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="accion" value="movimiento">
                            <div class="btn-group btn-group-toggle d-flex mb-2" data-toggle="buttons">
                                <label class="btn btn-outline-danger w-100 active"><input type="radio" name="tipo" value="egreso" checked> Gasto</label>
                                <label class="btn btn-outline-success w-100"><input type="radio" name="tipo" value="ingreso"> Ingreso</label>
                            </div>
                            <input type="text" name="concepto" class="form-control mb-2" maxlength="150" placeholder="Concepto (ej: compra de pan, gas)" required aria-label="Concepto">
                            <div class="form-row">
                                <div class="col-6"><input type="number" step="0.10" min="0.10" name="monto" class="form-control" placeholder="Monto" required aria-label="Monto"></div>
                                <div class="col-6">
                                    <select name="metodo_pago" class="form-control" aria-label="Método">
                                        <?php foreach (METODOS_PAGO as $valor => $texto) { ?><option value="<?php echo $valor; ?>"><?php echo $texto; ?></option><?php } ?>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-dark btn-block mt-2"><i class="fas fa-plus"></i> Registrar</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header bg-danger text-white"><i class="fas fa-lock"></i> Cerrar caja</div>
                    <div class="card-body">
                        <form method="post" class="confirmar" data-titulo="¿Cerrar la caja?" data-texto="Después de cerrarla, los cobros irán a la siguiente caja." data-boton="Sí, cerrar">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="accion" value="cerrar">
                            <label for="efectivo_contado">Efectivo contado en caja</label>
                            <input type="number" step="0.10" min="0" name="efectivo_contado" id="efectivo_contado" class="form-control form-control-lg mb-1" required oninput="difCaja(this.value)">
                            <div class="mb-2">Diferencia: <b id="diferencia">—</b></div>
                            <input type="text" name="observacion" class="form-control mb-2" maxlength="255" placeholder="Observación (opcional)" aria-label="Observación">
                            <button class="btn btn-danger btn-lg btn-block"><i class="fas fa-lock"></i> Cerrar caja</button>
                        </form>
                    </div>
                </div>
                <script>
                    function difCaja(v) {
                        const d = (parseFloat(v) || 0) - <?php echo json_encode((float)$resumen['efectivo_esperado']); ?>;
                        const el = document.getElementById('diferencia');
                        el.textContent = (d >= 0 ? '+' : '') + d.toFixed(2) + (Math.abs(d) < 0.005 ? ' (cuadra)' : d > 0 ? ' (sobra)' : ' (falta)');
                        el.className = Math.abs(d) < 0.005 ? 'text-success' : 'text-danger';
                    }
                </script>
            <?php } else {
                $dif = (float)$vista['efectivo_contado'] - (float)$vista['efectivo_esperado']; ?>
                <div class="card">
                    <div class="card-header">Cierre</div>
                    <div class="card-body">
                        <p class="mb-1">Efectivo esperado: <b>S/ <?php echo dinero($vista['efectivo_esperado']); ?></b></p>
                        <p class="mb-1">Efectivo contado: <b>S/ <?php echo dinero($vista['efectivo_contado']); ?></b></p>
                        <p class="h5 <?php echo abs($dif) < 0.005 ? 'text-success' : 'text-danger'; ?>">Diferencia: S/ <?php echo dinero($dif); ?></p>
                        <?php if ($vista['observacion']) { ?><p class="text-muted mb-0"><?php echo e($vista['observacion']); ?></p><?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<div class="card mt-3 no-imprimir">
    <div class="card-header">Historial de cajas</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="thead-light"><tr><th>#</th><th>Apertura</th><th>Cierre</th><th>Abrió</th><th class="text-right">Esperado</th><th class="text-right">Contado</th><th class="text-right">Diferencia</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($historial as $h) {
                    $dif = $h['estado'] === 'cerrada' ? (float)$h['efectivo_contado'] - (float)$h['efectivo_esperado'] : null; ?>
                    <tr>
                        <td><?php echo (int)$h['id']; ?></td>
                        <td><?php echo e(date('d/m H:i', strtotime($h['fecha_apertura']))); ?></td>
                        <td><?php echo $h['fecha_cierre'] ? e(date('d/m H:i', strtotime($h['fecha_cierre']))) : '<span class="badge badge-success">Abierta</span>'; ?></td>
                        <td><?php echo e($h['abrio']); ?></td>
                        <td class="text-right"><?php echo $dif === null ? '—' : dinero($h['efectivo_esperado']); ?></td>
                        <td class="text-right"><?php echo $dif === null ? '—' : dinero($h['efectivo_contado']); ?></td>
                        <td class="text-right <?php echo $dif !== null && abs($dif) >= 0.005 ? 'text-danger font-weight-bold' : ''; ?>"><?php echo $dif === null ? '—' : dinero($dif); ?></td>
                        <td><a href="caja.php?ver=<?php echo (int)$h['id']; ?>" class="btn btn-sm btn-outline-dark">Ver</a></td>
                    </tr>
                <?php } ?>
                <?php if (!$historial) { ?><tr><td colspan="8" class="text-center text-muted p-3">Aún no hay cajas</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
