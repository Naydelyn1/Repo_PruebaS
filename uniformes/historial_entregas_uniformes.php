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

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection configuration
require_once "../config/database.php";

// Consultar almacenes
if ($usuario_rol == 'admin') {
    $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes ORDER BY id DESC";
    $result_almacenes = $conn->query($sql_almacenes);
} else {
    // Si no es admin, mostrar solo el almacén asignado
    if ($usuario_almacen_id) {
        $sql_almacenes = "SELECT id, nombre, ubicacion FROM almacenes WHERE id = ?";
        $stmt_almacenes = $conn->prepare($sql_almacenes);
        $stmt_almacenes->bind_param("i", $usuario_almacen_id);
        $stmt_almacenes->execute();
        $result_almacenes = $stmt_almacenes->get_result();
    } else {
        $result_almacenes = false;
    }
}

// Variable para almacenar entregas
$entregas = [];
$error_mensaje = '';

// Configuración de paginación
$registros_por_pagina = 10; // Cantidad de registros por página
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Función para obtener entregas por almacén con paginación
function obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros = [], $limite = null, $offset = null) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            eu.cantidad,
            a.nombre as almacen_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];

    // Agregar filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
    }

    // Filtros de fecha
    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND eu.fecha_entrega >= ?';
        $params[] = $filtros['fecha_inicio'];
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND eu.fecha_entrega <= ?';
        $params[] = $filtros['fecha_fin'];
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC';

    // Agregar límite y offset para paginación
    if ($limite !== null && $offset !== null) {
        $query_with_limit = $query . ' LIMIT ? OFFSET ?';
        $params_with_limit = $params;
        $params_with_limit[] = $limite;
        $params_with_limit[] = $offset;
        
        $stmt = $conn->prepare($query_with_limit);
        $types = str_repeat('s', count($params)) . 'ii'; // Agregar dos enteros para LIMIT y OFFSET
        $stmt->bind_param($types, ...$params_with_limit);
    } else {
        $stmt = $conn->prepare($query);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $entregasAgrupadas = [];
    $ids_procesados = []; // Para evitar duplicados en la paginación
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['fecha_entrega'] . '|' . $row['nombre_destinatario'] . '|' . $row['dni_destinatario'] . '|' . $row['almacen_nombre'];
        
        if (!isset($entregasAgrupadas[$key])) {
            $entregasAgrupadas[$key] = [
                'id' => $row['id'],
                'fecha_entrega' => $row['fecha_entrega'],
                'nombre_destinatario' => $row['nombre_destinatario'],
                'dni_destinatario' => $row['dni_destinatario'],
                'almacen_nombre' => $row['almacen_nombre'],
                'productos' => []
            ];
        }
        
        $productoExistente = false;
        foreach ($entregasAgrupadas[$key]['productos'] as &$producto) {
            if ($producto['nombre'] === $row['producto_nombre']) {
                $producto['cantidad'] += $row['cantidad'];
                $productoExistente = true;
                break;
            }
        }
        
        if (!$productoExistente) {
            $entregasAgrupadas[$key]['productos'][] = [
                'nombre' => $row['producto_nombre'],
                'cantidad' => $row['cantidad']
            ];
        }
    }

    return array_values($entregasAgrupadas);
}

// Función para contar el total de entregas (para la paginación)
function contarEntregasPorAlmacen($conn, $almacen_id, $filtros = []) {
    // Primero obtenemos todas las entregas para poder agruparlas correctamente
    $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id, $filtros);
    return count($entregas);
}

// Preparar filtros
$filtros = [];
if (isset($_GET['dni']) || isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['almacen_id'])) {
    $filtros = [
        'dni' => $_GET['dni'] ?? '',
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? ''
    ];
    
    // Si es admin y se seleccionó un almacén específico
    $almacen_id_seleccionado = isset($_GET['almacen_id']) ? intval($_GET['almacen_id']) : null;
    if ($usuario_rol == 'admin' && $almacen_id_seleccionado) {
        $total_entregas = contarEntregasPorAlmacen($conn, $almacen_id_seleccionado, $filtros);
        $entregas = obtenerEntregasPorAlmacen($conn, $almacen_id_seleccionado, $filtros, $registros_por_pagina, $offset);
    } elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $total_entregas = contarEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros);
        $entregas = obtenerEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros, $registros_por_pagina, $offset);
    }
} elseif ($usuario_rol != 'admin' && $usuario_almacen_id) {
    // Si no es admin, obtener entregas del almacén asignado con paginación
    $total_entregas = contarEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros);
    $entregas = obtenerEntregasPorAlmacen($conn, $usuario_almacen_id, $filtros, $registros_por_pagina, $offset);
}

// Calcular total de páginas
$total_paginas = isset($total_entregas) ? ceil($total_entregas / $registros_por_pagina) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Entregas de Uniformes - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/uniform-delivery-history.css">
    <link rel="stylesheet" href="../assets/css/styles-almacenes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
    /* Estilos para la paginación */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    .pagination a, .pagination span {
        color: #333;
        text-decoration: none;
        display: inline-block;
        padding: 8px 16px;
        border: 1px solid #ddd;
        margin: 0 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .pagination a:hover {
        background-color: #f2f2f2;
    }
    .pagination .active {
        background-color: #4CAF50;
        color: white;
        border: 1px solid #4CAF50;
    }
    .pagination .disabled {
        color: #aaa;
        cursor: not-allowed;
    }
    </style>
</head>
<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Users - Only visible to administrators -->
        <?php if ($usuario_rol == 'admin'): ?>
        <li class="submenu-container">
            <a href="#" aria-label="Menú Usuarios">
                <i class="fas fa-users"></i> Usuarios <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../usuarios/registrar.php"><i class="fas fa-user-plus"></i> Registrar Usuario</a></li>
                <li><a href="../usuarios/listar.php"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Warehouses - Adjusted according to permissions -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Almacenes">
                <i class="fas fa-warehouse"></i> Almacenes <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <?php if ($usuario_rol == 'admin'): ?>
                <li><a href="../almacenes/registrar.php"><i class="fas fa-plus"></i> Registrar Almacén</a></li>
                <?php endif; ?>
                <li><a href="../almacenes/listar.php"><i class="fas fa-list"></i> Lista de Almacenes</a></li>
            </ul>
        </li>
        
        <!-- Notifications -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
                <?php 
                // Count pending requests to show in badge
                $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                
                // If user is not admin, filter by their warehouse
                if ($usuario_rol != 'admin') {
                    $sql_pendientes .= " AND almacen_destino = ?";
                    $stmt_pendientes = $conn->prepare($sql_pendientes);
                    $stmt_pendientes->bind_param("i", $usuario_almacen_id);
                    $stmt_pendientes->execute();
                    $result_pendientes = $stmt_pendientes->get_result();
                } else {
                    $result_pendientes = $conn->query($sql_pendientes);
                }
                
                if ($result_pendientes && $row_pendientes = $result_pendientes->fetch_assoc()) {
                    echo '<span class="badge">' . $row_pendientes['total'] . '</span>';
                }
                ?>
                </a></li>
                <li><a href="../notificaciones/historial.php"><i class="fas fa-list"></i> Historial de Solicitudes</a></li>
                <li><a href="../uniformes/historial_entregas_uniformes.php"><i class="fas fa-tshirt"></i> Historial de Entregas de Uniformes</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

    <!-- Main Content -->
    <main class="content" id="main-content">
        <h2>Historial de Entregas de Uniformes</h2>

        <?php if ($usuario_rol == 'admin'): ?>
            <div class="almacenes-container">
                <?php if ($result_almacenes && $result_almacenes->num_rows > 0): ?>
                    <?php while ($almacen = $result_almacenes->fetch_assoc()): ?>
                        <div class="almacen-card">
                            <h3><?php echo htmlspecialchars($almacen["nombre"]); ?></h3>
                            <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($almacen["ubicacion"]); ?></p>
                            <a href="#" class="btn-ver mostrar-entregas" data-almacen-id="<?php echo $almacen['id']; ?>">
                                <i class="fas fa-eye"></i> Ver Entregas
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No hay almacenes registrados.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="contenedor-historial-entregas" style="<?php echo $usuario_rol != 'admin' ? 'display:block;' : 'display:none;'; ?>">
            <form method="GET" class="uniform-filter-form" id="formulario-filtros">
                <div class="row">
                    <div class="col-md-4">
                        <label for="filtro-dni" class="form-label">Filtrar por DNI</label>
                        <input type="text" class="form-control" id="filtro-dni" name="dni" 
                               placeholder="Número de DNI" 
                               value="<?php echo htmlspecialchars($_GET['dni'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro-fecha-inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="filtro-fecha-inicio" 
                               name="fecha_inicio"
                               value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro-fecha-fin" class="form-label">Fecha de Fin</label>
                        <input type="date" class="form-control" id="filtro-fecha-fin" 
                               name="fecha_fin"
                               value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="?" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
                <!-- Conservar el almacén seleccionado durante la paginación -->
                <?php if (isset($_GET['almacen_id'])): ?>
                    <input type="hidden" name="almacen_id" value="<?php echo htmlspecialchars($_GET['almacen_id']); ?>">
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table uniform-delivery-table" id="tabla-historial-entregas">
                    <thead>
                        <tr>
                            <th>Almacén</th>
                            <th>Fecha</th>
                            <th>Destinatario</th>
                            <th>DNI</th>
                            <th>Productos Entregados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="5" class="no-results-message">
                                    No hay entregas para mostrar
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entrega['almacen_nombre']); ?></td>
                                    <td>
                                        <?php 
                                        $fecha = new DateTime($entrega['fecha_entrega']);
                                        echo $fecha->format('d/m/Y'); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($entrega['nombre_destinatario']); ?></td>
                                    <td><?php echo htmlspecialchars($entrega['dni_destinatario']); ?></td>
                                    <td>
                                        <ul class="productos-lista">
                                            <?php foreach ($entrega['productos'] as $producto): ?>
                                                <li>
                                                    <?php 
                                                    echo htmlspecialchars($producto['nombre']) . 
                                                         ' (Cantidad: ' . 
                                                         htmlspecialchars($producto['cantidad']) . 
                                                         ')'; 
                                                    ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 0): ?>
            <div class="pagination">
                <?php
                // Parámetros actuales de URL para mantener en los enlaces de paginación
                $params = [];
                foreach ($_GET as $key => $value) {
                    if ($key != 'pagina') {
                        $params[] = $key . '=' . urlencode($value);
                    }
                }
                $url_params = !empty($params) ? '?' . implode('&', $params) . '&' : '?';
                
                // Mostrar enlace "Anterior" si no estamos en la primera página
                if ($pagina_actual > 1): ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual - 1; ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>
                
                <?php
                // Definir cuántas páginas mostrar a cada lado de la página actual
                $paginas_mostrar = 2;
                $inicio_paginas = max(1, $pagina_actual - $paginas_mostrar);
                $fin_paginas = min($total_paginas, $pagina_actual + $paginas_mostrar);
                
                // Mostrar página 1 si estamos muy lejos
                if ($inicio_paginas > 1): ?>
                    <a href="<?php echo $url_params; ?>pagina=1">1</a>
                    <?php if ($inicio_paginas > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Mostrar enlaces numerados -->
                <?php for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): ?>
                    <?php if ($i == $pagina_actual): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $url_params; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <!-- Mostrar última página si estamos muy lejos -->
                <?php if ($fin_paginas < $total_paginas): ?>
                    <?php if ($fin_paginas < $total_paginas - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $total_paginas; ?>"><?php echo $total_paginas; ?></a>
                <?php endif; ?>
                
                <!-- Mostrar enlace "Siguiente" si no estamos en la última página -->
                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo $url_params; ?>pagina=<?php echo $pagina_actual + 1; ?>">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Siguiente <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar clic en "Ver Entregas" para admin
        const botonesVerEntregas = document.querySelectorAll('.mostrar-entregas');
        const contenedorHistorial = document.getElementById('contenedor-historial-entregas');
        const tablaHistorial = document.getElementById('tabla-historial-entregas');
        const almacenesContainer = document.querySelector('.almacenes-container');

        botonesVerEntregas.forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                const almacenId = this.dataset.almacenId;

                // Ocultar los cuadros de almacén
                almacenesContainer.style.display = 'none';

                // Redirigir con el almacen_id seleccionado
                window.location.href = `?almacen_id=${almacenId}`;
            });
        });

        // Agregar botón para volver a la lista de almacenes (para admin)
        <?php if ($usuario_rol == 'admin' && isset($_GET['almacen_id'])): ?>
        const mainContent = document.getElementById('main-content');
        const volverBtn = document.createElement('button');
        volverBtn.className = 'btn btn-secondary';
        volverBtn.innerHTML = '<i class="fas fa-arrow-left"></i> Volver a la lista de almacenes';
        volverBtn.style.marginBottom = '20px';
        volverBtn.addEventListener('click', function() {
            window.location.href = 'historial_entregas_uniformes.php';
        });
        mainContent.insertBefore(volverBtn, contenedorHistorial);
        <?php endif; ?>

        // Validación de fechas
        const form = document.querySelector('.uniform-filter-form');
        form.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('filtro-fecha-inicio').value;
            const fechaFin = document.getElementById('filtro-fecha-fin').value;

            if (fechaInicio && fechaFin && new Date(fechaInicio) > new Date(fechaFin)) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor que la fecha de fin');
            }
        });
    });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>