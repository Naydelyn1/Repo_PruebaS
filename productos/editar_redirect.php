<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Verificar permisos de administrador
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para editar productos.";
    header("Location: listar.php");
    exit();
}

// Verificar método POST y parámetros requeridos
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['producto_id'])) {
    $_SESSION['error'] = "Acceso no válido.";
    header("Location: listar.php");
    exit();
}

// Validar y almacenar datos en sesión
$producto_id = filter_var($_POST['producto_id'], FILTER_VALIDATE_INT);
if (!$producto_id || $producto_id <= 0) {
    $_SESSION['error'] = "ID de producto no válido.";
    header("Location: listar.php");
    exit();
}

// Almacenar datos en sesión
$_SESSION['edit_producto_id'] = $producto_id;

// Almacenar contexto si existe
if (isset($_POST['context_params']) && !empty($_POST['context_params'])) {
    $_SESSION['edit_context_params'] = $_POST['context_params'];
}

// Redirigir a la edición del producto
header("Location: editar.php");
exit();
?>