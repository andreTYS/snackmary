<?php
/*
 * Comanda para cocina: letra grande, sin precios.
 * Si la mesa pidió más de una vez, imprime solo la última ronda (?ronda=todas para todo).
 */
require_once __DIR__ . '/../includes/app.php';
require_permiso('nueva_venta', 'pedidos', 'mesas', 'cocina');
require_once __DIR__ . '/fpdf/fpdf.php';

function pdf_txt($texto): string
{
    $r = @iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$texto);
    return $r === false ? (string)$texto : $r;
}

$id = entero($_GET['v'] ?? 0);
$pedido = $id ? obtener_pedido($id) : null;
if (!$pedido) {
    http_response_code(404);
    exit('Pedido no encontrado');
}
$todas = ($_GET['ronda'] ?? '') === 'todas';
$items = db_all(
    'SELECT d.cantidad, d.ronda, p.descripcion FROM detalle_venta d
     INNER JOIN producto p ON p.codproducto = d.id_producto
     LEFT JOIN categorias c ON c.id = p.id_categoria
     WHERE d.id_venta = ? AND COALESCE(c.cocina, 1) = 1' . ($todas ? '' : ' AND d.ronda = ?') . '
     ORDER BY d.ronda, d.id',
    $todas ? [$id] : [$id, $pedido['ultima_ronda']]
);

$alto = 70 + count($items) * 10 + ($pedido['nota'] ? 15 : 0);
$pdf = new FPDF('P', 'mm', [80, max(100, $alto)]);
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(true, 3);
$pdf->AddPage();
$pdf->SetTitle('Comanda ' . $id);

$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(74, 9, 'COMANDA #' . $id, 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 14);
$donde = $pedido['tipo'] === 'mesa' ? ($pedido['mesa'] ?: 'Mesa') : ($pedido['tipo'] === 'delivery' ? 'DELIVERY' : 'PARA LLEVAR');
if ($pedido['ultima_ronda'] > 1 && !$todas) {
    $donde .= ' - Ronda ' . $pedido['ultima_ronda'];
}
$pdf->Cell(74, 8, pdf_txt($donde), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(74, 5, pdf_txt(date('d/m/Y H:i') . ' · ' . ($pedido['vendedor'] ?? '')), 0, 1, 'C');
if ((int)$pedido['id_cliente'] !== 1) {
    $pdf->Cell(74, 5, pdf_txt('Cliente: ' . $pedido['cliente']), 0, 1, 'C');
}
$pdf->Ln(2);
$pdf->Line(3, $pdf->GetY(), 77, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 15);
if (!$items) {
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(74, 6, pdf_txt('Este pedido no tiene productos para cocina.'), 0, 'C');
}
foreach ($items as $it) {
    $pdf->Cell(14, 9, (int)$it['cantidad'] . ' x', 0, 0, 'L');
    $pdf->MultiCell(60, 9, pdf_txt($it['descripcion']), 0, 'L');
}
if ($pedido['nota']) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->MultiCell(74, 7, pdf_txt('NOTA: ' . $pedido['nota']), 0, 'L', true);
}
$pdf->Output('I', 'comanda_' . $id . '.pdf');
