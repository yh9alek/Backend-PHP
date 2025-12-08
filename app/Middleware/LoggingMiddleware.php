<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\Helpers\Logger;
use Closure;

/**
 * Middleware que registra todas las peticiones HTTP.
 */
class LoggingMiddleware
{
    /**
     * Maneja la petición y registra información relevante.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Registrar información de la petición
        $requestData = [
            'method' => $request->method(),
            'uri' => $request->uri(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'params' => $request->allParams(),
        ];

        // No registrar contraseñas en logs
        if (isset($requestData['params']['password'])) {
            $requestData['params']['password'] = '***';
        }
        if (isset($requestData['params']['pass'])) {
            $requestData['params']['pass'] = '***';
        }

        Logger::request($requestData);

        // Ejecutar la petición
        $response = $next($request);

        // Calcular tiempo de respuesta
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Registrar respuesta
        Logger::info('Petición completada', [
            'method' => $request->method(),
            'uri' => $request->uri(),
            'status' => $response->statusCode,
            'execution_time_ms' => $executionTime,
        ]);

        return $response;
    }
}