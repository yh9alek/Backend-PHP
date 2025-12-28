<?php

namespace app\Core;

use stdClass;

class Request
{
    private array $queryParams = [];
    private array $postParams = [];
    private array $routeParams = [];
    private array $files = [];
    private array $cookies = [];
    private string $method;
    private string $uri;
    
    private ?stdClass $authUser = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->queryParams = $_GET;
        $this->cookies = $_COOKIE;
        
        // Manejar JSON en el body para APIs
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            $json = file_get_contents('php://input');
            $this->postParams = json_decode($json, true) ?? [];
        } else {
            $this->postParams = $_POST;
        }
        
        $this->files = $_FILES;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Establece parámetros extraídos de la ruta.
     * Este método es llamado por Router cuando coincide una ruta con parámetros.
     * 
     * @param array $params Parámetros de la ruta (ej: ['id' => '2'])
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Obtiene un parámetro de cualquier fuente (ruta, POST, GET).
     * Prioridad: Ruta > POST > GET
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function param(string $key, $default = null)
    {
        return $this->routeParams[$key]     // Primero busca en parámetros de ruta
            ?? $this->postParams[$key]      // Luego en POST
            ?? $this->queryParams[$key]     // Luego en GET
            ?? $default;
    }

    /**
     * Obtiene un parámetro SOLO de query string (GET)
     */
    public function query(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Obtiene un parámetro SOLO de POST
     */
    public function post(string $key, $default = null)
    {
        return $this->postParams[$key] ?? $default;
    }

    /**
     * Obtiene una cookie por su nombre
     * 
     * @param string $key Nombre de la cookie
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Verifica si existe una cookie
     * 
     * @param string $key Nombre de la cookie
     * @return bool
     */
    public function hasCookie(string $key): bool
    {
        return isset($this->cookies[$key]);
    }

    /**
     * Obtiene todas las cookies
     * 
     * @return array
     */
    public function allCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Obtiene TODOS los parámetros de todas las fuentes combinados
     */
    public function allParams(): array
    {
        return array_merge(
            $this->routeParams,    // Incluye parámetros de ruta
            $this->queryParams,
            $this->postParams
        );
    }

    /**
     * Obtiene SOLO los parámetros de ruta
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Verifica si existe un parámetro de ruta
     */
    public function hasRouteParam(string $key): bool
    {
        return isset($this->routeParams[$key]);
    }

    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    // --- MÉTODOS DE AUTENTICACIÓN ---

    /**
     * Alias de user() para compatibilidad con middlewares.
     * Ambos métodos retornan el mismo objeto de usuario autenticado.
     */
    public function getAuthUser(): ?stdClass
    {
        return $this->authUser;
    }

    public function setAuthUser(stdClass $user): void
    {
        $this->authUser = $user;
    }

    public function user(): ?stdClass
    {
        return $this->authUser;
    }

    public function isAuthenticated(): bool
    {
        return $this->authUser !== null;
    }

    public function userId(): ?string
    {
        return $this->authUser?->sub ?? null;
    }

    public function userEmail(): ?string
    {
        return $this->authUser?->email ?? null;
    }

    public function username(): ?string
    {
        return $this->authUser?->preferred_username ?? null;
    }

    /**
     * Obtiene un parámetro sanitizado como string
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->param($key, $default);
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtiene un parámetro como entero
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->param($key, $default);
    }

    /**
     * Obtiene un parámetro como booleano
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->param($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Obtiene solo los campos especificados
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->allParams(), array_flip($keys));
    }

    /**
     * Obtiene todos los campos excepto los especificados
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->allParams(), array_flip($keys));
    }
}