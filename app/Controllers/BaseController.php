<?php

namespace app\Controllers;

use app\Core\Container;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AuthService;
use app\Services\KeycloakService;
use app\Helpers\Logger;

abstract class BaseController
{
    protected Container $container;
    protected Request $request;
    protected AuthService $authService;
    protected KeycloakService $keycloakService;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $container->get(Request::class);
        $this->authService = $container->get(AuthService::class);
        $this->keycloakService = $container->get(KeycloakService::class);
    }

    /**
     * Valida los datos de una petición.
     */
    protected function validate(Request $request, array $rules): Response
    {
        $dataToValidate = $request->allParams();
        $validator = Validator::make($dataToValidate, $rules);

        if ($validator->fails()) {            
            return $this->json($validator->getErrorResponse(), 400);
        }

        return new Response(statusCode: 200);
    }

    /**
     * Devuelve una respuesta JSON.
     */
    protected function json(array $data, int $statusCode = 200): Response
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return new Response(
            $content,
            $statusCode,
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => $this->getAllowedOrigin(),
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Allow-Credentials' => 'true'
            ]
        );
    }

    /**
     * Devuelve una respuesta de error estandarizada.
     */
    protected function error(string $message, int $statusCode = 500, array $details = []): Response
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($details)) {
            // En producción no mostrar detalles técnicos
            if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
                $response['details'] = $details;
            }
        }

        return $this->json($response, $statusCode);
    }

    /**
     * Devuelve una respuesta de éxito estandarizada.
     */
    protected function success($data = null, ?string $message = null, int $statusCode = 200): Response
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->json($response, $statusCode);
    }

    /**
     * Obtiene el usuario autenticado desde el request.
     */
    protected function user(): ?\stdClass
    {
        return $this->request->user();
    }

    /**
     * Verifica si el usuario tiene un rol específico.
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $this->keycloakService->hasRole($user, $role);
    }

    /**
     * Obtiene el origen permitido para CORS.
     */
    private function getAllowedOrigin(): string
    {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
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

    /**
     * Maneja las peticiones OPTIONS para CORS preflight.
     */
    protected function handlePreflight(): Response
    {
        return new Response('', 204, [
            'Access-Control-Allow-Origin' => $this->getAllowedOrigin(),
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400'
        ]);
    }

    /**
     * Captura y maneja excepciones en los controladores.
     */
    protected function handleException(\Throwable $e): Response
    {
        // Log de la excepción
        Logger::exception($e, [
            'endpoint' => $this->request->uri(),
            'method' => $this->request->method(),
            'user' => $this->user()?->sub ?? 'guest',
        ]);

        // Determinar código de estado
        $statusCode = 500;

        // Respuesta según entorno
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            return $this->error(
                $e->getMessage(),
                $statusCode,
                [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'type' => get_class($e),
                ]
            );
        }

        return $this->error(
            'Ha ocurrido un error interno. Por favor contacte al administrador.',
            $statusCode
        );
    }
}