<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Solo administradores pueden acceder a este reporte
if ($_SESSION["user_role"] != 'admin') {
    header("Location: ../dashboard.php");
    exit();
}

// Prevent session hijacking
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

// Require database connection
require_once "../config/database.php";

// ===== CONFIGURACIÓN DE PAGINACIÓN =====
$registros_por_pagina = isset($_GET['registros_por_pagina']) ? max(10, intval($_GET['registros_por_pagina'])) : 15;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ===== CONTAR SOLICITUDES PENDIENTES PARA EL BADGE =====
$total_pendientes = 0;
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
$result_pendientes = $conn->query($sql_pendientes);

if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}

// ===== OBTENER DATOS DE ACTIVIDAD DE USUARIOS CON PAGINACIÓN =====
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';

// Parámetros de fecha
$param_fecha_inicio = $filtro_fecha_inicio . ' 00:00:00';
$param_fecha_fin = $filtro_fecha_fin . ' 23:59:59';

// Query para contar total de usuarios activos
$sql_count_usuarios = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'activo'";
if (!empty($filtro_usuario)) {
    $sql_count_usuarios .= " AND id = ?";
}

$stmt_count = $conn->prepare($sql_count_usuarios);
if (!empty($filtro_usuario)) {
    $stmt_count->bind_param("i", $filtro_usuario);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_usuarios = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_usuarios / $registros_por_pagina);

// Query para actividad de usuarios con paginación
$sql_actividad = "
    SELECT 
        u.id as usuario_id,
        u.nombre as usuario_nombre,
        u.correo as usuario_email,
        u.rol,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as total_actividades,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.estado = 'completado'
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.estado = 'aprobada'
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as completadas,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.estado = 'pendiente'
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.estado = 'pendiente'
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as pendientes,
        (
            SELECT COUNT(*) 
            FROM movimientos m 
            WHERE m.usuario_id = u.id 
            AND m.estado = 'rechazado'
            AND m.fecha BETWEEN ? AND ?
        ) +
        (
            SELECT COUNT(*) 
            FROM solicitudes_transferencia s 
            WHERE s.usuario_id = u.id 
            AND s.estado = 'rechazada'
            AND s.fecha_solicitud BETWEEN ? AND ?
        ) as rechazadas,
        GREATEST(
            COALESCE((SELECT MAX(m.fecha) FROM movimientos m WHERE m.usuario_id = u.id), '1970-01-01'),
            COALESCE((SELECT MAX(s.fecha_solicitud) FROM solicitudes_transferencia s WHERE s.usuario_id = u.id), '1970-01-01')
        ) as ultima_actividad,
        a.nombre as almacen_nombre
    FROM usuarios u
    LEFT JOIN almacenes a ON u.almacen_id = a.id
    WHERE u.estado = 'activo'
";

// Construir parámetros
$params = [
    $param_fecha_inicio, $param_fecha_fin,  // movimientos count
    $param_fecha_inicio, $param_fecha_fin,  // solicitudes count
    $param_fecha_inicio, $param_fecha_fin,  // movimientos completados
    $param_fecha_inicio, $param_fecha_fin,  // solicitudes aprobadas
    $param_fecha_inicio, $param_fecha_fin,  // movimientos pendientes
    $param_fecha_inicio, $param_fecha_fin,  // solicitudes pendientes
    $param_fecha_inicio, $param_fecha_fin,  // movimientos rechazados
    $param_fecha_inicio, $param_fecha_fin   // solicitudes rechazadas
];
$types = "ssssssssssssssss";

if (!empty($filtro_usuario)) {
    $sql_actividad .= " AND u.id = ?";
    $params[] = $filtro_usuario;
    $types .= "i";
}

$sql_actividad .= " ORDER BY total_actividades DESC LIMIT ? OFFSET ?";
$params[] = $registros_por_pagina;
$params[] = $offset;
$types .= "ii";

$stmt_actividad = $conn->prepare($sql_actividad);
$stmt_actividad->bind_param($types, ...$params);
$stmt_actividad->execute();
$result_actividad = $stmt_actividad->get_result();

// Estadísticas generales
$sql_stats = "
    SELECT 
        COUNT(DISTINCT u.id) as usuarios_activos,
        (
            SELECT COUNT(*) FROM movimientos m 
            WHERE m.fecha BETWEEN ? AND ?
        ) + 
        (
            SELECT COUNT(*) FROM solicitudes_transferencia s 
            WHERE s.fecha_solicitud BETWEEN ? AND ?
        ) as total_actividades,
        (
            (
                SELECT COUNT(*) FROM movimientos m 
                WHERE m.fecha BETWEEN ? AND ?
            ) + 
            (
                SELECT COUNT(*) FROM solicitudes_transferencia s 
                WHERE s.fecha_solicitud BETWEEN ? AND ?
            )
        ) / COUNT(DISTINCT u.id) as promedio_por_usuario,
        (
            SELECT MAX(user_activity.total) FROM (
                SELECT 
                    (
                        (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u2.id AND m.fecha BETWEEN ? AND ?) +
                        (SELECT COUNT(*) FROM solicitudes_transferencia s WHERE s.usuario_id = u2.id AND s.fecha_solicitud BETWEEN ? AND ?)
                    ) as total
                FROM usuarios u2 
                WHERE u2.estado = 'activo'
            ) user_activity
        ) as max_actividad
    FROM usuarios u
    WHERE u.estado = 'activo'
";

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("ssssssssssss", 
    $param_fecha_inicio, $param_fecha_fin,
    $param_fecha_inicio, $param_fecha_fin,
    $param_fecha_inicio, $param_fecha_fin,
    $param_fecha_inicio, $param_fecha_fin,
    $param_fecha_inicio, $param_fecha_fin,
    $param_fecha_inicio, $param_fecha_fin
);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

// Obtener lista de usuarios para el filtro
$sql_usuarios = "SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre";
$result_usuarios = $conn->query($sql_usuarios);
$usuarios = [];
if ($result_usuarios) {
    while ($row = $result_usuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// Actividad reciente detallada con paginación limitada
$actividad_por_pagina = 20;
$pagina_actividad = isset($_GET['pagina_actividad']) ? max(1, intval($_GET['pagina_actividad'])) : 1;
$offset_actividad = ($pagina_actividad - 1) * $actividad_por_pagina;

$sql_reciente = "
    (
        SELECT 
            m.id,
            m.fecha as fecha_actividad,
            m.cantidad,
            m.estado,
            m.tipo as tipo_actividad,
            u.nombre as usuario_nombre,
            p.nombre as producto_nombre,
            ao.nombre as almacen_origen,
            ad.nombre as almacen_destino,
            'movimiento' as tipo_registro
        FROM movimientos m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN productos p ON m.producto_id = p.id
        LEFT JOIN almacenes ao ON m.almacen_origen = ao.id
        LEFT JOIN almacenes ad ON m.almacen_destino = ad.id
        WHERE m.fecha BETWEEN ? AND ?
    )
    UNION ALL
    (
        SELECT 
            s.id,
            s.fecha_solicitud as fecha_actividad,
            s.cantidad,
            s.estado,
            'transferencia' as tipo_actividad,
            u.nombre as usuario_nombre,
            p.nombre as producto_nombre,
            ao.nombre as almacen_origen,
            ad.nombre as almacen_destino,
            'solicitud' as tipo_registro
        FROM solicitudes_transferencia s
        JOIN usuarios u ON s.usuario_id = u.id
        LEFT JOIN productos p ON s.producto_id = p.id
        LEFT JOIN almacenes ao ON s.almacen_origen = ao.id
        LEFT JOIN almacenes ad ON s.almacen_destino = ad.id
        WHERE s.fecha_solicitud BETWEEN ? AND ?
    )
    ORDER BY fecha_actividad DESC
    LIMIT ? OFFSET ?
";

$stmt_reciente = $conn->prepare($sql_reciente);
$stmt_reciente->bind_param("ssssii", $param_fecha_inicio, $param_fecha_fin, $param_fecha_inicio, $param_fecha_fin, $actividad_por_pagina, $offset_actividad);
$stmt_reciente->execute();
$result_reciente = $stmt_reciente->get_result();

// Función para construir URL con parámetros
function construirURL($pagina, $tipo_pagina = 'pagina') {
    $params = $_GET;
    $params[$tipo_pagina] = $pagina;
    return '?' . http_build_query($params);
}

// Función para formatear fechas
function formatearFecha($fecha) {
    if (!$fecha || $fecha == '1970-01-01 00:00:00') {
        return 'Sin actividad';
    }
    return date('d/m/Y H:i', strtotime($fecha));
}

// Función para obtener el icono según el tipo de actividad
function obtenerIconoActividad($estado) {
    $estado_normalizado = $estado;
    
    // Normalizar estados diferentes entre tablas
    if ($estado_normalizado == 'aprobada') $estado_normalizado = 'completado';
    if ($estado_normalizado == 'rechazada') $estado_normalizado = 'rechazado';
    
    switch($estado_normalizado) {
        case 'completado':
            return 'fas fa-check-circle text-success';
        case 'pendiente':
            return 'fas fa-clock text-warning';
        case 'rechazado':
            return 'fas fa-times-circle text-danger';
        default:
            return 'fas fa-circle text-secondary';
    }
}

// Función para normalizar estados
function normalizarEstado($estado) {
    if ($estado == 'aprobada') return 'completado';
    if ($estado == 'rechazada') return 'rechazado';
    return $estado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividad de Usuarios - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Reportes de actividad de usuarios del sistema">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para reportes de usuarios -->
    <link rel="stylesheet" href="../assets/css/reportes/reportes-usuarios.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
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

        <!-- Users Section -->
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

        <!-- Warehouses Section -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes" aria-expanded="false" role="button" tabindex="0">
                <span><i class="fas fa-warehouse"></i> Almacenes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu" role="menu">
                <li><a href="../almacenes/registrar.php" role="menuitem"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
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
        <li class="submenu-container active">
            <a href="#" aria-label="Menú Reportes" aria-expanded="true" role="button" tabindex="0">
                <span><i class="fas fa-chart-bar"></i> Reportes</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu activo" role="menu">
                <li><a href="../reportes/inventario.php" role="menuitem"><i class="fas fa-warehouse"></i> Inventario General</a></li>
                <li><a href="../reportes/movimientos.php" role="menuitem"><i class="fas fa-exchange-alt"></i> Movimientos</a></li>
                <li class="active"><a href="../reportes/usuarios.php" role="menuitem"><i class="fas fa-users"></i> Actividad de Usuarios</a></li>
            </ul>
        </li>

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
    <!-- Header -->
    <header class="usuarios-header">
        <div class="header-content">
            <div class="header-info">
                <h1><i class="fas fa-users-cog"></i> Actividad de Usuarios</h1>
                <p>Análisis detallado del rendimiento y actividad de los usuarios del sistema</p>
            </div>
            <div class="header-actions">
                <button class="btn-export" onclick="exportarUsuariosPDF()">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
            </div>
        </div>
    </header>

    <!-- Estadísticas Generales -->
    <section class="stats-grid">
        <div class="stat-card usuarios">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo number_format($stats['usuarios_activos']); ?></div>
            <div class="stat-label">Usuarios Activos</div>
        </div>
        
        <div class="stat-card actividades">
            <div class="stat-icon"><i class="fas fa-activity"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_actividades']); ?></div>
            <div class="stat-label">Total Actividades</div>
        </div>
        
        <div class="stat-card promedio">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo number_format($stats['promedio_por_usuario'], 1); ?></div>
            <div class="stat-label">Promedio por Usuario</div>
        </div>
        
        <div class="stat-card max">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-value"><?php echo number_format($stats['max_actividad']); ?></div>
            <div class="stat-label">Máxima Actividad</div>
        </div>
    </section>

    <!-- Filtros -->
    <section class="filters-section">
        <div class="filters-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        
        <form class="filters-form" method="GET" id="filtrosForm">
            <input type="hidden" name="pagina" value="1">
            
            <div class="filter-group">
                <label class="filter-label">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>" class="filter-control">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>" class="filter-control">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Usuario</label>
                <select name="usuario" class="filter-control">
                    <option value="">Todos los usuarios</option>
                    <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?php echo $usuario['id']; ?>" <?php echo ($filtro_usuario == $usuario['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </form>
    </section>

    <!-- Grid de Contenido -->
    <div class="content-grid">
        <!-- Tabla de Actividad de Usuarios -->
        <section class="usuarios-table-section">
            <div class="table-header">
                <h3><i class="fas fa-table"></i> Resumen por Usuario</h3>
            </div>
            
            <!-- Información de Paginación -->
            <?php if ($total_usuarios > 0): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    <div class="pagination-stats">
                        <strong>
                            <?php 
                            $inicio = $offset + 1;
                            $fin = min($offset + $registros_por_pagina, $total_usuarios);
                            echo number_format($inicio) . '-' . number_format($fin) . ' de ' . number_format($total_usuarios) . ' usuarios';
                            ?>
                        </strong>
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
                            <option value="10" <?php echo ($registros_por_pagina == 10) ? 'selected' : ''; ?>>10</option>
                            <option value="15" <?php echo ($registros_por_pagina == 15) ? 'selected' : ''; ?>>15</option>
                            <option value="25" <?php echo ($registros_por_pagina == 25) ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($registros_por_pagina == 50) ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-loading" id="tableLoading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Cargando usuarios...</p>
            </div>
            
            <div class="table-responsive">
                <table class="usuarios-table" id="usuariosTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Usuario</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-user-tag"></i> Rol</th>
                            <th><i class="fas fa-warehouse"></i> Almacén</th>
                            <th><i class="fas fa-chart-bar"></i> Total</th>
                            <th><i class="fas fa-check"></i> Completadas</th>
                            <th><i class="fas fa-clock"></i> Pendientes</th>
                            <th><i class="fas fa-times"></i> Rechazadas</th>
                            <th><i class="fas fa-calendar"></i> Última Actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_actividad->num_rows > 0): ?>
                            <?php while ($usuario = $result_actividad->fetch_assoc()): ?>
                            <tr>
                                <td class="user-info">
                                    <div class="user-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <strong><?php echo htmlspecialchars($usuario['usuario_nombre']); ?></strong>
                                </td>
                                <td class="user-email"><?php echo htmlspecialchars($usuario['usuario_email']); ?></td>
                                <td class="user-role">
                                    <span class="role-badge <?php echo htmlspecialchars($usuario['rol']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($usuario['rol'])); ?>
                                    </span>
                                </td>
                                <td class="user-almacen"><?php echo htmlspecialchars($usuario['almacen_nombre'] ?? 'N/A'); ?></td>
                                <td class="activity-total"><?php echo number_format($usuario['total_actividades']); ?></td>
                                <td class="activity-completed"><?php echo number_format($usuario['completadas']); ?></td>
                                <td class="activity-pending"><?php echo number_format($usuario['pendientes']); ?></td>
                                <td class="activity-rejected"><?php echo number_format($usuario['rechazadas']); ?></td>
                                <td class="last-activity">
                                    <?php echo formatearFecha($usuario['ultima_actividad']); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-results">
                                    <i class="fas fa-search"></i>
                                    <p>No se encontraron usuarios con actividad en el período seleccionado</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Actividad Reciente -->
        <section class="recent-activity-section">
            <div class="section-header">
                <h3><i class="fas fa-clock"></i> Actividad Reciente</h3>
            </div>
            
            <div class="activity-timeline">
                <?php if ($result_reciente->num_rows > 0): ?>
                    <?php while ($actividad = $result_reciente->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="<?php echo obtenerIconoActividad($actividad['estado']); ?>"></i>
                        </div>
                        
                        <div class="activity-content">
                            <div class="activity-header">
                                <strong><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></strong>
                                <span class="activity-time"><?php echo formatearFecha($actividad['fecha_actividad']); ?></span>
                            </div>
                            
                            <div class="activity-description">
                                <?php echo ucfirst(htmlspecialchars($actividad['tipo_actividad'])); ?> de 
                                <strong><?php echo number_format($actividad['cantidad']); ?></strong> unidades de 
                                <em><?php echo htmlspecialchars($actividad['producto_nombre']); ?></em>
                                <?php if ($actividad['almacen_origen'] && $actividad['almacen_destino']): ?>
                                desde <strong><?php echo htmlspecialchars($actividad['almacen_origen']); ?></strong> 
                                hacia <strong><?php echo htmlspecialchars($actividad['almacen_destino']); ?></strong>
                                <?php endif; ?>
                                <small>(<?php echo ucfirst(htmlspecialchars($actividad['tipo_registro'])); ?>)</small>
                            </div>
                            
                            <div class="activity-status">
                                <span class="status-badge <?php echo normalizarEstado($actividad['estado']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($actividad['estado'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <!-- Paginación para actividad reciente -->
                    <?php 
                    // Calcular total de páginas para actividad (estimación)
                    $total_actividades = 200; // Estimación o calcular con otra query
                    $total_paginas_actividad = ceil($total_actividades / $actividad_por_pagina);
                    ?>
                    
                    <?php if ($total_paginas_actividad > 1): ?>
                    <div class="activity-pagination">
                        <div class="pagination-controls">
                            <?php if ($pagina_actividad > 1): ?>
                                <a href="<?php echo construirURL($pagina_actividad - 1, 'pagina_actividad'); ?>" class="prev">
                                    <i class="fas fa-angle-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <span class="current"><?php echo $pagina_actividad; ?></span>
                            
                            <?php if ($pagina_actividad < $total_paginas_actividad): ?>
                                <a href="<?php echo construirURL($pagina_actividad + 1, 'pagina_actividad'); ?>" class="next">
                                    Siguiente <i class="fas fa-angle-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-activity">
                        <i class="fas fa-calendar-times"></i>
                        <p>No hay actividad reciente en el período seleccionado</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
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

    // Animaciones para las filas de la tabla
    const tableRows = document.querySelectorAll('.usuarios-table tbody tr');
    tableRows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.1}s`;
        row.classList.add('fade-in');
    });

    // Animaciones para actividad reciente
    const activityItems = document.querySelectorAll('.activity-item');
    activityItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('slide-in');
    });
});

// Función para cambiar registros por página
function cambiarRegistrosPorPagina(nuevaCantidad) {
    const url = new URL(window.location);
    url.searchParams.set('registros_por_pagina', nuevaCantidad);
    url.searchParams.set('pagina', '1'); // Resetear a la primera página
    
    // Mostrar indicador de carga
    document.getElementById('tableLoading').classList.add('active');
    document.getElementById('usuariosTable').classList.add('loading');
    
    // Redirigir después de un breve delay para mostrar la animación
    setTimeout(() => {
        window.location.href = url.toString();
    }, 300);
}

// Función para exportar usuarios a PDF
function exportarUsuariosPDF() {
    mostrarNotificacion('Generando reporte PDF de actividad de usuarios...', 'info');
    
    // Obtener filtros actuales de la página
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]')?.value || '';
    const fechaFin = document.querySelector('input[name="fecha_fin"]')?.value || '';
    const usuario = document.querySelector('select[name="usuario"]')?.value || '';
    
    let url = '../reportes/exportar_pdf.php?tipo=usuarios';
    if (fechaInicio) url += '&fecha_inicio=' + fechaInicio;
    if (fechaFin) url += '&fecha_fin=' + fechaFin;
    if (usuario) url += '&usuario=' + usuario;
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
        document.body.appendChild(container);
    }
    
    // Crear notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    notificacion.innerHTML = `
        <i class="fas ${tipo === 'success' ? 'fa-check-circle' : tipo === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${mensaje}</span>
    `;
    
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
                if (prevBtn && prevBtn.href) window.location.href = prevBtn.href;
                break;
            case 'ArrowRight':
                e.preventDefault();
                const nextBtn = document.querySelector('.pagination-controls .next:not(.disabled)');
                if (nextBtn && nextBtn.href) window.location.href = nextBtn.href;
                break;
        }
    }
});

// Función para buscar en tiempo real (opcional)
function buscarUsuario(termino) {
    const filas = document.querySelectorAll('.usuarios-table tbody tr');
    
    filas.forEach(fila => {
        const textoFila = fila.textContent.toLowerCase();
        const coincide = textoFila.includes(termino.toLowerCase());
        
        fila.style.display = coincide ? '' : 'none';
    });
}

// Mejorar la experiencia del usuario con loading states
function mostrarCargando() {
    document.getElementById('tableLoading').classList.add('active');
    document.getElementById('usuariosTable').classList.add('loading');
}

function ocultarCargando() {
    document.getElementById('tableLoading').classList.remove('active');
    document.getElementById('usuariosTable').classList.remove('loading');
}

// Auto-envío del formulario cuando cambian las fechas (opcional)
document.addEventListener('DOMContentLoaded', function() {
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]');
    const fechaFin = document.querySelector('input[name="fecha_fin"]');
    
    if (fechaInicio && fechaFin) {
        let timeout;
        
        function enviarFormulario() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                document.getElementById('filtrosForm').submit();
            }, 1000); // Esperar 1 segundo después del último cambio
        }
        
        fechaInicio.addEventListener('change', enviarFormulario);
        fechaFin.addEventListener('change', enviarFormulario);
    }
});
</script>
</body>
</html>