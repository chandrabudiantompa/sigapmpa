<?php
declare(strict_types=1);

// Separate cookies/storage prevent employee logout or session GC from interrupting a test.
ini_set('display_errors', '0');
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/recruitment_security.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) json_response(['ok' => false, 'message' => 'Konfigurasi server tes belum tersedia.'], 503);
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Jakarta');
$trustedProxies = $config['recruitment']['trusted_proxies'] ?? [];
$trustedProxy = is_array($trustedProxies) && in_array($_SERVER['REMOTE_ADDR'] ?? '', $trustedProxies, true);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($trustedProxy && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (($config['app']['force_https'] ?? false) && !$isHttps) {
    json_response(['ok' => false, 'message' => 'Tes harus diakses melalui HTTPS.'], 403);
}
$sessionDirectory = recruitment_storage_directory() . '/sessions';
if (!is_dir($sessionDirectory) && !@mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    json_response(['ok' => false, 'message' => 'Penyimpanan sesi tes tidak tersedia.'], 503);
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', '10800');
session_save_path($sessionDirectory);
session_name('SIGAPRECRUITMENT');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Strict']);
if (!session_start()) json_response(['ok' => false, 'message' => 'Sesi tes tidak tersedia.'], 503);
