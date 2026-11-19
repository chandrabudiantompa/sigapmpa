<?php
declare(strict_types=1);

const RECRUITMENT_DURATIONS = ['aptitude' => 3600, 'workstyle' => 2700];

function recruitment_storage_directory(): string
{
    return sys_get_temp_dir() . '/sigap-recruitment-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);
}

final class RecruitmentRequestException extends RuntimeException
{
    public function __construct(string $message, public int $status, public string $error)
    {
        parent::__construct($message);
    }
}

function recruitment_state(array $session, string $type, int $now): array
{
    $attempt = $session['recruitment_attempts'][$type] ?? null;
    $result = $session['recruitment_results'][$type]['result'] ?? null;
    return ['result' => $result, 'server_now' => $now, 'attempt' => $attempt,
        'expired' => $attempt !== null && $result === null && $now >= $attempt['deadline']];
}

function recruitment_start(array &$session, string $type, int $now): array
{
    if (!isset(RECRUITMENT_DURATIONS[$type])) throw new InvalidArgumentException('Jenis tes tidak valid.');
    // Refreshing, opening another tab, or pressing start again never resets the deadline.
    if (!isset($session['recruitment_attempts'][$type]) && !isset($session['recruitment_results'][$type])) {
        $session['recruitment_attempts'][$type] = ['id' => bin2hex(random_bytes(24)),
            'started_at' => $now, 'deadline' => $now + RECRUITMENT_DURATIONS[$type]];
    }
    return recruitment_state($session, $type, $now);
}

function recruitment_submit(array &$session, string $type, string $attemptId, array $answers, int $now): array
{
    $attempt = $session['recruitment_attempts'][$type] ?? null;
    if (!$attempt || !hash_equals($attempt['id'], $attemptId)) {
        throw new RecruitmentRequestException('Sesi tes tidak valid. Buka tes melalui halaman pilihan.', 409, 'invalid_attempt');
    }
    if (isset($session['recruitment_results'][$type])) return recruitment_state($session, $type, $now);
    if ($now >= $attempt['deadline']) {
        throw new RecruitmentRequestException('Waktu tes sudah habis. Jawaban tidak dapat dikirim lagi.', 410, 'test_expired');
    }
    $result = recruitment_score($type, $answers);
    $result['submitted_at'] = date(DATE_ATOM, $now);
    $session['recruitment_results'][$type] = ['answers' => $answers, 'result' => $result];
    return recruitment_state($session, $type, $now);
}

// Fixed windows in a locked file outside the web root; persists across fresh cookies/sessions.
// REMOTE_ADDR only: never trust arbitrary X-Forwarded-For headers from clients.
function recruitment_rate_limit(string $directory, string $ip, string $bucket, int $limit, int $window, int $now): void
{
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create recruitment rate storage');
    }
    $handle = @fopen($directory . '/limits.json', 'c+');
    if (!$handle) throw new RuntimeException('Cannot open recruitment rate storage');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock recruitment rate storage');
        $raw = stream_get_contents($handle, 2000001);
        if ($raw === false || strlen($raw) > 2000000) throw new RuntimeException('Invalid recruitment rate storage');
        $entries = $raw === '' ? [] : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($entries)) throw new RuntimeException('Invalid recruitment rate storage');
        foreach ($entries as $key => $entry) {
            if (($entry['until'] ?? 0) <= $now) unset($entries[$key]);
        }
        $key = hash('sha256', $ip . '|' . $bucket);
        $entry = $entries[$key] ?? ['count' => 0, 'until' => $now + $window];
        if ($entry['count'] >= $limit) {
            throw new RecruitmentRequestException('Terlalu banyak permintaan tes. Silakan coba lagi nanti.', 429, 'rate_limited');
        }
        if (count($entries) >= 10000 && !isset($entries[$key])) throw new RuntimeException('Recruitment rate storage full');
        $entry['count']++;
        $entries[$key] = $entry;
        $encoded = json_encode($entries, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
            throw new RuntimeException('Cannot update recruitment rate storage');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
