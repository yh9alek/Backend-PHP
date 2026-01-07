<?php

use app\Controllers\ArticulosController;
use app\Controllers\LoginController;
use app\Controllers\ModulosController;
use app\Controllers\UsuariosController;
use app\Router;

/** @var Router $router */

# ============================================================
# RUTAS PÚBLICAS (Sin autenticación)
# ============================================================

$router->group(['prefix' => '/api'], function (Router $router) {
    
    // Autenticación
    $router->post('/auth/login',   [LoginController::class, 'login']);
    $router->post('/auth/refresh', [LoginController::class, 'refresh']);
    
});

# ============================================================
# RUTAS PROTEGIDAS (Requieren autenticación)
# ============================================================

$router->group(['prefix' => '/api', 'middleware' => ['auth']], function (Router $router) {
    
    // --- AUTENTICACIÓN ---
    $router->post('/auth/logout', [LoginController::class, 'logout']);
    $router->get('/auth/me',      [LoginController::class, 'me']);

    // --- USUARIOS ---
    $router->get('/usuarios',              [UsuariosController::class, 'index']);
    $router->get('/usuarios/{id}',         [UsuariosController::class, 'show']);
    $router->post('/usuarios',             [UsuariosController::class, 'store']);
    $router->put('/usuarios/{id}',         [UsuariosController::class, 'update']);
    $router->delete('/usuarios/{id}',      [UsuariosController::class, 'destroy']);
    
    // // Endpoints específicos
    // $router->post('/usuarios/search',      [UsuariosController::class, 'search']);
    // $router->post('/usuarios/paginate',    [UsuariosController::class, 'paginate']);

    $router->get('/modulos/usuario',       [ModulosController::class, 'getUserModules']);
    $router->get('/modulos',               [ModulosController::class, 'index']);
    $router->get('/modulos/{id}',          [ModulosController::class, 'show']);

    $router->get('/articulos', [ArticulosController::class, 'index']);

});

# ============================================================
# NOTAS IMPORTANTES:
# ============================================================
# 
# 1. Todas las rutas usan /api como prefijo
# 2. No hay rutas para servir vistas (no más SSR)
# 3. El middleware 'auth' valida autenticación del usuario en Keycloak antes de proceder
# 4. Seguimos convenciones RESTful:

#    - GET    /recursos       → Listar todos
#    - GET    /recursos/{id}  → Obtener uno
#    - POST   /recursos       → Crear
#    - PUT    /recursos/{id}  → Actualizar
#    - DELETE /recursos/{id}  → Eliminar

# 5. El frontend consumirá estas APIs
