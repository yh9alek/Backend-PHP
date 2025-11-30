<?php

namespace app\services;

use app\Models\UserModel;
use app\Core\QueryBuilder;
use \stdClass;

class AuthService
{
    public function __construct(private JwtService $jwtService) {}

    /**
     * Valida las credenciales de un usuario.
     *
     * @param string|null $username
     * @param string|null $pass
     * @return stdClass
     */
    public function validate(?string $username, ?string $pass): stdClass
    {
        $result = [];

        if (empty($username) || empty($pass))
            return (object)[
                'success' => false,
                'error'   => 1, 
                'message' => 'El usuario y la contraseña son requeridos.'
            ];

        // Usa el ORM para encontrar el usuario.
        $userCollection = UserModel::where('username', '=', $username)->limit(1)->get();
        $user = $userCollection[0] ?? null;

        // Verifica si el usuario existe
        if(!$user)
            return (object)[
                'success' => false,
                'error'   => 1, 
                'message' => 'No existe el usuario ingresado.'
            ];

        // Verifica si la contraseña es correcta
        if (password_verify($pass, $user->pass)) {

            $payload = [
                'user_id'    => $user->id,
                'username'   => $user->username,
                'email'      => $user->email,
                'profile_id' => $user->profileId,
            ];

            // Crear el token JWT
            $token = $this->jwtService->generateToken($payload);

            $result = (object)[
                'success' => true, 
                'message' => 'username exitoso.', 
                'token' => $token
            ];
        } else 
            $result = (object)[
                'success' => false, 
                'message' => 'Contraseña incorrecta.'
            ];

        return $result;
    }
}
