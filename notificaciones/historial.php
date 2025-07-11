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

// Obtener el almacén y rol del usuario actual
$usuario_almacen_id = $_SESSION["almacen_id"]; 
$usuario_rol = $_SESSION["user_role"];
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";

$sql = "SELECT 
            st.id, 
            st.producto_id, 
            st.almacen_origen, 
            st.almacen_destino, 
            st.cantidad, 
            st.estado,
            st.fecha_solicitud,
            st.usuario_aprobador_id,
            CASE
                WHEN st.estado = 'aprobada' THEN COALESCE(m.fecha, st.fecha_solicitud)
                WHEN st.estado = 'rechazada' THEN st.fecha_solicitud
                ELSE NULL
            END as fecha_respuesta,
            p.nombre as producto_nombre, 
            p.color, 
            p.talla_dimensiones, 
            p.modelo, 
            p.estado as estado_producto,
            c.nombre as categoria_nombre,
            a1.nombre as origen_nombre, 
            a2.nombre as destino_nombre,
            u_solicitante.nombre as solicitante_nombre,
            u_solicitante.apellidos as solicitante_apellidos,
            u_aprobador.nombre as aprobador_nombre,
            u_aprobador.apellidos as aprobador_apellidos
        FROM solicitudes_transferencia st
        JOIN productos p ON st.producto_id = p.id
        JOIN categorias c ON p.categoria_id = c.id
        JOIN almacenes a1 ON st.almacen_origen = a1.id
        JOIN almacenes a2 ON st.almacen_destino = a2.id
        JOIN usuarios u_solicitante ON st.usuario_id = u_solicitante.id
        LEFT JOIN usuarios u_aprobador ON st.usuario_aprobador_id = u_aprobador.id
        LEFT JOIN movimientos m ON (m.producto_id = st.producto_id 
                                  AND m.almacen_origen = st.almacen_origen 
                                  AND m.almacen_destino = st.almacen_destino 
                                  AND m.cantidad = st.cantidad 
                                  AND m.tipo = 'transferencia'
                                  AND m.estado = 'completado')
        WHERE st.estado != 'pendiente'";

// Si el usuario no es admin, filtrar solo por solicitudes relacionadas con su almacén
if ($usuario_rol != 'admin') {
    $sql .= " AND (st.almacen_origen = ? OR st.almacen_destino = ?)";
}
        
$sql .= " ORDER BY 
            CASE 
                WHEN st.estado = 'aprobada' THEN m.fecha 
                ELSE st.fecha_solicitud 
            END DESC";

// Ejecutar la consulta con o sin filtro de almacén
$solicitudes_historial = [];
if ($usuario_rol != 'admin') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $usuario_almacen_id, $usuario_almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_historial[] = $row;
    }
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
    <title>Historial de Solicitudes - COMSEPROA | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Historial de solicitudes del sistema de inventario COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS específico para historial -->
    
    <link rel="stylesheet" href="../assets/css/notificaciones/notificaciones-historial-specific.css">
    
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

<!-- Main Content -->
<main class="main-content" id="main-content" role="main">
    <h1>Historial de Solicitudes de Transferencia</h1>
    
    <!-- Contenedor de notificaciones -->
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
    
    <!-- Sección de filtros -->
    <div class="filtros-container">
        <div class="filtros-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        
        <div class="filtros">
            <div>
                <label for="filtro-estado">Estado:</label>
                <select id="filtro-estado">
                    <option value="todos">Todos los estados</option>
                    <option value="aprobada">Aprobadas</option>
                    <option value="rechazada">Rechazadas</option>
                </select>
            </div>
            
            <div>
                <label for="filtro-almacen">Almacén:</label>
                <select id="filtro-almacen">
                    <option value="todos">Todos los almacenes</option>
                    <?php
                    $sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
                    $result_almacenes = $conn->query($sql_almacenes);
                    
                    if ($result_almacenes && $result_almacenes->num_rows > 0) {
                        while ($row_almacen = $result_almacenes->fetch_assoc()) {
                            echo '<option value="' . $row_almacen['id'] . '">' . htmlspecialchars($row_almacen['nombre']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label for="filtro-fecha">Fecha:</label>
                <input type="date" id="filtro-fecha" placeholder="Fecha">
            </div>
            
            <div>
                <button class="btn-filtrar" onclick="filtrarHistorial()">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tabla de historial -->
    <div class="tabla-container">
        <div class="tabla-wrapper">
            <table class="tabla-historial" id="tabla-historial">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Cantidad</th>
                        <th>Almacén Origen</th>
                        <th>Almacén Destino</th>
                        <th>Enviado por</th>
                        <th>Fecha Solicitud</th>
                        <th>Estado</th>
                        <th>Aprobado/Rechazado por</th>
                        <th>Fecha A/R</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($solicitudes_historial) > 0): ?>
                        <?php foreach ($solicitudes_historial as $solicitud): ?>
                            <tr class="fila-historial" 
                                data-estado="<?php echo $solicitud['estado']; ?>"
                                data-origen="<?php echo $solicitud['almacen_origen']; ?>"
                                data-destino="<?php echo $solicitud['almacen_destino']; ?>"
                                data-fecha="<?php echo date('Y-m-d', strtotime($solicitud['fecha_solicitud'])); ?>">
                                <td><?php echo $solicitud['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($solicitud['producto_nombre']); ?></strong>
                                    <br>
                                    <small>
                                        <?php echo htmlspecialchars($solicitud['color']); ?>, 
                                        <?php echo htmlspecialchars($solicitud['talla_dimensiones']); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($solicitud['categoria_nombre']); ?></td>
                                <td><strong><?php echo intval($solicitud['cantidad']); ?></strong></td>
                                <td><?php echo htmlspecialchars($solicitud['origen_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['destino_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['solicitante_nombre'] . ' ' . $solicitud['solicitante_apellidos']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></td>
                                <td>
                                    <span class="solicitud-estado estado-<?php echo $solicitud['estado']; ?>">
                                        <?php echo ucfirst($solicitud['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($solicitud['aprobador_nombre'])): ?>
                                        <?php echo htmlspecialchars($solicitud['aprobador_nombre'] . ' ' . $solicitud['aprobador_apellidos']); ?>
                                    <?php else: ?>
                                        <em>-</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($solicitud['fecha_respuesta'])): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_respuesta'])); ?>
                                    <?php else: ?>
                                        <em>-</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="tabla-vacia">
                                <i class="fas fa-inbox"></i>
                                <h3>No hay registros</h3>
                                <p>No se han encontrado solicitudes procesadas en el historial.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Acciones rápidas -->
    <div class="acciones-rapidas">
        <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
        <div class="acciones-grid">
            <a href="pendientes.php" class="accion-rapida">
                <i class="fas fa-clock"></i>
                <span>Ver Pendientes</span>
            </a>
            
            <a href="../dashboard.php" class="accion-rapida">
                <i class="fas fa-home"></i>
                <span>Ir a Inicio</span>
            </a>
            
            <a href="../almacenes/listar.php" class="accion-rapida">
                <i class="fas fa-warehouse"></i>
                <span>Ver Almacenes</span>
            </a>
            
            <a href="../productos/listar.php" class="accion-rapida">
                <i class="fas fa-boxes"></i>
                <span>Ver Productos</span>
            </a>
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
    console.log('🚀 Inicializando historial de solicitudes');
    
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
        const totalRegistros = <?php echo count($solicitudes_historial); ?>;
        if (totalRegistros > 0) {
            mostrarNotificacion(
                `Se encontraron ${totalRegistros} registro${totalRegistros > 1 ? 's' : ''} en el historial.`, 
                'exito', 
                4000
            );
        }
    }, 1000);
    
    console.log('✅ Historial de solicitudes iniciado correctamente');
});

// Función para filtrar el historial
function filtrarHistorial() {
    const filtroEstado = document.getElementById('filtro-estado').value;
    const filtroAlmacen = document.getElementById('filtro-almacen').value;
    const filtroFecha = document.getElementById('filtro-fecha').value;
    
    // Obtener todas las filas de la tabla
    const filas = document.querySelectorAll('#tabla-historial tbody tr.fila-historial');
    let registrosMostrados = 0;
    
    filas.forEach(fila => {
        let mostrar = true;
        
        // Filtrar por estado
        if (filtroEstado !== 'todos' && fila.dataset.estado !== filtroEstado) {
            mostrar = false;
        }
        
        // Filtrar por almacén (origen o destino)
        if (filtroAlmacen !== 'todos' && 
            fila.dataset.origen !== filtroAlmacen && 
            fila.dataset.destino !== filtroAlmacen) {
            mostrar = false;
        }
        
        // Filtrar por fecha
        if (filtroFecha && fila.dataset.fecha !== filtroFecha) {
            mostrar = false;
        }
        
        // Mostrar u ocultar la fila según los filtros
        fila.style.display = mostrar ? '' : 'none';
        if (mostrar) {
            registrosMostrados++;
        }
    });
    
    // Mostrar notificación con resultados
    const mensaje = registrosMostrados === 0 
        ? 'No se encontraron registros con los filtros aplicados.'
        : `Se ${registrosMostrados === 1 ? 'encontró' : 'encontraron'} ${registrosMostrados} registro${registrosMostrados > 1 ? 's' : ''}.`;
    
    mostrarNotificacion(mensaje, registrosMostrados === 0 ? 'info' : 'exito', 3000);
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