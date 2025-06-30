<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;

// ⭐ MANTENER LA LÓGICA ORIGINAL - Validar el ID del producto
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de producto no válido";
    header("Location: listar.php");
    exit();
}

$producto_id = $_GET['id'];

// ⭐ MANTENER LA LÓGICA ORIGINAL - OBTENER Y PROCESAR PARÁMETROS DE CONTEXTO
$context_params = isset($_GET['from']) ? $_GET['from'] : '';
parse_str($context_params, $context_array);

// Función para construir URL de retorno inteligente
function buildReturnUrl($context_array, $current_product) {
    $base_url = 'listar.php';
    $params = [];
    
    // Prioridad: categoría > almacén > default
    if (isset($context_array['categoria_id']) && !empty($context_array['categoria_id'])) {
        $params['categoria_id'] = $context_array['categoria_id'];
    }
    
    if (isset($context_array['almacen_id']) && !empty($context_array['almacen_id'])) {
        $params['almacen_id'] = $context_array['almacen_id'];
    }
    
    if (isset($context_array['busqueda']) && !empty($context_array['busqueda'])) {
        $params['busqueda'] = $context_array['busqueda'];
    }
    
    if (isset($context_array['pagina']) && !empty($context_array['pagina'])) {
        $params['pagina'] = $context_array['pagina'];
    }
    
    // Si no hay contexto específico, usar el almacén del producto
    if (empty($params)) {
        $params['almacen_id'] = $current_product['almacen_id'];
    }
    
    return $base_url . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Función para obtener texto descriptivo del contexto
function getContextDescription($context_array, $producto) {
    if (isset($context_array['categoria_id']) && !empty($context_array['categoria_id'])) {
        return 'Categoría: ' . htmlspecialchars($producto['categoria_nombre']);
    } elseif (isset($context_array['almacen_id']) && !empty($context_array['almacen_id'])) {
        return 'Almacén: ' . htmlspecialchars($producto['almacen_nombre']);
    } elseif (isset($context_array['busqueda']) && !empty($context_array['busqueda'])) {
        return 'Búsqueda: ' . htmlspecialchars($context_array['busqueda']);
    }
    return 'Lista de Productos';
}

// Función para determinar si el retorno debe ser al almacén
function shouldReturnToWarehouse($context_array, $current_product) {
    // Si no hay contexto específico de lista, volver al almacén
    if (empty($context_array) || 
        (!isset($context_array['categoria_id']) && !isset($context_array['busqueda']) && !isset($context_array['pagina']))) {
        return true;
    }
    return false;
}

// Obtener información completa del producto
$sql = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
        FROM productos p 
        JOIN categorias c ON p.categoria_id = c.id 
        JOIN almacenes a ON p.almacen_id = a.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();
$stmt->close();

if (!$producto) {
    $_SESSION['error'] = "Producto no encontrado";
    header("Location: listar.php");
    exit();
}

// ⭐ CONSTRUIR URLs DE NAVEGACIÓN CON CONTEXTO
$return_url = buildReturnUrl($context_array, $producto);
$return_text = getContextDescription($context_array, $producto);
$should_return_to_warehouse = shouldReturnToWarehouse($context_array, $producto);

// URL para retorno al almacén
$warehouse_return_url = "../almacenes/ver_redirect.php?id=" . $producto['almacen_id'];

// Verificar permisos de acceso (si no es admin, solo puede ver productos de su almacén)
if ($usuario_rol != 'admin' && $usuario_almacen_id != $producto['almacen_id']) {
    $_SESSION['error'] = "No tiene permisos para ver este producto";
    header("Location: listar.php");
    exit();
}

// Obtener historial de movimientos del producto
$sql_movimientos = "SELECT m.*, 
                    CASE 
                        WHEN m.tipo = 'transferencia' THEN CONCAT(COALESCE(ao.nombre, 'N/A'), ' → ', COALESCE(ad.nombre, 'N/A'))
                        WHEN m.tipo = 'entrada' THEN CONCAT('Entrada a ', COALESCE(ao.nombre, 'N/A'))
                        WHEN m.tipo = 'salida' THEN CONCAT('Salida de ', COALESCE(ao.nombre, 'N/A'))
                        ELSE 'Movimiento'
                    END as descripcion_movimiento,
                    u.nombre as usuario_nombre,
                    ao.nombre as almacen_origen_nombre,
                    ad.nombre as almacen_destino_nombre
                    FROM movimientos m
                    LEFT JOIN usuarios u ON m.usuario_id = u.id
                    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
                    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
                    WHERE m.producto_id = ?
                    ORDER BY m.fecha DESC
                    LIMIT 10";
$stmt_movimientos = $conn->prepare($sql_movimientos);
$stmt_movimientos->bind_param("i", $producto_id);
$stmt_movimientos->execute();
$movimientos = $stmt_movimientos->get_result();
$stmt_movimientos->close();

// Obtener solicitudes de transferencia relacionadas
$sql_solicitudes = "SELECT s.*, 
                    ao.nombre as almacen_origen_nombre,
                    ad.nombre as almacen_destino_nombre,
                    u.nombre as usuario_nombre
                    FROM solicitudes_transferencia s
                    LEFT JOIN almacenes ao ON s.almacen_origen = ao.id
                    LEFT JOIN almacenes ad ON s.almacen_destino = ad.id
                    LEFT JOIN usuarios u ON s.usuario_id = u.id
                    WHERE s.producto_id = ?
                    ORDER BY s.fecha_solicitud DESC
                    LIMIT 5";
$stmt_solicitudes = $conn->prepare($sql_solicitudes);
$stmt_solicitudes->bind_param("i", $producto_id);
$stmt_solicitudes->execute();
$solicitudes = $stmt_solicitudes->get_result();
$stmt_solicitudes->close();

// Buscar productos similares (misma categoría, diferente almacén)
$sql_similares = "SELECT p.*, a.nombre as almacen_nombre
                  FROM productos p
                  JOIN almacenes a ON p.almacen_id = a.id
                  WHERE p.categoria_id = ? AND p.id != ? AND p.cantidad > 0
                  ORDER BY p.cantidad DESC
                  LIMIT 5";
$stmt_similares = $conn->prepare($sql_similares);
$stmt_similares->bind_param("ii", $producto['categoria_id'], $producto_id);
$stmt_similares->execute();
$productos_similares = $stmt_similares->get_result();
$stmt_similares->close();

// Contar solicitudes pendientes para el badge
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
    <title>Ver Producto - <?php echo htmlspecialchars($producto['nombre']); ?> - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Detalle del producto <?php echo htmlspecialchars($producto['nombre']); ?> - Sistema GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para ver producto -->
    <link rel="stylesheet" href="../assets/css/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/productos/productos-ver.css">
</head>
<body data-producto-id="<?php echo $producto_id; ?>" 
      data-almacen-id="<?php echo $producto['almacen_id']; ?>"
      class="productos-ver-page">

<!-- Botón de hamburguesa para dispositivos móviles -->
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

<!-- Contenido Principal -->
<main class="content" id="main-content" role="main">
    <!-- Mensajes de éxito o error -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Header del Producto -->
    <header class="product-header">
        <div class="header-content">
            <div class="product-info">
                <div class="product-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="product-details">
                    <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="product-meta">
                        <span class="categoria">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                        </span>
                        <span class="almacen">
                            <i class="fas fa-warehouse"></i>
                            <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                        </span>
                        <span class="estado estado-<?php echo strtolower($producto['estado']); ?>">
                            <i class="fas fa-info-circle"></i>
                            <?php echo htmlspecialchars($producto['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <?php if ($usuario_rol == 'admin'): ?>
                <!-- ⭐ BOTÓN EDITAR CON LÓGICA ORIGINAL -->
                <button class="btn-action btn-edit" onclick="editarProductoConContexto(<?php echo $producto_id; ?>)" title="Editar producto">
                    <i class="fas fa-edit"></i>
                    <span>Editar</span>
                </button>
                <button class="btn-action btn-delete" onclick="eliminarProducto(<?php echo $producto_id; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')" title="Eliminar producto">
                    <i class="fas fa-trash"></i>
                    <span>Eliminar</span>
                </button>
                <?php endif; ?>
                
                <?php if ($producto['cantidad'] > 0): ?>
                <button class="btn-action btn-transfer" 
                    data-id="<?php echo $producto_id; ?>"
                    data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                    data-almacen="<?php echo $producto['almacen_id']; ?>"
                    data-cantidad="<?php echo $producto['cantidad']; ?>"
                    onclick="abrirModalTransferencia(this)"
                    title="Transferir producto">
                    <i class="fas fa-paper-plane"></i>
                    <span>Transferir</span>
                </button>
                <?php endif; ?>
                
                <!-- ⭐ BOTÓN VOLVER INTELIGENTE -->
                <button class="btn-action btn-back" onclick="navegarRetorno()" title="Volver">
                    <i class="fas fa-arrow-left"></i>
                    <span id="textoRetorno">Volver</span>
                </button>
            </div>
        </div>
        
        <!-- ⭐ BREADCRUMB DINÁMICO -->
        <nav class="breadcrumb" aria-label="Ruta de navegación" id="breadcrumbContainer">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <!-- Se completará dinámicamente -->
            <span class="current"><?php echo htmlspecialchars($producto['nombre']); ?></span>
        </nav>
    </header>

    <!-- Información del Producto -->
    <div class="main-content-grid">
        <!-- Panel Principal del Producto -->
        <section class="product-details-section">
            <div class="details-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Información del Producto
                    </h2>
                </div>
                
                <div class="card-body">
                    <div class="details-grid">
                        <div class="detail-group">
                            <label>Nombre del Producto</label>
                            <value><?php echo htmlspecialchars($producto['nombre']); ?></value>
                        </div>

                        <?php if (!empty($producto['modelo'])): ?>
                        <div class="detail-group">
                            <label>Modelo</label>
                            <value><?php echo htmlspecialchars($producto['modelo']); ?></value>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($producto['color'])): ?>
                        <div class="detail-group">
                            <label>Color</label>
                            <value><?php echo htmlspecialchars($producto['color']); ?></value>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($producto['talla_dimensiones'])): ?>
                        <div class="detail-group">
                            <label>Talla / Dimensiones</label>
                            <value><?php echo htmlspecialchars($producto['talla_dimensiones']); ?></value>
                        </div>
                        <?php endif; ?>

                        <div class="detail-group">
                            <label>Cantidad en Stock</label>
                            <value class="stock-value <?php 
                                if ($producto['cantidad'] < 5) echo 'stock-critical';
                                elseif ($producto['cantidad'] < 10) echo 'stock-warning';
                                else echo 'stock-good';
                            ?>">
                                <span id="cantidad-actual"><?php echo number_format($producto['cantidad']); ?></span> 
                                <?php echo htmlspecialchars($producto['unidad_medida']); ?>
                                <?php if ($usuario_rol == 'admin'): ?>
                                <div class="stock-controls">
                                    <button class="stock-btn decrease" 
                                            data-id="<?php echo $producto_id; ?>" 
                                            data-accion="restar" 
                                            <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?>
                                            title="Reducir stock">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <button class="stock-btn increase" 
                                            data-id="<?php echo $producto_id; ?>" 
                                            data-accion="sumar"
                                            title="Aumentar stock">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Estado</label>
                            <value class="estado estado-<?php echo strtolower($producto['estado']); ?>">
                                <?php echo htmlspecialchars($producto['estado']); ?>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Categoría</label>
                            <value>
                                <a href="listar.php?categoria_id=<?php echo $producto['categoria_id']; ?>" class="link-categoria">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                </a>
                            </value>
                        </div>

                        <div class="detail-group">
                            <label>Almacén</label>
                            <value>
                                <a href="javascript:void(0)" onclick="navegarAlAlmacen()" class="link-almacen">
                                    <i class="fas fa-warehouse"></i>
                                    <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                                </a>
                            </value>
                        </div>

                        <?php if (!empty($producto['observaciones'])): ?>
                        <div class="detail-group full-width">
                            <label>Observaciones</label>
                            <value><?php echo nl2br(htmlspecialchars($producto['observaciones'])); ?></value>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Historial de Movimientos -->
            <div class="movements-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-history"></i>
                        Historial de Movimientos
                    </h2>
                </div>
                
                <div class="card-body">
                    <?php if ($movimientos && $movimientos->num_rows > 0): ?>
                        <div class="movements-list">
                            <?php while ($movimiento = $movimientos->fetch_assoc()): ?>
                            <div class="movement-item">
                                <div class="movement-icon">
                                    <i class="fas fa-<?php 
                                        echo $movimiento['tipo'] == 'entrada' ? 'plus-circle' : 
                                             ($movimiento['tipo'] == 'salida' ? 'minus-circle' : 'exchange-alt'); 
                                    ?>"></i>
                                </div>
                                <div class="movement-details">
                                    <div class="movement-description">
                                        <?php echo htmlspecialchars($movimiento['descripcion_movimiento']); ?>
                                    </div>
                                    <div class="movement-meta">
                                        <span class="movement-quantity">
                                            <?php echo $movimiento['cantidad']; ?> unidades
                                        </span>
                                        <span class="movement-user">
                                            por <?php echo htmlspecialchars($movimiento['usuario_nombre'] ?: 'Sistema'); ?>
                                        </span>
                                        <span class="movement-date">
                                            <?php echo date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="movement-status">
                                    <span class="status-<?php echo $movimiento['estado']; ?>">
                                        <?php echo ucfirst($movimiento['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-history"></i>
                            <p>No hay movimientos registrados para este producto.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Panel Lateral -->
        <aside class="sidebar-panel">
            <!-- Solicitudes de Transferencia -->
            <div class="requests-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-paper-plane"></i>
                        Solicitudes de Transferencia
                    </h3>
                </div>
                
                <div class="card-body">
                    <?php if ($solicitudes && $solicitudes->num_rows > 0): ?>
                        <div class="requests-list">
                            <?php while ($solicitud = $solicitudes->fetch_assoc()): ?>
                            <div class="request-item">
                                <div class="request-header">
                                    <span class="request-status status-<?php echo $solicitud['estado']; ?>">
                                        <?php echo ucfirst($solicitud['estado']); ?>
                                    </span>
                                    <span class="request-date">
                                        <?php echo date('d/m', strtotime($solicitud['fecha_solicitud'])); ?>
                                    </span>
                                </div>
                                <div class="request-details">
                                    <div class="transfer-route">
                                        <?php echo htmlspecialchars($solicitud['almacen_origen_nombre']); ?>
                                        <i class="fas fa-arrow-right"></i>
                                        <?php echo htmlspecialchars($solicitud['almacen_destino_nombre']); ?>
                                    </div>
                                    <div class="request-quantity">
                                        <?php echo $solicitud['cantidad']; ?> unidades
                                    </div>
                                    <div class="request-user">
                                        por <?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-paper-plane"></i>
                            <p>No hay solicitudes de transferencia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Productos Similares -->
            <div class="similar-products-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-box-open"></i>
                        Productos Similares
                    </h3>
                </div>
                
                <div class="card-body">
                    <?php if ($productos_similares && $productos_similares->num_rows > 0): ?>
                        <div class="similar-list">
                            <?php while ($similar = $productos_similares->fetch_assoc()): ?>
                            <div class="similar-item">
                                <a href="ver-producto.php?id=<?php echo $similar['id']; ?>" class="similar-link">
                                    <div class="similar-info">
                                        <div class="similar-name"><?php echo htmlspecialchars($similar['nombre']); ?></div>
                                        <div class="similar-meta">
                                            <span class="similar-almacen">
                                                <i class="fas fa-warehouse"></i>
                                                <?php echo htmlspecialchars($similar['almacen_nombre']); ?>
                                            </span>
                                            <span class="similar-stock">
                                                <?php echo $similar['cantidad']; ?> unidades
                                            </span>
                                        </div>
                                    </div>
                                    <div class="similar-action">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                </a>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-message">
                            <i class="fas fa-box-open"></i>
                            <p>No hay productos similares disponibles.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="quick-actions-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-bolt"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                
                <div class="card-body">
                    <div class="quick-actions-list">
                        <a href="listar.php?categoria_id=<?php echo $producto['categoria_id']; ?>" class="quick-action">
                            <i class="fas fa-list"></i>
                            <span>Ver Categoría Completa</span>
                        </a>
                        
                        <a href="javascript:void(0)" onclick="navegarAlAlmacen()" class="quick-action">
                            <i class="fas fa-warehouse"></i>
                            <span>Ver Almacén Completo</span>
                        </a>
                        
                        <?php if ($usuario_rol == 'admin'): ?>
                        <a href="registrar.php?categoria_id=<?php echo $producto['categoria_id']; ?>&almacen_id=<?php echo $producto['almacen_id']; ?>" class="quick-action">
                            <i class="fas fa-plus"></i>
                            <span>Producto Similar</span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="../notificaciones/pendientes.php" class="quick-action">
                            <i class="fas fa-bell"></i>
                            <span>Ver Solicitudes</span>
                            <?php if ($total_pendientes > 0): ?>
                            <span class="action-badge"><?php echo $total_pendientes; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- Modal de Transferencia -->
<div id="modalTransferencia" class="modal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-paper-plane"></i>
                Transferir Producto
            </h2>
            <button class="modal-close" onclick="cerrarModal()" aria-label="Cerrar modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formTransferencia" method="POST" action="procesar_formulario.php">
            <div class="modal-body">
                <input type="hidden" id="producto_id_modal" name="producto_id">
                <input type="hidden" id="almacen_origen_modal" name="almacen_origen">
                
                <div class="transfer-info">
                    <div class="product-summary">
                        <div class="product-icon-modal">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="product-details-modal">
                            <h3 id="producto_nombre_modal"></h3>
                            <p>Stock disponible: <span id="stock_disponible_modal" class="stock-highlight"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cantidad_modal" class="form-label">
                        <i class="fas fa-sort-numeric-up"></i>
                        Cantidad a transferir
                    </label>
                    <div class="quantity-input">
                        <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="cantidad_modal" name="cantidad" min="1" value="1" class="qty-input">
                        <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="almacen_destino_modal" class="form-label">
                        <i class="fas fa-warehouse"></i>
                        Almacén de destino
                    </label>
                    <select id="almacen_destino_modal" name="almacen_destino" required class="form-select">
                        <option value="">Seleccione un almacén</option>
                        <?php
                        // Obtener lista de almacenes para el modal
                        $sql_almacenes_modal = "SELECT id, nombre FROM almacenes WHERE id != ? ORDER BY nombre";
                        $stmt_almacenes_modal = $conn->prepare($sql_almacenes_modal);
                        $stmt_almacenes_modal->bind_param("i", $producto['almacen_id']);
                        $stmt_almacenes_modal->execute();
                        $result_almacenes_modal = $stmt_almacenes_modal->get_result();
                        
                        while ($almacen_modal = $result_almacenes_modal->fetch_assoc()) {
                            echo "<option value='{$almacen_modal['id']}'>{$almacen_modal['nombre']}</option>";
                        }
                        $stmt_almacenes_modal->close();
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="cerrarModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn-modal btn-confirm">
                    <i class="fas fa-paper-plane"></i>
                    Transferir Producto
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<script>
// Variables para el contexto
const CONTEXT_PARAMS = '<?php echo urlencode($context_params); ?>';
const PRODUCT_ID = <?php echo $producto_id; ?>;
const ALMACEN_ID = <?php echo $producto['almacen_id']; ?>;
const RETURN_URL = '<?php echo $return_url; ?>';
const RETURN_TEXT = '<?php echo addslashes($return_text); ?>';
const SHOULD_RETURN_TO_WAREHOUSE = <?php echo $should_return_to_warehouse ? 'true' : 'false'; ?>;
const WAREHOUSE_RETURN_URL = '<?php echo $warehouse_return_url; ?>';

// Función para configurar la interfaz según el contexto
function configurarInterfazContexto() {
    const textoRetorno = document.getElementById('textoRetorno');
    const breadcrumbContainer = document.getElementById('breadcrumbContainer');
    
    if (SHOULD_RETURN_TO_WAREHOUSE) {
        // Configurar para retorno al almacén
        textoRetorno.textContent = 'Volver al Almacén';
        
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarAlAlmacen()">Almacén</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($producto['nombre']); ?></span>
        `;
    } else {
        // Configurar para retorno a lista de productos
        if (RETURN_TEXT.includes('Categoría:')) {
            textoRetorno.textContent = 'Volver a Categoría';
        } else if (RETURN_TEXT.includes('Almacén:')) {
            textoRetorno.textContent = 'Volver al Almacén';
        } else {
            textoRetorno.textContent = 'Volver a Lista';
        }
        
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarRetorno()">${RETURN_TEXT}</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($producto['nombre']); ?></span>
        `;
    }
}

// Función para navegar de retorno
function navegarRetorno() {
    if (SHOULD_RETURN_TO_WAREHOUSE) {
        navegarAlAlmacen();
    } else {
        // Verificar si hay contexto de productos guardado
        const productosContext = sessionStorage.getItem('productos_context');
        
        if (productosContext) {
            const context = JSON.parse(productosContext);
            if (context.filtro_almacen_id === ALMACEN_ID) {
                // El contexto coincide, usar la URL de retorno
                window.location.href = RETURN_URL;
                return;
            }
        }
        
        // Usar URL de retorno por defecto
        window.location.href = RETURN_URL;
    }
}

// Función para navegar al almacén
function navegarAlAlmacen() {
    // Crear formulario para navegar de forma segura al almacén
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../almacenes/ver_redirect.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'view_almacen_id';
    input.value = ALMACEN_ID;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// ⭐ LÓGICA ORIGINAL MANTENIDA - Función para editar con contexto
function editarProductoConContexto(productoId) {
    const baseUrl = 'editar.php?id=' + productoId;
    const fullUrl = CONTEXT_PARAMS ? baseUrl + '&from=' + CONTEXT_PARAMS : baseUrl;
    window.location.href = fullUrl;
}

// Función para abrir modal de transferencia
function abrirModalTransferencia(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    // Usar la función del objeto global si existe
    if (window.productosVer) {
        window.productosVer.abrirModal(datos);
    } else {
        // Fallback simple
        console.log('Abriendo modal para:', datos);
    }
}

// Funciones de compatibilidad
function cerrarModal() {
    if (window.productosVer) {
        window.productosVer.cerrarModal();
    }
}

function adjustQuantity(increment) {
    if (window.productosVer) {
        window.productosVer.adjustQuantity(increment);
    }
}

async function eliminarProducto(id, nombre) {
    if (window.productosVer) {
        // Usar el sistema de confirmación del objeto
        const confirmado = await window.productosVer.confirmarAccion(
            `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
            'Eliminar Producto',
            'danger'
        );
        
        if (confirmado) {
            window.productosVer.mostrarNotificacion('Eliminando producto...', 'info');
            
            try {
                const response = await fetch('eliminar_producto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    window.productosVer.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                    
                    setTimeout(() => {
                        navegarRetorno();
                    }, 2000);
                } else {
                    window.productosVer.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                window.productosVer.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
            }
        }
    } else {
        // Fallback simple
        if (confirm(`¿Estás seguro que deseas eliminar el producto "${nombre}"?`)) {
            console.log('Eliminar producto ID:', id);
        }
    }
}

function manejarCerrarSesion(event) {
    if (window.productosVer) {
        window.productosVer.manejarCerrarSesion(event);
    } else {
        event.preventDefault();
        if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
            window.location.href = '../logout.php';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Configurar la interfaz según el contexto
    configurarInterfazContexto();
    
    // Manejar navegación del navegador (botón atrás)
    window.addEventListener('popstate', function(event) {
        // Navegar según el contexto
        navegarRetorno();
    });
});
</script>

<!-- JavaScript principal -->
<script src="../assets/js/productos-ver.js"></script>
</body>
</html>