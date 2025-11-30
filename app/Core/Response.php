<?php

namespace app\Core;

/**
 * Clase para crear un objeto de respuesta estandar.
 */
class Response
{
    public function __construct(
        public readonly string $content = '',
        public readonly int $statusCode = 200,
        public readonly array $headers = []
    ) {}

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
}