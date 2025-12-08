<?php

// 1. Cargar el autoloader de Composer
require_once __DIR__.'/../vendor/autoload.php';

use app\Router;

use app\Core\Container;
use app\Core\View;
use app\Core\Response;
use app\Core\Request;

use app\Middleware\AuthMiddleware;

use app\Services\KeycloakService;
use app\Services\AuthService;
use app\Services\MenuService;

use app\Controllers\LoginController;
use app\Controllers\AppController;
use app\Controllers\AreaController;
use app\Controllers\AssetsController;
use app\Controllers\PerfilController;
use app\Controllers\UsuariosController;

use Dotenv\Dotenv as ENV;

// Asegurar que los errores no se muestren
ini_set('display_errors', '0');
error_reporting(0);

// Cargar variables de entorno con manejo de errores
try {
    $dotenv = ENV::createImmutable(dirname(__DIR__));
    $dotenv->load();
    
} catch (\Dotenv\Exception\InvalidFileException $e) {
    // Error de sintaxis en .env
    error_log('Error al parsear .env: ' . $e->getMessage());
    
    // Respuesta JSON limpia
    http_response_code(500);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => 'Error de configuración',
        'message' => 'El archivo de configuración tiene errores de sintaxis. Contacte al administrador.',
    ], JSON_UNESCAPED_UNICODE);
    
    exit(1);
    
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Archivo .env no encontrado
    error_log('.env no encontrado: ' . $e->getMessage());
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => 'Error de configuración',
        'message' => 'Archivo de configuración no encontrado.',
    ], JSON_UNESCAPED_UNICODE);
    
    exit(1);
    
} catch (\Exception $e) {
    // Cualquier otro error
    error_log('Error desconocido al cargar .env: ' . $e->getMessage());
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => 'Error de configuración',
        'message' => 'Error al cargar la configuración del sistema.',
    ], JSON_UNESCAPED_UNICODE);
    
    exit(1);
}

// Configurar manejo de errores basado en APP_ENV
$appEnv = $_ENV['APP_ENV'] ?? 'production';
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'development' && $appDebug) {
    // Desarrollo: mostrar errores en logs pero NO en pantalla
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    
    // Crear directorio de logs si no existe
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    // Producción: silencio total en pantalla
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    ini_set('error_log', $logDir . '/php_errors.log');
}

// Crear el contenedor de dependencias
$container = new Container();

// Request
$container->set(Request::class, function () {
    return new Request();
});

// Response
$container->set(Response::class, function () {
    return new Response();
});

# --- Registro de Servicios/Dependencias ---

$container->set(View::class, function () {
    return new View(__DIR__ . '/views');
});

# --- SERVICIOS --- #

$container->set(MenuService::class, fn() => new MenuService);

$container->set(KeycloakService::class, function () {
    return new KeycloakService();
});

$container->set(AuthService::class, function (Container $container) {
    return new AuthService(
        $container->get(KeycloakService::class)
    );
});

# --- MIDDLEWARES --- #

$container->set(AuthMiddleware::class, fn(Container $c) => new AuthMiddleware(
    $c->get(KeycloakService::class),
));

# --- CONTROLADORES --- #

$container->set(LoginController::class,    fn(Container $c) => new LoginController( $c ));
$container->set(AppController::class,      fn(Container $c) => new AppController( $c ));
$container->set(AssetsController::class,   fn(Container $c) => new AssetsController( $c ));

$container->set(UsuariosController::class, fn(Container $c) => new UsuariosController( $c ));
$container->set(PerfilController::class,   fn(Container $c) => new PerfilController( $c ));
$container->set(AreaController::class,     fn(Container $c) => new AreaController( $c ));

# ------------------------------------------


// 4. Crear el Router y pasarle el contenedor
$container->set(Router::class, function (Container $container) {
    return new Router($container);
});

// 5. Devolver el Router configurado
return $container;