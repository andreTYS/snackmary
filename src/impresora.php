<?php
require_once "includes/app.php";
require_login();
include_once "includes/header.php";
?>
<h1 class="h3 mb-1"><i class="fas fa-print"></i> Impresora de esta tablet</h1>
<p class="text-muted">Esta configuración se guarda en <b>este dispositivo</b>. Si usas varias tablets o celulares, configura cada uno.</p>

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card mb-3">
            <div class="card-header">1. ¿Cómo imprimir?</div>
            <div class="card-body">
                <div class="custom-control custom-radio mb-3">
                    <input type="radio" id="mRawbt" name="metodo" value="rawbt" class="custom-control-input">
                    <label class="custom-control-label" for="mRawbt"><b>App RawBT (recomendado)</b><br>
                        <small class="text-muted">Para tablets y celulares Android. Funciona con casi cualquier impresora térmica Bluetooth (58 u 80 mm), aunque sea de las "clásicas".</small></label>
                </div>
                <div class="custom-control custom-radio mb-3">
                    <input type="radio" id="mBle" name="metodo" value="ble" class="custom-control-input">
                    <label class="custom-control-label" for="mBle"><b>Bluetooth directo</b><br>
                        <small class="text-muted">Sin instalar nada, desde Chrome. Solo para impresoras <b>BLE</b> (Bluetooth de bajo consumo). Si no aparece en la lista, usa RawBT.</small></label>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" id="mPdf" name="metodo" value="pdf" class="custom-control-input">
                    <label class="custom-control-label" for="mPdf"><b>PDF</b><br>
                        <small class="text-muted">Abre el ticket en PDF y se imprime con la impresora configurada en el equipo (útil en una PC).</small></label>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="bloqueBle">
            <div class="card-header">2. Conectar la impresora Bluetooth</div>
            <div class="card-body">
                <p class="mb-2">Estado: <b id="estadoBle">—</b></p>
                <button type="button" class="btn btn-primary" id="btnConectar"><i class="fab fa-bluetooth-b"></i> Buscar y conectar</button>
                <button type="button" class="btn btn-outline-secondary" id="btnDesconectar">Desconectar</button>
                <p class="small text-muted mt-2 mb-0">Enciende la impresora y el Bluetooth de la tablet. Si recargas la página, puede que tengas que volver a tocar <b>Buscar y conectar</b>; por eso Nueva venta ya no se recarga después de cada venta.</p>
                <p class="small text-danger mt-1 mb-0 d-none" id="sinBle">Este navegador no permite Bluetooth directo (por ejemplo, Safari en iPhone/iPad). Usa Chrome en Android o el método RawBT.</p>
            </div>
        </div>

        <div class="card mb-3" id="bloqueRawbt">
            <div class="card-header">2. Preparar la app RawBT (solo la primera vez)</div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>En la tablet, instala <b>RawBT</b> desde Google Play (busca "RawBT impresora térmica").</li>
                    <li>Empareja la impresora en <i>Ajustes de Android → Bluetooth</i> (la clave suele ser <b>0000</b> o <b>1234</b>).</li>
                    <li>Abre RawBT → <i>Ajustes</i> → <i>Conexión</i>: elige <b>Bluetooth</b> y tu impresora.</li>
                    <li>En RawBT, en <i>Ajustes</i>, elige el ancho del papel (58 u 80 mm).</li>
                    <li>Vuelve aquí y toca <b>Imprimir prueba</b>. La primera vez Android pregunta con qué app abrir: elige RawBT y <b>Siempre</b>.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">3. Papel y opciones</div>
            <div class="card-body">
                <div class="form-group">
                    <label for="ancho">Ancho del papel</label>
                    <select id="ancho" class="form-control">
                        <option value="32">58 mm (32 caracteres)</option>
                        <option value="42">80 mm, letra grande (42 caracteres)</option>
                        <option value="48">80 mm (48 caracteres)</option>
                    </select>
                </div>
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="acentos">
                    <label class="custom-control-label" for="acentos">Imprimir tildes y ñ</label>
                    <small class="form-text text-muted">Si en la prueba salen símbolos raros en vez de "á é ñ", apágalo.</small>
                </div>
                <hr>
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" id="autoTicket">
                    <label class="custom-control-label" for="autoTicket">Imprimir el <b>ticket</b> solo, al generar cada venta</label>
                </div>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="autoComanda">
                    <label class="custom-control-label" for="autoComanda">Imprimir la <b>comanda</b> sola, cuando hay comida para cocina</label>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-success btn-lg btn-block mb-2" id="btnGuardar"><i class="fas fa-save"></i> Guardar</button>
        <button type="button" class="btn btn-dark btn-lg btn-block" id="btnPrueba"><i class="fas fa-print"></i> Imprimir prueba</button>
    </div>
</div>
<?php include_once "includes/footer.php"; ?>
<script>
(function () {
    const c = Impresora.config();
    document.querySelector(`input[name="metodo"][value="${c.metodo}"]`).checked = true;
    $('#ancho').val(String(c.ancho));
    $('#acentos').prop('checked', !!c.acentos);
    $('#autoTicket').prop('checked', !!c.autoTicket);
    $('#autoComanda').prop('checked', !!c.autoComanda);
    $('#sinBle').toggleClass('d-none', Impresora.soportaBle());

    const leer = () => ({
        metodo: $('input[name="metodo"]:checked').val(),
        ancho: Number($('#ancho').val()),
        acentos: $('#acentos').is(':checked'),
        autoTicket: $('#autoTicket').is(':checked'),
        autoComanda: $('#autoComanda').is(':checked'),
    });
    function mostrar() {
        const m = $('input[name="metodo"]:checked').val();
        $('#bloqueBle').toggleClass('d-none', m !== 'ble');
        $('#bloqueRawbt').toggleClass('d-none', m !== 'rawbt');
        const e = Impresora.estado();
        $('#estadoBle').text(e.conectada ? 'Conectada a ' + e.nombre : 'Sin conectar').toggleClass('text-success', e.conectada).toggleClass('text-danger', !e.conectada);
    }
    $('input[name="metodo"]').on('change', mostrar);
    setInterval(mostrar, 1500);
    mostrar();

    $('#btnGuardar').on('click', () => { Impresora.guardar(leer()); aviso('success', 'Configuración guardada en esta tablet'); });
    $('#btnConectar').on('click', () => { Impresora.guardar(leer()); Impresora.conectar().then(mostrar); });
    $('#btnDesconectar').on('click', () => { Impresora.desconectar(); setTimeout(mostrar, 300); });
    $('#btnPrueba').on('click', () => { Impresora.guardar(leer()); Impresora.prueba(); });
})();
</script>
