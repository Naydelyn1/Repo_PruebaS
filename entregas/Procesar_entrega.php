<?php
session_start();

// Configurar cabeceras para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Función para enviar respuesta JSON y terminar
function enviarRespuesta($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    enviarRespuesta(false, 'Sesión expirada. Por favor, inicie sesión nuevamente.');
}

// Verificar que sea una petición POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    enviarRespuesta(false, 'Método no permitido. Use POST.');
}

require_once "../config/database.php";

// Verificar conexión a la base de datos
if ($conn->connect_error) {
    http_response_code(500);
    enviarRespuesta(false, 'Error de conexión a la base de datos.');
}

$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['user_role'] ?? 'usuario';
$usuario_almacen_id = $_SESSION['almacen_id'] ?? null;

try {
    // Obtener y validar datos del formulario
    $nombre_destinatario = trim($_POST['nombre_destinatario'] ?? '');
    $dni_destinatario = trim($_POST['dni_destinatario'] ?? '');
    $productos_json = $_POST['productos'] ?? '';
    
    // Validaciones básicas
    if (empty($nombre_destinatario)) {
        enviarRespuesta(false, 'El nombre del destinatario es obligatorio.');
    }
    
    if (empty($dni_destinatario) || !preg_match('/^\d{8}$/', $dni_destinatario)) {
        enviarRespuesta(false, 'El DNI debe tener exactamente 8 dígitos.');
    }
    
    if (empty($productos_json)) {
        enviarRespuesta(false, 'No se recibieron productos para entregar.');
    }
    
    // Decodificar productos
    $productos = json_decode($productos_json, true);
    if (!$productos || !is_array($productos)) {
        enviarRespuesta(false, 'Error al procesar la lista de productos.');
    }
    
    if (count($productos) === 0) {
        enviarRespuesta(false, 'Debe seleccionar al menos un producto.');
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    $productos_actualizados = [];
    $total_productos_entregados = 0;
    
    foreach ($productos as $producto) {
        // Validar datos del producto
        $producto_id = intval($producto['id'] ?? 0);
        $cantidad = intval($producto['cantidad'] ?? 0);
        $almacen_id = intval($producto['almacen'] ?? 0);
        
        if ($producto_id <= 0 || $cantidad <= 0) {
            throw new Exception('Datos de producto inválidos.');
        }
        
        // Verificar permisos del usuario para este almacén
        if ($usuario_rol !== 'admin' && $usuario_almacen_id != $almacen_id) {
            throw new Exception('No tiene permisos para entregar productos de este almacén.');
        }
        
        // Obtener información actual del producto con bloqueo
        $sql_producto = "SELECT id, nombre, cantidad, almacen_id 
                         FROM productos 
                         WHERE id = ? AND almacen_id = ? FOR UPDATE";
        $stmt = $conn->prepare($sql_producto);
        $stmt->bind_param("ii", $producto_id, $almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Producto no encontrado o no pertenece al almacén especificado.");
        }
        
        $producto_info = $result->fetch_assoc();
        $stock_actual = intval($producto_info['cantidad']);
        $stmt->close();
        
        // Verificar stock suficiente
        if ($cantidad > $stock_actual) {
            throw new Exception("Stock insuficiente para el producto '{$producto_info['nombre']}'. Disponible: {$stock_actual}, solicitado: {$cantidad}.");
        }
        
        // Reducir stock del producto
        $nuevo_stock = $stock_actual - $cantidad;
        $sql_update = "UPDATE productos SET cantidad = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $nuevo_stock, $producto_id);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar stock del producto '{$producto_info['nombre']}'.");
        }
        $stmt_update->close();
        
        // Registrar la entrega
        $sql_entrega = "INSERT INTO entrega_uniformes 
                        (usuario_responsable_id, nombre_destinatario, dni_destinatario, 
                         producto_id, cantidad, almacen_id, fecha_entrega) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt_entrega = $conn->prepare($sql_entrega);
        $stmt_entrega->bind_param("issiii", 
            $usuario_id, 
            $nombre_destinatario, 
            $dni_destinatario, 
            $producto_id, 
            $cantidad, 
            $almacen_id
        );
        
        if (!$stmt_entrega->execute()) {
            throw new Exception("Error al registrar la entrega del producto '{$producto_info['nombre']}'.");
        }
        $stmt_entrega->close();
        
        // Registrar movimiento de salida
        $sql_movimiento = "INSERT INTO movimientos 
                           (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado, fecha) 
                           VALUES (?, ?, ?, 'salida', ?, 'completado', NOW())";
        $stmt_movimiento = $conn->prepare($sql_movimiento);
        $stmt_movimiento->bind_param("iiii", $producto_id, $almacen_id, $cantidad, $usuario_id);
        
        if (!$stmt_movimiento->execute()) {
            throw new Exception("Error al registrar movimiento del producto '{$producto_info['nombre']}'.");
        }
        $stmt_movimiento->close();
        
        // Agregar a productos actualizados para la respuesta
        $productos_actualizados[] = [
            'id' => $producto_id,
            'nombre' => $producto_info['nombre'],
            'stock_anterior' => $stock_actual,
            'nuevo_stock' => $nuevo_stock,
            'cantidad_entregada' => $cantidad
        ];
        
        $total_productos_entregados += $cantidad;
    }
    
    // Registrar en log de actividad (si la tabla existe)
    try {
        $tables_result = $conn->query("SHOW TABLES LIKE 'logs_actividad'");
        if ($tables_result && $tables_result->num_rows > 0) {
            $productos_nombres = array_column($productos_actualizados, 'nombre');
            $detalle = "Entregó " . count($productos) . " tipo(s) de productos (" . $total_productos_entregados . " unidades total) a {$nombre_destinatario} (DNI: {$dni_destinatario}). Productos: " . implode(', ', array_slice($productos_nombres, 0, 3)) . (count($productos_nombres) > 3 ? '...' : '');
            
            $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                        VALUES (?, 'ENTREGA_PRODUCTOS', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("is", $usuario_id, $detalle);
            $stmt_log->execute();
            $stmt_log->close();
        }
    } catch (Exception $e) {
        // No es crítico si falla el log
        error_log("Error al registrar log de actividad (no crítico): " . $e->getMessage());
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Preparar mensaje de éxito
    $mensaje_exito = "✅ Entrega realizada con éxito.\n";
    $mensaje_exito .= "📋 Destinatario: {$nombre_destinatario} (DNI: {$dni_destinatario})\n";
    $mensaje_exito .= "📦 Productos entregados: " . count($productos) . " tipo(s) (" . $total_productos_entregados . " unidades total)\n";
    $mensaje_exito .= "📅 Fecha: " . date('d/m/Y H:i');
    
    enviarRespuesta(true, $mensaje_exito, [
        'entrega_id' => $conn->insert_id,
        'productos_actualizados' => $productos_actualizados,
        'total_productos' => count($productos),
        'total_unidades' => $total_productos_entregados,
        'destinatario' => [
            'nombre' => $nombre_destinatario,
            'dni' => $dni_destinatario
        ],
        'fecha_entrega' => date('Y-m-d H:i:s')
    ]);
    
} catch (mysqli_sql_exception $e) {
    // Rollback en caso de error de base de datos
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log del error para debugging
    error_log("Error de base de datos en procesar_entrega.php: " . $e->getMessage());
    
    // Manejar errores específicos
    if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
        http_response_code(500);
        enviarRespuesta(false, 'Error de integridad de datos. Verifique que todos los productos existan.');
    } else {
        http_response_code(500);
        enviarRespuesta(false, 'Error de base de datos. Por favor, inténtelo más tarde.');
    }
    
} catch (Exception $e) {
    // Rollback en caso de cualquier otro error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log del error
    error_log("Error general en procesar_entrega.php: " . $e->getMessage() . " | Usuario: " . ($_SESSION["user_id"] ?? 'desconocido'));
    
    http_response_code(500);
    enviarRespuesta(false, $e->getMessage());
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>