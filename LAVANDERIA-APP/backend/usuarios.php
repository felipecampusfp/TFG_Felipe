<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once 'config.php';
ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        if (isset($_GET['id'])) {
            $id   = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT u.id, u.nombre, u.apellidos, u.email, u.rol_id, u.activo, u.ultimo_login, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } else {
            $res = $conn->query("SELECT u.id, u.nombre, u.apellidos, u.email, u.rol_id, u.activo, u.ultimo_login, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id ORDER BY u.id");
            $usuarios = [];
            while ($fila = $res->fetch_assoc()) $usuarios[] = $fila;
            echo json_encode($usuarios);
        }
        break;

    case 'POST':
        $data      = json_decode(file_get_contents("php://input"), true);
        $nombre    = trim($data['nombre']    ?? '');
        $apellidos = trim($data['apellidos'] ?? '');
        $email     = trim($data['email']     ?? '');
        $password  = $data['password']       ?? '';
        $rol_id    = intval($data['rol_id']  ?? 2);

        if (!$nombre || !$email || !$password) {
            http_response_code(400);
            echo json_encode(["error" => "Nombre, email y contrasena son obligatorios"]);
            exit();
        }

        // Verificar email unico
        $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(["error" => "Ya existe un usuario con ese email"]);
            exit();
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, email, password_hash, rol_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $nombre, $apellidos, $email, $hash, $rol_id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;

    case 'PUT':
        $data      = json_decode(file_get_contents("php://input"), true);
        $id        = intval($data['id']       ?? 0);
        $nombre    = trim($data['nombre']     ?? '');
        $apellidos = trim($data['apellidos']  ?? '');
        $email     = trim($data['email']      ?? '');
        $rol_id    = intval($data['rol_id']   ?? 2);
        $activo    = intval($data['activo']   ?? 1);

        if (!$id || !$nombre || !$email) {
            http_response_code(400);
            echo json_encode(["error" => "Datos incompletos"]);
            exit();
        }

        // Si viene nueva contrasena la actualizamos tambien
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=?, rol_id=?, activo=?, password_hash=? WHERE id=?");
            $stmt->bind_param("ssssiis", $nombre, $apellidos, $email, $rol_id, $activo, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=?, rol_id=?, activo=? WHERE id=?");
            $stmt->bind_param("ssssii", $nombre, $apellidos, $email, $rol_id, $activo, $id);
        }

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
            // Borrado permanente
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        } else {
            // Baja logica
            $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
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