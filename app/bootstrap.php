<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'not_configured', 'message' => 'File app/config.local.php belum dibuat.']);
    exit;
}
$config = require $configFile;

date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Jakarta');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (($config['app']['force_https'] ?? false) && !$isHttps) {
    $host = $_SERVER['HTTP_HOST'] ?? parse_url((string)$config['app']['url'], PHP_URL_HOST);
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

session_name((string)($config['app']['session_name'] ?? 'MPAWORKFORCESESSID'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=(), publickey-credentials-get=(self), publickey-credentials-create=(self)');
header('Cache-Control: no-store, private');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = rtrim((string)($config['app']['allowed_origin'] ?? ''), '/');
if ($origin !== '' && $allowedOrigin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $allowedHost = parse_url($allowedOrigin, PHP_URL_HOST);
    $originAllowed = ($config['app']['force_https'] ?? false)
        ? rtrim($origin, '/') === $allowedOrigin
        : ($originHost !== null && $originHost === $allowedHost);
    if (!$originAllowed) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'origin_denied', 'message' => 'Origin tidak diizinkan.']);
        exit;
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/webauthn.php';
