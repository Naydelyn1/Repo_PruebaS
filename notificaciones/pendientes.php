<?php
session_start();
require_once "../config/database.php";

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Asegurarse de que user_role y almacen_id existan
if (!isset($_SESSION["user_role"]) || !isset($_SESSION["almacen_id"])) {
    $_SESSION['error'] = "Información de usuario incompleta. Por favor inicie sesión nuevamente.";
    header("Location: ../views/login_form.php");
    exit();
}

// Obtener el almacén y rol del usuario actual
$usuario_almacen_id = $_SESSION["almacen_id"]; 
$usuario_rol = $_SESSION["user_role"];
$usuario_actual_id = $_SESSION["user_id"]; // ID del usuario que está aprobando/rechazando

// Procesar aprobación o rechazo de solicitudes
if (isset($_POST['accion']) && isset($_POST['solicitud_id'])) {
    $solicitud_id = intval($_POST['solicitud_id']);
    $accion = $_POST['accion'];
    
    if ($accion === 'aprobar' || $accion === 'rechazar') {
        $nuevo_estado = ($accion === 'aprobar') ? 'aprobada' : 'rechazada';
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Verificar que el usuario tenga permisos para esta solicitud
            $sql_verificar_permisos = "SELECT st.* FROM solicitudes_transferencia st 
                                      WHERE st.id = ? AND (st.almacen_destino = ? OR ? = 'admin')";
            $stmt_permisos = $conn->prepare($sql_verificar_permisos);
            $stmt_permisos->bind_param("iis", $solicitud_id, $usuario_almacen_id, $usuario_rol);
            $stmt_permisos->execute();
            $result_permisos = $stmt_permisos->get_result();
            
            if ($result_permisos->num_rows === 0) {
                throw new Exception("No tiene permisos para gestionar esta solicitud");
            }
            
            // Obtener información de la solicitud
            $sql_sol = "SELECT producto_id, almacen_origen, almacen_destino, cantidad, usuario_id 
                        FROM solicitudes_transferencia WHERE id = ?";
            $stmt_sol = $conn->prepare($sql_sol);
            $stmt_sol->bind_param("i", $solicitud_id);
            $stmt_sol->execute();
            $result_sol = $stmt_sol->get_result();
            
            if ($result_sol->num_rows === 0) {
                throw new Exception("Solicitud no encontrada");
            }
            
            $solicitud = $result_sol->fetch_assoc();
            $stmt_sol->close();
            
            // Actualizar estado de la solicitud y registrar quién aprobó/rechazó
            $sql_update = "UPDATE solicitudes_transferencia 
                           SET estado = ?, usuario_aprobador_id = ? 
                           WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sii", $nuevo_estado, $usuario_actual_id, $solicitud_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Si se rechaza, devolver los productos al almacén de origen
            if ($accion === 'rechazar') {
                // Actualizar el stock en el almacén de origen sumando la cantidad solicitada
                $sql_origen = "UPDATE productos SET cantidad = cantidad + ? 
                              WHERE id = ? AND almacen_id = ?";
                $stmt_origen = $conn->prepare($sql_origen);
                $stmt_origen->bind_param("iii", $solicitud['cantidad'], $solicitud['producto_id'], 
                                        $solicitud['almacen_origen']);
                $stmt_origen->execute();
                
                if ($stmt_origen->affected_rows === 0) {
                    throw new Exception("No se pudo actualizar el stock en el almacén de origen");
                }
                $stmt_origen->close();
            }
            
            // Si se aprueba, crear un movimiento y actualizar los stocks
            if ($accion === 'aprobar') {
                // Verificar stock actual
                $sql_stock = "SELECT cantidad FROM productos WHERE id = ? AND almacen_id = ?";
                $stmt_stock = $conn->prepare($sql_stock);
                $stmt_stock->bind_param("ii", $solicitud['producto_id'], $solicitud['almacen_origen']);
                $stmt_stock->execute();
                $result_stock = $stmt_stock->get_result();
                
                if ($result_stock->num_rows === 0) {
                    throw new Exception("El producto no existe en el almacén de origen");
                }
                
                $stock_actual = $result_stock->fetch_assoc()['cantidad'];
                
                if ($stock_actual < $solicitud['cantidad']) {
                    throw new Exception("Stock insuficiente. Disponible: " . $stock_actual . ", Solicitado: " . $solicitud['cantidad']);
                }
                
                $stmt_stock->close();
                
                // Crear registro en la tabla movimientos
                $sql_mov = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, 
                            tipo, usuario_id, estado) 
                            VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado')";
                $stmt_mov = $conn->prepare($sql_mov);
                $stmt_mov->bind_param("iiiii", $solicitud['producto_id'], $solicitud['almacen_origen'], 
                                    $solicitud['almacen_destino'], $solicitud['cantidad'], $solicitud['usuario_id']);
                $stmt_mov->execute();
                $stmt_mov->close();
                
                // Restar del almacén origen
                $sql_origen = "UPDATE productos SET cantidad = cantidad - ? 
                              WHERE id = ? AND almacen_id = ? AND cantidad >= ?";
                $stmt_origen = $conn->prepare($sql_origen);
                $stmt_origen->bind_param("iiii", $solicitud['cantidad'], $solicitud['producto_id'], 
                                        $solicitud['almacen_origen'], $solicitud['cantidad']);
                $stmt_origen->execute();
                
                if ($stmt_origen->affected_rows === 0) {
                    throw new Exception("No hay suficiente stock en el almacén de origen");
                }
                $stmt_origen->close();
                
                // Obtener los detalles del producto para copiarlos si es necesario
                $sql_producto = "SELECT * FROM productos WHERE id = ? AND almacen_id = ?";
                $stmt_producto = $conn->prepare($sql_producto);
                $stmt_producto->bind_param("ii", $solicitud['producto_id'], $solicitud['almacen_origen']);
                $stmt_producto->execute();
                $result_producto = $stmt_producto->get_result();
                
                if ($result_producto->num_rows === 0) {
                    throw new Exception("Producto no encontrado");
                }
                
                $producto = $result_producto->fetch_assoc();
                $stmt_producto->close();
                
                // Verificar si el producto ya existe en el almacén destino
                $sql_verificar = "SELECT id FROM productos 
                                 WHERE nombre = ? AND color = ? AND talla_dimensiones = ? AND almacen_id = ?";
                $stmt_verificar = $conn->prepare($sql_verificar);
                $stmt_verificar->bind_param("sssi", $producto['nombre'], $producto['color'], 
                                          $producto['talla_dimensiones'], $solicitud['almacen_destino']);
                $stmt_verificar->execute();
                $result_verificar = $stmt_verificar->get_result();
                
                if ($result_verificar->num_rows > 0) {
                    // Si el producto ya existe en el almacén destino, actualizar la cantidad
                    $producto_destino = $result_verificar->fetch_assoc();
                    $sql_destino = "UPDATE productos SET cantidad = cantidad + ? 
                                   WHERE id = ?";
                    $stmt_destino = $conn->prepare($sql_destino);
                    $stmt_destino->bind_param("ii", $solicitud['cantidad'], $producto_destino['id']);
                    $stmt_destino->execute();
                    $stmt_destino->close();
                } else {
                    // Si el producto no existe en el almacén destino, crear una nueva entrada
                    $sql_insertar = "INSERT INTO productos (categoria_id, almacen_id, nombre, descripcion, modelo, 
                                    color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insertar = $conn->prepare($sql_insertar);
                    $stmt_insertar->bind_param("iisssssisss", $producto['categoria_id'], $solicitud['almacen_destino'], 
                                            $producto['nombre'], $producto['descripcion'], $producto['modelo'], 
                                            $producto['color'], $producto['talla_dimensiones'], $solicitud['cantidad'], 
                                            $producto['unidad_medida'], $producto['estado'], $producto['observaciones']);
                    $stmt_insertar->execute();
                    $stmt_insertar->close();
                }
                
                $stmt_verificar->close();
            }
            
            // Confirmar la transacción
            $conn->commit();
            $_SESSION['success'] = ($accion === 'aprobar') 
                ? "Solicitud de transferencia aprobada correctamente" 
                : "Solicitud de transferencia rechazada";
                
        } catch (Exception $e) {
            // Revertir en caso de error
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: pendientes.php");
    exit();
}

// Obtener todas las solicitudes de transferencia pendientes
$sql = "SELECT st.id, st.producto_id, st.almacen_origen, st.almacen_destino, st.cantidad, st.fecha_solicitud, 
        p.nombre as producto_nombre, p.color, p.talla_dimensiones, p.modelo, p.estado as estado_producto,
        c.nombre as categoria_nombre,
        a1.nombre as origen_nombre, 
        a2.nombre as destino_nombre,
        u.nombre as usuario_nombre,
        u.apellidos as usuario_apellidos
        FROM solicitudes_transferencia st
        JOIN productos p ON st.producto_id = p.id AND p.almacen_id = st.almacen_origen
        JOIN categorias c ON p.categoria_id = c.id
        JOIN almacenes a1 ON st.almacen_origen = a1.id
        JOIN almacenes a2 ON st.almacen_destino = a2.id
        JOIN usuarios u ON st.usuario_id = u.id
        WHERE st.estado = 'pendiente'";

// Si el usuario no es admin, filtrar solo por solicitudes destinadas a su almacén
if ($usuario_rol != 'admin') {
    $sql .= " AND st.almacen_destino = ?";  // Filtrar por almacén destino
}
        
$sql .= " ORDER BY st.fecha_solicitud DESC";

// Ejecutar la consulta con o sin filtro de almacén
$solicitudes_pendientes = [];
if ($usuario_rol != 'admin') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_pendientes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes Pendientes - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Usuarios - Solo visible para administradores -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Almacenes - Ajustado según permisos -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
                <?php 
                // Contar solicitudes pendientes para mostrar en el badge
                $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                
                // Si el usuario no es admin, filtrar por su almacén
                if ($usuario_rol != 'admin') {
                    $sql_pendientes .= " AND almacen_destino = ?";  // Filtrar por almacén destino
                    $stmt_pendientes = $conn->prepare($sql_pendientes);
                    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
                    $stmt_pendientes->execute();
                    $result_pendientes = $stmt_pendientes->get_result();
                } else {
                    $result_pendientes = $conn->query($sql_pendientes);
                }
                
                if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
                    echo '<span class="badge">' . $row_pendientes['total'] . '</span>';
                }
                ?>
                </a></li>
                <li><a href="../notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>
        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<div class="main-content">
    <h1>Solicitudes de Transferencia Pendientes</h1>
    
    <!-- Contenedor de notificaciones -->
    <div id="notificaciones-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notificacion exito">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notificacion error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="solicitudes-container">
        <?php if (count($solicitudes_pendientes) > 0): ?>
            <?php foreach ($solicitudes_pendientes as $solicitud): ?>
                <div class="solicitud">
                    <div class="solicitud-header">
                        <h3>Solicitud #<?php echo $solicitud['id']; ?></h3>
                        <span>Fecha: <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></span>
                    </div>
                    <div class="solicitud-info">
                        <div class="solicitud-detalles">
                            <h4>Detalles del Producto</h4>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($solicitud['producto_nombre']); ?></p>
                            <p><strong>Categoría:</strong> <?php echo htmlspecialchars($solicitud['categoria_nombre']); ?></p>
                            <p><strong>Modelo:</strong> <?php echo htmlspecialchars($solicitud['modelo']); ?></p>
                            <p><strong>Color:</strong> <?php echo htmlspecialchars($solicitud['color']); ?></p>
                            <p><strong>Talla/Dimensiones:</strong> <?php echo htmlspecialchars($solicitud['talla_dimensiones']); ?></p>
                            <p><strong>Estado:</strong> <?php echo htmlspecialchars($solicitud['estado_producto']); ?></p>
                            <p><strong>Cantidad solicitada:</strong> <?php echo intval($solicitud['cantidad']); ?> unidades</p>
                        </div>
                        <div class="solicitud-almacenes">
                            <h4>Información de Transferencia</h4>
                            <p><strong>Almacén Origen:</strong> <?php echo htmlspecialchars($solicitud['origen_nombre']); ?></p>
                            <p><strong>Almacén Destino:</strong> <?php echo htmlspecialchars($solicitud['destino_nombre']); ?></p>
                            <p><strong>Solicitado por:</strong> <?php echo htmlspecialchars($solicitud['usuario_nombre'] . ' ' . $solicitud['usuario_apellidos']); ?></p>
                        </div>
                    </div>
                    <div class="solicitud-acciones">
                        <form method="POST" action="pendientes.php" onsubmit="return confirm('¿Está seguro de aprobar esta solicitud?');">
                            <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['id']; ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar">
                                <i class="fas fa-check"></i> Aprobar
                            </button>
                        </form>
                        
                        <form method="POST" action="pendientes.php" onsubmit="return confirm('¿Está seguro de rechazar esta solicitud?');">
                            <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['id']; ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar">
                                <i class="fas fa-times"></i> Rechazar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sin-solicitudes">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #4CAF50;"></i>
                <p>No hay solicitudes de transferencia pendientes en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Corregido: Referencia al script.js en la ruta correcta -->
<script src="../assets/js/script.js"></script>
</body>
</html>