<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $hosts = [(string)$config['db_host']];
    if (strcasecmp((string)$config['db_host'], 'localhost') === 0) {
        $hosts[] = '127.0.0.1';
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $lastException = null;
    foreach (array_values(array_unique($hosts)) as $host) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $config['db_port'],
            $config['db_name']
        );

        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
            return $pdo;
        } catch (PDOException $exception) {
            $lastException = $exception;
        }
    }

    if ($lastException instanceof PDOException) {
        throw $lastException;
    }

    return $pdo;
}
