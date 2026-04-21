<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT v.*, p.cliente_id FROM v_pedidos_resumen v JOIN pedidos p ON p.id = v.pedido_id WHERE v.pedido_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } else {
            $res = $conn->query("SELECT v.*, p.cliente_id FROM v_pedidos_resumen v JOIN pedidos p ON p.id = v.pedido_id ORDER BY v.fecha_entrada DESC");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        break;

    case 'POST':
        $d           = json_decode(file_get_contents("php://input"), true);
        $cliente_id  = intval($d['cliente_id'] ?? 0);
        $fecha_est   = trim($d['fecha_estimada'] ?? '');
        $obs         = trim($d['observaciones']  ?? '');
        if (!$cliente_id) {
            http_response_code(400);
            echo json_encode(["error" => "cliente_id requerido"]);
            exit();
        }
        // Obtener empleado_id real
        $res_emp     = $conn->query("SELECT id FROM usuarios WHERE activo = 1 ORDER BY id LIMIT 1");
        $emp         = $res_emp->fetch_assoc();
        $empleado_id = $emp ? intval($emp['id']) : 1;

        $stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, empleado_id, fecha_estimada, observaciones) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $cliente_id, $empleado_id, $fecha_est, $obs);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $d        = json_decode(file_get_contents("php://input"), true);
        $id       = intval($d['id']        ?? 0);
        $estado   = intval($d['estado_id'] ?? 1);
        $fecha_e  = trim($d['fecha_entrega']   ?? '');
        $obs      = trim($d['observaciones']   ?? '');
        $stmt = $conn->prepare("UPDATE pedidos SET estado_id=?, fecha_entrega=?, observaciones=? WHERE id=?");
        $stmt->bind_param("issi", $estado, $fecha_e, $obs, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
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