<?php

namespace app\Core;

use stdClass;

class Request
{
    private array $queryParams = [];
    private array $postParams = [];
    private array $routeParams = [];
    private array $files = [];
    private string $method;
    private string $uri;
    
    private ?stdClass $authUser = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->queryParams = $_GET;
        
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
}