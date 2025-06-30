<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);
require_once "../config/database.php";

// Obtener información del usuario
$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Configuración de paginación optimizada - CAMBIADO DE 15 A 10
$productos_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

// Obtener filtros de la URL
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// ⭐ FUNCIÓN PARA CONSTRUIR PARÁMETROS DE CONTEXTO
function construirParametrosContexto() {
    global $filtro_almacen_id, $filtro_categoria_id, $busqueda, $pagina_actual;
    
    $params = [];
    if ($filtro_almacen_id) $params['almacen_id'] = $filtro_almacen_id;
    if ($filtro_categoria_id) $params['categoria_id'] = $filtro_categoria_id;
    if (!empty($busqueda)) $params['busqueda'] = $busqueda;
    if ($pagina_actual > 1) $params['pagina'] = $pagina_actual;
    
    return http_build_query($params);
}

$contexto_params = construirParametrosContexto();

// Verificar permisos si hay filtro de almacén
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    $_SESSION['error'] = "No tienes permiso para ver productos de este almacén";
    header("Location: ../almacenes/listar.php");
    exit();
}

// Obtener información del almacén (si hay filtro)
$almacen_info = null;
if ($filtro_almacen_id) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $filtro_almacen_id);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener información de la categoría (si hay filtro)
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Construir consulta base para contar total
$sql_count_base = "SELECT COUNT(*) as total 
                   FROM productos p 
                   JOIN categorias c ON p.categoria_id = c.id 
                   JOIN almacenes a ON p.almacen_id = a.id";

// Construir consulta base para datos
$sql_base = "SELECT p.*, c.nombre as categoria_nombre, a.nombre as almacen_nombre 
             FROM productos p 
             JOIN categorias c ON p.categoria_id = c.id 
             JOIN almacenes a ON p.almacen_id = a.id";

$where_conditions = [];
$params = [];
$param_types = "";

// Aplicar filtro de almacén
if ($filtro_almacen_id) {
    $where_conditions[] = "p.almacen_id = ?";
    $params[] = $filtro_almacen_id;
    $param_types .= "i";
} elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
    // Si no es admin, solo mostrar productos de su almacén
    $where_conditions[] = "p.almacen_id = ?";
    $params[] = $usuario_almacen_id;
    $param_types .= "i";
}

// Aplicar filtro de categoría
if ($filtro_categoria_id) {
    $where_conditions[] = "p.categoria_id = ?";
    $params[] = $filtro_categoria_id;
    $param_types .= "i";
}

// Aplicar búsqueda
if (!empty($busqueda)) {
    $where_conditions[] = "(p.nombre LIKE ? OR p.modelo LIKE ? OR p.color LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params = array_merge($params, [$busqueda_param, $busqueda_param, $busqueda_param]);
    $param_types .= "sss";
}

// Construir WHERE clause
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}

// Contar total de productos
$sql_count = $sql_count_base . $where_clause;
if (!empty($params)) {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($param_types, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_productos = $result_count->fetch_assoc()['total'];
    $stmt_count->close();
} else {
    $result_count = $conn->query($sql_count);
    $total_productos = $result_count->fetch_assoc()['total'];
}

// Calcular paginación
$total_paginas = ceil($total_productos / $productos_por_pagina);
$pagina_actual = min($pagina_actual, max(1, $total_paginas)); // Asegurar que no exceda el máximo

// Construir consulta final con paginación
$sql_productos = $sql_base . $where_clause . " ORDER BY p.nombre LIMIT ? OFFSET ?";
$params_final = array_merge($params, [$productos_por_pagina, $offset]);
$param_types_final = $param_types . "ii";

// Ejecutar consulta
if (!empty($params_final)) {
    $stmt = $conn->prepare($sql_productos);
    $stmt->bind_param($param_types_final, ...$params_final);
    $stmt->execute();
    $result_productos = $stmt->get_result();
    $stmt->close();
} else {
    $sql_productos = $sql_base . " ORDER BY p.nombre LIMIT $productos_por_pagina OFFSET $offset";
    $result_productos = $conn->query($sql_productos);
}

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

// Función para generar URL con parámetros
function buildUrl($params = []) {
    global $filtro_almacen_id, $filtro_categoria_id, $busqueda;
    
    $url_params = [];
    
    if ($filtro_almacen_id) $url_params['almacen_id'] = $filtro_almacen_id;
    if ($filtro_categoria_id) $url_params['categoria_id'] = $filtro_categoria_id;
    if (!empty($busqueda)) $url_params['busqueda'] = $busqueda;
    
    // Sobrescribir con parámetros proporcionados
    $url_params = array_merge($url_params, $params);
    
    return 'listar.php' . (!empty($url_params) ? '?' . http_build_query($url_params) : '');
}

// Calcular estadísticas para el header
$productos_mostrados = $result_productos ? $result_productos->num_rows : 0;
$inicio_rango = $total_productos > 0 ? (($pagina_actual - 1) * $productos_por_pagina) + 1 : 0;
$fin_rango = min($pagina_actual * $productos_por_pagina, $total_productos);

// Obtener lista de almacenes para el modal de transferencia
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
if ($filtro_almacen_id) {
    $sql_almacenes = "SELECT id, nombre FROM almacenes WHERE id != ? ORDER BY nombre";
    $stmt_almacenes = $conn->prepare($sql_almacenes);
    $stmt_almacenes->bind_param("i", $filtro_almacen_id);
    $stmt_almacenes->execute();
    $result_almacenes = $stmt_almacenes->get_result();
    $stmt_almacenes->close();
} else {
    $result_almacenes = $conn->query($sql_almacenes);
}

$almacenes_disponibles = [];
while ($almacen = $result_almacenes->fetch_assoc()) {
    $almacenes_disponibles[] = $almacen;
}

// Función para determinar la URL de retorno al almacén
function obtenerUrlRetornoAlmacen($almacen_id) {
    return "../almacenes/ver_redirect.php?id=" . $almacen_id;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php if ($almacen_info): ?>
            Inventario - <?php echo htmlspecialchars($almacen_info['nombre']); ?>
        <?php elseif ($categoria_info): ?>
            Productos - <?php echo htmlspecialchars($categoria_info['nombre']); ?>
        <?php else: ?>
            Lista de Productos
        <?php endif; ?>
        - GRUPO SEAL
    </title>
    
    <!-- Meta tags -->
    <meta name="description" content="Lista de productos del sistema GRUPO SEAL - Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preload de recursos críticos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico y limpio -->
    <link rel="stylesheet" href="../assets/css/productos/productos-tabla.css">
    
    <!-- Prefetch de páginas -->
    <?php if ($pagina_actual < $total_paginas): ?>
    <link rel="prefetch" href="<?php echo buildUrl(['pagina' => $pagina_actual + 1]); ?>">
    <?php endif; ?>
    <?php if ($pagina_actual > 1): ?>
    <link rel="prefetch" href="<?php echo buildUrl(['pagina' => $pagina_actual - 1]); ?>">
    <?php endif; ?>
</head>
<body data-user-role="<?php echo htmlspecialchars($usuario_rol); ?>" 
      data-almacen-id="<?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>"
      data-categoria-id="<?php echo $filtro_categoria_id; ?>"
      data-user-id="<?php echo htmlspecialchars($_SESSION['user_id']); ?>"
      data-page="<?php echo $pagina_actual; ?>"
      data-total-pages="<?php echo $total_paginas; ?>"
      data-context="<?php echo htmlspecialchars($contexto_params); ?>">

<!-- Indicador de carga -->
<div id="loading-indicator" class="loading-indicator" style="display: none;">
    <div class="loading-spinner"></div>
    <span>Cargando productos...</span>
</div>

<!-- Botón hamburguesa -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
</button>

<!-- ===== SIDEBAR Y NAVEGACIÓN UNIFICADO ===== -->
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
<main class="content" id="main-content">
    <!-- Mensajes -->
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

    <!-- Header -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <?php if ($almacen_info && $categoria_info): ?>
                        <i class="fas fa-box-open"></i> <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                        <small>en <?php echo htmlspecialchars($almacen_info['nombre']); ?></small>
                    <?php elseif ($almacen_info): ?>
                        <i class="fas fa-warehouse"></i> Inventario: <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                    <?php elseif ($categoria_info): ?>
                        <i class="fas fa-tag"></i> Productos: <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                    <?php else: ?>
                        <i class="fas fa-boxes"></i> Lista de Productos
                    <?php endif; ?>
                </h1>
                <p class="page-description">
                    <?php if ($total_productos > 0): ?>
                        Mostrando <?php echo number_format($inicio_rango); ?> - <?php echo number_format($fin_rango); ?> 
                        de <?php echo number_format($total_productos); ?> productos
                        <?php if ($total_paginas > 1): ?>
                            - Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        No hay productos que mostrar
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="header-actions">
                <button id="btnEntregarPersonal" class="btn-entregar-personal" title="Activar modo de selección múltiple">
                    <i class="fas fa-hand-holding"></i>
                    <span>Entregar a Personal</span>
                </button>

                <?php if ($usuario_rol == 'admin'): ?>
                <a href="registrar.php<?php 
                    $params = [];
                    if ($filtro_almacen_id) $params[] = 'almacen_id=' . $filtro_almacen_id;
                    if ($filtro_categoria_id) $params[] = 'categoria_id=' . $filtro_categoria_id;
                    echo !empty($params) ? '?' . implode('&', $params) : '';
                ?>" class="btn-header btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo Producto</span>
                </a>
                <?php endif; ?>
                
                <!-- Botón inteligente de retorno -->
                <?php if ($filtro_almacen_id && $filtro_categoria_id): ?>
                <!-- Estamos en una categoría específica de un almacén -->
                <a href="<?php echo obtenerUrlRetornoAlmacen($filtro_almacen_id); ?>" class="btn-header btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver a Categorías</span>
                </a>
                <?php elseif ($filtro_almacen_id): ?>
                <!-- Estamos en un almacén específico -->
                <a href="<?php echo obtenerUrlRetornoAlmacen($filtro_almacen_id); ?>" class="btn-header btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver a Categorías</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Breadcrumb mejorado -->
    <nav class="breadcrumb">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        
        <?php if ($almacen_info): ?>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="<?php echo obtenerUrlRetornoAlmacen($filtro_almacen_id); ?>"><?php echo htmlspecialchars($almacen_info['nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            
            <?php if ($filtro_categoria_id && $categoria_info): ?>
                <span class="current"><?php echo htmlspecialchars($categoria_info['nombre']); ?></span>
            <?php else: ?>
                <span class="current">Productos</span>
            <?php endif; ?>
        <?php else: ?>
            <span class="current">Productos</span>
        <?php endif; ?>
    </nav>

    <!-- Filtros y búsqueda -->
    <section class="filters-section">
        <div class="search-container">
            <form method="GET" class="search-form" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <?php if ($filtro_almacen_id): ?>
                    <input type="hidden" name="almacen_id" value="<?php echo $filtro_almacen_id; ?>">
                <?php endif; ?>
                <?php if ($filtro_categoria_id): ?>
                    <input type="hidden" name="categoria_id" value="<?php echo $filtro_categoria_id; ?>">
                <?php endif; ?>
                
                <div class="search-input-group">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <input 
                        type="text" 
                        name="busqueda" 
                        placeholder="Buscar productos por nombre, modelo o color..." 
                        value="<?php echo htmlspecialchars($busqueda); ?>"
                        class="search-input"
                        autocomplete="off"
                    >
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span>Buscar</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Lista de productos -->
    <section class="products-section" id="productsSection">
        <?php if ($result_productos && $result_productos->num_rows > 0): ?>
            <div class="table-container">
                <table class="products-table" id="productosTabla">
                    <thead>
                        <tr>
                            <th class="selection-column" style="display: none;">
                                <div class="selection-header">
                                    <i class="fas fa-hand-holding"></i>
                                </div>
                            </th>
                            <th class="product-name-column">
                                <i class="fas fa-box"></i> Producto
                            </th>
                            <th class="category-column">
                                <i class="fas fa-tag"></i> Categoría
                            </th>
                            <?php if (!$filtro_almacen_id): ?>
                            <th class="warehouse-column">
                                <i class="fas fa-warehouse"></i> Almacén
                            </th>
                            <?php endif; ?>
                            <th class="details-column">
                                <i class="fas fa-info-circle"></i> Detalles
                            </th>
                            <th class="stock-column">
                                <i class="fas fa-cubes"></i> Stock
                            </th>
                            <th class="status-column">
                                <i class="fas fa-flag"></i> Estado
                            </th>
                            <th class="actions-column">
                                <i class="fas fa-cogs"></i> Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($producto = $result_productos->fetch_assoc()): ?>
                            <tr class="product-row" 
                                data-producto-id="<?php echo $producto['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>"
                                data-categoria="<?php echo htmlspecialchars($producto['categoria_nombre'], ENT_QUOTES); ?>"
                                data-almacen="<?php echo htmlspecialchars($producto['almacen_nombre'], ENT_QUOTES); ?>">
                                
                                <!-- Selección múltiple -->
                                <td class="selection-cell" style="display: none;">
                                    <div class="selection-checkbox" 
                                         data-id="<?php echo $producto['id']; ?>"
                                         tabindex="0">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </td>

                                <!-- Nombre del producto -->
                                <td class="product-name-cell">
                                    <div class="product-info">
                                        <h3 class="product-name"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                        <?php if (!empty($producto['modelo'])): ?>
                                            <span class="product-model">Modelo: <?php echo htmlspecialchars($producto['modelo']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Categoría -->
                                <td class="category-cell">
                                    <span class="category-badge">
                                        <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                    </span>
                                </td>

                                <!-- Almacén -->
                                <?php if (!$filtro_almacen_id): ?>
                                <td class="warehouse-cell">
                                    <span class="warehouse-badge">
                                        <?php echo htmlspecialchars($producto['almacen_nombre']); ?>
                                    </span>
                                </td>
                                <?php endif; ?>

                                <!-- Detalles -->
                                <td class="details-cell">
                                    <div class="product-details">
                                        <?php if (!empty($producto['color'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Color:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($producto['color']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($producto['talla_dimensiones'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Talla:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($producto['talla_dimensiones']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Stock -->
                                <td class="stock-cell">
                                    <div class="stock-container">
                                        <div class="stock-display">
                                            <?php if ($usuario_rol == 'admin'): ?>
                                            <button class="stock-btn decrease" 
                                                    data-id="<?php echo $producto['id']; ?>" 
                                                    data-accion="restar" 
                                                    <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?> 
                                                    title="Reducir stock">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <span class="stock-value <?php 
                                                if ($producto['cantidad'] < 5) echo 'stock-critical';
                                                elseif ($producto['cantidad'] < 10) echo 'stock-warning';
                                                else echo 'stock-good';
                                            ?>" id="cantidad-<?php echo $producto['id']; ?>">
                                                <?php echo number_format($producto['cantidad']); ?>
                                            </span>
                                            
                                            <?php if ($usuario_rol == 'admin'): ?>
                                            <button class="stock-btn increase" 
                                                    data-id="<?php echo $producto['id']; ?>" 
                                                    data-accion="sumar" 
                                                    title="Aumentar stock">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="status-cell">
                                    <span class="status-badge status-<?php echo strtolower($producto['estado']); ?>">
                                        <?php echo htmlspecialchars($producto['estado']); ?>
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <!-- ⭐ BOTÓN VER CON LÓGICA ORIGINAL -->
                                        <button class="btn-action btn-view" 
                                                onclick="verProductoConContexto(<?php echo $producto['id']; ?>)"
                                                title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <?php if ($producto['cantidad'] > 0): ?>
                                        <button class="btn-action btn-transfer" 
                                                data-id="<?php echo $producto['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-almacen="<?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>"
                                                data-cantidad="<?php echo $producto['cantidad']; ?>"
                                                onclick="abrirModalEnvio(this)"
                                                title="Transferir producto">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn-action btn-transfer disabled" 
                                                disabled 
                                                title="Sin stock">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($usuario_rol == 'admin'): ?>
                                        <!-- ⭐ BOTÓN EDITAR CON LÓGICA ORIGINAL -->
                                        <button class="btn-action btn-edit" 
                                                onclick="editarProductoConContexto(<?php echo $producto['id']; ?>)"
                                                title="Editar producto">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <button class="btn-action btn-delete" 
                                                onclick="eliminarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>')"
                                                title="Eliminar producto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Datos para JS -->
                                <script type="application/json" class="product-data">
                                {
                                    "id": <?php echo $producto['id']; ?>,
                                    "nombre": "<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>",
                                    "almacen": <?php echo $filtro_almacen_id ?: $usuario_almacen_id; ?>,
                                    "almacen_nombre": "<?php echo htmlspecialchars($almacen_info ? $almacen_info['nombre'] : $producto['almacen_nombre'], ENT_QUOTES, 'UTF-8'); ?>",
                                    "cantidad": <?php echo $producto['cantidad']; ?>,
                                    "modelo": "<?php echo htmlspecialchars($producto['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
                                    "color": "<?php echo htmlspecialchars($producto['color'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
                                    "talla": "<?php echo htmlspecialchars($producto['talla_dimensiones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                }
                                </script>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación MEJORADA -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    <i class="fas fa-info-circle"></i>
                    Mostrando <?php echo number_format($inicio_rango); ?> - <?php echo number_format($fin_rango); ?> 
                    de <?php echo number_format($total_productos); ?> productos
                </div>
                
                <nav class="pagination" aria-label="Navegación de páginas">
                    <?php if ($pagina_actual > 1): ?>
                        <!-- Primera página -->
                        <a href="<?php echo buildUrl(['pagina' => 1]); ?>" 
                           class="pagination-btn first" 
                           title="Primera página"
                           aria-label="Ir a la primera página">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- Página anterior -->
                        <a href="<?php echo buildUrl(['pagina' => $pagina_actual - 1]); ?>" 
                           class="pagination-btn prev" 
                           title="Página anterior"
                           aria-label="Ir a la página anterior">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <!-- Botones deshabilitados -->
                        <span class="pagination-btn first disabled" title="Primera página">
                            <i class="fas fa-angle-double-left"></i>
                        </span>
                        <span class="pagination-btn prev disabled" title="Página anterior">
                            <i class="fas fa-angle-left"></i>
                        </span>
                    <?php endif; ?>

                    <?php
                    // Calcular rango de páginas a mostrar
                    $rango_inicio = max(1, $pagina_actual - 2);
                    $rango_fin = min($total_paginas, $pagina_actual + 2);
                    
                    // Ajustar para mostrar siempre 5 páginas cuando sea posible
                    if ($rango_fin - $rango_inicio < 4) {
                        if ($rango_inicio == 1) {
                            $rango_fin = min($total_paginas, $rango_inicio + 4);
                        } else {
                            $rango_inicio = max(1, $rango_fin - 4);
                        }
                    }
                    
                    // Mostrar "..." si hay páginas anteriores
                    if ($rango_inicio > 1): ?>
                        <a href="<?php echo buildUrl(['pagina' => 1]); ?>" class="pagination-btn">1</a>
                        <?php if ($rango_inicio > 2): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php
                    // Mostrar páginas en el rango
                    for ($i = $rango_inicio; $i <= $rango_fin; $i++):
                    ?>
                        <?php if ($i == $pagina_actual): ?>
                            <span class="pagination-btn current" 
                                  aria-label="Página actual, página <?php echo $i; ?>"
                                  aria-current="page">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo buildUrl(['pagina' => $i]); ?>" 
                               class="pagination-btn"
                               aria-label="Ir a la página <?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php
                    // Mostrar "..." si hay páginas posteriores
                    if ($rango_fin < $total_paginas): ?>
                        <?php if ($rango_fin < $total_paginas - 1): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="<?php echo buildUrl(['pagina' => $total_paginas]); ?>" class="pagination-btn">
                            <?php echo $total_paginas; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($pagina_actual < $total_paginas): ?>
                        <!-- Página siguiente -->
                        <a href="<?php echo buildUrl(['pagina' => $pagina_actual + 1]); ?>" 
                           class="pagination-btn next" 
                           title="Página siguiente"
                           aria-label="Ir a la página siguiente">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- Última página -->
                        <a href="<?php echo buildUrl(['pagina' => $total_paginas]); ?>" 
                           class="pagination-btn last" 
                           title="Última página"
                           aria-label="Ir a la última página">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <!-- Botones deshabilitados -->
                        <span class="pagination-btn next disabled" title="Página siguiente">
                            <i class="fas fa-angle-right"></i>
                        </span>
                        <span class="pagination-btn last disabled" title="Última página">
                            <i class="fas fa-angle-double-right"></i>
                        </span>
                    <?php endif; ?>
                </nav>
                
                <!-- Información adicional -->
                <div class="pagination-summary">
                    <small>
                        Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                    </small>
                </div>
            </div>
            <?php else: ?>
            <!-- No hay suficientes productos para paginación -->
            <div class="pagination-container single-page">
                <div class="pagination-info">
                    <i class="fas fa-info-circle"></i>
                    Mostrando todos los <?php echo number_format($total_productos); ?> productos
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>No hay productos registrados</h3>
                <p>
                    <?php if (!empty($busqueda)): ?>
                        No se encontraron productos que coincidan con "<?php echo htmlspecialchars($busqueda); ?>".
                    <?php else: ?>
                        Aún no se han registrado productos en el sistema.
                    <?php endif; ?>
                </p>
                
                <div class="empty-actions">
                    <?php if (!empty($busqueda)): ?>
                    <a href="listar.php" class="btn-secondary">
                        <i class="fas fa-times-circle"></i> Limpiar filtros
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($usuario_rol == 'admin'): ?>
                    <a href="registrar.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Registrar Producto
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Carrito de Entrega MEJORADO PARA ESQUINA DERECHA -->
<div id="carritoEntrega" class="carrito-entrega">
    <div class="carrito-header">
        <div class="carrito-title">
            <!-- Versión completa para pantallas normales -->
            <span class="carrito-title-long">
                <i class="fas fa-hand-holding"></i>
                Productos para Entrega
            </span>
            
            <!-- Versión corta para pantallas muy pequeñas -->
            <span class="carrito-title-short">
                <i class="fas fa-shopping-cart"></i>
                Entrega
            </span>
            
            <span class="carrito-contador">0</span>
        </div>
        <!-- El botón toggle se agrega dinámicamente -->
    </div>
    
    <div class="carrito-lista" id="carritoLista">
        <div class="carrito-vacio">
            <i class="fas fa-hand-holding"></i>
            <p>Selecciona productos para entregar</p>
        </div>
    </div>
    
    <div class="carrito-footer">
        <div class="carrito-resumen">
            <div class="carrito-total">
                Total: <span id="totalUnidades">0</span> unidades
            </div>
        </div>
        
        <div class="carrito-acciones">
            <button class="btn-carrito btn-limpiar" onclick="limpiarCarrito()">
                <i class="fas fa-trash"></i>
                Limpiar
            </button>
            <button class="btn-carrito btn-proceder" onclick="procederEntrega()" disabled>
                <i class="fas fa-user"></i>
                Proceder
            </button>
        </div>
    </div>
</div>

<!-- El indicador se crea dinámicamente con JavaScript -->

<!-- Modal de Entrega -->
<div id="modalEntrega" class="modal-entrega">
    <div class="modal-entrega-content">
        <div class="modal-entrega-header">
            <h2>
                <i class="fas fa-user"></i>
                Datos del Destinatario
            </h2>
        </div>
        
        <div class="modal-entrega-body">
            <div class="resumen-entrega">
                <div class="resumen-titulo">
                    <i class="fas fa-clipboard-list"></i>
                    Resumen de la Entrega
                </div>
                
                <div class="productos-resumen" id="productosResumen">
                    <!-- Se llena dinámicamente -->
                </div>
                
                <div class="total-unidades">
                    <i class="fas fa-boxes"></i>
                    Total: <span id="totalUnidadesModal">0</span> unidades de <span id="totalTiposModal">0</span> tipo(s) de productos
                </div>
            </div>
            
            <form id="formEntregaPersonal">
                <div class="form-group">
                    <label for="nombreDestinatario" class="form-label">
                        <i class="fas fa-user"></i>
                        Nombre Completo del Destinatario *
                    </label>
                    <input 
                        type="text" 
                        id="nombreDestinatario" 
                        name="nombre_destinatario" 
                        required 
                        class="form-control"
                        placeholder="Ingrese el nombre completo"
                    >
                </div>
                
                <div class="form-group">
                    <label for="dniDestinatario" class="form-label">
                        <i class="fas fa-id-card"></i>
                        DNI del Destinatario *
                    </label>
                    <input 
                        type="text" 
                        id="dniDestinatario" 
                        name="dni_destinatario" 
                        required 
                        class="form-control"
                        placeholder="12345678"
                        pattern="[0-9]{8}"
                        maxlength="8"
                    >
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-cancel" onclick="cerrarModalEntrega()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <button type="button" class="btn-modal btn-confirm" onclick="confirmarEntrega()" disabled>
                <i class="fas fa-hand-holding"></i>
                Confirmar Entrega
            </button>
        </div>
    </div>
</div>

<!-- Modal de Transferencia -->
<div id="modalTransferencia" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>
                <i class="fas fa-paper-plane"></i>
                Transferir Producto
            </h2>
            <button class="modal-close" onclick="cerrarModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="formTransferencia" method="POST" action="procesar_formulario.php">
            <div class="modal-body">
                <input type="hidden" id="producto_id" name="producto_id">
                <input type="hidden" id="almacen_origen" name="almacen_origen">
                
                <div class="transfer-info">
                    <div class="product-summary">
                        <div class="product-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="product-details-modal">
                            <h3 id="producto_nombre"></h3>
                            <p>Stock disponible: <span id="stock_disponible" class="stock-highlight"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cantidad" class="form-label">
                        <i class="fas fa-sort-numeric-up"></i>
                        Cantidad a transferir
                    </label>
                    <div class="quantity-input">
                        <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="cantidad" name="cantidad" min="1" value="1" class="qty-input">
                        <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="almacen_destino" class="form-label">
                        <i class="fas fa-warehouse"></i>
                        Almacén de destino
                    </label>
                    <select id="almacen_destino" name="almacen_destino" required class="form-select">
                        <option value="">Seleccione un almacén</option>
                        <?php foreach ($almacenes_disponibles as $almacen): ?>
                            <option value="<?php echo $almacen['id']; ?>">
                                <?php echo htmlspecialchars($almacen['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
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

<!-- Container para notificaciones -->
<div id="notificaciones-container"></div>

<!-- ⭐ JAVASCRIPT CON LÓGICA ORIGINAL + URLs LIMPIAS -->
<script>
// Variables globales para el contexto
const CONTEXTO_PARAMS = document.body.dataset.context || '';
const FILTRO_ALMACEN_ID = <?php echo $filtro_almacen_id ? $filtro_almacen_id : 'null'; ?>;
const FILTRO_CATEGORIA_ID = <?php echo $filtro_categoria_id ? $filtro_categoria_id : 'null'; ?>;

// Guardar contexto en sessionStorage para navegación
function guardarContextoProductos() {
    const context = {
        page: 'productos-listar',
        filtro_almacen_id: FILTRO_ALMACEN_ID,
        filtro_categoria_id: FILTRO_CATEGORIA_ID,
        timestamp: Date.now()
    };
    
    sessionStorage.setItem('productos_context', JSON.stringify(context));
}

// ⭐ FUNCIONES ORIGINALES CON URLs LIMPIAS
function verProductoConContexto(id) {
    guardarContextoProductos();
    const baseUrl = 'ver-producto.php?id=' + id;
    const fullUrl = CONTEXTO_PARAMS ? baseUrl + '&from=' + encodeURIComponent(CONTEXTO_PARAMS) : baseUrl;
    window.location.href = fullUrl;
}

function editarProductoConContexto(id) {
    guardarContextoProductos();
    const baseUrl = 'editar.php?id=' + id;
    const fullUrl = CONTEXTO_PARAMS ? baseUrl + '&from=' + encodeURIComponent(CONTEXTO_PARAMS) : baseUrl;
    window.location.href = fullUrl;
}

// Funciones de compatibilidad
function verProducto(id) {
    verProductoConContexto(id);
}

function editarProducto(id) {
    editarProductoConContexto(id);
}

// Manejar navegación del navegador (botón atrás)
window.addEventListener('popstate', function(event) {
    // Si viene del almacén y tiene contexto guardado, redirigir correctamente
    const almacenContext = sessionStorage.getItem('almacen_context');
    
    if (almacenContext && FILTRO_ALMACEN_ID) {
        const context = JSON.parse(almacenContext);
        if (context.almacen_id === FILTRO_ALMACEN_ID && context.page === 'ver-almacen') {
            // Redirigir al almacén de forma segura
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../almacenes/ver_redirect.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'view_almacen_id';
            input.value = context.almacen_id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Guardar contexto inicial
    guardarContextoProductos();
    
    // ⭐ LIMPIAR URL EN EL HISTORIAL (SIN AFECTAR FUNCIONALIDAD)
    if (window.location.search && window.history.replaceState) {
        const cleanUrl = window.location.pathname;
        window.history.replaceState(
            { context: CONTEXTO_PARAMS }, 
            document.title, 
            cleanUrl
        );
    }
});
</script>

<!-- JavaScript principal -->
<script src="../assets/js/productos-listar-tabla.js"></script>

</body>
</html>