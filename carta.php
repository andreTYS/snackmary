<?php
/*
 * Carta pública: el cliente arma su pedido y lo envía por WhatsApp al negocio.
 * No requiere iniciar sesión y no escribe nada en la base de datos.
 */
require_once 'conexion.php';

$negocio = $conexion->query('SELECT nombre, telefono, direccion, whatsapp, codigo_pais FROM configuracion ORDER BY id LIMIT 1')->fetch_assoc() ?: [];
$productos = $conexion->query('SELECT codproducto, descripcion, precio FROM producto WHERE estado = 1 AND existencia > 0 ORDER BY descripcion')->fetch_all(MYSQLI_ASSOC);

$numero = preg_replace('/\D+/', '', $negocio['whatsapp'] ?: ($negocio['telefono'] ?? ''));
$pais = preg_replace('/\D+/', '', $negocio['codigo_pais'] ?? '51') ?: '51';
if ($numero !== '' && strlen($numero) <= 9) {
    $numero = $pais . ltrim($numero, '0');
}
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$nombreNegocio = $negocio['nombre'] ?? 'Snack Mary';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?php echo $h($nombreNegocio); ?> - Carta</title>
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link rel="icon" href="assets/img/logo.png">
    <script src="assets/js/all.min.js"></script>
    <style>
        body { background: #f5f5f5; padding-bottom: 90px; }
        .barra-pedido { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; }
        .btn-whatsapp { background: #25D366; color: #fff; }
        .btn-whatsapp:hover { background: #1ebe57; color: #fff; }
        .cantidad { min-width: 2rem; display: inline-block; text-align: center; }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <span class="navbar-brand"><img src="assets/img/logo.png" width="30" height="30" class="rounded mr-2" alt=""><?php echo $h($nombreNegocio); ?></span>
        <a href="index.php" class="text-white-50 small">Personal</a>
    </nav>
    <div class="container py-3">
        <h1 class="h4">Nuestra carta</h1>
        <p class="text-muted small mb-3"><?php echo $h($negocio['direccion'] ?? ''); ?></p>
        <?php if ($numero === '') { ?>
            <div class="alert alert-warning">Los pedidos por WhatsApp aún no están configurados.</div>
        <?php } ?>
        <input type="search" id="buscar" class="form-control mb-3" placeholder="Buscar producto...">
        <div class="list-group mb-4" id="lista">
            <?php foreach ($productos as $p) { ?>
                <div class="list-group-item d-flex justify-content-between align-items-center producto" data-id="<?php echo (int)$p['codproducto']; ?>" data-nombre="<?php echo $h($p['descripcion']); ?>" data-precio="<?php echo (float)$p['precio']; ?>">
                    <div>
                        <div class="font-weight-bold"><?php echo $h($p['descripcion']); ?></div>
                        <div class="text-success">S/ <?php echo number_format((float)$p['precio'], 2); ?></div>
                    </div>
                    <div class="text-nowrap">
                        <button class="btn btn-outline-danger btn-sm menos" type="button" aria-label="Quitar"><i class="fas fa-minus"></i></button>
                        <span class="cantidad font-weight-bold">0</span>
                        <button class="btn btn-outline-success btn-sm mas" type="button" aria-label="Agregar"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            <?php } ?>
            <?php if (!$productos) { ?>
                <div class="list-group-item text-muted">No hay productos disponibles por ahora.</div>
            <?php } ?>
        </div>

        <div class="card mb-4" id="datosCliente">
            <div class="card-header">Tus datos</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" class="form-control" maxlength="80" autocomplete="name">
                </div>
                <div class="form-group">
                    <label>Entrega</label><br>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="entregaDelivery" name="entrega" value="Delivery" class="custom-control-input" checked>
                        <label class="custom-control-label" for="entregaDelivery">Delivery</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="entregaRecojo" name="entrega" value="Recojo en tienda" class="custom-control-input">
                        <label class="custom-control-label" for="entregaRecojo">Recojo en tienda</label>
                    </div>
                </div>
                <div class="form-group" id="grupoDireccion">
                    <label for="direccion">Dirección y referencia</label>
                    <input type="text" id="direccion" class="form-control" maxlength="200" autocomplete="street-address">
                </div>
                <div class="form-group">
                    <label for="pago">Pago</label>
                    <select id="pago" class="form-control">
                        <option>Efectivo</option>
                        <option>Yape</option>
                        <option>Plin</option>
                        <option>Tarjeta</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="nota">Nota (opcional)</label>
                    <input type="text" id="nota" class="form-control" maxlength="200" placeholder="Ej: sin ají, con sencillo de S/ 50">
                </div>
            </div>
        </div>
    </div>

    <div class="barra-pedido bg-white border-top p-2">
        <div class="container d-flex justify-content-between align-items-center">
            <div><small class="text-muted">Total</small><div class="h5 mb-0">S/ <span id="total">0.00</span></div></div>
            <button class="btn btn-whatsapp btn-lg" id="enviar" type="button" <?php echo $numero === '' ? 'disabled' : ''; ?>><i class="fab fa-whatsapp"></i> Pedir por WhatsApp</button>
        </div>
    </div>

    <script>
        (function () {
            const numero = <?php echo json_encode($numero, JSON_HEX_TAG); ?>;
            const negocio = <?php echo json_encode($nombreNegocio, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
            const carrito = {};

            function actualizar() {
                let total = 0;
                document.querySelectorAll('.producto').forEach(function (el) {
                    const cant = carrito[el.dataset.id] || 0;
                    el.querySelector('.cantidad').textContent = cant;
                    total += cant * parseFloat(el.dataset.precio);
                });
                document.getElementById('total').textContent = total.toFixed(2);
                return total;
            }

            document.querySelectorAll('.producto').forEach(function (el) {
                el.querySelector('.mas').addEventListener('click', function () {
                    carrito[el.dataset.id] = (carrito[el.dataset.id] || 0) + 1;
                    actualizar();
                });
                el.querySelector('.menos').addEventListener('click', function () {
                    carrito[el.dataset.id] = Math.max(0, (carrito[el.dataset.id] || 0) - 1);
                    actualizar();
                });
            });

            document.getElementById('buscar').addEventListener('input', function () {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.producto').forEach(function (el) {
                    el.classList.toggle('d-none', q !== '' && el.dataset.nombre.toLowerCase().indexOf(q) === -1);
                });
            });

            document.querySelectorAll('input[name="entrega"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    document.getElementById('grupoDireccion').classList.toggle('d-none', this.value !== 'Delivery');
                });
            });

            document.getElementById('enviar').addEventListener('click', function () {
                const total = actualizar();
                const nombre = document.getElementById('nombre').value.trim();
                const entrega = document.querySelector('input[name="entrega"]:checked').value;
                const direccion = document.getElementById('direccion').value.trim();
                if (total <= 0) { alert('Agrega al menos un producto.'); return; }
                if (nombre === '') { alert('Escribe tu nombre.'); document.getElementById('nombre').focus(); return; }
                if (entrega === 'Delivery' && direccion === '') { alert('Escribe tu dirección.'); document.getElementById('direccion').focus(); return; }

                const lineas = ['Hola ' + negocio + ', quiero hacer un pedido:', ''];
                document.querySelectorAll('.producto').forEach(function (el) {
                    const cant = carrito[el.dataset.id] || 0;
                    if (cant > 0) {
                        lineas.push(cant + ' x ' + el.dataset.nombre + ' = S/ ' + (cant * parseFloat(el.dataset.precio)).toFixed(2));
                    }
                });
                lineas.push('', '*Total: S/ ' + total.toFixed(2) + '*' + (entrega === 'Delivery' ? ' (+ envío)' : ''), '');
                lineas.push('Nombre: ' + nombre);
                lineas.push('Entrega: ' + entrega);
                if (entrega === 'Delivery') lineas.push('Dirección: ' + direccion);
                lineas.push('Pago: ' + document.getElementById('pago').value);
                const nota = document.getElementById('nota').value.trim();
                if (nota) lineas.push('Nota: ' + nota);

                window.open('https://wa.me/' + numero + '?text=' + encodeURIComponent(lineas.join('\n')), '_blank');
            });
        })();
    </script>
</body>

</html>
