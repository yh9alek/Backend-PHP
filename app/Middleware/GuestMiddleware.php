<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\services\JwtService;

use Closure;

class GuestMiddleware
{
    public function __construct(private JwtService $jwtService) 
    {}

    /**
     * Si el usuario ya está autenticado, lo redirige al home.
     * De lo contrario, permite que la petición continúe.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Usamos validateToken() para comprobar si hay un token válido.
        if ($this->jwtService->validateToken()) {
            // El usuario ya está logueado, lo redirigimos a la página de inicio.
            return new Response('', 302, ['Location' => '/inicio']);
        }

        // El usuario no está logueado (es un invitado), le permitimos continuar
        // hacia el controlador.
        return $next($request);
    }
}