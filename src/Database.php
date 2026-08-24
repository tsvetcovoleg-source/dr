<?php
declare(strict_types=1);

namespace DecisionRules;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(string $root): PDO
    {
        $path = $root . '/config/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuration file is missing. Copy config/config.example.php to config/config.php.');
        }
        $config = require $path;
        $db = $config['db'] ?? [];
        foreach (['host', 'name', 'user', 'password'] as $key) {
            if (!array_key_exists($key, $db)) {
                throw new RuntimeException('Database configuration is incomplete.');
            }
        }
        $charset = $db['charset'] ?? 'utf8mb4';
        $port = (int) ($db['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $port, $db['name'], $charset);

        return new PDO($dsn, (string) $db['user'], (string) $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

