<?php
require_once 'config.php';

$tipos = [
    ['Toallas medianas',  2.00, 'Toallas de tamano mediano'],
    ['Toallas grandes',   2.00, 'Toallas de tamano grande'],
    ['Alfobrines',        2.20, 'Alfombrines de bano'],
    ['Panos de cara',     1.80, 'Panos y toallitas de cara'],
    ['Baberos',           1.80, 'Baberos infantiles'],
];

foreach ($tipos as $t) {
    // Comprobar que no existe ya
    $check = $conn->prepare("SELECT id FROM tipos_ropa WHERE nombre = ?");
    $check->bind_param("s", $t[0]);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "Ya existe: {$t[0]}<br>";
        continue;
    }
    $stmt = $conn->prepare("INSERT INTO tipos_ropa (nombre, precio_kg, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $t[0], $t[1], $t[2]);
    $stmt->execute();
    echo "Creado: {$t[0]}<br>";
}

echo "<br><strong style='color:green'>Listo. Borra este archivo cuando termines.</strong>";
$conn->close();
?>