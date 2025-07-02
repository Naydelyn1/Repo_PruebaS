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

// VALIDACIÓN CRÍTICA: Verificar que el usuario existe en la base de datos
$usuario_actual_id = $_SESSION["user_id"];
$sql_verificar_sesion = "SELECT id, nombre, apellidos, rol, almacen_id FROM usuarios WHERE id = ? AND estado = 'activo'";
$stmt_verificar_sesion = $conn->prepare($sql_verificar_sesion);
$stmt_verificar_sesion->bind_param("i", $usuario_actual_id);
$stmt_verificar_sesion->execute();
$result_verificar_sesion = $stmt_verificar_sesion->get_result();

if ($result_verificar_sesion->num_rows === 0) {
    // El usuario no existe o está inactivo - cerrar sesión y redirigir
    session_destroy();
    $_SESSION = array();
    $_SESSION['error'] = "Su sesión no es válida. Por favor inicie sesión nuevamente.";
    header("Location: ../views/login_form.php");
    exit();
}

// Obtener datos actualizados del usuario
$usuario_datos = $result_verificar_sesion->fetch_assoc();
$stmt_verificar_sesion->close();

// Actualizar datos de sesión con información actual de la BD
$_SESSION["user_role"] = $usuario_datos['rol'];
$_SESSION["almacen_id"] = $usuario_datos['almacen_id'];
$_SESSION["user_name"] = $usuario_datos['nombre'];

// Prevent session hijacking
session_regenerate_id(true);

// Obtener el almacén y rol del usuario actual
$usuario_almacen_id = $_SESSION["almacen_id"]; 
$usuario_rol = $_SESSION["user_role"];
$user_name = $_SESSION["user_name"];

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
            $sql_verificar_permisos = "SELECT st.*, u.nombre as solicitante_nombre 
                                      FROM solicitudes_transferencia st
                                      LEFT JOIN usuarios u ON st.usuario_id = u.id 
                                      WHERE st.id = ? AND st.estado = 'pendiente'
                                      AND (st.almacen_destino = ? OR ? = 'admin')";
            $stmt_permisos = $conn->prepare($sql_verificar_permisos);
            $stmt_permisos->bind_param("iis", $solicitud_id, $usuario_almacen_id, $usuario_rol);
            $stmt_permisos->execute();
            $result_permisos = $stmt_permisos->get_result();
            
            if ($result_permisos->num_rows === 0) {
                throw new Exception("No tiene permisos para gestionar esta solicitud o la solicitud ya fue procesada");
            }
            
            $solicitud = $result_permisos->fetch_assoc();
            $stmt_permisos->close();
            
            // Verificar que el usuario solicitante existe (integridad de datos)
            if (empty($solicitud['solicitante_nombre'])) {
                error_log("ADVERTENCIA: Solicitud ID {$solicitud_id} tiene usuario_id inexistente: {$solicitud['usuario_id']}");
                // Corregir automáticamente asignando al admin
                $sql_fix_user = "UPDATE solicitudes_transferencia SET usuario_id = ? WHERE id = ?";
                $stmt_fix = $conn->prepare($sql_fix_user);
                $stmt_fix->bind_param("ii", $usuario_actual_id, $solicitud_id);
                $stmt_fix->execute();
                $stmt_fix->close();
            }
            
            // Actualizar estado de la solicitud y registrar quién aprobó/rechazó
            $sql_update = "UPDATE solicitudes_transferencia 
                           SET estado = ?, usuario_aprobador_id = ?, fecha_procesamiento = NOW() 
                           WHERE id = ? AND estado = 'pendiente'";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sii", $nuevo_estado, $usuario_actual_id, $solicitud_id);
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows === 0) {
                throw new Exception("No se pudo actualizar la solicitud. Puede que ya haya sido procesada por otro usuario.");
            }
            $stmt_update->close();
            
            // Si se rechaza, devolver los productos al almacén de origen
            if ($accion === 'rechazar') {
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
                // Verificar stock actual en el almacén de origen
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
                
                // Crear registro en la tabla movimientos con usuario válido
                $usuario_movimiento = !empty($solicitud['usuario_id']) ? $solicitud['usuario_id'] : $usuario_actual_id;
                $sql_mov = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, 
                            tipo, usuario_id, estado, descripcion) 
                            VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado', ?)";
                $descripcion_mov = "Transferencia aprobada desde solicitud #{$solicitud_id}";
                $stmt_mov = $conn->prepare($sql_mov);
                $stmt_mov->bind_param("iiiiss", $solicitud['producto_id'], $solicitud['almacen_origen'], 
                                    $solicitud['almacen_destino'], $solicitud['cantidad'], $usuario_movimiento, $descripcion_mov);
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
                    throw new Exception("Producto no encontrado en almacén de origen");
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
                : "Solicitud de transferencia rechazada correctamente";
                
        } catch (Exception $e) {
            // Revertir en caso de error
            $conn->rollback();
            error_log("Error procesando solicitud {$solicitud_id}: " . $e->getMessage());
            $_SESSION['error'] = "Error al procesar la solicitud: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Acción no válida";
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: pendientes.php");
    exit();
}

// CONSULTA PRINCIPAL PARA OBTENER SOLICITUDES PENDIENTES CON VALIDACIÓN
$sql = "SELECT st.*, a1.nombre as origen_nombre, a2.nombre as destino_nombre, 
               u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
        FROM solicitudes_transferencia st
        LEFT JOIN almacenes a1 ON st.almacen_origen = a1.id
        LEFT JOIN almacenes a2 ON st.almacen_destino = a2.id  
        LEFT JOIN usuarios u ON st.usuario_id = u.id
        WHERE st.estado = 'pendiente'";

// Si el usuario no es admin, filtrar solo por solicitudes destinadas a su almacén
if ($usuario_rol != 'admin') {
    $sql .= " AND st.almacen_destino = ?";
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
        // OBTENER DATOS DEL PRODUCTO POR SEPARADO
        $sql_producto = "SELECT p.nombre, p.color, p.talla_dimensiones, p.modelo, p.estado, c.nombre as categoria_nombre
                        FROM productos p 
                        LEFT JOIN categorias c ON p.categoria_id = c.id 
                        WHERE p.id = ? 
                        LIMIT 1";
        $stmt_producto = $conn->prepare($sql_producto);
        $stmt_producto->bind_param("i", $row['producto_id']);
        $stmt_producto->execute();
        $result_producto = $stmt_producto->get_result();
        
        if ($result_producto && $producto_info = $result_producto->fetch_assoc()) {
            $row['producto_nombre'] = $producto_info['nombre'];
            $row['color'] = $producto_info['color'];
            $row['talla_dimensiones'] = $producto_info['talla_dimensiones'];
            $row['modelo'] = $producto_info['modelo'];
            $row['estado_producto'] = $producto_info['estado'];
            $row['categoria_nombre'] = $producto_info['categoria_nombre'];
        } else {
            // Si no se encuentra el producto, usar valores por defecto
            $row['producto_nombre'] = 'Producto ID: ' . $row['producto_id'];
            $row['color'] = 'Sin especificar';
            $row['talla_dimensiones'] = 'Sin especificar';
            $row['modelo'] = 'Sin especificar';
            $row['estado_producto'] = 'Sin especificar';
            $row['categoria_nombre'] = 'Sin categoría';
        }
        $stmt_producto->close();
        
        // Manejar usuarios inexistentes en solicitudes
        if (empty($row['usuario_nombre'])) {
            $row['usuario_nombre'] = 'Usuario';
            $row['usuario_apellidos'] = 'no válido';
        }
        
        $solicitudes_pendientes[] = $row;
    }
}

// CONTAR SOLICITUDES PENDIENTES PARA EL BADGE
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
if ($usuario_rol != 'admin') {
    $sql_pendientes .= " AND almacen_destino = ?";
    $stmt_pendientes = $conn->prepare($sql_pendientes);
    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
    $stmt_pendientes->execute();
    $result_pendientes = $stmt_pendientes->get_result();
} else {
    $result_pendientes = $conn->query($sql_pendientes);
}

$total_pendientes = 0;
if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes Pendientes - COMSEPROA | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Gestión de solicitudes pendientes del sistema de inventario COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico para pendientes -->
    <link rel="stylesheet" href="../assets/css/notificaciones/notificaciones-pendientes-specific.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body>

<!-- Mobile hamburger menu button -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Users Section - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-users"></i> Usuarios</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../usuarios/registrar.php" role="menuitem"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses Section - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php" role="menuitem"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Historial Section - Reemplaza la sección de Entregas -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php"role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
                
            </ul>
        </li>
        
        <!-- Notifications Section - Con badge rojo de notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span>
                    <i class="fas fa-bell"></i> Notificaciones
                </span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li>
                    <a href="../notificaciones/pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports Section (Admin only) -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Reportes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li><a href="../reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- User Profile -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Perfil" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                
                <li><a href="../perfil/cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li>
            <a href="#" onclick="manejarCerrarSesion(event)" aria-label="Cerrar sesión">
                <span><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<main class="main-content" id="main-content" role="main">
    <h1>Solicitudes de Transferencia Pendientes</h1>
    
    <!-- Contenedor de notificaciones -->
    <div id="notificaciones-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notificacion exito">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notificacion error">
                <i class="fas fa-exclamation-circle"></i>
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
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($solicitud['producto_nombre'] ?? 'No disponible'); ?></p>
                            <p><strong>Categoría:</strong> <?php echo htmlspecialchars($solicitud['categoria_nombre'] ?? 'No disponible'); ?></p>
                            <p><strong>Modelo:</strong> <?php echo htmlspecialchars($solicitud['modelo'] ?? 'No disponible'); ?></p>
                            <p><strong>Color:</strong> <?php echo htmlspecialchars($solicitud['color'] ?? 'No disponible'); ?></p>
                            <p><strong>Talla/Dimensiones:</strong> <?php echo htmlspecialchars($solicitud['talla_dimensiones'] ?? 'No disponible'); ?></p>
                            <p><strong>Estado:</strong> <?php echo htmlspecialchars($solicitud['estado_producto'] ?? 'No disponible'); ?></p>
                            <p><strong>Cantidad solicitada:</strong> <?php echo intval($solicitud['cantidad']); ?> unidades</p>
                        </div>
                        <div class="solicitud-almacenes">
                            <h4>Información de Transferencia</h4>
                            <p><strong>Almacén Origen:</strong> <?php echo htmlspecialchars($solicitud['origen_nombre'] ?? 'No disponible'); ?></p>
                            <p><strong>Almacén Destino:</strong> <?php echo htmlspecialchars($solicitud['destino_nombre'] ?? 'No disponible'); ?></p>
                            <p><strong>Solicitado por:</strong> <?php echo htmlspecialchars(($solicitud['usuario_nombre'] ?? '') . ' ' . ($solicitud['usuario_apellidos'] ?? '')); ?></p>
                        </div>
                    </div>
                    <div class="solicitud-acciones">
                        <!-- FORMULARIOS CON SISTEMA DE CONFIRMACIONES PERSONALIZADO -->
                        <form method="POST" action="pendientes.php" class="form-aprobar" data-solicitud-id="<?php echo $solicitud['id']; ?>" data-accion="aprobar">
                            <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['id']; ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar">
                                <i class="fas fa-check"></i> Aprobar
                            </button>
                        </form>
                        
                        <form method="POST" action="pendientes.php" class="form-rechazar" data-solicitud-id="<?php echo $solicitud['id']; ?>" data-accion="rechazar">
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
                <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                <p>No hay solicitudes de transferencia pendientes en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-adicionales" role="alert" aria-live="polite"></div>

<!-- INCLUIR EL SISTEMA UNIVERSAL DE CONFIRMACIONES -->
<script src="../assets/js/universal-confirmation-system.js"></script>

<!-- JavaScript PERSONALIZADO PARA CONFIRMACIONES -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando sistema de confirmaciones personalizado');
    
    // Elementos principales
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            // Cambiar icono del botón
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
                this.setAttribute('aria-label', 'Cerrar menú de navegación');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                this.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        });
    }
    
    // Funcionalidad de submenús
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            otherContainer.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                container.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });
    
    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
                menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
            }
        }
    });
    
    // Navegación por teclado
    document.addEventListener('keydown', function(e) {
        // Cerrar menú móvil con Escape
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (mainContent) {
                mainContent.classList.remove('with-sidebar');
            }
            menuToggle.focus();
        }
        
        // Indicador visual para navegación por teclado
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
    
    // Cerrar notificaciones
    document.querySelectorAll('.notificacion .cerrar').forEach(function(boton) {
        boton.addEventListener('click', function() {
            this.parentElement.style.animation = 'slideOutToTop 0.3s ease forwards';
            setTimeout(() => {
                this.parentElement.remove();
            }, 300);
        });
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            if (boton.parentElement) {
                boton.click();
            }
        }, 5000);
    });
    
    // ===== CONFIGURAR CONFIRMACIONES PERSONALIZADAS =====
    
    // FORMULARIOS DE APROBACIÓN
    document.querySelectorAll('.form-aprobar').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault(); // Evitar envío automático
            
            const solicitudId = this.dataset.solicitudId;
            console.log('🟢 Procesando aprobación de solicitud:', solicitudId);
            
            try {
                // Usar el sistema de confirmaciones personalizado
                const confirmado = await confirmarAprobacionSolicitud(
                    'Transferencia de Producto',
                    `
                        <h4>Detalles de la aprobación:</h4>
                        <p><strong>Solicitud ID:</strong> ${solicitudId}</p>
                        <p><strong>Acción:</strong> Aprobar transferencia</p>
                        <p><small>⚠️ Esta acción procesará inmediatamente la transferencia del producto.</small></p>
                    `
                );
                
                if (confirmado) {
                    console.log('✅ Aprobación confirmada, enviando formulario...');
                    mostrarNotificacion('Procesando aprobación...', 'info');
                    
                    // Enviar el formulario si se confirma
                    this.submit();
                } else {
                    console.log('❌ Aprobación cancelada por el usuario');
                }
            } catch (error) {
                console.error('Error en confirmación de aprobación:', error);
                mostrarNotificacion('Error al procesar la confirmación', 'error');
            }
        });
    });
    
    // FORMULARIOS DE RECHAZO
    document.querySelectorAll('.form-rechazar').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault(); // Evitar envío automático
            
            const solicitudId = this.dataset.solicitudId;
            console.log('🔴 Procesando rechazo de solicitud:', solicitudId);
            
            try {
                // Usar el sistema de confirmaciones personalizado
                const confirmado = await confirmarRechazoSolicitud(
                    'Transferencia de Producto',
                    `
                        <h4>Detalles del rechazo:</h4>
                        <p><strong>Solicitud ID:</strong> ${solicitudId}</p>
                        <p><strong>Acción:</strong> Rechazar transferencia</p>
                        <p><small>⚠️ Esta acción devolverá los productos al almacén de origen.</small></p>
                    `
                );
                
                if (confirmado) {
                    console.log('✅ Rechazo confirmado, enviando formulario...');
                    mostrarNotificacion('Procesando rechazo...', 'info');
                    
                    // Enviar el formulario si se confirma
                    this.submit();
                } else {
                    console.log('❌ Rechazo cancelado por el usuario');
                }
            } catch (error) {
                console.error('Error en confirmación de rechazo:', error);
                mostrarNotificacion('Error al procesar la confirmación', 'error');
            }
        });
    });
    
    // Efectos para tarjetas de solicitudes
    const solicitudes = document.querySelectorAll('.solicitud');
    solicitudes.forEach((solicitud, index) => {
        // Animación escalonada
        solicitud.style.animationDelay = `${index * 0.1}s`;
        
        // Efectos de hover mejorados
        solicitud.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        solicitud.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Mostrar notificación de bienvenida si hay solicitudes
    setTimeout(() => {
        const totalSolicitudes = <?php echo count($solicitudes_pendientes); ?>;
        if (totalSolicitudes > 0) {
            mostrarNotificacion(
                `Tienes ${totalSolicitudes} solicitud${totalSolicitudes > 1 ? 'es' : ''} pendiente${totalSolicitudes > 1 ? 's' : ''} de revisión.`, 
                'info', 
                4000
            );
        }
    }, 1000);
    
    console.log('✅ Sistema de confirmaciones personalizado iniciado correctamente');
});

// Función para cerrar sesión con confirmación PERSONALIZADA
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    try {
        const confirmado = await confirmarCerrarSesion();
        
        if (confirmado) {
            mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    } catch (error) {
        console.error('Error en confirmación de cierre de sesión:', error);
        // Fallback a confirm nativo si hay error
        if (confirm('¿Está seguro de que desea cerrar la sesión?')) {
            window.location.href = '../logout.php';
        }
    }
}

// Función para mostrar notificaciones dinámicas
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    const container = document.getElementById('notificaciones-adicionales');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    notificacion.innerHTML = `
        <i class="fas fa-${tipo === 'exito' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${mensaje}
        <span class="cerrar">&times;</span>
    `;
    
    container.appendChild(notificacion);
    
    // Agregar evento de cierre
    const cerrar = notificacion.querySelector('.cerrar');
    cerrar.addEventListener('click', function() {
        notificacion.style.animation = 'slideOutToTop 0.3s ease forwards';
        setTimeout(() => {
            notificacion.remove();
        }, 300);
    });
    
    // Auto-cerrar
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                cerrar.click();
            }
        }, duracion);
    }
}

// Animación para slideOutToTop
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutToTop {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-30px);
        }
    }
`;
document.head.appendChild(style);

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});
</script>
</body>
</html>