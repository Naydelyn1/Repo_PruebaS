<?php
session_start();

// Configurar cabeceras para respuesta JSON si es AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'No autorizado. Debe iniciar sesión.']);
        exit();
    }
    header("Location: ../views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_id = $_SESSION["user_id"];

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id'], $_GET['accion'])) {
    $error_msg = "Error: Faltan parámetros para procesar la solicitud.";
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }
    $_SESSION['error'] = $error_msg;
    header("Location: ../notificaciones/pendientes.php");
    exit();
}

$solicitud_id = intval($_GET['id']);
$accion = $_GET['accion'];

// Validar la acción
if ($accion !== "aprobar" && $accion !== "rechazar") {
    $error_msg = "Error: Acción no válida.";
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }
    $_SESSION['error'] = $error_msg;
    header("Location: ../notificaciones/pendientes.php");
    exit();
}

try {
    // Obtener información detallada de la solicitud
    $sql_solicitud = "SELECT st.*, 
                            p.nombre as producto_nombre,
                            p.categoria_id,
                            ao.nombre as almacen_origen_nombre,
                            ad.nombre as almacen_destino_nombre,
                            u.nombre as usuario_solicitante
                     FROM solicitudes_transferencia st
                     JOIN productos p ON st.producto_id = p.id
                     JOIN almacenes ao ON st.almacen_origen = ao.id
                     JOIN almacenes ad ON st.almacen_destino = ad.id
                     JOIN usuarios u ON st.usuario_id = u.id
                     WHERE st.id = ? AND st.estado = 'pendiente'";
    
    $stmt_solicitud = $conn->prepare($sql_solicitud);
    $stmt_solicitud->bind_param("i", $solicitud_id);
    $stmt_solicitud->execute();
    $result_solicitud = $stmt_solicitud->get_result();

    if ($result_solicitud->num_rows === 0) {
        $error_msg = "Error: La solicitud no existe o ya ha sido procesada.";
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        }
        $_SESSION['error'] = $error_msg;
        header("Location: ../notificaciones/pendientes.php");
        exit();
    }

    $solicitud = $result_solicitud->fetch_assoc();
    $stmt_solicitud->close();

    // Verificar permisos (solo el administrador o el responsable del almacén destino puede aprobar)
    if ($usuario_rol !== 'admin' && $_SESSION["almacen_id"] != $solicitud['almacen_destino']) {
        $error_msg = "Error: No tiene permisos para procesar esta solicitud.";
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        }
        $_SESSION['error'] = $error_msg;
        header("Location: ../notificaciones/pendientes.php");
        exit();
    }

    // Comenzar transacción
    $conn->begin_transaction();

    // Si se aprueba, realizar la transferencia
    if ($accion === "aprobar") {
        // Verificar stock actual en almacén origen
        $sql_verificar = "SELECT cantidad FROM productos 
                         WHERE id = ? AND almacen_id = ?";
        $stmt_verificar = $conn->prepare($sql_verificar);
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
            throw new Exception("No hay suficiente stock en el almacén origen. Stock actual: " . $producto_origen['cantidad']);
        }
        
        // Reducir stock en almacén origen
        $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? 
                       WHERE id = ? AND almacen_id = ?";
        $stmt_reducir = $conn->prepare($sql_reducir);
        $stmt_reducir->bind_param("iii", $solicitud['cantidad'], $solicitud['producto_id'], $solicitud['almacen_origen']);
        
        if (!$stmt_reducir->execute()) {
            throw new Exception("Error al reducir stock en almacén origen: " . $stmt_reducir->error);
        }
        $stmt_reducir->close();
        
        // Verificar si el producto ya existe en el almacén destino
        $sql_existe = "SELECT id, cantidad FROM productos 
                      WHERE nombre = ? AND categoria_id = ? AND almacen_id = ?";
        $stmt_existe = $conn->prepare($sql_existe);
        $stmt_existe->bind_param("sii", $solicitud['producto_nombre'], $solicitud['categoria_id'], $solicitud['almacen_destino']);
        $stmt_existe->execute();
        $result_existe = $stmt_existe->get_result();
        
        if ($result_existe->num_rows > 0) {
            // Actualizar cantidad si el producto ya existe
            $producto_destino = $result_existe->fetch_assoc();
            $sql_aumentar = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
            $stmt_aumentar = $conn->prepare($sql_aumentar);
            $stmt_aumentar->bind_param("ii", $solicitud['cantidad'], $producto_destino['id']);
            
            if (!$stmt_aumentar->execute()) {
                throw new Exception("Error al aumentar stock en almacén destino: " . $stmt_aumentar->error);
            }
            $stmt_aumentar->close();
        } else {
            // Crear nuevo producto en almacén destino copiando propiedades del original
            $sql_obtener = "SELECT * FROM productos WHERE id = ?";
            $stmt_obtener = $conn->prepare($sql_obtener);
            $stmt_obtener->bind_param("i", $solicitud['producto_id']);
            $stmt_obtener->execute();
            $result_obtener = $stmt_obtener->get_result();
            $producto_original = $result_obtener->fetch_assoc();
            $stmt_obtener->close();
            
            $sql_insertar = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, 
                           unidad_medida, estado, observaciones, categoria_id, almacen_id, fecha_registro) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_insertar = $conn->prepare($sql_insertar);
            $stmt_insertar->bind_param("ssssssssii", 
                $producto_original['nombre'],
                $producto_original['modelo'],
                $producto_original['color'],
                $producto_original['talla_dimensiones'],
                $solicitud['cantidad'],
                $producto_original['unidad_medida'],
                $producto_original['estado'],
                $producto_original['observaciones'],
                $producto_original['categoria_id'],
                $solicitud['almacen_destino']
            );
            
            if (!$stmt_insertar->execute()) {
                throw new Exception("Error al crear producto en almacén destino: " . $stmt_insertar->error);
            }
            $stmt_insertar->close();
        }
        $stmt_existe->close();
        
        // Registrar el movimiento en la tabla de movimientos
        $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, tipo, usuario_id, estado, fecha_movimiento)
                          VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado', NOW())";
        $stmt_movimiento = $conn->prepare($sql_movimiento);
        $stmt_movimiento->bind_param("iiiii", 
            $solicitud['producto_id'], 
            $solicitud['almacen_origen'], 
            $solicitud['almacen_destino'], 
            $solicitud['cantidad'], 
            $usuario_id
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
                      WHERE id = ?";
    $stmt_actualizar = $conn->prepare($sql_actualizar);
    $stmt_actualizar->bind_param("sii", $nuevo_estado, $usuario_id, $solicitud_id);
    
    if (!$stmt_actualizar->execute()) {
        throw new Exception("Error al actualizar estado de solicitud: " . $stmt_actualizar->error);
    }
    $stmt_actualizar->close();
    
    // Registrar en log de actividad
    $detalle_log = ($accion === "aprobar") 
        ? "Aprobó transferencia de {$solicitud['cantidad']} {$solicitud['producto_nombre']} de {$solicitud['almacen_origen_nombre']} a {$solicitud['almacen_destino_nombre']}"
        : "Rechazó transferencia de {$solicitud['cantidad']} {$solicitud['producto_nombre']} de {$solicitud['almacen_origen_nombre']} a {$solicitud['almacen_destino_nombre']}";
    
    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                VALUES (?, ?, ?, NOW())";
    $stmt_log = $conn->prepare($sql_log);
    $accion_log = strtoupper($accion) . "_TRANSFERENCIA";
    $stmt_log->bind_param("iss", $usuario_id, $accion_log, $detalle_log);
    $stmt_log->execute();
    $stmt_log->close();
    
    // Confirmar la transacción
    $conn->commit();
    
    $success_msg = "La solicitud ha sido " . ($accion === "aprobar" ? "aprobada" : "rechazada") . " correctamente.";
    
    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'message' => $success_msg,
            'accion' => $accion,
            'solicitud_id' => $solicitud_id
        ]);
        exit();
    }
    
    $_SESSION['success'] = $success_msg;
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    
    // Log del error
    error_log("Error en procesar_solicitud.php: " . $e->getMessage());
    
    $error_msg = "Error: " . $e->getMessage();
    
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }
    
    $_SESSION['error'] = $error_msg;
} finally {
    // Cerrar conexión
    if (isset($conn)) {
        $conn->close();
    }
}

// Redireccionar si no es AJAX
if (!$isAjax) {
    $redirect_url = "../notificaciones/pendientes.php";
    if (isset($_GET['redirect'])) {
        $allowed_redirects = ['historial', 'dashboard'];
        if (in_array($_GET['redirect'], $allowed_redirects)) {
            $redirect_url = $_GET['redirect'] === 'historial' 
                ? "../notificaciones/historial.php" 
                : "../dashboard.php";
        }
    }
    
    header("Location: $redirect_url");
    exit();
}
?>