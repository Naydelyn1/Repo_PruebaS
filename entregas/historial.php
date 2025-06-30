<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
$usuario_rol = $_SESSION["user_role"] ?? "usuario";

require_once "../config/database.php";

// Obtener parámetros de filtro
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Verificar permisos
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    $_SESSION['error'] = "No tienes permiso para ver entregas de este almacén";
    header("Location: ../dashboard.php");
    exit();
}

// Obtener almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    if ($usuario_almacen_id) {
        $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt_almacenes = $conn->prepare($sql_almacenes);
        $stmt_almacenes->bind_param("i", $usuario_almacen_id);
        $stmt_almacenes->execute();
        $result_almacenes = $stmt_almacenes->get_result();
    } else {
        $result_almacenes = false;
    }
}

// Determinar qué almacén mostrar
$almacen_id_mostrar = null;
if ($usuario_rol == 'admin') {
    $almacen_id_mostrar = $filtro_almacen_id;
} else {
    $almacen_id_mostrar = $usuario_almacen_id;
}

// Obtener información del almacén seleccionado
$almacen_info = null;
if ($almacen_id_mostrar) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $almacen_id_mostrar);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener categorías que tienen entregas en este almacén
$categorias_con_entregas = [];
if ($almacen_id_mostrar) {
    $sql_categorias = "SELECT DISTINCT c.id, c.nombre, COUNT(eu.id) as total_entregas,
                       SUM(eu.cantidad) as total_productos_entregados
                       FROM categorias c
                       INNER JOIN productos p ON c.id = p.categoria_id
                       INNER JOIN entrega_uniformes eu ON p.id = eu.producto_id
                       WHERE eu.almacen_id = ?
                       GROUP BY c.id, c.nombre
                       ORDER BY c.nombre";
    $stmt_categorias = $conn->prepare($sql_categorias);
    $stmt_categorias->bind_param("i", $almacen_id_mostrar);
    $stmt_categorias->execute();
    $categorias_con_entregas = $stmt_categorias->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_categorias->close();
}

// Obtener información de la categoría seleccionada
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Función corregida para obtener entregas agrupadas SIN paginación en la consulta
function obtenerTodasLasEntregasAgrupadas($conn, $almacen_id, $categoria_id = null, $filtros = []) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre,
            u.nombre as usuario_responsable,
            c.nombre as categoria_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        JOIN
            categorias c ON p.categoria_id = c.id
        LEFT JOIN
            usuarios u ON eu.usuario_responsable_id = u.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Filtro por categoría
    if ($categoria_id) {
        $query .= ' AND p.categoria_id = ?';
        $params[] = $categoria_id;
        $types .= 'i';
    }

    // Otros filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['nombre'])) {
        $query .= ' AND eu.nombre_destinatario LIKE ?';
        $params[] = '%' . $filtros['nombre'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND DATE(eu.fecha_entrega) >= ?';
        $params[] = $filtros['fecha_inicio'];
        $types .= 's';
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND DATE(eu.fecha_entrega) <= ?';
        $params[] = $filtros['fecha_fin'];
        $types .= 's';
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC, eu.id DESC';
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Error preparando consulta: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        error_log("Error ejecutando consulta: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    $entregasAgrupadas = [];
    
    // Agrupar por fecha_entrega + nombre_destinatario + dni_destinatario
    while ($row = $result->fetch_assoc()) {
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'id' => $row['id'],
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'usuario_responsable' => $row['usuario_responsable'],
                'categoria_nombre' => $row['categoria_nombre'],
                'productos' => []
            ];
        }
        
        $entregasAgrupadas[$key]['productos'][] = [
            'nombre' => $row['producto_nombre'],
            'cantidad' => $row['cantidad']
        ];
    }

    $stmt->close();
    
    // Convertir el array asociativo a array indexado y ordenar por fecha descendente
    $entregasOrdenadas = array_values($entregasAgrupadas);
    
    // Ordenar por fecha de entrega descendente
    usort($entregasOrdenadas, function($a, $b) {
        return strtotime($b['fecha_entrega']) - strtotime($a['fecha_entrega']);
    });

    return $entregasOrdenadas;
}

// Función para aplicar paginación a entregas ya agrupadas
function aplicarPaginacion($entregas, $pagina_actual, $registros_por_pagina) {
    $total_entregas = count($entregas);
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    $entregas_paginadas = array_slice($entregas, $offset, $registros_por_pagina);
    
    return [
        'entregas' => $entregas_paginadas,
        'total' => $total_entregas
    ];
}

// Preparar filtros
$filtros = [
    'dni' => $_GET['dni'] ?? '',
    'nombre' => $_GET['nombre'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

// Obtener TODAS las entregas agrupadas primero
$todas_las_entregas = [];
$total_entregas = 0;
$entregas = [];

if ($almacen_id_mostrar && $filtro_categoria_id) {
    // Obtener todas las entregas agrupadas (sin paginación en SQL)
    $todas_las_entregas = obtenerTodasLasEntregasAgrupadas($conn, $almacen_id_mostrar, $filtro_categoria_id, $filtros);
    
    // Aplicar paginación a las entregas ya agrupadas
    $resultado_paginacion = aplicarPaginacion($todas_las_entregas, $pagina_actual, $registros_por_pagina);
    $entregas = $resultado_paginacion['entregas'];
    $total_entregas = $resultado_paginacion['total'];
}

$total_paginas = $total_entregas > 0 ? ceil($total_entregas / $registros_por_pagina) : 0;

// Ajustar página actual si está fuera de rango
if ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
    $resultado_paginacion = aplicarPaginacion($todas_las_entregas, $pagina_actual, $registros_por_pagina);
    $entregas = $resultado_paginacion['entregas'];
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

// Función para generar URL de descarga con filtros actuales
function generarUrlDescarga($formato, $almacen_id, $categoria_id = null, $filtros = []) {
    $params = [
        'formato' => $formato,
        'almacen_id' => $almacen_id
    ];
    
    if ($categoria_id) {
        $params['categoria_id'] = $categoria_id;
    }
    
    foreach ($filtros as $key => $value) {
        if (!empty($value)) {
            $params[$key] = $value;
        }
    }
    
    return 'generar_reporte.php?' . http_build_query($params);
}

// Debug: Log para verificar datos (eliminar en producción)
if ($almacen_id_mostrar && $filtro_categoria_id) {
    error_log("Debug - Almacén: $almacen_id_mostrar, Categoría: $filtro_categoria_id, Total entregas agrupadas: $total_entregas, Página: $pagina_actual");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Entregas por Categoría - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Historial de entregas organizadas por categoría - Sistema GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico -->
    <link rel="stylesheet" href="../assets/css/historial-entregas.css">
</head>
<body class="historial-page">

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
        
        <!-- Historial Section -->
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
        
        <!-- Notifications Section -->
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
<main class="content" id="main-content" role="main">
    <div class="historial-header-section">
        <div class="historial-title-container">
            <h2 class="historial-title">
                <i class="fas fa-history"></i> 
                <?php if ($almacen_info && $categoria_info): ?>
                    Historial de Entregas - <?php echo htmlspecialchars($categoria_info['nombre']); ?>
                <?php elseif ($almacen_info): ?>
                    Historial de Entregas - <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                <?php elseif ($usuario_rol == 'admin' && !$filtro_almacen_id): ?>
                    Seleccionar Almacén para Ver Entregas
                <?php else: ?>
                    Historial de Entregas por Categoría
                <?php endif; ?>
            </h2>
        </div>
    </div>

    <!-- Navegación por niveles -->
    <?php if ($almacen_info && $categoria_info): ?>
        <!-- Viendo entregas de una categoría específica -->
        <a href="?almacen_id=<?php echo $almacen_info['id']; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Volver a Categorías de <?php echo htmlspecialchars($almacen_info['nombre']); ?>
        </a>

        <div class="categoria-breadcrumb">
            <h4>
                <i class="fas fa-tag"></i>
                <?php echo htmlspecialchars($categoria_info['nombre']); ?>
            </h4>
            <p>
                Entregas realizadas en <?php echo htmlspecialchars($almacen_info['nombre']); ?>
                <strong>(<?php echo number_format($total_entregas); ?> envíos encontrados)</strong>
            </p>
        </div>

        <!-- Sección de Descarga de Reportes -->
        <div class="download-section">
            <div class="download-header">
                <i class="fas fa-download"></i>
                <div>
                    <h3>Descargar Reporte</h3>
                    <p>
                        Generar reporte de entregas para la categoría "<?php echo htmlspecialchars($categoria_info['nombre']); ?>"
                    </p>
                </div>
            </div>

            <div class="download-buttons">
                <button class="download-btn download-btn-pdf" onclick="confirmarDescarga('pdf')">
                    <i class="fas fa-file-pdf"></i>
                    Descargar PDF
                </button>
            </div>

            <div class="download-info">
                <p><strong>Información:</strong> El reporte incluirá todas las entregas de esta categoría según los filtros aplicados. 
                Los filtros de fecha, nombre y DNI se conservarán en el reporte.</p>
            </div>
        </div>

        <!-- Filtros para la categoría específica -->
        <div class="filtros-categoria">
            <h3>
                <i class="fas fa-filter"></i>
                Filtros de Búsqueda
            </h3>
            <form method="GET" class="historial-filter-form" id="formulario-filtros">
                <input type="hidden" name="almacen_id" value="<?php echo $filtro_almacen_id; ?>">
                <input type="hidden" name="categoria_id" value="<?php echo $filtro_categoria_id; ?>">
                
                <div class="historial-filter-row">
                    <div class="historial-form-group">
                        <label for="filtro-nombre" class="historial-form-label">Filtrar por Nombre</label>
                        <input type="text" class="historial-form-control" id="filtro-nombre" name="nombre" 
                               placeholder="Nombre del destinatario" 
                               value="<?php echo htmlspecialchars($_GET['nombre'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-dni" class="historial-form-label">Filtrar por DNI</label>
                        <input type="text" class="historial-form-control" id="filtro-dni" name="dni" 
                               placeholder="Número de DNI" 
                               value="<?php echo htmlspecialchars($_GET['dni'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-fecha-inicio" class="historial-form-label">Fecha de Inicio</label>
                        <input type="date" class="historial-form-control" id="filtro-fecha-inicio" 
                               name="fecha_inicio"
                               value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                    </div>
                    <div class="historial-form-group">
                        <label for="filtro-fecha-fin" class="historial-form-label">Fecha de Fin</label>
                        <input type="date" class="historial-form-control" id="filtro-fecha-fin" 
                               name="fecha_fin"
                               value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                    </div>
                </div>
                <div class="historial-filter-actions">
                    <button type="submit" class="historial-btn historial-btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="?almacen_id=<?php echo $filtro_almacen_id; ?>&categoria_id=<?php echo $filtro_categoria_id; ?>" class="historial-btn historial-btn-secondary">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Información de paginación -->
        <?php if ($total_entregas > 0): ?>
        <div class="pagination-info">
            <p>
                Mostrando envíos <?php echo number_format((($pagina_actual - 1) * $registros_por_pagina) + 1); ?> - 
                <?php echo number_format(min($pagina_actual * $registros_por_pagina, $total_entregas)); ?> 
                de <?php echo number_format($total_entregas); ?> envíos totales
                <?php if ($total_paginas > 1): ?>
                    (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Tabla de entregas de la categoría -->
        <div class="historial-table-container">
            <div class="historial-table-responsive">
                <table class="historial-table" id="tabla-historial-entregas">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Fecha y Hora</th>
                            <th><i class="fas fa-user"></i> Destinatario</th>
                            <th><i class="fas fa-id-card"></i> DNI</th>
                            <th><i class="fas fa-boxes"></i> Productos Entregados</th>
                            <th><i class="fas fa-user-shield"></i> Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="5" class="historial-no-results">
                                    <i class="fas fa-inbox"></i>
                                    <?php if ($total_entregas == 0): ?>
                                        No hay entregas registradas para esta categoría
                                    <?php else: ?>
                                        No se encontraron entregas con los filtros aplicados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td class="historial-fecha-cell">
                                        <?php 
                                        $fecha = new DateTime($entrega['fecha_entrega']);
                                        echo $fecha->format('d/m/Y H:i'); 
                                        ?>
                                    </td>
                                    <td class="historial-destinatario-cell">
                                        <?php echo htmlspecialchars($entrega['nombre_destinatario']); ?>
                                    </td>
                                    <td>
                                        <span class="historial-dni-cell">
                                            <?php echo htmlspecialchars($entrega['dni_destinatario']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <ul class="historial-productos-lista">
                                            <?php foreach ($entrega['productos'] as $producto): ?>
                                                <li class="historial-producto-item">
                                                    <i class="fas fa-box"></i>
                                                    <?php echo htmlspecialchars($producto['nombre']); ?>
                                                    <span class="historial-producto-cantidad">
                                                        (<?php echo htmlspecialchars($producto['cantidad']); ?> unidad<?php echo ($producto['cantidad'] != 1) ? 'es' : ''; ?>)
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td class="historial-responsable-cell">
                                        <i class="fas fa-user-shield"></i>
                                        <?php echo htmlspecialchars($entrega['usuario_responsable'] ?? 'No registrado'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación mejorada -->
        <?php if ($total_paginas > 1): ?>
        <div class="historial-pagination">
            <?php
            $params = [];
            foreach ($_GET as $key => $value) {
                if ($key != 'pagina') {
                    $params[] = $key . '=' . urlencode($value);
                }
            }
            $url_params = !empty($params) ? '?' . implode('&', $params) . '&' : '?';
            
            // Calcular rango de páginas a mostrar
            $rango = 2;
            $inicio = max(1, $pagina_actual - $rango);
            $fin = min($total_paginas, $pagina_actual + $rango);
            
            // Primera página
            if ($inicio > 1): ?>
                <a href="<?php echo $url_params; ?>pagina=1" class="pagination-btn">
                    <i class="fas fa-angle-double-left"></i> Primera
                </a>
            <?php endif;
            
            // Página anterior
            if ($pagina_actual > 1): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual - 1; ?>" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            <?php endif;
            
            // Páginas numeradas
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <?php if ($i == $pagina_actual): ?>
                    <span class="pagination-btn pagination-active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $i; ?>" class="pagination-btn"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor;
            
            // Página siguiente
            if ($pagina_actual < $total_paginas): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual + 1; ?>" class="pagination-btn">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif;
            
            // Última página
            if ($fin < $total_paginas): ?>
                <a href="<?php echo $url_params; ?>pagina=<?php echo $total_paginas; ?>" class="pagination-btn">
                    Última <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
            
            <!-- Salto directo a página -->
            <div class="pagination-jump">
                <span>Ir a página:</span>
                <input type="number" id="jumpPage" min="1" max="<?php echo $total_paginas; ?>" 
                       value="<?php echo $pagina_actual; ?>" 
                       onkeypress="if(event.key==='Enter') saltarAPagina()">
                <button onclick="saltarAPagina()" class="pagination-btn">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($almacen_info): ?>
        <!-- Mostrando categorías del almacén -->
        <?php if ($usuario_rol == 'admin'): ?>
        <a href="historial.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Volver a Lista de Almacenes
        </a>
        <?php endif; ?>

        <div class="categoria-breadcrumb">
            <h4>
                <i class="fas fa-warehouse"></i>
                <?php echo htmlspecialchars($almacen_info['nombre']); ?>
            </h4>
            <p>Selecciona una categoría para ver las entregas realizadas</p>
        </div>

        <?php if (!empty($categorias_con_entregas)): ?>
            <div class="categorias-grid">
                <?php foreach ($categorias_con_entregas as $categoria): ?>
                    <div class="categoria-card categoria-<?php echo strtolower(str_replace(' ', '-', $categoria['nombre'])); ?>">
                        <div class="categoria-header">
                            <div class="categoria-icon">
                                <?php
                                // Iconos específicos por categoría
                                $iconos = [
                                    'uniforme' => 'fas fa-tshirt',
                                    'arma' => 'fas fa-crosshairs',
                                    'equipo' => 'fas fa-tools',
                                    'vehiculo' => 'fas fa-car',
                                    'comunicacion' => 'fas fa-radio',
                                    'ropa' => 'fas fa-tshirt',
                                    'accesorio' => 'fas fa-shield-alt',
                                    'kebra' => 'fas fa-vest',
                                    'walkie' => 'fas fa-radio',
                                    'default' => 'fas fa-box'
                                ];
                                
                                $icono = 'fas fa-box';
                                foreach ($iconos as $key => $value) {
                                    if (stripos($categoria['nombre'], $key) !== false) {
                                        $icono = $value;
                                        break;
                                    }
                                }
                                ?>
                                <i class="<?php echo $icono; ?>"></i>
                            </div>
                            <div class="categoria-info">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <p>Entregas registradas en esta categoría</p>
                            </div>
                        </div>

                        <div class="categoria-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_entregas']); ?></span>
                                <span class="stat-label">Entregas</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_productos_entregados']); ?></span>
                                <span class="stat-label">Productos</span>
                            </div>
                        </div>

                        <div class="categoria-actions">
                            <a href="?almacen_id=<?php echo $almacen_info['id']; ?>&categoria_id=<?php echo $categoria['id']; ?>" 
                               class="btn-categoria btn-ver-entregas">
                                <i class="fas fa-history"></i>
                                Ver Entregas
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="historial-no-results">
                <i class="fas fa-inbox"></i>
                <h3>No hay entregas registradas</h3>
                <p>Este almacén aún no tiene entregas registradas en ninguna categoría.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($usuario_rol == 'admin' && !$filtro_almacen_id): ?>
        <!-- Lista de almacenes para admin -->
        <div class="historial-almacenes-container">
            <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                    <div class="historial-almacen-card">
                        <h3><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                        <a href="?almacen_id=<?php echo $almacen['id']; ?>" class="historial-btn historial-btn-primary">
                            <i class="fas fa-eye"></i> Ver Entregas por Categoría
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay almacenes registrados.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Usuario no admin - mostrar directamente las categorías de su almacén -->
        <?php if (!empty($categorias_con_entregas)): ?>
            <div class="categorias-grid">
                <?php foreach ($categorias_con_entregas as $categoria): ?>
                    <div class="categoria-card categoria-<?php echo strtolower(str_replace(' ', '-', $categoria['nombre'])); ?>">
                        <div class="categoria-header">
                            <div class="categoria-icon">
                                <?php
                                $iconos = [
                                    'uniforme' => 'fas fa-tshirt',
                                    'arma' => 'fas fa-crosshairs',
                                    'equipo' => 'fas fa-tools',
                                    'vehiculo' => 'fas fa-car',
                                    'comunicacion' => 'fas fa-radio',
                                    'ropa' => 'fas fa-tshirt',
                                    'accesorio' => 'fas fa-shield-alt',
                                    'kebra' => 'fas fa-vest',
                                    'walkie' => 'fas fa-radio',
                                    'default' => 'fas fa-box'
                                ];
                                
                                $icono = 'fas fa-box';
                                foreach ($iconos as $key => $value) {
                                    if (stripos($categoria['nombre'], $key) !== false) {
                                        $icono = $value;
                                        break;
                                    }
                                }
                                ?>
                                <i class="<?php echo $icono; ?>"></i>
                            </div>
                            <div class="categoria-info">
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <p>Entregas registradas en esta categoría</p>
                            </div>
                        </div>

                        <div class="categoria-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_entregas']); ?></span>
                                <span class="stat-label">Entregas</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($categoria['total_productos_entregados']); ?></span>
                                <span class="stat-label">Productos</span>
                            </div>
                        </div>

                        <div class="categoria-actions">
                            <a href="?almacen_id=<?php echo $almacen_id_mostrar; ?>&categoria_id=<?php echo $categoria['id']; ?>" 
                               class="btn-categoria btn-ver-entregas">
                                <i class="fas fa-history"></i>
                                Ver Entregas
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="historial-no-results">
                <i class="fas fa-inbox"></i>
                <h3>No hay entregas registradas</h3>
                <p>Tu almacén aún no tiene entregas registradas en ninguna categoría.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Modal de Confirmación de Descarga -->
<div id="downloadModal" class="download-modal">
    <div class="download-modal-content">
        <h4><i class="fas fa-download"></i> Confirmar Descarga</h4>
        <p id="modalMessage">¿Desea descargar el reporte en formato <span id="formatoSeleccionado">PDF</span>?</p>
        <div class="download-modal-buttons">
            <button class="modal-btn modal-btn-confirm" onclick="procederDescarga()">
                <i class="fas fa-check"></i> Confirmar
            </button>
            <button class="modal-btn modal-btn-cancel" onclick="cerrarModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Indicador de Descarga -->
<div id="downloadIndicator" class="download-indicator">
    <i class="fas fa-download"></i>
    <span>Preparando descarga...</span>
</div>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript mejorado -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // Validación de fechas en formularios
    const form = document.querySelector('.historial-filter-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('filtro-fecha-inicio');
            const fechaFin = document.getElementById('filtro-fecha-fin');
            
            if (fechaInicio && fechaFin && fechaInicio.value && fechaFin.value) {
                if (new Date(fechaInicio.value) > new Date(fechaFin.value)) {
                    e.preventDefault();
                    mostrarNotificacion('La fecha de inicio no puede ser mayor que la fecha de fin', 'error');
                }
            }
        });
    }

    // Animaciones para las tarjetas de categoría
    const categoriaCards = document.querySelectorAll('.categoria-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    categoriaCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
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
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (mainContent) {
                mainContent.classList.remove('with-sidebar');
            }
            menuToggle.focus();
        }
        
        if (e.key === 'Escape') {
            cerrarModal();
        }
        
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });

    // Efecto de carga para la tabla
    const tabla = document.getElementById('tabla-historial-entregas');
    if (tabla) {
        tabla.style.opacity = '0';
        tabla.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            tabla.style.transition = 'all 0.6s ease';
            tabla.style.opacity = '1';
            tabla.style.transform = 'translateY(0)';
        }, 200);
    }

    // Animación de entrada para la sección de descarga
    const downloadSection = document.querySelector('.download-section');
    if (downloadSection) {
        downloadSection.style.opacity = '0';
        downloadSection.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            downloadSection.style.transition = 'all 0.8s ease';
            downloadSection.style.opacity = '1';
            downloadSection.style.transform = 'translateY(0)';
        }, 400);
    }
});

// Variables globales para el sistema de descarga
let formatoSeleccionado = '';
let urlDescarga = '';

// Función para saltar a página específica
function saltarAPagina() {
    const pageInput = document.getElementById('jumpPage');
    const pageNumber = parseInt(pageInput.value);
    const maxPages = parseInt(pageInput.max);
    
    if (pageNumber >= 1 && pageNumber <= maxPages) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('pagina', pageNumber);
        window.location.href = currentUrl.toString();
    } else {
        mostrarNotificacion(`Por favor ingrese un número entre 1 y ${maxPages}`, 'warning');
        pageInput.focus();
    }
}

// Función para confirmar descarga
function confirmarDescarga(formato) {
    formatoSeleccionado = formato;
    
    const params = new URLSearchParams(window.location.search);
    const almacenId = params.get('almacen_id');
    const categoriaId = params.get('categoria_id');
    
    if (!almacenId || !categoriaId) {
        mostrarNotificacion('Error: No se puede generar el reporte sin seleccionar una categoría', 'error');
        return;
    }
    
    const reportParams = new URLSearchParams({
        formato: formato,
        almacen_id: almacenId,
        categoria_id: categoriaId
    });
    
    const filtros = ['dni', 'nombre', 'fecha_inicio', 'fecha_fin'];
    filtros.forEach(filtro => {
        const valor = params.get(filtro);
        if (valor) {
            reportParams.append(filtro, valor);
        }
    });
    
    urlDescarga = 'generar_reporte.php?' + reportParams.toString();
    
    document.getElementById('formatoSeleccionado').textContent = formato.toUpperCase();
    document.getElementById('downloadModal').style.display = 'block';
    
    setTimeout(() => {
        document.querySelector('.download-modal-content').style.transform = 'scale(1)';
    }, 10);
}

// Función para proceder con la descarga
function procederDescarga() {
    cerrarModal();
    
    const indicator = document.getElementById('downloadIndicator');
    indicator.style.display = 'block';
    
    const link = document.createElement('a');
    link.href = urlDescarga;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        indicator.style.display = 'none';
        mostrarNotificacion('¡Descarga iniciada correctamente!', 'success');
    }, 2000);
}

// Función para cerrar modal
function cerrarModal() {
    const modal = document.getElementById('downloadModal');
    const modalContent = document.querySelector('.download-modal-content');
    
    modalContent.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        modalContent.style.transform = 'scale(1)';
    }, 200);
}

// Cerrar modal al hacer clic fuera
document.getElementById('downloadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        window.location.href = '../logout.php';
    }
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `historial-notificacion historial-${tipo}`;
    
    let color = '#0a253c';
    let icono = 'fas fa-info-circle';
    
    switch(tipo) {
        case 'success':
            color = '#28a745';
            icono = 'fas fa-check-circle';
            break;
        case 'error':
            color = '#dc3545';
            icono = 'fas fa-exclamation-circle';
            break;
        case 'warning':
            color = '#ffc107';
            icono = 'fas fa-exclamation-triangle';
            break;
    }
    
    notificacion.innerHTML = `
        <i class="${icono}"></i>
        <span>${mensaje}</span>
    `;
    
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${color};
        color: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        z-index: 10001;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        max-width: 400px;
    `;
    
    container.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.style.opacity = '1';
        notificacion.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (container.contains(notificacion)) {
                container.removeChild(notificacion);
            }
        }, 300);
    }, duracion);
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});
</script>
</body>
</html>