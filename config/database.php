<?php
$host = "localhost";
$usuario = "u797525844_BdPruebaSeal"; // Cambia si usas otro usuario
$contraseña = "!6slIGRr3+:";  // Cambia si tu MySQL tiene contraseña

$base_datos = "u797525844_BdPruebaSeal";


//$base_datos = "comseproa_db";


// Conectar a la base de datos
$conn = new mysqli($host, $usuario, $contraseña, $base_datos);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
// Establecer el conjunto de caracteres
$conn->set_charset("utf8mb4");
