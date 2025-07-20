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

// Prevent session hijacking
session_regenerate_id(true);

// Obtener información del usuario actual
$usuario_almacen_id = $_SESSION["almacen_id"]; 
$usuario_rol = $_SESSION["user_role"];
$usuario_actual_id = $_SESSION["user_id"];
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Error: Falta el ID del movimiento a procesar.";
    header("Location: pendientes.php");
    exit();
}

$movimiento_id = intval($_GET['id']);

// Procesar la acción si se recibió
if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];
    
    // Validar la acción
    if ($accion !== "aprobar" && $accion !== "rechazar") {
        $_SESSION['error'] = "Error: Acción no válida.";
        header("Location: imiento.php?id=" . $movimiento_id);
        exit();
    }
    
    // Obtener información del movimiento
    $sql_movimiento = "SELECT st.*, p.nombre as producto_nombre, p.color, p.talla_dimensiones, p.modelo,
                       c.nombre as categoria_nombre, a1.nombre as origen_nombre, a2.nombre as destino_nombre,
                       u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
                       FROM solicitudes_transferencia st
                       JOIN productos p ON st.producto_id = p.id
                       JOIN categorias c ON p.categoria_id = c.id
                       JOIN almacenes a1 ON st.almacen_origen = a1.id
                       JOIN almacenes a2 ON st.almacen_destino = a2.id
                       JOIN usuarios u ON st.usuario_id = u.id
                       WHERE st.id = ? AND st.estado = 'pendiente'";
    $stmt_movimiento = $conn->prepare($sql_movimiento);
    $stmt_movimiento->bind_param("i", $movimiento_id);
    $stmt_movimiento->execute();
    $result_movimiento = $stmt_movimiento->get_result();
    
    if ($result_movimiento->num_rows === 0) {
        $_SESSION['error'] = "Error: El movimiento no existe o ya ha sido procesado.";
        header("Location: pendientes.php");
        exit();
    }
    
    $movimiento = $result_movimiento->fetch_assoc();
    $stmt_movimiento->close();
    
    // Verificar permisos del usuario
    if ($usuario_rol != 'admin' && $movimiento['almacen_destino'] != $usuario_almacen_id) {
        $_SESSION['error'] = "Error: No tiene permisos para procesar esta solicitud.";
        header("Location: pendientes.php");
        exit();
    }
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    try {
        $nuevo_estado = ($accion === "aprobar") ? "aprobada" : "rechazada";
        
        // Actualizar estado de la solicitud y registrar quién aprobó/rechazó
        $sql_update = "UPDATE solicitudes_transferencia 
                       SET estado = ?, usuario_aprobador_id = ? 
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("sii", $nuevo_estado, $usuario_actual_id, $movimiento_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        // Si se rechaza, devolver los productos al almacén de origen
        if ($accion === 'rechazar') {
            // Actualizar el stock en el almacén de origen sumando la cantidad solicitada
            $sql_origen = "UPDATE productos SET cantidad = cantidad + ? 
                          WHERE id = ? AND almacen_id = ?";
            $stmt_origen = $conn->prepare($sql_origen);
            $stmt_origen->bind_param("iii", $movimiento['cantidad'], $movimiento['producto_id'], 
                                    $movimiento['almacen_origen']);
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
            $stmt_stock->bind_param("ii", $movimiento['producto_id'], $movimiento['almacen_origen']);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            
            if ($result_stock->num_rows === 0) {
                throw new Exception("El producto no existe en el almacén de origen");
            }
            
            $stock_actual = $result_stock->fetch_assoc()['cantidad'];
            
            if ($stock_actual < $movimiento['cantidad']) {
                throw new Exception("Stock insuficiente. Disponible: " . $stock_actual . ", Solicitado: " . $movimiento['cantidad']);
            }
            
            $stmt_stock->close();
            
            // Crear registro en la tabla movimientos
            $sql_mov = "INSERT INTO movimientos (producto_id, almacen_origen, almacen_destino, cantidad, 
                        tipo, usuario_id, estado) 
                        VALUES (?, ?, ?, ?, 'transferencia', ?, 'completado')";
            $stmt_mov = $conn->prepare($sql_mov);
            $stmt_mov->bind_param("iiiii", $movimiento['producto_id'], $movimiento['almacen_origen'], 
                                $movimiento['almacen_destino'], $movimiento['cantidad'], $movimiento['usuario_id']);
            $stmt_mov->execute();
            $stmt_mov->close();
            
            // Restar del almacén origen
            $sql_origen = "UPDATE productos SET cantidad = cantidad - ? 
                          WHERE id = ? AND almacen_id = ? AND cantidad >= ?";
            $stmt_origen = $conn->prepare($sql_origen);
            $stmt_origen->bind_param("iiii", $movimiento['cantidad'], $movimiento['producto_id'], 
                                    $movimiento['almacen_origen'], $movimiento['cantidad']);
            $stmt_origen->execute();
            
            if ($stmt_origen->affected_rows === 0) {
                throw new Exception("No hay suficiente stock en el almacén de origen");
            }
            $stmt_origen->close();
            
            // Obtener los detalles del producto para copiarlos si es necesario
            $sql_producto = "SELECT * FROM productos WHERE id = ? AND almacen_id = ?";
            $stmt_producto = $conn->prepare($sql_producto);
            $stmt_producto->bind_param("ii", $movimiento['producto_id'], $movimiento['almacen_origen']);
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
                                      $producto['talla_dimensiones'], $movimiento['almacen_destino']);
            $stmt_verificar->execute();
            $result_verificar = $stmt_verificar->get_result();
            
            if ($result_verificar->num_rows > 0) {
                // Si el producto ya existe en el almacén destino, actualizar la cantidad
                $producto_destino = $result_verificar->fetch_assoc();
                $sql_destino = "UPDATE productos SET cantidad = cantidad + ? 
                               WHERE id = ?";
                $stmt_destino = $conn->prepare($sql_destino);
                $stmt_destino->bind_param("ii", $movimiento['cantidad'], $producto_destino['id']);
                $stmt_destino->execute();
                $stmt_destino->close();
            } else {
                // Si el producto no existe en el almacén destino, crear una nueva entrada
                $sql_insertar = "INSERT INTO productos (categoria_id, almacen_id, nombre, descripcion, modelo, 
                                color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insertar = $conn->prepare($sql_insertar);
                $stmt_insertar->bind_param("iisssssisss", $producto['categoria_id'], $movimiento['almacen_destino'], 
                                        $producto['nombre'], $producto['descripcion'], $producto['modelo'], 
                                        $producto['color'], $producto['talla_dimensiones'], $movimiento['cantidad'], 
                                        $producto['unidad_medida'], $producto['estado'], $producto['observaciones']);
                $stmt_insertar->execute();
                $stmt_insertar->close();
            }
            
            $stmt_verificar->close();
        }
        
        // Confirmar la transacción
        $conn->commit();
        $_SESSION['success'] = "Solicitud " . ($accion === "aprobar" ? "aprobada" : "rechazada") . " correctamente.";
        
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: pendientes.php");
    exit();
}

// Obtener información del movimiento para mostrar
$sql_info = "SELECT st.*, p.nombre as producto_nombre, p.color, p.talla_dimensiones, p.modelo, p.descripcion,
             c.nombre as categoria_nombre, a1.nombre as origen_nombre, a2.nombre as destino_nombre,
             u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
             FROM solicitudes_transferencia st
             JOIN productos p ON st.producto_id = p.id
             JOIN categorias c ON p.categoria_id = c.id
             JOIN almacenes a1 ON st.almacen_origen = a1.id
             JOIN almacenes a2 ON st.almacen_destino = a2.id
             JOIN usuarios u ON st.usuario_id = u.id
             WHERE st.id = ? AND st.estado = 'pendiente'";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $movimiento_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();

if ($result_info->num_rows === 0) {
    $_SESSION['error'] = "Error: El movimiento no existe o ya ha sido procesado.";
    header("Location: pendientes.php");
    exit();
}

$solicitud_info = $result_info->fetch_assoc();
$stmt_info->close();

// Verificar permisos del usuario
if ($usuario_rol != 'admin' && $solicitud_info['almacen_destino'] != $usuario_almacen_id) {
    $_SESSION['error'] = "Error: No tiene permisos para procesar esta solicitud.";
    header("Location: pendientes.php");
    exit();
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
    <title>Procesar Movimiento - COMSEPROA | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Procesar movimiento del sistema de inventario COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico para procesar -->
    <link rel="stylesheet" href="../assets/css/notificaciones-procesar-specific.css">
    
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
        

        
        <!-- Notifications Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-bell"></i> Notificaciones</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li>
                    <a href="pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small" aria-label="<?php echo $total_pendientes; ?> solicitudes pendientes"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="../entregas/historial.php"role="menuitem"><i class="fas fa-history"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php" role="menuitem"><i class="fas fa-tshirt"></i> Ver Historial de Entregas</a></li>
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
    <h1>Procesar Solicitud de Transferencia</h1>
    
    <!-- Notificaciones -->
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
    
    <div class="procesar-container">
        <div class="proceso-header">
            <div class="proceso-icon">
                <i class="fas fa-cogs"></i>
            </div>
            <h2>Solicitud #<?php echo $solicitud_info['id']; ?></h2>
            <p>Revise la información y seleccione una acción</p>
        </div>
        
        <div class="movimiento-info">
            <div class="info-grid">
                <div class="info-card">
                    <h4><i class="fas fa-box"></i> Información del Producto</h4>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($solicitud_info['producto_nombre']); ?></p>
                    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($solicitud_info['categoria_nombre']); ?></p>
                    <p><strong>Modelo:</strong> <?php echo htmlspecialchars($solicitud_info['modelo']); ?></p>
                    <p><strong>Color:</strong> <?php echo htmlspecialchars($solicitud_info['color']); ?></p>
                    <p><strong>Talla/Dimensiones:</strong> <?php echo htmlspecialchars($solicitud_info['talla_dimensiones']); ?></p>
                    <p><strong>Cantidad:</strong> <?php echo intval($solicitud_info['cantidad']); ?> unidades</p>
                </div>
                
                <div class="info-card">
                    <h4><i class="fas fa-warehouse"></i> Información de Almacenes</h4>
                    <p><strong>Almacén Origen:</strong> <?php echo htmlspecialchars($solicitud_info['origen_nombre']); ?></p>
                    <p><strong>Almacén Destino:</strong> <?php echo htmlspecialchars($solicitud_info['destino_nombre']); ?></p>
                    <p><strong>Solicitado por:</strong> <?php echo htmlspecialchars($solicitud_info['usuario_nombre'] . ' ' . $solicitud_info['usuario_apellidos']); ?></p>
                    <p><strong>Fecha de solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud_info['fecha_solicitud'])); ?></p>
                    <p><strong>Estado actual:</strong> 
                        <span class="estado-proceso estado-pendiente">
                            <?php echo ucfirst($solicitud_info['estado']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="proceso-acciones">
            <div class="acciones-header">
                <h3>Seleccione una Acción</h3>
                <p>Esta decisión no se puede deshacer una vez confirmada</p>
            </div>
            
            <div class="confirmacion-alerta">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h4>¡Atención!</h4>
                    <p>
                        <strong>Aprobar:</strong> Transferirá <?php echo $solicitud_info['cantidad']; ?> unidades de "<?php echo htmlspecialchars($solicitud_info['producto_nombre']); ?>" 
                        desde <?php echo htmlspecialchars($solicitud_info['origen_nombre']); ?> hacia <?php echo htmlspecialchars($solicitud_info['destino_nombre']); ?>.<br>
                        <strong>Rechazar:</strong> Devolverá los productos al almacén de origen y cancelará la solicitud.
                    </p>
                </div>
            </div>
            
            <div class="acciones-botones">
                <a href="#" class="btn-proceso btn-aprobar" onclick="confirmarAccion('aprobar', <?php echo $movimiento_id; ?>)">
                    <i class="fas fa-check"></i> Aprobar Transferencia
                </a>
                
                <a href="#" class="btn-proceso btn-rechazar" onclick="confirmarAccion('rechazar', <?php echo $movimiento_id; ?>)">
                    <i class="fas fa-times"></i> Rechazar Solicitud
                </a>
                
                <a href="pendientes.php" class="btn-proceso btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver a Pendientes
                </a>
            </div>
        </div>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-adicionales" role="alert" aria-live="polite"></div>

<!-- INCLUIR EL SISTEMA UNIVERSAL DE CONFIRMACIONES -->
<script src="../assets/js/universal-confirmation-system.js"></script>

<!-- JavaScript PERSONALIZADO -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando procesar movimiento');
    
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
    
    // Mostrar notificación de bienvenida
    setTimeout(() => {
        mostrarNotificacion(
            'Revise cuidadosamente la información antes de tomar una decisión.', 
            'info', 
            4000
        );
    }, 1000);
    
    console.log('✅ Procesar movimiento iniciado correctamente');
});

// Función para confirmar acciones
async function confirmarAccion(accion, movimientoId) {
    const producto = '<?php echo addslashes($solicitud_info['producto_nombre']); ?>';
    const cantidad = <?php echo $solicitud_info['cantidad']; ?>;
    const origen = '<?php echo addslashes($solicitud_info['origen_nombre']); ?>';
    const destino = '<?php echo addslashes($solicitud_info['destino_nombre']); ?>';
    
    let mensaje, titulo;
    
    if (accion === 'aprobar') {
        titulo = 'Aprobar Transferencia';
        mensaje = `
            <h4>¿Confirma la aprobación de esta transferencia?</h4>
            <div style="background: rgba(40, 167, 69, 0.1); padding: 15px; border-radius: 8px; margin: 10px 0;">
                <p><strong>Producto:</strong> ${producto}</p>
                <p><strong>Cantidad:</strong> ${cantidad} unidades</p>
                <p><strong>Desde:</strong> ${origen}</p>
                <p><strong>Hacia:</strong> ${destino}</p>
            </div>
            <p><small>⚠️ Esta acción procesará inmediatamente la transferencia.</small></p>
        `;
    } else {
        titulo = 'Rechazar Solicitud';
        mensaje = `
            <h4>¿Confirma el rechazo de esta solicitud?</h4>
            <div style="background: rgba(220, 53, 69, 0.1); padding: 15px; border-radius: 8px; margin: 10px 0;">
                <p><strong>Producto:</strong> ${producto}</p>
                <p><strong>Cantidad:</strong> ${cantidad} unidades</p>
                <p><strong>Desde:</strong> ${origen}</p>
                <p><strong>Hacia:</strong> ${destino}</p>
            </div>
            <p><small>⚠️ Los productos serán devueltos al almacén de origen.</small></p>
        `;
    }
    
    try {
        let confirmado;
        if (accion === 'aprobar') {
            confirmado = await confirmarAprobacionSolicitud(titulo, mensaje);
        } else {
            confirmado = await confirmarRechazoSolicitud(titulo, mensaje);
        }
        
        if (confirmado) {
            mostrarNotificacion(`Procesando ${accion}...`, 'info');
            
            // Redirigir con la acción
            setTimeout(() => {
                window.location.href = `imiento.php?id=${movimientoId}&accion=${accion}`;
            }, 1000);
        }
    } catch (error) {
        console.error('Error en confirmación:', error);
        // Fallback a confirm nativo
        const confirmNativo = confirm(`¿Está seguro de que desea ${accion} esta solicitud?`);
        if (confirmNativo) {
            window.location.href = `imiento.php?id=${movimientoId}&accion=${accion}`;
        }
    }
}

// Función para cerrar sesión con confirmación
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