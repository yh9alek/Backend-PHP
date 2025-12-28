<?php

namespace app\Core;

/**
 * Clase para crear un objeto de respuesta estándar.
 * Inmutable - cada modificación retorna una nueva instancia.
 */
class Response
{
    public function __construct(
        public readonly string $content = '',
        public readonly int $statusCode = 200,
        public readonly array $headers = []
    ) {}

    /**
     *  Crea una nueva Response con un header adicional
     * Mantiene la inmutabilidad retornando una nueva instancia
     */
    public function withHeader(string $name, string $value): self
    {
        $newHeaders = $this->headers;
        $newHeaders[$name] = $value;

        return new self(
            content: $this->content,
            statusCode: $this->statusCode,
            headers: $newHeaders
        );
    }

    /**
     *  Crea una nueva Response con múltiples headers
     */
    public function withHeaders(array $headers): self
    {
        $newHeaders = array_merge($this->headers, $headers);

        return new self(
            content: $this->content,
            statusCode: $this->statusCode,
            headers: $newHeaders
        );
    }

    /**
     *  Crea una nueva Response con un código de estado diferente
     */
    public function withStatusCode(int $statusCode): self
    {
        return new self(
            content: $this->content,
            statusCode: $statusCode,
            headers: $this->headers
        );
    }

    /**
     *  Crea una nueva Response con contenido diferente
     */
    public function withContent(string $content): self
    {
        return new self(
            content: $content,
            statusCode: $this->statusCode,
            headers: $this->headers
        );
    }

    /**
     * Envía la respuesta (código de estado, cabeceras y contenido) al navegador.
     */
    public function send(): void
    {
        // Enviar código de estado HTTP
        http_response_code($this->statusCode);

        // Enviar cabeceras
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Enviar el contenido
        echo $this->content;
    }

    /**
     *  Helper para respuestas JSON
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $defaultHeaders = ['Content-Type' => 'application/json'];
        $mergedHeaders = array_merge($defaultHeaders, $headers);

        return new self(
            content: json_encode($data),
            statusCode: $statusCode,
            headers: $mergedHeaders
        );
    }

    /**
     *  Helper para respuestas de éxito
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     *  Helper para respuestas de error
     */
    public static function error(string $message, int $statusCode = 400, mixed $errors = null): self
    {
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $statusCode,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return self::json($response, $statusCode);
    }
}