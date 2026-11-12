/*
 * Snack Mary - impresión de tickets y comandas desde la tablet.
 *
 * Métodos (se eligen en "Impresora", y se guardan en ESTE dispositivo):
 *   rawbt : app RawBT de Android. Funciona con cualquier impresora Bluetooth
 *           (clásica o BLE). Recomendado.
 *   ble   : Bluetooth directo desde Chrome (Web Bluetooth). Solo impresoras BLE.
 *   pdf   : abre el PDF y se imprime con la impresora del sistema.
 *
 * Los tickets se generan en el servidor en ESC/POS (imprimir.php); aquí solo se envían.
 */
const Impresora = (function () {
    const CLAVE = 'sm_impresora';
    const CLAVE_BLE = 'sm_impresora_ble';
    const BASE = { metodo: 'pdf', ancho: 32, acentos: true, autoTicket: false, autoComanda: false };
    let cfg = cargar();
    let ble = null;
    let conectada = false;
    let nombre = '';
    // Abre la app RawBT. Se puede reemplazar en pruebas automáticas.
    let navegar = url => { window.location.href = url; };

    function cargar() {
        try { return Object.assign({}, BASE, JSON.parse(localStorage.getItem(CLAVE) || '{}')); } catch (e) { return Object.assign({}, BASE); }
    }
    function guardar(cambios) {
        cfg = Object.assign(cfg, cambios);
        try { localStorage.setItem(CLAVE, JSON.stringify(cfg)); } catch (e) { }
        if (cfg.metodo === 'ble') iniciarBle();
        indicador();
    }
    /** Aviso propio (no usa SweetAlert, para no tapar la ventana de la venta). */
    function avisar(icono, texto, ms) {
        const colores = { success: '#28a745', error: '#dc3545', warning: '#e0a800', info: '#17a2b8' };
        const caja = document.createElement('div');
        caja.setAttribute('role', 'status');
        caja.className = 'aviso-impresora';
        caja.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:20000;max-width:90vw;' +
            'padding:12px 18px;border-radius:12px;color:#fff;font-size:1rem;box-shadow:0 4px 14px rgba(0,0,0,.25);background:' + (colores[icono] || '#343a40');
        caja.textContent = '🖨 ' + texto;
        document.body.appendChild(caja);
        setTimeout(() => caja.remove(), ms || 2500);
    }
    function soportaBle() {
        return !!(navigator.bluetooth && window.WebBluetoothReceiptPrinter);
    }

    function iniciarBle() {
        if (ble || !soportaBle()) return;
        ble = new WebBluetoothReceiptPrinter();
        ble.addEventListener('connected', d => {
            conectada = true;
            nombre = d.name || 'Impresora';
            try { localStorage.setItem(CLAVE_BLE, JSON.stringify({ type: d.type, id: d.id, name: d.name, language: d.language, codepageMapping: d.codepageMapping })); } catch (e) { }
            indicador();
        });
        ble.addEventListener('disconnected', () => { conectada = false; indicador(); });
        // Reconectar sin preguntar (solo en navegadores que ya lo permiten)
        try {
            const ultima = JSON.parse(localStorage.getItem(CLAVE_BLE) || 'null');
            if (ultima) ble.reconnect(ultima);
        } catch (e) { }
    }

    /** Debe llamarse desde un toque del usuario (requisito del navegador). */
    async function conectar() {
        if (!soportaBle()) {
            avisar('error', 'Este navegador no permite Bluetooth directo. Usa Chrome en Android, o el método RawBT.', 4500);
            return;
        }
        iniciarBle();
        await ble.connect();
    }
    function desconectar() {
        if (ble && conectada) ble.disconnect();
    }

    function urlPdf(id, doc) {
        return (doc === 'comanda' ? 'pdf/comanda.php' : 'pdf/generar.php') + '?v=' + encodeURIComponent(id);
    }

    async function obtener(doc, id) {
        const url = `imprimir.php?doc=${doc}&v=${encodeURIComponent(id || 0)}&ancho=${cfg.ancho}&acentos=${cfg.acentos ? 1 : 0}`;
        const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json();
        if (!j.ok) throw new Error(j.msg || 'No se pudo preparar el ticket');
        return j.datos;
    }

    function unir(base64s) {
        const partes = base64s.map(b => Uint8Array.from(atob(b), c => c.charCodeAt(0)));
        const total = new Uint8Array(partes.reduce((n, p) => n + p.length, 0));
        let pos = 0;
        partes.forEach(p => { total.set(p, pos); pos += p.length; });
        return total;
    }
    function aBase64(bytes) {
        let s = '';
        for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(s);
    }

    async function enviar(bytes) {
        if (cfg.metodo === 'rawbt') {
            // La app RawBT recibe los bytes y los imprime por su conexión Bluetooth
            navegar('intent:base64,' + aBase64(bytes) + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;');
            return true;
        }
        if (cfg.metodo === 'ble') {
            if (!conectada) {
                avisar('warning', 'La impresora no está conectada. Toca el botón de la impresora (arriba) para conectarla.', 4000);
                return false;
            }
            await ble.print(bytes);
            return true;
        }
        return false;
    }

    /**
     * Imprime uno o varios documentos de un pedido en un solo envío.
     * docs: 'ticket' | 'comanda' | ['ticket', 'comanda']
     */
    async function imprimir(id, docs) {
        docs = Array.isArray(docs) ? docs : [docs];
        if (cfg.metodo === 'pdf') {
            docs.forEach(d => window.open(urlPdf(id, d), '_blank'));
            return;
        }
        try {
            const datos = await Promise.all(docs.map(d => obtener(d, id)));
            if (await enviar(unir(datos)) && cfg.metodo === 'ble') {
                avisar('success', docs.length > 1 ? 'Enviado a la impresora' : (docs[0] === 'comanda' ? 'Comanda impresa' : 'Ticket impreso'), 1200);
            }
        } catch (e) {
            avisar('error', e.message || 'No se pudo imprimir', 3500);
        }
    }

    async function prueba() {
        if (cfg.metodo === 'pdf') {
            avisar('info', 'Con el método PDF se usa la impresora del sistema: abre cualquier ticket y elige Imprimir.', 4000);
            return;
        }
        try {
            await enviar(unir([await obtener('prueba', 0)]));
        } catch (e) {
            avisar('error', e.message, 3500);
        }
    }

    /** Ícono de impresora en la barra superior: color según el estado. */
    function indicador() {
        const b = document.getElementById('btnImpresora');
        if (!b) return;
        let color = 'text-white-50', titulo = 'Impresora: PDF (impresora del sistema)';
        if (cfg.metodo === 'rawbt') { color = 'text-info'; titulo = 'Impresora: app RawBT'; }
        if (cfg.metodo === 'ble') {
            color = conectada ? 'text-success' : 'text-danger';
            titulo = conectada ? 'Impresora conectada: ' + nombre : 'Impresora Bluetooth desconectada: toca para conectar';
        }
        b.className = 'btn btn-link nav-link ' + color;
        b.title = titulo;
        b.setAttribute('aria-label', titulo);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (cfg.metodo === 'ble') iniciarBle();
        indicador();
        const b = document.getElementById('btnImpresora');
        if (b) b.addEventListener('click', e => {
            if (cfg.metodo === 'ble' && !conectada) { e.preventDefault(); conectar(); }
        });
        // Botones/enlaces con data-imprimir="ticket|comanda" data-id="..."
        document.addEventListener('click', e => {
            const el = e.target.closest('[data-imprimir]');
            if (!el || cfg.metodo === 'pdf') return;   // PDF: el enlace abre normalmente
            e.preventDefault();
            imprimir(el.dataset.id, el.dataset.imprimir);
        });
    });

    return {
        config: () => Object.assign({}, cfg),
        guardar, conectar, desconectar, imprimir, prueba, soportaBle,
        estado: () => ({ conectada, nombre }),
        _navegar: fn => { navegar = fn; },
    };
})();
