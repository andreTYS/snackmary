<?php
require_once "includes/app.php";
require_permiso('cocina', 'pedidos');
include_once "includes/header.php";
?>
<script>document.body.classList.add('cocina-body', 'sb-sidenav-toggled');</script>
<div class="d-flex justify-content-between align-items-center mb-3 text-white">
    <h1 class="h3 mb-0"><i class="fas fa-fire text-warning"></i> Cocina <small class="text-white-50" id="contador"></small></h1>
    <div>
        <span class="text-white-50 mr-2" id="estadoSonido"><i class="fas fa-volume-mute"></i> Toca la pantalla para activar el sonido</span>
        <span class="text-white-50" id="reloj"></span>
    </div>
</div>
<div class="cocina-grid" id="cocinaGrid"></div>
<div id="cocinaVacia" class="text-center text-white-50 py-5 d-none">
    <i class="fas fa-check-circle fa-4x mb-3"></i>
    <h3>Sin pedidos por preparar</h3>
</div>

<?php include_once "includes/footer.php"; ?>
<script>
(function () {
    const TARDE = 15; // minutos para marcar un pedido como atrasado
    let conocidos = null;

    document.addEventListener('pointerdown', function () {
        $('#estadoSonido').html('<i class="fas fa-volume-up"></i> Sonido activo');
    }, { once: true });

    function accion(id, estado) {
        $.ajax({
            url: 'pedido_accion.php', type: 'POST', dataType: 'json',
            data: { id: id, cocina: estado, volver: 'cocina.php' },
            success: function (r) { if (!r.ok) aviso('error', r.msg); cargar(); },
            error: errorAjax
        });
    }
    window.cocinaAccion = accion;

    function pintar(lista) {
        $('#contador').text(lista.length ? '(' + lista.length + ' en cola)' : '');
        $('#cocinaVacia').toggleClass('d-none', lista.length > 0);
        $('#cocinaGrid').html(lista.map(p => {
            let titulo = '#' + p.id + ' · ';
            if (p.tipo === 'mesa') titulo += escapeHtml(p.mesa || 'Mesa');
            else if (p.tipo === 'delivery') titulo += '<i class="fas fa-motorcycle"></i> Delivery';
            else titulo += '<i class="fas fa-shopping-bag"></i> Para llevar';
            if (p.ronda > 1) titulo += ' · Ronda ' + p.ronda;
            const clase = p.estado + (p.minutos >= TARDE ? ' tarde' : '');
            const items = p.items.map(it =>
                `<li class="${it.anterior ? 'anterior' : ''}"><b>${it.cantidad}×</b> ${escapeHtml(it.nombre)}${it.nuevo ? '<span class="etiqueta-nuevo">NUEVO</span>' : ''}${it.opciones ? `<div class="opciones-cocina">${escapeHtml(it.opciones)}</div>` : ''}</li>`
            ).join('');
            const nota = p.nota ? `<div class="nota"><i class="fas fa-sticky-note"></i> ${escapeHtml(p.nota)}</div>` : '';
            const cliente = p.cliente ? `<div class="px-3 pt-2 text-muted"><i class="fas fa-user"></i> ${escapeHtml(p.cliente)}${p.origen === 'web' ? ' · <span class="badge badge-success">Web</span>' : ''}</div>` : '';
            const boton = p.estado === 'pendiente'
                ? `<button class="btn btn-warning" onclick="cocinaAccion(${p.id}, 'preparando')"><i class="fas fa-fire"></i> Preparar</button>`
                : `<button class="btn btn-success" onclick="cocinaAccion(${p.id}, 'listo')"><i class="fas fa-check"></i> ¡Listo!</button>`;
            return `<div class="cocina-ticket ${clase}">
                <header><span>${titulo}</span><span><i class="far fa-clock"></i> ${p.minutos} min</span></header>
                ${cliente}
                <ul>${items}</ul>
                ${nota}
                <div class="acciones">
                    <a class="btn btn-light" href="pdf/comanda.php?v=${p.id}" target="_blank" title="Imprimir comanda" data-imprimir="comanda" data-id="${p.id}" style="flex:0 0 72px"><i class="fas fa-print"></i></a>
                    ${boton}
                </div>
            </div>`;
        }).join(''));
    }

    function cargar() {
        $.getJSON('ajax.php', { cocina: 1 }, function (lista) {
            const ids = lista.map(p => p.id + ':' + p.ronda);
            if (conocidos !== null && ids.some(i => !conocidos.includes(i))) sonar(4);
            conocidos = ids;
            pintar(lista);
        });
    }
    function reloj() {
        $('#reloj').text(new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }));
    }
    cargar();
    reloj();
    setInterval(cargar, 6000);
    setInterval(reloj, 30000);
})();
</script>
