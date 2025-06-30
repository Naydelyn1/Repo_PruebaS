<?php
session_start();
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

// Verificar que el usuario sea administrador
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tiene permisos para editar almacenes.";
    header("Location: listar.php");
    exit();
}

// MODIFICACIÓN DE SEGURIDAD: Obtener ID del almacén usando sesión
if (isset($_SESSION['edit_almacen_id'])) {
    $almacen_id = (int) $_SESSION['edit_almacen_id'];
    // Limpiar la sesión después de obtener el ID
    unset($_SESSION['edit_almacen_id']);
} else {
    $_SESSION['error'] = "Acceso no válido.";
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
    $_SESSION['error'] = "Almacén no encontrado.";
    header("Location: listar.php");
    exit();
}

$mensaje = "";
$error = "";
$nombre = $almacen['nombre'];
$ubicacion = $almacen['ubicacion'];

// MODIFICACIÓN DE SEGURIDAD: Procesar formulario con ID en campo hidden
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Reobtener el ID del formulario hidden
    $almacen_id = (int) $_POST["almacen_id"];
    
    if (!empty($_POST["nombre"]) && !empty($_POST["ubicacion"])) {
        $nuevo_nombre = trim($_POST["nombre"]);
        $nueva_ubicacion = trim($_POST["ubicacion"]);

        // Verificar si el nuevo nombre ya existe (excepto el actual)
        $sql_check = "SELECT id FROM almacenes WHERE nombre = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $nuevo_nombre, $almacen_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "⚠️ Ya existe un almacén con ese nombre.";
        } else {
            // Actualizar el almacén
            $sql_update = "UPDATE almacenes SET nombre = ?, ubicacion = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                $stmt_update->bind_param("ssi", $nuevo_nombre, $nueva_ubicacion, $almacen_id);
                if ($stmt_update->execute()) {
                    // Registrar la acción en logs (opcional)
                    $usuario_id = $_SESSION["user_id"];
                    $sql_log = "INSERT INTO logs_actividad (usuario_id, accion, detalle, fecha_accion) 
                                VALUES (?, 'EDITAR_ALMACEN', ?, NOW())";
                    $stmt_log = $conn->prepare($sql_log);
                    $detalle = "Editó el almacén ID {$almacen_id}: '{$nombre}' -> '{$nuevo_nombre}'";
                    $stmt_log->bind_param("is", $usuario_id, $detalle);
                    $stmt_log->execute();
                    $stmt_log->close();
                    
                    $_SESSION['success'] = "✅ Almacén actualizado con éxito.";
                    // Redirigir de forma segura usando sesión
                    $_SESSION['view_almacen_id'] = $almacen_id;
                    header("Location: ver-almacen.php");
                    exit();
                } else {
                    $error = "❌ Error al actualizar el almacén: " . $stmt_update->error;
                }
                $stmt_update->close();
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
    <title>Editar Almacén - <?php echo htmlspecialchars($almacen['nombre']); ?> - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Editar información del almacén <?php echo htmlspecialchars($almacen['nombre']); ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para editar almacenes -->
    <link rel="stylesheet" href="../assets/css/usuarios/listar-usuarios.css">
    <link rel="stylesheet" href="../assets/css/almacen/almacenes-editar.css">
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
    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <header class="page-header">
        <h1>Editar Almacén</h1>
        <p class="page-description">
            Modifica la información del almacén "<?php echo htmlspecialchars($almacen['nombre']); ?>"
        </p>
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <button onclick="volverVerAlmacen()" class="breadcrumb-btn"><?php echo htmlspecialchars($almacen['nombre']); ?></button>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Editar</span>
        </nav>
    </header>

    <div class="edit-container">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-edit"></i>
            </div>
            <h2>Editar Información del Almacén</h2>
            <p>Actualice los campos que desea modificar</p>
        </div>

        <form id="formEditarAlmacen" action="" method="POST" autocomplete="off">
            <!-- CAMPO HIDDEN PARA SEGURIDAD -->
            <input type="hidden" name="almacen_id" value="<?= $almacen_id ?>">
            
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
                <button type="submit" class="btn-submit" id="btnGuardar">
                    <i class="fas fa-save"></i>
                    Guardar Cambios
                </button>
                
                <button type="button" onclick="volverVerAlmacen()" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
            </div>
        </form>

        <div class="additional-actions">
            <div class="action-item">
                <button onclick="volverVerAlmacen()" class="action-link">
                    <i class="fas fa-eye"></i>
                    <div>
                        <strong>Ver Detalle del Almacén</strong>
                        <small>Volver a la vista detallada del almacén</small>
                    </div>
                </button>
            </div>
            
            <div class="action-item">
                <a href="listar.php" class="action-link">
                    <i class="fas fa-list"></i>
                    <div>
                        <strong>Lista de Almacenes</strong>
                        <small>Ver todos los almacenes registrados</small>
                    </div>
                </a>
            </div>
            
            <div class="action-item">
                <a href="#" onclick="eliminarAlmacen(<?php echo $almacen_id; ?>, '<?php echo htmlspecialchars($almacen['nombre']); ?>')" class="action-link danger">
                    <i class="fas fa-trash"></i>
                    <div>
                        <strong>Eliminar Almacén</strong>
                        <small>Eliminar permanentemente este almacén</small>
                    </div>
                </a>
            </div>
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
    const formEditar = document.getElementById('formEditarAlmacen');
    
    // Valores originales para detectar cambios
    const valoresOriginales = {
        nombre: document.getElementById('nombre').value,
        ubicacion: document.getElementById('ubicacion').value
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
    
    // Detectar cambios en el formulario
    function detectarCambios() {
        const nombre = document.getElementById('nombre').value;
        const ubicacion = document.getElementById('ubicacion').value;
        
        return nombre !== valoresOriginales.nombre || ubicacion !== valoresOriginales.ubicacion;
    }
    
    // Validación y envío del formulario con confirmación
    if (formEditar) {
        formEditar.addEventListener('submit', async function(e) {
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
            
            // Verificar si hay cambios
            if (!detectarCambios()) {
                mostrarNotificacion('No se han realizado cambios', 'warning');
                return;
            }
            
            // Confirmación antes de guardar
            const confirmado = await confirmarEdicionUsuario('<?php echo htmlspecialchars($almacen['nombre']); ?>');
            
            if (confirmado) {
                const btnSubmit = document.getElementById('btnGuardar');
                const originalText = btnSubmit.innerHTML;
                
                // Mostrar estado de carga
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
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
    
    // Indicador visual de cambios
    [nombreInput, ubicacionInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                const hasChanges = detectarCambios();
                const btnGuardar = document.getElementById('btnGuardar');
                
                if (hasChanges) {
                    btnGuardar.classList.add('has-changes');
                    document.title = '* Editar Almacén - COMSEPROA';
                } else {
                    btnGuardar.classList.remove('has-changes');
                    document.title = 'Editar Almacén - COMSEPROA';
                }
            });
        }
    });
    
    // Advertencia al salir sin guardar
    window.addEventListener('beforeunload', function(e) {
        if (detectarCambios()) {
            e.preventDefault();
            e.returnValue = '';
        }
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

// FUNCIÓN SEGURA PARA VOLVER A VER ALMACÉN
function volverVerAlmacen() {
    // Crear formulario oculto para navegación segura
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ver_redirect.php';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'view_almacen_id';
    input.value = '<?php echo $almacen_id; ?>';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
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

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + S para guardar
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('btnGuardar').click();
    }
    
    // Esc para cancelar
    if (e.key === 'Escape') {
        volverVerAlmacen();
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

.btn-submit.has-changes {
    background: linear-gradient(135deg, var(--list-warning), #e0a800);
    animation: pulseWarning 2s infinite;
}

@keyframes pulseWarning {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
    }
    50% {
        transform: scale(1.02);
        box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
    }
}

.action-link.danger {
    border-color: var(--list-danger);
}

.action-link.danger:hover {
    border-color: var(--list-danger);
    background: rgba(220, 53, 69, 0.05);
}

.action-link.danger i {
    color: var(--list-danger);
}

.breadcrumb-btn {
    background: none;
    border: none;
    color: #007bff;
    text-decoration: underline;
    cursor: pointer;
    font: inherit;
}

.breadcrumb-btn:hover {
    color: #0056b3;
}
</style>
</body>
</html>