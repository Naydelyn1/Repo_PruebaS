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
    
    // NUEVA VERIFICACIÓN: Movimientos que hacen referencia al almacén
    $sql_movements = "SELECT COUNT(*) as total FROM movimientos 
                     WHERE almacen_origen = ? OR almacen_destino = ?";
    $stmt_movements = $conn->prepare($sql_movements);
    $stmt_movements->bind_param("ii", $almacen_id, $almacen_id);
    $stmt_movements->execute();
    $result_movements = $stmt_movements->get_result();
    $movements_count = $result_movements->fetch_assoc()['total'];
    $stmt_movements->close();
    
    // NUEVA VERIFICACIÓN: Entregas de uniformes desde este almacén
    $sql_deliveries = "SELECT COUNT(*) as total FROM entrega_uniformes WHERE almacen_id = ?";
    $stmt_deliveries = $conn->prepare($sql_deliveries);
    $stmt_deliveries->bind_param("i", $almacen_id);
    $stmt_deliveries->execute();
    $result_deliveries = $stmt_deliveries->get_result();
    $deliveries_count = $result_deliveries->fetch_assoc()['total'];
    $stmt_deliveries->close();
    
    // Verificar si hay solicitudes de transferencia relacionadas
    $sql_requests = "SELECT COUNT(*) as total FROM solicitudes_transferencia 
                     WHERE almacen_origen = ? OR almacen_destino = ?";
    $stmt_requests = $conn->prepare($sql_requests);
    $stmt_requests->bind_param("ii", $almacen_id, $almacen_id);
    $stmt_requests->execute();
    $result_requests = $stmt_requests->get_result();
    $requests_count = $result_requests->fetch_assoc()['total'];
    $stmt_requests->close();
    
    // EVALUACIÓN INTELIGENTE: Permitir eliminación con limpieza de datos
    $hasActiveRelations = ($products_count > 0 || $users_count > 0);
    $hasHistoricalData = ($movements_count > 0 || $deliveries_count > 0 || $requests_count > 0);
    
    if ($hasActiveRelations) {
        $conn->rollback();
        http_response_code(409);
        
        $reasons = [];
        if ($users_count > 0) $reasons[] = "{$users_count} usuario(s) asignado(s)";
        if ($products_count > 0) $reasons[] = "{$products_count} producto(s) registrado(s)";
        
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar el almacén. Tiene: " . implode(", ", $reasons) . ". Debe reasignar o eliminar estos elementos primero."
        ]);
        exit();
    }
    
    // Si solo hay datos históricos, ofrecer la opción de limpiar
    if ($hasHistoricalData) {
        // Primero limpiamos las referencias históricas estableciendo NULL donde sea posible
        
        // 1. Actualizar movimientos (permitir NULL en origen/destino para mantener historial)
        if ($movements_count > 0) {
            $sql_update_movements = "UPDATE movimientos 
                                   SET almacen_origen = NULL 
                                   WHERE almacen_origen = ?";
            $stmt_update = $conn->prepare($sql_update_movements);
            $stmt_update->bind_param("i", $almacen_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            $sql_update_movements2 = "UPDATE movimientos 
                                    SET almacen_destino = NULL 
                                    WHERE almacen_destino = ?";
            $stmt_update2 = $conn->prepare($sql_update_movements2);
            $stmt_update2->bind_param("i", $almacen_id);
            $stmt_update2->execute();
            $stmt_update2->close();
        }
        
        // 2. Las entregas de uniformes y solicitudes de transferencia se mantienen como están
        // ya que son datos históricos importantes
        
        // Para este caso específico, informamos que hay datos históricos pero procedemos
        $historical_message = "";
        if ($movements_count > 0) $historical_message .= "{$movements_count} movimiento(s), ";
        if ($deliveries_count > 0) $historical_message .= "{$deliveries_count} entrega(s), ";
        if ($requests_count > 0) $historical_message .= "{$requests_count} solicitud(es), ";
        
        $historical_message = rtrim($historical_message, ', ');
    }
    
    // Si llegamos aquí, es seguro eliminar el almacén
    $sql_delete = "DELETE FROM almacenes WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $almacen_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            // Registrar la acción en un log
            $usuario_id = $_SESSION["user_id"];
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ELIMINAR_ALMACEN', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $detalle = "Eliminó el almacén: " . $almacen['nombre'] . " (ID: {$almacen_id})";
            
            // Agregar información sobre datos históricos si existían
            if (!empty($historical_message)) {
                $detalle .= ". Referencias históricas actualizadas: " . $historical_message;
            }
            
            $stmt_log->bind_param("is", $usuario_id, $detalle);
            $stmt_log->execute();
            $stmt_log->close();
            
            // Confirmar transacción
            $conn->commit();
            
            $response_message = "El almacén '{$almacen['nombre']}' ha sido eliminado exitosamente.";
            if (!empty($historical_message)) {
                $response_message .= " Se actualizaron las referencias en: " . $historical_message . ".";
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $response_message
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
    
    // Log del error
    error_log("Error al eliminar almacén ID {$almacen_id}: " . $e->getMessage());
    
    // Verificar si es un error de constraint específico
    if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
        strpos($e->getMessage(), 'FOREIGN_KEY_CONSTRAINT_FAILS') !== false) {
        
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar el almacén debido a restricciones de integridad. Verifique que no haya datos relacionados.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor. Por favor, inténtelo más tarde.'
        ]);
    }
} finally {
    // Cerrar conexión
    if (isset($conn)) {
        $conn->close();
    }
}
?>