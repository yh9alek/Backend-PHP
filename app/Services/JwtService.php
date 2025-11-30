<?php

namespace app\services;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use \Exception;
use \stdClass;

/**
 * Servicio para manejar la lógica de JWT.
 */
class JwtService {
    private const COOKIE_NAME = 'jwt_token';

    private string $secretKey;
    private string $algorithm;
    private int $expirationTime;

    public function __construct(string $secretKey, string $algorithm = 'HS512', int $expirationTime = 3600) {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
        $this->expirationTime = $expirationTime;
    }

    public function generateToken(array $payload): string {
        $this->logout();

        $payload['exp'] = time() + $this->expirationTime;
        $payload['iat'] = time();

        $token = FirebaseJWT::encode($payload, $this->secretKey, $this->algorithm);

        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires'  => time() + $this->expirationTime,
                'path'     => '/',
                'httponly' => true,
                'secure'   => isset($_SERVER['HTTPS']), // Para desarrollo local sin HTTPS
                'samesite' => 'Strict'
            ]
        );

        return $token;
    }

    public function validateToken(): ?stdClass {
        if (!isset($_COOKIE[self::COOKIE_NAME]))
            return null;

        try {
            $token = FirebaseJWT::decode(
                $_COOKIE[self::COOKIE_NAME],
                new Key($this->secretKey, $this->algorithm)
            );

            $token->remaining = gmdate("H:i:s", $token->exp - time());
            return $token;
        } catch (Exception $e) {
            return null;
        }
    }

    public function logout(): void {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires'  => time() - $this->expirationTime,
                'path'     => '/',
                'httponly' => true,
                'secure'   => isset($_SERVER['HTTPS']),
                'samesite' => 'Strict'
            ]);
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }
}