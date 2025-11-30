<?php

// 1. Iniciar la sesión para toda la aplicación.
session_start();

// 2. Cargar el contenedor de dependencias y crear el Router.
$router = require_once __DIR__.'/../app/bootstrap.php';

// 3. Crear un objeto Request con la petición actual del usuario.
$request = \app\Core\Request::getRequest();

// 4. Cargar el archivo de definiciones de rutas para nuestro sistema.
require_once __DIR__.'/../app/routes/web.php';

// 5. Resolvemos la petición del usuario con el Router, generando una respuesta.
$response = $router->resolve($request);

// 6. Envía la respuesta final (código, cabeceras y contenido) al navegador.
$response->send();