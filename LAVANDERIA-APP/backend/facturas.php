<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['generar_mes'])) {
            $mes = $conn->real_escape_string($_GET['generar_mes']);
            $sql = "SELECT p.id AS pedido_id, CONCAT(c.nombre,' ',c.apellidos) AS cliente,
                    COALESCE(SUM(s.subtotal), 0) AS base
                    FROM pedidos p
                    JOIN clientes c ON p.cliente_id = c.id
                    LEFT JOIN sacos s ON s.pedido_id = p.id
                    LEFT JOIN facturas f ON f.pedido_id = p.id
                    WHERE (p.estado_id = 3 OR p.estado_id = 4)
                    AND DATE_FORMAT(p.fecha_entrada,'%Y-%m') = '$mes'
                    AND f.id IS NULL
                    GROUP BY p.id, c.nombre, c.apellidos";
            $res = $conn->query($sql);
            $generadas = 0;
            while ($row = $res->fetch_assoc()) {
                $pid   = intval($row['pedido_id']);
                $base  = floatval($row['base']);
                if ($base <= 0) continue;
                $iva_i = round($base * 0.21, 2);
                $total = round($base + $iva_i, 2);
                $fecha = date('Y-m-d');
                $year  = date('Y');
                $seq_r = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_factura,'-',-1) AS UNSIGNED)),0)+1 AS seq FROM facturas WHERE numero_factura LIKE 'FAC-$year-%'");
                $seq   = intval($seq_r->fetch_assoc()['seq']);
                $num   = "FAC-$year-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
                $stmt  = $conn->prepare("INSERT INTO facturas (pedido_id, numero_factura, fecha_emision, base_imponible, iva_porcentaje, iva_importe, total) VALUES (?, ?, ?, ?, 21, ?, ?)");
                $stmt->bind_param("issddd", $pid, $num, $fecha, $base, $iva_i, $total);
                $stmt->execute();
                $generadas++;
            }
            echo json_encode(["ok" => true, "generadas" => $generadas]);

        } elseif (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT f.*, CONCAT(c.nombre,' ',c.apellidos) AS cliente FROM facturas f JOIN pedidos p ON f.pedido_id = p.id JOIN clientes c ON p.cliente_id = c.id WHERE f.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());

        } else {
            $res = $conn->query("SELECT f.id, f.numero_factura, f.fecha_emision, f.base_imponible, f.iva_porcentaje, f.iva_importe, f.total, f.pagada, f.fecha_pago, CONCAT(c.nombre,' ',c.apellidos) AS cliente, c.id AS cliente_id, p.id AS pedido_id FROM facturas f JOIN pedidos p ON f.pedido_id = p.id JOIN clientes c ON p.cliente_id = c.id ORDER BY f.fecha_emision DESC");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        break;

    case 'POST':
        $d         = json_decode(file_get_contents("php://input"), true);
        $pedido_id = intval($d['pedido_id']      ?? 0);
        $base      = floatval($d['base_imponible'] ?? 0);
        $iva_pct   = floatval($d['iva_porcentaje'] ?? 21);
        $fecha     = trim($d['fecha_emision']    ?? date('Y-m-d'));

        if (!$pedido_id) {
            http_response_code(400);
            echo json_encode(["error" => "pedido_id requerido"]);
            exit();
        }

        $iva_imp = round($base * $iva_pct / 100, 2);
        $total   = round($base + $iva_imp, 2);
        $year    = date('Y', strtotime($fecha));
        $seq_r   = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_factura,'-',-1) AS UNSIGNED)),0)+1 AS seq FROM facturas WHERE numero_factura LIKE 'FAC-$year-%'");
        $seq     = intval($seq_r->fetch_assoc()['seq']);
        $num     = "FAC-$year-" . str_pad($seq, 5, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO facturas (pedido_id, numero_factura, fecha_emision, base_imponible, iva_porcentaje, iva_importe, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdddd", $pedido_id, $num, $fecha, $base, $iva_pct, $iva_imp, $total);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id, "numero" => $num]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $d          = json_decode(file_get_contents("php://input"), true);
        $id         = intval($d['id']             ?? 0);
        $base       = floatval($d['base_imponible'] ?? 0);
        $iva_p      = floatval($d['iva_porcentaje'] ?? 21);
        $iva_i      = round($base * $iva_p / 100, 2);
        $total      = round($base + $iva_i, 2);
        $pagada     = intval($d['pagada']         ?? 0);
        $fecha_pago = $pagada ? ($d['fecha_pago'] ?? date('Y-m-d')) : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID requerido"]);
            exit();
        }

        $stmt = $conn->prepare("UPDATE facturas SET base_imponible=?, iva_porcentaje=?, iva_importe=?, total=?, pagada=?, fecha_pago=? WHERE id=?");
        $stmt->bind_param("ddddisi", $base, $iva_p, $iva_i, $total, $pagada, $fecha_pago, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Metodo no permitido"]);
}
$conn->close();
?>