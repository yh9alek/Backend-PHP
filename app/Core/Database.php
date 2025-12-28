<?php

namespace app\Core;

use PDO;
use PDOException;

/**
 * Clase de conexión a la base de datos.
 */
class Database {
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function getInstance(): ?PDO {
        if (self::$pdo === null) {
            try {
                $host = $_ENV['DB_HOST'];
                $port = $_ENV['DB_PORT'];
                $user = $_ENV['DB_USER'];
                $pass = $_ENV['DB_PASS'];
                $db   = $_ENV['DB_NAME'];
                $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
                
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO\Mysql::ATTR_ERRMODE            => PDO\Mysql::ERRMODE_EXCEPTION,
                    PDO\Mysql::ATTR_DEFAULT_FETCH_MODE => PDO\Mysql::ERRMODE_EXCEPTION,
                    PDO\Mysql::ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } catch (\PDOException $e) {
                file_put_contents(__DIR__.'/../../logs/db.log', "[ ". date('d/m/Y H:i:s A')." ] {$e->getMessage()} \n\n", FILE_APPEND);
                throw new PDOException('Error de conexión a la base de datos');
            }
        }
        return self::$pdo;
    }
}