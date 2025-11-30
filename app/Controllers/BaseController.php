<?php

namespace app\Controllers;

use app\Core\Container;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Core\View;
use app\services\AuthService;
use app\services\JwtService;
use app\services\MenuService;

use function app\helpers\route;

abstract class BaseController {

    protected View $view;
    protected Request $request;
    protected JwtService $jwtService;
    protected AuthService $authService;
    protected MenuService $menuService;

    protected Container $container;

    /**
     * Inyección de dependencias para los controladores
     */
    public function __construct(Container $container)
    {   
        $this->container    = $container;
        $this->view         = $container->get(View::class);
        $this->request      = $container->get(Request::class);
        $this->jwtService   = $container->get(JwtService::class);
        $this->authService  = $container->get(AuthService::class);
        $this->menuService  = $container->get(MenuService::class);
    }

    /**
     * Valida los datos de una petición.
     * Si la validación falla, envía una respuesta JSON de error 400 y termina el script.
     * Si tiene éxito, no hace nada y el controlador puede continuar.
     *
     * @param Request $request El objeto de la petición.
     * @param array $rules Las reglas de validación.
     */
    protected function validate(Request $request, array $rules): Response
    {
        // Obtenemos todos los parámetros de la petición como un array
        $dataToValidate = $request->allParams();

        $validator = Validator::make($dataToValidate, $rules);

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 400);
        }

        return new Response(statusCode: 200);
    }

    /**
     * Renderiza una vista y la devuelve como un objeto Response.
     */
    protected function view(string $viewName, array $data = [], string $layout = '_login'): Response
    {
        $content = $this->view->render($viewName, $data, $layout);
        return new Response($content);
    }

    /**
     * Renderiza solo un módulo (vista parcial) sin el layout.
     * Devuelve el HTML del módulo como un objeto Response.
     * 
     * @param string $viewName Nombre de la vista/módulo a renderizar.
     * @param array  $data Datos a pasar a la vista.
     * @return Response
     */
    protected function partial(string $viewName, array $data = []): Response
    {
        $content = $this->view->renderPartial($viewName, $data);
        return new Response($content);
    }

    /**
     * Devuelve una respuesta de redirección.
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return new Response('', $statusCode, ['Location' => $url]);
    }
    
    /**
     * Devuelve una respuesta de redirección a una ruta con nombre.
     */
    protected function redirectToRoute(string $routeName, array $params = [], int $statusCode = 302): Response
    {
        $url = route($routeName, $params);
        return $this->redirect($url, $statusCode);
    }

    /**
     * Devuelve una respuesta JSON.
     */
    protected function json(array $data, int $statusCode = 200): Response
    {
        $content = json_encode($data);
        return new Response($content, $statusCode, ['Content-Type' => 'application/json']);
    }
}