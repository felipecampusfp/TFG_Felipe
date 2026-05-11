<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

function generarNumero($conn) {
    $year  = date('Y');
    $seq_r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_factura,'-',-1) AS UNSIGNED)),0)+1 AS seq FROM facturas WHERE numero_factura LIKE 'FAC-$year-%'");
    $seq   = intval($seq_r->fetch_assoc()['seq']);
    return "FAC-$year-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

switch ($method) {

    case 'GET':
        if (isset($_GET['generar_mes'])) {
            $mes = $conn->real_escape_string($_GET['generar_mes']);
            $sql = "SELECT p.id AS pedido_id, CONCAT(c.nombre,' ',c.apellidos) AS cliente_nombre,
                           COALESCE(SUM(s.subtotal),0) AS base
                    FROM pedidos p JOIN clientes c ON p.cliente_id=c.id
                    LEFT JOIN sacos s ON s.pedido_id=p.id
                    LEFT JOIN facturas f ON f.pedido_id=p.id
                    WHERE (p.estado_id=3 OR p.estado_id=4)
                    AND DATE_FORMAT(p.fecha_entrada,'%Y-%m')='$mes' AND f.id IS NULL
                    GROUP BY p.id, c.nombre, c.apellidos";
            $res = $conn->query($sql);
            $generadas = 0;
            while ($row = $res->fetch_assoc()) {
                if (floatval($row['base']) <= 0) continue;
                $num  = generarNumero($conn);
                $base = floatval($row['base']);
                $iva  = round($base*0.21,2);
                $tot  = round($base+$iva,2);
                $pid  = intval($row['pedido_id']);
                $fecha = date('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO facturas (pedido_id,numero_factura,fecha_emision,base_imponible,iva_porcentaje,iva_importe,total) VALUES (?,?,?,?,21,?,?)");
                $stmt->bind_param("issddd", $pid, $num, $fecha, $base, $iva, $tot);
                if ($stmt->execute()) $generadas++;
            }
            echo json_encode(["ok"=>true, "generadas"=>$generadas]);
            break;
        }

        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT f.*, CONCAT(c.nombre,' ',c.apellidos) AS cliente, c.email, c.telefono, c.direccion FROM facturas f JOIN pedidos p ON f.pedido_id=p.id JOIN clientes c ON p.cliente_id=c.id WHERE f.id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $factura = $stmt->get_result()->fetch_assoc();
            if ($factura) {
                // Cargar sacos como lineas
                $sacos = $conn->query("SELECT s.peso_kg, s.precio_kg, s.subtotal, t.nombre FROM sacos s JOIN tipos_ropa t ON s.tipo_ropa_id=t.id WHERE s.pedido_id={$factura['pedido_id']}")->fetch_all(MYSQLI_ASSOC);
                $lineas = [];
                foreach ($sacos as $s) {
                    $lineas[] = [
                        'descripcion'    => 'Lavado ' . $s['nombre'] . ' - ' . number_format($s['peso_kg'],2) . ' kg',
                        'cantidad'       => $s['peso_kg'],
                        'precio_unitario'=> $s['precio_kg'],
                        'subtotal'       => $s['subtotal'],
                    ];
                }
                $factura['lineas'] = $lineas;
                $factura['estado'] = $factura['pagada']=='1' ? 'pagada' : 'confirmada';
            }
            echo json_encode($factura ?: ["error"=>"No encontrada"]);
            break;
        }

        $res = $conn->query("SELECT f.id, f.numero_factura, f.fecha_emision, f.base_imponible, f.iva_porcentaje, f.iva_importe, f.total, f.pagada, f.fecha_pago, CONCAT(c.nombre,' ',c.apellidos) AS cliente, c.id AS cliente_id, p.id AS pedido_id FROM facturas f JOIN pedidos p ON f.pedido_id=p.id JOIN clientes c ON p.cliente_id=c.id ORDER BY f.fecha_emision DESC, f.id DESC");
        $data = [];
        while ($r = $res->fetch_assoc()) {
            $r['estado'] = $r['pagada']=='1' ? 'pagada' : 'confirmada';
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case 'POST':
        $d      = json_decode(file_get_contents("php://input"), true);
        $accion = $d['accion'] ?? 'crear';

        if ($accion === 'pagar') {
            $id    = intval($d['id'] ?? 0);
            $fecha = $d['fecha_pago'] ?? date('Y-m-d');
            $stmt  = $conn->prepare("UPDATE facturas SET pagada=1, fecha_pago=? WHERE id=?");
            $stmt->bind_param("si", $fecha, $id);
            echo $stmt->execute() ? json_encode(["ok"=>true]) : json_encode(["error"=>$conn->error]);
            break;
        }

        if ($accion === 'cancelar') {
            $id = intval($d['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM facturas WHERE id=?");
            $stmt->bind_param("i", $id);
            echo $stmt->execute() ? json_encode(["ok"=>true]) : json_encode(["error"=>$conn->error]);
            break;
        }

        // Crear factura nueva
        $pedido_id = intval($d['pedido_id'] ?? 0);
        $fecha     = trim($d['fecha_emision'] ?? date('Y-m-d'));
        $lineas    = $d['lineas'] ?? [];

        if (!$pedido_id) { http_response_code(400); echo json_encode(["error"=>"pedido_id requerido"]); exit(); }

        // Calcular base desde lineas
        $base = 0;
        foreach ($lineas as $l) $base += (floatval($l['cantidad']??1) * floatval($l['precio_unitario']??0));

        // Si no hay lineas con importe, calcular desde sacos
        if ($base <= 0) {
            $res  = $conn->query("SELECT COALESCE(SUM(subtotal),0) AS base FROM sacos WHERE pedido_id=$pedido_id");
            $base = floatval($res->fetch_assoc()['base']);
        }

        $iva  = round($base*0.21,2);
        $tot  = round($base+$iva,2);
        $num  = generarNumero($conn);

        $stmt = $conn->prepare("INSERT INTO facturas (pedido_id,numero_factura,fecha_emision,base_imponible,iva_porcentaje,iva_importe,total) VALUES (?,?,?,?,21,?,?)");
        $stmt->bind_param("issddd", $pedido_id, $num, $fecha, $base, $iva, $tot);
        if ($stmt->execute()) {
            echo json_encode(["ok"=>true, "id"=>$conn->insert_id, "numero"=>$num]);
        } else {
            http_response_code(500);
            echo json_encode(["error"=>$conn->error]);
        }
        break;

    case 'PUT':
        $d      = json_decode(file_get_contents("php://input"), true);
        $id     = intval($d['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(["error"=>"ID requerido"]); exit(); }

        $pagada     = intval($d['pagada'] ?? 0);
        $fecha_pago = $pagada ? ($d['fecha_pago'] ?? date('Y-m-d')) : null;

        if (isset($d['base_imponible']) && floatval($d['base_imponible']) > 0) {
            $base  = floatval($d['base_imponible']);
            $iva_p = floatval($d['iva_porcentaje'] ?? 21);
            $iva_i = round($base*$iva_p/100,2);
            $total = round($base+$iva_i,2);
            $stmt  = $conn->prepare("UPDATE facturas SET base_imponible=?,iva_porcentaje=?,iva_importe=?,total=?,pagada=?,fecha_pago=? WHERE id=?");
            $stmt->bind_param("ddddisi", $base, $iva_p, $iva_i, $total, $pagada, $fecha_pago, $id);
        } else {
            $stmt = $conn->prepare("UPDATE facturas SET pagada=?,fecha_pago=? WHERE id=?");
            $stmt->bind_param("isi", $pagada, $fecha_pago, $id);
        }
        echo $stmt->execute() ? json_encode(["ok"=>true]) : json_encode(["error"=>$conn->error]);
        break;

    case 'DELETE':
        $id   = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM facturas WHERE id=?");
        $stmt->bind_param("i", $id);
        echo $stmt->execute() ? json_encode(["ok"=>true]) : json_encode(["error"=>$conn->error]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error"=>"Metodo no permitido"]);
}
$conn->close();
?>