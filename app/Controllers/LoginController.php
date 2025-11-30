<?php

namespace app\Controllers;

use app\Core\Request;
use app\Core\Response;

class LoginController extends BaseController 
{
    /**
     * Muestra el formulario de login (Maneja las peticiones GET a /login).
     *
     * @param Request $request
     * @return Response
     */
    public function mostrarLogin(Request $request): Response 
    {
        $state = [
            'user' => null,
            'notificaciones' => []
        ];
        
        // Renderiza la vista.
        return $this->view('login', $state);
    }

    /**
     * Procesa los datos del formulario de login (Maneja las peticiones POST a /login).
     *
     * @param Request $request
     * @return Response
     */
    public function validarAcceso(Request $request): Response
    {
        // Obtenemos los datos del formulario a través del objeto Request.
        $user     = $request->param('user');
        $password = $request->param('password');

        // Usamos el servicio de autenticación.
        $result = $this->authService->validate($user, $password);

        if ($result->success) {
            return $this->json([
                'token' => $result->token,
            ]);
        }

        return $this->json(['msg'   => $result->message,
                            'error' => $result->error], 401);
    }

    public function logout(Request $request): Response {
        $this->jwtService->logOut();
        return $this->redirectToRoute('login.show', statusCode: 302);
    }
}