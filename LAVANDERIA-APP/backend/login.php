<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once 'config.php';
ob_clean();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Email y contrasena requeridos"]);
    exit();
}

$email    = trim($data['email']);
$password = $data['password'];

$stmt = $conn->prepare("SELECT id, nombre, apellidos, password_hash, rol_id FROM usuarios WHERE email = ? AND activo = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas"]);
    exit();
}

$usuario = $result->fetch_assoc();

if (!password_verify($password, $usuario['password_hash'])) {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas"]);
    exit();
}

// Obtener nombre del rol
$stmtRol = $conn->prepare("SELECT nombre FROM roles WHERE id = ?");
$stmtRol->bind_param("i", $usuario['rol_id']);
$stmtRol->execute();
$rol = $stmtRol->get_result()->fetch_assoc();

// Actualizar ultimo login
$stmtUpdate = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
$stmtUpdate->bind_param("i", $usuario['id']);
$stmtUpdate->execute();

// Guardar sesion
session_start();
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['rol']        = $rol['nombre'];

echo json_encode([
    "ok"       => true,
    "nombre"   => $usuario['nombre'] . " " . $usuario['apellidos'],
    "email"    => $email,
    "rol"      => $rol['nombre']
]);

$stmt->close();
$conn->close();
?>