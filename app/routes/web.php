<?php

use app\Controllers\AppController;
use app\Controllers\AreaController;
use app\Controllers\AssetsController;
use app\Controllers\LoginController;
use app\Controllers\PerfilController;
use app\Controllers\UsuariosController;
use app\Router;

/** @var Router $router */

# --- GRUPO DE RUTAS PARA "INVITADOS" (usuarios no autenticados) ---
$router->group(['middleware' => ['guest']], function (Router $router) {

    $router->get('/login',   [LoginController::class, 'mostrarLogin'])  ->name('login.show');
    $router->post('/login',  [LoginController::class, 'validarAcceso']) ->name('login.submit');

});

# --- GRUPO DE RUTAS PARA CARGAR HTML COMPLETO (Autenticación Requerida) ---
$router->group(['middleware' => ['auth']], function (Router $router) {

    $router->get('/api/assets', [AssetsController::class,  'serve']);
    $router->get('/inicio',     [AppController::class,     'inicio']);
    $router->get('/',           [AppController::class,     'inicio']);
    $router->get('/logout',     [LoginController::class,   'logout']) ->name('logout');
    $router->get('/proc',       [UsuariosController::class,'proc']);

});

# --- GRUPO DE RUTAS PARA PETICIONES HTTP PROTEGIDAS (Autenticación Requerida) ---
$router->group(['middleware' => ['auth:api']], function ($router) {

    $router->get('/tiempo_sesion',                 [AppController::class,      'obtenerTiempoSesion']);
    $router->post('/usuarios',                     [UsuariosController::class, 'mostrarModuloUsuarios']);
    $router->post('/usuarios/obtenerUsuarios',     [UsuariosController::class, 'obtenerUsuariosSinORM']);
    $router->post('/usuarios/obtenerUsuariosProc', [UsuariosController::class, 'obtenerUsuariosProc']);
    $router->post('/usuarios/registrar',           [UsuariosController::class, 'guardarUsuario']);

    $router->post('/perfil/obtenerPerfiles',       [PerfilController::class,   'obtenerPerfiles']);
    $router->post('/area/obtenerAreas',            [AreaController::class,     'obtenerAreas']);

});