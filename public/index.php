<?php

use app\Core\Request;
use app\Router;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// 2. DEFINIR MANEJADOR DE ERRORES PERSONALIZADO
set_error_handler(function ($severity, $message, $file, $line) {
    // Log interno pero no mostrar
    error_log("[$severity] $message en $file:$line");
    
    // No mostrar nada al usuario
    return true;
});

// 3. DEFINIR MANEJADOR DE EXCEPCIONES
set_exception_handler(function ($exception) {
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Log de la excepción
    error_log('Excepción no capturada: ' . $exception->getMessage());
    error_log('Stack trace: ' . $exception->getTraceAsString());
    
    // Respuesta JSON limpia
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Determinar si estamos en desarrollo o producción
    $isDevelopment = (getenv('APP_ENV') === 'development');
    
    if ($isDevelopment) {
        // En desarrollo: más detalles (pero sin stack trace completo)
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor',
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        // En producción: mensaje genérico
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor',
            'message' => 'Ha ocurrido un error. Por favor contacte al administrador.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    exit(1);
});

// 4. DEFINIR MANEJADOR DE ERRORES FATALES
register_shutdown_function(function () {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Limpiar buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Log del error fatal
        error_log("Error fatal: {$error['message']} en {$error['file']}:{$error['line']}");
        
        // Respuesta JSON
        http_response_code(500);
        header('Content-Type: application/json');
        
        $isDevelopment = (getenv('APP_ENV') === 'development');
        
        if ($isDevelopment) {
            echo json_encode([
                'success' => false,
                'error' => 'Error fatal',
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => 'Ha ocurrido un error crítico. Por favor contacte al administrador.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        exit(1);
    }
});

// 5. INICIAR OUTPUT BUFFERING (para poder limpiar output si hay error)
ob_start();

// 6. CARGAR BOOTSTRAP CON TRY-CATCH
try {

    $container = require_once __DIR__ . '/../app/bootstrap.php';
    $router = $container->get(Router::class);
    $router->loadRoutes(__DIR__ . '/../app/routes/api.php');
    $request = $container->get(Request::class);
    $response = $router->resolve($request);
    
    // Limpiar buffer y enviar respuesta
    ob_end_clean();
    $response->send();
    
} catch (\Throwable $e) {
    // Limpiar buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log
    error_log('Error en index.php: ' . $e->getMessage());
    error_log('Stack: ' . $e->getTraceAsString());
    
    // Respuesta JSON limpia
    http_response_code(500);
    header('Content-Type: application/json');
    
    $isDevelopment = (getenv('APP_ENV') === 'development');
    
    if ($isDevelopment) {
        echo json_encode([
            'success' => false,
            'error' => 'Error en la inicialización',
            'message' => $e->getMessage(),
            'type' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor',
            'message' => 'No se pudo procesar la solicitud.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}