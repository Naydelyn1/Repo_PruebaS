<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

// Require database connection
require_once "../config/database.php";

// ===== CONFIGURACIÓN DE PAGINACIÓN CORREGIDA =====
// CORREGIDO: Obtener registros por página de la URL, no hardcodeado
$registros_por_pagina = isset($_GET['registros_por_pagina']) ? max(5, intval($_GET['registros_por_pagina'])) : 15;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ===== CONTAR SOLICITUDES PENDIENTES PARA EL BADGE =====
$total_pendientes = 0;
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";

if ($usuario_rol != 'admin') {
    $sql_pendientes .= " AND almacen_destino = ?";
    $stmt_pendientes = $conn->prepare($sql_pendientes);
    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
    $stmt_pendientes->execute();
    $result_pendientes = $stmt_pendientes->get_result();
    $stmt_pendientes->close();
} else {
    $result_pendientes = $conn->query($sql_pendientes);
}

if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}

// ===== OBTENER DATOS DE MOVIMIENTOS CON PAGINACIÓN =====
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_almacen = isset($_GET['almacen']) ? $_GET['almacen'] : '';
$filtro_tipo = isset($_GET['tipo_movimiento']) ? $_GET['tipo_movimiento'] : '';

// Construir parámetros dinámicamente
$param_fecha_inicio = $filtro_fecha_inicio . ' 00:00:00';
$param_fecha_fin = $filtro_fecha_fin . ' 23:59:59';

// Query base para contar total de registros
$sql_count = "
    SELECT COUNT(*) as total
    FROM movimientos m
    LEFT JOIN productos p ON m.producto_id = p.id
    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.fecha BETWEEN ? AND ?
";

// Query para movimientos con paginación
$sql_movimientos = "
    SELECT 
        m.id,
        m.fecha,
        m.cantidad,
        m.estado,
        m.tipo as tipo_movimiento,
        p.nombre as producto_nombre,
        CONCAT('PROD-', LPAD(p.id, 4, '0')) as producto_codigo,
        ao.nombre as almacen_origen,
        ad.nombre as almacen_destino,
        u.nombre as usuario_nombre
    FROM movimientos m
    LEFT JOIN productos p ON m.producto_id = p.id
    LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
    LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.fecha BETWEEN ? AND ?
";

// Construir condiciones WHERE adicionales
$where_conditions = "";
$params_count = [];
$params_data = [];
$types_count = "ss";
$types_data = "ss";

// Agregar parámetros base
$params_count[] = $param_fecha_inicio;
$params_count[] = $param_fecha_fin;
$params_data[] = $param_fecha_inicio;
$params_data[] = $param_fecha_fin;

// Filtros específicos según rol y parámetros
if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
    $where_conditions .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    $params_count[] = $filtro_almacen;
    $params_count[] = $filtro_almacen;
    $params_data[] = $filtro_almacen;
    $params_data[] = $filtro_almacen;
    $types_count .= "ii";
    $types_data .= "ii";
} elseif ($usuario_rol != 'admin') {
    $where_conditions .= " AND (m.almacen_origen = ? OR m.almacen_destino = ?)";
    $params_count[] = $usuario_almacen_id;
    $params_count[] = $usuario_almacen_id;
    $params_data[] = $usuario_almacen_id;
    $params_data[] = $usuario_almacen_id;
    $types_count .= "ii";
    $types_data .= "ii";
}

if (!empty($filtro_tipo)) {
    $where_conditions .= " AND m.tipo = ?";
    $params_count[] = $filtro_tipo;
    $params_data[] = $filtro_tipo;
    $types_count .= "s";
    $types_data .= "s";
}

// Aplicar condiciones a ambas queries
$sql_count .= $where_conditions;
$sql_movimientos .= $where_conditions;

// Ejecutar query de conteo
$stmt_count = $conn->prepare($sql_count);
if (!empty($params_count)) {
    $stmt_count->bind_param($types_count, ...$params_count);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Agregar ORDER BY y LIMIT a la query de datos
$sql_movimientos .= " ORDER BY m.fecha DESC LIMIT ? OFFSET ?";
$params_data[] = $registros_por_pagina;
$params_data[] = $offset;
$types_data .= "ii";

// Ejecutar query de datos
$stmt_movimientos = $conn->prepare($sql_movimientos);
if (!empty($params_data)) {
    $stmt_movimientos->bind_param($types_data, ...$params_data);
}
$stmt_movimientos->execute();
$result_movimientos = $stmt_movimientos->get_result();

// Estadísticas (para las tarjetas de arriba - estas sí deben ser del total, no de la página)
$sql_stats = "
    SELECT 
        COUNT(*) as total_movimientos,
        SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM movimientos 
    WHERE fecha BETWEEN ? AND ?
";

// CORREGIDO: Aplicar los mismos filtros a las estadísticas
$stats_where_conditions = "";
$params_stats = [$param_fecha_inicio, $param_fecha_fin];
$types_stats = "ss";

if (!empty($filtro_almacen) && $usuario_rol == 'admin') {
    $stats_where_conditions .= " AND (almacen_origen = ? OR almacen_destino = ?)";
    $params_stats[] = $filtro_almacen;
    $params_stats[] = $filtro_almacen;
    $types_stats .= "ii";
} elseif ($usuario_rol != 'admin') {
    $stats_where_conditions .= " AND (almacen_origen = ? OR almacen_destino = ?)";
    $params_stats[] = $usuario_almacen_id;
    $params_stats[] = $usuario_almacen_id;
    $types_stats .= "ii";
}

if (!empty($filtro_tipo)) {
    $stats_where_conditions .= " AND tipo = ?";
    $params_stats[] = $filtro_tipo;
    $types_stats .= "s";
}

$sql_stats .= $stats_where_conditions;
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param($types_stats, ...$params_stats);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Obtener lista de almacenes para el filtro
$almacenes = [];
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
    $result_almacenes = $conn->query($sql_almacenes);
    if ($result_almacenes) {
        while ($row = $result_almacenes->fetch_assoc()) {
            $almacenes[] = $row;
        }
    }
}

// Función para construir URL con parámetros
function construirURL($pagina) {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Movimientos - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Reportes de movimientos del sistema de gestión de inventario">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para reportes de movimientos -->
    <link rel="stylesheet" href="../assets/css/reportes/reportes-movimientos.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    
    <style>
    /* Estilos para paginación */
    .pagination-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        border: 1px solid #e0e7ff;
    }

    .pagination-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-stats {
        color: #6b7280;
        font-size: 14px;
    }

    .pagination-stats strong {
        color: #1f2937;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pagination-controls a,
    .pagination-controls span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        min-width: 36px;
        transition: all 0.2s ease;
    }

    .pagination-controls a:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        transform: translateY(-1px);
    }

    .pagination-controls .current {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        font-weight: 600;
    }

    .pagination-controls .disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .pagination-controls .prev,
    .pagination-controls .next {
        font-weight: 500;
        padding: 8px 16px;
    }

    .pagination-controls .prev:hover,
    .pagination-controls .next:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .records-per-page {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
    }

    .records-per-page select {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: white;
        color: #374151;
        font-size: 14px;
        cursor: pointer;
    }

    .records-per-page select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Responsive para paginación */
    @media (max-width: 768px) {
        .pagination-info {
            flex-direction: column;
            align-items: flex-start;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }

        .records-per-page {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }
    }

    /* Animación de carga */
    .table-loading {
        display: none;
        text-align: center;
        padding: 40px;
        color: #6b7280;
    }

    .table-loading.active {
        display: block;
    }

    .movimientos-table.loading {
        opacity: 0.5;
        pointer-events: none;
    }

    /* Estilos para botones de exportación */
    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-export, .btn-export-all {
        background: #007bff;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,123,255,0.2);
    }

    .btn-export:hover, .btn-export-all:hover {
        background: #0056b3;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,123,255,0.3);
    }

    .btn-export-all {
        background: #28a745;
        box-shadow: 0 2px 4px rgba(40,167,69,0.2);
    }

    .btn-export-all:hover {
        background: #1e7e34;
        box-shadow: 0 4px 8px rgba(40,167,69,0.3);
    }

    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            gap: 15px;
            
        }
        
        .header-actions {
            width: 100%;
            justify-content: center;
        }
        
        .btn-export, .btn-export-all {
            flex: 1;
            justify-content: center;
            min-width: 120px;
        }
    }
    </style>
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

        <!-- Warehouses Section -->
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
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
                <li><a href="../notificaciones/historial.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Historial de Solicitudes</a></li>
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
                    <a href="../notificaciones/pendientes.php" role="menuitem">
                        <i class="fas fa-clock"></i> Solicitudes Pendientes
                        <?php if ($total_pendientes > 0): ?>
                        <span class="badge-small"><?php echo $total_pendientes; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports Section -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container active">
            <a href="#" aria-label="Menú Reportes" aria-expanded="true" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu activo" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li class="active"><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
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
    <!-- Header con botones de exportación modificados -->
    <header class="movimientos-header">
        <div class="header-content">
            <div class="header-info">
                <h1><i class="fas fa-exchange-alt"></i> Reportes de Movimientos</h1>
                <p>Análisis detallado de transferencias y movimientos de inventario</p>
            </div>
            <div class="header-actions">
                <!-- Botón principal: Exportar página actual -->
                <button class="btn-export" onclick="exportarMovimientosPDF()" title="Exportar solo los registros de esta página">
                    <i class="fas fa-file-pdf"></i> Exportar Página Actual
                </button>
                
                <!-- Botón secundario: Exportar todo -->
                <button class="btn-export-all" onclick="exportarTodosMovimientosPDF()" title="Exportar TODOS los registros que cumplen con los filtros">
                    <i class="fas fa-file-export"></i> Exportar TODO
                </button>
            </div>
        </div>
    </header>

    <!-- Estadísticas Generales -->
    <section class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_movimientos']); ?></div>
            <div class="stat-label">Total Movimientos</div>
        </div>
        
        <div class="stat-card completados">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['completados']); ?></div>
            <div class="stat-label">Completados</div>
        </div>
        
        <div class="stat-card pendientes">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo number_format($stats['pendientes']); ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        
        <div class="stat-card rechazados">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['rechazados']); ?></div>
            <div class="stat-label">Rechazados</div>
        </div>
    </section>

    <!-- Filtros -->
    <section class="filters-section">
        <div class="filters-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        
        <form class="filters-form" method="GET" id="filtrosForm">
            <input type="hidden" name="pagina" value="1">
            <input type="hidden" name="registros_por_pagina" value="<?php echo $registros_por_pagina; ?>">
            
            <div class="filter-group">
                <label class="filter-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>" class="filter-control">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>" class="filter-control">
            </div>
            
            <?php if ($usuario_rol == 'admin'): ?>
            <div class="filter-group">
                <label class="filter-label">Almacén</label>
                <select name="almacen" class="filter-control">
                    <option value="">Todos los almacenes</option>
                    <?php foreach ($almacenes as $almacen): ?>
                    <option value="<?php echo $almacen['id']; ?>" <?php echo ($filtro_almacen == $almacen['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($almacen['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label class="filter-label">Tipo</label>
                <select name="tipo_movimiento" class="filter-control">
                    <option value="">Todos los tipos</option>
                    <option value="entrada" <?php echo ($filtro_tipo == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                    <option value="salida" <?php echo ($filtro_tipo == 'salida') ? 'selected' : ''; ?>>Salida</option>
                    <option value="transferencia" <?php echo ($filtro_tipo == 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                    <option value="ajuste" <?php echo ($filtro_tipo == 'ajuste') ? 'selected' : ''; ?>>Ajuste</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </form>
    </section>

    <!-- Información de Paginación -->
    <?php if ($total_registros > 0): ?>
    <section class="pagination-section">
        <div class="pagination-info">
            <div class="pagination-stats">
                <strong>
                    <?php 
                    $inicio = $offset + 1;
                    $fin = min($offset + $registros_por_pagina, $total_registros);
                    echo number_format($inicio) . '-' . number_format($fin) . ' de ' . number_format($total_registros) . ' movimientos';
                    ?>
                </strong>
                <small style="display: block; color: #6b7280; margin-top: 4px;">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> 
                    • Mostrando <?php echo $registros_por_pagina; ?> registros por página
                    • El botón "Exportar Página Actual" descargará solo estos <?php echo ($fin - $inicio + 1); ?> registros
                </small>
            </div>
            
            <div class="pagination-controls">
                <?php if ($pagina_actual > 1): ?>
                    <a href="<?php echo construirURL(1); ?>" class="prev">
                        <i class="fas fa-angle-double-left"></i> Primera
                    </a>
                    <a href="<?php echo construirURL($pagina_actual - 1); ?>" class="prev">
                        <i class="fas fa-angle-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="prev disabled">
                        <i class="fas fa-angle-double-left"></i> Primera
                    </span>
                    <span class="prev disabled">
                        <i class="fas fa-angle-left"></i> Anterior
                    </span>
                <?php endif; ?>

                <?php
                // Lógica para mostrar números de página
                $rango = 2;
                $inicio_rango = max(1, $pagina_actual - $rango);
                $fin_rango = min($total_paginas, $pagina_actual + $rango);

                if ($inicio_rango > 1) {
                    echo '<a href="' . construirURL(1) . '">1</a>';
                    if ($inicio_rango > 2) {
                        echo '<span>...</span>';
                    }
                }

                for ($i = $inicio_rango; $i <= $fin_rango; $i++) {
                    if ($i == $pagina_actual) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        echo '<a href="' . construirURL($i) . '">' . $i . '</a>';
                    }
                }

                if ($fin_rango < $total_paginas) {
                    if ($fin_rango < $total_paginas - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="' . construirURL($total_paginas) . '">' . $total_paginas . '</a>';
                }
                ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo construirURL($pagina_actual + 1); ?>" class="next">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="<?php echo construirURL($total_paginas); ?>" class="next">
                        Última <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="next disabled">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </span>
                    <span class="next disabled">
                        Última <i class="fas fa-angle-double-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="records-per-page">
                <label for="registrosPorPagina">Mostrar:</label>
                <select id="registrosPorPagina" onchange="cambiarRegistrosPorPagina(this.value)">
                    <option value="5" <?php echo ($registros_por_pagina == 5) ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo ($registros_por_pagina == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="15" <?php echo ($registros_por_pagina == 15) ? 'selected' : ''; ?>>15</option>
                    <option value="25" <?php echo ($registros_por_pagina == 25) ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo ($registros_por_pagina == 50) ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($registros_por_pagina == 100) ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tabla de Movimientos -->
    <section class="movimientos-table-section">
        <div class="table-header">
            <h3><i class="fas fa-table"></i> Detalle de Movimientos</h3>
        </div>
        
        <div class="table-loading" id="tableLoading">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Cargando movimientos...</p>
        </div>
        
        <div class="table-responsive">
            <table class="movimientos-table" id="movimientosTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-calendar"></i> Fecha</th>
                        <th><i class="fas fa-box"></i> Producto</th>
                        <th><i class="fas fa-sort-numeric-up"></i> Cantidad</th>
                        <th><i class="fas fa-warehouse"></i> Origen</th>
                        <th><i class="fas fa-warehouse"></i> Destino</th>
                        <th><i class="fas fa-user"></i> Usuario</th>
                        <th><i class="fas fa-info-circle"></i> Estado</th>
                        <th><i class="fas fa-tag"></i> Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_movimientos->num_rows > 0): ?>
                        <?php while ($movimiento = $result_movimientos->fetch_assoc()): ?>
                        <tr>
                            <td class="mov-id">#<?php echo str_pad($movimiento['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td class="mov-fecha"><?php echo date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?></td>
                            <td class="mov-producto">
                                <div class="producto-info">
                                    <strong><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></strong>
                                    <small><?php echo htmlspecialchars($movimiento['producto_codigo']); ?></small>
                                </div>
                            </td>
                            <td class="mov-cantidad"><?php echo number_format($movimiento['cantidad']); ?></td>
                            <td class="mov-almacen"><?php echo htmlspecialchars($movimiento['almacen_origen'] ?? 'Sistema'); ?></td>
                            <td class="mov-almacen"><?php echo htmlspecialchars($movimiento['almacen_destino'] ?? 'Sistema'); ?></td>
                            <td class="mov-usuario"><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></td>
                            <td class="mov-estado">
                                <?php
                                $estado_class = '';
                                switch($movimiento['estado']) {
                                    case 'completado':
                                        $estado_class = 'estado-completado';
                                        break;
                                    case 'pendiente':
                                        $estado_class = 'estado-pendiente';
                                        break;
                                    case 'rechazado':
                                        $estado_class = 'estado-rechazado';
                                        break;
                                }
                                ?>
                                <span class="estado-badge <?php echo $estado_class; ?>">
                                    <?php echo ucfirst($movimiento['estado']); ?>
                                </span>
                            </td>
                            <td class="mov-tipo"><?php echo ucfirst($movimiento['tipo_movimiento']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-results">
                                <i class="fas fa-search"></i>
                                <p>No se encontraron movimientos con los filtros aplicados</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Paginación inferior -->
    <?php if ($total_registros > 0 && $total_paginas > 1): ?>
    <section class="pagination-section">
        <div class="pagination-info">
            <div class="pagination-stats">
                Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
            </div>
            
            <div class="pagination-controls">
                <?php if ($pagina_actual > 1): ?>
                    <a href="<?php echo construirURL($pagina_actual - 1); ?>" class="prev">
                        <i class="fas fa-angle-left"></i> Anterior
                    </a>
                <?php endif; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo construirURL($pagina_actual + 1); ?>" class="next">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/universal-confirmation-system.js"></script>
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
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherLink = otherContainer.querySelector('a');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherLink) {
                                otherLink.setAttribute('aria-expanded', 'false');
                            }
                        }
                    }
                });
                
                submenu.classList.toggle('activo');
                const isExpanded = submenu.classList.contains('activo');
                link.setAttribute('aria-expanded', isExpanded.toString());
            });
        }
    });

    // Animación de entrada para las filas de la tabla
    const tableRows = document.querySelectorAll('.movimientos-table tbody tr');
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
});

// CORREGIDO: Función para cambiar registros por página
function cambiarRegistrosPorPagina(nuevaCantidad) {
    const url = new URL(window.location);
    url.searchParams.set('registros_por_pagina', nuevaCantidad);
    url.searchParams.set('pagina', '1'); // Resetear a la primera página
    
    // Mostrar indicador de carga
    document.getElementById('tableLoading').classList.add('active');
    document.getElementById('movimientosTable').classList.add('loading');
    
    // Redirigir después de un breve delay para mostrar la animación
    setTimeout(() => {
        window.location.href = url.toString();
    }, 300);
}

// Función para exportar movimientos a PDF - PÁGINA ACTUAL
function exportarMovimientosPDF() {
    mostrarNotificacion('Generando reporte PDF de la página actual...', 'info');
    
    // Obtener filtros actuales de la página
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]')?.value || '';
    const fechaFin = document.querySelector('input[name="fecha_fin"]')?.value || '';
    const almacen = document.querySelector('select[name="almacen"]')?.value || '';
    const tipoMovimiento = document.querySelector('select[name="tipo_movimiento"]')?.value || '';
    
    // Obtener datos de paginación actual
    const paginaActual = new URLSearchParams(window.location.search).get('pagina') || '1';
    const registrosPorPagina = new URLSearchParams(window.location.search).get('registros_por_pagina') || '15';
    
    let url = '../reportes/exportar_pdf.php?tipo=movimientos';
    if (fechaInicio) url += '&fecha_inicio=' + fechaInicio;
    if (fechaFin) url += '&fecha_fin=' + fechaFin;
    if (almacen) url += '&almacen=' + almacen;
    if (tipoMovimiento) url += '&tipo_movimiento=' + tipoMovimiento;
    
    // Agregar parámetros de paginación
    url += '&pagina_actual=' + paginaActual;
    url += '&registros_por_pagina=' + registrosPorPagina;
    url += '&solo_pagina_actual=1'; // Indicador para exportar solo página actual
    url += '&auto_print=1';
    
    window.open(url, '_blank');
}

// CORREGIDO: Función para exportar TODOS los datos (sin límites)
function exportarTodosMovimientosPDF() {
    mostrarNotificacion('Generando reporte PDF de TODOS los registros...', 'info');
    
    // Obtener filtros actuales de la página
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]')?.value || '';
    const fechaFin = document.querySelector('input[name="fecha_fin"]')?.value || '';
    const almacen = document.querySelector('select[name="almacen"]')?.value || '';
    const tipoMovimiento = document.querySelector('select[name="tipo_movimiento"]')?.value || '';
    
    let url = '../reportes/exportar_pdf.php?tipo=movimientos';
    if (fechaInicio) url += '&fecha_inicio=' + fechaInicio;
    if (fechaFin) url += '&fecha_fin=' + fechaFin;
    if (almacen) url += '&almacen=' + almacen;
    if (tipoMovimiento) url += '&tipo_movimiento=' + tipoMovimiento;
    url += '&exportar_todo=1'; // NUEVO: Indicador para exportar TODO sin límites
    url += '&auto_print=1';
    
    window.open(url, '_blank');
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    // Crear contenedor si no existe
    let container = document.getElementById('notificaciones-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificaciones-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    // Crear notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.style.cssText = `
        background: ${tipo === 'success' ? '#d4edda' : tipo === 'error' ? '#f8d7da' : '#d1ecf1'};
        color: ${tipo === 'success' ? '#155724' : tipo === 'error' ? '#721c24' : '#0c5460'};
        border: 1px solid ${tipo === 'success' ? '#c3e6cb' : tipo === 'error' ? '#f5c6cb' : '#bee5eb'};
        border-radius: 5px;
        padding: 12px 16px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    notificacion.textContent = mensaje;
    
    container.appendChild(notificacion);
    
    // Animar entrada
    setTimeout(() => {
        notificacion.style.opacity = '1';
        notificacion.style.transform = 'translateX(0)';
    }, 100);
    
    // Remover después del tiempo especificado
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

// Función para cerrar sesión
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    const confirmado = await confirmarCerrarSesion();
    
    if (confirmado) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// Navegación con teclado para accesibilidad
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case 'ArrowLeft':
                e.preventDefault();
                const prevBtn = document.querySelector('.pagination-controls .prev:not(.disabled)');
                if (prevBtn) prevBtn.click();
                break;
            case 'ArrowRight':
                e.preventDefault();
                const nextBtn = document.querySelector('.pagination-controls .next:not(.disabled)');
                if (nextBtn) nextBtn.click();
                break;
        }
    }
});
</script>
</body>
</html>