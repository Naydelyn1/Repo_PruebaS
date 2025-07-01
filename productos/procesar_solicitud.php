<?php
session_start();

// Configurar cabeceras para respuesta JSON si es AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

// Función para enviar respuestas de manera consistente
function enviarRespuesta($success, $message, $data = [], $redirect_url = null) {
    global $isAjax;
    
    if ($isAjax) {
        $response = array_merge([
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $data);
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        
        if (!$redirect_url) {
            $redirect_url = "../notificaciones/pendientes.php";
        }
        
        header("Location: " . $redirect_url);
        exit();
    }
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    enviarRespuesta(false, 'No autorizado. Debe iniciar sesión.', [], '../views/login_form.php');
}

// Verificar que sea una petición GET o POST válida
if ($_SERVER["REQUEST_METHOD"] !== "GET" && $_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    enviarRespuesta(false, 'Método no permitido.');
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

// Verificar conexión a la base de datos
if ($conn->connect_error) {
    http_response_code(500);
    error_log("Error de conexión a BD en procesar_solicitud.php: " . $conn->connect_error);
    enviarRespuesta(false, 'Error de conexión a la base de datos.');
}

$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_id = $_SESSION["user_id"];
$almacen_usuario = $_SESSION["almacen_id"] ?? null;

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id'], $_GET['accion'])) {
    http_response_code(400);
    enviarRespuesta(false, "Error: Faltan parámetros para procesar la solicitud.");
}

// Validar y sanitizar parámetros
$solicitud_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$accion = trim($_GET['accion']);

if (!$solicitud_id || $solicitud_id <= 0) {
    http_response_code(400);
    enviarRespuesta(false, "Error: ID de solicitud inválido.");
}

// Validar la acción
if (!in_array($accion, ["aprobar", "rechazar"], true)) {
    http_response_code(400);
    enviarRespuesta(false, "Error: Acción no válida.");
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Obtener información detallada de la solicitud con bloqueo FOR UPDATE
    $sql_solicitud = "SELECT st.*, 
                            p.nombre as producto_nombre,
                            p.categoria_id,
                            p.modelo,
                            p.color,
                            p.talla_dimensiones,
                            p.unidad_medida,
                            p.estado,
                            p.observaciones,
                            p.descripcion,
                            ao.nombre as almacen_origen_nombre,
                            ad.nombre as almacen_destino_nombre,
                            u.nombre as usuario_solicitante,
                            u.apellidos as usuario_apellidos
                     FROM solicitudes_transferencia st
                     JOIN productos p ON st.producto_id = p.id
                     JOIN almacenes ao ON st.almacen_origen = ao.id
                     JOIN almacenes ad ON st.almacen_destino = ad.id
                     JOIN usuarios u ON st.usuario_id = u.id
                     WHERE st.id = ? AND st.estado = 'pendiente'
                     FOR UPDATE";
    
    $stmt_solicitud = $conn->prepare($sql_solicitud);
    
    if (!$stmt_solicitud) {
        throw new Exception("Error preparando consulta de solicitud: " . $conn->error);
    }
    
    $stmt_solicitud->bind_param("i", $solicitud_id);
    $stmt_solicitud->execute();
    $result_solicitud = $stmt_solicitud->get_result();

    if ($result_solicitud->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        enviarRespuesta(false, "Error: La solicitud no existe o ya ha sido procesada.");
    }

    $solicitud = $result_solicitud->fetch_assoc();
    $stmt_solicitud->close();

    // Verificar permisos mejorado
    $tiene_permisos = false;
    
    if ($usuario_rol === 'admin') {
        $tiene_permisos = true;
    } elseif ($almacen_usuario == $solicitud['almacen_destino']) {
        $tiene_permisos = true;
    }
    
    if (!$tiene_permisos) {
        $conn->rollback();
        http_response_code(403);
        enviarRespuesta(false, "Error: No tiene permisos para procesar esta solicitud.");
    }

    // Si se aprueba, realizar la transferencia
    if ($accion === "aprobar") {
        // Verificar stock actual en almacén origen con bloqueo
        $sql_verificar = "SELECT cantidad FROM productos 
                         WHERE id = ? AND almacen_id = ? FOR UPDATE";
        $stmt_verificar = $conn->prepare($sql_verificar);
        
        if (!$stmt_verificar) {
            throw new Exception("Error preparando verificación de stock: " . $conn->error);
        }
        
        $stmt_verificar->bind_param("ii", $solicitud['producto_id'], $solicitud['almacen_origen']);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();
        
        if ($result_verificar->num_rows === 0) {
            throw new Exception("El producto ya no existe en el almacén origen.");
        }
        
        $producto_origen = $result_verificar->fetch_assoc();
        $stmt_verificar->close();
        
        // Verificar si hay suficiente stock
        if ($producto_origen['cantidad'] < $solicitud['cantidad']) {
            throw new Exception("No hay suficiente stock en el almacén origen. Stock actual: " . $producto_origen['cantidad'] . ", solicitado: " . $solicitud['cantidad']);
        }
        
        // Reducir stock en almacén origen
        $nuevo_stock_origen = $producto_origen['cantidad'] - $solicitud['cantidad'];
        $sql_reducir = "UPDATE productos SET cantidad = ? 
                       WHERE id = ? AND almacen_id = ?";
        $stmt_reducir = $conn->prepare($sql_reducir);
        
        if (!$stmt_reducir) {
            throw new Exception("Error preparando actualización de stock origen: " . $conn->error);
        }
        
        $stmt_reducir->bind_param("iii", $nuevo_stock_origen, $solicitud['producto_id'], $solicitud['almacen_origen']);
        
        if (!$stmt_reducir->execute()) {
            throw new Exception("Error al reducir stock en almacén origen: " . $stmt_reducir->error);
        }
        
        if ($stmt_reducir->affected_rows === 0) {
            throw new Exception("No se pudo actualizar el stock en el almacén origen.");
        }
        $stmt_reducir->close();
        
        // Verificar si el producto ya existe en el almacén destino (coincidencia exacta)
        $sql_existe = "SELECT id, cantidad FROM productos 
                      WHERE nombre = ? AND categoria_id = ? AND almacen_id = ? 
                      AND COALESCE(modelo, '') = COALESCE(?, '')
                      AND COALESCE(color, '') = COALESCE(?, '')
                      AND COALESCE(talla_dimensiones, '') = COALESCE(?, '')
                      FOR UPDATE";
        $stmt_existe = $conn->prepare($sql_existe);
        
        if (!$stmt_existe) {
            throw new Exception("Error preparando verificación de producto destino: " . $conn->error);
        }
        
        $stmt_existe->bind_param("sisss", 
            $solicitud['producto_nombre'], 
            $solicitud['categoria_id'], 
            $solicitud['almacen_destino'],
            $solicitud['modelo'],
            $solicitud['color'],
            $solicitud['talla_dimensiones']
        );
        $stmt_existe->execute();
        $result_existe = $stmt_existe->get_result();
        
        if ($result_existe->num_rows > 0) {
            // Actualizar cantidad si el producto ya existe
            $producto_destino = $result_existe->fetch_assoc();
            $nuevo_stock_destino = $producto_destino['cantidad'] + $solicitud['cantidad'];
            
            $sql_aumentar = "UPDATE productos SET cantidad = ? WHERE id = ?";
            $stmt_aumentar = $conn->prepare($sql_aumentar);
            
            if (!$stmt_aumentar) {
                throw new Exception("Error preparando actualización de stock destino: " . $conn->error);
            }
            
            $stmt_aumentar->bind_param("ii", $nuevo_stock_destino, $producto_destino['id']);
            
            if (!$stmt_aumentar->execute()) {
                throw new Exception("Error al aumentar stock en almacén destino: " . $stmt_aumentar->error);
            }
            
            if ($stmt_aumentar->affected_rows === 0) {
                throw new Exception("No se pudo actualizar el stock en el almacén destino.");
            }
            $stmt_aumentar->close();
            $producto_destino_id = $producto_destino['id'];
        } else {
            // Crear nuevo producto en almacén destino
            // Verificar si existe la columna fecha_registro
            $check_columns = $conn->query("SHOW COLUMNS FROM productos LIKE 'fecha_registro'");
            $has_fecha_registro = $check_columns->num_rows > 0;
            
            if ($has_fecha_registro) {
                $sql_insertar = "INSERT INTO productos (nombre, descripcion, modelo, color, talla_dimensiones, cantidad, 
                               unidad_medida, estado, observaciones, categoria_id, almacen_id, fecha_registro) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            } else {
                $sql_insertar = "INSERT INTO productos (nombre, descripcion, modelo, color, talla_dimensiones, cantidad, 
                               unidad_medida, estado, observaciones, categoria_id, almacen_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }
            
            $stmt_insertar = $conn->prepare($sql_insertar);
            
            if (!$stmt_insertar) {
                throw new Exception("Error preparando inserción de producto: " . $conn->error);
            }
            
            $stmt_insertar->bind_param("sssssisssii", 
                $solicitud['producto_nombre'],
                $solicitud['descripcion'],
                $solicitud['modelo'],
                $solicitud['color'],
                $solicitud['talla_dimensiones'],
                $solicitud['cantidad'],
                $solicitud['unidad_medida'],
                $solicitud['estado'],
                $solicitud['observaciones'],
                $solicitud['categoria_id'],
                $solicitud['almacen_destino']
            );
            
            if (!$stmt_insertar->execute()) {
                throw new Exception("Error al crear producto en almacén destino: " . $stmt_insertar->error);
            }
            
            $producto_destino_id = $conn->insert_id;
            $stmt_insertar->close();
        }
        $stmt_existe->close();
        
        // Registrar el movimiento en la tabla de movimientos
        // Verificar estructura de la tabla movimientos
        $check_mov_columns = $conn->query("SHOW COLUMNS FROM movimientos");
        $mov_columns = [];
        while ($col = $check_mov_columns->fetch_assoc()) {
            $mov_columns[] = $col['Field'];
        }
        
        // Adaptar la consulta según las columnas disponibles
        $descripcion_movimiento = "Transferencia aprobada desde solicitud #{$solicitud_id}";
        
        if (in_array('fecha_movimiento', $mov_columns)) {
            $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, descripcion, fecha_movimiento)
                              VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado', ?, NOW())";
        } else if (in_array('fecha', $mov_columns)) {
            $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, descripcion, fecha)
                              VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado', ?, NOW())";
        } else {
            $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, descripcion)
                              VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado', ?)";
        }
        
        $stmt_movimiento = $conn->prepare($sql_movimiento);
        
        if (!$stmt_movimiento) {
            throw new Exception("Error preparando registro de movimiento: " . $conn->error);
        }
        
        $stmt_movimiento->bind_param("iiiiss", 
            $solicitud['producto_id'], 
            $solicitud['almacen_origen'], 
            $solicitud['almacen_destino'], 
            $solicitud['cantidad'], 
            $usuario_id,
            $descripcion_movimiento
        );
        
        if (!$stmt_movimiento->execute()) {
            throw new Exception("Error al registrar movimiento: " . $stmt_movimiento->error);
        }
        $stmt_movimiento->close();
    }
    
    // Actualizar estado de la solicitud
    $nuevo_estado = ($accion === "aprobar") ? "aprobada" : "rechazada";
    $sql_actualizar = "UPDATE solicitudes_transferencia 
                      SET estado = ?, fecha_procesamiento = NOW(), procesado_por = ? 
                      WHERE id = ? AND estado = 'pendiente'";
    $stmt_actualizar = $conn->prepare($sql_actualizar);
    
    if (!$stmt_actualizar) {
        throw new Exception("Error preparando actualización de solicitud: " . $conn->error);
    }
    
    $stmt_actualizar->bind_param("sii", $nuevo_estado, $usuario_id, $solicitud_id);
    
    if (!$stmt_actualizar->execute()) {
        throw new Exception("Error al actualizar estado de solicitud: " . $stmt_actualizar->error);
    }
    
    if ($stmt_actualizar->affected_rows === 0) {
        throw new Exception("La solicitud ya fue procesada por otro usuario.");
    }
    $stmt_actualizar->close();
    
    // Registrar en log de actividad (verificar si existe la tabla)
    try {
        $check_logs = $conn->query("SHOW TABLES LIKE 'logs_actividad'");
        if ($check_logs && $check_logs->num_rows > 0) {
            $detalle_log = ($accion === "aprobar") 
                ? "Aprobó transferencia de {$solicitud['cantidad']} unidades de '{$solicitud['producto_nombre']}' desde {$solicitud['almacen_origen_nombre']} hacia {$solicitud['almacen_destino_nombre']}"
                : "Rechazó transferencia de {$solicitud['cantidad']} unidades de '{$solicitud['producto_nombre']}' desde {$solicitud['almacen_origen_nombre']} hacia {$solicitud['almacen_destino_nombre']}";
            
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, ?, ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            
            if ($stmt_log) {
                $accion_log = strtoupper($accion) . "_TRANSFERENCIA";
                $stmt_log->bind_param("iss", $usuario_id, $accion_log, $detalle_log);
                $stmt_log->execute();
                $stmt_log->close();
            }
        }
    } catch (Exception $e) {
        // No es crítico si falla el log
        error_log("Error al registrar log de actividad (no crítico): " . $e->getMessage());
    }
    
    // Confirmar la transacción
    $conn->commit();
    
    $success_msg = "✅ La solicitud ha sido " . ($accion === "aprobar" ? "aprobada" : "rechazada") . " correctamente.";
    
    // Preparar datos adicionales para la respuesta
    $response_data = [
        'accion' => $accion,
        'solicitud_id' => $solicitud_id,
        'producto' => $solicitud['producto_nombre'],
        'cantidad' => $solicitud['cantidad'],
        'almacen_origen' => $solicitud['almacen_origen_nombre'],
        'almacen_destino' => $solicitud['almacen_destino_nombre']
    ];
    
    // Determinar URL de redirección
    $redirect_url = "../notificaciones/pendientes.php";
    if (isset($_GET['redirect'])) {
        $allowed_redirects = ['historial', 'dashboard'];
        if (in_array($_GET['redirect'], $allowed_redirects)) {
            $redirect_url = $_GET['redirect'] === 'historial' 
                ? "../notificaciones/historial.php" 
                : "../dashboard.php";
        }
    }
    
    enviarRespuesta(true, $success_msg, $response_data, $redirect_url);
    
} catch (mysqli_sql_exception $e) {
    // Revertir cambios en caso de error de BD
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error de BD en procesar_solicitud.php: " . $e->getMessage() . " | Usuario: " . ($_SESSION["user_id"] ?? 'desconocido') . " | Solicitud: " . ($solicitud_id ?? 'desconocido'));
    
    // Manejar errores específicos
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $error_msg = "Error: Ya existe un registro duplicado. Inténtelo nuevamente.";
    } elseif (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        $error_msg = "Error de integridad de datos. Contacte al administrador.";
    } else {
        $error_msg = "Error de base de datos. Inténtelo más tarde.";
    }
    
    http_response_code(500);
    enviarRespuesta(false, $error_msg);
    
} catch (Exception $e) {
    // Revertir cambios en caso de cualquier otro error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error general en procesar_solicitud.php: " . $e->getMessage() . " | Usuario: " . ($_SESSION["user_id"] ?? 'desconocido') . " | Solicitud: " . ($solicitud_id ?? 'desconocido'));
    
    http_response_code(500);
    enviarRespuesta(false, $e->getMessage());
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>