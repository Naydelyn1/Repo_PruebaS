<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

// Evitar secuestro de sesión
session_regenerate_id(true);

// Obtener información del usuario
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

require_once "../config/database.php";

// MODIFICACIÓN DE SEGURIDAD: Obtener ID del almacén usando sesión
if (isset($_SESSION['view_almacen_id'])) {
    $almacen_id = (int) $_SESSION['view_almacen_id'];
    // Limpiar la sesión después de obtener el ID
    unset($_SESSION['view_almacen_id']);
} else {
    $_SESSION['error'] = "Acceso no válido";
    header("Location: listar.php");
    exit();
}

// Si el usuario no es admin, verificar que solo pueda acceder a su almacén asignado
if ($usuario_rol != 'admin' && $usuario_almacen_id != $almacen_id) {
    $_SESSION['error'] = "No tienes permiso para acceder a este almacén";
    header("Location: listar.php");
    exit();
}

// Obtener información del almacén
$sql = "SELECT * FROM almacenes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $almacen_id);
$stmt->execute();
$result = $stmt->get_result();
$almacen = $result->fetch_assoc();
$stmt->close();

if (!$almacen) {
    $_SESSION['error'] = "Almacén no encontrado";
    header("Location: listar.php");
    exit();
}

// CORREGIDO: Obtener todas las categorías (SIN FILTRO HAVING)
$sql_categorias = "SELECT c.id, c.nombre,
                   (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.almacen_id = ?) AS total_productos,
                   (SELECT COALESCE(SUM(p.cantidad), 0) FROM productos p WHERE p.categoria_id = c.id AND p.almacen_id = ?) AS total_stock
                   FROM categorias c
                   ORDER BY c.nombre";
$stmt_categorias = $conn->prepare($sql_categorias);
$stmt_categorias->bind_param("ii", $almacen_id, $almacen_id);
$stmt_categorias->execute();
$categorias = $stmt_categorias->get_result();
$stmt_categorias->close();

// Obtener estadísticas generales del almacén
$sql_stats = "SELECT 
    COUNT(DISTINCT p.categoria_id) as total_categorias,
    COUNT(p.id) as total_productos,
    COALESCE(SUM(p.cantidad), 0) as total_stock,
    COALESCE(AVG(p.cantidad), 0) as promedio_stock
    FROM productos p 
    WHERE p.almacen_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $almacen_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// Obtener productos con stock bajo (menos de 10 unidades)
$sql_low_stock = "SELECT p.id, p.nombre, p.cantidad, c.nombre as categoria_nombre
                  FROM productos p
                  JOIN categorias c ON p.categoria_id = c.id
                  WHERE p.almacen_id = ? AND p.cantidad < 10
                  ORDER BY p.cantidad ASC, p.nombre ASC
                  LIMIT 10";
$stmt_low_stock = $conn->prepare($sql_low_stock);
$stmt_low_stock->bind_param("i", $almacen_id);
$stmt_low_stock->execute();
$productos_bajo_stock = $stmt_low_stock->get_result();
$stmt_low_stock->close();

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
    <title>Ver Almacén - <?php echo htmlspecialchars($almacen['nombre']); ?> - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Detalle del almacén <?php echo htmlspecialchars($almacen['nombre']); ?> - Sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para ver almacén -->
    <link rel="stylesheet" href="../assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/almacen/almacenes-ver.css">
</head>
<body data-almacen-id="<?php echo $almacen_id; ?>">

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

    <!-- Header del Almacén -->
    <header class="warehouse-header">
        <div class="header-content">
            <div class="warehouse-info">
                <div class="warehouse-icon">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="warehouse-details">
                    <h1><?php echo htmlspecialchars($almacen['nombre']); ?></h1>
                    <p class="warehouse-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($almacen['ubicacion']); ?>
                    </p>
                </div>
            </div>
            
            <div class="header-actions">
                <?php if ($usuario_rol == 'admin'): ?>
                <button class="btn-action btn-edit" onclick="editarAlmacen(<?php echo $almacen_id; ?>)" title="Editar almacén">
                    <i class="fas fa-edit"></i>
                    <span>Editar</span>
                </button>
                <button class="btn-action btn-delete" onclick="eliminarAlmacen(<?php echo $almacen_id; ?>, '<?php echo htmlspecialchars($almacen['nombre']); ?>')" title="Eliminar almacén">
                    <i class="fas fa-trash"></i>
                    <span>Eliminar</span>
                </button>
                <?php endif; ?>
                
                <a href="listar.php" class="btn-action btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver</span>
                </a>
            </div>
        </div>
        
        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?php echo htmlspecialchars($almacen['nombre']); ?></span>
        </nav>
    </header>

    <!-- Estadísticas del Almacén -->
    <section class="warehouse-stats" aria-label="Estadísticas del almacén">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_categorias']; ?></div>
                    <div class="stat-label">Categorías</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_productos']; ?></div>
                    <div class="stat-label">Productos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_stock']); ?></div>
                    <div class="stat-label">Stock Total</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['promedio_stock'], 1); ?></div>
                    <div class="stat-label">Promedio por Producto</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contenido Principal -->
    <div class="main-content-grid">
        <!-- Categorías del Almacén (SECCIÓN CORREGIDA) -->
        <section class="categories-section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-box-open"></i>
                    Categorías en este almacén
                </h2>
                <?php if ($usuario_rol == 'admin'): ?>
                <a href="javascript:void(0)" onclick="navegarAgregarProducto()" class="btn-add-product">
                    <i class="fas fa-plus"></i>
                    Agregar Producto
                </a>
                <?php endif; ?>
            </div>
            
            <div class="categorias-container">
                <?php if ($categorias->num_rows > 0): ?>
                    <?php while ($categoria = $categorias->fetch_assoc()): ?>
                        <div class="categoria-card <?php echo ($categoria['total_productos'] == 0) ? 'categoria-empty' : ''; ?>" data-categoria-id="<?php echo $categoria['id']; ?>">
                            <div class="categoria-header">
                                <div class="categoria-icon">
                                    <?php if ($categoria['total_productos'] > 0): ?>
                                        <i class="fas fa-box-open"></i>
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                <?php if ($categoria['total_productos'] == 0): ?>
                                    <span class="empty-badge">Sin productos</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="categoria-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $categoria['total_productos']; ?></span>
                                    <span class="stat-text">Productos</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo number_format($categoria['total_stock']); ?></span>
                                    <span class="stat-text">Stock</span>
                                </div>
                            </div>

                            <div class="categoria-actions">
                                <?php if ($usuario_rol == 'admin'): ?>
                                <button class="btn-categoria btn-registrar" onclick="navegarAgregarProductoCategoria(<?php echo $categoria['id']; ?>)">
                                    <i class="fas fa-plus"></i>
                                    <?php echo ($categoria['total_productos'] == 0) ? 'Primer Producto' : 'Registrar Producto'; ?>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($categoria['total_productos'] > 0): ?>
                                <button class="btn-categoria btn-lista" onclick="navegarProductosCategoria(<?php echo $categoria['id']; ?>)">
                                    <i class="fas fa-list"></i>
                                    Ver Productos
                                </button>
                                <?php else: ?>
                                <button class="btn-categoria btn-lista disabled" disabled>
                                    <i class="fas fa-list"></i>
                                    Sin productos
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No hay categorías registradas</h3>
                        <p>No se han registrado categorías en el sistema.</p>
                        <?php if ($usuario_rol == 'admin'): ?>
                        <a href="../productos/categorias.php" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Registrar Primera Categoría
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Panel de Alertas -->
        <aside class="alerts-panel">
            <div class="panel-header">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    Alertas de Stock
                </h3>
            </div>
            
            <div class="alerts-content">
                <?php if ($productos_bajo_stock->num_rows > 0): ?>
                    <div class="alert-item low-stock">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="alert-content">
                            <h4>Stock Bajo</h4>
                            <p><?php echo $productos_bajo_stock->num_rows; ?> productos con menos de 10 unidades</p>
                        </div>
                    </div>
                    
                    <div class="low-stock-list">
                        <h5>Productos con stock crítico:</h5>
                        <ul>
                            <?php while ($producto_bajo = $productos_bajo_stock->fetch_assoc()): ?>
                            <li class="low-stock-item">
                                <span class="product-name"><?php echo htmlspecialchars($producto_bajo['nombre']); ?></span>
                                <span class="product-category"><?php echo htmlspecialchars($producto_bajo['categoria_nombre']); ?></span>
                                <span class="stock-level stock-critical"><?php echo $producto_bajo['cantidad']; ?> unidades</span>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert-item success">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert-content">
                            <h4>Stock Saludable</h4>
                            <p>Todos los productos tienen stock adecuado</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- ACCIONES RÁPIDAS SIMPLIFICADAS -->
                <div class="quick-actions">
                    <h5>Acciones Rápidas</h5>
                    <div class="action-buttons">
                        
                        <?php if ($usuario_rol == 'admin'): ?>
                        <a href="../reportes/inventario.php?almacen_id=<?php echo $almacen_id; ?>" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            Generar Reporte
                        </a>
                        <?php endif; ?>
                        
                        <a href="../notificaciones/pendientes.php" class="quick-action-btn">
                            <i class="fas fa-bell"></i>
                            Ver Solicitudes
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

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript -->
<script src="../assets/js/universal-confirmation-system.js"></script>
<script>
// Variables globales para el contexto del almacén
const ALMACEN_ID = <?php echo $almacen_id; ?>;
const ALMACEN_NOMBRE = '<?php echo htmlspecialchars($almacen['nombre'], ENT_QUOTES, 'UTF-8'); ?>';

// Guardar contexto en sessionStorage para navegación
function guardarContextoAlmacen() {
    sessionStorage.setItem('almacen_context', JSON.stringify({
        almacen_id: ALMACEN_ID,
        almacen_nombre: ALMACEN_NOMBRE,
        page: 'ver-almacen',
        timestamp: Date.now()
    }));
}

// Funciones de navegación que mantienen el contexto
function navegarAgregarProducto() {
    guardarContextoAlmacen();
    window.location.href = `../productos/registrar.php?almacen_id=${ALMACEN_ID}`;
}

function navegarAgregarProductoCategoria(categoriaId) {
    guardarContextoAlmacen();
    window.location.href = `../productos/registrar.php?almacen_id=${ALMACEN_ID}&categoria_id=${categoriaId}`;
}

function navegarProductosCategoria(categoriaId) {
    guardarContextoAlmacen();
    window.location.href = `../productos/listar.php?almacen_id=${ALMACEN_ID}&categoria_id=${categoriaId}`;
}

document.addEventListener('DOMContentLoaded', function() {
    // Guardar contexto inicial
    guardarContextoAlmacen();
    
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
    
    // Mostrar submenú de almacenes activo por defecto
    const almacenesSubmenu = document.querySelector('.submenu-container .submenu');
    const almacenesChevron = document.querySelector('.submenu-container .fa-chevron-down');
    const almacenesLink = document.querySelector('.submenu-container > a');
    
    if (almacenesSubmenu) {
        almacenesSubmenu.classList.add('activo');
        if (almacenesChevron) {
            almacenesChevron.style.transform = 'rotate(180deg)';
        }
        if (almacenesLink) {
            almacenesLink.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Efectos para las tarjetas de categorías
    const categoriaCards = document.querySelectorAll('.categoria-card');
    categoriaCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Efectos para las tarjetas de estadísticas
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.15}s`;
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Auto-cerrar alertas
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideOutUp 0.5s ease-in-out';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Manejar navegación del navegador (botón atrás)
    window.addEventListener('popstate', function(event) {
        // Si hay un contexto guardado, redirigir al almacén correcto
        const context = sessionStorage.getItem('almacen_context');
        if (context) {
            const contextData = JSON.parse(context);
            if (contextData.almacen_id === ALMACEN_ID) {
                // Ya estamos en el almacén correcto
                return;
            }
        }
    });
});

// Función para cerrar sesión con confirmación
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

// FUNCIÓN SEGURA PARA EDITAR ALMACÉN
function editarAlmacen(almacenId) {
    // Crear formulario oculto para navegación segura
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'editar_redirect.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'edit_almacen_id';
    input.value = almacenId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Función para eliminar almacén
async function eliminarAlmacen(id, nombre) {
    const confirmado = await confirmarEliminacion('Almacén', nombre);
    
    if (confirmado) {
        mostrarNotificacion('Eliminando almacén...', 'info');
        
        fetch('eliminar_almacen.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Almacén eliminado correctamente', 'exito');
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar el almacén', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al eliminar el almacén', 'error');
        });
    }
}

// Contador animado para las estadísticas
function animateCounters() {
    const counters = document.querySelectorAll('.stat-value');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/,/g, ''));
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };
        
        // Iniciar animación cuando la tarjeta sea visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(counter.closest('.stat-card'));
    });
}

// Iniciar animaciones cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(animateCounters, 500);
});
</script>

<style>
@keyframes slideOutUp {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
    }
}

.categoria-card {
    animation: slideInUp 0.6s ease-out both;
}

.stat-card {
    animation: slideInUp 0.6s ease-out both;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estilos para categorías vacías */
.categoria-card.categoria-empty {
    border: 2px dashed #ddd;
    background: rgba(248, 249, 250, 0.7);
    opacity: 0.8;
}

.categoria-card.categoria-empty:hover {
    border-color: #007bff;
    background: rgba(0, 123, 255, 0.05);
    opacity: 1;
}

.categoria-card.categoria-empty .categoria-icon {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.categoria-card.categoria-empty .categoria-header h3 {
    color: #6c757d;
}

.empty-badge {
    background: #6c757d;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
    font-weight: 500;
}

.categoria-card.categoria-empty .stat-number {
    color: #6c757d;
}

.btn-categoria.disabled {
    background: #f8f9fa;
    color: #6c757d;
    border-color: #dee2e6;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-categoria.disabled:hover {
    background: #f8f9fa;
    color: #6c757d;
    border-color: #dee2e6;
    transform: none;
}

/* Animación especial para categorías vacías */
.categoria-card.categoria-empty {
    animation: slideInUpEmpty 0.6s ease-out both;
}

@keyframes slideInUpEmpty {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 0.8;
        transform: translateY(0) scale(1);
    }
}

/* Efecto hover mejorado para categorías vacías */
.categoria-card.categoria-empty:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.15);
}

/* Destacar botón de primer producto */
.categoria-empty .btn-registrar {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    font-weight: 500;
}

.categoria-empty .btn-registrar:hover {
    background: linear-gradient(135deg, #20c997, #28a745);
    transform: translateY(-2px);
}
</style>
</body>
</html>