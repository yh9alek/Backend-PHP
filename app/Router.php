<?php
// app/Router.php

namespace app;

use app\Core\Container;
use app\Core\View;
use app\Core\Request;
use app\Core\Response;

class Router
{
    private array $routes = [];
    private Container $container;
    private array $middlewareStack = [];
    private array $middlewareMap = [];
    private array $prefixStack = [];

    # Rutas con alias para el action de formularios
    private static array $namedRoutes = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addMiddleware(string $alias, string $class): void
    {
        $this->middlewareMap[$alias] = $class;
    }


    private function addRoute(string $method, string $url, array $handler): void
    {
        // Une todos los prefijos de la pila actual.
        $prefix = implode('', $this->prefixStack);
        
        // Asegura que la URL empiece con una barra, pero no haya dobles barras.
        $finalUrl = $prefix . '/' . ltrim($url, '/');

        // Quita la barra final si no es la ruta raíz.
        if (strlen($finalUrl) > 1) {
            $finalUrl = rtrim($finalUrl, '/');
        }

        $this->routes[] = [
            'method'     => $method,
            'url'        => $finalUrl,
            'handler'    => $handler,
            'middleware' => $this->middlewareStack
        ];
    }

    /**
     * Asigna un nombre a la última ruta que fue añadida.
     * Permite la encadenación de métodos.
     *
     * @param string $name El nombre único para la ruta.
     * @return self
     */
    public function name(string $name): self
    {
        $lastRouteIndex = count($this->routes) - 1;

        if (isset($this->routes[$lastRouteIndex])) {
            // Comprueba si el nombre ya está en uso para evitar duplicados
            if (array_key_exists($name, self::$namedRoutes)) {
                throw new \Exception("El nombre de ruta '{$name}' ya está en uso.");
            }
            // Guarda la URL en la propiedad estática
            self::$namedRoutes[$name] = $this->routes[$lastRouteIndex]['url'];
        }

        return $this;
    }

    /**
     * Devuelve el mapa de rutas con nombre.
     * Necesario para que el helper global pueda acceder a las rutas.
     *
     * @return array
     */
    public static function getNamedRoutes(): array
    {
        return self::$namedRoutes;
    }

    public function get(string $url, array $fn): self
    {
        $this->addRoute('GET', $url, $fn);
        return $this;
    }

    public function post(string $url, array $fn): self
    {
        $this->addRoute('POST', $url, $fn);
        return $this;
    }

    /**
     * Define un grupo de rutas que compartirán middlewares y/o prefijos.
     * @param array $options Opciones como ['middleware' => [...], 'prefix' => '...']
     * @param callable $callback Una función que define las rutas del grupo.
     */
    public function group(array $options, callable $callback): void {
        // Guardamos el estado actual de la pila de middlewares.
        $initialMiddlewareStack = $this->middlewareStack;

        // Aplicamos los middlewares del grupo, si existen.
        if (isset($options['middleware'])) {
            
             // Aseguramos que los middlewares siempre sean un array.
            $middlewaresToAdd = is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $this->middlewareStack = array_merge($this->middlewareStack, $middlewaresToAdd);
        }
        
        // Añadimos el prefijo del grupo a la pila.
        if (isset($options['prefix'])) {
            $this->prefixStack[] = rtrim($options['prefix'], '/');
        }

        // Ejecutamos el callback que define las rutas dentro del grupo.
        $callback($this);

        // --- RESTAURAMOS EL ESTADO ANTERIOR ---
        // Quitamos el prefijo que acabamos de añadir para no afectar a otros grupos.
        if (isset($options['prefix'])) {
            array_pop($this->prefixStack);
        }

        // Restauramos la pila de middlewares a su estado original.
        $this->middlewareStack = $initialMiddlewareStack;
    }

    /**
     * Resuelve la petición, ejecuta los middlewares y el controlador, y devuelve una Respuesta.
     *
     * @param Request $request El objeto de la petición HTTP.
     * @return Response El objeto de la respuesta HTTP.
     */
    public function resolve(Request $request): Response
    {
        // Usamos el objeto Request
        $currentUrl = $request->uri;
        $method     = $request->method;

        // Buscar la ruta que coincida
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['url'] === $currentUrl) {

                // --- LÓGICA DE MIDDLEWARE ---

                // El "destino" final es el callback del controlador.
                $handlerCallback = function (Request $req) use ($route) {
                    $controllerClass = $route['handler'][0];
                    $methodName      = $route['handler'][1];

                    /** @var mixed $controllerInstance */
                    $controllerInstance = $this->container->get($controllerClass);

                    // El método del controlador ahora recibe el Request y debe devolver un Response.
                    return $controllerInstance->$methodName($req);
                };

                // Construimos la cadena de middlewares que se llamarán en orden inverso.
                // Cada middleware llamará al siguiente hasta llegar al controlador.
                $middlewares = array_reverse($route['middleware']);

                $callableToExecute = array_reduce(
                    $middlewares,
                    function ($next, $middlewareString) {
                        return function (Request $request) use ($next, $middlewareString) {

                            // --- PARSEO DE MIDDLEWARE ---
                            $parts = explode(':', $middlewareString, 2);
                            $alias = $parts[0];
                            $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

                            if (isset($this->middlewareMap[$alias])) {
                                $middlewareClass = $this->middlewareMap[$alias];
                                /** @var mixed $middlewareInstance */
                                $middlewareInstance = $this->container->get($middlewareClass);
                                
                                // Pasamos los parámetros al método handle.
                                return $middlewareInstance->handle($request, $next, ...$params);
                            }
                            return $next($request);
                        };
                    },
                    $handlerCallback
                );

                // Ejecutamos la cadena completa, empezando por el primer middleware.
                $response = $callableToExecute($request);

                // Asegurarnos de que el resultado siempre sea un objeto Response.
                if (!$response instanceof Response) {
                    // Si el controlador devolvió un string, lo encapsulamos.
                    if (is_string($response)) {
                        return new Response($response);
                    }
                    // Si no, es un error del programador.
                    throw new \Exception("El controlador para la ruta {$currentUrl} debe devolver una instancia de Response.");
                }

                return $response;
            }
        }

        // Si el bucle termina, no se encontró la ruta.
        // Devolvemos un Response 404.
        $view = $this->container->get(View::class);

        $content404 = $view->render('error', [

            'message'   => 'La ruta solicitada no existe en este servidor.',
            'errorCode' => 404

        ], '_error');

        return new Response($content404, 404);
    }
}
