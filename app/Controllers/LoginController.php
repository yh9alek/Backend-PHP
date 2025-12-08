<?php

namespace app\Controllers;

use app\Core\Request;
use app\Core\Response;

/**
 * Controlador de autenticación que ahora solo maneja endpoints de API.
 * El frontend será responsable de almacenar y enviar tokens.
 */
class LoginController extends BaseController
{
    /**
     * Endpoint POST /api/auth/login
     * Autentica al usuario y devuelve tokens de Keycloak.
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        // 1. Validar parámetros
        $validationResponse = $this->validate($request, [
            'username',
            'password'
        ]);

        if ($validationResponse->statusCode != 200) {
            return $validationResponse;
        }

        // 2. Extraer credenciales
        $username = $request->param('username');
        $password = $request->param('password');

        // 3. Autenticar con Keycloak via AuthService
        $result = $this->authService->validate($username, $password);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->error,
                'message' => $result->message ?? 'Error de autenticación.'
            ], $result->code ?? 401);
        }

        // 4. Responder con tokens y datos del usuario
        return $this->json([
            'success' => true,
            'data' => [
                'access_token' => $result->access_token,
                'refresh_token' => $result->refresh_token,
                'expires_in' => $result->expires_in,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $result->user->id,
                    'username' => $result->user->username,
                    'email' => $result->user->email,
                    'profile_id' => $result->user->profileId,
                    'keycloak_id' => $result->keycloak_id
                ],
                'roles' => $result->roles
            ]
        ], 200);
    }

    /**
     * Endpoint POST /api/auth/refresh
     * Refresca el access token usando el refresh token.
     *
     * @param Request $request
     * @return Response
     */
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->param('refresh_token');

        if (empty($refreshToken)) {
            return $this->json([
                'success' => false,
                'error' => 'Refresh token no proporcionado.'
            ], 400);
        }

        $result = $this->authService->refreshToken($refreshToken);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->error
            ], 401);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'access_token' => $result->access_token,
                'refresh_token' => $result->refresh_token,
                'expires_in' => $result->expires_in
            ]
        ], 200);
    }

    /**
     * Endpoint POST /api/auth/logout
     * Cierra sesión en Keycloak.
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $refreshToken = $request->param('refresh_token');

        if (empty($refreshToken)) {
            return $this->json([
                'success' => false,
                'error' => 'Refresh token no proporcionado.'
            ], 400);
        }

        $success = $this->authService->logout($refreshToken);

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Sesión cerrada exitosamente.' : 'Error al cerrar sesión.'
        ], $success ? 200 : 500);
    }

    /**
     * Endpoint GET /api/auth/me
     * Devuelve información del usuario autenticado actual.
     *
     * @param Request $request
     * @return Response
     */
    public function me(Request $request): Response
    {
        // El middleware ya validó el token y estableció el usuario en el request
        $user = $request->user();

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Usuario no autenticado.'
            ], 401);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'keycloak_id' => $user->sub,
                'username' => $user->preferred_username ?? null,
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
                'given_name' => $user->given_name ?? null,
                'family_name' => $user->family_name ?? null,
                'email_verified' => $user->email_verified ?? false,
                'roles' => $this->container->get(\app\Services\KeycloakService::class)->getUserRoles($user)
            ]
        ], 200);
    }
}