<?php
require_once "includes/app.php";
require_permiso('nueva_venta');

$mesas = db_all(
    "SELECT m.id, m.nombre,
            (SELECT v.id FROM ventas v WHERE v.tipo = 'mesa' AND v.id_mesa = m.id AND v.estado NOT IN ('pagado', 'anulado') ORDER BY v.id DESC LIMIT 1) AS pedido_abierto
     FROM mesas m WHERE m.estado = 1 ORDER BY m.id"
);
$tipoInicial = array_key_exists($_GET['tipo'] ?? '', TIPOS_PEDIDO) ? $_GET['tipo'] : 'mostrador';
$mesaInicial = entero($_GET['mesa'] ?? 0);
$publico = db_one('SELECT idcliente, nombre, telefono, direccion FROM cliente WHERE idcliente = 1');
include_once "includes/header.php";
?>
<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white"><i class="fas fa-clipboard-list"></i> Tipo de pedido</div>
            <div class="card-body">
                <div class="btn-group btn-group-toggle d-flex mb-3" data-toggle="buttons" id="grupoTipo">
                    <?php foreach (TIPOS_PEDIDO as $valor => $texto) { ?>
                        <label class="btn btn-outline-primary w-100<?php echo $valor === $tipoInicial ? ' active' : ''; ?>">
                            <input type="radio" name="tipo" value="<?php echo $valor; ?>" autocomplete="off" <?php echo $valor === $tipoInicial ? 'checked' : ''; ?>> <?php echo $texto; ?>
                        </label>
                    <?php } ?>
                </div>

                <div id="bloqueMesa" class="form-group d-none">
                    <label for="id_mesa">Mesa</label>
                    <select id="id_mesa" class="form-control">
                        <option value="">-- Selecciona una mesa --</option>
                        <?php foreach ($mesas as $m) { ?>
                            <option value="<?php echo (int)$m['id']; ?>" <?php echo (int)$m['id'] === $mesaInicial ? 'selected' : ''; ?>>
                                <?php echo e($m['nombre']); ?><?php echo $m['pedido_abierto'] ? ' (ocupada: se agrega al pedido #' . (int)$m['pedido_abierto'] . ')' : ' (libre)'; ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?php if (!$mesas) { ?><small class="text-danger">No hay mesas creadas. Créalas en el menú Mesas.</small><?php } ?>
                </div>

                <div id="bloqueDelivery" class="d-none">
                    <div class="form-group">
                        <label for="direccion_entrega">Dirección de entrega</label>
                        <input type="text" id="direccion_entrega" class="form-control" maxlength="200" placeholder="Calle, número, referencia">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-7">
                            <label for="telefono_entrega"><i class="fab fa-whatsapp"></i> Teléfono</label>
                            <input type="tel" id="telefono_entrega" class="form-control" maxlength="20">
                        </div>
                        <div class="form-group col-5">
                            <label for="costo_envio">Envío (S/)</label>
                            <input type="number" id="costo_envio" class="form-control" min="0" step="0.50" value="0">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="metodo_pago">Método de pago</label>
                        <select id="metodo_pago" class="form-control">
                            <?php foreach (METODOS_PAGO as $valor => $texto) { ?>
                                <option value="<?php echo $valor; ?>"><?php echo $texto; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label>Vendedor</label>
                        <p class="form-control-plaintext text-uppercase text-danger font-weight-bold"><?php echo e($_SESSION['nombre']); ?></p>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="nota">Nota para cocina / pedido</label>
                    <input type="text" id="nota" class="form-control" maxlength="255" placeholder="Ej: sin cebolla, para llevar...">
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user"></i> Cliente <a href="clientes.php" class="float-right small">+ Nuevo cliente</a></div>
            <div class="card-body">
                <input type="hidden" id="idcliente" value="1">
                <div class="form-group">
                    <label for="nom_cliente">Nombre (escribe 3 letras para buscar)</label>
                    <input type="text" id="nom_cliente" class="form-control" placeholder="<?php echo e($publico['nombre'] ?? 'Público en General'); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group col-6 mb-0">
                        <label for="tel_cliente">Teléfono</label>
                        <input type="text" id="tel_cliente" class="form-control" disabled>
                    </div>
                    <div class="form-group col-6 mb-0">
                        <label for="dir_cliente">Dirección</label>
                        <input type="text" id="dir_cliente" class="form-control" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-search"></i> Buscar producto</div>
            <div class="card-body">
                <input id="producto" class="form-control form-control-lg" type="text" placeholder="Escribe el código o nombre del producto">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="tblDetalle">
                <thead class="thead-dark">
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">Precio</th>
                        <th class="text-right">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="detalle_venta"></tbody>
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="3">Productos</td>
                        <td class="text-right" id="totalProductos">0.00</td>
                        <td></td>
                    </tr>
                    <tr class="font-weight-bold d-none" id="filaEnvio">
                        <td colspan="3">Envío</td>
                        <td class="text-right" id="totalEnvio">0.00</td>
                        <td></td>
                    </tr>
                    <tr class="font-weight-bold h5">
                        <td colspan="3">Total a pagar (S/)</td>
                        <td class="text-right" id="totalPagar">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <button type="button" class="btn btn-success btn-lg btn-block" id="btn_generar"><i class="fas fa-save"></i> Generar pedido / venta</button>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
