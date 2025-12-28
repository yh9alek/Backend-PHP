<?php

namespace app\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Exception;
use GuzzleHttp\Exception\RequestException;
use stdClass;

/**
 * Servicio para integración con Keycloak.
 * Maneja autenticación, validación de tokens y obtención de información del usuario.
 */
class KeycloakService
{
    private Client $httpClient;
    private string $keycloakUrl;
    private string $realm;
    private string $clientId;
    private string $clientSecret;
    private string $expectedAudience;
    private array $requiredRealmRoles;
    private ?array $publicKeys = null;

    public function __construct()
    {
        $this->keycloakUrl          = $_ENV['KEYCLOAK_URL'];
        $this->realm                = $_ENV['KEYCLOAK_REALM'];
        $this->clientId             = $_ENV['KEYCLOAK_CLIENT_ID'];
        $this->clientSecret         = $_ENV['KEYCLOAK_CLIENT_SECRET'];
        $this->expectedAudience     = $_ENV['KEYCLOAK_EXPECTED_AUDIENCE'] ?? $this->clientId;
        $this->requiredRealmRoles   = !empty($_ENV['KEYCLOAK_REQUIRED_REALM_ROLES']) 
            ? explode(',', $_ENV['KEYCLOAK_REQUIRED_REALM_ROLES']) 
            : [];

        $this->httpClient = new Client([
            'base_uri' => $this->keycloakUrl,
            'timeout'  => 10,
            'verify'   => true // Cambiar a false en desarrollo local si es necesario
        ]);
    }

    /**
     * Autentica un usuario con Keycloak usando username y password.
     * 
     * @param string $username
     * @param string $password
     * @return stdClass
     */
    public function authenticate(string $username, string $password): stdClass
    {
        try {
            $response = $this->httpClient->post("/realms/{$this->realm}/protocol/openid-connect/token", [
                'form_params' => [
                    'grant_type'    => 'password',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'username'      => $username,
                    'password'      => $password,
                    'scope'         => 'openid profile email'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents());

            return (object)[
                'success'       => true,
                'access_token'  => $data->access_token,
                'refresh_token' => $data->refresh_token,
                'expires_in'    => $data->expires_in,
                'token_type'    => $data->token_type
            ];

        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 500;
            // $errorBody = $e->getResponse()?->getBody()->getContents() ?? 'Error desconocido';

            return (object)[
                'success' => false,
                'message' => $statusCode === 401 ? 'Credenciales inválidas.' : 'Error de autenticación.',
            ];
        } catch (GuzzleException $e) {

            error_log($e->getMessage());

            return (object)[
                'success' => false,
                'message' => 'Error inesperado durante la autenticación.',
                'code'    => 500,
            ];
        }
    }

    /**
     * Valida un token JWT emitido por Keycloak.
     * Verifica firma, expiración, issuer, audience y roles de realm requeridos.
     * 
     * @param string $token
     * @return stdClass|null
     * @throws Exception Si el token es inválido con detalles del error
     */
    public function validateToken(string $token): stdClass
    {
        try {
            // 1. Obtener las claves públicas de Keycloak (se cachean)
            $publicKeys = $this->getPublicKeys();

            // 2. Decodificar el token usando las claves públicas de Keycloak
            $decoded = JWT::decode($token, $publicKeys);

            // 3. Validar Issuer
            $expectedIssuer = "{$this->keycloakUrl}/realms/{$this->realm}";
            if ($decoded->iss !== $expectedIssuer) {
                throw new Exception("Token issuer inválido. Esperado: {$expectedIssuer}, Recibido: {$decoded->iss}");
            }

            // 4. Validar expiración
            if ($decoded->exp < time()) {
                throw new Exception("Token expirado");
            }

            // 5. Validar Audience (CRÍTICO para seguridad entre sistemas)
            $this->validateAudience($decoded);

            // 6. Validar Roles de Realm requeridos
            $this->validateRealmRoles($decoded);

            return $decoded;

        } catch (Exception $e) {
            // En producción, considera loguear estos errores
            error_log("Error validando token: " . $e->getMessage());
            return (object)[
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida que el token tenga el audience correcto.
     * Esto previene que un token de otro sistema sea usado aquí.
     * 
     * @param stdClass $decoded
     * @throws Exception
     */
    private function validateAudience(stdClass $decoded): void
    {
        // El audience puede ser string o array
        $audiences = isset($decoded->aud) 
            ? (is_array($decoded->aud) ? $decoded->aud : [$decoded->aud])
            : [];

        if (!in_array($this->expectedAudience, $audiences, true)) {
            throw new Exception(
                "Token no válido para este servicio. "
                // "Audience esperado: '{$this->expectedAudience}', " .
                // "Audiences en token: [" . implode(', ', $audiences) . "]"
            );
        }
    }

    /**
     * Valida que el usuario tenga los roles de realm requeridos.
     * Esto verifica que el usuario tenga acceso a este sistema.
     * 
     * @param stdClass $decoded
     * @throws Exception
     */
    private function validateRealmRoles(stdClass $decoded): void
    {
        if (empty($this->requiredRealmRoles)) {
            return; // No hay roles requeridos
        }

        $userRealmRoles = $decoded->realm_access->roles ?? [];

        foreach ($this->requiredRealmRoles as $requiredRole) {
            if (!in_array($requiredRole, $userRealmRoles, true)) {
                throw new Exception(
                    "No tienes acceso a este sistema."
                );
            }
        }
    }

    /**
     * Refresca un access token usando un refresh token.
     * 
     * @param string $refreshToken
     * @return stdClass
     */
    public function refreshToken(string $refreshToken): stdClass
    {
        try {
            $response = $this->httpClient->post("/realms/{$this->realm}/protocol/openid-connect/token", [
                'form_params' => [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken
                ]
            ]);

            $data = json_decode($response->getBody()->getContents());

            return (object)[
                'success'       => true,
                'access_token'  => $data->access_token,
                'refresh_token' => $data->refresh_token,
                'expires_in'    => $data->expires_in
            ];

        } catch (GuzzleException $e) {
            return (object)[
                'success' => false,
                'error'   => 'No se pudo refrescar el token.',
                'message' => $e->getMessage(),
                'code'    => 500
            ];
        }
    }

    /**
     * Cierra la sesión en Keycloak.
     * 
     * @param string $refreshToken
     * @return bool
     */
    public function logout(string $refreshToken): bool
    {
        try {
            $this->httpClient->post("/realms/{$this->realm}/protocol/openid-connect/logout", [
                'form_params' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken
                ]
            ]);

            return true;

        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Obtiene información del usuario desde Keycloak usando el access token.
     * 
     * @param string $accessToken
     * @return stdClass|null
     */
    public function getUserInfo(string $accessToken): ?stdClass
    {
        try {
            $response = $this->httpClient->get("/realms/{$this->realm}/protocol/openid-connect/userinfo", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}"
                ]
            ]);

            return json_decode($response->getBody()->getContents());

        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Obtiene y cachea las claves públicas de Keycloak para validar tokens.
     * 
     * @return array
     */
    private function getPublicKeys(): array
    {
        // Intentar cargar desde caché
        $cacheFile = sys_get_temp_dir() . '/keycloak_jwks_' . md5($this->realm) . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            return JWK::parseKeySet($cached);
        }

        // Obtener de Keycloak
        $response = $this->httpClient->get("/realms/{$this->realm}/protocol/openid-connect/certs");
        $jwks = json_decode($response->getBody()->getContents(), true);
        
        // Guardar en caché
        file_put_contents($cacheFile, json_encode($jwks));
        
        return JWK::parseKeySet($jwks);
    }

    /**
     * Verifica si un usuario tiene un rol específico de realm.
     * 
     * @param stdClass $decodedToken
     * @param string $roleName
     * @return bool
     */
    public function hasRealmRole(stdClass $decodedToken, string $roleName): bool
    {
        $realmRoles = $decodedToken->realm_access->roles ?? [];
        return in_array($roleName, $realmRoles, true);
    }

    /**
     * Verifica si un usuario tiene un rol específico de cliente.
     * 
     * @param stdClass $decodedToken
     * @param string $roleName
     * @param string|null $clientId Cliente específico (opcional, por defecto usa el configurado)
     * @return bool
     */
    public function hasClientRole(stdClass $decodedToken, string $roleName, ?string $clientId = null): bool
    {
        $clientId = $clientId ?? $this->clientId;

        $clientRoles = $decodedToken->resource_access->$clientId->roles ?? [];
        return in_array($roleName, $clientRoles, true);
    }

    /**
     * Verifica si un usuario tiene un rol específico (busca en realm y cliente).
     * DEPRECATED: Usa hasRealmRole() o hasClientRole() para mayor claridad.
     * 
     * @param stdClass $decodedToken
     * @param string $roleName
     * @param string|null $clientId Cliente específico (opcional)
     * @return bool
     */
    public function hasRole(stdClass $decodedToken, string $roleName, ?string $clientId = null): bool
    {
        // Buscar en realm roles
        if ($this->hasRealmRole($decodedToken, $roleName)) {
            return true;
        }

        // Buscar en client roles
        return $this->hasClientRole($decodedToken, $roleName, $clientId);
    }

    /**
     * Obtiene todos los roles de realm de un usuario desde el token.
     * 
     * @param stdClass $decodedToken
     * @return array
     */
    public function getRealmRoles(stdClass $decodedToken): array
    {
        return $decodedToken->realm_access->roles ?? [];
    }

    /**
     * Obtiene los roles de cliente de un usuario para un cliente específico.
     * 
     * @param stdClass $decodedToken
     * @param string|null $clientId Cliente específico (opcional)
     * @return array
     */
    public function getClientRoles(stdClass $decodedToken, ?string $clientId = null): array
    {
        $clientId = $clientId ?? $this->clientId;
        return $decodedToken->resource_access->$clientId->roles ?? [];
    }

    /**
     * Obtiene todos los roles de un usuario desde el token (realm + todos los clientes).
     * 
     * @param stdClass $decodedToken
     * @return array
     */
    public function getUserRoles(stdClass $decodedToken): array
    {
        $roles = [];

        // Roles de realm
        if (isset($decodedToken->realm_access->roles)) {
            $roles = array_merge($roles, $decodedToken->realm_access->roles);
        }

        // Roles de todos los clientes
        if (isset($decodedToken->resource_access)) {
            foreach ($decodedToken->resource_access as $client => $data) {
                if (isset($data->roles)) {
                    $roles = array_merge($roles, $data->roles);
                }
            }
        }

        return array_unique($roles);
    }

    /**
     * Verifica si el usuario tiene alguno de los roles especificados.
     * 
     * @param stdClass $decodedToken
     * @param array $allowedRoles Lista de roles permitidos
     * @param string|null $clientId Para roles de cliente específico
     * @return bool
     */
    public function hasAnyRole(stdClass $decodedToken, array $allowedRoles, ?string $clientId = null): bool
    {
        foreach ($allowedRoles as $role) {
            if ($clientId) {
                if ($this->hasClientRole($decodedToken, $role, $clientId)) {
                    return true;
                }
            } else {
                if ($this->hasRole($decodedToken, $role)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Verifica si el usuario tiene todos los roles especificados.
     * 
     * @param stdClass $decodedToken
     * @param array $requiredRoles Lista de roles requeridos
     * @param string|null $clientId Para roles de cliente específico
     * @return bool
     */
    public function hasAllRoles(stdClass $decodedToken, array $requiredRoles, ?string $clientId = null): bool
    {
        foreach ($requiredRoles as $role) {
            if ($clientId) {
                if (!$this->hasClientRole($decodedToken, $role, $clientId)) {
                    return false;
                }
            } else {
                if (!$this->hasRole($decodedToken, $role)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Obtiene el username del token.
     * 
     * @param stdClass $decodedToken
     * @return string|null
     */
    public function getUsername(stdClass $decodedToken): ?string
    {
        return $decodedToken->preferred_username ?? null;
    }

    /**
     * Obtiene el email del token.
     * 
     * @param stdClass $decodedToken
     * @return string|null
     */
    public function getEmail(stdClass $decodedToken): ?string
    {
        return $decodedToken->email ?? null;
    }

    /**
     * Obtiene el UUID del usuario (sub claim).
     * 
     * @param stdClass $decodedToken
     * @return string|null
     */
    public function getUserId(stdClass $decodedToken): ?string
    {
        return $decodedToken->sub ?? null;
    }
}