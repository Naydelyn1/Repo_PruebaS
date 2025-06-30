<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

session_regenerate_id(true);
require_once "../config/database.php";

// NUEVO: Configurar zona horaria de Perú
date_default_timezone_set('America/Lima');

$user_name = $_SESSION["user_name"] ?? "Usuario";
$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Obtener ID del almacén (si se especifica)
$almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;

// Verificar permisos
if ($usuario_rol != 'admin' && $almacen_id && $usuario_almacen_id != $almacen_id) {
    $_SESSION['error'] = "No tienes permiso para ver este reporte";
    header("Location: ../almacenes/listar.php");
    exit();
}

// Obtener información del almacén
$almacen_info = null;
if ($almacen_id) {
    $sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
    $stmt = $conn->prepare($sql_almacen);
    $stmt->bind_param("i", $almacen_id);
    $stmt->execute();
    $almacen_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$almacen_info) {
        $_SESSION['error'] = "Almacén no encontrado";
        header("Location: ../almacenes/listar.php");
        exit();
    }
}

// Estadísticas generales
if ($almacen_id) {
    // Estadísticas específicas del almacén
    $sql_stats = "SELECT 
        COUNT(DISTINCT p.categoria_id) as total_categorias,
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock,
        COALESCE(AVG(p.cantidad), 0) as promedio_stock,
        COALESCE(MIN(p.cantidad), 0) as stock_minimo,
        COALESCE(MAX(p.cantidad), 0) as stock_maximo
        FROM productos p 
        WHERE p.almacen_id = ?";
    $stmt = $conn->prepare($sql_stats);
    $stmt->bind_param("i", $almacen_id);
} else {
    // CORREGIDO: Verificar que haya productos válidos
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        // Para usuarios no admin, mostrar solo su almacén
        $sql_stats = "SELECT 
            COUNT(DISTINCT p.categoria_id) as total_categorias,
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock,
            COALESCE(AVG(p.cantidad), 0) as promedio_stock,
            COALESCE(MIN(p.cantidad), 0) as stock_minimo,
            COALESCE(MAX(p.cantidad), 0) as stock_maximo
            FROM productos p 
            WHERE p.almacen_id = ?";
        $stmt = $conn->prepare($sql_stats);
        $stmt->bind_param("i", $usuario_almacen_id);
    } else {
        // Para admin, estadísticas globales
        $sql_stats = "SELECT 
            COUNT(DISTINCT p.categoria_id) as total_categorias,
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock,
            COALESCE(AVG(p.cantidad), 0) as promedio_stock,
            COALESCE(MIN(p.cantidad), 0) as stock_minimo,
            COALESCE(MAX(p.cantidad), 0) as stock_maximo
            FROM productos p";
        $stmt = $conn->prepare($sql_stats);
    }
}

$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// CORREGIDO: Productos por categoría con mejor lógica
if ($almacen_id) {
    // Para almacén específico
    $sql_categorias = "SELECT c.nombre, 
        COUNT(p.id) as total_productos,
        COALESCE(SUM(p.cantidad), 0) as total_stock
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
        GROUP BY c.id, c.nombre
        HAVING total_productos > 0 OR total_stock > 0
        ORDER BY total_stock DESC";
    $stmt = $conn->prepare($sql_categorias);
    $stmt->bind_param("i", $almacen_id);
} else {
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        // Para usuarios no admin
        $sql_categorias = "SELECT c.nombre, 
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id AND p.almacen_id = ?
            GROUP BY c.id, c.nombre
            HAVING total_productos > 0 OR total_stock > 0
            ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
        $stmt->bind_param("i", $usuario_almacen_id);
    } else {
        // Para admin - todas las categorías con productos
        $sql_categorias = "SELECT c.nombre, 
            COUNT(p.id) as total_productos,
            COALESCE(SUM(p.cantidad), 0) as total_stock
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id
            GROUP BY c.id, c.nombre
            HAVING total_productos > 0 OR total_stock > 0
            ORDER BY total_stock DESC";
        $stmt = $conn->prepare($sql_categorias);
    }
}

$stmt->execute();
$categorias_stats = $stmt->get_result();
$stmt->close();

// Productos con stock bajo
if ($almacen_id) {
    $sql_bajo_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.almacen_id = ? AND p.cantidad < 10
        ORDER BY p.cantidad ASC";
    $stmt = $conn->prepare($sql_bajo_stock);
    $stmt->bind_param("i", $almacen_id);
} else {
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_bajo_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.almacen_id = ? AND p.cantidad < 10
            ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_bajo_stock);
        $stmt->bind_param("i", $usuario_almacen_id);
    } else {
        $sql_bajo_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            JOIN almacenes a ON p.almacen_id = a.id
            WHERE p.cantidad < 10
            ORDER BY p.cantidad ASC";
        $stmt = $conn->prepare($sql_bajo_stock);
    }
}

$stmt->execute();
$productos_bajo_stock = $stmt->get_result();
$stmt->close();

// Productos con más stock
if ($almacen_id) {
    $sql_alto_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.almacen_id = ?
        ORDER BY p.cantidad DESC
        LIMIT 10";
    $stmt = $conn->prepare($sql_alto_stock);
    $stmt->bind_param("i", $almacen_id);
} else {
    if ($usuario_rol != 'admin' && $usuario_almacen_id) {
        $sql_alto_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.almacen_id = ?
            ORDER BY p.cantidad DESC
            LIMIT 10";
        $stmt = $conn->prepare($sql_alto_stock);
        $stmt->bind_param("i", $usuario_almacen_id);
    } else {
        $sql_alto_stock = "SELECT p.nombre, p.cantidad, c.nombre as categoria, a.nombre as almacen
            FROM productos p
            JOIN categorias c ON p.categoria_id = c.id
            JOIN almacenes a ON p.almacen_id = a.id
            ORDER BY p.cantidad DESC
            LIMIT 10";
        $stmt = $conn->prepare($sql_alto_stock);
    }
}

$stmt->execute();
$productos_alto_stock = $stmt->get_result();
$stmt->close();

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

// NUEVO: Función para formatear fecha y hora en zona horaria de Perú
function formatearFechaHora() {
    return date('d/m/Y H:i:s');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inventario<?php echo $almacen_info ? ' - ' . htmlspecialchars($almacen_info['nombre']) : ''; ?> - COMSEPROA</title>
    
    <!-- Meta tags adicionales -->
    <meta name="description" content="Reporte detallado de inventario - Sistema COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS base -->
    <link rel="stylesheet" href="../assets/css/reportes/reportes-inventario.css">
</head>
<body>
    <div class="action-buttons">
        <button class="action-btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
        
        <?php if ($almacen_id): ?>
        <a href="../almacenes/ver-almacen.php?id=<?php echo $almacen_id; ?>" class="action-btn btn-back">
            <i class="fas fa-arrow-left"></i> Volver al Almacén
        </a>
        <?php else: ?>
        <a href="../dashboard.php" class="action-btn btn-back">
            <i class="fas fa-arrow-left"></i> Volver al Inicio
        </a>
        <?php endif; ?>
    </div>

    <div class="report-container">
        <div class="report-header">
            <h1><i class="fas fa-chart-bar"></i> Reporte de Inventario</h1>
            <?php if ($almacen_info): ?>
                <h2><i class="fas fa-warehouse"></i> <?php echo htmlspecialchars($almacen_info['nombre']); ?></h2>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($almacen_info['ubicacion']); ?></p>
            <?php else: ?>
                <h2><i class="fas fa-building"></i> Inventario General</h2>
                <p>Reporte completo de todos los almacenes del sistema</p>
            <?php endif; ?>
            <!-- CORREGIDO: Fecha y hora con zona horaria correcta -->
            <p><i class="fas fa-calendar-alt"></i> Generado el: <?php echo formatearFechaHora(); ?></p>
            <p><i class="fas fa-user"></i> Por: <?php echo htmlspecialchars($user_name); ?></p>
        </div>

        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-boxes"></i> Total Productos</h3>
                <div class="stat-value"><?php echo number_format($stats['total_productos']); ?></div>
                <div class="stat-label">Productos Registrados</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-tags"></i> Categorías</h3>
                <div class="stat-value"><?php echo number_format($stats['total_categorias']); ?></div>
                <div class="stat-label">Categorías Activas</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-cubes"></i> Stock Total</h3>
                <div class="stat-value"><?php echo number_format($stats['total_stock']); ?></div>
                <div class="stat-label">Unidades en Inventario</div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-chart-line"></i> Promedio</h3>
                <div class="stat-value"><?php echo number_format($stats['promedio_stock'], 1); ?></div>
                <div class="stat-label">Unidades por Producto</div>
            </div>
        </div>

        <!-- CORREGIDO: Distribución por Categorías con mejor manejo de datos vacíos -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-chart-pie"></i> Distribución por Categorías</h3>
            </div>
            <div class="section-content">
                <?php if ($categorias_stats->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Productos</th>
                                <th>Stock Total</th>
                                <th>% del Stock</th>
                                <th>Distribución</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_general = $stats['total_stock'];
                            $categorias_array = [];
                            
                            // Almacenar los datos en array para debug
                            while ($cat = $categorias_stats->fetch_assoc()) {
                                $categorias_array[] = $cat;
                            }
                            
                            // Verificar si hay categorías
                            if (empty($categorias_array)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #666; padding: 20px;">
                                        <i class="fas fa-info-circle"></i> No hay categorías con productos en este momento
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categorias_array as $cat): 
                                    $porcentaje = $total_general > 0 ? ($cat['total_stock'] / $total_general) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                                    <td><?php echo number_format($cat['total_productos']); ?></td>
                                    <td><?php echo number_format($cat['total_stock']); ?></td>
                                    <td><?php echo number_format($porcentaje, 1); ?>%</td>
                                    <td>
                                        <div class="percentage-bar">
                                            <div class="percentage-fill" style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-inbox"></i>
                    <h4>No hay datos de categorías</h4>
                    <p>No se encontraron categorías con productos para mostrar.</p>
                    <?php if ($usuario_rol == 'admin'): ?>
                    <p><small>Verifique que existan productos registrados en las categorías.</small></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resto del código permanece igual -->
        <!-- Productos con Stock Crítico -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Productos con Stock Crítico (< 10 unidades)</h3>
            </div>
            <div class="section-content">
                <?php if ($productos_bajo_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <?php if (!$almacen_id && ($usuario_rol == 'admin' || !$usuario_almacen_id)): ?><th>Almacén</th><?php endif; ?>
                                <th>Stock Actual</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $productos_bajo_stock->data_seek(0); // Reset cursor
                            while ($prod = $productos_bajo_stock->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prod['categoria']); ?></td>
                                <?php if (!$almacen_id && ($usuario_rol == 'admin' || !$usuario_almacen_id)): ?><td><?php echo htmlspecialchars($prod['almacen'] ?? 'N/A'); ?></td><?php endif; ?>
                                <td>
                                    <span class="<?php echo $prod['cantidad'] < 5 ? 'stock-critical' : 'stock-warning'; ?>">
                                        <?php echo $prod['cantidad']; ?> unidades
                                    </span>
                                </td>
                                <td>
                                    <?php if ($prod['cantidad'] < 5): ?>
                                        <span class="stock-critical"><i class="fas fa-exclamation-circle"></i> CRÍTICO</span>
                                    <?php else: ?>
                                        <span class="stock-warning"><i class="fas fa-exclamation-triangle"></i> BAJO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($prod['cantidad'] < 3): ?>
                                        <span style="color: #dc3545;"><i class="fas fa-arrow-up"></i> URGENTE</span>
                                    <?php elseif ($prod['cantidad'] < 5): ?>
                                        <span style="color: #fd7e14;"><i class="fas fa-arrow-up"></i> ALTA</span>
                                    <?php else: ?>
                                        <span style="color: #ffc107;"><i class="fas fa-minus"></i> MEDIA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-check-circle"></i>
                    <h4 class="stock-good">¡Excelente gestión de inventario!</h4>
                    <p>No hay productos con stock crítico en este momento.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 10 Productos con Mayor Stock -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-trophy"></i> Top 10 - Productos con Mayor Stock</h3>
            </div>
            <div class="section-content">
                <?php if ($productos_alto_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Posición</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <?php if (!$almacen_id && ($usuario_rol == 'admin' || !$usuario_almacen_id)): ?><th>Almacén</th><?php endif; ?>
                                <th>Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ranking = 1;
                            $productos_alto_stock->data_seek(0); // Reset cursor
                            while ($prod = $productos_alto_stock->fetch_assoc()): 
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $ranking; ?></strong>
                                    <?php if ($ranking <= 3): ?>
                                        <i class="fas fa-medal" style="color: <?php echo $ranking == 1 ? '#ffd700' : ($ranking == 2 ? '#c0c0c0' : '#cd7f32'); ?>;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prod['categoria']); ?></td>
                                <?php if (!$almacen_id && ($usuario_rol == 'admin' || !$usuario_almacen_id)): ?><td><?php echo htmlspecialchars($prod['almacen'] ?? 'N/A'); ?></td><?php endif; ?>
                                <td class="stock-good"><?php echo number_format($prod['cantidad']); ?> unidades</td>
                                <td><span class="stock-good"><i class="fas fa-check-circle"></i> ÓPTIMO</span></td>
                            </tr>
                            <?php 
                            $ranking++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-inbox"></i>
                    <h4>No hay productos registrados</h4>
                    <p>No se encontraron productos en el inventario.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen Ejecutivo -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-clipboard-check"></i> Resumen Ejecutivo</h3>
            </div>
            <div class="section-content">
                <div class="summary-grid">
                    <div class="summary-card">
                        <h4><i class="fas fa-chart-line"></i> Estado del Inventario</h4>
                        <ul>
                            <li>
                                <span>Total de productos:</span>
                                <strong><?php echo number_format($stats['total_productos']); ?></strong>
                            </li>
                            <li>
                                <span>Stock mínimo:</span>
                                <strong><?php echo number_format($stats['stock_minimo']); ?> unidades</strong>
                            </li>
                            <li>
                                <span>Stock máximo:</span>
                                <strong><?php echo number_format($stats['stock_maximo']); ?> unidades</strong>
                            </li>
                            <li>
                                <span>Productos críticos:</span>
                                <strong style="color: #dc3545;"><?php echo $productos_bajo_stock->num_rows; ?></strong>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="summary-card">
                        <h4><i class="fas fa-lightbulb"></i> Recomendaciones</h4>
                        <ul style="list-style: none;">
                            <?php if ($productos_bajo_stock->num_rows > 0): ?>
                            <li><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Reabastecer <?php echo $productos_bajo_stock->num_rows; ?> productos con stock crítico</li>
                            <?php endif; ?>
                            
                            <?php if ($stats['total_productos'] > 0): ?>
                            <li><i class="fas fa-chart-line" style="color: #007bff;"></i> Revisar productos con mayor rotación</li>
                            <li><i class="fas fa-sync-alt" style="color: #28a745;"></i> Optimizar distribución entre categorías</li>
                            <?php endif; ?>
                            
                            <li><i class="fas fa-calendar-check" style="color: #fd7e14;"></i> Programar próxima revisión de inventario</li>
                            
                            <?php if ($stats['promedio_stock'] < 50): ?>
                            <li><i class="fas fa-arrow-up" style="color: #6f42c1;"></i> Considerar aumentar stock promedio</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 10px; border-left: 5px solid #2196f3;">
                    <h5 style="color: #1565c0; margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i> Conclusión
                    </h5>
                    <p style="margin: 0; color: #424242; line-height: 1.6;">
                        <?php if ($almacen_info): ?>
                            El almacén <strong><?php echo htmlspecialchars($almacen_info['nombre']); ?></strong> cuenta con 
                            <strong><?php echo number_format($stats['total_productos']); ?> productos</strong> distribuidos en 
                            <strong><?php echo $stats['total_categorias']; ?> categorías</strong>, con un stock total de 
                            <strong><?php echo number_format($stats['total_stock']); ?> unidades</strong>.
                        <?php else: ?>
                            El sistema cuenta con un total de <strong><?php echo number_format($stats['total_productos']); ?> productos</strong> 
                            distribuidos en <strong><?php echo $stats['total_categorias']; ?> categorías</strong>, con un stock total de 
                            <strong><?php echo number_format($stats['total_stock']); ?> unidades</strong> en todos los almacenes.
                        <?php endif; ?>
                        
                        <?php if ($productos_bajo_stock->num_rows > 0): ?>
                            Se requiere atención inmediata para <strong style="color: #dc3545;"><?php echo $productos_bajo_stock->num_rows; ?> productos</strong> 
                            con stock crítico.
                        <?php else: ?>
                            <strong style="color: #28a745;">Todos los productos mantienen niveles de stock adecuados.</strong>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animación de las barras de porcentaje al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.percentage-fill');
            bars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
            
            // Animación de los números
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                const target = parseInt(stat.textContent.replace(/,/g, ''));
                animateCounter(stat, target);
            });
        });
        
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 20);
        }
        
        // Función para imprimir con estilos optimizados
        function optimizedPrint() {
            const printCSS = `
                @media print {
                    body { font-size: 12px; }
                    .report-header { background: #f8f9fa !important; color: #000 !important; }
                    .stat-card { page-break-inside: avoid; }
                    .report-section { page-break-inside: avoid; margin-bottom: 20px; }
                    table { font-size: 11px; }
                }
            `;
            const style = document.createElement('style');
            style.textContent = printCSS;
            document.head.appendChild(style);
            window.print();
            document.head.removeChild(style);
        }
    </script>
</body>
</html>