/* Snack Mary - funciones del panel */

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
    Swal.fire({ position: 'top-end', icon: icono, title: titulo, showConfirmButton: false, timer: ms });
}

function errorAjax(xhr) {
    let msg = 'Error de conexión';
    try { msg = JSON.parse(xhr.responseText).msg || msg; } catch (e) { }
    aviso('error', msg, 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    $('#tbl').DataTable({ language: DT_ES, order: [] });
    $('.abrir-al-cargar').modal('show');

    $('.confirmar').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: form.dataset.titulo || '¿Está seguro?',
            text: form.dataset.texto || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: form.dataset.boton || 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    if (document.getElementById('detalle_venta')) {
        iniciarVentas();
    }
    if (document.getElementById('auto-refresh')) {
        // Pantalla de pedidos: se actualiza sola cada 30 s si no hay un modal abierto
        setInterval(() => {
            if (!document.querySelector('.modal.show') && !document.querySelector('.swal2-container')) {
                location.reload();
            }
        }, 30000);
    }
});

/* ------------------------- Nueva venta ------------------------- */
function iniciarVentas() {
    const tipoActual = () => $('input[name="tipo"]:checked').val();

    function mostrarBloques() {
        const tipo = tipoActual();
        $('#bloqueMesa').toggleClass('d-none', tipo !== 'mesa');
        $('#bloqueDelivery').toggleClass('d-none', tipo !== 'delivery');
        $('#filaEnvio').toggleClass('d-none', tipo !== 'delivery');
        calcular();
    }
    $('input[name="tipo"]').on('change', mostrarBloques);
    $('#costo_envio').on('input', calcular);
    mostrarBloques();

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

    $('#producto').autocomplete({
        minLength: 2,
        source: function (request, response) {
            $.getJSON('ajax.php', { pro: request.term }, response);
        },
        select: function (event, ui) {
            agregarProducto(ui.item.id, 1);
            $('#producto').val('');
            return false;
        }
    });

    $('#btn_generar').on('click', function (e) {
        e.preventDefault();
        if ($('#detalle_venta tr').length === 0) {
            aviso('warning', 'No hay productos para generar la venta');
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
                const titulo = res.agregado ? 'Productos agregados al pedido #' + res.id_venta : 'Pedido #' + res.id_venta + ' generado';
                Swal.fire({
                    icon: 'success',
                    title: titulo,
                    showDenyButton: !!res.whatsapp,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> Ticket',
                    denyButtonText: '<i class="fab fa-whatsapp"></i> WhatsApp',
                    cancelButtonText: 'Cerrar',
                    denyButtonColor: '#25D366'
                }).then((r) => {
                    if (r.isConfirmed) generarPDF(res.id_venta);
                    if (r.isDenied) window.open(res.whatsapp, '_blank');
                    location.reload();
                });
            },
            error: function (xhr) {
                boton.disabled = false;
                errorAjax(xhr);
            }
        });
    });

    listar();
}

function listar() {
    $.getJSON('ajax.php', { detalle: 1 }, function (filas) {
        let html = '';
        filas.forEach(row => {
            html += `<tr>
                <td>${escapeHtml(row.descripcion)}</td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-outline-danger btn-sm" type="button" onclick="cambiarDetalle('restar', ${row.id})"><i class="fas fa-minus"></i></button>
                    <span class="mx-2 font-weight-bold">${row.cantidad}</span>
                    <button class="btn btn-outline-success btn-sm" type="button" onclick="agregarProducto(${row.id_producto}, 1)"><i class="fas fa-plus"></i></button>
                </td>
                <td class="text-right">${escapeHtml(row.precio_venta)}</td>
                <td class="text-right subtotal" data-valor="${Number(row.sub_total)}">${Number(row.sub_total).toFixed(2)}</td>
                <td><button class="btn btn-danger btn-sm" type="button" onclick="cambiarDetalle('quitar', ${row.id})"><i class="fas fa-trash-alt"></i></button></td>
            </tr>`;
        });
        document.querySelector('#detalle_venta').innerHTML = html;
        calcular();
    });
}

function agregarProducto(id, cant) {
    $.ajax({
        url: 'ajax.php', type: 'POST', dataType: 'json',
        data: { action: 'agregar', id: id, cant: cant },
        success: function (res) {
            aviso(res.ok ? 'success' : 'error', res.msg, res.ok ? 1000 : 3000);
            if (res.ok) listar();
            $('#producto').focus();
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

/* ------------------------- Gráficos del inicio ------------------------- */
if (document.getElementById('sales-chart')) {
    $.post('chart.php', { action: 'sales' }, function (data) {
        const ctx = document.getElementById('sales-chart');
        ctx.height = 150;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.descripcion),
                datasets: [{
                    label: 'Stock disponible',
                    data: data.map(d => d.existencia),
                    backgroundColor: 'rgba(220,53,69,0.75)'
                }]
            },
            options: {
                responsive: true,
                legend: { display: false },
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] }
            }
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
                datasets: [{
                    data: data.map(d => d.cantidad),
                    backgroundColor: ['#007bff', '#dc3545', '#28a745', '#ffc107', '#6f42c1']
                }],
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
