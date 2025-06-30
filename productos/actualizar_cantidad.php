<?php
session_start();

// Configurar cabeceras JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($success, $message, $data = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Verificar si el usuario ha iniciado sesión
    if (!isset($_SESSION["user_id"])) {
        http_response_code(401);
        sendJsonResponse(false, 'Sesión expirada. Por favor, inicie sesión nuevamente.');
    }

    // Verificar si la solicitud es POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        sendJsonResponse(false, 'Método no permitido. Solo se acepta POST.');
    }

    // Verificar permisos del usuario
    $usuario_rol = $_SESSION["user_role"] ?? "usuario";
    if ($usuario_rol !== 'admin') {
        http_response_code(403);
        sendJsonResponse(false, 'No tiene permisos para modificar cantidades.');
    }

    // Verificar datos requeridos
    if (!isset($_POST['producto_id'], $_POST['accion'])) {
        http_response_code(400);
        sendJsonResponse(false, 'Faltan parámetros requeridos (producto_id, accion).');
    }

    $producto_id = filter_var($_POST['producto_id'], FILTER_VALIDATE_INT);
    $accion = trim($_POST['accion']);

    // Validar producto_id
    if (!$producto_id || $producto_id <= 0) {
        http_response_code(400);
        sendJsonResponse(false, 'ID de producto no válido.');
    }

    // Validar acción
    if (!in_array($accion, ['sumar', 'restar'])) {
        http_response_code(400);
        sendJsonResponse(false, 'Acción no válida. Solo se permite "sumar" o "restar".');
    }

    require_once "../config/database.php";

    // Verificar conexión a la base de datos
    if (!$conn) {
        http_response_code(500);
        sendJsonResponse(false, 'Error de conexión a la base de datos.');
    }

    // Obtener información actual del producto (usando tu estructura exacta)
    $sql_producto = "SELECT p.id, p.nombre, p.cantidad, p.almacen_id, a.nombre as almacen_nombre 
                     FROM productos p 
                     LEFT JOIN almacenes a ON p.almacen_id = a.id 
                     WHERE p.id = ?";
    
    $stmt_producto = $conn->prepare($sql_producto);
    if (!$stmt_producto) {
        http_response_code(500);
        sendJsonResponse(false, 'Error en la preparación de la consulta: ' . $conn->error);
    }

    $stmt_producto->bind_param("i", $producto_id);
    $stmt_producto->execute();
    $result = $stmt_producto->get_result();

    if ($result->num_rows === 0) {
        $stmt_producto->close();
        http_response_code(404);
        sendJsonResponse(false, 'Producto no encontrado.');
    }

    $producto = $result->fetch_assoc();
    $cantidad_actual = (int)$producto['cantidad'];
    $almacen_id = (int)$producto['almacen_id'];
    $stmt_producto->close();

    // Calcular nueva cantidad
    if ($accion === 'sumar') {
        $nueva_cantidad = $cantidad_actual + 1;
        $tipo_movimiento = 'entrada';
    } elseif ($accion === 'restar') {
        if ($cantidad_actual <= 0) {
            sendJsonResponse(false, 'No se puede reducir la cantidad. El stock ya es 0.');
        }
        $nueva_cantidad = $cantidad_actual - 1;
        $tipo_movimiento = 'salida';
    }

    // Validar límites
    if ($nueva_cantidad < 0) {
        sendJsonResponse(false, 'La cantidad no puede ser negativa.');
    }
    
    if ($nueva_cantidad > 999999) {
        sendJsonResponse(false, 'La cantidad no puede exceder 999,999 unidades.');
    }

    // Actualizar la cantidad en la base de datos
    $sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if (!$stmt_update) {
        http_response_code(500);
        sendJsonResponse(false, 'Error en la preparación de la consulta de actualización: ' . $conn->error);
    }
    
    $stmt_update->bind_param("ii", $nueva_cantidad, $producto_id);
    
    if (!$stmt_update->execute()) {
        $stmt_update->close();
        http_response_code(500);
        sendJsonResponse(false, 'Error al actualizar la cantidad en la base de datos: ' . $stmt_update->error);
    }
    
    $affected_rows = $stmt_update->affected_rows;
    $stmt_update->close();

    if ($affected_rows === 0) {
        sendJsonResponse(false, 'No se pudo actualizar el producto. Verifique que el ID sea válido.');
    }

    // Opcional: Registrar el movimiento si las tablas existen
    try {
        $usuario_id = $_SESSION["user_id"];
        $descripcion = "Ajuste manual de cantidad: {$accion} 1 unidad";
        
        // Verificar si la tabla movimientos existe y tiene las columnas necesarias
        $check_movimientos = $conn->query("SHOW TABLES LIKE 'movimientos'");
        if ($check_movimientos && $check_movimientos->num_rows > 0) {
            // Verificar estructura de la tabla movimientos
            $columns_result = $conn->query("SHOW COLUMNS FROM movimientos");
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            
            // Insertar solo si las columnas necesarias existen
            if (in_array('producto_id', $columns) && in_array('cantidad', $columns)) {
                // Usar solo columnas que sabemos que existen
                if (in_array('fecha', $columns)) {
                    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, descripcion, fecha) 
                                       VALUES (?, ?, 1, ?, ?, 'completado', ?, NOW())";
                } else if (in_array('fecha_movimiento', $columns)) {
                    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, descripcion, fecha_movimiento) 
                                       VALUES (?, ?, 1, ?, ?, 'completado', ?, NOW())";
                } else {
                    // Sin fecha
                    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, descripcion) 
                                       VALUES (?, ?, 1, ?, ?, 'completado', ?)";
                }
                
                $stmt_movimiento = $conn->prepare($sql_movimiento);
                if ($stmt_movimiento) {
                    if (strpos($sql_movimiento, 'NOW()') !== false) {
                        $stmt_movimiento->bind_param("iisis", $producto_id, $almacen_id, $tipo_movimiento, $usuario_id, $descripcion);
                    } else {
                        $stmt_movimiento->bind_param("iisis", $producto_id, $almacen_id, $tipo_movimiento, $usuario_id, $descripcion);
                    }
                    $stmt_movimiento->execute();
                    $stmt_movimiento->close();
                }
            }
        }
    } catch (Exception $e) {
        // No es crítico si falla el log de movimientos
        error_log("Error al registrar movimiento: " . $e->getMessage());
    }

    // Opcional: Registrar en log de actividad si la tabla existe
    try {
        $check_logs = $conn->query("SHOW TABLES LIKE 'logs_actividad'");
        if ($check_logs && $check_logs->num_rows > 0) {
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ACTUALIZAR_STOCK', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            if ($stmt_log) {
                $detalle = "Actualizó stock del producto '{$producto['nombre']}' de {$cantidad_actual} a {$nueva_cantidad} unidades";
                $stmt_log->bind_param("is", $usuario_id, $detalle);
                $stmt_log->execute();
                $stmt_log->close();
            }
        }
    } catch (Exception $e) {
        // No es crítico si falla el log de actividad
        error_log("Error al registrar log de actividad: " . $e->getMessage());
    }
    
    // Determinar estado del stock para respuesta
    $estado_stock = 'normal';
    if ($nueva_cantidad < 5) {
        $estado_stock = 'critico';
    } elseif ($nueva_cantidad < 10) {
        $estado_stock = 'bajo';
    }
    
    // ⭐ RESPUESTA EXITOSA CON URLs LIMPIAS OPCIONALES
    $response_data = [
        'nueva_cantidad' => $nueva_cantidad,
        'cantidad_anterior' => $cantidad_actual,
        'cambio' => $accion === 'sumar' ? '+1' : '-1',
        'estado_stock' => $estado_stock,
        'producto_nombre' => $producto['nombre'],
        'almacen_nombre' => $producto['almacen_nombre'] ?? 'Sin almacén',
        'puede_restar' => $nueva_cantidad > 0,
        'producto_id' => $producto_id
    ];
    
    sendJsonResponse(true, 'Cantidad actualizada correctamente', $response_data);

} catch (mysqli_sql_exception $e) {
    // Log del error para debugging
    error_log("Error de base de datos en actualizar_cantidad.php: " . $e->getMessage());
    
    http_response_code(500);
    sendJsonResponse(false, 'Error de base de datos. Contacte al administrador.');
    
} catch (Exception $e) {
    // Log del error
    error_log("Error general en actualizar_cantidad.php: " . $e->getMessage());
    
    http_response_code(500);
    sendJsonResponse(false, 'Error inesperado. Contacte al administrador.');
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>