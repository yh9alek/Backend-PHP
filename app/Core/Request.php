<?php

namespace app\Core;

class Request
{
    public readonly string $uri;
    public readonly string $method;
    public readonly array $get;
    public readonly array $post;

    private function __construct()
    {
        $this->uri    = strtok($_SERVER['REQUEST_URI'], '?');
        $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->get    = $_GET;
        $this->post   = $_POST;
    }

    /**
     * Crea una instancia de Request a partir de las variables globales de PHP.
     */
    public static function getRequest(): self
    {
        return new self();
    }

    /**
     * Obtiene un parámetro de la petición (POST tiene prioridad sobre GET).
     *
     * @param string     $key     La clave del parámetro.
     * @param mixed|null $default El valor por defecto si no se encuentra.
     * @return mixed
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /**
     * Obtiene todos los parámetros de la petición como un único array.
     * Los parámetros de POST/JSON tienen prioridad sobre los de GET en caso de claves duplicadas.
     *
     * @return array
     */
    public function allParams(): array
    {
        return array_merge($this->get, $this->post);
    }
}