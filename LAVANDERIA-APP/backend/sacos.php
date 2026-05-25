<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['pedido_id'])) {
            $pedido_id = intval($_GET['pedido_id']);
            $stmt = $conn->prepare("SELECT s.*, t.nombre AS tipo_ropa FROM sacos s JOIN tipos_ropa t ON s.tipo_ropa_id = t.id WHERE s.pedido_id = ? ORDER BY s.id");
            $stmt->bind_param("i", $pedido_id);
            $stmt->execute();
            $data = [];
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        } else {
            $res = $conn->query("SELECT s.*, t.nombre AS tipo_ropa, p.fecha_entrada, CONCAT(c.nombre,' ',c.apellidos) AS cliente FROM sacos s JOIN tipos_ropa t ON s.tipo_ropa_id = t.id JOIN pedidos p ON s.pedido_id = p.id JOIN clientes c ON p.cliente_id = c.id ORDER BY p.fecha_entrada DESC");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        break;

    case 'POST':
        $d            = json_decode(file_get_contents("php://input"), true);
        $pedido_id    = intval($d['pedido_id']    ?? 0);
        $tipo_ropa_id = intval($d['tipo_ropa_id'] ?? 1);
        $peso_kg      = floatval($d['peso_kg']    ?? 0);
        if (!$pedido_id || $peso_kg <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "pedido_id y peso_kg son obligatorios"]);
            exit();
        }
        $res   = $conn->query("SELECT precio_kg FROM tipos_ropa WHERE id = $tipo_ropa_id");
        $tipo  = $res->fetch_assoc();
        $precio = $tipo ? floatval($tipo['precio_kg']) : 1.50;

        $stmt = $conn->prepare("INSERT INTO sacos (pedido_id, tipo_ropa_id, peso_kg, precio_kg) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iidd", $pedido_id, $tipo_ropa_id, $peso_kg, $precio);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $d         = json_decode(file_get_contents("php://input"), true);
        $id        = intval($d['id']        ?? 0);
        $pedido_id = intval($d['pedido_id'] ?? 0);
        if (!$id || !$pedido_id) { http_response_code(400); echo json_encode(["error"=>"id y pedido_id requeridos"]); exit(); }
        $stmt = $conn->prepare("UPDATE sacos SET pedido_id=? WHERE id=?");
        $stmt->bind_param("ii", $pedido_id, $id);
        echo $stmt->execute() ? json_encode(["ok"=>true]) : json_encode(["error"=>$conn->error]);
        break;

    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM sacos WHERE id = ?");
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