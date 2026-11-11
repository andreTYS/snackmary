<?php
require_once "includes/app.php";
require_permiso('nueva_venta');

$mesas = db_all(
    "SELECT m.id, m.nombre,
            (SELECT v.id FROM ventas v WHERE v.tipo = 'mesa' AND v.id_mesa = m.id AND v.estado NOT IN ('pagado', 'anulado') ORDER BY v.id DESC LIMIT 1) AS pedido_abierto
     FROM mesas m WHERE m.estado = 1 ORDER BY m.id"
);
$categorias = db_all('SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY orden, nombre');
$hayOtros = (int)db_val('SELECT COUNT(*) FROM producto WHERE estado = 1 AND (id_categoria IS NULL OR id_categoria NOT IN (SELECT id FROM categorias WHERE estado = 1))') > 0;
$tipoInicial = array_key_exists($_GET['tipo'] ?? '', TIPOS_PEDIDO) ? $_GET['tipo'] : 'mostrador';
$mesaInicial = entero($_GET['mesa'] ?? 0);
include_once "includes/header.php";
?>
<?php if (!$cajaActual && tiene_permiso('caja')) { ?>
    <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle"></i> No hay caja abierta. Las ventas cobradas no entrarán al arqueo. <a href="caja.php" class="alert-link">Abrir caja</a></div>
<?php } ?>
<div class="row">
    <!-- Productos -->
    <div class="col-lg-7 mb-3">
        <div class="d-flex mb-2">
            <input id="buscarProducto" class="form-control mr-2" type="search" placeholder="Buscar producto o código...">
        </div>
        <div class="pos-categorias mb-2" id="categorias">
            <button type="button" class="btn btn-dark" data-cat="todas">Todas</button>
            <?php foreach ($categorias as $c) { ?>
                <button type="button" class="btn btn-outline-dark" data-cat="<?php echo (int)$c['id']; ?>"><?php echo e($c['nombre']); ?></button>
            <?php } ?>
            <?php if ($hayOtros) { ?>
                <button type="button" class="btn btn-outline-dark" data-cat="0">Otros</button>
            <?php } ?>
        </div>
        <div class="pos-grilla" id="grilla">
            <div class="text-muted p-3">Cargando productos...</div>
        </div>
    </div>

    <!-- Pedido -->
    <div class="col-lg-5">
        <div class="pos-carrito">
            <div class="card mb-2">
                <div class="card-body p-3">
                    <div class="btn-group btn-group-toggle d-flex mb-2" data-toggle="buttons" id="grupoTipo">
                        <?php foreach (TIPOS_PEDIDO as $valor => $texto) { ?>
                            <label class="btn btn-outline-primary w-100<?php echo $valor === $tipoInicial ? ' active' : ''; ?>">
                                <input type="radio" name="tipo" value="<?php echo $valor; ?>" autocomplete="off" <?php echo $valor === $tipoInicial ? 'checked' : ''; ?>> <?php echo $texto; ?>
                            </label>
                        <?php } ?>
                    </div>

                    <div id="bloqueMostrador" class="custom-control custom-switch mb-1">
                        <input type="checkbox" class="custom-control-input" id="cobrarAhora" checked>
                        <label class="custom-control-label" for="cobrarAhora">Cobrar ahora (si no, queda pendiente para llevar)</label>
                    </div>

                    <div id="bloqueMesa" class="d-none">
                        <select id="id_mesa" class="form-control" aria-label="Mesa">
                            <option value="">-- Selecciona la mesa --</option>
                            <?php foreach ($mesas as $m) { ?>
                                <option value="<?php echo (int)$m['id']; ?>" <?php echo (int)$m['id'] === $mesaInicial ? 'selected' : ''; ?>>
                                    <?php echo e($m['nombre']); ?><?php echo $m['pedido_abierto'] ? ' · ocupada (se suma al #' . (int)$m['pedido_abierto'] . ')' : ' · libre'; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div id="bloqueDelivery" class="d-none">
                        <input type="text" id="direccion_entrega" class="form-control mb-2" maxlength="200" placeholder="Dirección y referencia" aria-label="Dirección de entrega">
                        <div class="form-row">
                            <div class="col-7"><input type="tel" id="telefono_entrega" class="form-control" maxlength="20" placeholder="Teléfono / WhatsApp" aria-label="Teléfono"></div>
                            <div class="col-5"><input type="number" id="costo_envio" class="form-control" min="0" step="0.50" value="0" aria-label="Costo de envío" title="Costo de envío"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-2">
                <div class="card-body p-0">
                    <table class="table mb-0" id="tblDetalle">
                        <tbody id="detalle_venta"></tbody>
                    </table>
                    <div id="carritoVacio" class="text-center text-muted p-4"><i class="fas fa-shopping-basket fa-2x mb-2"></i><br>Toca un producto para agregarlo</div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-none" id="filaEnvio"><div class="d-flex justify-content-between"><span>Envío</span><span id="totalEnvio">0.00</span></div></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total</span>
                        <span class="pos-total">S/ <span id="totalPagar">0.00</span></span>
                    </div>
                    <span id="totalProductos" class="d-none">0.00</span>
                </div>
            </div>

            <div class="card mb-2">
                <div class="card-body p-3">
                    <input type="hidden" id="idcliente" value="1">
                    <div class="form-row">
                        <div class="col-7 mb-2">
                            <input type="text" id="nom_cliente" class="form-control" placeholder="Cliente (escribe 3 letras)" aria-label="Cliente">
                        </div>
                        <div class="col-5 mb-2">
                            <select id="metodo_pago" class="form-control" aria-label="Método de pago">
                                <?php foreach (METODOS_PAGO as $valor => $texto) { ?>
                                    <option value="<?php echo $valor; ?>"><?php echo $texto; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="tel_cliente"><input type="hidden" id="dir_cliente">
                    <input type="text" id="nota" class="form-control" maxlength="255" placeholder="Nota para cocina (sin cebolla, para llevar...)" aria-label="Nota">
                </div>
            </div>

            <button type="button" class="btn btn-success btn-lg btn-block mb-3" id="btn_generar"><i class="fas fa-check"></i> Generar pedido</button>
        </div>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
