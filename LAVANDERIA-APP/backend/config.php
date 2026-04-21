<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host     = "localhost";
$usuario  = "root";
$password = "";
$base     = "lavanderia";

$conn = new mysqli($host, $usuario, $password, $base);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexion: " . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8mb4");
?>