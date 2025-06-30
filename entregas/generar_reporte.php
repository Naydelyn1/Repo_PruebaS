<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login_form.php");
    exit();
}

require_once "../config/database.php";

$usuario_rol = $_SESSION["user_role"] ?? "usuario";
$usuario_almacen_id = $_SESSION["almacen_id"] ?? null;

// Obtener parámetros
$formato = $_GET['formato'] ?? 'pdf'; // pdf, excel o csv
$filtro_almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
$filtro_categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;

// Verificar permisos
if ($filtro_almacen_id && $usuario_rol != 'admin' && $usuario_almacen_id != $filtro_almacen_id) {
    die("No tienes permisos para generar reportes de este almacén");
}

// Determinar almacén
$almacen_id_reporte = null;
if ($usuario_rol == 'admin') {
    $almacen_id_reporte = $filtro_almacen_id;
} else {
    $almacen_id_reporte = $usuario_almacen_id;
}

if (!$almacen_id_reporte) {
    die("Debe especificar un almacén para generar el reporte");
}

// Preparar filtros para la consulta
$filtros = [
    'dni' => $_GET['dni'] ?? '',
    'nombre' => $_GET['nombre'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? ''
];

// Función para obtener entregas para el reporte
function obtenerEntregasReporte($conn, $almacen_id, $categoria_id = null, $filtros = []) {
    $query = '
        SELECT 
            eu.id,
            eu.nombre_destinatario,
            eu.dni_destinatario,
            eu.fecha_entrega,
            p.nombre as producto_nombre,
            p.modelo,
            p.color,
            p.talla_dimensiones,
            eu.cantidad,
            p.unidad_medida,
            a.nombre as almacen_nombre,
            a.ubicacion as almacen_ubicacion,
            u.nombre as usuario_responsable,
            u.apellidos as usuario_apellidos,
            c.nombre as categoria_nombre
        FROM 
            entrega_uniformes eu
        JOIN 
            productos p ON eu.producto_id = p.id
        JOIN 
            almacenes a ON eu.almacen_id = a.id
        JOIN
            categorias c ON p.categoria_id = c.id
        LEFT JOIN
            usuarios u ON eu.usuario_responsable_id = u.id
        WHERE 
            eu.almacen_id = ?
    ';

    $params = [$almacen_id];
    $types = 'i';

    // Filtro por categoría
    if ($categoria_id) {
        $query .= ' AND p.categoria_id = ?';
        $params[] = $categoria_id;
        $types .= 'i';
    }

    // Otros filtros
    if (!empty($filtros['dni'])) {
        $query .= ' AND eu.dni_destinatario LIKE ?';
        $params[] = '%' . $filtros['dni'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['nombre'])) {
        $query .= ' AND eu.nombre_destinatario LIKE ?';
        $params[] = '%' . $filtros['nombre'] . '%';
        $types .= 's';
    }

    if (!empty($filtros['fecha_inicio'])) {
        $query .= ' AND DATE(eu.fecha_entrega) >= ?';
        $params[] = $filtros['fecha_inicio'];
        $types .= 's';
    }
    if (!empty($filtros['fecha_fin'])) {
        $query .= ' AND DATE(eu.fecha_entrega) <= ?';
        $params[] = $filtros['fecha_fin'];
        $types .= 's';
    }

    $query .= ' ORDER BY eu.fecha_entrega DESC, eu.nombre_destinatario';
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Obtener información del almacén
$sql_almacen = "SELECT * FROM almacenes WHERE id = ?";
$stmt = $conn->prepare($sql_almacen);
$stmt->bind_param("i", $almacen_id_reporte);
$stmt->execute();
$almacen_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$almacen_info) {
    die("Almacén no encontrado");
}

// Obtener información de la categoría si existe
$categoria_info = null;
if ($filtro_categoria_id) {
    $sql_categoria = "SELECT * FROM categorias WHERE id = ?";
    $stmt = $conn->prepare($sql_categoria);
    $stmt->bind_param("i", $filtro_categoria_id);
    $stmt->execute();
    $categoria_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Obtener datos para el reporte
$entregas = obtenerEntregasReporte($conn, $almacen_id_reporte, $filtro_categoria_id, $filtros);

if (empty($entregas)) {
    die("No hay datos para generar el reporte con los filtros seleccionados");
}

// Generar reporte según el formato
if ($formato === 'excel') {
    generarReporteExcel($entregas, $almacen_info, $categoria_info, $filtros);
} elseif ($formato === 'csv') {
    generarReporteCSV($entregas, $almacen_info, $categoria_info, $filtros);
} else {
    generarReportePDF($entregas, $almacen_info, $categoria_info, $filtros);
}

// Función para generar reporte en CSV
function generarReporteCSV($entregas, $almacen_info, $categoria_info, $filtros) {
    $categoria_texto = $categoria_info ? '_' . str_replace(' ', '_', $categoria_info['nombre']) : '';
    $filename = 'reporte_entregas_' . str_replace(' ', '_', $almacen_info['nombre']) . $categoria_texto . '_' . date('Y-m-d_H-i-s') . '.csv';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Información del reporte
    fputcsv($output, ['REPORTE DE ENTREGAS - GRUPO SEAL']);
    fputcsv($output, []);
    fputcsv($output, ['Almacén:', $almacen_info['nombre']]);
    fputcsv($output, ['Ubicación:', $almacen_info['ubicacion']]);
    if ($categoria_info) {
        fputcsv($output, ['Categoría:', $categoria_info['nombre']]);
    }
    fputcsv($output, ['Fecha del reporte:', date('d/m/Y H:i')]);
    fputcsv($output, ['Total de entregas:', count($entregas)]);
    
    // Mostrar filtros aplicados
    $filtros_aplicados = [];
    if (!empty($filtros['nombre'])) $filtros_aplicados[] = 'Nombre: ' . $filtros['nombre'];
    if (!empty($filtros['dni'])) $filtros_aplicados[] = 'DNI: ' . $filtros['dni'];
    if (!empty($filtros['fecha_inicio'])) $filtros_aplicados[] = 'Desde: ' . $filtros['fecha_inicio'];
    if (!empty($filtros['fecha_fin'])) $filtros_aplicados[] = 'Hasta: ' . $filtros['fecha_fin'];
    
    if (!empty($filtros_aplicados)) {
        fputcsv($output, ['Filtros aplicados:', implode(' | ', $filtros_aplicados)]);
    }
    
    fputcsv($output, []);
    
    // Encabezados
    fputcsv($output, [
        'Fecha Entrega', 'Destinatario', 'DNI', 'Categoría', 'Producto',
        'Modelo', 'Color', 'Talla/Dimensiones', 'Cantidad', 'Unidad', 'Responsable'
    ]);
    
    // Datos
    foreach ($entregas as $entrega) {
        $responsable = '';
        if (!empty($entrega['usuario_responsable'])) {
            $responsable = $entrega['usuario_responsable'];
            if (!empty($entrega['usuario_apellidos'])) {
                $responsable .= ' ' . $entrega['usuario_apellidos'];
            }
        } else {
            $responsable = 'No registrado';
        }
        
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])),
            $entrega['nombre_destinatario'],
            $entrega['dni_destinatario'],
            $entrega['categoria_nombre'],
            $entrega['producto_nombre'],
            $entrega['modelo'] ?: '-',
            $entrega['color'] ?: '-',
            $entrega['talla_dimensiones'] ?: '-',
            $entrega['cantidad'],
            $entrega['unidad_medida'],
            $responsable
        ]);
    }
    
    fclose($output);
    exit();
}

// Función para generar reporte en Excel (usando CSV como fallback)
function generarReporteExcel($entregas, $almacen_info, $categoria_info, $filtros) {
    // Generar CSV con extensión .xls para compatibilidad con Excel
    $categoria_texto = $categoria_info ? '_' . str_replace(' ', '_', $categoria_info['nombre']) : '';
    $filename = 'reporte_entregas_' . str_replace(' ', '_', $almacen_info['nombre']) . $categoria_texto . '_' . date('Y-m-d_H-i-s') . '.xls';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    // Generar tabla HTML que Excel puede interpretar
    echo '<table border="1">';
    
    // Información del reporte
    echo '<tr><th colspan="11" style="background-color: #0a253c; color: white; font-weight: bold; text-align: center;">REPORTE DE ENTREGAS - GRUPO SEAL</th></tr>';
    echo '<tr><td></td></tr>';
    echo '<tr><td><strong>Almacén:</strong></td><td colspan="10">' . htmlspecialchars($almacen_info['nombre']) . '</td></tr>';
    echo '<tr><td><strong>Ubicación:</strong></td><td colspan="10">' . htmlspecialchars($almacen_info['ubicacion']) . '</td></tr>';
    
    if ($categoria_info) {
        echo '<tr><td><strong>Categoría:</strong></td><td colspan="10">' . htmlspecialchars($categoria_info['nombre']) . '</td></tr>';
    }
    
    echo '<tr><td><strong>Fecha del reporte:</strong></td><td colspan="10">' . date('d/m/Y H:i') . '</td></tr>';
    echo '<tr><td><strong>Total de entregas:</strong></td><td colspan="10">' . count($entregas) . '</td></tr>';
    
    // Mostrar filtros aplicados
    $filtros_aplicados = [];
    if (!empty($filtros['nombre'])) $filtros_aplicados[] = 'Nombre: ' . $filtros['nombre'];
    if (!empty($filtros['dni'])) $filtros_aplicados[] = 'DNI: ' . $filtros['dni'];
    if (!empty($filtros['fecha_inicio'])) $filtros_aplicados[] = 'Desde: ' . $filtros['fecha_inicio'];
    if (!empty($filtros['fecha_fin'])) $filtros_aplicados[] = 'Hasta: ' . $filtros['fecha_fin'];
    
    if (!empty($filtros_aplicados)) {
        echo '<tr><td><strong>Filtros aplicados:</strong></td><td colspan="10">' . htmlspecialchars(implode(' | ', $filtros_aplicados)) . '</td></tr>';
    }
    
    echo '<tr><td></td></tr>';
    
    // Encabezados
    echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
    echo '<th>Fecha Entrega</th>';
    echo '<th>Destinatario</th>';
    echo '<th>DNI</th>';
    echo '<th>Categoría</th>';
    echo '<th>Producto</th>';
    echo '<th>Modelo</th>';
    echo '<th>Color</th>';
    echo '<th>Talla/Dimensiones</th>';
    echo '<th>Cantidad</th>';
    echo '<th>Unidad</th>';
    echo '<th>Responsable</th>';
    echo '</tr>';
    
    // Datos
    foreach ($entregas as $entrega) {
        $responsable = '';
        if (!empty($entrega['usuario_responsable'])) {
            $responsable = $entrega['usuario_responsable'];
            if (!empty($entrega['usuario_apellidos'])) {
                $responsable .= ' ' . $entrega['usuario_apellidos'];
            }
        } else {
            $responsable = 'No registrado';
        }
        
        echo '<tr>';
        echo '<td>' . date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) . '</td>';
        echo '<td>' . htmlspecialchars($entrega['nombre_destinatario']) . '</td>';
        echo '<td>' . htmlspecialchars($entrega['dni_destinatario']) . '</td>';
        echo '<td>' . htmlspecialchars($entrega['categoria_nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($entrega['producto_nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($entrega['modelo'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($entrega['color'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($entrega['talla_dimensiones'] ?: '-') . '</td>';
        echo '<td style="text-align: center;">' . $entrega['cantidad'] . '</td>';
        echo '<td>' . htmlspecialchars($entrega['unidad_medida']) . '</td>';
        echo '<td>' . htmlspecialchars($responsable) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

// Función para generar reporte en PDF (HTML como fallback)
function generarReportePDF($entregas, $almacen_info, $categoria_info, $filtros) {
    $categoria_texto = $categoria_info ? '_' . str_replace(' ', '_', $categoria_info['nombre']) : '';
    $filename = 'reporte_entregas_' . str_replace(' ', '_', $almacen_info['nombre']) . $categoria_texto . '_' . date('Y-m-d_H-i-s') . '.html';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Reporte de Entregas - GRUPO SEAL</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                line-height: 1.4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px; 
                border-bottom: 3px solid #0a253c;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #0a253c;
                margin: 0;
                font-size: 24px;
            }
            .header h2 {
                color: #666;
                margin: 5px 0;
                font-size: 18px;
                font-weight: normal;
            }
            .info { 
                margin-bottom: 25px; 
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #0a253c;
            }
            .info-row {
                display: flex;
                margin-bottom: 8px;
            }
            .info-label {
                font-weight: bold;
                min-width: 120px;
                color: #0a253c;
            }
            .filtros {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border-left: 4px solid #2196f3;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                font-size: 12px;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
                vertical-align: top;
            }
            th { 
                background-color: #0a253c; 
                color: white;
                font-weight: bold; 
                text-align: center;
            }
            tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            tbody tr:hover {
                background-color: #f5f5f5;
            }
            .center { text-align: center; }
            .numero { text-align: right; }
            .fecha { font-family: monospace; }
            .dni { 
                font-family: monospace; 
                background: #f0f0f0;
                padding: 2px 6px;
                border-radius: 3px;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
                font-size: 11px;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            @media print { 
                body { margin: 0; font-size: 10px; }
                .header h1 { font-size: 18px; }
                .header h2 { font-size: 14px; }
                th, td { padding: 4px; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>REPORTE DE ENTREGAS</h1>
            <h2>GRUPO SEAL - Sistema de Gestión</h2>';
    
    if ($categoria_info) {
        echo '<h3 style="color: #0a253c; margin: 10px 0;">Categoría: ' . htmlspecialchars($categoria_info['nombre']) . '</h3>';
    }
    
    echo '</div>
        
        <div class="info">
            <div class="info-row">
                <span class="info-label">Almacén:</span>
                <span>' . htmlspecialchars($almacen_info['nombre']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Ubicación:</span>
                <span>' . htmlspecialchars($almacen_info['ubicacion']) . '</span>
            </div>';
    
    if ($categoria_info) {
        echo '<div class="info-row">
                <span class="info-label">Categoría:</span>
                <span>' . htmlspecialchars($categoria_info['nombre']) . '</span>
              </div>';
    }
    
    echo '<div class="info-row">
            <span class="info-label">Fecha del reporte:</span>
            <span>' . date('d/m/Y H:i') . '</span>
          </div>
          <div class="info-row">
            <span class="info-label">Total de entregas:</span>
            <span><strong>' . count($entregas) . '</strong></span>
          </div>
        </div>';
    
    // Mostrar filtros aplicados
    $filtros_aplicados = [];
    if (!empty($filtros['nombre'])) $filtros_aplicados[] = '<strong>Nombre:</strong> ' . htmlspecialchars($filtros['nombre']);
    if (!empty($filtros['dni'])) $filtros_aplicados[] = '<strong>DNI:</strong> ' . htmlspecialchars($filtros['dni']);
    if (!empty($filtros['fecha_inicio'])) $filtros_aplicados[] = '<strong>Desde:</strong> ' . htmlspecialchars($filtros['fecha_inicio']);
    if (!empty($filtros['fecha_fin'])) $filtros_aplicados[] = '<strong>Hasta:</strong> ' . htmlspecialchars($filtros['fecha_fin']);
    
    if (!empty($filtros_aplicados)) {
        echo '<div class="filtros">
                <strong>Filtros aplicados:</strong><br>
                ' . implode(' | ', $filtros_aplicados) . '
              </div>';
    }
    
    echo '<table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Destinatario</th>
                    <th>DNI</th>
                    <th>Categoría</th>
                    <th>Producto</th>
                    <th>Modelo</th>
                    <th>Color</th>
                    <th>Talla</th>
                    <th>Cant.</th>
                    <th>Unidad</th>
                    <th>Responsable</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($entregas as $entrega) {
        $responsable = '';
        if (!empty($entrega['usuario_responsable'])) {
            $responsable = $entrega['usuario_responsable'];
            if (!empty($entrega['usuario_apellidos'])) {
                $responsable .= ' ' . $entrega['usuario_apellidos'];
            }
        } else {
            $responsable = 'No registrado';
        }
        
        echo '<tr>
                <td class="fecha center">' . date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])) . '</td>
                <td>' . htmlspecialchars($entrega['nombre_destinatario']) . '</td>
                <td class="center"><span class="dni">' . htmlspecialchars($entrega['dni_destinatario']) . '</span></td>
                <td>' . htmlspecialchars($entrega['categoria_nombre']) . '</td>
                <td><strong>' . htmlspecialchars($entrega['producto_nombre']) . '</strong></td>
                <td>' . htmlspecialchars($entrega['modelo'] ?: '-') . '</td>
                <td>' . htmlspecialchars($entrega['color'] ?: '-') . '</td>
                <td>' . htmlspecialchars($entrega['talla_dimensiones'] ?: '-') . '</td>
                <td class="center numero"><strong>' . $entrega['cantidad'] . '</strong></td>
                <td class="center">' . htmlspecialchars($entrega['unidad_medida']) . '</td>
                <td>' . htmlspecialchars($responsable) . '</td>
              </tr>';
    }
    
    echo '</tbody>
        </table>
        
        <div class="footer">
            <p>Reporte generado el ' . date('d/m/Y \a \l\a\s H:i') . ' por el Sistema de Gestión GRUPO SEAL</p>
            <p><strong>Nota:</strong> Este documento puede imprimirse o guardarse como PDF usando las opciones del navegador (Ctrl+P)</p>
        </div>
    </body>
    </html>';
    
    exit();
}
?>