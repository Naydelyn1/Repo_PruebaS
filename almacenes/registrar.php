<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evita secuestro de sesión
session_regenerate_id(true);

require_once "../config/database.php";


$mensaje = "";
$error = "";
$nombre = "";
$ubicacion = "";

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST["nombre"]) && !empty($_POST["ubicacion"])) {
        $nombre = trim($_POST["nombre"]);
        $ubicacion = trim($_POST["ubicacion"]);

        // Verificar si el almacén ya existe
        $sql_check = "SELECT id FROM almacenes WHERE nombre = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ El almacén ya existe.";
        } else {
            // Insertar el nuevo almacén
            $sql = "INSERT INTO almacenes (nombre, ubicacion) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ss", $nombre, $ubicacion);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "✅ Almacén registrado con éxito.";
                    // Limpiar valores después del registro exitoso
                    $nombre = "";
                    $ubicacion = "";
                    // Redirigir para evitar reenvío del formulario
                    header("Location: registrar.php");
                    exit();
                } else {
                    $error = "❌ Error al registrar el almacén: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $error = "⚠️ Todos los campos son obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Almacén - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Registrar nuevo almacén en el sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para registrar almacenes -->
    <link rel="stylesheet" href="../assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/almacen/almacenes-registrar.css">
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

    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <header class="page-header">
        <h1>Registrar Nuevo Almacén</h1>
        <p class="page-description">
            Ingresa la información del nuevo almacén que deseas agregar al sistema
        </p>
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Registrar</span>
        </nav>
    </header>

    <div class="register-container">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-warehouse"></i>
            </div>
            <h2>Información del Almacén</h2>
            <p>Complete los campos requeridos para registrar el almacén</p>
        </div>

        <form id="formRegistrarAlmacen" action="" method="POST" autocomplete="off">
            <div class="form-group">
                <label for="nombre" class="form-label">
                    <i class="fas fa-building"></i>
                    Nombre del Almacén
                    <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    value="<?php echo htmlspecialchars($nombre); ?>" 
                    placeholder="Ej: Almacén Central, Bodega Norte..."
                    required
                    autocomplete="off"
                    maxlength="100"
                >
                <div class="field-hint">
                    <i class="fas fa-info-circle"></i>
                    Ingrese un nombre descriptivo y único para el almacén
                </div>
            </div>

            <div class="form-group">
                <label for="ubicacion" class="form-label">
                    <i class="fas fa-map-marker-alt"></i>
                    Ubicación del Almacén
                    <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="ubicacion" 
                    name="ubicacion" 
                    value="<?php echo htmlspecialchars($ubicacion); ?>" 
                    placeholder="Ej: Av. Industrial 123, Lima..."
                    required
                    autocomplete="off"
                    maxlength="200"
                >
                <div class="field-hint">
                    <i class="fas fa-info-circle"></i>
                    Dirección completa o referencia de la ubicación del almacén
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit" id="btnRegistrar">
                    <i class="fas fa-save"></i>
                    Registrar Almacén
                </button>
                
                <a href="listar.php" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
            </div>
        </form>

        <div class="additional-actions">
            <div class="action-item">
                <a href="listar.php" class="action-link">
                    <i class="fas fa-list"></i>
                    <div>
                        <strong>Ver Lista de Almacenes</strong>
                        <small>Consultar todos los almacenes registrados</small>
                    </div>
                </a>
            </div>
            
            <?php if ($usuario_rol == 'admin'): ?>
            <div class="action-item">
                <a href="../usuarios/registrar.php" class="action-link">
                    <i class="fas fa-user-plus"></i>
                    <div>
                        <strong>Registrar Usuario</strong>
                        <small>Agregar nuevo usuario al sistema</small>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
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
    const formRegistrar = document.getElementById('formRegistrarAlmacen');
    
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
    
    // Validación y envío del formulario con confirmación
    if (formRegistrar) {
        formRegistrar.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nombre = document.getElementById('nombre').value.trim();
            const ubicacion = document.getElementById('ubicacion').value.trim();
            
            // Validaciones básicas
            if (!nombre || !ubicacion) {
                mostrarNotificacion('Todos los campos son obligatorios', 'error');
                return;
            }
            
            if (nombre.length < 3) {
                mostrarNotificacion('El nombre del almacén debe tener al menos 3 caracteres', 'error');
                return;
            }
            
            if (ubicacion.length < 5) {
                mostrarNotificacion('La ubicación debe tener al menos 5 caracteres', 'error');
                return;
            }
            
            // Confirmación antes de registrar
            const confirmado = await confirmarRegistroAlmacen(nombre, ubicacion);
            
            if (confirmado) {
                const btnSubmit = document.getElementById('btnRegistrar');
                const originalText = btnSubmit.innerHTML;
                
                // Mostrar estado de carga
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
                btnSubmit.disabled = true;
                
                // Enviar formulario
                this.submit();
            }
        });
    }
    
    // Validación en tiempo real
    const nombreInput = document.getElementById('nombre');
    const ubicacionInput = document.getElementById('ubicacion');
    
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            validarCampo(this, 3, 'El nombre debe tener al menos 3 caracteres');
        });
    }
    
    if (ubicacionInput) {
        ubicacionInput.addEventListener('input', function() {
            validarCampo(this, 5, 'La ubicación debe tener al menos 5 caracteres');
        });
    }
    
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
});

// Función para validar campos en tiempo real
function validarCampo(input, minLength, mensaje) {
    const value = input.value.trim();
    const isValid = value.length >= minLength;
    
    input.classList.toggle('invalid', !isValid && value.length > 0);
    input.classList.toggle('valid', isValid);
    
    // Remover mensaje de error previo
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Mostrar mensaje de error si es necesario
    if (!isValid && value.length > 0) {
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
        input.parentNode.appendChild(errorElement);
    }
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

// Función para limpiar formulario
function limpiarFormulario() {
    document.getElementById('formRegistrarAlmacen').reset();
    document.querySelectorAll('.field-error').forEach(error => error.remove());
    document.querySelectorAll('input').forEach(input => {
        input.classList.remove('valid', 'invalid');
    });
}

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + S para guardar
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('btnRegistrar').click();
    }
    
    // Esc para cancelar
    if (e.key === 'Escape') {
        window.location.href = 'listar.php';
    }
});

// Indicador visual para navegación por teclado
document.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        document.body.classList.add('keyboard-navigation');
    }
});

document.addEventListener('mousedown', function() {
    document.body.classList.remove('keyboard-navigation');
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

.field-error {
    color: var(--list-danger);
    font-size: 13px;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    animation: slideInDown 0.3s ease;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

input.valid {
    border-color: var(--list-success) !important;
    background: rgba(40, 167, 69, 0.05);
}

input.invalid {
    border-color: var(--list-danger) !important;
    background: rgba(220, 53, 69, 0.05);
}
</style>
</body>
</html>