<?php
/*
 * Generador de tickets ESC/POS para impresoras térmicas (58 mm = 32 columnas,
 * 80 mm = 48 columnas). Produce los bytes; la tablet los envía a la impresora
 * por Bluetooth (directo o con la app RawBT).
 *
 * Texto con tildes y ñ: se usa la página de códigos PC850 (ESC t 2), que la
 * mayoría de impresoras chinas soporta. Si una impresora imprime símbolos raros,
 * se configura "sin tildes" y el texto se envía en ASCII.
 */
final class EscPos
{
    private string $buf = '';
    private int $cols;
    private bool $acentos;

    public function __construct(int $columnas = 32, bool $acentos = true)
    {
        $this->cols = in_array($columnas, [32, 42, 48], true) ? $columnas : 32;
        $this->acentos = $acentos;
        $this->buf .= "\x1B\x40";                          // ESC @  reiniciar
        if ($acentos) {
            $this->buf .= "\x1B\x74\x02";                  // ESC t 2  PC850 (español)
        }
    }

    public function columnas(): int
    {
        return $this->cols;
    }

    /** Convierte UTF-8 a lo que entiende la impresora. */
    private function cod(string $texto): string
    {
        $texto = str_replace(["\r", "\t"], ['', ' '], $texto);
        if ($this->acentos) {
            $r = @iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $texto);
            if ($r !== false) {
                return $r;
            }
        }
        $r = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return $r === false ? preg_replace('/[^\x20-\x7E\n]/', '?', $texto) : $r;
    }

    /** Largo en caracteres (no en bytes). */
    private function largo(string $t): int
    {
        return mb_strlen($t, 'UTF-8');
    }

    public function centro(): self { $this->buf .= "\x1B\x61\x01"; return $this; }
    public function izquierda(): self { $this->buf .= "\x1B\x61\x00"; return $this; }
    public function negrita(bool $si = true): self { $this->buf .= "\x1B\x45" . ($si ? "\x01" : "\x00"); return $this; }

    /** Tamaño: 1 = normal, 2 = doble alto y ancho. */
    public function tamano(int $n = 1): self
    {
        $n = max(1, min(2, $n));
        $this->buf .= "\x1D\x21" . chr((($n - 1) << 4) | ($n - 1));
        return $this;
    }

    public function texto(string $t): self { $this->buf .= $this->cod($t); return $this; }
    public function linea(string $t = ''): self { $this->buf .= $this->cod($t) . "\n"; return $this; }
    public function avanzar(int $n = 1): self { $this->buf .= str_repeat("\n", max(0, $n)); return $this; }
    public function separador(string $c = '-'): self { return $this->linea(str_repeat($c, $this->cols)); }

    /** Parte un texto en líneas de como máximo $ancho caracteres. */
    public function partir(string $t, int $ancho): array
    {
        $lineas = [];
        foreach (preg_split('/\n/', $t) as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/', trim($parrafo)) as $palabra) {
                while ($this->largo($palabra) > $ancho) {
                    if ($actual !== '') { $lineas[] = $actual; $actual = ''; }
                    $lineas[] = mb_substr($palabra, 0, $ancho);
                    $palabra = mb_substr($palabra, $ancho);
                }
                if ($actual === '') {
                    $actual = $palabra;
                } elseif ($this->largo($actual) + 1 + $this->largo($palabra) <= $ancho) {
                    $actual .= ' ' . $palabra;
                } else {
                    $lineas[] = $actual;
                    $actual = $palabra;
                }
            }
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /** Texto a la izquierda y monto a la derecha en la misma línea. */
    public function dosColumnas(string $izq, string $der): self
    {
        $anchoIzq = $this->cols - $this->largo($der) - 1;
        $lineas = $this->partir($izq, max(8, $anchoIzq));
        foreach ($lineas as $i => $l) {
            if ($i === count($lineas) - 1) {
                $this->linea($l . str_repeat(' ', max(1, $this->cols - $this->largo($l) - $this->largo($der))) . $der);
            } else {
                $this->linea($l);
            }
        }
        return $this;
    }

    /** Párrafo partido al ancho del papel, con sangría opcional. */
    public function parrafo(string $t, int $sangria = 0): self
    {
        foreach ($this->partir($t, $this->cols - $sangria) as $l) {
            $this->linea(str_repeat(' ', $sangria) . $l);
        }
        return $this;
    }

    /** Avanza el papel y corta (si la impresora tiene cortador). */
    public function cortar(): self
    {
        $this->buf .= "\n\n\n\x1D\x56\x42\x00";            // GS V 66 0  corte parcial
        return $this;
    }

    public function bytes(): string
    {
        return $this->buf;
    }
}

/** Ticket del cliente (o comanda de cocina sin precios). */
function escpos_pedido(array $p, string $doc, int $cols, bool $acentos): string
{
    $neg = config_negocio();
    $e = new EscPos($cols, $acentos);
    $donde = $p['tipo'] === 'mesa' ? ($p['mesa'] ?: 'Mesa') : ($p['tipo'] === 'delivery' ? 'DELIVERY' : 'PARA LLEVAR');

    if ($doc === 'comanda') {
        $items = db_all(
            'SELECT d.cantidad, d.ronda, d.opciones_txt, pr.descripcion FROM detalle_venta d
             INNER JOIN producto pr ON pr.codproducto = d.id_producto
             LEFT JOIN categorias c ON c.id = pr.id_categoria
             WHERE d.id_venta = ? AND d.ronda = ? AND COALESCE(c.cocina, 1) = 1 ORDER BY d.id',
            [(int)$p['id'], (int)$p['ultima_ronda']]
        );
        $e->centro()->negrita()->tamano(2)->linea('COMANDA #' . $p['id'])->tamano(1);
        $e->linea($donde . ($p['ultima_ronda'] > 1 ? ' - Ronda ' . $p['ultima_ronda'] : ''))->negrita(false);
        $e->linea(date('d/m/Y H:i') . ($p['vendedor'] ? ' - ' . $p['vendedor'] : ''));
        if ((int)$p['id_cliente'] !== 1 && $p['cliente']) {
            $e->linea('Cliente: ' . $p['cliente']);
        }
        $e->izquierda()->separador('=');
        if (!$items) {
            $e->linea('Sin productos para cocina.');
        }
        foreach ($items as $it) {
            $e->negrita()->tamano(2);
            // En doble ancho caben la mitad de columnas
            foreach ($e->partir((int)$it['cantidad'] . 'x ' . $it['descripcion'], intdiv($cols, 2)) as $l) {
                $e->linea($l);
            }
            $e->tamano(1)->negrita(false);
            if ($it['opciones_txt'] !== '') {
                $e->parrafo('> ' . $it['opciones_txt'], 2);
            }
            $e->separador('-');
        }
        if ($p['nota']) {
            $e->negrita()->parrafo('NOTA: ' . $p['nota'])->negrita(false);
        }
        return $e->cortar()->bytes();
    }

    // Ticket del cliente
    $e->centro()->negrita()->tamano(2);
    foreach ($e->partir($neg['nombre'] ?? 'Snack Mary', intdiv($cols, 2)) as $l) {
        $e->linea($l);
    }
    $e->tamano(1)->negrita(false);
    if (!empty($neg['direccion'])) {
        $e->parrafo($neg['direccion']);
    }
    if (!empty($neg['telefono'])) {
        $e->linea('Tel: ' . $neg['telefono']);
    }
    $e->separador('=');
    $e->negrita()->linea('Pedido #' . $p['id'] . ' - ' . $donde)->negrita(false);
    $e->izquierda();
    $e->linea('Fecha: ' . date('d/m/Y H:i', strtotime($p['fecha'])));
    if ((int)$p['id_cliente'] !== 1 && $p['cliente']) {
        $e->parrafo('Cliente: ' . $p['cliente']);
    }
    if ($p['tipo'] === 'delivery') {
        $tel = telefono_pedido($p);
        if ($tel) {
            $e->linea('Teléfono: ' . $tel);
        }
        if ($p['direccion_entrega']) {
            $e->parrafo('Dirección: ' . $p['direccion_entrega']);
        }
    }
    $e->linea('Atendió: ' . ($p['vendedor'] ?? ($p['origen'] === 'web' ? 'Carta web' : '')));
    $e->separador();
    $subtotal = 0.0;
    foreach ($p['items'] as $it) {
        $importe = $it['cantidad'] * $it['precio'];
        $subtotal += $importe;
        $nombre = preg_replace('/ \(.*\)$/', '', $it['descripcion']);
        $e->dosColumnas((int)$it['cantidad'] . ' x ' . $nombre, dinero($importe));
        if (!empty($it['opciones_txt'])) {
            $e->parrafo($it['opciones_txt'], 4);
        }
        if ((int)$it['cantidad'] > 1) {
            $e->linea('    S/ ' . dinero($it['precio']) . ' c/u');
        }
    }
    $e->separador();
    if ((float)$p['costo_envio'] > 0) {
        $e->dosColumnas('Subtotal', dinero($subtotal));
        $e->dosColumnas('Envío', dinero($p['costo_envio']));
    }
    $e->negrita()->tamano(2);
    $tot = 'S/ ' . dinero($p['total']);
    $anchoGrande = intdiv($cols, 2);
    $etq = 'TOTAL';
    $e->linea($etq . str_repeat(' ', max(1, $anchoGrande - mb_strlen($etq) - mb_strlen($tot))) . $tot);
    $e->tamano(1)->negrita(false);
    $e->linea('Pago: ' . (METODOS_PAGO[$p['metodo_pago']] ?? $p['metodo_pago']) . ' - ' . (ESTADOS_PEDIDO[$p['estado']][0] ?? $p['estado']));
    if ($p['estado'] === 'anulado') {
        $e->centro()->negrita()->tamano(2)->linea('ANULADO')->tamano(1)->negrita(false)->izquierda();
    }
    if ($p['nota']) {
        $e->parrafo('Nota: ' . $p['nota']);
    }
    $e->separador('=');
    $e->centro()->linea('¡Gracias por su preferencia!')->izquierda();
    return $e->cortar()->bytes();
}

/** Página de prueba para comprobar la impresora y las tildes. */
function escpos_prueba(int $cols, bool $acentos): string
{
    $e = new EscPos($cols, $acentos);
    $e->centro()->negrita()->tamano(2)->linea('PRUEBA')->tamano(1)->linea(config_negocio()['nombre'] ?? 'Snack Mary')->negrita(false);
    $e->linea(date('d/m/Y H:i'))->izquierda()->separador('=');
    $e->linea('Papel: ' . ($cols === 32 ? '58 mm (32 col.)' : ($cols === 48 ? '80 mm (48 col.)' : $cols . ' col.')));
    $e->linea('Tildes: ' . ($acentos ? 'si (PC850)' : 'no (ASCII)'));
    $e->separador();
    $e->linea('Acentos: á é í ó ú ñ Ñ ¿ ¡');
    $e->dosColumnas('2 x Salchipapa con mollejitas', '32.00');
    $e->dosColumnas('1 x Inca Kola 500 ml', '3.50');
    $e->separador();
    $e->linea(substr(str_repeat('1234567890', 5), 0, $cols));
    $e->linea('^ La regla debe ocupar el ancho');
    $e->linea('  sin pasarse a otra línea.');
    return $e->cortar()->bytes();
}
