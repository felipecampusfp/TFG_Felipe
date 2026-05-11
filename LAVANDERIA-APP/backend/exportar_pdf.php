<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    // Si no hay id, exportar por mes (funcionalidad anterior)
    header('Location: exportar_facturas.php?mes=' . ($_GET['mes'] ?? date('Y-m')));
    exit();
}

// Obtener datos de la factura
$stmt = $conn->prepare("
    SELECT f.*, CONCAT(c.nombre,' ',c.apellidos) AS cliente_nombre,
           c.telefono, c.email, c.direccion
    FROM facturas f
    JOIN pedidos p ON f.pedido_id = p.id
    JOIN clientes c ON p.cliente_id = c.id
    WHERE f.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$f = $stmt->get_result()->fetch_assoc();

if (!$f) { echo "Factura no encontrada"; exit(); }

// Obtener sacos del pedido como lineas
$sacos = $conn->query("
    SELECT s.peso_kg, s.precio_kg, s.subtotal, t.nombre AS tipo
    FROM sacos s JOIN tipos_ropa t ON s.tipo_ropa_id = t.id
    WHERE s.pedido_id = {$f['pedido_id']}
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Nombres de meses
$mesesEs = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
            '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$partes = explode('-', $f['fecha_emision'] ?? date('Y-m-d'));
$fechaFormato = ($partes[2] ?? '01') . ' de ' . ($mesesEs[$partes[1]] ?? '') . ' de ' . ($partes[0] ?? '');
$estado = $f['pagada']=='1' ? 'PAGADA' : 'PENDIENTE';
$colorEstado = $f['pagada']=='1' ? '#15803d' : '#b45309';
$bgEstado    = $f['pagada']=='1' ? '#e6f9f0' : '#fff8e6';

// Generar HTML para imprimir como PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura <?= htmlspecialchars($f['numero_factura']) ?></title>
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1a2744; background: #fff; padding: 40px; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 32px; border-bottom: 3px solid #1a2744; padding-bottom: 20px; }
  .logo-area h1 { font-size: 26px; color: #1a2744; font-weight: 700; }
  .logo-area p  { color: #6b7799; font-size: 12px; margin-top: 4px; }
  .factura-info { text-align: right; }
  .factura-info h2 { font-size: 22px; color: #3e85f5; font-weight: 700; }
  .factura-info .num { font-size: 14px; color: #1a2744; font-weight: 600; margin-top: 4px; }
  .factura-info .fecha { color: #6b7799; font-size: 12px; margin-top: 2px; }
  .estado-badge { display:inline-block; padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; background: <?= $bgEstado ?>; color: <?= $colorEstado ?>; border: 1px solid <?= $colorEstado ?>; margin-top: 6px; }
  .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
  .datos-box { background: #f5f7fa; border-radius: 8px; padding: 14px 16px; }
  .datos-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7799; margin-bottom: 8px; font-weight: 600; }
  .datos-box p { font-size: 13px; color: #1a2744; line-height: 1.6; }
  .datos-box strong { font-weight: 700; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  thead tr { background: #1a2744; }
  thead th { color: #fff; padding: 10px 12px; text-align: left; font-size: 12px; font-weight: 600; }
  thead th:last-child { text-align: right; }
  tbody tr { border-bottom: 1px solid #e2e6ef; }
  tbody tr:nth-child(even) { background: #f9fafb; }
  tbody td { padding: 10px 12px; font-size: 13px; }
  tbody td:nth-child(2), tbody td:nth-child(3), tbody td:nth-child(4) { text-align: right; }
  .totales { display: flex; justify-content: flex-end; margin-bottom: 28px; }
  .totales-box { width: 280px; }
  .totales-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
  .totales-row.total { border-top: 2px solid #1a2744; margin-top: 6px; padding-top: 8px; font-size: 16px; font-weight: 700; color: #1a2744; }
  .totales-row.total span:last-child { color: #3e85f5; }
  .footer { border-top: 1px solid #e2e6ef; padding-top: 16px; text-align: center; color: #9aa3be; font-size: 11px; }
  @media print {
    body { padding: 20px; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<!-- Botón imprimir (se oculta al imprimir) -->
<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px">
  <button onclick="window.print()" style="background:#1a2744;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer">🖨️ Imprimir / Guardar PDF</button>
  <button onclick="window.close()" style="background:#f5f7fa;color:#1a2744;border:1px solid #e2e6ef;border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer">✕ Cerrar</button>
</div>

<!-- Cabecera -->
<div class="header">
  <div class="logo-area">
    <h1>🧺 LavanderApp</h1>
    <p>Sistema de Gestión de Lavandería</p>
  </div>
  <div class="factura-info">
    <h2>FACTURA</h2>
    <div class="num"><?= htmlspecialchars($f['numero_factura']) ?></div>
    <div class="fecha"><?= $fechaFormato ?></div>
    <div><span class="estado-badge"><?= $estado ?></span></div>
    <?php if ($f['pagada']=='1' && $f['fecha_pago']): ?>
    <div style="font-size:11px;color:#15803d;margin-top:4px">Pagada el <?= $f['fecha_pago'] ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Datos cliente y empresa -->
<div class="datos">
  <div class="datos-box">
    <h3>Facturado a</h3>
    <p><strong><?= htmlspecialchars($f['cliente_nombre']) ?></strong></p>
    <?php if ($f['direccion']): ?><p><?= htmlspecialchars($f['direccion']) ?></p><?php endif; ?>
    <?php if ($f['telefono']): ?><p>Tel: <?= htmlspecialchars($f['telefono']) ?></p><?php endif; ?>
    <?php if ($f['email']): ?><p><?= htmlspecialchars($f['email']) ?></p><?php endif; ?>
  </div>
  <div class="datos-box">
    <h3>Datos de la factura</h3>
    <p><strong>Nº Factura:</strong> <?= htmlspecialchars($f['numero_factura']) ?></p>
    <p><strong>Fecha emisión:</strong> <?= $fechaFormato ?></p>
    <p><strong>Estado:</strong> <?= $estado ?></p>
    <?php if ($f['fecha_pago']): ?><p><strong>Fecha pago:</strong> <?= $f['fecha_pago'] ?></p><?php endif; ?>
  </div>
</div>

<!-- Líneas de detalle -->
<table>
  <thead>
    <tr>
      <th style="width:50%">Descripción</th>
      <th>Cantidad (kg)</th>
      <th>Precio/kg</th>
      <th>Subtotal</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($sacos)): ?>
      <?php foreach ($sacos as $s): ?>
      <tr>
        <td>Lavado <?= htmlspecialchars($s['tipo']) ?></td>
        <td style="text-align:right"><?= number_format($s['peso_kg'],2,',','.') ?> kg</td>
        <td style="text-align:right"><?= number_format($s['precio_kg'],2,',','.') ?> €/kg</td>
        <td style="text-align:right;font-weight:600"><?= number_format($s['subtotal'],2,',','.') ?> €</td>
      </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="4" style="text-align:center;color:#9aa3be;padding:16px">Servicio de lavandería</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Totales -->
<div class="totales">
  <div class="totales-box">
    <div class="totales-row">
      <span style="color:#6b7799">Base imponible:</span>
      <span><?= number_format($f['base_imponible'],2,',','.') ?> €</span>
    </div>
    <div class="totales-row">
      <span style="color:#6b7799">IVA (<?= $f['iva_porcentaje'] ?>%):</span>
      <span><?= number_format($f['iva_importe'],2,',','.') ?> €</span>
    </div>
    <div class="totales-row total">
      <span>TOTAL:</span>
      <span><?= number_format($f['total'],2,',','.') ?> €</span>
    </div>
  </div>
</div>

<!-- Pie de página -->
<div class="footer">
  <p>LavanderApp · Sistema de Gestión de Lavandería · Factura generada automáticamente</p>
  <p style="margin-top:4px">Este documento es válido como factura oficial</p>
</div>

</body>
</html>