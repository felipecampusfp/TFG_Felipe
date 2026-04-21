<?php
// Ejecuta este archivo UNA SOLA VEZ para crear los usuarios de prueba
// Luego puedes borrarlo o moverlo fuera de htdocs

require_once 'config.php';

$usuarios = [
    [
        'nombre'    => 'Admin',
        'apellidos' => 'Sistema',
        'email'     => 'admin@lavanderia.com',
        'password'  => 'Admin1234!',
        'rol_id'    => 1
    ],
    [
        'nombre'    => 'Empleado',
        'apellidos' => 'Demo',
        'email'     => 'empleado@lavanderia.com',
        'password'  => 'Empleado2024!',
        'rol_id'    => 2
    ],
];

// Borrar usuarios existentes para evitar duplicados
$conn->query("DELETE FROM usuarios");

foreach ($usuarios as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellidos, email, password_hash, rol_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $u['nombre'], $u['apellidos'], $u['email'], $hash, $u['rol_id']);
    if ($stmt->execute()) {
        echo "Usuario creado: " . $u['email'] . "<br>";
    } else {
        echo "Error con: " . $u['email'] . " — " . $conn->error . "<br>";
    }
}

echo "<br><strong>Listo. Ahora borra este archivo (setup.php).</strong>";
$conn->close();
?>