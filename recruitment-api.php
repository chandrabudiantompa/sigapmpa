<?php
declare(strict_types=1);
require_once __DIR__ . '/app/recruitment_bootstrap.php';
require_once __DIR__ . '/app/recruitment.php';
require_once __DIR__ . '/app/recruitment_security.php';
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store, private');
if (session_status() !== PHP_SESSION_ACTIVE) {
    json_response(['ok' => false, 'message' => 'Sesi tes tidak tersedia. Silakan coba lagi.'], 503);
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    json_response(['ok' => false, 'message' => 'Permintaan lintas situs ditolak.'], 403);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$expectedOrigin = ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
if ($origin !== '' && $origin !== $expectedOrigin) {
    json_response(['ok' => false, 'message' => 'Origin tes tidak diizinkan.'], 403);
}
try {
    $now = time();
    $directory = recruitment_storage_directory();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    recruitment_rate_limit($directory, $ip, 'requests', 120, 60, $now);
    if (method() === 'GET') {
        $type = $_GET['test'] ?? '';
        if (!is_string($type) || !isset(RECRUITMENT_DURATIONS[$type])) {
            throw new RecruitmentRequestException('Jenis tes tidak valid.', 422, 'invalid_test');
        }
        json_response(['ok' => true, 'csrf' => csrf_token()] + recruitment_state($_SESSION, $type, $now));
    }
    if (method() !== 'POST') {
        header('Allow: GET, POST');
        throw new RecruitmentRequestException('Metode tidak diizinkan.', 405, 'invalid_method');
    }
    require_csrf();
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new RecruitmentRequestException('Permintaan harus menggunakan JSON.', 415, 'invalid_content_type');
    }
    // Bound actual bytes, not only the client-controlled Content-Length header.
    $raw = file_get_contents('php://input', false, null, 0, 20001);
    if ($raw === false || strlen($raw) > 20000) {
        throw new RecruitmentRequestException('Data jawaban terlalu besar.', 413, 'body_too_large');
    }
    try { $payload = json_decode($raw, true, 12, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RecruitmentRequestException('JSON tidak valid.', 400, 'invalid_json'); }
    if (!is_array($payload) || array_is_list($payload)) {
        throw new RecruitmentRequestException('Data permintaan tidak valid.', 422, 'invalid_payload');
    }
    $type = $payload['test'] ?? null;
    $action = $payload['action'] ?? null;
    if (!is_string($type) || !isset(RECRUITMENT_DURATIONS[$type]) || !in_array($action, ['start', 'submit'], true)) {
        throw new RecruitmentRequestException('Jenis tes atau tindakan tidak valid.', 422, 'invalid_action');
    }
    $allowed = $action === 'start' ? ['test', 'action'] : ['test', 'action', 'attempt_id', 'answers'];
    if (array_diff(array_keys($payload), $allowed)) {
        throw new RecruitmentRequestException('Field permintaan tidak valid.', 422, 'invalid_fields');
    }
    if ($action === 'start') {
        if (!isset($_SESSION['recruitment_attempts'][$type]) && !isset($_SESSION['recruitment_results'][$type])) {
            recruitment_rate_limit($directory, $ip, 'new_attempts', 30, 3600, $now);
        }
        json_response(['ok' => true] + recruitment_start($_SESSION, $type, $now));
    }
    recruitment_rate_limit($directory, $ip, 'submissions', 60, 3600, $now);
    if (!is_string($payload['attempt_id'] ?? null) || !preg_match('/^[a-f0-9]{48}$/D', $payload['attempt_id'])
        || !is_array($payload['answers'] ?? null)) {
        throw new RecruitmentRequestException('Data jawaban tidak valid.', 422, 'invalid_answers');
    }
    json_response(['ok' => true] + recruitment_submit($_SESSION, $type, $payload['attempt_id'], $payload['answers'], $now));
} catch (RecruitmentRequestException $e) {
    if ($e->status === 429) header('Retry-After: 60');
    json_response(['ok' => false, 'error' => $e->error, 'message' => $e->getMessage()], $e->status);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => 'invalid_answers', 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Recruitment API error: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Layanan tes sementara tidak tersedia. Silakan coba lagi.'], 503);
}
