<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$mes = $_GET['mes'] ?? date('Y-m');
$mesSql = $conn->real_escape_string($mes);

$sql = "
    SELECT
        f.numero_factura,
        CONCAT(c.nombre, ' ', c.apellidos) AS cliente,
        f.base_imponible,
        f.iva_importe,
        f.total
    FROM facturas f
    JOIN pedidos p ON f.pedido_id = p.id
    JOIN clientes c ON p.cliente_id = c.id
    WHERE DATE_FORMAT(f.fecha_emision, '%Y-%m') = '$mesSql'
    ORDER BY f.numero_factura ASC
";

$res      = $conn->query($sql);
$facturas = $res->fetch_all(MYSQLI_ASSOC);

// Nombre del mes en español
$mesesEs = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
            '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$partes    = explode('-', $mes);
$nombreMes = ($mesesEs[$partes[1]] ?? $partes[1]) . ' ' . $partes[0];

// Totales
$totalBase = array_sum(array_column($facturas, 'base_imponible'));
$totalIva  = array_sum(array_column($facturas, 'iva_importe'));
$totalFin  = array_sum(array_column($facturas, 'total'));

// Generar Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Facturas_' . str_replace('-','_',$mes) . '.xls"');
header('Cache-Control: max-age=0');

// BOM UTF-8
echo "\xEF\xBB\xBF";

// Titulo
echo "FACTURACIÓN - $nombreMes\n";
echo "\n";

// Cabecera columnas
echo "Nº Factura\tCliente\tImporte sin IVA\tIVA (21%)\tImporte final\n";

// Filas
foreach ($facturas as $f) {
    echo htmlspecialchars_decode($f['numero_factura']) . "\t";
    echo htmlspecialchars_decode($f['cliente']) . "\t";
    echo number_format(floatval($f['base_imponible']), 2, ',', '.') . " €\t";
    echo number_format(floatval($f['iva_importe']),    2, ',', '.') . " €\t";
    echo number_format(floatval($f['total']),          2, ',', '.') . " €\n";
}

// Fila totales
echo "\n";
echo "TOTAL\t\t";
echo number_format($totalBase, 2, ',', '.') . " €\t";
echo number_format($totalIva,  2, ',', '.') . " €\t";
echo number_format($totalFin,  2, ',', '.') . " €\n";

$conn->close();
?>