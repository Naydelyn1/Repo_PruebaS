<?php
session_start();

// Configurar cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado. Debe iniciar sesión.'
    ]);
    exit();
}

// Verificar que el usuario sea administrador
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
if ($usuario_rol !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para eliminar productos.'
    ]);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit();
}

require_once "../config/database.php";

// Obtener y validar datos de entrada
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de producto no proporcionado.'
    ]);
    exit();
}

$producto_id = filter_var($input['id'], FILTER_VALIDATE_INT);

if (!$producto_id || $producto_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de producto no válido.'
    ]);
    exit();
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar que el producto existe y obtener información
    $sql_check = "SELECT id, nombre, cantidad, almacen_id FROM productos WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $producto_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El producto no existe.'
        ]);
        exit();
    }
    
    $producto = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar si el producto tiene solicitudes de transferencia pendientes
    $sql_solicitudes = "SELECT COUNT(*) as total FROM solicitudes_transferencia 
                        WHERE producto_id = ? AND estado = 'pendiente'";
    $stmt_solicitudes = $conn->prepare($sql_solicitudes);
    $stmt_solicitudes->bind_param("i", $producto_id);
    $stmt_solicitudes->execute();
    $result_solicitudes = $stmt_solicitudes->get_result();
    $solicitudes_count = $result_solicitudes->fetch_assoc()['total'];
    $stmt_solicitudes->close();
    
    if ($solicitudes_count > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar el producto. Tiene {$solicitudes_count} solicitud(es) de transferencia pendiente(s)."
        ]);
        exit();
    }
    
    // Verificar si hay movimientos relacionados (opcional - para auditoría)
    $sql_movimientos = "SELECT COUNT(*) as total FROM movimientos WHERE producto_id = ?";
    $stmt_movimientos = $conn->prepare($sql_movimientos);
    $stmt_movimientos->bind_param("i", $producto_id);
    $stmt_movimientos->execute();
    $result_movimientos = $stmt_movimientos->get_result();
    $movimientos_count = $result_movimientos->fetch_assoc()['total'];
    $stmt_movimientos->close();
    
    // Si hay movimientos, registrar la eliminación en lugar de eliminar físicamente
    if ($movimientos_count > 0) {
        // Marcar como eliminado en lugar de borrar físicamente
        $sql_soft_delete = "UPDATE productos SET estado = 'Eliminado', cantidad = 0 WHERE id = ?";
        $stmt_soft_delete = $conn->prepare($sql_soft_delete);
        $stmt_soft_delete->bind_param("i", $producto_id);
        $delete_success = $stmt_soft_delete->execute();
        $stmt_soft_delete->close();
        
        if ($delete_success) {
            // Registrar la acción en logs
            $usuario_id = $_SESSION["user_id"];
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ELIMINAR_PRODUCTO_SOFT', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $detalle = "Marcó como eliminado el producto: " . $producto['nombre'] . " (ID: {$producto_id}) del almacén ID: " . $producto['almacen_id'];
            $stmt_log->bind_param("is", $usuario_id, $detalle);
            $stmt_log->execute();
            $stmt_log->close();
            
            $mensaje = "El producto '{$producto['nombre']}' ha sido marcado como eliminado debido a que tiene historial de movimientos.";
        }
    } else {
        // Eliminación física si no hay movimientos
        $sql_delete = "DELETE FROM productos WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $producto_id);
        $delete_success = $stmt_delete->execute();
        $affected_rows = $stmt_delete->affected_rows;
        $stmt_delete->close();
        
        if ($delete_success && $affected_rows > 0) {
            // Registrar la acción en logs
            $usuario_id = $_SESSION["user_id"];
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ELIMINAR_PRODUCTO', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $detalle = "Eliminó el producto: " . $producto['nombre'] . " (ID: {$producto_id}) del almacén ID: " . $producto['almacen_id'];
            $stmt_log->bind_param("is", $usuario_id, $detalle);
            $stmt_log->execute();
            $stmt_log->close();
            
            $mensaje = "El producto '{$producto['nombre']}' ha sido eliminado exitosamente.";
        }
    }
    
    if ($delete_success) {
        // Confirmar transacción
        $conn->commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'producto_id' => $producto_id
        ]);
    } else {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar el producto.'
        ]);
    }
    
} catch (Exception $e) {
    // Rollback en caso de error
    $conn->rollback();
    
    // Log del error
    error_log("Error al eliminar producto ID {$producto_id}: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Por favor, inténtelo más tarde.'
    ]);
} finally {
    // Cerrar conexión
    if (isset($conn)) {
        $conn->close();
    }
}
?>