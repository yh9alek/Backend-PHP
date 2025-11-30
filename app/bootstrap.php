<?php

// 1. Cargar el autoloader de Composer
require_once __DIR__.'/../vendor/autoload.php';

use app\Router;

use app\Core\Container;
use app\Core\View;
use app\Core\Request;

use app\Middleware\AuthMiddleware;
use app\Middleware\GuestMiddleware;

use app\services\AuthService;
use app\services\JwtService;
use app\services\MenuService;

use app\Controllers\LoginController;
use app\Controllers\AppController;
use app\Controllers\AreaController;
use app\Controllers\AssetsController;
use app\Controllers\PerfilController;
use app\Controllers\UsuariosController;

// 2. Cargar las variables de entorno
use Dotenv\Dotenv as ENV;
ENV::createImmutable(dirname(__DIR__))->load();

    // Configurar el entorno (DEBUG y MANEJO DE ERRORES)
    define('APP_DEBUG', $_ENV['APP_DEBUG'] === 'true');

    // Establece un manejador de excepciones global
    set_exception_handler(function(Throwable $e) {
        if (APP_DEBUG) {
            http_response_code(500);
            echo '<h1>Error 500 - DEBUG MODE</h1><hr>';
            echo '<b>Message:</b> ' . $e->getMessage() . '<br>';
            echo '<b>File:</b> ' . $e->getFile() . ' on line ' . $e->getLine() . '<br>';
            echo '<h3>Stack Trace:</h3><pre>' . $e->getTraceAsString() . '</pre>';
        } else {

            http_response_code(500);        
            require __DIR__ . '/views/templates/_500.php';
        }
    });

// 3. Crear y configurar el Contenedor de Inyección de Dependencias
$container = new Container();

    $container->set(Request::class, fn() => Request::getRequest());

    # --- Registro de Servicios/Dependencias ---

    $container->set(View::class, function(Container $c) {
        // Inyectamos Request en la Vista al crearla
        $view = new View(__DIR__ . '/views');
        $view->setRequest($c->get(Request::class));
        return $view;
    });

    # --- SERVICIOS JWT Y AUTENTICACIÓN --- #

    $container->set(JwtService::class, fn() => new JwtService(

        $_ENV['JWT_SECRET_KEY'],'HS512',
        (int)($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600)

    ));

    $container->set(AuthService::class, fn(Container $c) => new AuthService(
        $c->get(JwtService::class)
    ));

    # --- MIDDLEWARES --- #

    $container->set(AuthMiddleware::class, fn(Container $c) => new AuthMiddleware(
        $c->get(JwtService::class),
        $c->get(View::class)
    ));
    
    $container->set(GuestMiddleware::class, fn(Container $c) => new GuestMiddleware(
        $c->get(JwtService::class)
    ));

    # --- SERVICIOS --- #

    $container->set(MenuService::class, fn() => new MenuService);

    # --- CONTROLADORES --- #

    $container->set(LoginController::class,    fn(Container $c) => new LoginController( $c ));
    $container->set(AppController::class,      fn(Container $c) => new AppController( $c ));
    $container->set(AssetsController::class,   fn(Container $c) => new AssetsController( $c ));

    $container->set(UsuariosController::class, fn(Container $c) => new UsuariosController( $c ));
    $container->set(PerfilController::class,   fn(Container $c) => new PerfilController( $c ));
    $container->set(AreaController::class,     fn(Container $c) => new AreaController( $c ));

    # ------------------------------------------


// 4. Crear el Router y pasarle el contenedor
$router = new Router($container);

    // Configurar los Middlewares a utilizar.
    $router->addMiddleware('auth', AuthMiddleware::class);
    $router->addMiddleware('guest', GuestMiddleware::class);

// 5. Devolver el Router configurado
return $router;