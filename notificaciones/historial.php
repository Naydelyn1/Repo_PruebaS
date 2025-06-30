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

// Obtener el almacén y rol del usuario actual
$usuario_almacen_id = $_SESSION["almacen_id"]; 
$usuario_rol = $_SESSION["user_role"];

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Solicitudes - COMSEPROA</title>
    <link rel="stylesheet" href="../assets/css/styles-dashboard.css">
    <link rel="stylesheet" href="../assets/css/styles-pendientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Estilos específicos para el historial */
        .solicitud-estado {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .estado-aprobada {
            background-color: #d4edda;
            color: #155724;
        }
        
        .estado-rechazada {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .solicitud-fechas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 5px;
        }
        
        .tabla-historial {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .tabla-historial th, .tabla-historial td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .tabla-historial th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        
        .tabla-historial tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .tabla-historial tr:hover {
            background-color: #f1f1f1;
        }
        
        .filtros {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filtros select, .filtros input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-filtrar {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-filtrar:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<!-- Menú Lateral -->
<nav class="sidebar">
    <h2>GRUPO SEAL</h2>
    <ul>
        <li><a href="../dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>

        <!-- Usuarios - Solo visible para administradores -->
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

        <!-- Almacenes - Ajustado según permisos -->
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
        
        <!-- Notificaciones -->
        <li class="submenu-container">
            <a href="#" aria-label="Menú Notificaciones">
                <i class="fas fa-bell"></i> Notificaciones <i class="fas fa-chevron-down"></i>
            </a>
            <ul class="submenu">
                <li><a href="../notificaciones/pendientes.php"><i class="fas fa-clock"></i> Solicitudes Pendientes 
                <?php 
                // Contar solicitudes pendientes para mostrar en el badge
                $sql_pendientes = "SELECT COUNT(*) as total FROM solicitudes_transferencia WHERE estado = 'pendiente'";
                
                // Si el usuario no es admin, filtrar por su almacén
                if ($usuario_rol != 'admin') {
                    $sql_pendientes .= " AND almacen_destino = ?";  // Filtrar por almacén destino
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
        <!-- Cerrar Sesión -->
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
    </ul>
</nav>

<div class="main-content">
    <h1>Historial de Solicitudes de Transferencia</h1>
    
    <!-- Contenedor de notificaciones -->
    <div id="notificaciones-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="notificacion exito">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notificacion error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <span class="cerrar">&times;</span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Filtros de búsqueda -->
    <div class="filtros">
        <select id="filtro-estado">
            <option value="todos">Todos los estados</option>
            <option value="aprobada">Aprobadas</option>
            <option value="rechazada">Rechazadas</option>
        </select>
        
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
        
        <input type="date" id="filtro-fecha" placeholder="Fecha">
        
        <button class="btn-filtrar" onclick="filtrarHistorial()">
            <i class="fas fa-filter"></i> Filtrar
        </button>
    </div>
    
    <!-- Tabla de historial -->
    <div style="overflow-x: auto;">
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
                <td><?php echo htmlspecialchars($solicitud['producto_nombre']); ?> 
                    (<?php echo htmlspecialchars($solicitud['color']); ?>, 
                    <?php echo htmlspecialchars($solicitud['talla_dimensiones']); ?>)</td>
                <td><?php echo htmlspecialchars($solicitud['categoria_nombre']); ?></td>
                <td><?php echo intval($solicitud['cantidad']); ?></td>
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
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($solicitud['fecha_respuesta'])): ?>
                        <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_respuesta'])); ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="11" style="text-align: center; padding: 20px;">
                <i class="fas fa-info-circle" style="font-size: 24px; color: #6c757d;"></i>
                <p>No hay registros de solicitudes procesadas.</p>
            </td>
        </tr>
    <?php endif; ?>
</tbody>
        </table>
    </div>
</div>

<script>
    // Función para filtrar el historial
    function filtrarHistorial() {
        const filtroEstado = document.getElementById('filtro-estado').value;
        const filtroAlmacen = document.getElementById('filtro-almacen').value;
        const filtroFecha = document.getElementById('filtro-fecha').value;
        
        // Obtener todas las filas de la tabla
        const filas = document.querySelectorAll('#tabla-historial tbody tr.fila-historial');
        
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
        });
    }
    
    // Cerrar notificaciones
    document.querySelectorAll('.notificacion .cerrar').forEach(function(boton) {
        boton.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
</script>

<!-- Referencia al script.js -->
<script src="../assets/js/script.js"></script>
</body>
</html>