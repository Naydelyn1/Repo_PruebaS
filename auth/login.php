<?php
// auth/login.php - Versión con debug
session_start();
require_once __DIR__ . "/../config/database.php";

// Habilitar logging de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/login_debug.log');

// Función para logging
function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/login_debug.log', $logMessage, FILE_APPEND | LOCK_EX);
}

logDebug("=== INICIO DEL PROCESO DE LOGIN ===");
logDebug("Método: " . $_SERVER["REQUEST_METHOD"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    logDebug("Datos POST recibidos: " . print_r($_POST, true));
    
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];
    
    logDebug("Correo procesado: " . $correo);
    logDebug("Password longitud: " . strlen($password));

    // Validación de campos vacíos
    if (empty($correo) || empty($password)) {
        logDebug("ERROR: Campos vacíos");
        $error = "empty_fields";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    // Validar formato de email
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        logDebug("ERROR: Email inválido");
        $error = "invalid_email";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    // Verificar conexión a la base de datos
    if (!$conn) {
        logDebug("ERROR: No hay conexión a la base de datos");
        $error = "system_error";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }
    
    logDebug("Conexión a BD exitosa");

    // Consulta SQL
    $sql = "SELECT id, nombre, apellidos, contrasena, rol, almacen_id FROM usuarios WHERE correo = ? AND estado = 'activo'";
    logDebug("SQL Query: " . $sql);
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        logDebug("ERROR: Error en prepare statement: " . $conn->error);
        $error = "system_error";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }

    $stmt->bind_param("s", $correo);
    $executeResult = $stmt->execute();
    
    if (!$executeResult) {
        logDebug("ERROR: Error en execute: " . $stmt->error);
        $error = "system_error";
        header("Location: ../views/login_form.php?error=" . urlencode($error));
        exit();
    }
    
    $result = $stmt->get_result();
    logDebug("Número de filas encontradas: " . $result->num_rows);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        logDebug("Usuario encontrado: " . $user['nombre'] . " " . $user['apellidos']);
        
        // Verificar la contraseña
        $passwordCheck = password_verify($password, $user["contrasena"]);
        logDebug("Verificación de contraseña: " . ($passwordCheck ? "EXITOSA" : "FALLIDA"));
        
        if ($passwordCheck) {
            logDebug("=== LOGIN EXITOSO ===");
            
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            logDebug("ID de sesión regenerado");
            
            // Guardar datos en la sesión
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["nombre"] . " " . $user["apellidos"];
            $_SESSION["user_role"] = $user["rol"];
            $_SESSION["almacen_id"] = $user["almacen_id"];
            $_SESSION["rol"] = $user["rol"];
            
            logDebug("Datos de sesión guardados:");
            logDebug("- user_id: " . $_SESSION["user_id"]);
            logDebug("- user_name: " . $_SESSION["user_name"]);
            logDebug("- user_role: " . $_SESSION["user_role"]);
            logDebug("- almacen_id: " . $_SESSION["almacen_id"]);
            
            // Verificar si el archivo dashboard.php existe
            $dashboardPath = __DIR__ . "/../dashboard.php";
            if (file_exists($dashboardPath)) {
                logDebug("Dashboard encontrado en: " . $dashboardPath);
            } else {
                logDebug("ERROR: Dashboard NO encontrado en: " . $dashboardPath);
            }
            
            logDebug("Redirigiendo a dashboard...");
            header("Location: ../dashboard.php");
            exit();
        } else {
            logDebug("ERROR: Contraseña incorrecta");
            $error = "invalid_credentials";
        }
    } else {
        logDebug("ERROR: Usuario no encontrado o inactivo");
        $error = "invalid_credentials";
    }

    $stmt->close();
    $conn->close();
    logDebug("Conexión a BD cerrada");
    
    logDebug("Redirigiendo con error: " . $error);
    header("Location: ../views/login_form.php?error=" . urlencode($error));
    exit();
} else {
    logDebug("Acceso directo sin POST - Redirigiendo a login");
    header("Location: ../views/login_form.php");
    exit();
}
?>