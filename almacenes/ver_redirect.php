<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Verificar permisos: admin puede ver cualquier almacén, usuario solo su almacén asignado
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Manejar petición POST (navegación normal)
if (isset($_POST['view_almacen_id'])) {
    $almacen_id = (int)$_POST['view_almacen_id'];
    
    // Si no es admin, verificar que solo pueda acceder a su almacén asignado
    if ($usuario_rol != 'admin' && $usuario_almacen_id != $almacen_id) {
        $_SESSION['error'] = "No tienes permiso para acceder a este almacén";
        header("Location: listar.php");
        exit();
    }
    
    $_SESSION['view_almacen_id'] = $almacen_id;
    header("Location: ver-almacen.php");
    exit();
}

// Manejar petición GET (navegación directa desde contexto guardado)
if (isset($_GET['id'])) {
    $almacen_id = (int)$_GET['id'];
    
    // Si no es admin, verificar que solo pueda acceder a su almacén asignado
    if ($usuario_rol != 'admin' && $usuario_almacen_id != $almacen_id) {
        $_SESSION['error'] = "No tienes permiso para acceder a este almacén";
        header("Location: listar.php");
        exit();
    }
    
    $_SESSION['view_almacen_id'] = $almacen_id;
    header("Location: ver-almacen.php");
    exit();
}

// Si no hay datos válidos, redirigir a la lista
header("Location: listar.php");
exit();
?>