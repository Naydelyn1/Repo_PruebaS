<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /views/login_form.php");
    exit();
}

session_regenerate_id(true);

require_once "../config/database.php";

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;
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

// Consultar almacenes registrados
if ($usuario_rol == 'admin') {
    $sql = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result = $conn->query($sql);
} else {
    if ($usuario_almacen_id) {
        $sql = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Almacenes - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Lista de almacenes del sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para listar almacenes -->
    <link rel="stylesheet" href="../assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/almacen/almacenes-listar.css">
</head>
<body>

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

    <header class="page-header">
        <h1><?php echo ($usuario_rol == 'admin') ? 'Almacenes Registrados' : 'Mi Almacén Asignado'; ?></h1>
        <p class="page-description">
            <?php echo ($usuario_rol == 'admin') ? 'Gestiona todos los almacenes del sistema' : 'Información de tu almacén asignado'; ?>
        </p>
    </header>

    <div class="almacenes-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="almacen-card" data-almacen-id="<?php echo $row['id']; ?>">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($row["nombre"]); ?></h3>
                        <div class="card-actions">
                            <?php if ($usuario_rol == 'admin'): ?>
                            <button class="btn-action btn-edit" onclick="editarAlmacen(<?php echo $row['id']; ?>)" title="Editar almacén">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action btn-delete" onclick="eliminarAlmacen(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')" title="Eliminar almacén">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <p class="ubicacion">
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Ubicación:</strong> <?php echo htmlspecialchars($row["ubicacion"]); ?>
                        </p>
                        
                        <div class="warehouse-stats">
                            <?php
                            // Obtener estadísticas del almacén
                            $almacen_id = $row['id'];
                            $sql_stats = "SELECT 
                                COUNT(DISTINCT p.categoria_id) as total_categorias,
                                COUNT(p.id) as total_productos,
                                COALESCE(SUM(p.cantidad), 0) as total_stock
                                FROM productos p 
                                WHERE p.almacen_id = ?";
                            $stmt_stats = $conn->prepare($sql_stats);
                            $stmt_stats->bind_param("i", $almacen_id);
                            $stmt_stats->execute();
                            $stats = $stmt_stats->get_result()->fetch_assoc();
                            ?>
                            
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $stats['total_categorias']; ?></span>
                                <span class="stat-label">Categorías</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $stats['total_productos']; ?></span>
                                <span class="stat-label">Productos</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $stats['total_stock']; ?></span>
                                <span class="stat-label">Stock Total</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <button onclick="verAlmacen(<?php echo $row['id']; ?>)" class="btn-ver">
                            <i class="fas fa-eye"></i> Ver Detalle
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-warehouse"></i>
                <?php if ($usuario_rol != 'admin' && !$usuario_almacen_id): ?>
                    <h3>Sin Almacén Asignado</h3>
                    <p>No tienes un almacén asignado. Contacta con un administrador.</p>
                <?php else: ?>
                    <h3>No hay almacenes registrados</h3>
                    <p>Aún no se han registrado almacenes en el sistema.</p>
                    <?php if ($usuario_rol == 'admin'): ?>
                    <a href="registrar.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Registrar Primer Almacén
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($usuario_rol == 'admin' && $result && $result->num_rows > 0): ?>
    <div class="action-bar">
        <a href="registrar.php" class="btn-add-warehouse">
            <i class="fas fa-plus"></i> Registrar Nuevo Almacén
        </a>
    </div>
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
    
    // Efectos para las tarjetas de almacenes
    const almacenCards = document.querySelectorAll('.almacen-card');
    almacenCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// FUNCIÓN MODIFICADA PARA MAYOR SEGURIDAD - VER ALMACÉN
function verAlmacen(almacenId) {
    const almacenCard = document.querySelector(`[data-almacen-id="${almacenId}"]`);
    almacenCard.style.background = 'rgba(23, 162, 184, 0.1)';
    almacenCard.style.transform = 'scale(1.02)';
    
    // Crear formulario oculto para enviar por POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ver_redirect.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'view_almacen_id';
    input.value = almacenId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    
    setTimeout(() => {
        form.submit();
    }, 200);
}

// FUNCIÓN MODIFICADA PARA MAYOR SEGURIDAD - EDITAR ALMACÉN
function editarAlmacen(almacenId) {
    const almacenCard = document.querySelector(`[data-almacen-id="${almacenId}"]`);
    almacenCard.style.background = 'rgba(255, 193, 7, 0.1)';
    almacenCard.style.transform = 'scale(1.02)';
    
    // Crear formulario oculto para enviar por POST
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
    
    setTimeout(() => {
        form.submit();
    }, 200);
}

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

// Función para eliminar almacén
async function eliminarAlmacen(id, nombre) {
    const confirmado = await confirmarEliminacion('Almacén', nombre);
    
    if (confirmado) {
        mostrarNotificacion('Eliminando almacén...', 'info');
        
        // Realizar petición AJAX para eliminar
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
                // Remover la tarjeta del DOM
                const card = document.querySelector(`[data-almacen-id="${id}"]`);
                if (card) {
                    card.style.animation = 'fadeOut 0.5s ease';
                    setTimeout(() => {
                        card.remove();
                        // Verificar si quedan almacenes
                        const remainingCards = document.querySelectorAll('.almacen-card');
                        if (remainingCards.length === 0) {
                            location.reload(); // Recargar para mostrar estado vacío
                        }
                    }, 500);
                }
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

// Animación de entrada para las tarjetas
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.almacen-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('card-enter');
    });
});
</script>

<style>
@keyframes fadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
}

.card-enter {
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
</style>
</body>
</html>