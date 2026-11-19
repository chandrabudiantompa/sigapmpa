<?php
declare(strict_types=1);

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'invalid_json', 'message' => 'Payload JSON tidak valid.'], 400);
    }
    return $data;
}

function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function client_ip(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function user_agent(): string
{
    return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

function now_sql(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
        json_response(['ok' => false, 'error' => 'csrf_failed', 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
    }
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || trim((string)$data[$field]) === '') {
            json_response(['ok' => false, 'error' => 'validation_failed', 'message' => "Field {$field} wajib diisi."], 422);
        }
    }
}

function clean_string(mixed $value, int $max = 255): string
{
    return mb_substr(trim((string)$value), 0, $max);
}

function decimal_value(mixed $value): float
{
    if (is_string($value)) {
        $value = trim($value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }
    }
    return round((float)$value, 2);
}

function haversine_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function audit(?int $actorUserId, string $action, string $entityType, ?string $entityId = null, mixed $before = null, mixed $after = null): void
{
    $stmt = db()->prepare('INSERT INTO audit_logs (actor_user_id, action_name, entity_type, entity_id, before_json, after_json, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $actorUserId,
        $action,
        $entityType,
        $entityId,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        client_ip(),
        user_agent(),
    ]);
}

function security_event(?int $userId, ?int $employeeId, string $type, string $severity, string $detail): void
{
    $stmt = db()->prepare('INSERT INTO security_events (user_id, employee_id, event_type, severity, detail, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $employeeId, $type, $severity, $detail, client_ip(), user_agent()]);
}

function route_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $pos = strpos($path, '/api');
    if ($pos !== false) {
        $path = substr($path, $pos + 4);
    }
    return '/' . trim($path, '/');
}

function path_segments(): array
{
    $path = trim(route_path(), '/');
    return $path === '' ? [] : explode('/', $path);
}

function sigap_pantun_checkin_late(): string
{
    $pantun = [
        "Jalan-jalan ke Pasar Minggu,\nBeli duku sama rambutan.\nBangun pagi jangan kesiangan lagi,\nBiar gak kena sindiran rekan.",
        "Burung nuri burung dara,\nTerbang ke sarang di pohon jati.\nSudah tahu jam kerja dimulai mana,\nMasa absen selalu telat lagi hati-hati.",
        "Beli sarung di Tanah Abang,\nSarungnya bagus bermotif kotak.\nKalau terus terlambat datang,\nSupervisor bisa jadi geregetan agak.",
        "Pergi ke pasar membeli pisang,\nPisangnya manis dibawa pulang.\nBangun lebih pagi jangan sampai kesiangan,\nBiar gak jadi langganan terlambat datang.",
        "Ke kantin beli es kelapa,\nEs kelapa segar dengan gula aren.\nTerlambat terus apa sebabnya,\nSemoga besok sudah berubah, amin.",
        "Naik delman ke kota tua,\nKota tua ramai dikunjungi.\nSekali telat masih bisa dimaklumi semua,\nTapi jangan jadi kebiasaan ya, HSE lihat sendiri.",
        "Air mengalir ke muara,\nMuara luas menuju lautan.\nYuk pasang alarm lebih awal saudara,\nBiar absen masuk tak lagi jadi masalah perusahaan.",
        "Di sawah ada burung pipit,\nPipit terbang mencari makan.\nTerlambat sedikit memang menyakitkan sedikit,\nTapi perbaikan besok lebih utamakan.",
    ];
    return $pantun[array_rand($pantun)];
}

function sigap_pantun_checkin_ontime(): string
{
    $pantun = [
        "Pergi memancing ke tepi kali,\nDapat ikan dibawa pulang.\nDatang tepat waktu hari ini,\nSemangat kerja makin cemerlang.",
        "Bunga melati bunga mawar,\nMekar indah di pagi hari.\nDisiplin begini patut jadi ukur,\nTeladan baik untuk tim sendiri.",
        "Dari Bogor ke Jakarta,\nNaik kereta paling pagi.\nTepat waktu memang hebat sudah pasti,\nSemoga proyek lancar setiap hari.",
        "Beli kopi di kedai tepi jalan,\nKopinya hitam harum semerbak.\nOn time terus patut dibanggakan,\nSemoga rezeki hari ini makin banyak.",
        "Layang-layang terbang tinggi,\nDitiup angin pagi yang segar.\nDatang tepat waktu setiap hari,\nBukti kerja keras penuh semangat yang besar.",
        "Pagi hari embun berjatuhan,\nMembasahi daun di halaman.\nTerima kasih sudah tepat waktu datang,\nSemoga harimu penuh kelancaran.",
        "Naik sepeda keliling kota,\nUdara pagi terasa segar.\nDisiplin begini contoh yang nyata,\nSemoga proyek MPA makin berdampak besar.",
        "Pagi cerah burung berkicau,\nHinggap riang di dahan jati.\nSemangat kerja hari ini kian terjaga,\nSelamat bertugas, sukses selalu menyertai.",
    ];
    return $pantun[array_rand($pantun)];
}

function sigap_pantun_checkin(bool $isLate): string
{
    return $isLate ? sigap_pantun_checkin_late() : sigap_pantun_checkin_ontime();
}

function sigap_pantun_checkout(): string
{
    $pantun = [
        "Matahari terbenam di ufuk barat,\nBurung pulang menuju sarang.\nTerima kasih sudah bekerja keras dan hebat,\nSelamat istirahat, sampai jumpa esok petang.",
        "Senja tiba warnanya jingga,\nIndah dilihat dari beranda.\nHari ini kerja sudah tuntas semua,\nSelamat pulang, hati-hati di jalan ya.",
        "Pulang kerja naik angkutan,\nSampai rumah disambut keluarga.\nTerima kasih atas kerja keras hari ini kawan,\nIstirahat cukup, besok semangat lagi bekerja.",
        "Lampu kota mulai menyala,\nMenerangi jalan pulang ke rumah.\nSatu hari penuh perjuangan sudah dilalui semua,\nSelamat beristirahat, semoga esok makin cerah.",
        "Dari proyek pulang ke rumah,\nMelepas lelah bersama keluarga.\nTerima kasih untuk kerja kerasmu hari ini sudah,\nSampai jumpa besok dengan semangat baru.",
        "Petang datang angin pun sejuk,\nBurung kembali ke sarangnya.\nKerja hari ini sungguh membuahkan hasil yang elok,\nSelamat pulang, jaga diri di jalan raya.",
        "Bintang mulai tampak satu-satu,\nMenghiasi langit malam nan indah.\nSelesai sudah tugas hari ini kita semua,\nSampai jumpa esok dengan hati yang riang gembira.",
        "Jam pulang sudah tiba waktunya,\nSaatnya kembali ke rumah tercinta.\nTerima kasih atas dedikasi hari ini semua,\nSelamat beristirahat, sampai jumpa esok lagi.",
    ];
    return $pantun[array_rand($pantun)];
}

function telegram_send_message(string $botToken, string $chatId, string $text, ?array $replyMarkup = null): bool
{
    if ($botToken === '' || $chatId === '') {
        return false;
    }
    $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
    $body = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($replyMarkup !== null) {
        $body['reply_markup'] = $replyMarkup;
    }
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($response === false || $httpCode !== 200) {
                error_log('SIGAP MPA Telegram error: ' . ($curlError ?: ('HTTP ' . $httpCode . ' ' . (string)$response)));
                return false;
            }
            return true;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('SIGAP MPA Telegram error: gagal mengirim pesan (file_get_contents).');
            return false;
        }
        return true;
    } catch (Throwable $e) {
        error_log('SIGAP MPA Telegram error: ' . $e->getMessage());
        return false;
    }
}

function telegram_notify_checkin(array $employee, int $lateMinutes, string $locationName, DateTimeImmutable $checkInTime, bool $hasPermission): void
{
    global $config;
    $tg = $config['telegram'] ?? [];
    if (empty($tg['enabled']) || empty($tg['bot_token']) || empty($tg['owner_chat_id'])) {
        return;
    }
    if ($lateMinutes > 0) {
        $statusLabel = $hasPermission ? 'Terlambat (ada izin)' : 'Terlambat';
        $text = "⏰ *Karyawan Terlambat*\n\n"
            . "Nama: " . ($employee['full_name'] ?? '-') . "\n"
            . "Jabatan: " . ($employee['position_name'] ?? '-') . "\n"
            . "Jam Masuk: " . $checkInTime->format('H:i') . " WIB\n"
            . "Terlambat: {$lateMinutes} menit\n"
            . "Status: {$statusLabel}\n"
            . "Lokasi: " . $locationName;
    } else {
        $text = "✅ *Absen Masuk*\n\n"
            . "Nama: " . ($employee['full_name'] ?? '-') . "\n"
            . "Jabatan: " . ($employee['position_name'] ?? '-') . "\n"
            . "Jam Masuk: " . $checkInTime->format('H:i') . " WIB\n"
            . "Status: Tepat Waktu\n"
            . "Lokasi: " . $locationName;
    }
    telegram_send_message((string)$tg['bot_token'], (string)$tg['owner_chat_id'], $text);
}

function sigap_ensure_quick_token_table(): void
{
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS request_quick_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NOT NULL,
            decision ENUM('approved','rejected') NOT NULL,
            decider_user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME(6) NOT NULL,
            used_at DATETIME(6) NULL,
            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            UNIQUE KEY uniq_token_hash (token_hash),
            KEY idx_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('SIGAP MPA quick token table error: ' . $e->getMessage());
    }
}

function sigap_generate_quick_token(int $requestId, string $decision, int $deciderUserId): string
{
    sigap_ensure_quick_token_table();
    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s.u');
    db()->prepare('INSERT INTO request_quick_tokens (request_id,decision,decider_user_id,token_hash,expires_at) VALUES (?,?,?,?,?)')
        ->execute([$requestId, $decision, $deciderUserId, $hash, $expiresAt]);
    return $token;
}

function sigap_quick_decision_url(int $requestId, string $decision, string $token): string
{
    global $config;
    $base = rtrim((string)($config['app']['url'] ?? ''), '/');
    return $base . '/api/requests/quick-decision?id=' . $requestId . '&decision=' . $decision . '&token=' . $token;
}

function sigap_quick_decision_page(string $title, string $message, bool $success): never
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $color = $success ? '#168447' : '#d60812';
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . ' - SIGAP MPA</title>'
        . '<style>body{font-family:Inter,"Segoe UI",Arial,sans-serif;background:#f3f4f6;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:20px}'
        . '.box{background:#fff;border-radius:20px;padding:34px 28px;max-width:380px;text-align:center;box-shadow:0 12px 34px rgba(27,30,35,.12);border-top:6px solid ' . $color . '}'
        . 'h1{font-size:20px;color:' . $color . ';margin:0 0 12px}p{color:#373a3f;line-height:1.5;margin:0}</style></head>'
        . '<body><div class="box"><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p></div></body></html>';
    exit;
}

function sigap_ensure_telegram_link_tables(): void
{
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS employee_telegram_links (
            employee_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            chat_id VARCHAR(32) NOT NULL,
            linked_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('SIGAP MPA telegram link table error: ' . $e->getMessage());
    }
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS employee_telegram_link_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            employee_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME(6) NOT NULL,
            used_at DATETIME(6) NULL,
            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            UNIQUE KEY uniq_token_hash (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('SIGAP MPA telegram link token table error: ' . $e->getMessage());
    }
}

function telegram_notify_employee(int $employeeId, string $text): bool
{
    global $config;
    $botToken = (string)($config['telegram']['bot_token'] ?? '');
    if ($botToken === '') {
        return false;
    }
    sigap_ensure_telegram_link_tables();
    $stmt = db()->prepare('SELECT chat_id FROM employee_telegram_links WHERE employee_id=? LIMIT 1');
    $stmt->execute([$employeeId]);
    $chatId = $stmt->fetchColumn();
    if (!$chatId) {
        return false;
    }
    return telegram_send_message($botToken, (string)$chatId, $text);
}

function telegram_notify_request_decided(array $request, string $decision, ?string $decisionNote): void
{
    $typeLabels = [
        'leave' => 'Cuti', 'permission' => 'Izin', 'sick' => 'Sakit',
        'overtime' => 'Lembur', 'late_permission' => 'Izin Terlambat',
    ];
    $label = $typeLabels[$request['request_type']] ?? 'Pengajuan';
    $approved = $decision === 'approved';
    $emoji = $approved ? '✅' : '❌';
    $verb = $approved ? 'disetujui' : 'ditolak';
    $text = "{$emoji} *Pengajuan {$label} {$verb}*\n\n"
        . "Tanggal: " . $request['start_date'] . ($request['end_date'] !== $request['start_date'] ? ' s.d. ' . $request['end_date'] : '') . "\n"
        . "Status: " . ucfirst($verb);
    if ($decisionNote) {
        $text .= "\nCatatan: " . $decisionNote;
    }
    telegram_notify_employee((int)$request['employee_id'], $text);
}

function telegram_notify_request_submitted(array $employee, int $requestId, string $requestType, string $startDate, string $endDate, ?string $startTime, ?string $endTime, float $quantity, string $reason): void
{
    global $config;
    $tg = $config['telegram'] ?? [];
    if (empty($tg['enabled']) || empty($tg['bot_token']) || empty($tg['owner_chat_id'])) {
        return;
    }
    $typeLabels = [
        'leave' => ['📅', 'Pengajuan Cuti'],
        'permission' => ['📝', 'Pengajuan Izin'],
        'sick' => ['🤒', 'Pengajuan Sakit'],
        'overtime' => ['🕘', 'Pengajuan Lembur'],
        'late_permission' => ['⏰', 'Izin Terlambat'],
    ];
    [$emoji, $label] = $typeLabels[$requestType] ?? ['📄', 'Pengajuan'];
    $periodLine = $requestType === 'overtime'
        ? "Tanggal: {$startDate}" . ($startTime ? " ({$startTime}–{$endTime})" : '') . "\nDurasi: {$quantity} jam"
        : ($requestType === 'late_permission'
            ? "Tanggal: {$startDate}\nPerkiraan masuk: " . ($startTime ?? '-')
            : "Tanggal: {$startDate}" . ($endDate !== $startDate ? " s.d. {$endDate}" : '') . "\nJumlah: {$quantity} hari");
    $text = "{$emoji} *{$label}*\n\n"
        . "Nama: " . ($employee['full_name'] ?? '-') . "\n"
        . "Jabatan: " . ($employee['position_name'] ?? '-') . "\n"
        . $periodLine . "\n"
        . "Alasan: " . $reason;
    $replyMarkup = null;
    try {
        $ownerStmt = db()->prepare("SELECT id FROM users WHERE role='owner' AND status='active' ORDER BY id LIMIT 1");
        $ownerStmt->execute();
        $ownerUserId = (int)($ownerStmt->fetchColumn() ?: 0);
        if ($ownerUserId > 0) {
            $approveToken = sigap_generate_quick_token($requestId, 'approved', $ownerUserId);
            $rejectToken = sigap_generate_quick_token($requestId, 'rejected', $ownerUserId);
            $replyMarkup = [
                'inline_keyboard' => [[
                    ['text' => '✅ Approve', 'url' => sigap_quick_decision_url($requestId, 'approved', $approveToken)],
                    ['text' => '❌ Reject', 'url' => sigap_quick_decision_url($requestId, 'rejected', $rejectToken)],
                ]],
            ];
        }
    } catch (Throwable $e) {
        error_log('SIGAP MPA quick token error: ' . $e->getMessage());
    }
    telegram_send_message((string)$tg['bot_token'], (string)$tg['owner_chat_id'], $text, $replyMarkup);
}
