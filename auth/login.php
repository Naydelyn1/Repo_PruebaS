<?php
session_start();
require_once __DIR__ . "/../config/database.php"; // Conexión a la BD

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if (empty($correo) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    // Modificado para incluir almacen_id en la consulta
    $sql = "SELECT id, nombre, apellidos, contrasena, rol, almacen_id FROM usuarios WHERE correo = ? AND estado = 'activo'";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Error en la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verificar la contraseña
        if (password_verify($password, $user["contrasena"])) {
            // Guardar sesión
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["nombre"] . " " . $user["apellidos"];
            $_SESSION["user_role"] = $user["rol"];
            // Agregar almacen_id a la sesión
            $_SESSION["almacen_id"] = $user["almacen_id"];
            // Para mantener compatibilidad con el código existente
            $_SESSION["rol"] = $user["rol"];

            // Redirigir al dashboard
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    } else {
        $error = "Correo o contraseña incorrectos.";
    }

    $stmt->close();
    $conn->close();
    
    header("Location: ../views/login_form.php?error=" . urlencode($error));
    exit();
}
?>