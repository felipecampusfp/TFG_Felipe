<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } else {
            $res = $conn->query("SELECT * FROM clientes WHERE activo = 1 ORDER BY nombre");
            $data = [];
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        break;

    case 'POST':
        $d         = json_decode(file_get_contents("php://input"), true);
        $nombre    = trim($d['nombre']    ?? '');
        $apellidos = trim($d['apellidos'] ?? '');
        $telefono  = trim($d['telefono']  ?? '');
        $email     = trim($d['email']     ?? '');
        $direccion = trim($d['direccion'] ?? '');
        if (!$nombre || !$apellidos) {
            http_response_code(400);
            echo json_encode(["error" => "Nombre y apellidos obligatorios"]);
            exit();
        }
        $stmt = $conn->prepare("INSERT INTO clientes (nombre, apellidos, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre, $apellidos, $telefono, $email, $direccion);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $d         = json_decode(file_get_contents("php://input"), true);
        $id        = intval($d['id']       ?? 0);
        $nombre    = trim($d['nombre']    ?? '');
        $apellidos = trim($d['apellidos'] ?? '');
        $telefono  = trim($d['telefono']  ?? '');
        $email     = trim($d['email']     ?? '');
        $direccion = trim($d['direccion'] ?? '');
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID requerido"]);
            exit();
        }
        $stmt = $conn->prepare("UPDATE clientes SET nombre=?, apellidos=?, telefono=?, email=?, direccion=? WHERE id=?");
        $stmt->bind_param("sssssi", $nombre, $apellidos, $telefono, $email, $direccion, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $id     = intval($_GET['id']     ?? 0);
        $forzar = intval($_GET['forzar'] ?? 0);

        if ($forzar) {
            // Borrado completo en cascada
            // 1. Obtener pedidos del cliente
            $resPed = $conn->query("SELECT id FROM pedidos WHERE cliente_id = $id");
            $pedidoIds = [];
            while ($row = $resPed->fetch_assoc()) $pedidoIds[] = $row['id'];

            if (!empty($pedidoIds)) {
                $ids = implode(',', $pedidoIds);
                // 2. Eliminar sacos
                $conn->query("DELETE FROM sacos WHERE pedido_id IN ($ids)");
                // 3. Eliminar facturas
                $conn->query("DELETE FROM facturas WHERE pedido_id IN ($ids)");
                // 4. Eliminar pedidos
                $conn->query("DELETE FROM pedidos WHERE id IN ($ids)");
            }
            // 5. Eliminar cliente
            $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
        } else {
            // Baja logica
            $stmt = $conn->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
        }

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