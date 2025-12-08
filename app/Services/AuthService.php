<?php

namespace app\Services;

use app\Models\UserModel;
use stdClass;

/**
 * Servicio de autenticación que ahora delega en Keycloak.
 * Mantiene sincronización local de usuarios para relaciones en BD.
 */
class AuthService
{
    public function __construct(
        private KeycloakService $keycloakService
    ) {}

    /**
     * Valida las credenciales de un usuario contra Keycloak.
     *
     * @param string|null $username
     * @param string|null $password
     * @return stdClass
     */
    public function validate(?string $username, ?string $password): stdClass
    {
        if (empty($username) || empty($password)) {
            return (object)[
                'success' => false,
                'error'   => 'El usuario y la contraseña son requeridos.',
                'code'    => 400
            ];
        }

        // 1. Autenticar con Keycloak
        $keycloakResult = $this->keycloakService->authenticate($username, $password);

        if (!$keycloakResult->success) {
            return $keycloakResult;
        }

        // 2. Validar el token para obtener información del usuario
        $decodedToken = $this->keycloakService->validateToken($keycloakResult->access_token);

        if (!$decodedToken) {
            return (object)[
                'success' => false,
                'error'   => 'Token inválido recibido de Keycloak.',
                'code'    => 500
            ];
        }

        // 3. Sincronizar usuario en BD local (si no existe, crearlo)
        $localUser = $this->syncUserFromKeycloak($decodedToken);

        // 4. Obtener roles desde Keycloak
        $roles = $this->keycloakService->getUserRoles($decodedToken);

        return (object)[
            'success'       => true,
            'access_token'  => $keycloakResult->access_token,
            'refresh_token' => $keycloakResult->refresh_token,
            'expires_in'    => $keycloakResult->expires_in,
            'user'          => $localUser,
            'roles'         => $roles,
            'keycloak_id'   => $decodedToken->sub
        ];
    }

    /**
     * Sincroniza el usuario de Keycloak con la base de datos local.
     * 
     * @param stdClass $keycloakToken
     * @return UserModel|null
     */
    private function syncUserFromKeycloak(stdClass $keycloakToken): ?UserModel
    {
        $keycloakId = $keycloakToken->sub;
        $email = $keycloakToken->email ?? null;
        $username = $keycloakToken->preferred_username ?? null;

        // Buscar usuario por username o email
        $user = UserModel::where('username', '=', $username)
            ->limit(1)
            ->get()[0] ?? null;

        if (!$user && $email) {
            $user = UserModel::where('email', '=', $email)
                ->limit(1)
                ->get()[0] ?? null;
        }

        // Si no existe, crear un registro básico
        if (!$user) {
            $user = new UserModel();
            $user->username = $username;
            $user->email = $email;
            
            // Extraer nombre del token de Keycloak
            $user->nombre = $keycloakToken->given_name ?? '';
            $user->apellidoPaterno = $keycloakToken->family_name ?? '';
            
            // Valores por defecto (ajustar según tu lógica de negocio)
            $user->keycloakId = $keycloakId;
            $user->profileId = 1; // Perfil por defecto
            $user->areaId = 1;    // Área por defecto
            $user->pass = ''; // No se almacena contraseña (Keycloak la maneja)
            $user->createUser = 1; // Sistema
            $user->createdAt = date('Y-m-d H:i:s');
            
            $user->save();
        }

        return $user;
    }

    /**
     * Refresca el access token usando el refresh token.
     * 
     * @param string $refreshToken
     * @return stdClass
     */
    public function refreshToken(string $refreshToken): stdClass
    {
        return $this->keycloakService->refreshToken($refreshToken);
    }

    /**
     * Cierra sesión en Keycloak.
     * 
     * @param string $refreshToken
     * @return bool
     */
    public function logout(string $refreshToken): bool
    {
        return $this->keycloakService->logout($refreshToken);
    }
}