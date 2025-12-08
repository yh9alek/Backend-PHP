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
    private ?array $publicKeys = null;

    public function __construct()
    {
        $this->keycloakUrl   = $_ENV['KEYCLOAK_URL'];
        $this->realm         = $_ENV['KEYCLOAK_REALM'];
        $this->clientId      = $_ENV['KEYCLOAK_CLIENT_ID'];
        $this->clientSecret  = $_ENV['KEYCLOAK_CLIENT_SECRET'];

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
            $errorBody = $e->getResponse()?->getBody()->getContents() ?? 'Error desconocido';

            return (object)[
                'success' => false,
                'error'   => $statusCode === 401 ? 'Credenciales inválidas.' : 'Error de autenticación.',
                'message' => $errorBody,
                'code'    => $statusCode
            ];
        } catch (GuzzleException $e) {
            return (object)[
                'success' => false,
                'error'   => 'Error inesperado durante la autenticación.',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida un token JWT emitido por Keycloak.
     * 
     * @param string $token
     * @return stdClass|null
     */
    public function validateToken(string $token): ?stdClass
    {
        try {
            // 1. Obtener las claves públicas de Keycloak (se cachean)
            $publicKeys = $this->getPublicKeys();

            // 2. Decodificar el token usando las claves públicas de Keycloak
            $decoded = JWT::decode($token, $publicKeys);

            // 3. Validar claims adicionales
            if ($decoded->iss !== "{$this->keycloakUrl}/realms/{$this->realm}") {
                return null;
            }

            if ($decoded->exp < time()) {
                return null;
            }

            return $decoded;

        } catch (Exception $e) {
            // Token inválido, expirado o mal formado
            return null;
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
                'message' => $e->getMessage()
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
        if ($this->publicKeys !== null) {
            return $this->publicKeys;
        }

        try {
            $response = $this->httpClient->get("/realms/{$this->realm}/protocol/openid-connect/certs");
            $jwks = json_decode($response->getBody()->getContents(), true);

            // Convertir JWKS a formato que jwt-php puede usar
            $this->publicKeys = JWK::parseKeySet($jwks);

            return $this->publicKeys;

        } catch (Exception $e) {
            throw new Exception("No se pudieron obtener las claves públicas de Keycloak: " . $e->getMessage());
        }
    }

    /**
     * Verifica si un usuario tiene un rol específico.
     * 
     * @param stdClass $decodedToken
     * @param string $roleName
     * @param string $clientId Cliente específico (opcional, por defecto usa el configurado)
     * @return bool
     */
    public function hasRole(stdClass $decodedToken, string $roleName, ?string $clientId = null): bool
    {
        $clientId = $clientId ?? $this->clientId;

        // Roles de realm
        if (isset($decodedToken->realm_access->roles) && in_array($roleName, $decodedToken->realm_access->roles)) {
            return true;
        }

        // Roles de cliente específico
        if (isset($decodedToken->resource_access->$clientId->roles) && 
            in_array($roleName, $decodedToken->resource_access->$clientId->roles)) {
            return true;
        }

        return false;
    }

    /**
     * Obtiene todos los roles de un usuario desde el token.
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

        // Roles de cliente
        if (isset($decodedToken->resource_access)) {
            foreach ($decodedToken->resource_access as $client => $data) {
                if (isset($data->roles)) {
                    $roles = array_merge($roles, $data->roles);
                }
            }
        }

        return array_unique($roles);
    }
}