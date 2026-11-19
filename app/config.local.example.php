<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u6066544_mpa_workforce',
        'user' => 'u6066544_mpawf_app',
        'pass' => 'GANTI_DENGAN_PASSWORD_DATABASE',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'SIGAP MPA',
        'url' => 'https://sigap.multipowerabadi.co.id',
        'timezone' => 'Asia/Jakarta',
        'session_name' => 'SIGAPMPASESSID',
        'allowed_origin' => 'https://sigap.multipowerabadi.co.id',
        'force_https' => true,
    ],
    'attendance' => [
        'work_start' => '08:00',
        'work_end' => '17:00',
        // 1=Senin ... 7=Minggu. Default MPA: Senin-Sabtu.
        'work_days' => [1,2,3,4,5,6],
        // Isi tanggal libur khusus agar tidak dihitung sebagai alfa, contoh: ['2026-08-17'].
        'holidays' => [],
        'late_tolerance_minutes' => 15,
        'penalty_per_minute' => 1000,
        'overtime_rate_per_hour' => 25000,
    ],
    'security' => [
        'login_max_attempts' => 5,
        'login_window_minutes' => 15,
        'session_idle_minutes' => 120,
        'require_attendance_auth' => false,
        'require_webauthn_attendance' => false, // kompatibilitas versi lama
        'webauthn_rp_id' => 'sigap.multipowerabadi.co.id',
        'webauthn_origin' => 'https://sigap.multipowerabadi.co.id',
        // Kunci enkripsi data pribadi (NIK KTP, No. KK, NIK keluarga, rekening, NPWP,
        // catatan medis) untuk fitur Database Karyawan Lengkap. WAJIB diisi sebelum
        // fitur ini dipakai, dan JANGAN PERNAH diubah/dihapus setelah ada data
        // tersimpan (data terenkripsi akan tidak bisa dibuka lagi). Generate sekali
        // dengan: php -r "echo base64_encode(random_bytes(32));"
        'pii_encryption_key' => '',
    ],
    // Folder penyimpanan dokumen karyawan (KTP/KK/ijazah/dll) untuk fitur Database
    // Karyawan Lengkap. WAJIB di luar direktori yang bisa diakses lewat web (di luar
    // folder yang Bapak upload sebagai document root), supaya dokumen tidak bisa
    // diunduh langsung lewat URL. Contoh di Hostinger: '/home/u6066544/private_storage/employee_documents'
    // (ganti u6066544 dengan username hosting Bapak). Kosongkan untuk fallback otomatis.
    'storage' => [
        'employee_documents_path' => '',
    ],
    // Notifikasi Telegram ke Owner saat ada karyawan absen masuk terlambat.
    // 1. Chat dengan @BotFather di Telegram, buat bot baru, salin token ke bot_token.
    // 2. Owner chat bot-nya sekali (kirim pesan apa saja), lalu buka
    //    https://api.telegram.org/bot<TOKEN>/getUpdates untuk menemukan "chat":{"id": ...}
    //    dan salin angka itu ke owner_chat_id.
    // 3. Set enabled => true setelah bot_token dan owner_chat_id terisi.
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'owner_chat_id' => '',
        // Username bot (tanpa @), dipakai untuk link "Hubungkan Telegram" karyawan.
        'bot_username' => '',
    ],
    // Melindungi endpoint /api/cron/attendance-reminder dari pemanggilan sembarangan.
    // Generate sekali dengan: php -r "echo bin2hex(random_bytes(24));"
    // lalu pakai nilai yang sama sebagai ?key=... saat setting Cron Job di cPanel.
    'cron' => [
        'secret' => '',
    ],
    // Modul Tes Calon Karyawan (recruitment.html/aptitude.html/workstyle.html).
    // Hanya perlu diisi jika HTTPS dihentikan di reverse proxy/CDN di depan Apache.
    // Isi dengan IP proxy yang BENAR-BENAR dipercaya (exact match) — jangan kosongkan
    // sembarangan karena X-Forwarded-Proto dari IP lain akan diabaikan.
    // Jika HTTPS langsung lewat Apache (kasus umum di Hostinger), biarkan array kosong.
    'recruitment' => [
        'trusted_proxies' => [],
    ],
];
