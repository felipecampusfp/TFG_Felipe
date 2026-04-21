<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['stock_bajo'])) {
            $res = $conn->query("SELECT * FROM v_productos_stock_bajo");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        } elseif (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias_producto c ON p.categoria_id = c.id WHERE p.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } else {
            $res = $conn->query("SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias_producto c ON p.categoria_id = c.id WHERE p.activo = 1 ORDER BY p.nombre");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        break;

    case 'POST':
        $d            = json_decode(file_get_contents("php://input"), true);
        $categoria_id = intval($d['categoria_id']   ?? 1);
        $nombre       = trim($d['nombre']           ?? '');
        $stock        = intval($d['stock_actual']   ?? 0);
        $minimo       = intval($d['stock_minimo']   ?? 5);
        $precio       = floatval($d['precio_unidad'] ?? 0);
        if (!$nombre) {
            http_response_code(400);
            echo json_encode(["error" => "Nombre obligatorio"]);
            exit();
        }
        $stmt = $conn->prepare("INSERT INTO productos (categoria_id, nombre, stock_actual, stock_minimo, precio_unidad) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiid", $categoria_id, $nombre, $stock, $minimo, $precio);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $d      = json_decode(file_get_contents("php://input"), true);
        $id     = intval($d['id']             ?? 0);
        $nombre = trim($d['nombre']           ?? '');
        $stock  = intval($d['stock_actual']   ?? 0);
        $minimo = intval($d['stock_minimo']   ?? 5);
        $precio = floatval($d['precio_unidad'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID requerido"]);
            exit();
        }
        $stmt = $conn->prepare("UPDATE productos SET nombre=?, stock_actual=?, stock_minimo=?, precio_unidad=? WHERE id=?");
        $stmt->bind_param("siidi", $nombre, $stock, $minimo, $precio, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
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