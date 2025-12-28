<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\Services\KeycloakService;
use Closure;

/**
 * Middleware para validar roles específicos de cliente.
 * Usar después de AuthMiddleware en rutas que requieran roles específicos.
 */
class RequireClientRoleMiddleware
{
    private array $allowedRoles;
    private ?string $clientId;
    private bool $requireAll;

    public function __construct(
        private KeycloakService $keycloakService
    ) {}

    /**
     * Crea una instancia del middleware que requiere AL MENOS UNO de los roles.
     * 
     * @param array $allowedRoles Lista de roles permitidos
     * @param string|null $clientId Cliente específico (opcional)
     * @return self
     */
    public static function any(array $allowedRoles, ?string $clientId = null): self
    {
        $instance = new self(new KeycloakService());
        $instance->allowedRoles = $allowedRoles;
        $instance->clientId = $clientId;
        $instance->requireAll = false;
        return $instance;
    }

    /**
     * Crea una instancia del middleware que requiere TODOS los roles.
     * 
     * @param array $requiredRoles Lista de roles requeridos
     * @param string|null $clientId Cliente específico (opcional)
     * @return self
     */
    public static function all(array $requiredRoles, ?string $clientId = null): self
    {
        $instance = new self(new KeycloakService());
        $instance->allowedRoles = $requiredRoles;
        $instance->clientId = $clientId;
        $instance->requireAll = true;
        return $instance;
    }

    /**
     * Maneja la validación de roles.
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $decodedToken = $request->getAuthUser();

        if (!$decodedToken) {
            return $this->forbiddenResponse('Usuario no autenticado.');
        }

        // Validar roles
        $hasAccess = $this->requireAll
            ? $this->keycloakService->hasAllRoles($decodedToken, $this->allowedRoles, $this->clientId)
            : $this->keycloakService->hasAnyRole($decodedToken, $this->allowedRoles, $this->clientId);

        if (!$hasAccess) {
            $rolesStr = implode(', ', $this->allowedRoles);
            $condition = $this->requireAll ? 'todos estos roles' : 'uno de estos roles';
            return $this->forbiddenResponse("Acceso denegado. Se requiere {$condition}: {$rolesStr}");
        }

        return $next($request);
    }

    /**
     * Respuesta de error 403.
     */
    private function forbiddenResponse(string $message): Response
    {
        $content = json_encode([
            'success' => false,
            'error' => $message,
            'code' => 403
        ]);

        return new Response($content, 403, ['Content-Type' => 'application/json']);
    }
}