<?php
require_once __DIR__ . '/../includes/app.php';
require_permiso('ventas', 'nueva_venta', 'pedidos', 'mesas');
require_once __DIR__ . '/fpdf/fpdf.php';

/** FPDF trabaja en ISO-8859-1 / Windows-1252. */
function pdf_txt($texto): string
{
    $r = @iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$texto);
    return $r === false ? (string)$texto : $r;
}

$id = entero($_GET['v'] ?? 0);
$pedido = $id ? obtener_pedido($id) : null;
if (!$pedido) {
    http_response_code(404);
    exit('Venta no encontrada');
}
$datos = config_negocio();

// Altura del ticket según cantidad de productos
$alto = 130 + count($pedido['items']) * 9 + ($pedido['tipo'] === 'delivery' ? 15 : 0) + ($pedido['nota'] ? 10 : 0);
$pdf = new FPDF('P', 'mm', [80, max(150, $alto)]);
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(true, 3);
$pdf->AddPage();
$pdf->SetTitle('Ticket ' . $id);

// Encabezado
$pdf->Image(__DIR__ . '/../../assets/img/logo.png', 35, 5, 10, 10, 'PNG');
$pdf->Ln(12);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(74, 5, pdf_txt($datos['nombre'] ?? 'Snack Mary'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(74, 4, pdf_txt('Tel: ' . ($datos['telefono'] ?? '') . ' · ' . ($datos['email'] ?? '')), 0, 1, 'C');
$pdf->MultiCell(74, 4, pdf_txt($datos['direccion'] ?? ''), 0, 'C');
$pdf->Ln(2);

// Datos del pedido
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$titulo = 'Pedido #' . $id . ' - ' . (TIPOS_PEDIDO[$pedido['tipo']] ?? $pedido['tipo']);
if ($pedido['tipo'] === 'mesa' && $pedido['mesa']) {
    $titulo .= ' (' . $pedido['mesa'] . ')';
}
$pdf->Cell(74, 6, pdf_txt($titulo), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

$fila = function (string $etiqueta, ?string $valor) use ($pdf) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(22, 5, pdf_txt($etiqueta), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(52, 5, pdf_txt($valor ?? ''), 0, 'L');
};
$fila('Fecha:', date('d/m/Y H:i', strtotime($pedido['fecha'])));
$fila('Cliente:', $pedido['cliente']);
if ($pedido['tipo'] === 'delivery') {
    $fila('Teléfono:', telefono_pedido($pedido));
    $fila('Dirección:', $pedido['direccion_entrega']);
} elseif (!empty($pedido['cliente_telefono']) && (int)$pedido['id_cliente'] !== 1) {
    $fila('Teléfono:', $pedido['cliente_telefono']);
}
$fila('Atendió:', $pedido['vendedor'] ?? ($pedido['origen'] === 'web' ? 'Carta web' : ''));
$fila('Pago:', (METODOS_PAGO[$pedido['metodo_pago']] ?? $pedido['metodo_pago']) . ' - ' . (ESTADOS_PEDIDO[$pedido['estado']][0] ?? $pedido['estado']));
if ($pedido['nota']) {
    $fila('Nota:', $pedido['nota']);
}
$pdf->Ln(2);

// Detalle
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(36, 5, pdf_txt('Descripción'), 1, 0, 'L');
$pdf->Cell(10, 5, 'Cant', 1, 0, 'C');
$pdf->Cell(13, 5, 'P.U.', 1, 0, 'R');
$pdf->Cell(15, 5, 'Total', 1, 1, 'R');
$pdf->SetFont('Arial', '', 9);

$subtotal = 0;
foreach ($pedido['items'] as $row) {
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell(36, 5, pdf_txt($row['descripcion']), 1, 'L');
    $altura = $pdf->GetY() - $y;
    $pdf->SetXY($x + 36, $y);
    $importe = $row['cantidad'] * $row['precio'];
    $subtotal += $importe;
    $pdf->Cell(10, $altura, (string)(int)$row['cantidad'], 1, 0, 'C');
    $pdf->Cell(13, $altura, dinero($row['precio']), 1, 0, 'R');
    $pdf->Cell(15, $altura, dinero($importe), 1, 1, 'R');
}

$pdf->Ln(2);
if ((float)$pedido['costo_envio'] > 0) {
    $pdf->Cell(59, 5, 'Subtotal', 0, 0, 'R');
    $pdf->Cell(15, 5, dinero($subtotal), 0, 1, 'R');
    $pdf->Cell(59, 5, pdf_txt('Envío'), 0, 0, 'R');
    $pdf->Cell(15, 5, dinero($pedido['costo_envio']), 0, 1, 'R');
}
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(59, 6, 'TOTAL S/', 1, 0, 'R');
$pdf->Cell(15, 6, dinero($pedido['total']), 1, 1, 'R');
if ($pedido['estado'] === 'anulado') {
    $pdf->Ln(2);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell(74, 6, 'ANULADO', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(74, 5, pdf_txt('¡Gracias por su preferencia!'), 0, 1, 'C');

$pdf->Output('I', 'ticket_' . $id . '.pdf');
