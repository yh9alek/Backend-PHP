<?php

namespace app\Controllers;

use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;

/**
 * Controlador de autenticación que funciona en DESARROLLO y PRODUCCIÓN
 * - Desarrollo (HTTP + Proxy): SameSite=Lax, Secure=false
 * - Producción (HTTPS): SameSite=None, Secure=true
 */
class LoginController extends BaseController
{
    /**
     * Endpoint POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        // 1. Validar parámetros
        $validator = Validator::make($request->allParams(), [
            'username' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 422);
        }

        // 2. Extraer credenciales
        $username = $request->param('username');
        $password = $request->param('password');

        // 3. Autenticar con Keycloak
        $result = $this->authService->validate($username, $password);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'message' => $result->message
            ], $result->code ?? 401);
        }

        // 4. Crear cookie con configuración según entorno
        $this->setRefreshTokenCookie($result->refresh_token);

        // 5. Responder con access_token
        return $this->json([
            'success' => true,
            'data' => [
                'access_token'  => $result->access_token,
                'expires_in'    => $result->expires_in,
                'user' => [
                    'username'    => $result->user->username,
                    'email'       => $result->user->email,
                    'roles'       => $result->roles,
                ]
            ]
        ], 200);
    }

    /**
     * Endpoint POST /api/auth/refresh
     */
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->cookie('refresh_token');

        if (empty($refreshToken)) {
            return $this->json([
                'success' => false,
                'message' => 'refresh_token no proporcionado'
            ], 401);
        }

        $result = $this->authService->refreshToken($refreshToken);

        if (!$result->success) {
            $this->deleteRefreshTokenCookie();
            
            return $this->json([
                'success' => false,
                'message' => $result->error ?? 'Invalid refresh token',
            ], $result->code ?? 401);
        }

        // Actualizar cookie
        $this->setRefreshTokenCookie($result->refresh_token);

        return $this->json([
            'success' => true,
            'data' => [
                'access_token' => $result->access_token,
                'expires_in' => $result->expires_in
            ]
        ], 200);
    }

    /**
     * Endpoint POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $refreshToken = $request->cookie('refresh_token');

        if (empty($refreshToken)) {
            return $this->json([
                'success' => false,
                'message' => 'No refresh token provided'
            ], 400);
        }

        $success = $this->authService->logout($refreshToken);
        $this->deleteRefreshTokenCookie();

        return $this->json([
            'success' => $success,
            'message' => $success ? 'Sesión cerrada exitosamente.' : 'Error al cerrar sesión.'
        ], $success ? 200 : 500);
    }

    /**
     * Endpoint GET /api/auth/me
     */
    public function me(Request $request): Response
    {
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

    // ========================================
    // MÉTODOS PRIVADOS - Manejo de Cookies
    // ========================================

    /**
     * Crea/actualiza la cookie refresh_token con configuración según entorno
     */
    private function setRefreshTokenCookie(string $token): void
    {
        $isProduction = $this->isProduction();
        $isHttps = $this->isHttps();

        // DESARROLLO (HTTP con proxy): usar Lax sin Secure
        // PRODUCCIÓN (HTTPS): usar None con Secure
        
        if ($isHttps) {
            // HTTPS: Usar None para permitir cross-origin
            setcookie(
                'refresh_token',
                $token,
                [
                    'expires' => time() + (7 * 24 * 60 * 60),
                    'path' => '/',
                    'domain' => $isProduction ? $this->getCookieDomain() : '',
                    'secure' => true,      // ✅ Requiere HTTPS
                    'httponly' => true,
                    'samesite' => 'None'   // ✅ Permite cross-origin
                ]
            );
            
            error_log("Cookie creada (HTTPS): SameSite=None, Secure=true");
            
        } else {
            // HTTP: Usar Lax (funciona con proxy en desarrollo)
            setcookie(
                'refresh_token',
                $token,
                time() + (7 * 24 * 60 * 60),
                '/',
                '',
                false,     // ✅ HTTP no requiere Secure
                true       // ✅ Siempre HttpOnly
            );
            // Nota: SameSite se establece automáticamente como Lax en PHP 7.3+
            
            error_log("Cookie creada (HTTP): SameSite=Lax, Secure=false");
        }
    }

    /**
     * Elimina la cookie refresh_token
     */
    private function deleteRefreshTokenCookie(): void
    {
        $isHttps = $this->isHttps();
        
        if ($isHttps) {
            setcookie(
                'refresh_token',
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'None'
                ]
            );
        } else {
            setcookie('refresh_token', '', time() - 3600, '/');
        }
        
        error_log("Cookie eliminada");
    }

    /**
     * Detecta si estamos en producción
     */
    private function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }

    /**
     * Detecta si la conexión es HTTPS
     */
    private function isHttps(): bool
    {
        // 1. Variable HTTPS del servidor
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // 2. Puerto 443 (HTTPS)
        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        // 3. Header X-Forwarded-Proto (proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // 4. Header X-Forwarded-SSL
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        return false;
    }

    /**
     * Obtiene el dominio para cookies en producción
     */
    private function getCookieDomain(): string
    {
        // Leer desde .env si existe
        $domain = $_ENV['COOKIE_DOMAIN'] ?? '';
        
        // Si no está configurado, intentar detectar
        if (empty($domain) && !empty($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            // Remover puerto si existe
            $host = explode(':', $host)[0];
            // Si es un subdominio, usar el dominio principal
            // Ejemplo: api.tudominio.com → .tudominio.com
            $parts = explode('.', $host);
            if (count($parts) > 2) {
                // Usar formato .tudominio.com para permitir subdominios
                $domain = '.' . implode('.', array_slice($parts, -2));
            }
        }
        
        return $domain;
    }
}