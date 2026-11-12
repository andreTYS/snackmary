<?php
/*
 * Carta pública: el cliente arma su pedido, que entra directo al sistema
 * (pantalla de Pedidos y Cocina) y además puede avisar por WhatsApp.
 */
require_once __DIR__ . '/src/includes/app.php';

$negocio = config_negocio();
$categorias = db_all('SELECT id, nombre FROM categorias WHERE estado = 1 ORDER BY orden, nombre');
$productos = db_all(
    'SELECT p.codproducto, p.descripcion, p.precio, p.imagen, p.id_categoria
     FROM producto p LEFT JOIN categorias c ON c.id = p.id_categoria AND c.estado = 1
     WHERE p.estado = 1 AND (p.controla_stock = 0 OR p.existencia > 0)
     ORDER BY COALESCE(c.orden, 999), p.descripcion'
);
$idsCat = array_map('intval', array_column($categorias, 'id'));
$grupos = [];
foreach ($productos as $p) {
    $cat = in_array((int)$p['id_categoria'], $idsCat, true) ? (int)$p['id_categoria'] : 0;
    $grupos[$cat][] = $p;
}
$opcionesCarta = [];
foreach (productos_con_opciones() as $idp => $_) {
    $g = grupos_opciones((int)$idp);
    if ($g) {
        $opcionesCarta[(int)$idp] = $g;
    }
}
$nombreNegocio = $negocio['nombre'] ?? 'Snack Mary';
$costoEnvio = (float)($negocio['costo_envio'] ?? 0);
$activa = !empty($negocio['carta_activa']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?php echo e($nombreNegocio); ?> - Carta</title>
    <meta name="description" content="Pide en <?php echo e($nombreNegocio); ?>: delivery o recojo en tienda.">
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link href="assets/css/tablet.css?v=4" rel="stylesheet" />
    <link rel="icon" href="assets/img/icon-192.png">
    <link rel="apple-touch-icon" href="assets/img/icon-192.png">
    <meta name="theme-color" content="#e21e24">
    <script src="assets/js/all.min.js"></script>
    <style>
        body { padding-bottom: 96px; }
        .cabecera { background: var(--sm-rojo); color: #fff; padding: 1.25rem 0 1rem; }
        .cabecera img { width: 64px; height: 64px; border-radius: 50%; background: #fff; }
        .tabs { position: sticky; top: 0; z-index: 10; background: var(--sm-fondo); padding: .6rem 0; }
        .tabs .pos-categorias { margin: 0; }
        .producto { background: #fff; border-radius: var(--sm-radio); box-shadow: 0 1px 3px rgba(0,0,0,.08); display: flex; overflow: hidden; margin-bottom: .75rem; }
        .producto .foto { width: 108px; min-height: 108px; background: #f0e6e6 center/cover no-repeat; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: var(--sm-rojo); font-size: 1.8rem; }
        .producto .info { padding: .75rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .producto .nombre { font-weight: 600; font-size: 1.05rem; }
        .producto .precio { color: var(--sm-rojo); font-weight: 700; font-size: 1.1rem; }
        .cantidad { min-width: 2rem; display: inline-block; text-align: center; font-weight: 700; font-size: 1.1rem; }
        .barra-pedido { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; box-shadow: 0 -2px 10px rgba(0,0,0,.1); }
        .seccion { scroll-margin-top: 70px; }
        .trampa { position: absolute; left: -9999px; }
    </style>
</head>

<body>
    <div class="cabecera">
        <div class="container d-flex align-items-center">
            <img src="assets/img/icon-192.png" alt="" class="mr-3">
            <div>
                <h1 class="h4 mb-0"><?php echo e($nombreNegocio); ?></h1>
                <div class="small text-white-50"><?php echo e($negocio['direccion'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <div class="tabs">
        <div class="container">
            <input type="search" id="buscar" class="form-control mb-2" placeholder="Buscar en la carta...">
            <div class="pos-categorias">
                <?php foreach ($categorias as $c) {
                    if (empty($grupos[(int)$c['id']])) continue; ?>
                    <a href="#cat<?php echo (int)$c['id']; ?>" class="btn btn-outline-dark"><?php echo e($c['nombre']); ?></a>
                <?php } ?>
                <?php if (!empty($grupos[0]) && $categorias) { ?><a href="#cat0" class="btn btn-outline-dark">Otros</a><?php } ?>
            </div>
        </div>
    </div>

    <div class="container py-2">
        <?php if (!$activa) { ?>
            <div class="alert alert-warning"><i class="fas fa-store-slash"></i> En este momento no estamos recibiendo pedidos por aquí. ¡Vuelve pronto!</div>
        <?php } ?>
        <?php
        $secciones = array_merge(array_map(fn($c) => [(int)$c['id'], $c['nombre']], $categorias), [[0, $categorias ? 'Otros' : 'Nuestra carta']]);
        foreach ($secciones as [$idCat, $nombreCat]) {
            if (empty($grupos[$idCat])) continue; ?>
            <section class="seccion" id="cat<?php echo $idCat; ?>">
                <h2 class="h5 mt-3 mb-2"><?php echo e($nombreCat); ?></h2>
                <?php foreach ($grupos[$idCat] as $p) {
                    $img = imagen_producto($p['imagen']); ?>
                    <div class="producto" data-id="<?php echo (int)$p['codproducto']; ?>" data-nombre="<?php echo e($p['descripcion']); ?>" data-precio="<?php echo (float)$p['precio']; ?>">
                        <?php if ($img) { ?>
                            <div class="foto" style="background-image:url('<?php echo e($img); ?>')" role="img" aria-label="<?php echo e($p['descripcion']); ?>"></div>
                        <?php } else { ?>
                            <div class="foto"><i class="fas fa-utensils"></i></div>
                        <?php } ?>
                        <div class="info">
                            <div class="nombre"><?php echo e($p['descripcion']); ?></div>
                            <?php if (isset($opcionesCarta[(int)$p['codproducto']])) { ?><div class="small text-muted"><i class="fas fa-list-ul"></i> Elige tu opción</div><?php } ?>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="precio">S/ <?php echo dinero($p['precio']); ?></span>
                                <span class="text-nowrap">
                                    <button class="btn btn-outline-danger btn-sm menos" type="button" aria-label="Quitar"><i class="fas fa-minus"></i></button>
                                    <span class="cantidad">0</span>
                                    <button class="btn btn-success btn-sm mas" type="button" aria-label="Agregar"><i class="fas fa-plus"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>
        <?php if (!$productos) { ?>
            <div class="alert alert-light">No hay productos disponibles por ahora.</div>
        <?php } ?>
    </div>

    <!-- Datos para el pedido -->
    <div class="modal fade" id="modalPedido" tabindex="-1" role="dialog" aria-labelledby="tituloPedido" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloPedido">Tu pedido</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <ul class="list-unstyled mb-2" id="resumen"></ul>
                    <form id="frmPedido" autocomplete="on">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="text" name="web" class="trampa" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="form-group">
                            <label for="nombre">Tu nombre</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" maxlength="80" autocomplete="name" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Celular / WhatsApp</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" maxlength="15" inputmode="numeric" autocomplete="tel" placeholder="9XX XXX XXX" required>
                        </div>
                        <div class="btn-group btn-group-toggle d-flex mb-3" data-toggle="buttons">
                            <label class="btn btn-outline-primary w-100 active"><input type="radio" name="entrega" value="delivery" checked> <i class="fas fa-motorcycle"></i> Delivery</label>
                            <label class="btn btn-outline-primary w-100"><input type="radio" name="entrega" value="recojo"> <i class="fas fa-store"></i> Recojo</label>
                        </div>
                        <div id="grupoDireccion">
                            <div class="form-group mb-2">
                                <label for="direccion">Dirección y referencia</label>
                                <input type="text" id="direccion" name="direccion" class="form-control" maxlength="200" autocomplete="street-address">
                            </div>
                            <input type="hidden" name="ubicacion" id="ubicacion">
                            <button type="button" class="btn btn-outline-dark btn-block mb-3" id="btnUbicacion"><i class="fas fa-location-arrow"></i> Usar mi ubicación actual</button>
                        </div>
                        <div class="form-group">
                            <label for="pago">¿Cómo pagarás?</label>
                            <select id="pago" name="pago" class="form-control">
                                <?php foreach (METODOS_PAGO as $valor => $texto) { ?><option value="<?php echo $valor; ?>"><?php echo $texto; ?></option><?php } ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label for="nota">Nota (opcional)</label>
                            <input type="text" id="nota" name="nota" class="form-control" maxlength="200" placeholder="Ej: sin ají, pago con S/ 50">
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-block">
                    <div class="d-flex justify-content-between mb-2"><span>Total</span><b class="h5 mb-0">S/ <span id="totalModal">0.00</span></b></div>
                    <button type="button" class="btn btn-success btn-lg btn-block" id="confirmar"><i class="fas fa-check"></i> Confirmar pedido</button>
                </div>
            </div>
        </div>
    </div>

    <div class="barra-pedido bg-white p-2">
        <div class="container d-flex justify-content-between align-items-center">
            <div><small class="text-muted"><span id="numItems">0</span> productos</small><div class="h5 mb-0">S/ <span id="total">0.00</span></div></div>
            <button class="btn btn-success btn-lg" id="verPedido" type="button" <?php echo $activa ? '' : 'disabled'; ?>><i class="fas fa-shopping-bag"></i> Ver pedido</button>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script src="assets/js/opciones.js?v=4"></script>
    <script>
        (function () {
            const ENVIO = <?php echo json_encode($costoEnvio); ?>;
            const OPCIONES = <?php echo json_encode($opcionesCarta, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            // Carrito por líneas: un mismo plato con distintas opciones va en líneas separadas
            let lineas = [];
            let guardado = {};
            try { guardado = JSON.parse(localStorage.getItem('sm_cliente') || '{}'); } catch (e) { }
            ['nombre', 'telefono', 'direccion'].forEach(k => { if (guardado[k]) document.getElementById(k).value = guardado[k]; });

            const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const entrega = () => document.querySelector('input[name="entrega"]:checked').value;

            function sumarLinea(id, nombre, precio, opciones, texto, cant) {
                const clave = id + '|' + opciones.slice().sort((a, b) => a - b).join(',');
                const l = lineas.find(x => x.clave === clave);
                if (l) l.cant += cant;
                else lineas.push({ clave, id, nombre, precio, opciones, texto, cant });
                actualizar();
            }

            function actualizar() {
                let total = 0, n = 0;
                lineas = lineas.filter(l => l.cant > 0);
                document.querySelectorAll('.producto').forEach(el => {
                    el.querySelector('.cantidad').textContent = lineas.filter(l => String(l.id) === el.dataset.id).reduce((a, l) => a + l.cant, 0);
                });
                lineas.forEach(l => { total += l.cant * l.precio; n += l.cant; });
                document.getElementById('total').textContent = total.toFixed(2);
                document.getElementById('numItems').textContent = n;
                return total;
            }

            document.querySelectorAll('.producto').forEach(el => {
                const id = Number(el.dataset.id);
                el.querySelector('.mas').addEventListener('click', () => {
                    if (OPCIONES[id]) {
                        elegirOpciones({ id, nombre: el.dataset.nombre, precio: parseFloat(el.dataset.precio), grupos: OPCIONES[id] },
                            (ids, texto, precio, cant) => sumarLinea(id, el.dataset.nombre, precio, ids, texto, cant));
                    } else {
                        sumarLinea(id, el.dataset.nombre, parseFloat(el.dataset.precio), [], '', 1);
                    }
                });
                el.querySelector('.menos').addEventListener('click', () => {
                    // Quita una unidad de la última línea de este plato
                    const ultima = lineas.filter(l => l.id === id).pop();
                    if (ultima) { ultima.cant--; actualizar(); }
                });
            });

            document.getElementById('buscar').addEventListener('input', function () {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.producto').forEach(el => el.classList.toggle('d-none', q !== '' && !el.dataset.nombre.toLowerCase().includes(q)));
                document.querySelectorAll('.seccion').forEach(s => s.classList.toggle('d-none', !s.querySelector('.producto:not(.d-none)')));
            });

            $('input[name="entrega"]').on('change', () => {
                $('#grupoDireccion').toggleClass('d-none', entrega() !== 'delivery');
                pintarResumen();
            });

            function pintarResumen() {
                let html = '', subtotal = 0;
                lineas.forEach((l, i) => {
                    const imp = l.cant * l.precio;
                    subtotal += imp;
                    html += `<li class="d-flex justify-content-between align-items-start py-1"><span><b>${l.cant}×</b> ${esc(l.nombre)}${l.texto ? `<br><small class="text-danger">${esc(l.texto)}</small>` : ''}</span>
                        <span class="text-nowrap">S/ ${imp.toFixed(2)} <button type="button" class="btn btn-link text-danger p-0 ml-2 quitar-linea" data-i="${i}" aria-label="Quitar"><i class="fas fa-times"></i></button></span></li>`;
                });
                const envio = entrega() === 'delivery' ? ENVIO : 0;
                if (envio > 0) html += `<li class="d-flex justify-content-between text-muted"><span>Delivery</span><span>S/ ${envio.toFixed(2)}</span></li>`;
                $('#resumen').html(html + '<hr class="my-2">');
                $('#totalModal').text((subtotal + envio).toFixed(2));
            }
            $('#resumen').on('click', '.quitar-linea', function () {
                lineas.splice(Number(this.dataset.i), 1);
                actualizar();
                if (!lineas.length) { $('#modalPedido').modal('hide'); return; }
                pintarResumen();
            });

            $('#verPedido').on('click', () => {
                if (actualizar() <= 0) {
                    Swal.fire({ icon: 'info', title: 'Agrega al menos un producto', confirmButtonColor: '#e21e24' });
                    return;
                }
                pintarResumen();
                $('#modalPedido').modal('show');
            });

            $('#btnUbicacion').on('click', function () {
                const btn = this;
                if (!navigator.geolocation) { Swal.fire('Tu celular no permite compartir la ubicación'); return; }
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando tu ubicación...';
                navigator.geolocation.getCurrentPosition(pos => {
                    $('#ubicacion').val(pos.coords.latitude.toFixed(6) + ',' + pos.coords.longitude.toFixed(6));
                    btn.className = 'btn btn-success btn-block mb-3';
                    btn.innerHTML = '<i class="fas fa-check"></i> Ubicación agregada';
                    btn.disabled = false;
                }, () => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-location-arrow"></i> Usar mi ubicación actual';
                    Swal.fire({ icon: 'warning', title: 'No pudimos obtener tu ubicación', text: 'Escribe tu dirección con una referencia.', confirmButtonColor: '#e21e24' });
                }, { enableHighAccuracy: true, timeout: 15000 });
            });

            $('#confirmar').on('click', function () {
                const form = document.getElementById('frmPedido');
                if (!form.reportValidity()) return;
                const items = lineas.map(l => ({ id: l.id, cant: l.cant, opciones: l.opciones }));
                const datos = new FormData(form);
                datos.append('items', JSON.stringify(items));
                const btn = this;
                btn.disabled = true;
                fetch('pedido_web.php', { method: 'POST', body: datos, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(r => {
                        btn.disabled = false;
                        if (!r.ok) { Swal.fire({ icon: 'error', title: r.msg, confirmButtonColor: '#e21e24' }); return; }
                        try { localStorage.setItem('sm_cliente', JSON.stringify({ nombre: datos.get('nombre'), telefono: datos.get('telefono'), direccion: datos.get('direccion') })); } catch (e) { }
                        $('#modalPedido').modal('hide');
                        lineas = [];
                        actualizar();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Pedido #' + r.id + ' recibido!',
                            html: 'Total: <b>S/ ' + esc(r.total) + '</b><br>Ya lo estamos preparando. Te escribiremos al WhatsApp.' +
                                (r.whatsapp ? '<br><br><a class="btn btn-success btn-lg btn-block" target="_blank" rel="noopener" href="' + esc(r.whatsapp) + '"><i class="fab fa-whatsapp"></i> Avisar por WhatsApp</a>' : ''),
                            confirmButtonText: 'Listo',
                            confirmButtonColor: '#6c757d'
                        });
                    })
                    .catch(() => { btn.disabled = false; Swal.fire({ icon: 'error', title: 'Sin conexión. Intenta de nuevo.' }); });
            });
        })();
    </script>
</body>

</html>
