<?php
// Configuración de debugging para transferencias
// Este archivo debe ser incluido en procesar_formulario.php

// Habilitar errores solo en desarrollo
if (isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    
    // Entorno de desarrollo
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    define('DEBUG_MODE', true);
} else {
    // Entorno de producción
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE);
    
    define('DEBUG_MODE', false);
}

// Configurar logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/transferencia_errors.log');

// Función de debugging
function debug_log($message, $context = []) {
    if (DEBUG_MODE) {
        $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
        
        // También mostrar en pantalla si estamos en desarrollo
        if (DEBUG_MODE) {
            echo '<div style="background:#f0f0f0;padding:5px;margin:2px;border-left:3px solid #007bff;">';
            echo '<strong>DEBUG:</strong> ' . htmlspecialchars($message);
            if (!empty($context)) {
                echo '<pre>' . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT)) . '</pre>';
            }
            echo '</div>';
        }
    }
}

// Función para verificar requisitos del sistema
function verificar_requisitos() {
    $errores = [];
    
    // Verificar extensiones PHP necesarias
    if (!extension_loaded('mysqli')) {
        $errores[] = 'Extensión MySQLi no está instalada';
    }
    
    if (!extension_loaded('json')) {
        $errores[] = 'Extensión JSON no está instalada';
    }
    
    // Verificar permisos de directorio
    if (!is_writable(__DIR__ . '/logs')) {
        $errores[] = 'Directorio de logs no tiene permisos de escritura';
    }
    
    return $errores;
}

// Verificar al incluir este archivo
$errores_sistema = verificar_requisitos();
if (!empty($errores_sistema)) {
    foreach ($errores_sistema as $error) {
        error_log('REQUISITO FALTANTE: ' . $error);
    }
}
?>