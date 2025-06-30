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
// Require database connection
require_once "config/database.php";

// ===== CONTAR SOLICITUDES PENDIENTES PARA EL BADGE =====
$total_pendientes = 0;

// Contar solicitudes pendientes para el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";

if ($usuario_rol != 'admin') {
    // Si no es admin, solo mostrar solicitudes de su almacén
    $sql_pendientes .= " AND almacen_destino = ?";
    $stmt_pendientes = $conn->prepare($sql_pendientes);
    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
    $stmt_pendientes->execute();
    $result_pendientes = $stmt_pendientes->get_result();
    $stmt_pendientes->close();
} else {
    // Si es admin, mostrar todas las solicitudes pendientes
    $result_pendientes = $conn->query($sql_pendientes);
}

if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
    $total_pendientes = $row_pendientes['total'];
}

// Liberar resultado
if ($result_pendientes) {
    $result_pendientes->free();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GRUPO SEAL | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Panel de control del sistema de gestión de inventario GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS consistente con las otras páginas -->
    <link rel="stylesheet" href="assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="assets/css/dashboard-consistent.css">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
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
                <li><a href="../entregas/historial.php" role="menuitem"><i class="fas fa-hand-holding"></i> Historial de Entregas</a></li>
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
<main class="content" id="main-content" role="main">
    <header>
        <h1>
            Bienvenido, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
            <small>
                <?php 
                echo $usuario_rol == 'admin' ? 'Administrador del Sistema' : 'Usuario de Almacén'; 
                if ($usuario_almacen_id && $usuario_rol != 'admin') {
                    // Obtener nombre del almacén
                    $sql_almacen = "SELECT nombre FROM almacenes WHERE id = ?";
                    $stmt_almacen = $conn->prepare($sql_almacen);
                    $stmt_almacen->bind_param("i", $usuario_almacen_id);
                    $stmt_almacen->execute();
                    $result_almacen = $stmt_almacen->get_result();
                    if ($row_almacen = $result_almacen->fetch_assoc()) {
                        echo ' - ' . htmlspecialchars($row_almacen['nombre']);
                    }
                }
                ?>
            </small>
        </h1>
    </header>

    <div id="contenido-dinamico">
        <section class="dashboard-grid" role="region" aria-label="Panel de control">
            <?php if ($usuario_rol == 'admin'): ?>
            <!-- Admin Dashboard Cards -->
            <a href="usuarios/listar.php" class="dashboard-card admin-card" tabindex="0" aria-label="Gestión de usuarios">
                <h3><i class="fas fa-users"></i> Gestión de Usuarios</h3>
                <p>Administrar usuarios del sistema, roles y permisos de acceso. Crear, editar y gestionar cuentas de usuario.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Usuarios
                    </span>
                </div>
            </a>

            <a href="almacenes/listar.php" class="dashboard-card warehouse-card" tabindex="0" aria-label="Gestión de almacenes">
                <h3><i class="fas fa-warehouse"></i> Gestión de Almacenes</h3>
                <p>Administrar ubicaciones de almacenes y asignaciones. Controlar inventarios por ubicación.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Almacenes
                    </span>
                </div>
            </a>

            <a href="reportes/inventario.php" class="dashboard-card report-card" tabindex="0" aria-label="Reportes del sistema">
                <h3><i class="fas fa-chart-line"></i> Reportes y Estadísticas</h3>
                <p>Generar reportes detallados del inventario y movimientos. Análisis de datos del sistema.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Reportes
                    </span>
                </div>
            </a>

            <a href="notificaciones/pendientes.php" class="dashboard-card notification-card" tabindex="0" aria-label="Centro de notificaciones">
                <h3><i class="fas fa-bell"></i> Centro de Notificaciones</h3>
                <p>Revisar todas las solicitudes y notificaciones del sistema. Gestionar aprobaciones pendientes.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Notificaciones
                    </span>
                    <?php if (isset($total_pendientes) && $total_pendientes > 0): ?>
                    <span class="badge"><?php echo $total_pendientes; ?></span>
                    <?php endif; ?>
                </div>
            </a>

            <a href="usuarios/registrar.php" class="dashboard-card admin-card" tabindex="0" aria-label="Acciones rápidas">
                <h3><i class="fas fa-plus-circle"></i> Acciones Rápidas</h3>
                <p>Registrar nuevos usuarios y almacenes. Herramientas de administración rápida del sistema.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Registrar Nuevo
                    </span>
                </div>
            </a>

            <a href="entregas/historial.php" class="dashboard-card notification-card" tabindex="0" aria-label="Gestión de uniformes">
                <h3><i class="fas fa-tshirt"></i> Gestión de Uniformes</h3>
                <p>Administrar entregas de uniformes y equipamiento. Control de distribución de materiales.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Historial de Entregas
                    </span>
                </div>
            </a>

            <?php else: ?>
            <!-- Regular User Dashboard Cards -->
            <a href="almacenes/listar.php" class="dashboard-card warehouse-card" tabindex="0" aria-label="Mi almacén">
                <h3><i class="fas fa-warehouse"></i> Mi Almacén</h3>
                <p>Ver información detallada de tu almacén asignado y gestionar el inventario disponible.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Mi Almacén
                    </span>
                </div>
            </a>

            <a href="notificaciones/pendientes.php" class="dashboard-card notification-card" tabindex="0" aria-label="Solicitudes pendientes">
                <h3><i class="fas fa-clock"></i> Solicitudes Pendientes</h3>
                <p>Revisar y gestionar solicitudes de transferencia pendientes de aprobación.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Solicitudes
                    </span>
                    <?php if (isset($total_pendientes) && $total_pendientes > 0): ?>
                    <span class="badge"><?php echo $total_pendientes; ?></span>
                    <?php endif; ?>
                </div>
            </a>

            <a href="notificaciones/historial.php" class="dashboard-card notification-card" tabindex="0" aria-label="Historial de actividad">
                <h3><i class="fas fa-history"></i> Historial de Actividad</h3>
                <p>Consultar el historial completo de solicitudes y transferencias realizadas.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Historial
                    </span>
                </div>
            </a>

            <a href="uniformes/historial_entregas_uniformes.php" class="dashboard-card notification-card" tabindex="0" aria-label="Entregas de uniformes">
                <h3><i class="fas fa-tshirt"></i> Entregas de Uniformes</h3>
                <p>Consultar Ver Historial de Entregas de uniformes y equipamiento asignado.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Historial de Entregas
                    </span>
                </div>
            </a>

            <a href="reportes/inventario.php" class="dashboard-card report-card" tabindex="0" aria-label="Reportes de mi almacén">
                <h3><i class="fas fa-chart-bar"></i> Reportes de Almacén</h3>
                <p>Generar reportes específicos de tu almacén asignado y movimientos realizados.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Ver Reportes
                    </span>
                </div>
            </a>

            <a href="perfil/configuracion.php" class="dashboard-card profile-card" tabindex="0" aria-label="Mi perfil">
                <h3><i class="fas fa-user-cog"></i> Mi Perfil</h3>
                <p>Gestionar configuración personal y cambiar contraseña de usuario.</p>
                <div class="card-footer">
                    <span class="card-action">
                        <i class="fas fa-arrow-right"></i> Configurar Perfil
                    </span>
                </div>
            </a>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript optimizado -->
<script src="assets/js/universal-confirmation-system.js"></script>
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
    
    // Efectos mejorados para las tarjetas
    const dashboardCards = document.querySelectorAll('.dashboard-card');
    dashboardCards.forEach((card, index) => {
        // Efectos de hover mejorados
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-12px) scale(1.03)';
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.zIndex = '1';
        });
        
        // Efecto de clic
        card.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(-8px) scale(1.01)';
        });
        
        card.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-12px) scale(1.03)';
        });
        
        // Efecto ripple
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple-effect');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Mostrar notificación de bienvenida
    setTimeout(() => {
        mostrarNotificacion(
            `¡Bienvenido de vuelta, <?php echo htmlspecialchars($user_name); ?>!`, 
            'exito', 
            4000
        );
    }, 1500);
    
    // Actualizar badges de notificaciones (opcional)
    function actualizarBadgesNotificaciones() {
        fetch('api/obtener_notificaciones_count.php')
            .then(response => response.json())
            .then(data => {
                const badges = document.querySelectorAll('.badge, .badge-small');
                badges.forEach(badge => {
                    if (data.pendientes > 0) {
                        badge.textContent = data.pendientes;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(error => {
                console.log('No se pudieron actualizar las notificaciones:', error);
            });
    }
    
    // Optimización de rendimiento
    if ('IntersectionObserver' in window) {
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        
        dashboardCards.forEach(card => {
            cardObserver.observe(card);
        });
    }
});

// Función para cerrar sesión con confirmación
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    const confirmado = await confirmarCerrarSesion();
    
    if (confirmado) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        
        setTimeout(() => {
            window.location.href = 'logout.php';
        }, 1000);
    }
}

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});

// Función de confirmación global
window.confirmarAccion = function(mensaje, callback) {
    if (confirm(mensaje)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
};
</script>
</body>
</html>