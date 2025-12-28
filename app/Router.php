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

        // ✅ Manejar CORS preflight (OPTIONS)
        if ($method === 'OPTIONS') {
            return $this->addCorsHeaders($this->handleCorsPreflight());
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

                $request->setRouteParams($params);

                // ✅ Agregar headers CORS a la respuesta
                $response = $this->executeRoute($route, $request);
                return $this->addCorsHeaders($response);
            }
        }

        // ✅ Agregar headers CORS también al 404
        return $this->addCorsHeaders($this->notFoundResponse());
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
            'role' => \app\Middleware\RequireClientRoleMiddleware::class,
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
            content: json_encode([
                'success' => false,
                'error' => 'Endpoint no encontrado',
                'code' => 404
            ]),
            statusCode: 404,
            headers: ['Content-Type' => 'application/json']
        );
    }

    private function handleCorsPreflight(): Response
    {
        return new Response(content: '', statusCode: 204);
    }

    /**
     * Retorna una nueva instancia de Response con headers CORS
     */
    private function addCorsHeaders(Response $response): Response
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin'      => $this->getAllowedOrigin(),
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true', // ✅ CRÍTICO para cookies
            'Access-Control-Max-Age'           => '86400',
        ];

        // ✅ Usa withHeaders() que retorna una nueva instancia
        return $response->withHeaders($corsHeaders);
    }

    /**
     * Obtiene el origen permitido para CORS
     * 
     * ✅ ACTUALIZADO: Leer desde .env y agregar todos los puertos comunes
     */
    private function getAllowedOrigin(): string
    {
        // Intentar leer desde .env
        $envOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        
        $allowedOrigins = [];
        
        if (!empty($envOrigins)) {
            // Si existe en .env, parsear la lista
            $allowedOrigins = array_map('trim', explode(',', $envOrigins));
        } else {
            // Valores por defecto para desarrollo
            $allowedOrigins = [
                // Localhost con diferentes puertos
                'https://siabi.tmaz.mx',     // Vite (puerto por defecto)
                'http://localhost:3000',     // React/Next.js
                'http://localhost:4200',     // Angular
                'http://localhost:8080',     // Vue CLI / Keycloak
                
                // 127.0.0.1 (equivalente a localhost)
                // 'http://127.0.0.1:5173',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:4200',
                'http://127.0.0.1:8080',
                
                // Producción (agregar los dominios)
                // 'https://siabi.tudominio.com',
                // 'https://siga.tudominio.com',
                // 'https://sigep.tudominio.com',
                // 'https://sian.tudominio.com',
            ];
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Si el origin está en la lista permitida, retornarlo
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        // En desarrollo, permitir cualquier origin local
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            // Permitir cualquier localhost o 127.0.0.1
            if (preg_match('#^https?://(localhost|127\.0\.0\.1):\d+$#', $origin)) {
                return $origin;
            }
            return $origin ?: '*';
        }

        // En producción, no permitir nada que no esté en la lista
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