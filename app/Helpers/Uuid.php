<?php

namespace app\Helpers;

/**
 * Helper para generación y validación de UUIDs (RFC 4122)
 * 
 * Genera UUIDs versión 4 (random) compatibles con estándares
 * Ejemplo: 550e8400-e29b-41d4-a716-446655440000
 */
class Uuid
{
    /**
     * Genera un UUID versión 4 (random)
     * 
     * @return string UUID en formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    public static function generate(): string
    {
        // Generación usando random_bytes (seguro y compatible ^PHP 7.0+)
        $data = random_bytes(16);
        
        // Set version (0100xxxx) - versión 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        
        // Set variant (10xxxxxx) - RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Formatear como UUID estándar
        return strtolower(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
    }

    /**
     * Valida si una cadena es un UUID válido (v4)
     * 
     * @param string $uuid UUID a validar
     * @return bool True si es válido, false si no
     */
    public static function isValid(string $uuid): bool
    {
        // Patrón UUID v4: 8-4-4-4-12 caracteres hexadecimales
        // Versión: 4 (cuarto grupo empieza con 4)
        // Variante: RFC 4122 (quinto grupo empieza con 8, 9, a, o b)
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }

    /**
     * Valida formato de cualquier versión de UUID (no solo v4)
     * Útil para validar UUIDs de Keycloak u otros sistemas
     * 
     * @param string $uuid UUID a validar
     * @return bool True si tiene formato UUID válido
     */
    public static function isValidAny(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }

    /**
     * Convierte UUID a formato binario (16 bytes)
     * Útil para almacenamiento optimizado en MySQL como BINARY(16)
     * 
     * @param string $uuid UUID en formato string
     * @return string UUID en formato binario
     */
    public static function toBinary(string $uuid): string
    {
        if (!self::isValid($uuid)) {
            throw new \InvalidArgumentException("UUID inválido: {$uuid}");
        }

        return hex2bin(str_replace('-', '', $uuid));
    }

    /**
     * Convierte formato binario a UUID string
     * 
     * @param string $binary UUID en formato binario (16 bytes)
     * @return string UUID en formato string
     */
    public static function fromBinary(string $binary): string
    {
        if (strlen($binary) !== 16) {
            throw new \InvalidArgumentException("Binario debe tener 16 bytes");
        }

        $hex = bin2hex($binary);
        
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Genera múltiples UUIDs únicos
     * 
     * @param int $count Cantidad de UUIDs a generar
     * @return array Array de UUIDs únicos
     */
    public static function generateMany(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $uuids[] = self::generate();
        }

        return $uuids;
    }

    /**
     * Obtiene la versión de un UUID
     * 
     * @param string $uuid UUID a analizar
     * @return int|null Versión del UUID (1-5) o null si es inválido
     */
    public static function getVersion(string $uuid): ?int
    {
        if (!self::isValidAny($uuid)) {
            return null;
        }

        // La versión está en el 13er caracter (contando desde 0)
        $parts = explode('-', $uuid);
        $versionChar = substr($parts[2], 0, 1);

        return (int) $versionChar;
    }

    /**
     * Verifica si dos UUIDs son iguales (case-insensitive)
     * 
     * @param string $uuid1 Primer UUID
     * @param string $uuid2 Segundo UUID
     * @return bool True si son iguales
     */
    public static function equals(string $uuid1, string $uuid2): bool
    {
        return strtolower($uuid1) === strtolower($uuid2);
    }

    /**
     * Normaliza un UUID a minúsculas con formato estándar
     * 
     * @param string $uuid UUID a normalizar
     * @return string UUID normalizado
     */
    public static function normalize(string $uuid): string
    {
        // Remover guiones y convertir a minúsculas
        $clean = strtolower(str_replace('-', '', $uuid));
        
        // Validar longitud
        if (strlen($clean) !== 32) {
            throw new \InvalidArgumentException("UUID inválido: longitud incorrecta");
        }

        // Reformatear
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($clean, 0, 8),
            substr($clean, 8, 4),
            substr($clean, 12, 4),
            substr($clean, 16, 4),
            substr($clean, 20, 12)
        );
    }

    /**
     * Genera UUID v4 con timestamp embebido (útil para ordenamiento)
     * NOTA: No es UUID estándar, usa versión personalizada
     * 
     * @return string UUID ordenable por tiempo
     */
    public static function generateOrdered(): string
    {
        // Timestamp en microsegundos (primeros 8 bytes)
        $time = microtime(true);
        $timePart = str_pad(dechex((int)($time * 10000)), 16, '0', STR_PAD_LEFT);
        
        // Parte random (últimos 8 bytes)
        $randomPart = bin2hex(random_bytes(8));
        
        // Formatear como UUID
        $hex = $timePart . $randomPart;
        
        return sprintf(
            '%s-%s-4%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            '8' . substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }

    /**
     * Extrae timestamp de un UUID ordenable
     * 
     * @param string $uuid UUID generado con generateOrdered()
     * @return float|null Timestamp o null si no es ordenable
     */
    public static function extractTimestamp(string $uuid): ?float
    {
        try {
            $clean = str_replace('-', '', $uuid);
            $timePart = substr($clean, 0, 16);
            $timestamp = hexdec($timePart) / 10000;
            
            return $timestamp;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Genera UUID nulo (todos ceros)
     * Útil para valores por defecto o comparaciones
     * 
     * @return string UUID nulo
     */
    public static function nil(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Verifica si un UUID es nulo
     * 
     * @param string $uuid UUID a verificar
     * @return bool True si es UUID nulo
     */
    public static function isNil(string $uuid): bool
    {
        return self::equals($uuid, self::nil());
    }
}