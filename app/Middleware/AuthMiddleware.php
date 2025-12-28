<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\Services\KeycloakService;
use Closure;
use Exception;

/**
 * Middleware que valida tokens JWT de Keycloak.
 * Valida: firma, expiración, issuer, audience y realm roles requeridos.
 */
class AuthMiddleware
{
    public function __construct(
        private KeycloakService $keycloakService
    ) {}

    /**
     * Maneja la validación del token JWT.
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 1. Extraer el token del header Authorization
            $token = $this->extractBearerToken();

            if (!$token) {
                return $this->unauthorizedResponse('Token no proporcionado. Incluye el header: Authorization: Bearer <token>');
            }

            // 2. Validar el token con Keycloak
            // Esto valida: firma, expiración, issuer, audience y realm roles
            $decodedToken = $this->keycloakService->validateToken($token);

            if (isset($decodedToken->success) && !$decodedToken->success) {
                return $this->forbiddenResponse('Token inválido o no tienes acceso a este sistema.');
            }

            // 3. Agregar información del usuario al request para uso posterior
            $request->setAuthUser($decodedToken);

            // 4. Continuar con la petición
            return $next($request);

        } catch (Exception $e) {
            // Error durante la validación (puede incluir detalles de audience/roles inválidos)
            return $this->forbiddenResponse($e->getMessage());
        }
    }

    /**
     * Middleware adicional para validar roles específicos de cliente.
     * Usar como segundo middleware en rutas que requieran roles específicos.
     * 
     * Ejemplo de uso en router:
     * Route::get('/equipos', [Controller::class, 'index'])
     *      ->middleware(AuthMiddleware::class)
     *      ->middleware(RequireClientRole::with(['Equipos_Portuarios', 'Administrador']));
     * 
     * @param array $allowedRoles Roles de cliente permitidos
     * @param string|null $clientId Cliente específico (opcional)
     */
    public function requireClientRole(Request $request, Closure $next, array $allowedRoles, ?string $clientId = null): Response
    {
        $decodedToken = $request->getAuthUser();

        if (!$decodedToken) {
            return $this->unauthorizedResponse('Usuario no autenticado.');
        }

        // Verificar si tiene al menos uno de los roles permitidos
        $hasAccess = $this->keycloakService->hasAnyRole($decodedToken, $allowedRoles, $clientId);

        if (!$hasAccess) {
            $rolesStr = implode(', ', $allowedRoles);
            return $this->forbiddenResponse("Acceso denegado. Se requiere uno de estos roles: {$rolesStr}");
        }

        return $next($request);
    }

    /**
     * Middleware para validar que el usuario tenga TODOS los roles especificados.
     * 
     * @param array $requiredRoles Roles de cliente requeridos (todos)
     * @param string|null $clientId Cliente específico (opcional)
     */
    public function requireAllClientRoles(Request $request, Closure $next, array $requiredRoles, ?string $clientId = null): Response
    {
        $decodedToken = $request->getAuthUser();

        if (!$decodedToken) {
            return $this->unauthorizedResponse('Usuario no autenticado.');
        }

        $hasAllRoles = $this->keycloakService->hasAllRoles($decodedToken, $requiredRoles, $clientId);

        if (!$hasAllRoles) {
            $rolesStr = implode(', ', $requiredRoles);
            return $this->forbiddenResponse("Acceso denegado. Se requieren todos estos roles: {$rolesStr}");
        }

        return $next($request);
    }

    /**
     * Extrae el token Bearer del header Authorization.
     * Soporta tanto HTTP_AUTHORIZATION como Authorization header.
     * 
     * @return string|null
     */
    private function extractBearerToken(): ?string
    {
        // Intentar obtener de diferentes fuentes
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? apache_request_headers()['Authorization'] 
            ?? '';

        if (empty($authHeader)) {
            return null;
        }

        // Extraer el token después de "Bearer "
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Respuesta estándar de error 401 Unauthorized.
     * Se usa cuando el token no está presente o es inválido.
     */
    private function unauthorizedResponse(string $message): Response
    {
        $content = json_encode([
            'success' => false,
            'message' => $message,
        ]);

        return new Response($content, 401, ['Content-Type' => 'application/json']);
    }

    /**
     * Respuesta estándar de error 403 Forbidden.
     * Se usa cuando el usuario está autenticado pero no tiene permisos.
     */
    private function forbiddenResponse(string $message): Response
    {
        $content = json_encode([
            'success' => false,
            'message' => $message,
        ]);

        return new Response($content, 403, ['Content-Type' => 'application/json']);
    }
}