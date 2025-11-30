<?php

namespace app\Middleware;

use app\Core\Request;
use app\Core\Response;
use app\Core\View;
use app\services\JwtService;

use Closure;

class AuthMiddleware 
{
    public function __construct(
        private JwtService $jwtService,
        private View $view
    ) {}

    /**
     * @param Request $request
     * @param Closure $next
     * @param string|null $guard El "guardia" que se usará ('api' o null para web).
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = $this->jwtService->validateToken();

        if (!$user) {
            if ($guard === 'api') {

                // $content = $this->view->render('error', [

                //     'message'   => 'Sin Autorización. Sesión Expirada.',
                //     'errorCode' => 401

                // ], '_error');

                // return new Response($content);

                return new Response(json_encode(['error' => 'Sin autorización. Sesión Expirada.']), 401, ['Content-Type' => 'application/json']);
            } else {
                // Para la web, redirigimos al login.
                $this->jwtService->logout();
                return new Response('', 302, ['Location' => '/login']);
            }
        }
        
        return $next($request);
    }
}