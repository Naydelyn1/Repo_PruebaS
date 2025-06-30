<?php
session_start();
require_once "../config/database.php";

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id'], $_GET['accion'])) {
    $_SESSION['error'] = "Error: Faltan parámetros para procesar el movimiento.";
    header("Location: movimientos_pendientes.php");
    exit();
}

$movimiento_id = intval($_GET['id']);
$accion = $_GET['accion'];

// Validar la acción
if ($accion !== "aprobar" && $accion !== "rechazar") {
    $_SESSION['error'] = "Error: Acción no válida.";
    header("Location: movimientos_pendientes.php");
    exit();
}

// Obtener información del movimiento
$sql_movimiento = "SELECT producto_id, almacen_origen, almacen_destino, cantidad, tipo 
                  FROM movimientos 
                  WHERE id = ? AND estado = 'pendiente'";
$stmt_movimiento = $conn->prepare($sql_movimiento);
$stmt_movimiento->bind_param("i", $movimiento_id);
$stmt_movimiento->execute();
$result_movimiento = $stmt_movimiento->get_result();

if ($result_movimiento->num_rows === 0) {
    $_SESSION['error'] = "Error: El movimiento no existe o ya ha sido procesado.";
    header("Location: movimientos_pendientes.php");
    exit();
}

$movimiento = $result_movimiento->fetch_assoc();
$stmt_movimiento->close();

// Comenzar transacción
$conn->begin_transaction();

try {
    // Si se aprueba, realizar el movimiento
    if ($accion === "aprobar") {
        // Procesar según el tipo de movimiento
        switch ($movimiento['tipo']) {
            case 'entrada':
                // Verificar si el producto ya existe en el almacén destino
                $sql_existe = "SELECT id, cantidad FROM productos WHERE id = ? AND almacen_id = ?";
                $stmt_existe = $conn->prepare($sql_existe);
                $stmt_existe->bind_param("ii", $movimiento['producto_id'], $movimiento['almacen_destino']);
                $stmt_existe->execute();
                $result_existe = $stmt_existe->get_result();
                
                if ($result_existe->num_rows > 0) {
                    // Actualizar cantidad si el producto ya existe
                    $sql_actualizar = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
                    $stmt_actualizar = $conn->prepare($sql_actualizar);
                    $stmt_actualizar->bind_param("ii", $movimiento['cantidad'], $movimiento['producto_id']);
                    $stmt_actualizar->execute();
                    $stmt_actualizar->close();
                } else {
                    // Podría necesitar lógica adicional para crear el producto
                    throw new Exception("El producto no existe en el almacén de destino.");
                }
                break;
                
            case 'salida':
                // Verificar stock actual
                $sql_verificar = "SELECT cantidad FROM productos WHERE id = ? AND almacen_id = ?";
                $stmt_verificar = $conn->prepare($sql_verificar);
                $stmt_verificar->bind_param("ii", $movimiento['producto_id'], $movimiento['almacen_origen']);
                $stmt_verificar->execute();
                $result_verificar = $stmt_verificar->get_result();
                
                if ($result_verificar->num_rows === 0) {
                    throw new Exception("El producto ya no existe en el almacén origen.");
                }
                
                $producto = $result_verificar->fetch_assoc();
                $stmt_verificar->close();
                
                // Verificar si hay suficiente stock
                if ($producto['cantidad'] < $movimiento['cantidad']) {
                    throw new Exception("No hay suficiente stock en el almacén origen.");
                }
                
                // Reducir stock
                $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
                $stmt_reducir = $conn->prepare($sql_reducir);
                $stmt_reducir->bind_param("iii", $movimiento['cantidad'], $movimiento['producto_id'], $movimiento['almacen_origen']);
                $stmt_reducir->execute();
                $stmt_reducir->close();
                break;
                
            case 'transferencia':
                // Similar a la lógica en procesar_solicitud.php
                // Verificar stock en origen
                $sql_verificar = "SELECT cantidad FROM productos WHERE id = ? AND almacen_id = ?";
                $stmt_verificar = $conn->prepare($sql_verificar);
                $stmt_verificar->bind_param("ii", $movimiento['producto_id'], $movimiento['almacen_origen']);
                $stmt_verificar->execute();
                $result_verificar = $stmt_verificar->get_result();
                
                if ($result_verificar->num_rows === 0) {
                    throw new Exception("El producto ya no existe en el almacén origen.");
                }
                
                $producto_origen = $result_verificar->fetch_assoc();
                $stmt_verificar->close();
                
                // Verificar si hay suficiente stock
                if ($producto_origen['cantidad'] < $movimiento['cantidad']) {
                    throw new Exception("No hay suficiente stock en el almacén origen.");
                }
                
                // Reducir stock en almacén origen
                $sql_reducir = "UPDATE productos SET cantidad = cantidad - ? WHERE id = ? AND almacen_id = ?";
                $stmt_reducir = $conn->prepare($sql_reducir);
                $stmt_reducir->bind_param("iii", $movimiento['cantidad'], $movimiento['producto_id'], $movimiento['almacen_origen']);
                $stmt_reducir->execute();
                $stmt_reducir->close();
                
                // Verificar si el producto ya existe en el almacén destino
                $sql_obtener = "SELECT * FROM productos WHERE id = ?";
                $stmt_obtener = $conn->prepare($sql_obtener);
                $stmt_obtener->bind_param("i", $movimiento['producto_id']);
                $stmt_obtener->execute();
                $result_obtener = $stmt_obtener->get_result();
                $producto_original = $result_obtener->fetch_assoc();
                $stmt_obtener->close();
                
                $sql_existe = "SELECT id, cantidad FROM productos WHERE nombre = ? 
                             AND color = ? AND talla_dimensiones = ? AND almacen_id = ?";
                $stmt_existe = $conn->prepare($sql_existe);
                $stmt_existe->bind_param("sssi", 
                    $producto_original['nombre'],
                    $producto_original['color'],
                    $producto_original['talla_dimensiones'],
                    $movimiento['almacen_destino']
                );
                $stmt_existe->execute();
                $result_existe = $stmt_existe->get_result();
                
                if ($result_existe->num_rows > 0) {
                    // Actualizar cantidad si el producto ya existe
                    $producto_destino = $result_existe->fetch_assoc();
                    $sql_aumentar = "UPDATE productos SET cantidad = cantidad + ? WHERE id = ?";
                    $stmt_aumentar = $conn->prepare($sql_aumentar);
                    $stmt_aumentar->bind_param("ii", $movimiento['cantidad'], $producto_destino['id']);
                    $stmt_aumentar->execute();
                    $stmt_aumentar->close();
                } else {
                    // Crear nuevo producto en almacén destino
                    $sql_insertar = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, 
                                   unidad_medida, estado, observaciones, categoria_id, almacen_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insertar = $conn->prepare($sql_insertar);
                    $stmt_insertar->bind_param("ssssssssii", 
                        $producto_original['nombre'],
                        $producto_original['modelo'],
                        $producto_original['color'],
                        $producto_original['talla_dimensiones'],
                        $movimiento['cantidad'],
                        $producto_original['unidad_medida'],
                        $producto_original['estado'],
                        $producto_original['observaciones'],
                        $producto_original['categoria_id'],
                        $movimiento['almacen_destino']
                    );
                    $stmt_insertar->execute();
                    $stmt_insertar->close();
                }
                break;
                
            default:
                throw new Exception("Tipo de movimiento no válido.");
        }
    }
    
    // Actualizar estado del movimiento
    $nuevo_estado = ($accion === "aprobar") ? "completado" : "rechazado";
    $sql_actualizar = "UPDATE movimientos SET estado = ? WHERE id = ?";
    $stmt_actualizar = $conn->prepare($sql_actualizar);
    $stmt_actualizar->bind_param("si", $nuevo_estado, $movimiento_id);
    $stmt_actualizar->execute();
    $stmt_actualizar->close();
    
    // Confirmar la transacción
    $conn->commit();
    
    $_SESSION['success'] = "El movimiento ha sido " . ($accion === "aprobar" ? "completado" : "rechazado") . " correctamente.";
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: movimientos_pendientes.php");
exit();
?>