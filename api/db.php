<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

function gis_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = gis_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['name']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function gis_config(): array
{
    $root = dirname(__DIR__);
    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'config.local.php',
        dirname($root) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gisgame.php',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $config = require $path;
        if (!is_array($config)) {
            continue;
        }
        $host = (string) ($config['host'] ?? '');
        $name = (string) ($config['name'] ?? '');
        $user = (string) ($config['user'] ?? '');
        $pass = (string) ($config['pass'] ?? '');
        $port = (int) ($config['port'] ?? 0);
        if ($host === '' || $name === '' || $user === '' || $pass === '' || $port < 1) {
            continue;
        }
        return [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        ];
    }

    throw new RuntimeException('Database config is missing.');
}
