<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

$mes = $_GET['mes'] ?? date('Y-m');

// Obtener facturas del mes con datos del cliente
$mesSql = $conn->real_escape_string($mes);
$sql = "
    SELECT
        CONCAT(c.nombre, ' ', c.apellidos) AS cliente,
        c.telefono,
        c.email,
        f.numero_factura,
        f.fecha_emision,
        f.base_imponible,
        f.iva_porcentaje,
        f.iva_importe,
        f.total,
        f.pagada,
        f.fecha_pago
    FROM facturas f
    JOIN pedidos p ON f.pedido_id = p.id
    JOIN clientes c ON p.cliente_id = c.id
    WHERE DATE_FORMAT(f.fecha_emision, '%Y-%m') = '$mesSql'
    ORDER BY c.apellidos, c.nombre, f.fecha_emision
";

$res      = $conn->query($sql);
$facturas = $res->fetch_all(MYSQLI_ASSOC);

// Calcular totales por cliente
$porCliente = [];
foreach ($facturas as $f) {
    $cli = $f['cliente'];
    if (!isset($porCliente[$cli])) {
        $porCliente[$cli] = ['cliente' => $cli, 'telefono' => $f['telefono'], 'email' => $f['email'], 'facturas' => [], 'total_base' => 0, 'total_iva' => 0, 'total' => 0, 'pagadas' => 0, 'pendientes' => 0];
    }
    $porCliente[$cli]['facturas'][]    = $f;
    $porCliente[$cli]['total_base']   += floatval($f['base_imponible']);
    $porCliente[$cli]['total_iva']    += floatval($f['iva_importe']);
    $porCliente[$cli]['total']        += floatval($f['total']);
    if ($f['pagada'] == '1') $porCliente[$cli]['pagadas']++;
    else $porCliente[$cli]['pendientes']++;
}

// Nombre del mes en español
$mesesEs = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$partes   = explode('-', $mes);
$nombreMes = ($mesesEs[$partes[1]] ?? $partes[1]) . ' ' . $partes[0];

// Generar CSV con formato Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Facturacion_' . str_replace('-', '_', $mes) . '.xls"');
header('Cache-Control: max-age=0');

// BOM para UTF-8
echo "\xEF\xBB\xBF";

// Titulo
echo "INFORME DE FACTURACION - $nombreMes\t\t\t\t\t\t\t\t\t\t\n";
echo "Generado el: " . date('d/m/Y H:i') . "\t\t\t\t\t\t\t\t\t\t\n";
echo "\n";

if (empty($porCliente)) {
    echo "No hay facturas en este periodo.\n";
    exit();
}

// Por cada cliente
foreach ($porCliente as $cli => $datos) {
    echo "CLIENTE: " . strtoupper($cli) . "\t\t\t\t\t\t\t\t\t\t\n";
    if ($datos['telefono']) echo "Telefono: " . $datos['telefono'] . "\t\t\t\t\t\t\t\t\t\t\n";
    if ($datos['email'])    echo "Email: " . $datos['email'] . "\t\t\t\t\t\t\t\t\t\t\n";
    echo "\n";

    // Cabecera facturas
    echo "N. Factura\tFecha\tBase Imponible\tIVA (" . $datos['facturas'][0]['iva_porcentaje'] . "%)\tTotal\tEstado\tFecha Pago\n";

    foreach ($datos['facturas'] as $f) {
        $estado    = $f['pagada'] == '1' ? 'PAGADA' : 'PENDIENTE';
        $fechaPago = $f['fecha_pago'] ? date('d/m/Y', strtotime($f['fecha_pago'])) : '-';
        echo $f['numero_factura'] . "\t";
        echo date('d/m/Y', strtotime($f['fecha_emision'])) . "\t";
        echo number_format(floatval($f['base_imponible']), 2, ',', '.') . " EUR\t";
        echo number_format(floatval($f['iva_importe']), 2, ',', '.') . " EUR\t";
        echo number_format(floatval($f['total']), 2, ',', '.') . " EUR\t";
        echo $estado . "\t";
        echo $fechaPago . "\n";
    }

    // Subtotal cliente
    echo "\t\tSUBTOTAL " . strtoupper($cli) . ":\t\t";
    echo number_format($datos['total'], 2, ',', '.') . " EUR\t";
    echo $datos['pagadas'] . " pagadas / " . $datos['pendientes'] . " pendientes\t\n";
    echo "\n";
}

// Totales generales
$totalBase    = array_sum(array_column($porCliente, 'total_base'));
$totalIva     = array_sum(array_column($porCliente, 'total_iva'));
$totalGeneral = array_sum(array_column($porCliente, 'total'));
$totalPagadas = array_sum(array_column($porCliente, 'pagadas'));
$totalPend    = array_sum(array_column($porCliente, 'pendientes'));

echo "================================================================================\n";
echo "RESUMEN TOTAL - $nombreMes\t\t\t\t\t\t\t\t\t\t\n";
echo "\n";
echo "Total clientes facturados:\t" . count($porCliente) . "\t\t\t\t\t\t\t\t\t\n";
echo "Total facturas emitidas:\t" . count($facturas) . "\t\t\t\t\t\t\t\t\t\n";
echo "Facturas pagadas:\t" . $totalPagadas . "\t\t\t\t\t\t\t\t\t\n";
echo "Facturas pendientes:\t" . $totalPend . "\t\t\t\t\t\t\t\t\t\n";
echo "\n";
echo "Base imponible total:\t" . number_format($totalBase, 2, ',', '.') . " EUR\t\t\t\t\t\t\t\t\t\n";
echo "IVA total (21%):\t" . number_format($totalIva, 2, ',', '.') . " EUR\t\t\t\t\t\t\t\t\t\n";
echo "TOTAL FACTURADO:\t" . number_format($totalGeneral, 2, ',', '.') . " EUR\t\t\t\t\t\t\t\t\t\n";

$conn->close();
?>