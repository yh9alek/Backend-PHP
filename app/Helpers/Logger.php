<?php

namespace app\Helpers;

/**
 * Logger centralizado para la aplicación.
 * Registra errores y eventos en archivos estructurados.
 */
class Logger
{
    private static ?string $logDir = null;

    /**
     * Inicializa el directorio de logs
     */
    private static function init(): void
    {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../../logs';
            
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
    }

    /**
     * Registra un warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context, 'warnings.log');
    }

    /**
     * Registra información general
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context, 'info.log');
    }

    /**
     * Registra un evento de debug (solo en desarrollo)
     */
    public static function debug(string $message, array $context = []): void
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            self::log('DEBUG', $message, $context, 'debug.log');
        }
    }

    /**
     * Registra una petición HTTP
     */
    public static function request(array $data): void
    {
        self::log('REQUEST', '', $data, 'requests.log');
    }

    /**
     * Registra un evento de autenticación
     */
    public static function auth(string $event, array $data = []): void
    {
        self::log('AUTH', $event, $data, 'auth.log');
    }

    /**
     * Registra una excepción
     */
    public static function exception(\Throwable $e, array $context = []): void
    {
        $data = array_merge([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $context);

        self::log('EXCEPTION', get_class($e), $data, 'exceptions.log');
    }

    /**
     * Método principal de logging
     */
    private static function log(string $level, string $message, array $context, string $filename): void
    {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $logEntry = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $message,
            $contextJson
        );

        $logFile = self::$logDir . '/' . $filename;

        // Rotación de logs si el archivo es muy grande (>10MB)
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            $backupFile = self::$logDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.log';
            rename($logFile, $backupFile);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Limpia logs antiguos (más de 30 días)
     */
    public static function cleanup(int $days = 30): void
    {
        self::init();

        $files = glob(self::$logDir . '/*.log');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) >= $days * 86400)) {
                unlink($file);
            }
        }
    }
}