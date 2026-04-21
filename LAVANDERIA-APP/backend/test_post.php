<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

require_once 'config.php';

// Simular un POST de pedido
$res_cli = $conn->query("SELECT id FROM clientes WHERE activo = 1 LIMIT 1");
$cli = $res_cli->fetch_assoc();
$cliente_id = $cli['id'];

$res_emp = $conn->query("SELECT id FROM usuarios WHERE activo = 1 LIMIT 1");
$emp = $res_emp->fetch_assoc();
$empleado_id = $emp['id'];

$fecha_est = '2024-04-30';
$obs = 'test';

$stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, empleado_id, fecha_estimada, observaciones) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(["error" => "prepare: " . $conn->error]);
    exit();
}
$stmt->bind_param("iiss", $cliente_id, $empleado_id, $fecha_est, $obs);
if ($stmt->execute()) {
    $id = $conn->insert_id;
    $conn->query("DELETE FROM pedidos WHERE id = $id");
    echo json_encode(["ok" => true, "id" => $id, "cliente_id" => $cliente_id, "empleado_id" => $empleado_id]);
} else {
    echo json_encode(["error" => $stmt->error]);
}
$conn->close();
?>