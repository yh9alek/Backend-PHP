<?php

namespace app;

use app\Core\Container;
use app\Core\Request;
use app\Core\Response;
use Closure;

class Router
{
    private array $routes = [];
    private array $groupAttributes = [];
    private array $namedRoutes = [];

    public function __construct(private Container $container) {}

    public function group(array $attributes, Closure $callback): void
    {
        $previousGroupAttributes = $this->groupAttributes;
        $this->groupAttributes = array_merge_recursive($this->groupAttributes, $attributes);
        
        $callback($this);
        
        $this->groupAttributes = $previousGroupAttributes;
    }

    public function get(string $uri, $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function patch(string $uri, $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function name(string $name): self
    {
        $lastRoute = array_key_last($this->routes);
        if ($lastRoute !== null) {
            $this->namedRoutes[$name] = $this->routes[$lastRoute]['uri'];
        }
        return $this;
    }

    private function addRoute(string $method, string $uri, $action): self
    {
        $prefix = $this->groupAttributes['prefix'] ?? '';
        $middleware = $this->groupAttributes['middleware'] ?? [];

        $fullUri = $prefix . $uri;

        $this->routes[] = [
            'method' => $method,
            'uri' => $fullUri,
            'action' => $action,
            'middleware' => $middleware
        ];

        return $this;
    }

    public function resolve(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // Manejar CORS preflight (OPTIONS)
        if ($method === 'OPTIONS') {
            return $this->handleCorsPreflightGlobal();
        }

        foreach ($this->routes as $route) {
            // Convertir parámetros de ruta como {id} a expresiones regulares
            $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                // Extraer SOLO los parámetros nombrados (no los numéricos)
                $params = array_filter($matches, function($key) {
                    return is_string($key);
                }, ARRAY_FILTER_USE_KEY);

                // ⭐ IMPORTANTE: Usar setRouteParams() en lugar de $_REQUEST
                $request->setRouteParams($params);

                return $this->executeRoute($route, $request);
            }
        }

        return $this->notFoundResponse();
    }

    private function executeRoute(array $route, Request $request): Response
    {
        $middleware = $route['middleware'];
        $action = $route['action'];

        // Crear la cadena de middleware
        $next = function ($request) use ($action) {
            return $this->callAction($action, $request);
        };

        // Aplicar middleware en orden inverso
        foreach (array_reverse($middleware) as $middlewareClass) {
            $next = function ($request) use ($middlewareClass, $next) {
                [$class, $params] = $this->parseMiddleware($middlewareClass);
                
                $middlewareInstance = $this->container->get($class);
                return $middlewareInstance->handle($request, $next, ...$params);
            };
        }

        return $next($request);
    }

    private function parseMiddleware(string $middleware): array
    {
        $parts = explode(':', $middleware);
        $class = $this->resolveMiddlewareClass($parts[0]);
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];
        
        return [$class, $params];
    }

    private function resolveMiddlewareClass(string $alias): string
    {
        $middlewareMap = [
            'auth' => \app\Middleware\AuthMiddleware::class,
        ];

        return $middlewareMap[$alias] ?? $alias;
    }

    private function callAction($action, Request $request): Response
    {
        if (is_array($action)) {
            [$controller, $method] = $action;
            $controllerInstance = $this->container->get($controller);
            return $controllerInstance->$method($request);
        }

        if (is_callable($action)) {
            return $action($request);
        }

        return $this->notFoundResponse();
    }

    private function notFoundResponse(): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'error' => 'Endpoint no encontrado',
                'code' => 404
            ]),
            404,
            ['Content-Type' => 'application/json']
        );
    }

    private function handleCorsPreflightGlobal(): Response
    {
        return new Response('', 204, [
            'Access-Control-Allow-Origin' => $this->getAllowedOrigin(),
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400'
        ]);
    }

    private function getAllowedOrigin(): string
    {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:8080',
            'https://tudominio.com'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            return '*';
        }

        return '';
    }

    public function getNamedRoute(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $uri = $this->namedRoutes[$name];

        foreach ($params as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
        }

        return $uri;
    }

    public function loadRoutes(string $file): void
    {
        $router = $this;
        require $file;
    }
}