<?php
/*
 * Ayuda dentro de la app: guías de uso por rol. Las guías que corresponden a los
 * permisos de quien entra aparecen primero y abiertas.
 */
require_once "includes/app.php";
require_login();

$guias = [
    'cajero' => ['Cajero / mozo: tomar pedidos y cobrar', 'fas fa-cash-register', ['nueva_venta', 'mesas', 'pedidos']],
    'cocina' => ['Cocina: pantalla y comanda', 'fas fa-fire', ['cocina']],
    'repartidor' => ['Repartidor: Mis entregas', 'fas fa-motorcycle', ['repartidor']],
    'caja' => ['Caja: abrir, gastos y cierre', 'fas fa-lock-open', ['caja']],
    'impresora' => ['Impresora Bluetooth', 'fas fa-print', []],
    'productos' => ['Productos, categorías y opciones', 'fas fa-hamburger', ['productos']],
    'carta' => ['Carta para clientes (QR y WhatsApp)', 'fas fa-qrcode', ['pedidos', 'configuracion']],
    'reportes' => ['Reportes y ventas', 'fas fa-chart-bar', ['reportes', 'ventas']],
    'admin' => ['Administrador: primer día, usuarios y seguridad', 'fas fa-user-shield', ['usuarios', 'configuracion']],
    'problemas' => ['Problemas comunes', 'fas fa-life-ring', []],
];
// Primero las guías del rol de la persona
$propias = [];
$otras = [];
foreach ($guias as $clave => $g) {
    $mia = !$g[2] ? false : (bool)array_filter($g[2], 'tiene_permiso');
    if ($mia) {
        $propias[$clave] = $g;
    } else {
        $otras[$clave] = $g;
    }
}
$orden = $propias + $otras;
$abrir = array_key_first($propias) ?? 'cajero';
$enlace = fn(string $url, string $texto) => '<a href="' . e($url) . '">' . e($texto) . '</a>';
include_once "includes/header.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <h1 class="h3 mb-2"><i class="fas fa-question-circle"></i> Ayuda</h1>
    <div class="mb-2 no-imprimir">
        <input type="search" id="buscarAyuda" class="form-control d-inline-block" style="width:260px" placeholder="Buscar en la ayuda...">
        <button type="button" class="btn btn-outline-dark" onclick="$('.collapse').collapse('show'); setTimeout(() => window.print(), 400);"><i class="fas fa-print"></i> Imprimir todo</button>
    </div>
</div>
<p class="text-muted">Toca un tema para abrirlo. Arriba están las guías de tu puesto.</p>

<div class="accordion" id="ayuda">
<?php foreach ($orden as $clave => [$titulo, $icono]) { ?>
    <div class="card mb-2 tema-ayuda" id="tema-<?php echo $clave; ?>">
        <div class="card-header bg-white p-0">
            <button class="btn btn-link btn-block text-left text-dark p-3 h5 mb-0" type="button" data-toggle="collapse" data-target="#c-<?php echo $clave; ?>" aria-expanded="<?php echo $clave === $abrir ? 'true' : 'false'; ?>" aria-controls="c-<?php echo $clave; ?>">
                <i class="<?php echo $icono; ?> mr-2 text-danger"></i><?php echo e($titulo); ?>
                <?php if (isset($propias[$clave])) { ?><span class="badge badge-success ml-2">Tu puesto</span><?php } ?>
            </button>
        </div>
        <div id="c-<?php echo $clave; ?>" class="collapse<?php echo $clave === $abrir ? ' show' : ''; ?>">
            <div class="card-body ayuda-cuerpo">
<?php if ($clave === 'cajero') { ?>
                <p>Todo empieza en <?php echo $enlace('ventas.php', 'Nueva venta'); ?>. Toca la foto del producto para sumarlo; con <b>+</b> y <b>−</b> cambias la cantidad. Arriba puedes filtrar por categoría o buscar por nombre. Con lector de código de barras: escanea y se agrega solo.</p>
                <p>Si el plato tiene opciones (tipo, extras, bebida del combo), se abre una ventana para elegirlas. Lo obligatorio que falte se marca en rojo.</p>
                <h6>Mostrador (el cliente paga al pedir)</h6>
                <ol><li>Deja <b>Mostrador</b> y <b>Cobrar ahora</b> encendido.</li><li>Elige el método de pago.</li><li><b>Generar pedido</b>. Queda cobrado y, si hay comida, aparece en Cocina.</li><li>Si pagará al recoger, apaga <b>Cobrar ahora</b>: quedará pendiente en Pedidos.</li></ol>
                <h6>Mesa (se cobra al final)</h6>
                <ol><li>Elige <b>Mesa</b> y la mesa (o entra por <?php echo $enlace('mesas.php', 'Mesas'); ?> → <b>Tomar pedido</b>).</li><li>Agrega los productos y una nota si hace falta (“sin cebolla”).</li><li><b>Generar pedido</b>: la mesa queda ocupada.</li><li>¿Piden más? <b>Mesas</b> → <b>Agregar</b>: se suma a la misma cuenta y cocina lo ve como NUEVO.</li><li>Para cobrar: <b>Mesas</b> → método de pago → <b>Cobrar</b>. La mesa queda libre.</li></ol>
                <h6>Delivery por teléfono</h6>
                <ol><li>Elige <b>Delivery</b>; escribe 3 letras del cliente para buscarlo (se completan dirección y teléfono).</li><li>Revisa dirección, teléfono y costo de envío.</li><li><b>Generar pedido</b> → <b>WhatsApp</b> para confirmarle al cliente.</li><li>En <?php echo $enlace('pedidos.php', 'Pedidos'); ?>, asígnale un repartidor.</li></ol>
                <h6>Pedidos que llegan por la carta</h6>
                <p>Aparecen solos en <b>Pedidos</b> con borde verde y la etiqueta <b>Web</b>, y suena un aviso. Confírmale al cliente con el botón de WhatsApp. Si es delivery, asigna repartidor; si dice <i>RECOJO EN TIENDA</i>, se cobra cuando lo recojan.</p>
                <h6>Anular</h6>
                <p>Botón rojo ⊘ en la tarjeta del pedido. El stock (y las bebidas de los combos) vuelve al inventario.</p>
<?php } elseif ($clave === 'cocina') { ?>
                <p><?php echo $enlace('cocina.php', 'Cocina'); ?> muestra solo lo que hay que preparar, sin precios, y se actualiza sola. Las bebidas no aparecen. <b>Toca la pantalla una vez</b> al empezar para activar el sonido.</p>
                <ol><li>Llega un pedido: suena y aparece una tarjeta gris.</li><li>Al empezarlo: <b>Preparar</b> (se pone naranja).</li><li>Al terminarlo: <b>¡Listo!</b> (sale de la pantalla).</li><li>Tarjeta <b>roja</b> = lleva 15 minutos o más.</li><li>Mesa que pide más: lo nuevo dice <b>NUEVO</b> y lo anterior sale tachado.</li><li>Debajo de cada plato, en rojo, lo que eligió el cliente (pura papa, con mollejitas, + porción…).</li><li>Botón de impresora: imprime la comanda (solo la última ronda).</li></ol>
<?php } elseif ($clave === 'repartidor') { ?>
                <p>Entra desde tu celular con tu usuario y abre <?php echo $enlace('repartidor.php', 'Mis entregas'); ?>. Ves solo los pedidos que te asignaron, y suena cuando te asignan uno nuevo.</p>
                <ol><li><b>Mapa</b>: abre Google Maps con la dirección (o la ubicación exacta si el cliente la compartió).</li><li><b>Llamar</b> y <b>WhatsApp</b> para avisar “estoy en camino”.</li><li>Al salir: <b>Salí a entregar</b>.</li><li>Al entregar: elige cómo pagó y toca <b>Entregado y cobrado</b>.</li><li>El efectivo cobrado lo entregas al cajero antes del cierre de caja.</li></ol>
<?php } elseif ($clave === 'caja') { ?>
                <table class="table table-sm"><tbody>
                    <tr><th>Al abrir</th><td><?php echo $enlace('caja.php', 'Caja'); ?> → monto inicial (el sencillo) → <b>Abrir caja</b>.</td></tr>
                    <tr><th>Gasto con plata de caja</th><td><b>Registrar gasto o ingreso</b> → Gasto → concepto (“compra de pan”) → monto → <b>Registrar</b>.</td></tr>
                    <tr><th>Entra plata que no es venta</th><td>Lo mismo, eligiendo <b>Ingreso</b>.</td></tr>
                    <tr><th>Al cerrar</th><td>Cuenta billetes y monedas → <b>Efectivo contado</b> → revisa si sobra o falta → <b>Cerrar caja</b>.</td></tr>
                </tbody></table>
                <p><b>Efectivo que debe haber</b> = inicial + ventas en efectivo + ingresos en efectivo − gastos en efectivo. Yape, Plin y tarjeta se revisan aparte (en el celular o el POS). Si sobra o falta, escribe una observación. Las cajas anteriores quedan en el historial y se pueden imprimir.</p>
                <p class="mb-0">Si se cobra con la caja cerrada, la venta se guarda pero no entra al arqueo: abre la caja al empezar el día.</p>
<?php } elseif ($clave === 'impresora') { ?>
                <p>Cada tablet o celular configura su impresora en <?php echo $enlace('impresora.php', 'Impresora'); ?> (ícono 🖨 arriba). Hay tres formas:</p>
                <table class="table table-sm"><thead><tr><th>Método</th><th>Cuándo usarlo</th></tr></thead><tbody>
                    <tr><td><b>App RawBT</b> (recomendado)</td><td>Tablet o celular Android con cualquier impresora térmica Bluetooth de 58 u 80 mm. Instala RawBT desde Google Play, empareja la impresora en Ajustes → Bluetooth (clave 0000 o 1234) y elígela dentro de RawBT.</td></tr>
                    <tr><td><b>Bluetooth directo</b></td><td>Impresoras BLE, desde Chrome, sin instalar nada. Toca <b>Buscar y conectar</b>. Si la impresora no aparece en la lista, usa RawBT.</td></tr>
                    <tr><td><b>PDF</b></td><td>En una PC con impresora instalada.</td></tr>
                </tbody></table>
                <ul><li>Toca <b>Imprimir prueba</b>: si salen símbolos raros en vez de “á é ñ”, apaga <b>Imprimir tildes</b>.</li><li>Elige el ancho: 58 mm (32 caracteres) u 80 mm.</li><li>Puedes activar que el <b>ticket</b> y/o la <b>comanda</b> se impriman solos al generar cada venta.</li><li>El ícono 🖨 de arriba se pone <span class="text-success">verde</span> cuando la impresora Bluetooth está conectada y <span class="text-danger">rojo</span> cuando no; tócalo para reconectar.</li><li>iPhone/iPad no permiten Bluetooth desde el navegador: usa una tablet Android o el método PDF.</li></ul>
<?php } elseif ($clave === 'productos') { ?>
                <p>Lo que cargas en <?php echo $enlace('productos.php', 'Productos'); ?> aparece en Nueva venta y en la carta.</p>
                <table class="table table-sm"><thead><tr><th>Campo</th><th>Qué poner</th></tr></thead><tbody>
                    <tr><td>Código</td><td>Único: el de barras si es envasado, si no uno corto (HAMB-CL).</td></tr>
                    <tr><td>Categoría</td><td>La sección de la carta. En <?php echo $enlace('categorias.php', 'Categorías'); ?> apaga “Se prepara en cocina” para bebidas.</td></tr>
                    <tr><td>Foto</td><td>JPG/PNG/WEBP hasta 6 MB; el sistema la achica sola.</td></tr>
                    <tr><td>Controlar stock</td><td><b>Apagado</b> para lo que se prepara al momento; <b>encendido</b> para bebidas y envasados.</td></tr>
                </tbody></table>
                <h6>Opciones por plato (tipo, extras, bebida del combo)</h6>
                <ol><li>Productos → botón ☰ <b>Opciones</b> del plato.</li><li>Crea grupos: <b>Tipo</b> (debe elegir 1), <b>Extras</b> (puede elegir varias, con máximo), <b>Bebida del combo</b> (debe elegir 1).</li><li>Agrega opciones con su precio extra.</li><li>Bebida del combo: <b>Agregar de una vez todos los productos de Bebidas</b>; cada una queda vinculada a su stock.</li><li>Otro plato con lo mismo: <b>Copiar opciones</b>.</li></ol>
                <p class="mb-0"><b>Reponer stock:</b> Productos → botón azul <b>+</b> → cantidad que llegó.</p>
<?php } elseif ($clave === 'carta') { ?>
                <p>La carta pública está en <b><?php echo e((es_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . '/carta.php'); ?></b>. Compártela por WhatsApp, en estados o con el QR en las mesas.</p>
                <ul><li>El cliente elige, pone su nombre y celular, delivery o recojo (puede compartir su ubicación) y confirma. El pedido entra solo a Pedidos y a Cocina.</li><li>Solo salen productos activos con stock (los que no controlan stock salen siempre).</li><li>En <?php echo $enlace('config.php', 'Configuración'); ?>: número de WhatsApp del negocio, costo de delivery y <b>pausar la carta</b> cuando no se atiende.</li><li>Protección anti-spam: máximo 3 pedidos cada 10 minutos desde el mismo lugar.</li></ul>
<?php } elseif ($clave === 'reportes') { ?>
                <table class="table table-sm"><thead><tr><th>Cuándo</th><th>Qué mirar</th></tr></thead><tbody>
                    <tr><td>Cada noche</td><td>Total del día (<?php echo $enlace('reportes.php', 'Reportes'); ?> → Hoy) y el cierre de caja.</td></tr>
                    <tr><td>Cada semana</td><td>Productos más vendidos, horas fuertes, opciones más pedidas.</td></tr>
                    <tr><td>Cada mes</td><td>Ventas por trabajador, método de pago y local vs. carta web.</td></tr>
                    <tr><td>Para el contador</td><td>Botones verdes de Reportes: se abren en Excel.</td></tr>
                </tbody></table>
                <p class="mb-0">En <?php echo $enlace('lista_ventas.php', 'Ventas'); ?> está el historial pedido por pedido, con ticket, WhatsApp y anulación.</p>
<?php } elseif ($clave === 'admin') { ?>
                <h6>Primer día</h6>
                <ol><li>Cambia la contraseña de <b>admin</b> (el sistema lo exige).</li><li><?php echo $enlace('config.php', 'Configuración'); ?>: datos del negocio, WhatsApp y costo de delivery.</li><li>Categorías → Productos (con fotos) → Opciones de los platos.</li><li><?php echo $enlace('mesas.php', 'Mesas'); ?>.</li><li><?php echo $enlace('usuarios.php', 'Usuarios'); ?>: uno por persona y permisos con la llave.</li><li>Impresora de cada tablet.</li><li>Prueba completa: abre caja, vende, mira cocina, cobra y anula la prueba.</li></ol>
                <h6>Permisos por puesto</h6>
                <table class="table table-sm"><tbody>
                    <tr><th>Cajero / mozo</th><td>Nueva venta, Mesas, Pedidos, Caja, Clientes</td></tr>
                    <tr><th>Cocina</th><td>Pantalla de cocina</td></tr>
                    <tr><th>Repartidor</th><td>Repartidor (Mis entregas)</td></tr>
                    <tr><th>Encargado</th><td>Lo del cajero + Ver ventas + Reportes + Productos</td></tr>
                </tbody></table>
                <h6>Seguridad</h6>
                <ul class="mb-0"><li>Contraseñas de al menos 8 caracteres; las de fábrica o muy fáciles se deben cambiar al ingresar.</li><li>Tras 5 intentos fallidos, ese usuario queda bloqueado 15 minutos.</li><li>Si alguien deja de trabajar, <b>desactívalo</b>: su sesión se cierra de inmediato.</li><li>En <?php echo $enlace('auditoria.php', 'Auditoría'); ?> (solo admin) queda quién ingresó, anuló, cambió precios, permisos o cerró caja.</li><li>Cada persona con su propio usuario: nunca compartan claves.</li></ul>
<?php } elseif ($clave === 'problemas') { ?>
                <table class="table table-sm"><thead><tr><th>Pasa esto</th><th>Haz esto</th></tr></thead><tbody>
                    <tr><td>No suena el aviso</td><td>Toca la pantalla una vez después de abrirla y sube el volumen.</td></tr>
                    <tr><td>No imprime (RawBT)</td><td>Abre RawBT y revisa que la impresora esté elegida y encendida; imprime una prueba desde la app.</td></tr>
                    <tr><td>No imprime (Bluetooth directo)</td><td>Toca el ícono 🖨 rojo para reconectar. Si la impresora no aparece, usa RawBT.</td></tr>
                    <tr><td>Salen símbolos raros en el ticket</td><td>Impresora → apaga “Imprimir tildes”.</td></tr>
                    <tr><td>Un producto no aparece en la carta</td><td>Está desactivado o sin stock. Repón stock o apaga “Controlar stock” si se prepara al momento.</td></tr>
                    <tr><td>Un producto no aparece en Cocina</td><td>Su categoría tiene apagado “Se prepara en cocina”.</td></tr>
                    <tr><td>“Stock insuficiente”</td><td>Quedan menos unidades (puede ser la bebida del combo). Repón en Productos → +.</td></tr>
                    <tr><td>No veo un menú</td><td>Tu usuario no tiene ese permiso; pídeselo al administrador.</td></tr>
                    <tr><td>“Demasiados intentos fallidos”</td><td>Espera 15 minutos o pide al administrador que restablezca tu contraseña.</td></tr>
                    <tr><td>Olvidé mi contraseña</td><td>El administrador la restablece en Usuarios → editar.</td></tr>
                    <tr><td>La tablet no tiene internet</td><td>Aparece “Sin conexión”: revisa el Wi-Fi y toca Reintentar. Sin internet no se registran ventas.</td></tr>
                </tbody></table>
<?php } ?>
            </div>
        </div>
    </div>
<?php } ?>
</div>

<div class="card mt-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
        <div><b>¿Necesitas más ayuda?</b><br><span class="text-muted">Soporte técnico: Moqueguasoft</span></div>
        <?php $soporte = preg_replace('/\D+/', '', (string)getenv('SOPORTE_WHATSAPP')); ?>
        <a class="btn btn-whatsapp mt-2" href="https://wa.me/<?php echo e($soporte); ?>?text=<?php echo rawurlencode('Hola, necesito ayuda con el sistema Snack Mary'); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Escribir a soporte</a>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
<script>
document.getElementById('buscarAyuda').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.tema-ayuda').forEach(t => {
        const coincide = q === '' || t.textContent.toLowerCase().includes(q);
        t.classList.toggle('d-none', !coincide);
        if (q !== '' && coincide) $(t.querySelector('.collapse')).collapse('show');
    });
});
</script>
