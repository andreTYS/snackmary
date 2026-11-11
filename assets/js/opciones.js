/*
 * Snack Mary - selector de opciones de un plato (tipo, extras, bebida del combo).
 * Se usa en Nueva venta y en la carta pública. Requiere jQuery + Bootstrap 4.
 *
 * elegirOpciones(producto, alAceptar)
 *   producto = {id, nombre, precio, grupos: [{id, nombre, minimo, maximo, opciones: [{id, nombre, precio, agotado}]}]}
 *   alAceptar(ids, texto, precioUnitario, cantidad)
 */
(function () {
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const soles = n => 'S/ ' + Number(n).toFixed(2);

    function asegurarModal() {
        if (document.getElementById('modalOpciones')) return;
        $('body').append(`
<div class="modal fade" id="modalOpciones" tabindex="-1" role="dialog" aria-labelledby="opcTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="opcTitulo"></h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="opcCuerpo"></div>
      <div class="modal-footer d-flex justify-content-between">
        <div class="d-flex align-items-center">
          <button type="button" class="btn btn-outline-danger" id="opcMenos" aria-label="Uno menos"><i class="fas fa-minus"></i></button>
          <span class="mx-3 h5 mb-0" id="opcCant">1</span>
          <button type="button" class="btn btn-outline-success" id="opcMas" aria-label="Uno más"><i class="fas fa-plus"></i></button>
        </div>
        <button type="button" class="btn btn-success btn-lg" id="opcAceptar">Agregar · <span id="opcTotal"></span></button>
      </div>
    </div>
  </div>
</div>`);
        if (!document.getElementById('estiloOpciones')) {
            $('head').append(`<style id="estiloOpciones">
.opc-grupo { margin-bottom: 1.25rem; }
.opc-grupo h6 { font-weight: 700; margin-bottom: .5rem; }
.opc-grupo h6 small { font-weight: 400; color: #6c757d; }
.opc-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: .5rem; }
.opc-boton { border: 2px solid #dee2e6; border-radius: 12px; background: #fff; padding: .75rem; text-align: left; min-height: 56px; display: flex; justify-content: space-between; align-items: center; font-size: 1rem; cursor: pointer; }
.opc-boton .precio { color: #6c757d; font-size: .9rem; white-space: nowrap; margin-left: .5rem; }
.opc-boton.elegida { border-color: #e21e24; background: rgba(226,30,36,.08); font-weight: 600; }
.opc-boton > span:first-child { text-align: left; }
.opc-boton.elegida > span:first-child::before { content: '\\2713  '; color: #e21e24; }
.opc-boton:disabled { opacity: .45; cursor: not-allowed; }
.opc-falta h6 { color: #e21e24; }
</style>`);
        }
    }

    window.elegirOpciones = function (producto, alAceptar) {
        asegurarModal();
        const elegidas = {}; // id_grupo -> [ids]
        let cantidad = 1;
        producto.grupos.forEach(g => { elegidas[g.id] = []; });

        function precioUnitario() {
            let p = Number(producto.precio);
            producto.grupos.forEach(g => g.opciones.forEach(o => { if (elegidas[g.id].includes(o.id)) p += Number(o.precio); }));
            return p;
        }
        function faltantes() {
            return producto.grupos.filter(g => elegidas[g.id].length < g.minimo);
        }
        function pintar() {
            $('#opcTitulo').text(producto.nombre);
            $('#opcCuerpo').html(producto.grupos.map(g => {
                const regla = g.minimo >= 1 ? (g.maximo > 1 ? `elige de ${g.minimo} a ${g.maximo}` : 'elige 1') : (g.maximo > 1 ? `opcional, hasta ${g.maximo}` : 'opcional');
                return `<div class="opc-grupo" data-grupo="${g.id}"><h6>${esc(g.nombre)} <small>(${regla})</small></h6><div class="opc-lista">` +
                    g.opciones.map(o => `<button type="button" class="opc-boton${elegidas[g.id].includes(o.id) ? ' elegida' : ''}" data-grupo="${g.id}" data-opcion="${o.id}"${o.agotado ? ' disabled' : ''}>
                        <span>${esc(o.nombre)}${o.agotado ? ' (agotado)' : ''}</span>${Number(o.precio) > 0 ? `<span class="precio">+ ${soles(o.precio)}</span>` : ''}</button>`).join('') +
                    '</div></div>';
            }).join(''));
            $('#opcCant').text(cantidad);
            $('#opcTotal').text(soles(precioUnitario() * cantidad));
        }

        $('#opcCuerpo').off('click').on('click', '.opc-boton', function () {
            const g = producto.grupos.find(x => x.id === Number(this.dataset.grupo));
            const id = Number(this.dataset.opcion);
            const lista = elegidas[g.id];
            if (lista.includes(id)) {
                lista.splice(lista.indexOf(id), 1);
            } else if (g.maximo === 1) {
                lista.length = 0;
                lista.push(id);
            } else if (lista.length < g.maximo) {
                lista.push(id);
            }
            pintar();
        });
        $('#opcMas').off('click').on('click', () => { cantidad = Math.min(50, cantidad + 1); pintar(); });
        $('#opcMenos').off('click').on('click', () => { cantidad = Math.max(1, cantidad - 1); pintar(); });
        $('#opcAceptar').off('click').on('click', () => {
            const falta = faltantes();
            $('.opc-grupo').removeClass('opc-falta');
            if (falta.length) {
                falta.forEach(g => $(`.opc-grupo[data-grupo="${g.id}"]`).addClass('opc-falta'));
                const el = document.querySelector('.opc-falta');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            const ids = [], nombres = [];
            producto.grupos.forEach(g => g.opciones.forEach(o => {
                if (elegidas[g.id].includes(o.id)) { ids.push(o.id); nombres.push(o.nombre); }
            }));
            $('#modalOpciones').modal('hide');
            alAceptar(ids, nombres.join(', '), precioUnitario(), cantidad);
        });
        pintar();
        $('#modalOpciones').modal('show');
    };
})();
