<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);

// Obtener el rol del usuario para control de acceso
$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Solo administradores pueden registrar productos
if ($usuario_rol !== 'admin') {
    $_SESSION['error'] = "No tienes permisos para registrar productos.";
    header("Location: ../dashboard.php");
    exit();
}

require_once "../config/database.php";

// Obtener parámetros de la URL (opcionales)
$almacen_preseleccionado = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$categoria_preseleccionada = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Determinar la URL de retorno basada en el contexto
function determinarUrlRetorno($almacen_id, $categoria_id) {
    if ($almacen_id && $categoria_id) {
        // Si viene de una categoría específica del almacén
        return "../productos/listar.php?almacen_id={$almacen_id}&categoria_id={$categoria_id}";
    } elseif ($almacen_id) {
        // Si viene del almacén general
        return "../almacenes/ver-almacen.php?id={$almacen_id}";
    } else {
        // Lista general de productos
        return "listar.php";
    }
}

$url_retorno = determinarUrlRetorno($almacen_preseleccionado, $categoria_preseleccionada);

$mensaje = "";
$error = "";

// Obtener lista de almacenes
$sql_almacenes = "SELECT id, nombre FROM almacenes ORDER BY nombre";
$result_almacenes = $conn->query($sql_almacenes);

// Obtener lista de categorías
$sql_categorias = "SELECT id, nombre FROM categorias ORDER BY nombre";
$result_categorias = $conn->query($sql_categorias);

// Manejo del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"] ?? '');
    $modelo = trim($_POST["modelo"] ?? '');
    $color = trim($_POST["color"] ?? '');
    $talla_dimensiones = trim($_POST["talla_dimensiones"] ?? '');
    $cantidad = isset($_POST["cantidad"]) ? (int)$_POST["cantidad"] : 0;
    $unidad_medida = trim($_POST["unidad_medida"] ?? '');
    $estado = trim($_POST["estado"] ?? '');
    $observaciones = trim($_POST["observaciones"] ?? '');
    $almacen_id = isset($_POST["almacen_id"]) ? (int)$_POST["almacen_id"] : 0;
    $categoria_id = isset($_POST["categoria_id"]) ? (int)$_POST["categoria_id"] : 0;

    // Validaciones
    if (empty($nombre)) {
        $error = "⚠️ El nombre del producto es obligatorio.";
    } elseif ($cantidad <= 0) {
        $error = "⚠️ La cantidad debe ser mayor a 0.";
    } elseif (empty($unidad_medida)) {
        $error = "⚠️ La unidad de medida es obligatoria.";
    } elseif (empty($estado)) {
        $error = "⚠️ El estado del producto es obligatorio.";
    } elseif ($almacen_id <= 0) {
        $error = "⚠️ Debe seleccionar un almacén válido.";
    } elseif ($categoria_id <= 0) {
        $error = "⚠️ Debe seleccionar una categoría válida.";
    } else {
        // Verificar si el producto ya existe en el mismo almacén
        $sql_check = "SELECT id FROM productos WHERE nombre = ? AND almacen_id = ? AND categoria_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("sii", $nombre, $almacen_id, $categoria_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "⚠️ Ya existe un producto con ese nombre en este almacén y categoría.";
        } else {
            // Insertar el nuevo producto
            $sql = "INSERT INTO productos (nombre, modelo, color, talla_dimensiones, cantidad, unidad_medida, estado, observaciones, almacen_id, categoria_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ssssisssii", $nombre, $modelo, $color, $talla_dimensiones, $cantidad, $unidad_medida, $estado, $observaciones, $almacen_id, $categoria_id);
                
                if ($stmt->execute()) {
                    $producto_id = $conn->insert_id;
                    
                    // Registrar el movimiento inicial
                    $usuario_id = $_SESSION["user_id"];
                    $sql_movimiento = "INSERT INTO movimientos (producto_id, almacen_origen, cantidad, tipo, usuario_id, estado) 
                                       VALUES (?, ?, ?, 'entrada', ?, 'completado')";
                    $stmt_movimiento = $conn->prepare($sql_movimiento);
                    $stmt_movimiento->bind_param("iiii", $producto_id, $almacen_id, $cantidad, $usuario_id);
                    $stmt_movimiento->execute();
                    $stmt_movimiento->close();
                    
                    $_SESSION['success'] = "✅ Producto registrado con éxito.";
                    
                    // Redirigir según el contexto original
                    $redirect_url = determinarUrlRetorno($almacen_id, $categoria_id);
                    header("Location: " . $redirect_url);
                    exit();
                } else {
                    $error = "❌ Error al registrar el producto: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "❌ Error en la consulta SQL: " . $conn->error;
            }
        }
        $stmt_check->close();
    }
}

// Contar solicitudes pendientes para el badge
$sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
$result_pendientes = $conn->query($sql_pendientes);
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
    <title>Registrar Producto - GRUPO SEAL</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Registrar nuevo producto en el sistema GRUPO SEAL">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS específico para registrar productos -->
    <link rel="stylesheet" href="../assets/css/productos/productos-registrar.css">
</head>
<body data-almacen-id="<?php echo $almacen_preseleccionado; ?>" data-categoria-id="<?php echo $categoria_preseleccionada; ?>">

<!-- Botón de hamburguesa para dispositivos móviles -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú de navegación">
    <i class="fas fa-bars"></i>
</button>

<!-- ===== SIDEBAR Y NAVEGACIÓN UNIFICADO ===== -->
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
    <?php if (!empty($mensaje)): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Header de la página -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    Registrar Nuevo Producto
                </h1>
                <p class="page-description">
                    Complete la información del producto que desea agregar al inventario
                </p>
            </div>
            
            <div class="header-actions">
                <a href="listar.php" class="btn-header btn-secondary">
                    <i class="fas fa-list"></i>
                    <span>Ver Productos</span>
                </a>
                
                <!-- Botón dinámico de retorno -->
                <a href="javascript:void(0)" onclick="navegarRetorno()" class="btn-header btn-secondary" id="btnRetorno">
                    <i class="fas fa-arrow-left"></i>
                    <span id="textoRetorno">Volver</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Breadcrumb dinámico -->
    <nav class="breadcrumb" aria-label="Ruta de navegación" id="breadcrumbContainer">
        <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
        <span><i class="fas fa-chevron-right"></i></span>
        <!-- Se completará dinámicamente -->
        <span class="current">Registrar Producto</span>
    </nav>

    <!-- Formulario de registro -->
    <section class="form-section">
        <div class="form-container">
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h2>Información del Producto</h2>
                <p>Complete todos los campos marcados con (*) para registrar el producto</p>
            </div>

            <form id="formRegistrarProducto" action="" method="POST" autocomplete="off">
                <div class="form-grid">
                    <!-- Información básica -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-info-circle"></i> Información Básica</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre" class="form-label">
                            <i class="fas fa-tag"></i>
                            Nombre del Producto *
                        </label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            placeholder="Ej: Casco de seguridad, Uniforme operativo..."
                            required
                            autocomplete="off"
                            maxlength="100"
                        >
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Ingrese un nombre descriptivo y único para el producto
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modelo" class="form-label">
                            <i class="fas fa-barcode"></i>
                            Modelo
                        </label>
                        <input 
                            type="text" 
                            id="modelo" 
                            name="modelo" 
                            placeholder="Ej: XL-500, Pro-2024..."
                            autocomplete="off"
                            maxlength="50"
                        >
                    </div>

                    <div class="form-group">
                        <label for="color" class="form-label">
                            <i class="fas fa-palette"></i>
                            Color
                        </label>
                        <input 
                            type="text" 
                            id="color" 
                            name="color" 
                            placeholder="Ej: Negro, Azul marino, Verde..."
                            autocomplete="off"
                            maxlength="30"
                        >
                    </div>

                    <div class="form-group">
                        <label for="talla_dimensiones" class="form-label">
                            <i class="fas fa-ruler"></i>
                            Talla / Dimensiones
                        </label>
                        <input 
                            type="text" 
                            id="talla_dimensiones" 
                            name="talla_dimensiones" 
                            placeholder="Ej: XL, 42, 30x25x10 cm..."
                            autocomplete="off"
                            maxlength="50"
                        >
                    </div>

                    <!-- Ubicación -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-map-marker-alt"></i> Ubicación y Clasificación</h3>
                    </div>

                    <div class="form-group">
                        <label for="almacen_id" class="form-label">
                            <i class="fas fa-warehouse"></i>
                            Almacén de Destino *
                        </label>
                        <select id="almacen_id" name="almacen_id" required class="form-select">
                            <option value="">Seleccione un almacén</option>
                            <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                                <option value="<?php echo $almacen['id']; ?>" 
                                    <?php echo ($almacen_preseleccionado == $almacen['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($almacen['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Seleccione el almacén donde se guardará el producto
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id" class="form-label">
                            <i class="fas fa-layer-group"></i>
                            Categoría *
                        </label>
                        <select id="categoria_id" name="categoria_id" required class="form-select">
                            <option value="">Seleccione una categoría</option>
                            <?php while ($categoria = $result_categorias->fetch_assoc()): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo ($categoria_preseleccionada == $categoria['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Clasifique el producto según su tipo o uso
                        </div>
                    </div>

                    <!-- Cantidad y estado -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-cubes"></i> Cantidad y Estado</h3>
                    </div>

                    <div class="form-group">
                        <label for="cantidad" class="form-label">
                            <i class="fas fa-sort-numeric-up"></i>
                            Cantidad Inicial *
                        </label>
                        <div class="quantity-input">
                            <button type="button" class="qty-btn minus" onclick="adjustQuantity(-1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input 
                                type="number" 
                                id="cantidad" 
                                name="cantidad" 
                                min="1" 
                                value="1"
                                required
                                class="qty-input"
                            >
                            <button type="button" class="qty-btn plus" onclick="adjustQuantity(1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i>
                            Cantidad inicial que se agregará al inventario
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="unidad_medida" class="form-label">
                            <i class="fas fa-balance-scale"></i>
                            Unidad de Medida *
                        </label>
                        <select id="unidad_medida" name="unidad_medida" required class="form-select">
                            <option value="">Seleccione una unidad</option>
                            <option value="Unidad">Unidad</option>
                            <option value="Par">Par</option>
                            <option value="Set">Set</option>
                            <option value="Caja">Caja</option>
                            <option value="Paquete">Paquete</option>
                            <option value="Kilogramo">Kilogramo</option>
                            <option value="Metro">Metro</option>
                            <option value="Litro">Litro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado" class="form-label">
                            <i class="fas fa-check-circle"></i>
                            Estado del Producto *
                        </label>
                        <select id="estado" name="estado" required class="form-select">
                            <option value="">Seleccione el estado</option>
                            <option value="Nuevo">Nuevo</option>
                            <option value="Usado">Usado</option>
                            <option value="Renovado">Renovado</option>
                            <option value="Dañado">Dañado</option>
                        </select>
                    </div>

                    <!-- Observaciones -->
                    <div class="form-section-title">
                        <h3><i class="fas fa-comment-alt"></i> Información Adicional</h3>
                    </div>

                    <div class="form-group full-width">
                        <label for="observaciones" class="form-label">
                            <i class="fas fa-sticky-note"></i>
                            Observaciones
                        </label>
                        <textarea 
                            id="observaciones" 
                            name="observaciones" 
                            rows="4"
                            placeholder="Información adicional sobre el producto (características especiales, instrucciones, etc.)"
                            maxlength="500"
                            class="form-textarea"
                        ></textarea>
                        <div class="character-counter">
                            <span id="charCount">0</span>/500 caracteres
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btnRegistrar">
                        <i class="fas fa-save"></i>
                        Registrar Producto
                    </button>
                    
                    <button type="button" class="btn-reset" onclick="limpiarFormulario()">
                        <i class="fas fa-undo"></i>
                        Limpiar Formulario
                    </button>
                    
                    <button type="button" onclick="navegarRetorno()" class="btn-cancel">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <!-- Panel de ayuda -->
        <aside class="help-panel">
            <div class="help-header">
                <h3>
                    <i class="fas fa-question-circle"></i>
                    Ayuda para Registro
                </h3>
            </div>
            <div class="help-content">
                <div class="help-item">
                    <h4><i class="fas fa-lightbulb"></i> Consejos</h4>
                    <ul>
                        <li>Use nombres descriptivos y únicos</li>
                        <li>Incluya modelo y color cuando sea relevante</li>
                        <li>Verifique la categoría antes de guardar</li>
                        <li>Agregue observaciones para características especiales</li>
                    </ul>
                </div>
                
                <div class="help-item">
                    <h4><i class="fas fa-exclamation-triangle"></i> Campos Obligatorios</h4>
                    <ul>
                        <li>Nombre del producto</li>
                        <li>Almacén de destino</li>
                        <li>Categoría</li>
                        <li>Cantidad inicial</li>
                        <li>Unidad de medida</li>
                        <li>Estado del producto</li>
                    </ul>
                </div>
                
                <div class="help-item">
                    <h4><i class="fas fa-keyboard"></i> Atajos de Teclado</h4>
                    <ul>
                        <li><kbd>Ctrl + S</kbd> - Guardar producto</li>
                        <li><kbd>Ctrl + R</kbd> - Limpiar formulario</li>
                        <li><kbd>Esc</kbd> - Cancelar</li>
                    </ul>
                </div>
            </div>
        </aside>
    </section>
</main>

<!-- Container for dynamic notifications -->
<div id="notificaciones-container" role="alert" aria-live="polite"></div>

<script>
// Variables para el contexto de navegación
const ALMACEN_PRESELECCIONADO = <?php echo $almacen_preseleccionado ? $almacen_preseleccionado : 'null'; ?>;
const CATEGORIA_PRESELECCIONADA = <?php echo $categoria_preseleccionada ? $categoria_preseleccionada : 'null'; ?>;
const URL_RETORNO = '<?php echo $url_retorno; ?>';

// Función para determinar el contexto y navegar correctamente
function navegarRetorno() {
    // Verificar si hay contexto de sessionStorage (para navegación desde almacén)
    const almacenContext = sessionStorage.getItem('almacen_context');
    
    if (almacenContext) {
        const context = JSON.parse(almacenContext);
        // Si el almacén coincide con el preseleccionado, volver al almacén
        if (context.almacen_id === ALMACEN_PRESELECCIONADO) {
            // Crear formulario para navegar de forma segura al almacén
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../almacenes/ver_redirect.php';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'view_almacen_id';
            input.value = context.almacen_id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            return;
        }
    }
    
    // Usar la URL de retorno determinada por PHP
    window.location.href = URL_RETORNO;
}

// Función para configurar la interfaz basada en el contexto
function configurarInterfazContexto() {
    const btnRetorno = document.getElementById('btnRetorno');
    const textoRetorno = document.getElementById('textoRetorno');
    const breadcrumbContainer = document.getElementById('breadcrumbContainer');
    
    // Configurar texto y breadcrumb según el contexto
    if (ALMACEN_PRESELECCIONADO && CATEGORIA_PRESELECCIONADA) {
        textoRetorno.textContent = 'Volver a Categoría';
        
        // Breadcrumb para categoría específica
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarRetorno()">Almacén</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarRetorno()">Categoría</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Registrar Producto</span>
        `;
    } else if (ALMACEN_PRESELECCIONADO) {
        textoRetorno.textContent = 'Volver al Almacén';
        
        // Breadcrumb para almacén
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="../almacenes/listar.php">Almacenes</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="javascript:void(0)" onclick="navegarRetorno()">Almacén</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Registrar Producto</span>
        `;
    } else {
        textoRetorno.textContent = 'Volver a Productos';
        
        // Breadcrumb general
        breadcrumbContainer.innerHTML = `
            <a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <a href="listar.php">Productos</a>
            <span><i class="fas fa-chevron-right"></i></span>
            <span class="current">Registrar Producto</span>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Configurar la interfaz según el contexto
    configurarInterfazContexto();
    
    // ⭐ FUNCIONALIDAD EXISTENTE DEL FORMULARIO
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
        
        // ⭐ ATAJOS DE TECLADO COMO EN LA AYUDA
        if (e.ctrlKey) {
            switch(e.key) {
                case 's':
                    e.preventDefault();
                    document.getElementById('btnRegistrar').click();
                    break;
                case 'r':
                    e.preventDefault();
                    limpiarFormulario();
                    break;
            }
        }
        
        if (e.key === 'Escape' && !sidebar.classList.contains('active')) {
            // Confirmar antes de salir
            if (confirm('¿Desea cancelar el registro del producto?')) {
                navegarRetorno();
            }
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
    
    // ⭐ VALIDACIÓN DEL FORMULARIO
    const form = document.getElementById('formRegistrarProducto');
    const submitBtn = document.getElementById('btnRegistrar');
    
    if (form) {
        const requiredInputs = form.querySelectorAll('input[required], select[required]');
        
        function validateForm() {
            let isValid = true;
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    input.closest('.form-group').classList.add('error');
                } else {
                    input.classList.remove('error');
                    input.classList.add('success');
                    input.closest('.form-group').classList.remove('error');
                    input.closest('.form-group').classList.add('success');
                }
            });
            
            if (submitBtn) {
                submitBtn.disabled = !isValid;
                if (isValid) {
                    submitBtn.classList.add('ready');
                } else {
                    submitBtn.classList.remove('ready');
                }
            }
            
            return isValid;
        }
        
        // Validar en tiempo real
        requiredInputs.forEach(input => {
            input.addEventListener('blur', validateForm);
            input.addEventListener('input', validateForm);
        });
        
        // Validar al enviar
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                mostrarNotificacion('Por favor, complete todos los campos obligatorios.', 'error');
                
                // Enfocar el primer campo con error
                const firstError = form.querySelector('.error input, .error select');
                if (firstError) {
                    firstError.focus();
                }
            } else {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
            }
        });
        
        // Validación inicial
        setTimeout(validateForm, 100);
    }
    
    // ⭐ CONTADOR DE CARACTERES PARA OBSERVACIONES
    const observaciones = document.getElementById('observaciones');
    const charCount = document.getElementById('charCount');
    
    if (observaciones && charCount) {
        observaciones.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            if (count > 450) {
                charCount.parentElement.classList.add('warning');
            } else {
                charCount.parentElement.classList.remove('warning');
            }
        });
    }
    
    // Manejar navegación del navegador (botón atrás)
    window.addEventListener('popstate', function(event) {
        // Si el usuario presiona atrás, llevarlo al contexto correcto
        if (event.state && event.state.almacen_id) {
            navegarRetorno();
        }
    });
});

// ⭐ FUNCIÓN PARA AJUSTAR CANTIDAD
function adjustQuantity(increment) {
    const cantidadInput = document.getElementById('cantidad');
    if (cantidadInput) {
        let currentValue = parseInt(cantidadInput.value) || 1;
        let newValue = currentValue + increment;
        
        if (newValue < 1) newValue = 1;
        if (newValue > 99999) newValue = 99999;
        
        cantidadInput.value = newValue;
        
        // Trigger validation
        cantidadInput.dispatchEvent(new Event('input'));
    }
}

// ⭐ FUNCIÓN PARA LIMPIAR FORMULARIO
function limpiarFormulario() {
    if (confirm('¿Está seguro de que desea limpiar todos los campos del formulario?')) {
        const form = document.getElementById('formRegistrarProducto');
        if (form) {
            form.reset();
            
            // Limpiar clases de validación
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.classList.remove('error', 'success', 'modified');
                const formGroup = input.closest('.form-group');
                if (formGroup) {
                    formGroup.classList.remove('error', 'success');
                }
            });
            
            // Resetear contador de caracteres
            const charCount = document.getElementById('charCount');
            if (charCount) {
                charCount.textContent = '0';
                charCount.parentElement.classList.remove('warning');
            }
            
            // Restaurar valores preseleccionados
            if (ALMACEN_PRESELECCIONADO) {
                const almacenSelect = document.getElementById('almacen_id');
                if (almacenSelect) {
                    almacenSelect.value = ALMACEN_PRESELECCIONADO;
                }
            }
            
            if (CATEGORIA_PRESELECCIONADA) {
                const categoriaSelect = document.getElementById('categoria_id');
                if (categoriaSelect) {
                    categoriaSelect.value = CATEGORIA_PRESELECCIONADA;
                }
            }
            
            // Enfocar el primer campo
            const primerCampo = form.querySelector('input[type="text"]');
            if (primerCampo) {
                primerCampo.focus();
            }
            
            mostrarNotificacion('Formulario limpiado correctamente', 'info');
        }
    }
}

// ⭐ FUNCIÓN PARA MOSTRAR NOTIFICACIONES
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
    const container = document.getElementById('notificaciones-container');
    if (!container) return;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        'exito': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle', 
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    notificacion.innerHTML = `
        <i class="${iconos[tipo] || iconos['info']}"></i>
        <span>${mensaje}</span>
        <button class="cerrar" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    // Auto-remover después de la duración especificada
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }
}

// ⭐ FUNCIÓN PARA CERRAR SESIÓN
async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Está seguro de que desea cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

// ⭐ MOSTRAR NOTIFICACIONES DE SESIÓN PHP
<?php if (isset($_SESSION['success'])): ?>
mostrarNotificacion('<?php echo $_SESSION['success']; ?>', 'exito');
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
mostrarNotificacion('<?php echo $_SESSION['error']; ?>', 'error');
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

// Manejo de errores globales
window.addEventListener('error', function(e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Se ha producido un error. Por favor, recarga la página.', 'error');
});
</script>
</body>
</html>