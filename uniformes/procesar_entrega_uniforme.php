<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection configuration
$host = 'localhost';
$dbname = 'u797525844_comseproa_db';
$username = 'u797525844_comseproa_db';  // Update with your database username
$password = '';      // Update with your database password

// Response array
$response = [
    'success' => false,
    'message' => '',
    'error' => ''
];

try {
    // Debug: Log incoming POST data
    error_log('Incoming POST data: ' . print_r($_POST, true));
    error_log('Incoming FILES data: ' . print_r($_FILES, true));

    // Establish database connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Simulate session (remove in production and use real session management)
    $usuarioResponsableId = 8; // Hardcoded user ID for testing

    // Get form data
    $nombreDestinatario = $_POST['nombre_destinatario'] ?? '';
    $dniDestinatario = $_POST['dni_destinatario'] ?? '';
    $productoIds = $_POST['producto_id'] ?? [];
    $productoCantidades = $_POST['producto_cantidad'] ?? [];
    $productoAlmacenes = $_POST['producto_almacen'] ?? [];  // Nueva línea para obtener IDs de almacenes

    // Debug: Log processed data
    error_log('Nombre Destinatario: ' . $nombreDestinatario);
    error_log('DNI Destinatario: ' . $dniDestinatario);
    error_log('Producto IDs: ' . print_r($productoIds, true));
    error_log('Producto Cantidades: ' . print_r($productoCantidades, true));
    error_log('Producto Almacenes: ' . print_r($productoAlmacenes, true));

    // Validate required fields
    if (empty($nombreDestinatario) || empty($dniDestinatario) || empty($productoIds)) {
        throw new Exception('Datos incompletos para la entrega');
    }

    // Prepare statements
    $stmtEntregaUniforme = $conn->prepare('
        INSERT INTO entrega_uniformes 
        (usuario_responsable_id, nombre_destinatario, dni_destinatario, producto_id, cantidad, almacen_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    $stmtActualizarStock = $conn->prepare('
        UPDATE productos 
        SET cantidad = cantidad - ? 
        WHERE id = ? AND almacen_id = ?
    ');

    // Start transaction
    $conn->beginTransaction();

    // Process each product
    foreach ($productoIds as $index => $productoId) {
        $cantidad = intval($productoCantidades[$index]);
        $productoAlmacenId = intval($productoAlmacenes[$index]);  // Usar el ID de almacén específico del producto

        error_log("Procesando producto: ID $productoId, Cantidad $cantidad, Almacén $productoAlmacenId");

        // Insert delivery record
        $stmtEntregaUniforme->execute([
            $usuarioResponsableId, 
            $nombreDestinatario, 
            $dniDestinatario, 
            $productoId, 
            $cantidad, 
            $productoAlmacenId  // Usar el ID de almacén del producto
        ]);

        // Update product stock
        $stmtActualizarStock->execute([$cantidad, $productoId, $productoAlmacenId]);  // Usar el ID de almacén del producto
    }

    // Commit transaction
    $conn->commit();

    // Prepare success response
    $response['success'] = true;
    $response['message'] = 'Entrega de uniformes realizada con éxito';

} catch (Exception $e) {
    // Rollback transaction in case of error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log error details
    error_log('Error en procesar entrega: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());

    // Prepare error response
    $response['error'] = $e->getMessage();
} finally {
    // Close database connection
    $conn = null;
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();