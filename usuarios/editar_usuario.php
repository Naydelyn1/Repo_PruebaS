<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

// Evita secuestro de sesión
session_regenerate_id(true);

$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "Usuario";
$usuario_almacen_id = isset($_SESSION["almacen_id"]) ? $_SESSION["almacen_id"] : null;
$usuario_rol = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "usuario";

require_once "../config/database.php";
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

// MODIFICACIÓN: Manejar tanto GET como POST para el ID
$id = null;

// Primero intentar obtener de POST (cuando se envía el formulario)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_id"])) {
    $id = (int) $_POST["user_id"];
    
    // Procesar el formulario
    $nombre = trim($_POST["nombre"]);
    $apellidos = trim($_POST["apellidos"]);
    $dni = trim($_POST["dni"]);
    $correo = trim($_POST["correo"]);
    $rol = $_POST["rol"];
    $estado = $_POST["estado"];
    $almacen_id = $_POST["almacen_id"];

    // Validaciones
    $error = false;
    if (!preg_match("/^\d{8}$/", $dni)) {
        $_SESSION['mensaje_error'] = "El DNI debe tener exactamente 8 dígitos.";
        $error = true;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje_error'] = "Correo electrónico no válido.";
        $error = true;
    }

    if (!$error) {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, dni=?, correo=?, rol=?, estado=?, almacen_id=? WHERE id=?");
        $stmt->bind_param("ssssssii", $nombre, $apellidos, $dni, $correo, $rol, $estado, $almacen_id, $id);

        if ($stmt->execute()) {
            $_SESSION['mensaje_exito'] = "Usuario actualizado correctamente.";
            $stmt->close();
            header("Location: listar.php");
            exit();
        } else {
            $_SESSION['mensaje_error'] = "Error al actualizar usuario.";
        }
        $stmt->close();
    }
    
    // Si hay error, continuamos mostrando el formulario con el ID
}
// Si no es POST, intentar obtener de la sesión
else if (isset($_SESSION['edit_user_id'])) {
    $id = (int) $_SESSION['edit_user_id'];
    // Limpiar la sesión después de obtener el ID
    unset($_SESSION['edit_user_id']);
}
// Si no hay ID de ninguna forma, redirigir
else {
    $_SESSION['mensaje_error'] = "Acceso no válido.";
    header("Location: listar.php");
    exit();
}

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT nombre, apellidos, dni, correo, rol, estado, almacen_id, celular, direccion FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

if (!$usuario) {
    $_SESSION['mensaje_error'] = "Usuario no encontrado.";
    header("Location: listar.php");
    exit();
}

// Obtener lista de almacenes
$almacenes = [];
$stmt = $conn->prepare("SELECT id, nombre FROM almacenes");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $almacenes[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - GRUPO SEAL | Sistema de Gestión</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar información de usuario en el sistema de gestión de inventario GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Preconnect para optimizar carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- CSS consistente con dashboard -->
    <link rel="stylesheet" href="../assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/usuarios/registrar-usuario-consistent.css">
    
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
<main class="content" id="main-content" role="main">
    <header>
        <h1>Editar Usuario</h1>
    </header>
    
    <div class="register-container">
        <!-- Mostrar mensajes de sesión -->
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="mensaje exito" role="alert" style="display: none;">
                <?php echo $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="mensaje error" role="alert" style="display: none;">
                <?php echo $_SESSION['mensaje_error']; unset($_SESSION['mensaje_error']); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="editar_usuario.php" novalidate id="formEditarUsuario">
            <!-- CAMPO HIDDEN PARA SEGURIDAD -->
            <input type="hidden" name="user_id" value="<?= $id ?>">
            
            <div class="form-group">
                <input type="text" name="nombre" placeholder="Nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required aria-label="Nombre del usuario">
                <input type="text" name="apellidos" placeholder="Apellidos" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required aria-label="Apellidos del usuario">
            </div>
            <div class="form-group">
                <input type="text" name="dni" placeholder="DNI" maxlength="8" value="<?= htmlspecialchars($usuario['dni']) ?>" required pattern="\d{8}" title="El DNI debe contener 8 dígitos" aria-label="DNI del usuario">
                <input type="email" name="correo" placeholder="Correo Electrónico" value="<?= htmlspecialchars($usuario['correo']) ?>" required aria-label="Correo electrónico">
            </div>
            <div class="form-group">
                <select name="rol" required aria-label="Rol del usuario">
                    <option value="">Seleccione un rol</option>
                    <option value="admin" <?= $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    <option value="almacenero" <?= $usuario['rol'] == 'almacenero' ? 'selected' : ''; ?>>Almacenero</option>
                </select>
                <select name="almacen_id" aria-label="Almacén asignado">
                    <option value="">Seleccione un almacén</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?= $almacen["id"] ?>" <?= ($usuario['almacen_id'] == $almacen["id"]) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($almacen["nombre"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="estado" aria-label="Estado del usuario">
                    <option value="activo" <?= $usuario['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?= $usuario['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            <button type="button" class="btn" onclick="manejarGuardarCambios()">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </form>
        
        <!-- Enlaces de navegación -->
        <div style="text-align: center; margin-top: 30px; padding-top: 25px; border-top: 2px solid #e9ecef;">
            <a href="listar.php" class="btn" style="background: #c8c9ca; color: #0a253c; text-decoration: none; margin-right: 15px;">
                <i class="fas fa-arrow-left"></i> Volver a la Lista
            </a>
            <a href="registrar.php" class="btn" style="background: #17a2b8; color: white; text-decoration: none;">
                <i class="fas fa-user-plus"></i> Registrar Nuevo
            </a>
        </div>
    </div>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<!-- JavaScript optimizado -->
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

    // Auto-expandir el submenú de usuarios
    setTimeout(() => {
        const usuariosSubmenu = document.querySelector('.submenu-container');
        if (usuariosSubmenu) {
            const link = usuariosSubmenu.querySelector('a');
            const submenu = usuariosSubmenu.querySelector('.submenu');
            const chevron = usuariosSubmenu.querySelector('.fa-chevron-down');
            
            if (link && submenu) {
                submenu.classList.add('activo');
                if (chevron) {
                    chevron.style.transform = 'rotate(180deg)';
                }
                link.setAttribute('aria-expanded', 'true');
            }
        }
    }, 100);

    // Mostrar mensajes de sesión
    const mensajes = document.querySelectorAll('.mensaje');
    mensajes.forEach(mensaje => {
        const texto = mensaje.textContent.trim();
        if (texto) {
            const tipo = mensaje.classList.contains('exito') ? 'exito' : 'error';
            setTimeout(() => {
                mostrarNotificacion(texto, tipo);
            }, 500);
        }
    });

    // NO mostrar notificación de bienvenida automáticamente
    // Solo mostrar cuando el usuario realice alguna acción
    
    // Validación de formularios
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            clearValidationError(this);
        });
    });
    
    // Funciones de validación
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (field.name === 'dni') {
            if (value && !/^\d{8}$/.test(value)) {
                isValid = false;
                errorMessage = 'El DNI debe tener exactamente 8 dígitos';
            }
        } else if (field.name === 'correo') {
            if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                isValid = false;
                errorMessage = 'Formato de correo no válido';
            }
        }
        
        if (!isValid) {
            showValidationError(field, errorMessage);
        } else {
            clearValidationError(field);
        }
        
        return isValid;
    }
    
    function showValidationError(field, message) {
        field.classList.add('error');
        
        const existingError = field.parentNode.querySelector('.validation-error');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    function clearValidationError(field) {
        field.classList.remove('error');
        field.classList.add('success');
        const errorDiv = field.parentNode.querySelector('.validation-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
});

// Funciones de confirmación
async function manejarGuardarCambios() {
    const form = document.getElementById('formEditarUsuario');
    
    // Validar formulario primero
    let isFormValid = true;
    const formInputs = form.querySelectorAll('input[required], select[required]');
    
    formInputs.forEach(input => {
        if (!input.value.trim()) {
            isFormValid = false;
            input.classList.add('error');
        }
    });
    
    if (!isFormValid) {
        mostrarNotificacion('Por favor, completa todos los campos requeridos', 'error');
        return;
    }

    // Obtener datos del formulario
    const formData = new FormData(form);
    const nombreCompleto = `${formData.get('nombre')} ${formData.get('apellidos')}`;
    
    // Mostrar confirmación
    const confirmado = await confirmarEdicionUsuario(nombreCompleto);
    
    if (confirmado) {
        // Mostrar indicador de carga
        const submitBtn = form.querySelector('button');
        const originalHTML = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        submitBtn.disabled = true;
        
        // Enviar formulario
        form.submit();
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

// Prevenir envío accidental del formulario
document.getElementById('formEditarUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    manejarGuardarCambios();
});

// Detectar cambios sin guardar
let formChanged = false;
const form = document.getElementById('formEditarUsuario');
const inputs = form.querySelectorAll('input, select');

inputs.forEach(input => {
    input.addEventListener('input', () => {
        formChanged = true;
    });
});

// ELIMINADO: No mostrar advertencia del navegador al salir
// Solo se usará la confirmación personalizada cuando sea necesario

// Remover advertencia cuando se guarde
form.addEventListener('submit', () => {
    formChanged = false;
});

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