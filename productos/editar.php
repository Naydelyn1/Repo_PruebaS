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

// Verificar que el usuario sea administrador
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tiene permisos para editar productos.";
    header("Location: listar.php");
    exit();
}

// Validar el ID del producto
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de producto no válido.";
    header("Location: listar.php");
    exit();
}

$producto_id = $_GET['id'];

// Obtener y procesar parámetros de contexto
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

// Función para determinar URL de ver producto
function buildVerProductoUrl($producto_id, $context_params) {
    $base_url = 'ver-producto.php?id=' . $producto_id;
    return $context_params ? $base_url . '&from=' . urlencode($context_params) : $base_url;
}

// Función para determinar si el retorno debe ser al almacén
function shouldReturnToWarehouse($context_array) {
    // Si no hay contexto específico de lista, volver al almacén
    if (empty($context_array) || 
        (!isset($context_array['categoria_id']) && !isset($context_array['busqueda']) && !isset($context_array['pagina']))) {
        return true;
    }
    return false;
}

// Obtener información del producto
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
    $_SESSION['error'] = "Producto no encontrado.";
    header("Location: listar.php");
    exit();
}

// Construir URLs de navegación con contexto
$return_url = buildReturnUrl($context_array, $producto);
$return_text = getContextDescription($context_array, $producto);
$ver_producto_url = buildVerProductoUrl($producto_id, $context_params);
$should_return_to_warehouse = shouldReturnToWarehouse($context_array);

// URL para retorno al almacén
$warehouse_return_url = "../almacenes/ver_redirect.php?id=" . $producto['almacen_id'];

// Obtener lista de categorías
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
$categorias = $conn->query($sql_categorias);

// Obtener lista de almacenes
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
$almacenes = $conn->query($sql_almacenes);

$mensaje = "";
$error = "";

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"] ?? '');
    $modelo = trim($_POST["modelo"] ?? '');
    $color = trim($_POST["color"] ?? '');
    $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
    $cantidad = isset($_POST["cantidad"]) ? intval($_POST["cantidad"]) : 0;
    $unidad_medida = trim($_POST["unidad_medida"] ?? '');
    $estado = trim($_POST["estado"] ?? '');
    $observaciones = trim($_POST["observaciones"] ?? '');
    $categoria_id = isset($_POST["categoria_id"]) ? intval($_POST["categoria_id"]) : 0;
    $almacen_id = isset($_POST["almacen_id"]) ? intval($_POST["almacen_id"]) : 0;

    if (!empty($nombre) && $cantidad >= 0 && !empty($unidad_medida) && !empty($estado) && $categoria_id > 0 && $almacen_id > 0) {
        // Verificar si el nombre ya existe en otro producto del mismo almacén
        $sql_check = "SELECT id FROM productos WHERE nombre = ? AND almacen_id = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("sii", $nombre, $almacen_id, $producto_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ Ya existe un producto con ese nombre en el almacén seleccionado.";
        } else {
            // Actualizar el producto
            $sql_update = "UPDATE productos SET 
                          nombre = ?, modelo = ?, color = ?, talla_dimensiones = ?, 
                          cantidad = ?, unidad_medida = ?, estado = ?, observaciones = ?, 
                          categoria_id = ?, almacen_id = ? 
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                $stmt_update->bind_param("ssssisssiii", 
                    $nombre, $modelo, $color, $talla_dimensiones, 
                    $cantidad, $unidad_medida, $estado, $observaciones, 
                    $categoria_id, $almacen_id, $producto_id
                );
                
                if ($stmt_update->execute()) {
                    $_SESSION['success'] = "✅ Producto actualizado con éxito.";
                    
                    // Redirigir a ver producto manteniendo el contexto original
                    header("Location: " . $ver_producto_url);
                    exit();
                } else {
                    $error = "❌ Error al actualizar el producto: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $error = "⚠️ Todos los campos obligatorios deben estar completos.";
    }
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - <?php echo htmlspecialchars($producto['nombre']); ?> - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar producto <?php echo htmlspecialchars($producto['nombre']); ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico mejorado -->
    <link rel="stylesheet" href="../assets/css/productos/productos-editar.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body data-almacen-id="<?php echo $producto['almacen_id']; ?>" data-producto-id="<?php echo $producto_id; ?>">

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar y navegación -->
<nav class="sidebar" id="sidebar" role="navigation" aria-label="Menú principal">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li>
            <a href="../dashboard.php" aria-label="Ir a inicio">
                <span><i class="fas fa-home"></i> Inicio</span>
            </a>
        </li>

        <!-- Sección Usuarios - Solo visible para administradores -->
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

        <!-- Sección Almacenes -->
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
        
        <!-- Sección Historial -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Historial" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-history"></i> Historial</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
            </ul>
        </li>
        
        <!-- Sección Notificaciones -->
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

        <!-- Sección Reportes (Solo admin) -->
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

        <!-- Perfil de usuario -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Perfil" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-user-circle"></i> Mi Perfil</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../perfil/cambiar-password.php" role="menuitem"><i class="fas fa-key"></i> Cambiar Contraseña</a></li>
            </ul>
        </li>

        <!-- Cerrar sesión -->
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
    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Header de página -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            Editar Producto
        </h1>
        <p class="page-description">
            Modifica la información del producto "<strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>" de manera organizada y eficiente
        </p>
        
        <!-- Breadcrumb dinámico -->
        <div class="breadcrumb" id="breadcrumbContainer">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <!-- Se completará dinámicamente -->
            <span class="current">Editar</span>
        </div>
    </div>

    <!-- Layout de dos columnas -->
    <div class="edit-layout">
        <!-- Columna principal - Formulario -->
        <div class="edit-main">
            <div class="edit-container">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h2>Editar Información del Producto</h2>
                    <p>Complete los campos siguientes para actualizar la información del producto de manera organizada</p>
                </div>

                <form id="formEditarProducto" action="" method="POST" autocomplete="off">
                    
                    <!-- Sección 1: Información Básica -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-info-circle"></i> Información Básica</h3>
                            <p class="form-section-subtitle">Datos principales e identificación del producto</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-grid two-columns">
                                <div class="form-group">
                                    <label for="nombre" class="form-label">
                                        <i class="fas fa-box"></i>
                                        Nombre del Producto
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="nombre" 
                                        name="nombre" 
                                        value="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                        required
                                        autocomplete="off"
                                        maxlength="100"
                                        placeholder="Ingrese el nombre descriptivo del producto"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Nombre único y descriptivo que identifique claramente el producto
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="categoria_id" class="form-label">
                                        <i class="fas fa-tags"></i>
                                        Categoría
                                        <span class="required">*</span>
                                    </label>
                                    <select id="categoria_id" name="categoria_id" required>
                                        <option value="">Seleccione una categoría</option>
                                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                            <option value="<?php echo $categoria['id']; ?>" 
                                                    <?php echo ($categoria['id'] == $producto['categoria_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($categoria['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Categoría a la que pertenece este producto para su clasificación
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Características del Producto (MEJORADA) -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-cogs"></i> Características del Producto</h3>
                            <p class="form-section-subtitle">Detalles específicos y propiedades físicas del producto</p>
                        </div>
                        <div class="form-section-content">
                            <!-- Primera fila: Modelo y Color (2 columnas balanceadas) -->
                            <div class="form-grid two-columns">
                                <div class="form-group">
                                    <label for="modelo" class="form-label">
                                        <i class="fas fa-tag"></i>
                                        Modelo o Referencia
                                    </label>
                                    <input 
                                        type="text" 
                                        id="modelo" 
                                        name="modelo" 
                                        value="<?php echo htmlspecialchars($producto['modelo']); ?>" 
                                        autocomplete="off"
                                        maxlength="50"
                                        placeholder="Ej: ABC-123, Modelo X, Ref-2024"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Modelo, referencia o código del fabricante
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="color" class="form-label">
                                        <i class="fas fa-palette"></i>
                                        Color Principal
                                    </label>
                                    <input 
                                        type="text" 
                                        id="color" 
                                        name="color" 
                                        value="<?php echo htmlspecialchars($producto['color']); ?>" 
                                        autocomplete="off"
                                        maxlength="30"
                                        placeholder="Ej: Negro, Azul marino, Multicolor"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Color predominante o característica visual principal
                                    </div>
                                </div>
                            </div>

                            <!-- Segunda fila: Talla/Dimensiones y Estado (2 columnas balanceadas) -->
                            <div class="form-grid two-columns">
                                <div class="form-group">
                                    <label for="talla_dimensiones" class="form-label">
                                        <i class="fas fa-ruler"></i>
                                        Talla / Dimensiones
                                    </label>
                                    <input 
                                        type="text" 
                                        id="talla_dimensiones" 
                                        name="talla_dimensiones" 
                                        value="<?php echo htmlspecialchars($producto['talla_dimensiones']); ?>" 
                                        autocomplete="off"
                                        maxlength="50"
                                        placeholder="Ej: Talla L, 25x15x10 cm, Ø 5cm"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Talla de ropa, dimensiones físicas o medidas relevantes
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="estado" class="form-label">
                                        <i class="fas fa-shield-alt"></i>
                                        Estado del Producto
                                        <span class="required">*</span>
                                    </label>
                                    <select id="estado" name="estado" required>
                                        <option value="">Seleccione el estado</option>
                                        <option value="Nuevo" <?php echo ($producto['estado'] === 'Nuevo') ? 'selected' : ''; ?>>
                                            🆕 Nuevo
                                        </option>
                                        <option value="Usado" <?php echo ($producto['estado'] === 'Usado') ? 'selected' : ''; ?>>
                                            ♻️ Usado - Buen Estado
                                        </option>
                                        <option value="Dañado" <?php echo ($producto['estado'] === 'Dañado') ? 'selected' : ''; ?>>
                                            ⚠️ Dañado - Requiere Atención
                                        </option>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Condición física actual del producto
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Inventario y Ubicación -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-warehouse"></i> Inventario y Ubicación</h3>
                            <p class="form-section-subtitle">Control de stock, medidas y almacenamiento</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-grid three-columns">
                                <div class="form-group">
                                    <label for="cantidad" class="form-label">
                                        <i class="fas fa-sort-numeric-up"></i>
                                        Cantidad Disponible
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="number" 
                                        id="cantidad" 
                                        name="cantidad" 
                                        value="<?php echo $producto['cantidad']; ?>" 
                                        min="0"
                                        max="99999"
                                        required
                                        placeholder="0"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Cantidad actual disponible en inventario
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="unidad_medida" class="form-label">
                                        <i class="fas fa-balance-scale"></i>
                                        Unidad de Medida
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="unidad_medida" 
                                        name="unidad_medida" 
                                        value="<?php echo htmlspecialchars($producto['unidad_medida']); ?>" 
                                        required
                                        autocomplete="off"
                                        maxlength="20"
                                        placeholder="Ej: unidades, kg, litros, metros"
                                    >
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Unidad en la que se mide o cuenta este producto
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="almacen_id" class="form-label">
                                        <i class="fas fa-building"></i>
                                        Almacén de Ubicación
                                        <span class="required">*</span>
                                    </label>
                                    <select id="almacen_id" name="almacen_id" required>
                                        <option value="">Seleccione un almacén</option>
                                        <?php while ($almacen = $almacenes->fetch_assoc()): ?>
                                            <option value="<?php echo $almacen['id']; ?>" 
                                                    <?php echo ($almacen['id'] == $producto['almacen_id']) ? 'selected' : ''; ?>>
                                                🏢 <?php echo htmlspecialchars($almacen['nombre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="field-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Ubicación física donde se almacena el producto
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 4: Observaciones Adicionales -->
                    <div class="form-section-card">
                        <div class="form-section-header">
                            <h3><i class="fas fa-comment-alt"></i> Observaciones Adicionales</h3>
                            <p class="form-section-subtitle">Información complementaria, notas especiales y comentarios</p>
                        </div>
                        <div class="form-section-content">
                            <div class="form-group full-width">
                                <label for="observaciones" class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Notas y Observaciones
                                </label>
                                <textarea 
                                    id="observaciones" 
                                    name="observaciones" 
                                    rows="5"
                                    maxlength="500"
                                    placeholder="Escriba aquí cualquier información adicional relevante sobre el producto:&#10;• Instrucciones especiales de manejo&#10;• Características técnicas importantes&#10;• Notas sobre su uso o aplicación&#10;• Información de mantenimiento&#10;• Observaciones de calidad"
                                ><?php echo htmlspecialchars($producto['observaciones']); ?></textarea>
                                <div class="field-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Información complementaria que puede ser útil para el manejo, uso o identificación del producto (máximo 500 caracteres)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones del formulario -->
                    <div class="form-actions-card">
                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="btnGuardar">
                                <i class="fas fa-save"></i>
                                Guardar Cambios
                            </button>
                            
                            <button type="button" class="btn-cancel" onclick="navegarRetorno()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Barra lateral derecha - Acciones adicionales -->
        <div class="edit-sidebar">
            <div class="additional-actions">
                <div class="additional-actions-header">
                    <h3>Acciones Rápidas</h3>
                    <p>Navegación y opciones adicionales</p>
                </div>
                
                <div class="action-item">
                    <a href="<?php echo $ver_producto_url; ?>" class="action-link">
                        <i class="fas fa-eye"></i>
                        <div>
                            <strong>Ver Detalles Completos</strong>
                            <small>Vista completa del producto con toda la información</small>
                        </div>
                    </a>
                </div>
                
                <div class="action-item">
                    <a href="javascript:void(0)" onclick="navegarRetorno()" class="action-link">
                        <i class="fas fa-list"></i>
                        <div>
                            <strong id="textoRetorno">Volver a Lista</strong>
                            <small id="subtextoRetorno"><?php echo $return_text; ?></small>
                        </div>
                    </a>
                </div>
                
                <div class="action-item">
                    <a href="../almacenes/ver_redirect.php?id=<?php echo $producto['almacen_id']; ?>" class="action-link">
                        <i class="fas fa-warehouse"></i>
                        <div>
                            <strong>Ver Almacén</strong>
                            <small>Ir al almacén: <?php echo htmlspecialchars($producto['almacen_nombre']); ?></small>
                        </div>
                    </a>
                </div>
                
                <div class="action-item">
                    <a href="#" onclick="eliminarProducto(<?php echo $producto_id; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')" class="action-link danger">
                        <i class="fas fa-trash-alt"></i>
                        <div>
                            <strong>Eliminar Producto</strong>
                            <small>⚠️ Acción irreversible - Use con precaución</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Container para notificaciones dinámicas -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<script>
// Variables globales para el contexto
const CONTEXT_PARAMS = '<?php echo urlencode($context_params); ?>';
const PRODUCT_ID = <?php echo $producto_id; ?>;
const ALMACEN_ID = <?php echo $producto['almacen_id']; ?>;
const RETURN_URL = '<?php echo $return_url; ?>';
const RETURN_TEXT = '<?php echo addslashes($return_text); ?>';
const VER_PRODUCTO_URL = '<?php echo $ver_producto_url; ?>';
const SHOULD_RETURN_TO_WAREHOUSE = <?php echo $should_return_to_warehouse ? 'true' : 'false'; ?>;
const WAREHOUSE_RETURN_URL = '<?php echo $warehouse_return_url; ?>';

// Función para configurar la interfaz según el contexto
function configurarInterfazContexto() {
    const textoRetorno = document.getElementById('textoRetorno');
    const subtextoRetorno = document.getElementById('subtextoRetorno');
    const breadcrumbContainer = document.getElementById('breadcrumbContainer');
    
    if (SHOULD_RETURN_TO_WAREHOUSE) {
        // Configurar para retorno al almacén
        textoRetorno.textContent = 'Volver al Almacén';
        subtextoRetorno.textContent = 'Ver almacén completo';
        
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarAlAlmacen()">Almacén</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="${VER_PRODUCTO_URL}"><?php echo htmlspecialchars($producto['nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Editar</span>
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
            <a href="${VER_PRODUCTO_URL}"><?php echo htmlspecialchars($producto['nombre']); ?></a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Editar</span>
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

document.addEventListener('DOMContentLoaded', function() {
    // Configurar la interfaz según el contexto
    configurarInterfazContexto();
    
    // Elementos principales
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    const form = document.getElementById('formEditarProducto');
    
    // Valores originales para detectar cambios
    const valoresOriginales = {
        nombre: document.getElementById('nombre').value,
        modelo: document.getElementById('modelo').value,
        color: document.getElementById('color').value,
        talla_dimensiones: document.getElementById('talla_dimensiones').value,
        cantidad: document.getElementById('cantidad').value,
        unidad_medida: document.getElementById('unidad_medida').value,
        estado: document.getElementById('estado').value,
        observaciones: document.getElementById('observaciones').value,
        categoria_id: document.getElementById('categoria_id').value,
        almacen_id: document.getElementById('almacen_id').value
    };
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
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
                
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                submenu.classList.toggle('activo');
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
    
    // Detección de cambios en el formulario
    function detectarCambios() {
        const inputs = form.querySelectorAll('input, select, textarea');
        let hasChanges = false;
        
        inputs.forEach(input => {
            if (input.value !== valoresOriginales[input.name]) {
                hasChanges = true;
                input.classList.add('modified');
            } else {
                input.classList.remove('modified');
            }
        });
        
        const submitBtn = document.getElementById('btnGuardar');
        if (submitBtn) {
            if (hasChanges) {
                submitBtn.classList.add('has-changes');
            } else {
                submitBtn.classList.remove('has-changes');
            }
        }
        
        return hasChanges;
    }
    
    // Validación y envío del formulario con confirmación
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('input', detectarCambios);
            input.addEventListener('change', detectarCambios);
        });
        
        form.addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const cantidad = document.getElementById('cantidad').value;
            const unidad_medida = document.getElementById('unidad_medida').value.trim();
            const estado = document.getElementById('estado').value;
            const categoria_id = document.getElementById('categoria_id').value;
            const almacen_id = document.getElementById('almacen_id').value;
            
            // Validaciones básicas
            if (!nombre || !unidad_medida || !estado || !categoria_id || !almacen_id) {
                e.preventDefault();
                mostrarNotificacion('Todos los campos obligatorios deben estar completos', 'error');
                return;
            }
            
            if (parseInt(cantidad) < 0) {
                e.preventDefault();
                mostrarNotificacion('La cantidad no puede ser negativa', 'error');
                return;
            }
            
            // Verificar si hay cambios
            if (!detectarCambios()) {
                e.preventDefault();
                mostrarNotificacion('No se han realizado cambios', 'warning');
                return;
            }
            
            const btnSubmit = document.getElementById('btnGuardar');
            const originalText = btnSubmit.innerHTML;
            
            // Mostrar estado de carga
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btnSubmit.disabled = true;
        });
    }
    
    // Contador de caracteres para textarea
    const observacionesTextarea = document.getElementById('observaciones');
    if (observacionesTextarea) {
        const maxLength = observacionesTextarea.getAttribute('maxlength');
        const hintElement = observacionesTextarea.nextElementSibling;
        
        function actualizarContador() {
            const currentLength = observacionesTextarea.value.length;
            const remaining = maxLength - currentLength;
            
            if (hintElement) {
                const originalText = hintElement.textContent;
                const baseText = originalText.split('(máximo')[0];
                hintElement.textContent = `${baseText}(${remaining} caracteres restantes)`;
                
                if (remaining < 50) {
                    hintElement.style.color = 'var(--warning-color)';
                } else if (remaining < 20) {
                    hintElement.style.color = 'var(--danger-color)';
                } else {
                    hintElement.style.color = '';
                }
            }
        }
        
        observacionesTextarea.addEventListener('input', actualizarContador);
        actualizarContador(); // Llamar al cargar la página
    }
    
    // Manejar navegación del navegador (botón atrás)
    window.addEventListener('popstate', function(event) {
        // Navegar según el contexto
        navegarRetorno();
    });
});

// Función para mostrar notificaciones mejorada
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        'exito': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle', 
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos['info']}"></i>
        <span>${mensaje}</span>
        <button class="cerrar" onclick="this.parentElement.remove()" aria-label="Cerrar notificación">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    // Auto-remover después de la duración especificada
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }
}

// Función para eliminar producto con confirmación mejorada
async function eliminarProducto(id, nombre) {
    const confirmacion = confirm(
        `⚠️ CONFIRMACIÓN DE ELIMINACIÓN\n\n` +
        `¿Está completamente seguro de que desea eliminar el producto:\n` +
        `"${nombre}"?\n\n` +
        `Esta acción es IRREVERSIBLE y eliminará:\n` +
        `• Toda la información del producto\n` +
        `• Historial de movimientos\n` +
        `• Referencias en reportes\n\n` +
        `Haga clic en "Aceptar" solo si está seguro.`
    );
    
    if (confirmacion) {
        mostrarNotificacion('Eliminando producto, por favor espere...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                mostrarNotificacion('✅ Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    navegarRetorno();
                }, 2000);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});

// Funciones adicionales para mejorar la experiencia de usuario
function validarCampoEnTiempoReal(campo) {
    const valor = campo.value.trim();
    const grupo = campo.closest('.form-group');
    
    // Limpiar estados previos
    grupo.classList.remove('success', 'error', 'warning');
    
    if (campo.hasAttribute('required') && !valor) {
        grupo.classList.add('error');
        return false;
    }
    
    if (valor) {
        grupo.classList.add('success');
        return true;
    }
    
    return true;
}

// Aplicar validación en tiempo real a todos los campos requeridos
document.addEventListener('DOMContentLoaded', function() {
    const camposRequeridos = document.querySelectorAll('input[required], select[required]');
    
    camposRequeridos.forEach(campo => {
        campo.addEventListener('blur', function() {
            validarCampoEnTiempoReal(this);
        });
        
        campo.addEventListener('input', function() {
            // Debounce para evitar demasiadas validaciones
            clearTimeout(this.validationTimeout);
            this.validationTimeout = setTimeout(() => {
                validarCampoEnTiempoReal(this);
            }, 500);
        });
    });
});
</script>
</body>
</html>