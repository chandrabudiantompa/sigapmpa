<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = $config['db'] ?? [];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'] ?? 'localhost',
        $db['name'] ?? '',
        $db['charset'] ?? 'utf8mb4'
    );
    try {
        $pdo = new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (Throwable $e) {
        error_log('MPA Workforce DB error: ' . $e->getMessage());
        json_response(['ok' => false, 'error' => 'database_unavailable', 'message' => 'Koneksi database gagal.'], 503);
    }
    return $pdo;
}
