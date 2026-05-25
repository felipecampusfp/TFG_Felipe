<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$id  = intval($_GET['id'] ?? 0);
$mes = $_GET['mes'] ?? date('Y-m');

if ($id > 0) {
    // Exportar factura individual
    $sql = "SELECT f.numero_factura, f.fecha_emision, f.base_imponible, f.iva_porcentaje, f.iva_importe, f.total, f.pagada, f.fecha_pago, CONCAT(c.nombre,' ',c.apellidos) AS cliente, c.telefono, c.email FROM facturas f JOIN pedidos p ON f.pedido_id=p.id JOIN clientes c ON p.cliente_id=c.id WHERE f.id=$id";
    $resMes = $conn->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') AS m FROM facturas WHERE id=$id");
    $rowMes = $resMes->fetch_assoc();
    $mes = $rowMes['m'] ?? date('Y-m');
} else {
    // Exportar todas las del mes
    $mesSql = $conn->real_escape_string($mes);
    $sql = "SELECT f.numero_factura, f.fecha_emision, f.base_imponible, f.iva_porcentaje, f.iva_importe, f.total, f.pagada, f.fecha_pago, CONCAT(c.nombre,' ',c.apellidos) AS cliente, c.telefono, c.email FROM facturas f JOIN pedidos p ON f.pedido_id=p.id JOIN clientes c ON p.cliente_id=c.id WHERE DATE_FORMAT(f.fecha_emision,'%Y-%m')='$mesSql' ORDER BY f.numero_factura ASC";
}

$res      = $conn->query($sql);
$facturas = $res->fetch_all(MYSQLI_ASSOC);

$mesesEs  = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
             '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$partes    = explode('-', $mes);
$nombreMes = ($mesesEs[$partes[1]] ?? $partes[1]) . ' ' . $partes[0];

$totalBase = array_sum(array_column($facturas, 'base_imponible'));
$totalIva  = array_sum(array_column($facturas, 'iva_importe'));
$totalFin  = array_sum(array_column($facturas, 'total'));

$filename = $id > 0 ? "Factura_" . ($facturas[0]['numero_factura'] ?? $id) : "Facturas_" . str_replace('-','_',$mes);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
header('Cache-Control: max-age=0');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8">
<style>
  body  { font-family: Arial; font-size: 11pt; }
  table { border-collapse: collapse; width: 100%; }
  .titulo   { font-size: 15pt; font-weight: bold; color: #1A2744; }
  .subtitulo{ font-size: 10pt; color: #6B7799; }
  .cabecera { background-color: #1A2744; color: #FFFFFF; font-weight: bold; text-align: center; padding: 7px 10px; border: 1px solid #1A2744; }
  .par      { background-color: #F5F7FA; }
  .impar    { background-color: #FFFFFF; }
  .total    { background-color: #E8F0FE; font-weight: bold; }
  td, th    { border: 1px solid #CCCCCC; padding: 6px 10px; vertical-align: middle; }
  .der      { text-align: right; }
  .cen      { text-align: center; }
  .verde    { color: #15803d; font-weight: bold; }
  .rojo     { color: #b45309; font-weight: bold; }
</style>
</head>
<body>
<p class="titulo">FACTURAS — <?= htmlspecialchars($nombreMes) ?></p>
<p class="subtitulo">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?> &nbsp;|&nbsp; Total facturas: <?= count($facturas) ?></p>
<br>
<?php if (empty($facturas)): ?>
<p>No hay facturas en este periodo.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th class="cabecera" style="width:130px">Nº Factura</th>
      <th class="cabecera" style="width:60px">Fecha</th>
      <th class="cabecera" style="width:200px">Cliente</th>
      <th class="cabecera" style="width:140px">Teléfono</th>
      <th class="cabecera" style="width:200px">Email</th>
      <th class="cabecera" style="width:110px">Base imponible</th>
      <th class="cabecera" style="width:90px">IVA (<?= $facturas[0]['iva_porcentaje'] ?? 21 ?>%)</th>
      <th class="cabecera" style="width:110px">Importe final</th>
      <th class="cabecera" style="width:80px">Estado</th>
      <th class="cabecera" style="width:90px">Fecha pago</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($facturas as $i => $f): ?>
    <tr class="<?= $i % 2 === 0 ? 'par' : 'impar' ?>">
      <td style="font-weight:600;color:#1D4ED8"><?= htmlspecialchars($f['numero_factura']) ?></td>
      <td class="cen"><?= $f['fecha_emision'] ? date('d/m/Y', strtotime($f['fecha_emision'])) : '—' ?></td>
      <td><?= htmlspecialchars($f['cliente']) ?></td>
      <td class="cen"><?= htmlspecialchars($f['telefono'] ?? '—') ?></td>
      <td><?= htmlspecialchars($f['email'] ?? '—') ?></td>
      <td class="der"><?= number_format(floatval($f['base_imponible']),2,',','.') ?> €</td>
      <td class="der"><?= number_format(floatval($f['iva_importe']),2,',','.') ?> €</td>
      <td class="der" style="font-weight:700"><?= number_format(floatval($f['total']),2,',','.') ?> €</td>
      <td class="cen <?= $f['pagada']=='1' ? 'verde' : 'rojo' ?>"><?= $f['pagada']=='1' ? 'PAGADA' : 'PENDIENTE' ?></td>
      <td class="cen"><?= $f['fecha_pago'] ? date('d/m/Y', strtotime($f['fecha_pago'])) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total">
      <td colspan="5" style="text-align:right;font-size:12pt">TOTALES</td>
      <td class="der"><?= number_format($totalBase,2,',','.') ?> €</td>
      <td class="der"><?= number_format($totalIva,2,',','.') ?> €</td>
      <td class="der" style="font-size:12pt"><?= number_format($totalFin,2,',','.') ?> €</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>
<?php endif; ?>
</body></html>
<?php $conn->close(); ?>