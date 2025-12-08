<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\Services\KeycloakService;
use Closure;

/**
 * Middleware que valida tokens JWT de Keycloak.
 * Ya no usa cookies, solo valida el header Authorization.
 */
class AuthMiddleware
{
    public function __construct(
        private KeycloakService $keycloakService
    ) {}

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Extraer el token del header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Token no proporcionado.');
        }

        $token = substr($authHeader, 7); // Remover "Bearer "

        // 2. Validar el token con Keycloak
        $decodedToken = $this->keycloakService->validateToken($token);

        if (!$decodedToken) {
            return $this->unauthorizedResponse('Token inválido o expirado.');
        }

        // 3. Agregar información del usuario al request para uso posterior
        $request->setAuthUser($decodedToken);

        // 4. Continuar con la petición
        return $next($request);
    }

    /**
     * Respuesta estándar de error 401.
     */
    private function unauthorizedResponse(string $message): Response
    {
        $content = json_encode([
            'success' => false,
            'error' => $message,
            'code' => 401
        ]);

        return new Response($content, 401, ['Content-Type' => 'application/json']);
    }
}