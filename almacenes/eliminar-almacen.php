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
        'message' => 'No tiene permisos para eliminar almacenes.'
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
        'message' => 'ID de almacén no proporcionado.'
    ]);
    exit();
}

$almacen_id = filter_var($input['id'], FILTER_VALIDATE_INT);

if (!$almacen_id || $almacen_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de almacén no válido.'
    ]);
    exit();
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar que el almacén existe
    $sql_check = "SELECT id, nombre FROM almacenes WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $almacen_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El almacén no existe.'
        ]);
        exit();
    }
    
    $almacen = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar si hay usuarios asignados a este almacén
    $sql_users = "SELECT COUNT(*) as total FROM usuarios WHERE almacen_id = ?";
    $stmt_users = $conn->prepare($sql_users);
    $stmt_users->bind_param("i", $almacen_id);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    $users_count = $result_users->fetch_assoc()['total'];
    $stmt_users->close();
    
    if ($users_count > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar el almacén. Hay {$users_count} usuario(s) asignado(s) a este almacén."
        ]);
        exit();
    }
    
    // Verificar si hay productos en este almacén
    $sql_products = "SELECT COUNT(*) as total FROM productos WHERE almacen_id = ?";
    $stmt_products = $conn->prepare($sql_products);
    $stmt_products->bind_param("i", $almacen_id);
    $stmt_products->execute();
    $result_products = $stmt_products->get_result();
    $products_count = $result_products->fetch_assoc()['total'];
    $stmt_products->close();
    
    if ($products_count > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar el almacén. Hay {$products_count} producto(s) registrado(s) en este almacén."
        ]);
        exit();
    }
    
    // Verificar si hay solicitudes de transferencia relacionadas
    $sql_requests = "SELECT COUNT(*) as total FROM solicitudes_transferencia 
                     WHERE almacen_origen = ? OR almacen_destino = ?";
    $stmt_requests = $conn->prepare($sql_requests);
    $stmt_requests->bind_param("ii", $almacen_id, $almacen_id);
    $stmt_requests->execute();
    $result_requests = $stmt_requests->get_result();
    $requests_count = $result_requests->fetch_assoc()['total'];
    $stmt_requests->close();
    
    if ($requests_count > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar el almacén. Hay {$requests_count} solicitud(es) de transferencia relacionada(s)."
        ]);
        exit();
    }
    
    // Si llegamos aquí, es seguro eliminar el almacén
    $sql_delete = "DELETE FROM almacenes WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $almacen_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            // Registrar la acción en un log (opcional)
            $usuario_id = $_SESSION["user_id"];
            $usuario_nombre = $_SESSION["user_name"];
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ELIMINAR_ALMACEN', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $detalle = "Eliminó el almacén: " . $almacen['nombre'] . " (ID: {$almacen_id})";
            $stmt_log->bind_param("is", $usuario_id, $detalle);
            $stmt_log->execute();
            $stmt_log->close();
            
            // Confirmar transacción
            $conn->commit();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => "El almacén '{$almacen['nombre']}' ha sido eliminado exitosamente."
            ]);
        } else {
            $conn->rollback();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'El almacén no pudo ser eliminado.'
            ]);
        }
    } else {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error en la base de datos al eliminar el almacén.'
        ]);
    }
    
    $stmt_delete->close();
    
} catch (Exception $e) {
    // Rollback en caso de error
    $conn->rollback();
    
    // Log del error (opcional)
    error_log("Error al eliminar almacén ID {$almacen_id}: " . $e->getMessage());
    
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