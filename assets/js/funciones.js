/* Snack Mary - funciones del panel (optimizado para tablet) */

const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
$.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF } });

const DT_ES = {
    decimal: '', emptyTable: 'No hay datos', info: 'Mostrando _START_ a _END_ de _TOTAL_',
    infoEmpty: 'Mostrando 0 a 0 de 0', infoFiltered: '(filtrado de _MAX_)', lengthMenu: 'Mostrar _MENU_',
    loadingRecords: 'Cargando...', processing: 'Procesando...', search: 'Buscar:', zeroRecords: 'Sin resultados',
    paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
};

function escapeHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function aviso(icono, titulo, ms = 2000) {
    Swal.fire({ position: 'top-end', icon: icono, title: titulo, showConfirmButton: false, timer: ms, toast: true });
}

function errorAjax(xhr) {
    let msg = 'Error de conexión';
    try { msg = JSON.parse(xhr.responseText).msg || msg; } catch (e) { }
    aviso('error', msg, 3000);
}

/* ------------------------- Sonido de aviso ------------------------- */
let audioCtx = null;
function activarSonido() {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    } catch (e) { }
}
// Los navegadores solo permiten sonido después de que el usuario toca la pantalla
document.addEventListener('pointerdown', activarSonido, { once: true });

function sonar(veces = 3) {
    if (!audioCtx) return;
    for (let i = 0; i < veces; i++) {
        const t = audioCtx.currentTime + i * 0.35;
        const osc = audioCtx.createOscillator();
        const vol = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(i % 2 ? 660 : 880, t);
        vol.gain.setValueAtTime(0.0001, t);
        vol.gain.exponentialRampToValueAtTime(0.4, t + 0.02);
        vol.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
        osc.connect(vol).connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + 0.32);
    }
}

/* ------------------------- Al cargar ------------------------- */
document.addEventListener('DOMContentLoaded', function () {
    $('#tbl').DataTable({ language: DT_ES, order: [] });
    $('.abrir-al-cargar').modal('show');

    $(document).on('submit', '.confirmar', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: form.dataset.titulo || '¿Está seguro?',
            text: form.dataset.texto || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e21e24',
            cancelButtonColor: '#6c757d',
            confirmButtonText: form.dataset.boton || 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    // Pantalla completa (ideal en tablet)
    $('#btnPantallaCompleta').on('click', function () {
        if (!document.fullscreenElement) {
            (document.documentElement.requestFullscreen || function () { }).call(document.documentElement);
        } else if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    });

    if (document.getElementById('detalle_venta')) iniciarVentas();

    // Pantallas que se actualizan solas (Pedidos, Mesas, Mis entregas)
    const auto = document.getElementById('auto-refresh');
    if (auto) iniciarSondeo(auto.dataset.sonido || '0');
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => { }));
}

/* ------------------------- Sondeo de pedidos nuevos ------------------------- */
// modo: '0' sin sonido, '1' suena con pedidos nuevos, '2' suena con cualquier cambio
function iniciarSondeo(modo) {
    let cambio = null;
    let ultimo = null;
    function revisar() {
        $.getJSON('ajax.php', { sondeo: 1 }, function (r) {
            if (cambio !== null && r.cambio !== cambio) {
                const suena = modo === '2' || (modo === '1' && r.ultimo > ultimo);
                if (suena) sonar();
                // No recargar si el usuario está en medio de algo
                if (!document.querySelector('.modal.show') && !document.querySelector('.swal2-container') &&
                    !(document.activeElement && ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName))) {
                    setTimeout(() => location.reload(), suena ? 1200 : 0);
                }
            }
            cambio = r.cambio;
            ultimo = r.ultimo;
        });
    }
    revisar();
    setInterval(revisar, 8000);
}

/* ------------------------- Nueva venta ------------------------- */
let productosPOS = [];
let categoriaPOS = 'todas';

function iniciarVentas() {
    const tipoActual = () => $('input[name="tipo"]:checked').val();

    function mostrarBloques() {
        const tipo = tipoActual();
        $('#bloqueMostrador').toggleClass('d-none', tipo !== 'mostrador');
        $('#bloqueMesa').toggleClass('d-none', tipo !== 'mesa');
        $('#bloqueDelivery').toggleClass('d-none', tipo !== 'delivery');
        $('#filaEnvio').toggleClass('d-none', tipo !== 'delivery');
        calcular();
    }
    $('input[name="tipo"]').on('change', mostrarBloques);
    $('#costo_envio').on('input', calcular);
    mostrarBloques();

    // Grilla de productos
    $.getJSON('ajax.php', { grilla: 1 }, function (lista) {
        productosPOS = lista;
        pintarGrilla();
    });
    $('#categorias').on('click', 'button', function () {
        categoriaPOS = this.dataset.cat;
        $('#categorias button').removeClass('btn-dark').addClass('btn-outline-dark');
        $(this).removeClass('btn-outline-dark').addClass('btn-dark');
        pintarGrilla();
    });
    $('#buscarProducto').on('input', pintarGrilla).on('keydown', function (e) {
        // Lector de código de barras: escribe el código y presiona Enter
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = this.value.trim().toLowerCase();
            const exacto = productosPOS.find(p => String(p.codigo).toLowerCase() === q);
            const visibles = filtrarProductos();
            const elegido = exacto || (visibles.length === 1 ? visibles[0] : null);
            if (elegido) {
                agregarProducto(elegido.id, 1);
                this.value = '';
                pintarGrilla();
            }
        }
    });
    $('#grilla').on('click', '.pos-producto', function () {
        agregarProducto(this.dataset.id, 1);
    });

    $('#nom_cliente').autocomplete({
        minLength: 3,
        source: function (request, response) {
            $.getJSON('ajax.php', { q: request.term }, response);
        },
        select: function (event, ui) {
            $('#idcliente').val(ui.item.id);
            $('#nom_cliente').val(ui.item.label);
            $('#tel_cliente').val(ui.item.telefono);
            $('#dir_cliente').val(ui.item.direccion);
            if (!$('#telefono_entrega').val()) $('#telefono_entrega').val(ui.item.telefono);
            if (!$('#direccion_entrega').val()) $('#direccion_entrega').val(ui.item.direccion);
            return false;
        }
    }).on('input', function () {
        if (this.value === '') {
            $('#idcliente').val(1);
            $('#tel_cliente, #dir_cliente').val('');
        }
    });

    $('#btn_generar').on('click', function (e) {
        e.preventDefault();
        if ($('#detalle_venta tr').length === 0) {
            aviso('warning', 'No hay productos en el pedido');
            return;
        }
        const boton = this;
        boton.disabled = true;
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'procesar',
                tipo: tipoActual(),
                cobrar: $('#cobrarAhora').is(':checked') ? '1' : '0',
                id_cliente: $('#idcliente').val(),
                id_mesa: $('#id_mesa').val(),
                direccion_entrega: $('#direccion_entrega').val(),
                telefono_entrega: $('#telefono_entrega').val(),
                costo_envio: $('#costo_envio').val(),
                metodo_pago: $('#metodo_pago').val(),
                nota: $('#nota').val()
            },
            success: function (res) {
                if (!res.ok) {
                    boton.disabled = false;
                    aviso('error', res.msg, 3500);
                    return;
                }
                const titulo = res.agregado ? 'Agregado al pedido #' + res.id_venta : 'Pedido #' + res.id_venta + ' listo';
                let botones = '<div class="d-flex flex-wrap justify-content-center">';
                botones += `<button type="button" class="btn btn-secondary btn-lg m-1" onclick="generarPDF(${res.id_venta})"><i class="fas fa-receipt"></i> Ticket</button>`;
                if (res.cocina) botones += `<button type="button" class="btn btn-warning btn-lg m-1" onclick="comandaPDF(${res.id_venta})"><i class="fas fa-fire"></i> Comanda</button>`;
                if (res.whatsapp) botones += `<a class="btn btn-whatsapp btn-lg m-1" target="_blank" rel="noopener" href="${escapeHtml(res.whatsapp)}"><i class="fab fa-whatsapp"></i> WhatsApp</a>`;
                botones += '</div>';
                Swal.fire({
                    icon: 'success',
                    title: titulo,
                    html: botones,
                    confirmButtonText: 'Nueva venta',
                    confirmButtonColor: '#28a745'
                }).then(() => location.reload());
            },
            error: function (xhr) {
                boton.disabled = false;
                errorAjax(xhr);
            }
        });
    });

    listar();
}

function filtrarProductos() {
    const q = ($('#buscarProducto').val() || '').trim().toLowerCase();
    return productosPOS.filter(p =>
        (categoriaPOS === 'todas' || String(p.categoria) === categoriaPOS) &&
        (q === '' || p.nombre.toLowerCase().includes(q) || String(p.codigo).toLowerCase().includes(q))
    );
}

function pintarGrilla() {
    const lista = filtrarProductos();
    if (!lista.length) {
        $('#grilla').html('<div class="text-muted p-3">No hay productos aquí. Agrégalos en el menú Productos.</div>');
        return;
    }
    $('#grilla').html(lista.map(p => {
        const agotado = p.stock !== null && p.stock <= 0;
        const foto = p.imagen
            ? `<div class="foto" style="background-image:url('${escapeHtml(p.imagen)}')"></div>`
            : `<div class="foto"><i class="fas fa-utensils"></i></div>`;
        const stock = p.stock === null ? '' : `<div class="stock">${agotado ? 'Agotado' : 'Stock: ' + p.stock}</div>`;
        return `<button type="button" class="pos-producto${agotado ? ' agotado' : ''}" data-id="${p.id}">
            ${foto}
            <div class="info"><div class="nombre">${escapeHtml(p.nombre)}</div>
            <div><div class="precio">S/ ${escapeHtml(p.precio)}</div>${stock}</div></div>
        </button>`;
    }).join(''));
}

function listar() {
    $.getJSON('ajax.php', { detalle: 1 }, function (filas) {
        let html = '';
        filas.forEach(row => {
            html += `<tr>
                <td class="pl-3"><div class="font-weight-bold">${escapeHtml(row.descripcion)}</div><small class="text-muted">S/ ${escapeHtml(row.precio_venta)} c/u</small></td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-outline-danger btn-sm" type="button" onclick="cambiarDetalle('restar', ${row.id})" aria-label="Quitar uno"><i class="fas fa-minus"></i></button>
                    <span class="mx-2 font-weight-bold">${row.cantidad}</span>
                    <button class="btn btn-outline-success btn-sm" type="button" onclick="agregarProducto(${row.id_producto}, 1)" aria-label="Agregar uno"><i class="fas fa-plus"></i></button>
                </td>
                <td class="text-right font-weight-bold subtotal pr-3" data-valor="${Number(row.sub_total)}">${Number(row.sub_total).toFixed(2)}</td>
            </tr>`;
        });
        document.querySelector('#detalle_venta').innerHTML = html;
        $('#carritoVacio').toggleClass('d-none', filas.length > 0);
        calcular();
    });
}

function agregarProducto(id, cant) {
    $.ajax({
        url: 'ajax.php', type: 'POST', dataType: 'json',
        data: { action: 'agregar', id: id, cant: cant },
        success: function (res) {
            if (res.ok) listar(); else aviso('error', res.msg, 3000);
        },
        error: errorAjax
    });
}

function cambiarDetalle(accion, id) {
    $.ajax({
        url: 'ajax.php', type: 'POST', dataType: 'json',
        data: { action: accion, id: id },
        success: function (res) {
            if (!res.ok) aviso('error', res.msg);
            listar();
        },
        error: errorAjax
    });
}

function calcular() {
    let productos = 0;
    document.querySelectorAll('#detalle_venta .subtotal').forEach(td => {
        productos += parseFloat(td.dataset.valor) || 0;
    });
    const esDelivery = $('input[name="tipo"]:checked').val() === 'delivery';
    const envio = esDelivery ? (parseFloat($('#costo_envio').val()) || 0) : 0;
    $('#totalProductos').text(productos.toFixed(2));
    $('#totalEnvio').text(envio.toFixed(2));
    $('#totalPagar').text((productos + envio).toFixed(2));
}

function generarPDF(idVenta) {
    window.open('pdf/generar.php?v=' + encodeURIComponent(idVenta), '_blank');
}

function comandaPDF(idVenta) {
    window.open('pdf/comanda.php?v=' + encodeURIComponent(idVenta), '_blank');
}

/* ------------------------- Gráficos del inicio ------------------------- */
if (document.getElementById('sales-chart')) {
    $.post('chart.php', { action: 'sales' }, function (data) {
        const ctx = document.getElementById('sales-chart');
        ctx.height = 150;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.descripcion),
                datasets: [{ label: 'Stock disponible', data: data.map(d => d.existencia), backgroundColor: 'rgba(226,30,36,0.75)' }]
            },
            options: { responsive: true, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
        });
    }, 'json');
}
if (document.getElementById('polarChart')) {
    $.post('chart.php', { action: 'polarChart' }, function (data) {
        const ctx = document.getElementById('polarChart');
        ctx.height = 200;
        new Chart(ctx, {
            type: 'polarArea',
            data: {
                datasets: [{ data: data.map(d => d.cantidad), backgroundColor: ['#e21e24', '#fd7e14', '#28a745', '#ffc107', '#6f42c1'] }],
                labels: data.map(d => d.descripcion)
            },
            options: { legend: { position: 'top' }, responsive: true }
        });
    }, 'json');
}

/* ------------------------- Cambiar contraseña ------------------------- */
function btnCambiar(e) {
    e.preventDefault();
    const actual = document.getElementById('actual').value;
    const nueva = document.getElementById('nueva').value;
    if (actual === '' || nueva === '') {
        aviso('error', 'Los campos están vacíos');
        return;
    }
    $.ajax({
        url: 'ajax.php', type: 'POST', dataType: 'json',
        data: { action: 'cambiar_clave', actual: actual, nueva: nueva },
        success: function (res) {
            aviso(res.ok ? 'success' : 'error', res.msg, 2500);
            if (res.ok) {
                document.getElementById('frmPass').reset();
                $('#nuevo_pass').modal('hide');
            }
        },
        error: errorAjax
    });
}
