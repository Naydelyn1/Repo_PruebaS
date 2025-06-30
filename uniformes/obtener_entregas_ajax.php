<?php
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once "../config/database.php";

// Verificar si es una solicitud AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no permitido']);
    exit();
}

$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Validar el almacén
$almacen_id = $_GET['almacen_id'] ?? null;

if (!$almacen_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de almacén no proporcionado']);
    exit();
}

// Si no es admin, solo puede ver su propio almacén
if ($usuario_rol != 'admin' && $almacen_id != $usuario_almacen_id) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado para ver este almacén']);
    exit();
}

// Función para obtener entregas (igual que en el script anterior)
function obtenerEntregasPorAlmacen($conn, $almacen_id) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        WHERE 
            eu.almacen_id = ?
        ORDER BY 
            eu.fecha_entrega DESC
    ';

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $entregasAgrupadas = [];
    while ($row = $result->fetch_assoc()) {
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'] . '|' . $row['almacen_nombre'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'productos' => []
            ];
        }
        
        $productoExistente = false;
        foreach ($entregasAgrupadas[$key]['productos'] as &$producto) {
            if ($producto['nombre'] === $row['producto_nombre']) {
                $producto['cantidad'] += $row['cantidad'];
                $productoExistente = true;
                break;
            }
        }
        
        if (!$productoExistente) {
            $entregasAgrupadas[$key]['productos'][] = [
                'nombre' => $row['producto_nombre'],
                'cantidad' => $row['cantidad']
            ];
        }
    }

    return array_values($entregasAgrupadas);
}

// Obtener y devolver entregas
$entregas = obtenerEntregasPorAlmacen($conn, $almacen_id);
echo json_encode($entregas);
exit();