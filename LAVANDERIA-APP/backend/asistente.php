<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$data      = json_decode(file_get_contents("php://input"), true);
$pregunta  = trim($data['pregunta'] ?? '');
$historial = $data['historial'] ?? [];

if (!$pregunta) { echo json_encode(["error" => "Pregunta requerida"]); exit(); }

$GROQ_KEY = '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';
$hoy = date('Y-m-d');
$mes = date('Y-m');

// ── Datos de la BD ──
$pedidos  = $conn->query("SELECT v.*, p.cliente_id FROM v_pedidos_resumen v JOIN pedidos p ON p.id = v.pedido_id ORDER BY v.fecha_entrada DESC")->fetch_all(MYSQLI_ASSOC);
$clientes = $conn->query("SELECT * FROM clientes WHERE activo = 1")->fetch_all(MYSQLI_ASSOC);
$productos = $conn->query("SELECT p.*, c.nombre AS cat FROM productos p JOIN categorias_producto c ON p.categoria_id = c.id WHERE p.activo = 1")->fetch_all(MYSQLI_ASSOC);
$sacos    = $conn->query("SELECT s.*, t.nombre AS tipo, p.fecha_entrada, CONCAT(c.nombre,' ',c.apellidos) AS cliente FROM sacos s JOIN tipos_ropa t ON s.tipo_ropa_id = t.id JOIN pedidos p ON s.pedido_id = p.id JOIN clientes c ON p.cliente_id = c.id")->fetch_all(MYSQLI_ASSOC);
$facturas = $conn->query("SELECT f.*, CONCAT(c.nombre,' ',c.apellidos) AS cliente FROM facturas f JOIN pedidos p ON f.pedido_id = p.id JOIN clientes c ON p.cliente_id = c.id ORDER BY f.fecha_emision DESC")->fetch_all(MYSQLI_ASSOC);

$resMes = $conn->query("SELECT DATE_FORMAT(fecha_emision,'%Y-%m') AS mes, COUNT(*) AS num, SUM(base_imponible) AS base, SUM(iva_importe) AS iva, SUM(total) AS total, SUM(CASE WHEN pagada=1 THEN total ELSE 0 END) AS cobrado, SUM(CASE WHEN pagada=0 THEN total ELSE 0 END) AS pendiente FROM facturas GROUP BY mes ORDER BY mes DESC");
$porMes = [];
while ($r = $resMes->fetch_assoc()) $porMes[$r['mes']] = $r;

$resCli = $conn->query("SELECT CONCAT(c.nombre,' ',c.apellidos) AS cliente, COUNT(DISTINCT ped.id) AS pedidos, SUM(f.total) AS facturado, SUM(CASE WHEN f.pagada=1 THEN f.total ELSE 0 END) AS cobrado, SUM(CASE WHEN f.pagada=0 THEN f.total ELSE 0 END) AS pendiente FROM clientes c LEFT JOIN pedidos ped ON ped.cliente_id=c.id LEFT JOIN facturas f ON f.pedido_id=ped.id WHERE c.activo=1 GROUP BY c.id ORDER BY facturado DESC");
$porCliente = $resCli->fetch_all(MYSQLI_ASSOC);

// ── Calculos ──
$pendientes  = array_filter($pedidos, fn($p) => $p['estado']==='PENDIENTE');
$enProceso   = array_filter($pedidos, fn($p) => $p['estado']==='EN_PROCESO');
$pedidosHoy  = array_filter($pedidos, fn($p) => $p['fecha_entrada']===$hoy);
$stockBajo   = array_filter($productos, fn($p) => intval($p['stock_actual']) < intval($p['stock_minimo']));
$stockCrit   = array_filter($productos, fn($p) => intval($p['stock_actual'])===0);
$sinCobrar   = array_filter($facturas, fn($f) => $f['pagada']==='0');
$sacosHoy    = array_filter($sacos, fn($s) => $s['fecha_entrada']===$hoy);
$meActual    = $porMes[$mes] ?? null;
$mesAnt      = $porMes[date('Y-m', strtotime('-1 month'))] ?? null;

$totalFact   = array_sum(array_column($facturas, 'total'));
$totalPend   = array_sum(array_column(array_values($sinCobrar), 'total'));
$pesoHoy     = array_sum(array_column(array_values($sacosHoy), 'peso_kg'));
$crecimiento = ($mesAnt && floatval($mesAnt['total'])>0 && $meActual)
    ? ((floatval($meActual['total'])-floatval($mesAnt['total']))/floatval($mesAnt['total']))*100 : 0;

$diaRes = $conn->query("SELECT DAYOFWEEK(fecha_entrada) AS dia, COUNT(*) AS t FROM pedidos GROUP BY dia ORDER BY t DESC LIMIT 1");
$diaRow = $diaRes->fetch_assoc();
$dias   = [1=>'Domingo',2=>'Lunes',3=>'Martes',4=>'Miercoles',5=>'Jueves',6=>'Viernes',7=>'Sabado'];
$diaMasOcupado = $dias[$diaRow['dia'] ?? 2] ?? 'Lunes';

function e2($n) { return number_format(floatval($n),2,',','.') . ' EUR'; }
function mk($k) {
    $m=['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
        '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
    $p=explode('-',$k); return ($m[$p[1]]??$p[1]).' '.$p[0];
}

// ── Contexto para la IA ──
$ctx  = "Eres LavanIA, el asistente inteligente de LavanderApp, sistema de gestion de una lavanderia en Espana. ";
$ctx .= "Tienes acceso a los datos en tiempo real. Responde SIEMPRE en espanol, de forma clara y util. ";
$ctx .= "Usa emojis ocasionalmente. Puedes responder preguntas generales, dar consejos, contar chistes, etc. ";
$ctx .= "Cuando pregunten sobre datos del negocio, usa los datos reales que tienes.\n\n";
$ctx .= "DATOS ACTUALES (hoy $hoy):\n";
$ctx .= "- Pedidos: ".count($pendientes)." pendientes, ".count($enProceso)." en proceso, ".count($pedidosHoy)." hoy, ".count($pedidos)." total\n";
$ctx .= "- Clientes activos: ".count($clientes)."\n";
$ctx .= "- Sacos hoy: ".count($sacosHoy)." (".number_format($pesoHoy,1)." kg)\n";
$ctx .= "- Stock bajo minimo: ".count($stockBajo)." productos".( count($stockCrit)>0 ? " (".count($stockCrit)." sin stock)" : "")."\n";
$ctx .= "- Facturas sin cobrar: ".count($sinCobrar)." (".e2($totalPend).")\n";
$ctx .= "- Total facturado historico: ".e2($totalFact)."\n";
if ($meActual) $ctx .= "- Este mes: base=".e2($meActual['base'])." IVA=".e2($meActual['iva'])." TOTAL=".e2($meActual['total'])." cobrado=".e2($meActual['cobrado'])." pendiente=".e2($meActual['pendiente'])."\n";
if ($mesAnt && $meActual) $ctx .= "- Variacion vs mes anterior: ".number_format($crecimiento,1)."%\n";
$ctx .= "- Dia mas ocupado: $diaMasOcupado\n";
if (!empty($porMes)) {
    $ctx .= "- Historico mensual:\n";
    foreach (array_slice($porMes,0,12,true) as $k=>$d) $ctx .= "  ".mk($k).": ".e2($d['total'])." (".$d['num']." facturas)\n";
}
if (!empty($porCliente)) {
    $ctx .= "- Por cliente:\n";
    foreach (array_slice($porCliente,0,8) as $c) if(floatval($c['facturado'])>0) $ctx .= "  ".$c['cliente'].": ".$c['pedidos']." pedidos, ".e2($c['facturado'])." facturado, ".e2($c['pendiente'])." pendiente\n";
}
if (count($sinCobrar)>0) {
    $ctx .= "- Facturas pendientes de cobro:\n";
    foreach (array_values($sinCobrar) as $f) {
        $dias2 = (int)((time()-strtotime($f['fecha_emision']))/86400);
        $ctx .= "  ".$f['numero_factura']." | ".$f['cliente']." | ".e2($f['total'])." | $dias2 dias\n";
    }
}

// ── Llamada a Groq ──
$messages = [['role'=>'system','content'=>$ctx]];
foreach ($historial as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = ['role'=>$msg['role'], 'content'=>$msg['content']];
    }
}
$messages[] = ['role'=>'user', 'content'=>$pregunta];

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.7,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $GROQ_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode === 200) {
    $result    = json_decode($response, true);
    $respuesta = $result['choices'][0]['message']['content'] ?? null;
    if ($respuesta) {
        echo json_encode(["ok"=>true, "respuesta"=>trim($respuesta)]);
        $conn->close();
        exit();
    }
}

// ── Fallback ──
$p2 = strtolower($pregunta);
if (str_contains($p2,'pendiente') && str_contains($p2,'cobr')) {
    $r = count($sinCobrar)>0 ? "Hay ".count($sinCobrar)." facturas pendientes por ".e2($totalPend)."." : "No hay facturas pendientes!";
} elseif (str_contains($p2,'mes') && str_contains($p2,'factur')) {
    $r = $meActual ? "Este mes: ".e2($meActual['total'])." (cobrado: ".e2($meActual['cobrado']).", pendiente: ".e2($meActual['pendiente']).")" : "Sin facturas este mes.";
} elseif (str_contains($p2,'stock') || str_contains($p2,'reponer')) {
    $r = count($stockBajo)>0 ? "Reponer: ".implode(', ',array_column(array_values($stockBajo),'nombre'))."." : "Stock correcto.";
} elseif (str_contains($p2,'pedido')) {
    $r = count($pendientes)." pendientes, ".count($enProceso)." en proceso. Total: ".count($pedidos).".";
} else {
    $r = "Hoy: ".count($pedidosHoy)." pedidos. Stock bajo: ".count($stockBajo).". Sin cobrar: ".count($sinCobrar)." (".e2($totalPend).").";
}
echo json_encode(["ok"=>true, "respuesta"=>$r, "fallback"=>true]);
$conn->close();
?>