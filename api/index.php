<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$path = route_path();
$segments = path_segments();
$httpMethod = method();

if ($httpMethod !== 'GET' && $path !== '/auth/login' && $path !== '/telegram/webhook') {
    require_csrf();
}


function sigap_permission_catalog(): array
{
    return [
        'dashboard' => ['label'=>'Dashboard & Absensi','group'=>'Utama','description'=>'Beranda, absen masuk/pulang, dan riwayat absensi sendiri'],
        'security' => ['label'=>'Perangkat Absensi','group'=>'Utama','description'=>'Daftar dan cek status HP yang digunakan untuk absensi'],
        'requests' => ['label'=>'Cuti, Izin & Lembur','group'=>'Karyawan','description'=>'Membuat dan melihat pengajuan cuti, izin, sakit, dan lembur'],
        'trips' => ['label'=>'Perjalanan Dinas','group'=>'Karyawan','description'=>'Membuat dan melihat pengajuan perjalanan dinas'],
        'payslips' => ['label'=>'Slip Gaji','group'=>'Karyawan','description'=>'Melihat slip gaji milik sendiri'],
        'announcements_view' => ['label'=>'Lihat Pengumuman','group'=>'Informasi','description'=>'Melihat pengumuman aktif pada Dashboard'],
        'announcements_manage' => ['label'=>'Kelola Pengumuman','group'=>'Informasi','description'=>'Membuat, mengubah, dan menghapus pengumuman'],
        'owner_monitor' => ['label'=>'Monitoring & Rekap Absensi','group'=>'Manajemen','description'=>'Melihat kehadiran harian, rekap absensi per karyawan, dan export dasar payroll'],
        'recruitment' => ['label'=>'Tes Masuk Karyawan','group'=>'Manajemen','description'=>'Mengelola kandidat, mengirim link tes IQ/DISC, dan melihat hasilnya'],
        'employees' => ['label'=>'Master Karyawan','group'=>'Administrasi','description'=>'Melihat dan mengelola data karyawan serta akun'],
        'employee_profile_self' => ['label'=>'Profil Saya','group'=>'Karyawan','description'=>'Melengkapi dan mengajukan perubahan data pribadi sendiri (Database Karyawan Lengkap)'],
        'employee_profile_manage' => ['label'=>'Database Karyawan Lengkap','group'=>'Administrasi','description'=>'Verifikasi, kelola, ekspor, dan audit seluruh data pribadi karyawan'],
        'employee_profile_team' => ['label'=>'Data Tim (Terbatas)','group'=>'Manajemen','description'=>'Melihat nama, jabatan, kontak kerja, dan kontak darurat anggota tim (tanpa data sensitif)'],
        'employee_profile_payroll' => ['label'=>'Data Payroll Karyawan','group'=>'Finance','description'=>'Melihat rekening, NPWP, status PTKP, dan BPJS untuk keperluan penggajian'],
        'positions_access' => ['label'=>'Jabatan & Hak Akses','group'=>'Administrasi','description'=>'Membuat master jabatan dan mengatur menu aktif per jabatan'],
        'shifts' => ['label'=>'Shift Kerja','group'=>'Administrasi','description'=>'Mengatur jam masuk, jam pulang, hari kerja, jadwal jabatan, dan override per karyawan'],
        'locations' => ['label'=>'Lokasi Kerja','group'=>'Administrasi','description'=>'Mengatur lokasi, radius GPS, dan geofence absensi'],
        'devices' => ['label'=>'Persetujuan HP','group'=>'Administrasi','description'=>'Approve, reset, cabut, dan delete data perangkat'],
        'payroll' => ['label'=>'Payroll','group'=>'Finance','description'=>'Struktur gaji, komponen bulanan, generate payroll, dan export'],
        'audit' => ['label'=>'Audit Log','group'=>'Kontrol','description'=>'Melihat catatan aktivitas sistem'],
    ];
}

function sigap_role_permissions(): array
{
    return [
        'employee' => [
            'label' => 'Karyawan',
            'permissions' => ['dashboard','security','requests','trips','payslips','announcements_view','employee_profile_self']
        ],
        'supervisor' => [
            'label' => 'Supervisor',
            'permissions' => ['dashboard','security','requests','trips','payslips','announcements_view','employee_profile_self','employee_profile_team']
        ],
        'hr_admin' => [
            'label' => 'HR Admin',
            'permissions' => ['dashboard','security','requests','trips','payslips','announcements_view','announcements_manage','owner_monitor','recruitment','employees','locations','devices','shifts','employee_profile_self','employee_profile_manage']
        ],
        'payroll_finance' => [
            'label' => 'Finance Payroll',
            'permissions' => ['dashboard','security','payslips','announcements_view','owner_monitor','employees','payroll','employee_profile_self','employee_profile_payroll']
        ],
        'owner' => [
            'label' => 'Owner',
            'permissions' => ['dashboard','requests','announcements_view','announcements_manage','owner_monitor']
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'permissions' => array_keys(sigap_permission_catalog())
        ],
    ];
}

function sigap_normalize_permissions_json(mixed $json, string $role): array
{
    $catalog = sigap_permission_catalog();
    $roles = sigap_role_permissions();
    $fallback = $roles[$role]['permissions'] ?? ['dashboard','security'];

    if ($json === null || $json === '') {
        return array_values(array_unique($fallback));
    }

    $decoded = is_array($json) ? $json : json_decode((string)$json, true);
    if (!is_array($decoded)) {
        return array_values(array_unique($fallback));
    }

    $allowed = [];
    foreach ($decoded as $item) {
        if (is_string($item) && isset($catalog[$item])) {
            $allowed[] = $item;
        }
    }

    if (!$allowed) {
        return array_values(array_unique($fallback));
    }

    if (!in_array('dashboard', $allowed, true)) $allowed[] = 'dashboard';
    if (!in_array('security', $allowed, true)) $allowed[] = 'security';
    if ($role === 'super_admin') $allowed = array_keys($catalog);

    return array_values(array_unique($allowed));
}

function sigap_permission_labels(array $permissions): array
{
    $catalog = sigap_permission_catalog();
    $labels = [];
    foreach ($permissions as $key) {
        if (isset($catalog[$key])) $labels[] = $catalog[$key]['label'];
    }
    return $labels;
}

function sigap_excel_col_index(string $letters): int { $n=0; foreach(str_split($letters) as $c){$n=$n*26+(ord($c)-64);} return $n-1; }
function sigap_read_payroll_file(string $tmp, string $name): array {
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    if($ext==='csv'){
        $fh=fopen($tmp,'rb'); if(!$fh) throw new RuntimeException('File tidak dapat dibaca.');
        $rows=[]; while(($r=fgetcsv($fh,0,','))!==false)$rows[]=$r; fclose($fh); return $rows;
    }
    if($ext!=='xlsx') throw new RuntimeException('Gunakan file .xlsx atau .csv.');
    if(!class_exists('ZipArchive')) throw new RuntimeException('Ekstensi ZIP PHP belum aktif di hosting.');
    $zip=new ZipArchive(); if($zip->open($tmp)!==true) throw new RuntimeException('File Excel tidak valid.');
    $shared=[]; $sx=$zip->getFromName('xl/sharedStrings.xml');
    if($sx!==false){$xml=simplexml_load_string($sx); if($xml)foreach($xml->si as $si){$parts=[]; if(isset($si->t))$parts[]=(string)$si->t; foreach($si->r as $r)$parts[]=(string)$r->t; $shared[]=implode('',$parts);}}
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); if($sheet===false)throw new RuntimeException('Sheet pertama tidak ditemukan.');
    $xml=simplexml_load_string($sheet); if(!$xml)throw new RuntimeException('Isi Excel tidak dapat dibaca.');
    $rows=[]; foreach($xml->sheetData->row as $row){$out=[]; foreach($row->c as $c){$ref=(string)$c['r']; preg_match('/([A-Z]+)/',$ref,$m); $i=sigap_excel_col_index($m[1]??'A'); $type=(string)$c['t']; $v=(string)$c->v; if($type==='s')$v=$shared[(int)$v]??''; elseif($type==='inlineStr')$v=(string)$c->is->t; $out[$i]=$v;} if($out){ksort($out);$max=max(array_keys($out));$rows[]=array_map(fn($i)=>$out[$i]??'',range(0,$max));}}
    return $rows;
}
function sigap_payroll_number(mixed $v): float { if(is_numeric($v))return (float)$v; $x=preg_replace('/[^0-9,.-]/','',(string)$v); if(str_contains($x,',')&&!str_contains($x,'.'))$x=str_replace(',','.',$x); else $x=str_replace(',','',$x); return is_numeric($x)?(float)$x:0.0; }
function sigap_payroll_import_rows(PDO $pdo,array $raw): array {
    if(count($raw)<2)throw new RuntimeException('Template tidak berisi data karyawan.');
    $norm=fn($x)=>strtolower(trim(preg_replace('/\\s+/',' ',(string)$x)));
    $headers=array_map($norm,$raw[0]); $aliases=[
      'periode'=>['periode','period'],'nik'=>['nik','kode karyawan','nik/kode karyawan'],'nama'=>['nama','nama karyawan'],
      'gaji_pokok'=>['gaji pokok'],'lembur_jam'=>['lembur jam','jam lembur'],'tarif_lembur'=>['tarif lembur/jam','tarif lembur per jam'],'total_lembur'=>['total lembur'],
      'tunjangan'=>['tunjangan'],'bonus'=>['bonus'],'lain_tambah'=>['lain-lain tambahan','lain lain tambahan'],'pot_telat'=>['potongan terlambat'],
      'pot_absen'=>['potongan absensi'],'pot_lain'=>['potongan lain-lain','potongan lain lain'],'reimbursement'=>['reimbursement'],'total'=>['total diterima','total transfer'],'catatan'=>['catatan']];
    $idx=[]; foreach($aliases as $k=>$names){foreach($names as $n){$i=array_search($n,$headers,true);if($i!==false){$idx[$k]=$i;break;}}}
    foreach(['periode','nik','gaji_pokok','total'] as $r)if(!isset($idx[$r]))throw new RuntimeException('Kolom wajib tidak ditemukan: '.$r);
    $empStmt=$pdo->prepare('SELECT id,nik,full_name FROM employees WHERE nik=? AND employment_status=\'active\' LIMIT 1'); $out=[];
    foreach(array_slice($raw,1) as $lineNo=>$r){if(!array_filter($r,fn($v)=>trim((string)$v)!==''))continue; $get=fn($k)=>$r[$idx[$k]??-1]??''; $period=trim((string)$get('periode'));$nik=trim((string)$get('nik'));$errors=[];$warnings=[];
      if(!preg_match('/^\\d{4}-\\d{2}$/',$period))$errors[]='Periode harus YYYY-MM'; $empStmt->execute([$nik]);$emp=$empStmt->fetch();if(!$emp)$errors[]='NIK tidak ditemukan/aktif';
      $n=[];foreach(['gaji_pokok','lembur_jam','tarif_lembur','total_lembur','tunjangan','bonus','lain_tambah','pot_telat','pot_absen','pot_lain','reimbursement','total'] as $k)$n[$k]=sigap_payroll_number($get($k));
      $calcLembur=$n['total_lembur'] ?: $n['lembur_jam']*$n['tarif_lembur']; $expected=$n['gaji_pokok']+$calcLembur+$n['tunjangan']+$n['bonus']+$n['lain_tambah']-$n['pot_telat']-$n['pot_absen']-$n['pot_lain']+$n['reimbursement'];
      if(abs($expected-$n['total'])>1)$errors[]='Total diterima tidak sesuai formula (seharusnya '.number_format($expected,0,',','.').')';
      if($emp && trim((string)$get('nama'))!=='' && $norm($get('nama'))!==$norm($emp['full_name']))$warnings[]='Nama berbeda dengan master; sistem memakai nama master';
      $out[]=['line'=>$lineNo+2,'employee_id'=>$emp?(int)$emp['id']:null,'nik'=>$nik,'full_name'=>$emp['full_name']??trim((string)$get('nama')),'period'=>$period,'base_salary'=>$n['gaji_pokok'],'overtime_hours'=>$n['lembur_jam'],'overtime_rate'=>$n['tarif_lembur'],'overtime_total'=>$calcLembur,'allowance'=>$n['tunjangan'],'bonus'=>$n['bonus'],'other_earning'=>$n['lain_tambah'],'late_deduction'=>$n['pot_telat'],'absence_deduction'=>$n['pot_absen'],'other_deduction'=>$n['pot_lain'],'reimbursement'=>$n['reimbursement'],'total_transfer'=>$n['total'],'note'=>trim((string)$get('catatan')),'errors'=>$errors,'warnings'=>$warnings,'valid'=>!$errors];
    } return $out;
}


function sigap_get_user_permissions(array $user): array
{
    $role = (string)($user['role'] ?? 'employee');
    if ($role === 'super_admin') return array_keys(sigap_permission_catalog());

    $position = (string)($user['position_name'] ?? '');
    $department = (string)($user['department_name'] ?? '');
    if ($position !== '') {
        

try {
            sigap_ensure_position_access_table();
            $stmt = db()->prepare('SELECT role, permissions_json FROM position_access_profiles WHERE position_name=? AND department_name=? LIMIT 1');
            $stmt->execute([$position, $department]);
            $profile = $stmt->fetch();
            if ($profile) {
                $permissions = sigap_normalize_permissions_json($profile['permissions_json'] ?? null, (string)($profile['role'] ?: $role));
                if ($role === 'hr_admin' && !in_array('shifts',$permissions,true)) $permissions[] = 'shifts';
                if ((!empty($user['employee_id']) || $role === 'owner') && !in_array('requests',$permissions,true)) $permissions[] = 'requests';
                return array_values(array_unique($permissions));
            }
        } catch (Throwable $e) {
            // Fallback to role defaults when the access table is not ready.
        }
    }

    $permissions = sigap_normalize_permissions_json(null, $role);
    if ($role === 'hr_admin' && !in_array('shifts',$permissions,true)) $permissions[] = 'shifts';
    if ((!empty($user['employee_id']) || $role === 'owner') && !in_array('requests',$permissions,true)) $permissions[] = 'requests';
    return array_values(array_unique($permissions));
}

function sigap_require_permission(string $permission): array
{
    $user = require_auth();
    $permissions = sigap_get_user_permissions($user);
    if (!in_array($permission, $permissions, true)) {
        security_event((int)$user['id'], $user['employee_id'] ? (int)$user['employee_id'] : null, 'access_denied', 'warning', 'Permission ' . $permission . ' ditolak ke ' . route_path());
        json_response(['ok'=>false,'error'=>'forbidden','message'=>'Anda tidak memiliki hak akses.'],403);
    }
    return $user;
}

function sigap_infer_role(string $roles): string
{
    $roles = array_filter(array_map('trim', explode(',', $roles)));
    $priority = ['super_admin','owner','payroll_finance','hr_admin','supervisor','employee'];
    foreach ($priority as $role) {
        if (in_array($role, $roles, true)) return $role;
    }
    return 'employee';
}

function sigap_ensure_position_access_table(): void
{
    db()->exec("\n        CREATE TABLE IF NOT EXISTS job_positions (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            position_name VARCHAR(120) NOT NULL,\n            department_name VARCHAR(120) NOT NULL DEFAULT '',\n            default_role ENUM('employee','supervisor','hr_admin','payroll_finance','owner','super_admin') NOT NULL DEFAULT 'employee',\n            description TEXT NULL,\n            is_active TINYINT(1) NOT NULL DEFAULT 1,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            UNIQUE KEY uniq_job_position_department (position_name, department_name),\n            INDEX idx_job_positions_active (is_active),\n            INDEX idx_job_positions_role (default_role)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    db()->exec("\n        CREATE TABLE IF NOT EXISTS position_access_profiles (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            position_name VARCHAR(120) NOT NULL,\n            department_name VARCHAR(120) NOT NULL DEFAULT '',\n            role ENUM('employee','supervisor','hr_admin','payroll_finance','owner','super_admin') NOT NULL DEFAULT 'employee',\n            permissions_json LONGTEXT NULL,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            UNIQUE KEY uniq_position_department (position_name, department_name),\n            INDEX idx_position_access_role (role)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
}

function sigap_ensure_announcements_table(): void
{
    db()->exec("\n        CREATE TABLE IF NOT EXISTS announcements (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            title VARCHAR(180) NOT NULL,\n            body TEXT NOT NULL,\n            audience ENUM('all','employee','supervisor','hr_admin','payroll_finance','owner','super_admin') NOT NULL DEFAULT 'all',\n            priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',\n            is_active TINYINT(1) NOT NULL DEFAULT 1,\n            published_at DATETIME(6) NULL,\n            expires_at DATETIME(6) NULL,\n            created_by_user_id BIGINT UNSIGNED NULL,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            INDEX idx_announcements_active (is_active, published_at, expires_at),\n            INDEX idx_announcements_audience (audience),\n            CONSTRAINT fk_announcements_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
}

// ================= Rekrutmen: Tes Masuk Karyawan (IQ + DISC) =================
// Modul mandiri: kandidat BUKAN baris di `employees`/`users`, akses tes lewat
// access_token sekali pakai tanpa login. Lihat
// SIGAP_HANDOVER/03_DOCUMENTATION/features/TES_MASUK_KARYAWAN_DESIGN.md

function sigap_ensure_recruitment_tables(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_candidates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(160) NOT NULL,
        applied_position VARCHAR(120) NOT NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(160) NULL,
        notes TEXT NULL,
        created_by_user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_candidates_created_by (created_by_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_question_banks (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        test_type ENUM('iq','disc') NOT NULL,
        title VARCHAR(160) NOT NULL,
        version VARCHAR(20) NOT NULL DEFAULT '1.0',
        duration_minutes SMALLINT UNSIGNED NOT NULL,
        total_questions SMALLINT UNSIGNED NOT NULL,
        pass_threshold SMALLINT UNSIGNED NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_questions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bank_id BIGINT UNSIGNED NOT NULL,
        question_number SMALLINT UNSIGNED NOT NULL,
        question_text TEXT NULL,
        question_image_path VARCHAR(255) NULL,
        UNIQUE KEY uq_bank_question_number (bank_id, question_number),
        CONSTRAINT fk_question_bank FOREIGN KEY (bank_id) REFERENCES test_question_banks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_question_options (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id BIGINT UNSIGNED NOT NULL,
        option_label CHAR(1) NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        is_correct TINYINT(1) NULL,
        disc_trait ENUM('D','I','S','C') NULL,
        UNIQUE KEY uq_question_option_label (question_id, option_label),
        CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES test_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_iq_norm_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bank_id BIGINT UNSIGNED NOT NULL,
        raw_score_min TINYINT UNSIGNED NOT NULL,
        raw_score_max TINYINT UNSIGNED NOT NULL,
        scaled_score SMALLINT UNSIGNED NOT NULL,
        category_label VARCHAR(60) NOT NULL,
        INDEX idx_norm_bank (bank_id),
        CONSTRAINT fk_norm_bank FOREIGN KEY (bank_id) REFERENCES test_question_banks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        candidate_id BIGINT UNSIGNED NOT NULL,
        iq_bank_id BIGINT UNSIGNED NULL,
        disc_bank_id BIGINT UNSIGNED NULL,
        access_token CHAR(64) NOT NULL,
        status ENUM('not_started','iq_in_progress','iq_completed','disc_in_progress','completed','expired','aborted') NOT NULL DEFAULT 'not_started',
        iq_started_at DATETIME(6) NULL,
        iq_finished_at DATETIME(6) NULL,
        iq_raw_score TINYINT UNSIGNED NULL,
        iq_scaled_score SMALLINT UNSIGNED NULL,
        iq_category VARCHAR(60) NULL,
        iq_recommendation ENUM('direkomendasikan','tidak_direkomendasikan') NULL,
        disc_started_at DATETIME(6) NULL,
        disc_finished_at DATETIME(6) NULL,
        disc_result_json LONGTEXT NULL,
        invited_by_user_id BIGINT UNSIGNED NOT NULL,
        expires_at DATETIME(6) NOT NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY uq_sessions_access_token (access_token),
        INDEX idx_sessions_candidate (candidate_id),
        CONSTRAINT fk_session_candidate FOREIGN KEY (candidate_id) REFERENCES test_candidates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_iq_answers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id BIGINT UNSIGNED NOT NULL,
        question_id BIGINT UNSIGNED NOT NULL,
        selected_option CHAR(1) NULL,
        is_correct TINYINT(1) NULL,
        answered_at DATETIME(6) NULL,
        UNIQUE KEY uq_iq_session_question (session_id, question_id),
        CONSTRAINT fk_iqans_session FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE,
        CONSTRAINT fk_iqans_question FOREIGN KEY (question_id) REFERENCES test_questions(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_disc_answers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id BIGINT UNSIGNED NOT NULL,
        question_id BIGINT UNSIGNED NOT NULL,
        most_option CHAR(1) NOT NULL,
        least_option CHAR(1) NOT NULL,
        answered_at DATETIME(6) NULL,
        UNIQUE KEY uq_disc_session_question (session_id, question_id),
        CONSTRAINT fk_discans_session FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE,
        CONSTRAINT fk_discans_question FOREIGN KEY (question_id) REFERENCES test_questions(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_session_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id BIGINT UNSIGNED NOT NULL,
        event_type VARCHAR(40) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_events_session (session_id),
        CONSTRAINT fk_event_session FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function sigap_recruitment_seed_data(): array
{
    return [
        'iq' => [
            ['number'=>1,'text'=>'2, 4, 6, 8, 10, ...?','options'=>[['A','12',1],['B','13',0],['C','14',0],['D','16',0],['E','11',0]]],
            ['number'=>2,'text'=>'3, 6, 12, 24, 48, ...?','options'=>[['A','84',0],['B','96',1],['C','100',0],['D','72',0],['E','120',0]]],
            ['number'=>3,'text'=>'1, 4, 9, 16, 25, ...?','options'=>[['A','30',0],['B','32',0],['C','36',1],['D','34',0],['E','49',0]]],
            ['number'=>4,'text'=>'100, 90, 81, 73, 66, ...?','options'=>[['A','59',0],['B','61',0],['C','58',0],['D','60',1],['E','62',0]]],
            ['number'=>5,'text'=>'5, 10, 20, 40, 80, ...?','options'=>[['A','120',0],['B','150',0],['C','140',0],['D','180',0],['E','160',1]]],
            ['number'=>6,'text'=>'2, 3, 5, 8, 13, ...?','options'=>[['A','21',1],['B','20',0],['C','23',0],['D','19',0],['E','22',0]]],
            ['number'=>7,'text'=>'7, 14, 28, 56, ...?','options'=>[['A','100',0],['B','112',1],['C','120',0],['D','106',0],['E','98',0]]],
            ['number'=>8,'text'=>'1, 3, 6, 10, 15, ...?','options'=>[['A','20',0],['B','18',0],['C','21',1],['D','19',0],['E','22',0]]],
            ['number'=>9,'text'=>'81, 27, 9, 3, ...?','options'=>[['A','6',0],['B','9',0],['C','0',0],['D','1',1],['E','2',0]]],
            ['number'=>10,'text'=>'2, 6, 12, 20, 30, ...?','options'=>[['A','45',0],['B','40',0],['C','44',0],['D','41',0],['E','42',1]]],
            ['number'=>11,'text'=>'4, 9, 16, 25, 36, ...?','options'=>[['A','49',1],['B','52',0],['C','42',0],['D','45',0],['E','47',0]]],
            ['number'=>12,'text'=>'10, 9, 11, 10, 12, 11, ...?','options'=>[['A','14',0],['B','13',1],['C','16',0],['D','12',0],['E','15',0]]],
            ['number'=>13,'text'=>'Sebuah baju berharga Rp150.000 mendapat diskon 20%. Berapa harga setelah diskon?','options'=>[['A','Rp130.000',0],['B','Rp100.000',0],['C','Rp120.000',1],['D','Rp110.000',0],['E','Rp135.000',0]]],
            ['number'=>14,'text'=>'Jarak kota A ke kota B adalah 240 km, ditempuh dalam 4 jam. Berapa kecepatan rata-rata?','options'=>[['A','65 km/jam',0],['B','55 km/jam',0],['C','70 km/jam',0],['D','60 km/jam',1],['E','50 km/jam',0]]],
            ['number'=>15,'text'=>'Gaji seorang karyawan Rp4.000.000 per bulan naik 10%. Berapa gaji barunya?','options'=>[['A','Rp4.500.000',0],['B','Rp4.300.000',0],['C','Rp4.200.000',0],['D','Rp4.100.000',0],['E','Rp4.400.000',1]]],
            ['number'=>16,'text'=>'3 mesin menyelesaikan sebuah pekerjaan dalam 12 hari. Jika ada 6 mesin dengan laju kerja sama, berapa hari dibutuhkan?','options'=>[['A','6 hari',1],['B','4 hari',0],['C','5 hari',0],['D','8 hari',0],['E','7 hari',0]]],
            ['number'=>17,'text'=>'Sebuah tangki terisi 3/4 penuh berisi 300 liter. Berapa kapasitas penuh tangki tersebut?','options'=>[['A','350 liter',0],['B','400 liter',1],['C','375 liter',0],['D','425 liter',0],['E','450 liter',0]]],
            ['number'=>18,'text'=>'Rata-rata nilai 5 siswa adalah 80. Seorang siswa baru bernilai 92 bergabung. Berapa rata-rata 6 siswa sekarang?','options'=>[['A','80',0],['B','81',0],['C','82',1],['D','83',0],['E','84',0]]],
            ['number'=>19,'text'=>'Sebuah barang dibeli Rp80.000 dan dijual Rp100.000. Berapa persen keuntungannya?','options'=>[['A','30%',0],['B','35%',0],['C','20%',0],['D','25%',1],['E','15%',0]]],
            ['number'=>20,'text'=>'Dua pertiga dari sebuah bilangan adalah 48. Berapakah bilangan tersebut?','options'=>[['A','70',0],['B','68',0],['C','76',0],['D','64',0],['E','72',1]]],
            ['number'=>21,'text'=>'5 pekerja menyelesaikan sebuah proyek dalam 8 hari. Berapa hari dibutuhkan oleh 4 pekerja dengan laju kerja sama?','options'=>[['A','10 hari',1],['B','12 hari',0],['C','8 hari',0],['D','11 hari',0],['E','9 hari',0]]],
            ['number'=>22,'text'=>'Harga 3 buku yang sama adalah Rp45.000. Berapa harga 7 buku dengan harga per buku yang sama?','options'=>[['A','Rp95.000',0],['B','Rp105.000',1],['C','Rp110.000',0],['D','Rp115.000',0],['E','Rp100.000',0]]],
            ['number'=>23,'text'=>'DOKTER : RUMAH SAKIT = GURU : ...?','options'=>[['A','Kantor',0],['B','Pasar',0],['C','Sekolah',1],['D','Bengkel',0],['E','Toko',0]]],
            ['number'=>24,'text'=>'KUNCI : GEMBOK = PASSWORD : ...?','options'=>[['A','Meja',0],['B','Kertas',0],['C','Rumah',0],['D','Akun',1],['E','Gembok',0]]],
            ['number'=>25,'text'=>'IKAN : AIR = BURUNG : ...?','options'=>[['A','Pohon',0],['B','Api',0],['C','Air',0],['D','Tanah',0],['E','Udara',1]]],
            ['number'=>26,'text'=>'PENA : MENULIS = GUNTING : ...?','options'=>[['A','Memotong',1],['B','Mengukur',0],['C','Melipat',0],['D','Mengikat',0],['E','Menempel',0]]],
            ['number'=>27,'text'=>'PANAS : DINGIN = TERANG : ...?','options'=>[['A','Basah',0],['B','Gelap',1],['C','Kering',0],['D','Lembut',0],['E','Cerah',0]]],
            ['number'=>28,'text'=>'JAM : WAKTU = TERMOMETER : ...?','options'=>[['A','Berat',0],['B','Panjang',0],['C','Suhu',1],['D','Kecepatan',0],['E','Tekanan',0]]],
            ['number'=>29,'text'=>'PETANI : SAWAH = NELAYAN : ...?','options'=>[['A','Gunung',0],['B','Hutan',0],['C','Kebun',0],['D','Laut',1],['E','Pasar',0]]],
            ['number'=>30,'text'=>'BUKU : PERPUSTAKAAN = OBAT : ...?','options'=>[['A','Kantor',0],['B','Bank',0],['C','Pasar',0],['D','Sekolah',0],['E','Apotek',1]]],
            ['number'=>31,'text'=>'RODA : MOBIL = SAYAP : ...?','options'=>[['A','Pesawat',1],['B','Kereta',0],['C','Kapal',0],['D','Sepeda',0],['E','Mobil',0]]],
            ['number'=>32,'text'=>'GURU : MENGAJAR = KOKI : ...?','options'=>[['A','Menulis',0],['B','Memasak',1],['C','Menyanyi',0],['D','Melukis',0],['E','Mengemudi',0]]],
            ['number'=>33,'text'=>'Sinonim dari kata \'CERDAS\' adalah?','options'=>[['A','Bodoh',0],['B','Kaku',0],['C','Pintar',1],['D','Lambat',0],['E','Malas',0]]],
            ['number'=>34,'text'=>'Sinonim dari kata \'GIGIH\' adalah?','options'=>[['A','Ragu',0],['B','Takut',0],['C','Malas',0],['D','Tekun',1],['E','Menyerah',0]]],
            ['number'=>35,'text'=>'Sinonim dari kata \'MAKMUR\' adalah?','options'=>[['A','Kacau',0],['B','Sengsara',0],['C','Miskin',0],['D','Lemah',0],['E','Sejahtera',1]]],
            ['number'=>36,'text'=>'Sinonim dari kata \'TELITI\' adalah?','options'=>[['A','Cermat',1],['B','Sembarangan',0],['C','Ceroboh',0],['D','Lambat',0],['E','Malas',0]]],
            ['number'=>37,'text'=>'Sinonim dari kata \'MUSYAWARAH\' adalah?','options'=>[['A','Konflik',0],['B','Diskusi',1],['C','Sengketa',0],['D','Pertengkaran',0],['E','Perdebatan',0]]],
            ['number'=>38,'text'=>'Sinonim dari kata \'AMANAH\' adalah?','options'=>[['A','Lalai',0],['B','Curang',0],['C','Dapat dipercaya',1],['D','Sembrono',0],['E','Khianat',0]]],
            ['number'=>39,'text'=>'Antonim dari kata \'OPTIMIS\' adalah?','options'=>[['A','Gigih',0],['B','Semangat',0],['C','Yakin',0],['D','Pesimis',1],['E','Percaya',0]]],
            ['number'=>40,'text'=>'Antonim dari kata \'MAJU\' adalah?','options'=>[['A','Naik',0],['B','Lanjut',0],['C','Berkembang',0],['D','Tumbuh',0],['E','Mundur',1]]],
            ['number'=>41,'text'=>'Antonim dari kata \'HEMAT\' adalah?','options'=>[['A','Boros',1],['B','Bijak',0],['C','Cermat',0],['D','Irit',0],['E','Teliti',0]]],
            ['number'=>42,'text'=>'Antonim dari kata \'RAJIN\' adalah?','options'=>[['A','Ulet',0],['B','Malas',1],['C','Disiplin',0],['D','Tekun',0],['E','Giat',0]]],
            ['number'=>43,'text'=>'Antonim dari kata \'TERBUKA\' adalah?','options'=>[['A','Jujur',0],['B','Ramah',0],['C','Tertutup',1],['D','Jelas',0],['E','Transparan',0]]],
            ['number'=>44,'text'=>'Antonim dari kata \'KOKOH\' adalah?','options'=>[['A','Kuat',0],['B','Stabil',0],['C','Kekar',0],['D','Rapuh',1],['E','Tegar',0]]],
            ['number'=>45,'text'=>'Semua manajer di perusahaan wajib hadir rapat mingguan. Toni adalah manajer. Kesimpulan yang tepat?','options'=>[['A','Tidak dapat disimpulkan',0],['B','Toni hanya hadir rapat bulanan',0],['C','Rapat mingguan bersifat opsional bagi Toni',0],['D','Toni tidak perlu hadir rapat',0],['E','Toni wajib hadir rapat mingguan',1]]],
            ['number'=>46,'text'=>'Jika proyek selesai tepat waktu, maka bonus dibagikan. Bonus ternyata tidak dibagikan. Kesimpulan yang tepat?','options'=>[['A','Proyek tidak selesai tepat waktu',1],['B','Proyek selesai tepat waktu',0],['C','Bonus akan dibagikan bulan depan',0],['D','Proyek dibatalkan',0],['E','Tidak dapat disimpulkan',0]]],
            ['number'=>47,'text'=>'Semua staf yang lolos tes wajib mengikuti training. Dedi tidak mengikuti training. Kesimpulan yang tepat?','options'=>[['A','Tidak dapat disimpulkan',0],['B','Dedi tidak lolos tes',1],['C','Dedi sedang cuti',0],['D','Training bersifat opsional',0],['E','Dedi lolos tes',0]]],
            ['number'=>48,'text'=>'Sebagian karyawan produksi bekerja shift malam. Semua yang bekerja shift malam mendapat tunjangan shift. Kesimpulan yang tepat?','options'=>[['A','Tidak dapat disimpulkan',0],['B','Semua karyawan produksi mendapat tunjangan shift',0],['C','Sebagian karyawan produksi mendapat tunjangan shift',1],['D','Tidak ada karyawan produksi yang mendapat tunjangan shift',0],['E','Semua karyawan mendapat tunjangan shift',0]]],
            ['number'=>49,'text'=>'Jika anggaran disetujui, maka proyek dimulai bulan depan. Ternyata proyek dimulai bulan depan. Kesimpulan yang tepat?','options'=>[['A','Anggaran pasti tidak disetujui',0],['B','Proyek dibatalkan',0],['C','Anggaran pasti disetujui',0],['D','Tidak dapat dipastikan apakah anggaran disetujui',1],['E','Anggaran ditunda',0]]],
            ['number'=>50,'text'=>'Semua supervisor pernah menjadi karyawan biasa. Rian belum pernah menjadi karyawan biasa. Kesimpulan yang tepat?','options'=>[['A','Rian adalah supervisor',0],['B','Rian akan menjadi supervisor',0],['C','Tidak dapat disimpulkan',0],['D','Rian sedang magang',0],['E','Rian bukan supervisor',1]]],
            ['number'=>51,'text'=>'Tidak ada karyawan yang terlambat mendapat penghargaan bulanan. Sari mendapat penghargaan bulanan. Kesimpulan yang tepat?','options'=>[['A','Sari tidak terlambat',1],['B','Sari mendapat penghargaan karena terlambat',0],['C','Tidak dapat disimpulkan',0],['D','Penghargaan diberikan acak',0],['E','Sari terlambat',0]]],
            ['number'=>52,'text'=>'Semua pelamar yang lulus tes IQ dan tes DISC diterima kerja. Wati lulus tes IQ tetapi belum mengikuti tes DISC. Kesimpulan yang tepat?','options'=>[['A','Wati tidak perlu tes DISC',0],['B','Belum bisa dipastikan Wati diterima atau tidak',1],['C','Wati pasti diterima kerja',0],['D','Wati otomatis lulus tes DISC',0],['E','Wati pasti ditolak',0]]],
            ['number'=>53,'text'=>'A, C, E, G, ...?','options'=>[['A','H',0],['B','L',0],['C','I',1],['D','J',0],['E','K',0]]],
            ['number'=>54,'text'=>'Z, X, V, T, ...?','options'=>[['A','S',0],['B','O',0],['C','P',0],['D','R',1],['E','Q',0]]],
            ['number'=>55,'text'=>'B, D, H, N, ...?','options'=>[['A','W',0],['B','U',0],['C','X',0],['D','T',0],['E','V',1]]],
            ['number'=>56,'text'=>'C, F, I, L, ...?','options'=>[['A','O',1],['B','P',0],['C','N',0],['D','Q',0],['E','M',0]]],
            ['number'=>57,'text'=>'Manakah kata yang tidak sekelompok dengan yang lain?','options'=>[['A','Apel',0],['B','Wortel',1],['C','Mangga',0],['D','Pisang',0],['E','Jeruk',0]]],
            ['number'=>58,'text'=>'Manakah kata yang tidak sekelompok dengan yang lain?','options'=>[['A','Anjing',0],['B','Kucing',0],['C','Ular',1],['D','Serigala',0],['E','Harimau',0]]],
            ['number'=>59,'text'=>'Manakah kata yang tidak sekelompok dengan yang lain?','options'=>[['A','Surabaya',0],['B','Bandung',0],['C','Medan',0],['D','Sumatera',1],['E','Jakarta',0]]],
            ['number'=>60,'text'=>'Manakah kata yang tidak sekelompok dengan yang lain?','options'=>[['A','Trapesium',0],['B','Lingkaran',0],['C','Segitiga',0],['D','Persegi',0],['E','Kubus',1]]],
        ],
        'disc' => [
            ['number'=>1,'options'=>[['A','Teliti dalam memeriksa detail','C'],['B','Mudah akrab dengan orang baru','I'],['C','Tegas dalam mengambil keputusan','D'],['D','Sabar menghadapi tekanan','S']]],
            ['number'=>2,'options'=>[['A','Antusias dan penuh semangat','I'],['B','Setia pada tim dan atasan','S'],['C','Sistematis dalam bekerja','C'],['D','Kompetitif, ingin selalu unggul','D']]],
            ['number'=>3,'options'=>[['A','Persuasif, pandai meyakinkan orang','I'],['B','Berani mengambil risiko','D'],['C','Akurat dalam menghitung angka','C'],['D','Tenang dalam situasi mendesak','S']]],
            ['number'=>4,'options'=>[['A','Berhati-hati sebelum mengambil keputusan','C'],['B','Konsisten dalam bekerja','S'],['C','Menyukai tantangan baru','D'],['D','Optimis memandang masa depan','I']]],
            ['number'=>5,'options'=>[['A','Senang membantu rekan kerja','S'],['B','Suka mengarahkan orang lain','D'],['C','Ekspresif dalam menyampaikan perasaan','I'],['D','Taat pada prosedur dan aturan','C']]],
            ['number'=>6,'options'=>[['A','Pendengar yang baik bagi orang lain','S'],['B','Analitis dalam memecahkan masalah','C'],['C','Cepat dan langsung ke inti masalah','D'],['D','Hangat dan ramah pada semua orang','I']]],
            ['number'=>7,'options'=>[['A','Energik dan penuh gairah','I'],['B','Terorganisir dalam menyusun rencana','C'],['C','Dapat diandalkan dalam tugas rutin','S'],['D','Gigih mengejar target','D']]],
            ['number'=>8,'options'=>[['A','Memperhatikan detail kecil','C'],['B','Berorientasi pada hasil','D'],['C','Kooperatif dalam kerja tim','S'],['D','Humoris, suka mencairkan suasana','I']]],
            ['number'=>9,'options'=>[['A','Terbuka menceritakan diri sendiri','I'],['B','Rendah hati, tidak suka menonjol','S'],['C','Blak-blakan menyampaikan pendapat','D'],['D','Perfeksionis pada hasil kerja','C']]],
            ['number'=>10,'options'=>[['A','Senang menjadi pusat perhatian','I'],['B','Disiplin mengikuti jadwal','C'],['C','Menghargai stabilitas dan rutinitas','S'],['D','Ambisius meraih posisi lebih tinggi','D']]],
            ['number'=>11,'options'=>[['A','Objektif dalam menilai sesuatu','C'],['B','Percaya diri tinggi','D'],['C','Pandai membangun relasi baru','I'],['D','Loyal terhadap perusahaan','S']]],
            ['number'=>12,'options'=>[['A','Mengutamakan data dan fakta','C'],['B','Ceria dalam keseharian','I'],['C','Penuh perhatian pada sesama','S'],['D','Pantang menyerah pada hambatan','D']]],
            ['number'=>13,'options'=>[['A','Berpikir logis sebelum bertindak','C'],['B','Tidak menyukai konflik terbuka','S'],['C','Suka memimpin jalannya diskusi','D'],['D','Spontan dalam bertindak','I']]],
            ['number'=>14,'options'=>[['A','Komunikatif, suka mengobrol','I'],['B','Cermat memeriksa ulang pekerjaan','C'],['C','Mandiri, tidak bergantung pada orang lain','D'],['D','Mendukung keputusan tim','S']]],
            ['number'=>15,'options'=>[['A','Menginspirasi orang di sekitarnya','I'],['B','Metodis dalam menyelesaikan tugas','C'],['C','Telaten menyelesaikan pekerjaan','S'],['D','Tidak sabar menunggu proses lama','D']]],
            ['number'=>16,'options'=>[['A','Menuntut standar kerja yang tinggi','D'],['B','Mudah bergaul di lingkungan baru','I'],['C','Tekun menjalani tugas sehari-hari','S'],['D','Selalu mengikuti prosedur yang berlaku','C']]],
            ['number'=>17,'options'=>[['A','Berkemauan keras','D'],['B','Kritis terhadap informasi baru','C'],['C','Menyenangkan diajak bekerja sama','I'],['D','Ramah tanpa ingin menonjolkan diri','S']]],
            ['number'=>18,'options'=>[['A','Tepat waktu dalam segala hal','C'],['B','Suka mengambil keputusan cepat','D'],['C','Bersemangat memulai hal baru','I'],['D','Tenang menghadapi tekanan kerja','S']]],
            ['number'=>19,'options'=>[['A','Rapi dalam menyusun dokumen','C'],['B','Vokal menyuarakan pendapat','D'],['C','Mudah menyesuaikan diri dengan rutinitas','S'],['D','Suka berbagi cerita dan pengalaman','I']]],
            ['number'=>20,'options'=>[['A','Berhati-hati dengan risiko yang belum jelas','C'],['B','Penuh energi positif','I'],['C','Mengutamakan keharmonisan tim','S'],['D','Berorientasi pada kemenangan','D']]],
            ['number'=>21,'options'=>[['A','Tegas menegakkan aturan','D'],['B','Sabar mendengarkan keluhan orang lain','S'],['C','Ramah pada rekan kerja baru','I'],['D','Menyukai pekerjaan yang terstruktur','C']]],
            ['number'=>22,'options'=>[['A','Pandai mencairkan suasana tegang','I'],['B','Berani berkonfrontasi bila perlu','D'],['C','Detail dalam menyusun laporan','C'],['D','Setia pada komitmen yang dibuat','S']]],
            ['number'=>23,'options'=>[['A','Objektif menilai kinerja tim','C'],['B','Antusias menyambut ide baru','I'],['C','Tenang saat menghadapi kritik','S'],['D','Fokus mengejar target pribadi','D']]],
            ['number'=>24,'options'=>[['A','Suka menjaga suasana damai di tim','S'],['B','Cenderung mendominasi percakapan','D'],['C','Teliti membaca kontrak/perjanjian','C'],['D','Mudah beradaptasi dengan orang lain','I']]],
        ],
        'norm' => [
            [0,9,70,'Di bawah rata-rata'],
            [10,19,85,'Rata-rata bawah'],
            [20,29,95,'Rata-rata'],
            [30,39,105,'Rata-rata atas'],
            [40,45,115,'Di atas rata-rata'],
            [46,52,125,'Superior'],
            [53,60,135,'Sangat superior (kecerdasan tinggi)'],
        ],
    ];
}

function sigap_recruitment_seed_if_empty(): void
{
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) FROM test_question_banks')->fetchColumn();
    if ($count > 0) return;
    $seed = sigap_recruitment_seed_data();
    $pdo->beginTransaction();
    try {
        $bankStmt = $pdo->prepare('INSERT INTO test_question_banks (test_type,title,version,duration_minutes,total_questions,pass_threshold,is_active) VALUES (?,?,?,?,?,?,1)');
        $bankStmt->execute(['iq','Bank Soal Aptitude Test MPA v1','1.0',30,60,110]);
        $iqBankId = (int)$pdo->lastInsertId();
        $bankStmt->execute(['disc','Bank Soal Attitude Test (DISC) MPA v1','1.0',15,24,null]);
        $discBankId = (int)$pdo->lastInsertId();

        $qStmt = $pdo->prepare('INSERT INTO test_questions (bank_id,question_number,question_text) VALUES (?,?,?)');
        $oStmt = $pdo->prepare('INSERT INTO test_question_options (question_id,option_label,option_text,is_correct) VALUES (?,?,?,?)');
        foreach ($seed['iq'] as $item) {
            $qStmt->execute([$iqBankId, $item['number'], $item['text']]);
            $qid = (int)$pdo->lastInsertId();
            foreach ($item['options'] as $opt) {
                $oStmt->execute([$qid, $opt[0], $opt[1], $opt[2]]);
            }
        }
        $dqStmt = $pdo->prepare('INSERT INTO test_questions (bank_id,question_number,question_text) VALUES (?,?,NULL)');
        $doStmt = $pdo->prepare('INSERT INTO test_question_options (question_id,option_label,option_text,disc_trait) VALUES (?,?,?,?)');
        foreach ($seed['disc'] as $block) {
            $dqStmt->execute([$discBankId, $block['number']]);
            $qid = (int)$pdo->lastInsertId();
            foreach ($block['options'] as $opt) {
                $doStmt->execute([$qid, $opt[0], $opt[1], $opt[2]]);
            }
        }
        $normStmt = $pdo->prepare('INSERT INTO test_iq_norm_table (bank_id,raw_score_min,raw_score_max,scaled_score,category_label) VALUES (?,?,?,?,?)');
        foreach ($seed['norm'] as $row) {
            $normStmt->execute([$iqBankId, $row[0], $row[1], $row[2], $row[3]]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function sigap_recruitment_bank(int $bankId): ?array
{
    $stmt = db()->prepare('SELECT * FROM test_question_banks WHERE id=?');
    $stmt->execute([$bankId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sigap_recruitment_bank_questions(int $bankId): array
{
    $qStmt = db()->prepare('SELECT id,question_number,question_text,question_image_path FROM test_questions WHERE bank_id=? ORDER BY question_number');
    $qStmt->execute([$bankId]);
    $rows = $qStmt->fetchAll();
    if (!$rows) return [];
    $ids = array_map('intval', array_column($rows, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $optStmt = db()->prepare("SELECT question_id,option_label,option_text FROM test_question_options WHERE question_id IN ($placeholders) ORDER BY option_label");
    $optStmt->execute($ids);
    $byQ = [];
    foreach ($optStmt->fetchAll() as $o) $byQ[(int)$o['question_id']][] = ['label' => $o['option_label'], 'text' => $o['option_text']];
    foreach ($rows as &$q) {
        $q['id'] = (int)$q['id'];
        $q['question_number'] = (int)$q['question_number'];
        $q['options'] = $byQ[$q['id']] ?? [];
    }
    unset($q);
    return $rows;
}

function sigap_recruitment_log_event(int $sessionId, string $type): void
{
    $stmt = db()->prepare('INSERT INTO test_session_events (session_id,event_type,ip_address,user_agent) VALUES (?,?,?,?)');
    $stmt->execute([$sessionId, $type, client_ip(), user_agent()]);
}

function sigap_recruitment_finalize_iq(array $session): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM test_iq_answers WHERE session_id=? AND is_correct=1');
    $stmt->execute([$session['id']]);
    $raw = (int)$stmt->fetchColumn();
    $bank = sigap_recruitment_bank((int)$session['iq_bank_id']);
    $scaled = null; $category = null; $recommendation = null;
    if ($bank) {
        $normStmt = $pdo->prepare('SELECT scaled_score,category_label FROM test_iq_norm_table WHERE bank_id=? AND raw_score_min<=? AND raw_score_max>=? LIMIT 1');
        $normStmt->execute([$bank['id'], $raw, $raw]);
        $norm = $normStmt->fetch();
        if ($norm) {
            $scaled = (int)$norm['scaled_score'];
            $category = $norm['category_label'];
            $threshold = $bank['pass_threshold'] !== null ? (int)$bank['pass_threshold'] : 110;
            $recommendation = $scaled > $threshold ? 'direkomendasikan' : 'tidak_direkomendasikan';
        }
    }
    $update = $pdo->prepare('UPDATE test_sessions SET status=?, iq_finished_at=NOW(6), iq_raw_score=?, iq_scaled_score=?, iq_category=?, iq_recommendation=? WHERE id=?');
    $update->execute(['iq_completed', $raw, $scaled, $category, $recommendation, $session['id']]);
    sigap_recruitment_log_event((int)$session['id'], 'iq_submitted');
    return ['iq_raw_score' => $raw, 'iq_scaled_score' => $scaled, 'iq_category' => $category, 'iq_recommendation' => $recommendation];
}

function sigap_recruitment_finalize_disc(array $session): array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT o.disc_trait AS trait, SUM(a.most_option=o.option_label) AS most_hit, SUM(a.least_option=o.option_label) AS least_hit
        FROM test_disc_answers a
        JOIN test_question_options o ON o.question_id=a.question_id
        WHERE a.session_id=? AND o.disc_trait IS NOT NULL
        GROUP BY o.disc_trait");
    $stmt->execute([$session['id']]);
    $most = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
    $least = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $trait = (string)$row['trait'];
        if (!isset($most[$trait])) continue;
        $most[$trait] = (int)$row['most_hit'];
        $least[$trait] = (int)$row['least_hit'];
    }
    $result = ['most' => $most, 'least' => $least];
    $update = $pdo->prepare('UPDATE test_sessions SET status=?, disc_finished_at=NOW(6), disc_result_json=? WHERE id=?');
    $update->execute(['completed', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $session['id']]);
    sigap_recruitment_log_event((int)$session['id'], 'disc_submitted');
    return $result;
}

function sigap_recruitment_touch(array $session): array
{
    $pdo = db();
    if ($session['status'] === 'not_started' && strtotime((string)$session['expires_at']) < time()) {
        $pdo->prepare("UPDATE test_sessions SET status='expired' WHERE id=?")->execute([$session['id']]);
        $session['status'] = 'expired';
        return $session;
    }
    if ($session['status'] === 'iq_in_progress' && $session['iq_started_at']) {
        $bank = sigap_recruitment_bank((int)$session['iq_bank_id']);
        $duration = $bank ? (int)$bank['duration_minutes'] : 30;
        if (time() - strtotime((string)$session['iq_started_at']) >= $duration * 60) {
            $result = sigap_recruitment_finalize_iq($session);
            $session = array_merge($session, $result, ['status' => 'iq_completed']);
        }
    } elseif ($session['status'] === 'disc_in_progress' && $session['disc_started_at']) {
        $bank = sigap_recruitment_bank((int)$session['disc_bank_id']);
        $duration = $bank ? (int)$bank['duration_minutes'] : 15;
        if (time() - strtotime((string)$session['disc_started_at']) >= $duration * 60) {
            sigap_recruitment_finalize_disc($session);
            $session['status'] = 'completed';
        }
    }
    return $session;
}

function sigap_recruitment_session_by_token(string $token): array
{
    $stmt = db()->prepare('SELECT s.*, c.full_name AS candidate_name, c.applied_position FROM test_sessions s JOIN test_candidates c ON c.id=s.candidate_id WHERE s.access_token=? LIMIT 1');
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if (!$session) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Link tes tidak ditemukan.'], 404);
    return sigap_recruitment_touch($session);
}




// ================= Employee Master Data (Database Karyawan Lengkap) =================
// Modul satelit yang menggantung ke `employees.id`. Tabel inti `employees` TIDAK
// diubah strukturnya supaya absensi/payroll/login yang sudah berjalan tidak terdampak.
// Lihat SIGAP_HANDOVER/03_DOCUMENTATION/features/EMPLOYEE_MASTER_DATA_DESIGN.md

function sigap_pii_key(): string
{
    global $config;
    $raw = (string)($config['security']['pii_encryption_key'] ?? '');
    $key = $raw === '' ? false : base64_decode($raw, true);
    if ($key === false || strlen($key) !== 32) {
        error_log('SIGAP: pii_encryption_key belum diset atau tidak valid di app/config.local.php (harus base64 dari 32 byte acak).');
        json_response(['ok' => false, 'error' => 'encryption_not_configured', 'message' => 'Enkripsi data pribadi belum dikonfigurasi. Hubungi administrator.'], 503);
    }
    return $key;
}

function sigap_pii_encrypt(?string $plain): ?string
{
    $plain = trim((string)$plain);
    if ($plain === '') return null;
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', sigap_pii_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Enkripsi data pribadi gagal.');
    return base64_encode($iv . $tag . $cipher);
}

function sigap_pii_decrypt(?string $encoded): ?string
{
    if ($encoded === null || $encoded === '') return null;
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) return null;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', sigap_pii_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function sigap_mask_middle(string $value, int $keepStart, int $keepEnd): string
{
    $len = strlen($value);
    if ($value === '') return '';
    if ($len <= $keepStart + $keepEnd) return str_repeat('*', $len);
    return substr($value, 0, $keepStart) . str_repeat('*', $len - $keepStart - $keepEnd) . substr($value, -$keepEnd);
}

function sigap_employee_documents_directory(): string
{
    global $config;
    $path = (string)($config['storage']['employee_documents_path'] ?? '');
    if ($path === '') {
        // Fallback: di luar APP_ROOT (bukan di dalam public_html/src yang web-accessible).
        $path = dirname(APP_ROOT) . '/private_storage/employee_documents';
    }
    if (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Folder penyimpanan dokumen karyawan tidak dapat dibuat: ' . $path);
    }
    return $path;
}

function sigap_log_pii_access(int $employeeId, string $fieldName, int $viewedByUserId): void
{
    sigap_ensure_employee_master_tables();
    db()->prepare('INSERT INTO employee_pii_access_log (employee_id, field_name, viewed_by_user_id, ip_address) VALUES (?,?,?,?)')
        ->execute([$employeeId, $fieldName, $viewedByUserId, client_ip()]);
}

function sigap_ensure_employee_master_tables(): void
{
    static $ready = false;
    if ($ready) return;
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_profiles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        nickname VARCHAR(80) NULL,
        nik_ktp_encrypted VARCHAR(255) NULL,
        nik_ktp_masked VARCHAR(20) NULL,
        nik_ktp_hash CHAR(64) NULL,
        kk_number_encrypted VARCHAR(255) NULL,
        kk_number_masked VARCHAR(20) NULL,
        birth_place VARCHAR(100) NULL,
        birth_date DATE NULL,
        gender ENUM('L','P') NULL,
        religion ENUM('islam','kristen','katolik','hindu','buddha','khonghucu','lainnya') NULL,
        blood_type ENUM('A','B','AB','O','unknown') NOT NULL DEFAULT 'unknown',
        nationality VARCHAR(60) NOT NULL DEFAULT 'Indonesia',
        marital_status ENUM('belum_kawin','kawin','cerai_hidup','cerai_mati') NULL,
        family_card_role ENUM('kepala_keluarga','istri','anak','famili_lain') NULL,
        photo_path VARCHAR(255) NULL,
        ktp_address_json LONGTEXT NULL,
        domicile_same_as_ktp TINYINT(1) NOT NULL DEFAULT 1,
        domicile_address_json LONGTEXT NULL,
        residence_status ENUM('milik_sendiri','sewa','kos','ikut_keluarga') NULL,
        phone_primary VARCHAR(20) NULL,
        phone_secondary VARCHAR(20) NULL,
        email_personal VARCHAR(160) NULL,
        email_office VARCHAR(160) NULL,
        uniform_size VARCHAR(10) NULL,
        shoe_size VARCHAR(10) NULL,
        helmet_size VARCHAR(10) NULL,
        medical_notes_encrypted TEXT NULL,
        npwp_encrypted VARCHAR(255) NULL,
        npwp_masked VARCHAR(30) NULL,
        uses_nik_as_npwp TINYINT(1) NOT NULL DEFAULT 0,
        ptkp_status VARCHAR(10) NULL,
        ptkp_override TINYINT(1) NOT NULL DEFAULT 0,
        ptkp_override_note VARCHAR(255) NULL,
        bpjs_health_no VARCHAR(30) NULL,
        bpjs_health_faskes VARCHAR(160) NULL,
        bpjs_tk_no VARCHAR(30) NULL,
        bank_name VARCHAR(80) NULL,
        bank_account_encrypted VARCHAR(255) NULL,
        bank_account_masked VARCHAR(30) NULL,
        bank_account_holder_name VARCHAR(160) NULL,
        bank_account_holder_mismatch_note VARCHAR(255) NULL,
        profile_completion_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        profile_status ENUM('draft','menunggu_verifikasi','disetujui','ditolak') NOT NULL DEFAULT 'draft',
        profile_submitted_at DATETIME(6) NULL,
        profile_reviewed_by_user_id BIGINT UNSIGNED NULL,
        profile_reviewed_at DATETIME(6) NULL,
        profile_review_note VARCHAR(500) NULL,
        consent_accepted_at DATETIME(6) NULL,
        consent_version VARCHAR(20) NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        UNIQUE KEY uniq_employee_profile (employee_id),
        UNIQUE KEY uniq_employee_profile_nik_hash (nik_ktp_hash),
        CONSTRAINT fk_employee_profile_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migrasi untuk instalasi yang sudah berjalan sebelum status verifikasi disederhanakan
    // dari per-bagian (employee_profile_sections) menjadi satu status per karyawan.
    foreach ([
        'profile_status' => "ENUM('draft','menunggu_verifikasi','disetujui','ditolak') NOT NULL DEFAULT 'draft'",
        'profile_submitted_at' => "DATETIME(6) NULL",
        'profile_reviewed_by_user_id' => "BIGINT UNSIGNED NULL",
        'profile_reviewed_at' => "DATETIME(6) NULL",
        'profile_review_note' => "VARCHAR(500) NULL",
        'profile_legacy_sections_migrated' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $columnName => $columnDef) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_profiles' AND COLUMN_NAME=?");
            $check->execute([$columnName]);
            if (!(int)$check->fetchColumn()) $pdo->exec("ALTER TABLE employee_profiles ADD COLUMN {$columnName} {$columnDef}");
        } catch (Throwable $e) { /* abaikan jika sudah ada / tidak diizinkan ALTER */ }
    }
    // Sinkronkan satu kali dari status per-bagian lama (jika ada) ke status global baru,
    // supaya data yang sudah terlanjur dikirim karyawan untuk verifikasi tidak hilang.
    try {
        $pdo->exec("UPDATE employee_profiles p
            JOIN (
                SELECT employee_id,
                    MAX(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) AS has_pending,
                    MAX(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) AS has_rejected,
                    MIN(CASE WHEN section_code<>'kepegawaian' AND status='disetujui' THEN 1 ELSE 0 END) AS all_approved,
                    MAX(submitted_at) AS last_submitted_at
                FROM employee_profile_sections GROUP BY employee_id
            ) s ON s.employee_id = p.employee_id
            SET
                p.profile_status = CASE
                    WHEN s.has_pending=1 THEN 'menunggu_verifikasi'
                    WHEN s.all_approved=1 THEN 'disetujui'
                    WHEN s.has_rejected=1 THEN 'ditolak'
                    ELSE p.profile_status
                END,
                p.profile_submitted_at = CASE WHEN s.has_pending=1 THEN s.last_submitted_at ELSE p.profile_submitted_at END,
                p.profile_legacy_sections_migrated = 1
            WHERE p.profile_legacy_sections_migrated=0");
    } catch (Throwable $e) { /* tabel lama mungkin belum ada di instalasi baru */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_family_members (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        member_type ENUM('spouse','child','father','mother') NOT NULL,
        full_name VARCHAR(160) NOT NULL,
        nik_encrypted VARCHAR(255) NULL,
        nik_masked VARCHAR(20) NULL,
        birth_place VARCHAR(100) NULL,
        birth_date DATE NULL,
        gender ENUM('L','P') NULL,
        marriage_date DATE NULL,
        occupation VARCHAR(120) NULL,
        phone VARCHAR(20) NULL,
        education_level VARCHAR(40) NULL,
        is_dependent TINYINT(1) NOT NULL DEFAULT 0,
        bpjs_status VARCHAR(40) NULL,
        is_alive TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_family_employee (employee_id, member_type),
        CONSTRAINT fk_family_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        full_name VARCHAR(160) NOT NULL,
        relationship VARCHAR(60) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address VARCHAR(255) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_emergency_employee (employee_id),
        CONSTRAINT fk_emergency_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_education_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        education_level ENUM('SD','SMP','SMA_SMK','D1','D2','D3','D4','S1','S2','S3') NOT NULL,
        institution_name VARCHAR(160) NOT NULL,
        major VARCHAR(120) NULL,
        graduation_year SMALLINT UNSIGNED NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_education_employee (employee_id),
        CONSTRAINT fk_education_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_certifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        cert_type ENUM('skk_ska','k3_umum','ahli_k3','operator_alat_berat','sim_a','sim_b1','sim_b2','sim_c','sim_d','lainnya') NOT NULL,
        cert_name VARCHAR(160) NOT NULL,
        cert_number VARCHAR(80) NULL,
        issuer VARCHAR(160) NULL,
        issued_date DATE NULL,
        expiry_date DATE NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_cert_employee (employee_id),
        INDEX idx_cert_expiry (expiry_date),
        CONSTRAINT fk_cert_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_documents (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        document_type ENUM('ktp','kk','npwp','ijazah','buku_nikah','akta_lahir_anak','kartu_bpjs','buku_tabungan','sertifikat_kompetensi','sim','lainnya') NOT NULL,
        related_family_member_id BIGINT UNSIGNED NULL,
        file_path VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('menunggu_verifikasi','valid','ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
        rejection_note VARCHAR(255) NULL,
        verified_by_user_id BIGINT UNSIGNED NULL,
        verified_at DATETIME(6) NULL,
        uploaded_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_documents_employee (employee_id, document_type),
        CONSTRAINT fk_document_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_document_family FOREIGN KEY (related_family_member_id) REFERENCES employee_family_members(id) ON DELETE SET NULL,
        CONSTRAINT fk_document_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_profile_sections (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        section_code ENUM('identitas','alamat','kontak','keluarga','pendidikan','kepegawaian','administrasi','pendukung','dokumen') NOT NULL,
        status ENUM('draft','menunggu_verifikasi','disetujui','ditolak') NOT NULL DEFAULT 'draft',
        submitted_at DATETIME(6) NULL,
        reviewed_by_user_id BIGINT UNSIGNED NULL,
        reviewed_at DATETIME(6) NULL,
        review_note VARCHAR(255) NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        UNIQUE KEY uniq_profile_section (employee_id, section_code),
        CONSTRAINT fk_section_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_section_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_profile_change_requests (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        section_code ENUM('identitas','alamat','kontak','keluarga','pendidikan','kepegawaian','administrasi','pendukung','dokumen') NOT NULL,
        trigger_event ENUM('menikah','punya_anak','pindah_alamat','ganti_hp','ganti_rekening','lainnya') NOT NULL DEFAULT 'lainnya',
        before_json LONGTEXT NULL,
        after_json LONGTEXT NOT NULL,
        status ENUM('menunggu_verifikasi','disetujui','ditolak') NOT NULL DEFAULT 'menunggu_verifikasi',
        requested_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        reviewed_by_user_id BIGINT UNSIGNED NULL,
        reviewed_at DATETIME(6) NULL,
        review_note VARCHAR(255) NULL,
        INDEX idx_change_employee (employee_id, status),
        CONSTRAINT fk_change_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_change_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_profile_consents (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        consent_version VARCHAR(20) NOT NULL,
        accepted_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        INDEX idx_consent_employee (employee_id),
        CONSTRAINT fk_consent_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_pii_access_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        field_name VARCHAR(60) NOT NULL,
        viewed_by_user_id BIGINT UNSIGNED NOT NULL,
        viewed_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        ip_address VARCHAR(45) NULL,
        INDEX idx_pii_log_employee (employee_id),
        INDEX idx_pii_log_viewer (viewed_by_user_id),
        CONSTRAINT fk_pii_log_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_pii_log_viewer FOREIGN KEY (viewed_by_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_completion_reminders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        sent_by_user_id BIGINT UNSIGNED NOT NULL,
        channel ENUM('in_app','whatsapp') NOT NULL,
        sent_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_reminder_employee (employee_id),
        CONSTRAINT fk_reminder_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_reminder_sender FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_import_batches (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        imported_by_user_id BIGINT UNSIGNED NULL,
        filename VARCHAR(255) NOT NULL,
        total_rows INT UNSIGNED NOT NULL DEFAULT 0,
        success_rows INT UNSIGNED NOT NULL DEFAULT 0,
        failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        CONSTRAINT fk_import_batch_user FOREIGN KEY (imported_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_import_rows (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id BIGINT UNSIGNED NOT NULL,
        row_no INT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NULL,
        raw_json LONGTEXT NOT NULL,
        status ENUM('success','failed') NOT NULL,
        error_message VARCHAR(500) NULL,
        INDEX idx_import_row_batch (batch_id),
        CONSTRAINT fk_import_row_batch FOREIGN KEY (batch_id) REFERENCES employee_import_batches(id) ON DELETE CASCADE,
        CONSTRAINT fk_import_row_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_position_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        position_name VARCHAR(120) NOT NULL,
        department_name VARCHAR(120) NOT NULL DEFAULT '',
        placement_location VARCHAR(160) NULL,
        mutation_type ENUM('pengangkatan_awal','promosi','mutasi','rotasi','demosi') NOT NULL DEFAULT 'pengangkatan_awal',
        start_date DATE NOT NULL,
        end_date DATE NULL,
        decided_by_user_id BIGINT UNSIGNED NULL,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_position_history_employee (employee_id, start_date),
        CONSTRAINT fk_position_history_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_position_history_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_training_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        training_name VARCHAR(200) NOT NULL,
        provider VARCHAR(160) NULL,
        category ENUM('internal','eksternal') NOT NULL DEFAULT 'internal',
        start_date DATE NULL,
        end_date DATE NULL,
        duration_hours DECIMAL(6,2) NULL,
        location VARCHAR(160) NULL,
        result_note VARCHAR(255) NULL,
        status ENUM('selesai','berjalan','dibatalkan') NOT NULL DEFAULT 'selesai',
        resulting_certification_id BIGINT UNSIGNED NULL,
        document_id BIGINT UNSIGNED NULL,
        created_by_user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX idx_training_employee (employee_id),
        CONSTRAINT fk_training_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_training_certification FOREIGN KEY (resulting_certification_id) REFERENCES employee_certifications(id) ON DELETE SET NULL,
        CONSTRAINT fk_training_document FOREIGN KEY (document_id) REFERENCES employee_documents(id) ON DELETE SET NULL,
        CONSTRAINT fk_training_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_employment_details (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        nip_internal VARCHAR(40) NULL,
        contract_type ENUM('pkwt','pkwtt','harian','magang') NOT NULL DEFAULT 'pkwtt',
        contract_end_date DATE NULL,
        resign_date DATE NULL,
        resign_reason VARCHAR(255) NULL,
        updated_by_user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        UNIQUE KEY uniq_employment_details (employee_id),
        UNIQUE KEY uniq_employment_nip (nip_internal),
        CONSTRAINT fk_employment_details_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_employment_details_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function sigap_employee_position_history_open(PDO $pdo, int $employeeId, string $position, string $department, string $startDate, string $mutationType, ?int $decidedByUserId): void
{
    $pdo->prepare('INSERT INTO employee_position_history (employee_id,position_name,department_name,mutation_type,start_date,decided_by_user_id) VALUES (?,?,?,?,?,?)')
        ->execute([$employeeId, $position, $department, $mutationType, $startDate, $decidedByUserId]);
}

function sigap_employee_position_history_sync(PDO $pdo, int $employeeId, string $newPosition, string $newDepartment, ?int $decidedByUserId): void
{
    $current = $pdo->prepare('SELECT id, position_name, department_name FROM employee_position_history WHERE employee_id=? AND end_date IS NULL ORDER BY start_date DESC, id DESC LIMIT 1');
    $current->execute([$employeeId]);
    $row = $current->fetch();
    if ($row && $row['position_name'] === $newPosition && $row['department_name'] === $newDepartment) {
        return;
    }
    $today = (new DateTimeImmutable('now'))->format('Y-m-d');
    if ($row) {
        $pdo->prepare('UPDATE employee_position_history SET end_date=? WHERE id=?')->execute([$today, $row['id']]);
    }
    sigap_employee_position_history_open($pdo, $employeeId, $newPosition, $newDepartment, $today, $row ? 'mutasi' : 'pengangkatan_awal', $decidedByUserId);
}

// ================= Kuota Cuti (Leave Balance) =================
// `employees.annual_leave_quota` sudah ada (statis, diisi HR sekali) tapi SIGAP belum
// pernah melacak sisa/pemakaiannya. Tabel di bawah menambah pelacakan per tahun tanpa
// mengubah kolom lama tersebut (dipakai sebagai kuota dasar tiap tahun).

function sigap_ensure_leave_balance_tables(): void
{
    static $ready = false;
    if ($ready) return;
    db()->exec("CREATE TABLE IF NOT EXISTS employee_leave_balances (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        quota_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        used_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        UNIQUE KEY uniq_leave_balance (employee_id, year),
        CONSTRAINT fk_leave_balance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS employee_leave_balance_adjustments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id BIGINT UNSIGNED NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        adjustment_type ENUM('carry_over','bonus','koreksi','potongan') NOT NULL,
        amount_days DECIMAL(5,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_by_user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX idx_leave_adjustment_employee (employee_id, year),
        CONSTRAINT fk_leave_adjustment_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_leave_adjustment_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function sigap_leave_balance_get_or_create(PDO $pdo, int $employeeId, int $year): array
{
    $stmt = $pdo->prepare('SELECT * FROM employee_leave_balances WHERE employee_id=? AND year=? LIMIT 1');
    $stmt->execute([$employeeId, $year]);
    $row = $stmt->fetch();
    if ($row) return $row;
    $empStmt = $pdo->prepare('SELECT annual_leave_quota FROM employees WHERE id=? LIMIT 1');
    $empStmt->execute([$employeeId]);
    $quota = (float)($empStmt->fetchColumn() ?: 12);
    $pdo->prepare('INSERT INTO employee_leave_balances (employee_id, year, quota_days) VALUES (?,?,?) ON DUPLICATE KEY UPDATE employee_id=employee_id')
        ->execute([$employeeId, $year, $quota]);
    $stmt->execute([$employeeId, $year]);
    return $stmt->fetch();
}

function sigap_leave_balance_summary(PDO $pdo, int $employeeId, int $year): array
{
    $balance = sigap_leave_balance_get_or_create($pdo, $employeeId, $year);
    $adjStmt = $pdo->prepare('SELECT COALESCE(SUM(amount_days),0) FROM employee_leave_balance_adjustments WHERE employee_id=? AND year=?');
    $adjStmt->execute([$employeeId, $year]);
    $adjustmentTotal = (float)$adjStmt->fetchColumn();
    $quota = (float)$balance['quota_days'];
    $used = (float)$balance['used_days'];
    return [
        'employee_id' => $employeeId,
        'year' => $year,
        'quota_days' => $quota,
        'adjustment_days' => round($adjustmentTotal, 2),
        'used_days' => $used,
        'remaining_days' => round($quota + $adjustmentTotal - $used, 2),
    ];
}

function sigap_leave_balance_deduct(PDO $pdo, int $employeeId, int $year, float $days): void
{
    sigap_leave_balance_get_or_create($pdo, $employeeId, $year);
    $pdo->prepare('UPDATE employee_leave_balances SET used_days = used_days + ? WHERE employee_id=? AND year=?')
        ->execute([$days, $employeeId, $year]);
}

// ================= Form Self-Service Karyawan (Database Karyawan Lengkap, Tahap 3) =================

function sigap_pii_hash(string $plain): string
{
    global $config;
    $key = (string)($config['security']['pii_encryption_key'] ?? '');
    return hash('sha256', $key . '|' . $plain);
}

function sigap_normalize_phone_id(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    if ($digits === '') return '';
    if (str_starts_with($digits, '0')) $digits = '62' . substr($digits, 1);
    elseif (!str_starts_with($digits, '62')) $digits = '62' . $digits;
    return '+' . $digits;
}

function sigap_profile_section_codes(): array
{
    return ['identitas','alamat','kontak','keluarga','pendidikan','kepegawaian','administrasi','pendukung','dokumen'];
}

function sigap_profile_section_editable_by_self(string $code): bool
{
    return $code !== 'kepegawaian';
}

function sigap_profile_ensure_sections(PDO $pdo, int $employeeId): void
{
    foreach (sigap_profile_section_codes() as $code) {
        $pdo->prepare('INSERT INTO employee_profile_sections (employee_id, section_code) VALUES (?,?) ON DUPLICATE KEY UPDATE employee_id=employee_id')
            ->execute([$employeeId, $code]);
    }
}

function sigap_profile_section_status(PDO $pdo, int $employeeId, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM employee_profile_sections WHERE employee_id=? AND section_code=? LIMIT 1');
    $stmt->execute([$employeeId, $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sigap_profile_status(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare('SELECT profile_status, profile_submitted_at, profile_reviewed_by_user_id, profile_reviewed_at, profile_review_note FROM employee_profiles WHERE employee_id=? LIMIT 1');
    $stmt->execute([$employeeId]);
    return $stmt->fetch() ?: ['profile_status'=>'draft','profile_submitted_at'=>null,'profile_reviewed_by_user_id'=>null,'profile_reviewed_at'=>null,'profile_review_note'=>null];
}

function sigap_profile_require_editable(PDO $pdo, int $employeeId): void
{
    $status = sigap_profile_status($pdo, $employeeId)['profile_status'];
    if ($status === 'disetujui') {
        json_response(['ok'=>false,'error'=>'profile_locked','message'=>'Profil sudah terverifikasi dan terkunci. Gunakan "Ajukan Perubahan Data" untuk mengubahnya.'],409);
    }
    if ($status === 'menunggu_verifikasi') {
        json_response(['ok'=>false,'error'=>'profile_pending','message'=>'Profil sedang menunggu verifikasi HRD, belum bisa diubah.'],409);
    }
}

function sigap_profile_mark_draft(PDO $pdo, int $employeeId): void
{
    sigap_profile_get_or_create($pdo, $employeeId);
    $pdo->prepare("UPDATE employee_profiles SET profile_status='draft', profile_submitted_at=NULL, profile_reviewed_by_user_id=NULL, profile_reviewed_at=NULL, profile_review_note=NULL WHERE employee_id=? AND profile_status<>'disetujui'")
        ->execute([$employeeId]);
}

function sigap_profile_completion_recalculate(PDO $pdo, int $employeeId): int
{
    // Heuristik kelengkapan berdasarkan ADA-TIDAKNYA data terisi per bagian (bukan status
    // verifikasi lagi, karena verifikasi sekarang satu kali untuk seluruh profil).
    $profile = sigap_profile_get_or_create($pdo, $employeeId);
    $filled = 0; $total = 9;
    if ($profile['nik_ktp_encrypted'] || $profile['birth_date'] || $profile['gender']) $filled++;
    if ($profile['ktp_address_json'] && $profile['ktp_address_json'] !== '{}') $filled++;
    if ($profile['phone_primary']) $filled++;
    $familyCount = (int)$pdo->query('SELECT COUNT(*) FROM employee_family_members WHERE employee_id='.(int)$employeeId)->fetchColumn();
    if ($familyCount > 0 || $profile['marital_status']==='belum_kawin') $filled++;
    $eduCount = (int)$pdo->query('SELECT COUNT(*) FROM employee_education_history WHERE employee_id='.(int)$employeeId)->fetchColumn();
    $certCount = (int)$pdo->query('SELECT COUNT(*) FROM employee_certifications WHERE employee_id='.(int)$employeeId)->fetchColumn();
    if ($eduCount > 0 || $certCount > 0) $filled++;
    $filled++; // kepegawaian dikelola HRD, tidak dihitung sebagai kekurangan karyawan
    if ($profile['npwp_encrypted'] || $profile['bank_account_encrypted']) $filled++;
    if ($profile['uniform_size'] || $profile['shoe_size'] || $profile['medical_notes_encrypted']) $filled++;
    $docCount = (int)$pdo->query('SELECT COUNT(*) FROM employee_documents WHERE employee_id='.(int)$employeeId)->fetchColumn();
    if ($docCount > 0) $filled++;
    $percent = (int)round(min($filled, $total) / $total * 100);
    $pdo->prepare('UPDATE employee_profiles SET profile_completion_percent=? WHERE employee_id=?')->execute([$percent, $employeeId]);
    return $percent;
}

function sigap_profile_get_or_create(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare('SELECT * FROM employee_profiles WHERE employee_id=? LIMIT 1');
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    $pdo->prepare('INSERT INTO employee_profiles (employee_id) VALUES (?) ON DUPLICATE KEY UPDATE employee_id=employee_id')->execute([$employeeId]);
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

function sigap_profile_bundle(PDO $pdo, int $employeeId, bool $revealSensitive, ?int $viewerUserId = null): array
{
    $profile = sigap_profile_get_or_create($pdo, $employeeId);
    sigap_profile_ensure_sections($pdo, $employeeId);

    $sensitiveFields = ['nik_ktp' => 'nik_ktp_encrypted', 'kk_number' => 'kk_number_encrypted', 'npwp' => 'npwp_encrypted', 'bank_account' => 'bank_account_encrypted', 'medical_notes' => 'medical_notes_encrypted'];
    $out = [
        'employee_id' => $employeeId,
        'nickname' => $profile['nickname'],
        'nik_ktp' => $revealSensitive ? sigap_pii_decrypt($profile['nik_ktp_encrypted']) : $profile['nik_ktp_masked'],
        'kk_number' => $revealSensitive ? sigap_pii_decrypt($profile['kk_number_encrypted']) : $profile['kk_number_masked'],
        'birth_place' => $profile['birth_place'], 'birth_date' => $profile['birth_date'],
        'gender' => $profile['gender'], 'religion' => $profile['religion'], 'blood_type' => $profile['blood_type'],
        'nationality' => $profile['nationality'], 'marital_status' => $profile['marital_status'], 'family_card_role' => $profile['family_card_role'],
        'photo_path' => $profile['photo_path'] ? true : false,
        'ktp_address' => json_decode((string)($profile['ktp_address_json'] ?: '{}'), true) ?: [],
        'domicile_same_as_ktp' => (bool)$profile['domicile_same_as_ktp'],
        'domicile_address' => json_decode((string)($profile['domicile_address_json'] ?: '{}'), true) ?: [],
        'residence_status' => $profile['residence_status'],
        'phone_primary' => $profile['phone_primary'], 'phone_secondary' => $profile['phone_secondary'],
        'email_personal' => $profile['email_personal'], 'email_office' => $profile['email_office'],
        'uniform_size' => $profile['uniform_size'], 'shoe_size' => $profile['shoe_size'], 'helmet_size' => $profile['helmet_size'],
        'medical_notes' => $revealSensitive ? sigap_pii_decrypt($profile['medical_notes_encrypted']) : ($profile['medical_notes_encrypted'] ? '(tersimpan, terenkripsi)' : null),
        'npwp' => $revealSensitive ? sigap_pii_decrypt($profile['npwp_encrypted']) : $profile['npwp_masked'],
        'uses_nik_as_npwp' => (bool)$profile['uses_nik_as_npwp'],
        'ptkp_status' => $profile['ptkp_status'], 'ptkp_override' => (bool)$profile['ptkp_override'], 'ptkp_override_note' => $profile['ptkp_override_note'],
        'bpjs_health_no' => $profile['bpjs_health_no'], 'bpjs_health_faskes' => $profile['bpjs_health_faskes'], 'bpjs_tk_no' => $profile['bpjs_tk_no'],
        'bank_name' => $profile['bank_name'], 'bank_account' => $revealSensitive ? sigap_pii_decrypt($profile['bank_account_encrypted']) : $profile['bank_account_masked'],
        'bank_account_holder_name' => $profile['bank_account_holder_name'], 'bank_account_holder_mismatch_note' => $profile['bank_account_holder_mismatch_note'],
        'profile_completion_percent' => (int)$profile['profile_completion_percent'],
        'consent_accepted_at' => $profile['consent_accepted_at'], 'consent_version' => $profile['consent_version'],
    ];
    if ($revealSensitive && $viewerUserId) {
        foreach (array_keys($sensitiveFields) as $field) {
            if ($out[$field]) sigap_log_pii_access($employeeId, $field, $viewerUserId);
        }
    }

    $family = $pdo->prepare('SELECT * FROM employee_family_members WHERE employee_id=? ORDER BY member_type, id');
    $family->execute([$employeeId]);
    $out['family_members'] = array_map(function ($r) use ($revealSensitive) {
        $r['nik'] = $revealSensitive ? sigap_pii_decrypt($r['nik_encrypted']) : $r['nik_masked'];
        unset($r['nik_encrypted']);
        return $r;
    }, $family->fetchAll());

    $emergency = $pdo->prepare('SELECT * FROM employee_emergency_contacts WHERE employee_id=? ORDER BY sort_order, id');
    $emergency->execute([$employeeId]);
    $out['emergency_contacts'] = $emergency->fetchAll();

    $education = $pdo->prepare('SELECT * FROM employee_education_history WHERE employee_id=? ORDER BY graduation_year DESC, id');
    $education->execute([$employeeId]);
    $out['education_history'] = $education->fetchAll();

    $certifications = $pdo->prepare('SELECT * FROM employee_certifications WHERE employee_id=? ORDER BY expiry_date IS NULL, expiry_date, id');
    $certifications->execute([$employeeId]);
    $out['certifications'] = $certifications->fetchAll();

    $documents = $pdo->prepare('SELECT id, document_type, related_family_member_id, original_filename, mime_type, file_size_bytes, status, rejection_note, uploaded_at FROM employee_documents WHERE employee_id=? ORDER BY uploaded_at DESC');
    $documents->execute([$employeeId]);
    $out['documents'] = $documents->fetchAll();

    $status = sigap_profile_status($pdo, $employeeId);
    $out['profile_status'] = $status['profile_status'];
    $out['profile_submitted_at'] = $status['profile_submitted_at'];
    $out['profile_reviewed_at'] = $status['profile_reviewed_at'];
    $out['profile_review_note'] = $status['profile_review_note'];

    $positionHistory = $pdo->prepare('SELECT position_name, department_name, mutation_type, start_date, end_date, note FROM employee_position_history WHERE employee_id=? ORDER BY start_date DESC, id DESC');
    $positionHistory->execute([$employeeId]);
    $out['position_history'] = $positionHistory->fetchAll();

    $training = $pdo->prepare('SELECT id, training_name, provider, category, start_date, end_date, duration_hours, location, result_note, status FROM employee_training_history WHERE employee_id=? ORDER BY start_date DESC, id DESC');
    $training->execute([$employeeId]);
    $out['training_history'] = $training->fetchAll();

    $employment = sigap_employment_details_get_or_create($pdo, $employeeId);
    $out['employment_details'] = ['nip_internal'=>$employment['nip_internal'],'contract_type'=>$employment['contract_type'],'contract_end_date'=>$employment['contract_end_date'],'resign_date'=>$employment['resign_date'],'resign_reason'=>$employment['resign_reason']];

    return $out;
}

function sigap_employment_details_get_or_create(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare('SELECT * FROM employee_employment_details WHERE employee_id=? LIMIT 1');
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    $pdo->prepare('INSERT INTO employee_employment_details (employee_id) VALUES (?) ON DUPLICATE KEY UPDATE employee_id=employee_id')->execute([$employeeId]);
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

function sigap_profile_validate_section_fields(string $code, array $data): array
{
    $allowed = [
        'identitas' => ['nickname','nik_ktp','kk_number','birth_place','birth_date','gender','religion','blood_type','nationality','marital_status','family_card_role'],
        'alamat' => ['ktp_address','domicile_same_as_ktp','domicile_address','residence_status'],
        'kontak' => ['phone_primary','phone_secondary','email_personal','email_office'],
        'administrasi' => ['npwp','uses_nik_as_npwp','ptkp_override','ptkp_override_note','bpjs_health_no','bpjs_health_faskes','bpjs_tk_no','bank_name','bank_account','bank_account_holder_name','bank_account_holder_mismatch_note'],
        'pendukung' => ['uniform_size','shoe_size','helmet_size','medical_notes'],
    ];
    if (!isset($allowed[$code])) return [];
    $out = [];
    foreach ($allowed[$code] as $field) {
        if (array_key_exists($field, $data)) $out[$field] = $data[$field];
    }
    return $out;
}

// Hitung sisa digit tanggal lahir di NIK KTP (DDMMYY, +40 pada DD untuk perempuan) lalu
// bandingkan dengan tanggal lahir yang diinput. Hanya peringatan, tidak memblokir simpan
// (sesuai spesifikasi butir 5: "warning, bukan blok").
function sigap_nik_birth_date_warning(string $nik, ?string $birthDate): ?string
{
    if (strlen($nik) !== 16 || !ctype_digit($nik) || !$birthDate) return null;
    $dd = (int)substr($nik, 6, 2);
    $mm = (int)substr($nik, 8, 2);
    $yy = (int)substr($nik, 10, 2);
    if ($dd > 40) $dd -= 40;
    try {
        $input = new DateTimeImmutable($birthDate);
    } catch (Throwable $e) {
        return null;
    }
    if ((int)$input->format('d') !== $dd || (int)$input->format('m') !== $mm || (int)$input->format('y') !== $yy) {
        return 'Tanggal lahir pada NIK sepertinya tidak cocok dengan tanggal lahir yang diinput. Mohon dicek kembali.';
    }
    return null;
}

function sigap_normalize_shift_days(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($raw)) $raw = [];
    $days = [];
    foreach ($raw as $day) {
        $value = (int)$day;
        if ($value >= 1 && $value <= 7) $days[] = $value;
    }
    $days = array_values(array_unique($days));
    sort($days);
    return $days;
}

function sigap_ensure_shift_tables(): void
{
    static $ready = false;
    if ($ready) return;
    db()->exec("\n        CREATE TABLE IF NOT EXISTS work_shifts (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            shift_code VARCHAR(40) NOT NULL,\n            shift_name VARCHAR(120) NOT NULL,\n            work_start TIME NOT NULL,\n            work_end TIME NOT NULL,\n            late_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,\n            work_days_json LONGTEXT NOT NULL,\n            is_active TINYINT(1) NOT NULL DEFAULT 1,\n            created_by_user_id BIGINT UNSIGNED NULL,\n            updated_by_user_id BIGINT UNSIGNED NULL,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            UNIQUE KEY uniq_work_shift_code (shift_code),\n            INDEX idx_work_shifts_active (is_active),\n            CONSTRAINT fk_work_shift_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,\n            CONSTRAINT fk_work_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    // v1.10.3: pastikan kolom jadwal harian tersedia pada instalasi lama.
    // Tidak memakai ADD COLUMN IF NOT EXISTS karena sebagian server MySQL/MariaDB lama tidak mendukungnya.
    $columnStmt = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_shifts' AND COLUMN_NAME = 'daily_schedule_json'");
    $columnStmt->execute();
    if ((int)$columnStmt->fetchColumn() === 0) {
        db()->exec("ALTER TABLE work_shifts ADD COLUMN daily_schedule_json LONGTEXT NULL AFTER work_days_json");
    }

    db()->exec("\n        CREATE TABLE IF NOT EXISTS position_shift_settings (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            position_name VARCHAR(120) NOT NULL,\n            department_name VARCHAR(120) NOT NULL DEFAULT '',\n            shift_id BIGINT UNSIGNED NOT NULL,\n            updated_by_user_id BIGINT UNSIGNED NULL,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            UNIQUE KEY uniq_position_shift (position_name,department_name),\n            INDEX idx_position_shift_shift (shift_id),\n            CONSTRAINT fk_position_shift FOREIGN KEY (shift_id) REFERENCES work_shifts(id),\n            CONSTRAINT fk_position_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    db()->exec("\n        CREATE TABLE IF NOT EXISTS employee_shift_settings (\n            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n            employee_id BIGINT UNSIGNED NOT NULL,\n            shift_id BIGINT UNSIGNED NOT NULL,\n            updated_by_user_id BIGINT UNSIGNED NULL,\n            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),\n            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),\n            UNIQUE KEY uniq_employee_shift (employee_id),\n            INDEX idx_employee_shift_shift (shift_id),\n            CONSTRAINT fk_employee_shift_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,\n            CONSTRAINT fk_employee_shift FOREIGN KEY (shift_id) REFERENCES work_shifts(id),\n            CONSTRAINT fk_employee_shift_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    db()->exec("
        CREATE TABLE IF NOT EXISTS employee_schedule_exceptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id BIGINT UNSIGNED NOT NULL,
            work_date DATE NOT NULL,
            schedule_type ENUM('work','off') NOT NULL,
            work_start TIME NULL,
            work_end TIME NULL,
            replacement_date DATE NULL,
            reason VARCHAR(500) NOT NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uniq_employee_exception_date (employee_id,work_date),
            INDEX idx_exception_date (work_date),
            CONSTRAINT fk_exception_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            CONSTRAINT fk_exception_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $count = (int)db()->query('SELECT COUNT(*) FROM work_shifts')->fetchColumn();
    if ($count === 0) {
        global $config;
        $start = (string)($config['attendance']['work_start'] ?? '08:00');
        $end = (string)($config['attendance']['work_end'] ?? '17:00');
        $tolerance = max(0, min(180, (int)($config['attendance']['late_tolerance_minutes'] ?? 15)));
        $days = sigap_normalize_shift_days($config['attendance']['work_days'] ?? [1,2,3,4,5,6]);
        if (!$days) $days = [1,2,3,4,5,6];
        $stmt = db()->prepare("INSERT INTO work_shifts (shift_code,shift_name,work_start,work_end,late_tolerance_minutes,work_days_json,is_active) VALUES ('DEFAULT','Jam Kerja Default',?,?,?,?,1)");
        $stmt->execute([$start,$end,$tolerance,json_encode($days,JSON_UNESCAPED_UNICODE)]);
    }
    $ready = true;
}

function sigap_normalize_daily_schedule(mixed $raw, array $workDays, string $defaultStart, string $defaultEnd): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) $raw = [];
    $schedule = [];
    foreach ($workDays as $day) {
        $item = $raw[(string)$day] ?? $raw[$day] ?? [];
        $start = trim((string)($item['work_start'] ?? $defaultStart));
        $end = trim((string)($item['work_end'] ?? $defaultEnd));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)) $start = $defaultStart;
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)) $end = $defaultEnd;
        $schedule[(string)$day] = ['work_start'=>$start,'work_end'=>$end];
    }
    return $schedule;
}

function sigap_shift_schedule_for_date(array $shift, string|DateTimeInterface $date): array
{
    $dateObj = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
    $day = (string)(int)$dateObj->format('N');
    $daily = $shift['daily_schedule'] ?? [];
    return $daily[$day] ?? ['work_start'=>$shift['work_start'],'work_end'=>$shift['work_end']];
}

function sigap_get_schedule_exception(PDO $pdo, int $employeeId, string|DateTimeInterface $date): ?array
{
    sigap_ensure_shift_tables();
    $key = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string)$date,0,10);
    $stmt = $pdo->prepare('SELECT * FROM employee_schedule_exceptions WHERE employee_id=? AND work_date=? LIMIT 1');
    $stmt->execute([$employeeId,$key]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id'=>(int)$row['id'], 'employee_id'=>(int)$row['employee_id'], 'work_date'=>(string)$row['work_date'],
        'schedule_type'=>(string)$row['schedule_type'],
        'work_start'=>$row['work_start'] ? substr((string)$row['work_start'],0,5) : null,
        'work_end'=>$row['work_end'] ? substr((string)$row['work_end'],0,5) : null,
        'replacement_date'=>$row['replacement_date'] ?: null, 'reason'=>(string)$row['reason']
    ];
}

function sigap_resolve_employee_schedule(PDO $pdo, int $employeeId, array $shift, string|DateTimeInterface $date, array $holidays=[]): array
{
    $dateObj = $date instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($date) : new DateTimeImmutable((string)$date);
    $key = $dateObj->format('Y-m-d');
    $exception = sigap_get_schedule_exception($pdo,$employeeId,$dateObj);
    if ($exception) {
        if ($exception['schedule_type'] === 'off') return ['is_workday'=>false,'source'=>'special_off','exception'=>$exception,'work_start'=>null,'work_end'=>null];
        return ['is_workday'=>true,'source'=>'special_work','exception'=>$exception,'work_start'=>$exception['work_start'],'work_end'=>$exception['work_end']];
    }
    $isWorkday = in_array((int)$dateObj->format('N'),$shift['work_days'] ?? [],true) && empty($holidays[$key]);
    $schedule = sigap_shift_schedule_for_date($shift,$dateObj);
    return ['is_workday'=>$isWorkday,'source'=>'shift','exception'=>null,'work_start'=>$schedule['work_start'],'work_end'=>$schedule['work_end']];
}

function sigap_shift_public(array $row, string $source='default'): array
{
    $days = sigap_normalize_shift_days($row['work_days_json'] ?? []);
    $start = substr((string)($row['work_start'] ?? '08:00'),0,5);
    $end = substr((string)($row['work_end'] ?? '17:00'),0,5);
    $dailySchedule = sigap_normalize_daily_schedule($row['daily_schedule_json'] ?? [],$days ?: [1,2,3,4,5,6],$start,$end);
    return [
        'id'=>(int)($row['id'] ?? 0),
        'shift_code'=>(string)($row['shift_code'] ?? 'DEFAULT'),
        'shift_name'=>(string)($row['shift_name'] ?? 'Jam Kerja Default'),
        'work_start'=>$start,
        'work_end'=>$end,
        'daily_schedule'=>$dailySchedule,
        'late_tolerance_minutes'=>(int)($row['late_tolerance_minutes'] ?? 15),
        'work_days'=>$days ?: [1,2,3,4,5,6],
        'source'=>$source,
        'is_active'=>(int)($row['is_active'] ?? 1),
    ];
}

function sigap_get_employee_shift(PDO $pdo, int $employeeId): array
{
    sigap_ensure_shift_tables();
    $stmt = $pdo->prepare("\n        SELECT e.position_name,e.department_name,\n               es.id AS employee_setting_id, es.shift_id AS employee_shift_id,\n               ps.id AS position_setting_id, ps.shift_id AS position_shift_id\n        FROM employees e\n        LEFT JOIN employee_shift_settings es ON es.employee_id=e.id\n        LEFT JOIN position_shift_settings ps ON ps.position_name=e.position_name AND ps.department_name=COALESCE(e.department_name,'')\n        WHERE e.id=? LIMIT 1\n    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    if (!$employee) throw new RuntimeException('Data karyawan tidak ditemukan.');

    $shiftId = null;
    $source = 'default';
    if (!empty($employee['employee_shift_id'])) {
        $shiftId = (int)$employee['employee_shift_id'];
        $source = 'employee';
    } elseif (!empty($employee['position_shift_id'])) {
        $shiftId = (int)$employee['position_shift_id'];
        $source = 'position';
    }

    if ($shiftId) {
        $shiftStmt = $pdo->prepare('SELECT * FROM work_shifts WHERE id=? AND is_active=1 LIMIT 1');
        $shiftStmt->execute([$shiftId]);
        $shift = $shiftStmt->fetch();
        if ($shift) return sigap_shift_public($shift,$source);
    }

    $shift = $pdo->query("SELECT * FROM work_shifts WHERE is_active=1 ORDER BY (shift_code='DEFAULT') DESC,id LIMIT 1")->fetch();
    if ($shift) return sigap_shift_public($shift,'default');

    global $config;
    return [
        'id'=>0,
        'shift_code'=>'DEFAULT',
        'shift_name'=>'Jam Kerja Default',
        'work_start'=>(string)($config['attendance']['work_start'] ?? '08:00'),
        'work_end'=>(string)($config['attendance']['work_end'] ?? '17:00'),
        'late_tolerance_minutes'=>(int)($config['attendance']['late_tolerance_minutes'] ?? 15),
        'work_days'=>sigap_report_working_days(),
        'source'=>'default',
        'is_active'=>1,
    ];
}

function sigap_validate_shift_payload(array $data): array
{
    require_fields($data,['shift_code','shift_name','work_start','work_end','work_days']);
    $code = strtoupper(clean_string($data['shift_code'],40));
    $name = clean_string($data['shift_name'],120);
    if (!preg_match('/^[A-Z0-9_-]+$/',$code)) {
        json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Kode shift hanya boleh berisi huruf, angka, tanda hubung, atau underscore.'],422);
    }
    $start = trim((string)$data['work_start']);
    $end = trim((string)$data['work_end']);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)) {
        json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Format jam kerja harus HH:MM.'],422);
    }
    if ($end <= $start) {
        json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jam pulang harus lebih besar dari jam masuk. Shift lintas tengah malam belum didukung.'],422);
    }
    $days = sigap_normalize_shift_days($data['work_days']);
    if (!$days) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Pilih minimal satu hari kerja.'],422);
    $rawSchedule = $data['daily_schedule'] ?? ($data['daily_schedule_json'] ?? []);
    $dailySchedule = sigap_normalize_daily_schedule($rawSchedule,$days,$start,$end);
    foreach ($dailySchedule as $day=>$schedule) {
        if ($schedule['work_end'] <= $schedule['work_start']) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jam pulang setiap hari harus lebih besar dari jam masuk.'],422);
    }
    $tolerance = max(0,min(180,(int)($data['late_tolerance_minutes'] ?? 15)));
    return [$code,$name,$start,$end,$tolerance,$days,$dailySchedule];
}


function sigap_report_working_days(): array
{
    global $config;
    $raw = $config['attendance']['work_days'] ?? [1,2,3,4,5,6];
    if (!is_array($raw)) $raw = [1,2,3,4,5,6];
    $days = [];
    foreach ($raw as $day) {
        $value = (int)$day;
        if ($value >= 1 && $value <= 7) $days[] = $value;
    }
    $days = array_values(array_unique($days));
    sort($days);
    return $days ?: [1,2,3,4,5,6];
}

function sigap_report_holidays(): array
{
    global $config;
    $raw = $config['attendance']['holidays'] ?? [];
    if (!is_array($raw)) return [];
    $holidays = [];
    foreach ($raw as $date) {
        $date = (string)$date;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $holidays[$date] = true;
    }
    return $holidays;
}

function sigap_report_is_workday(DateTimeImmutable $date, array $workingDays, array $holidays): bool
{
    $key = $date->format('Y-m-d');
    return in_array((int)$date->format('N'), $workingDays, true) && empty($holidays[$key]);
}

function sigap_report_round(float $value): float
{
    return round($value, 2);
}

function sigap_calculate_overtime_hours(string $startDate, string $endDate, string $startTime, string $endTime): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new InvalidArgumentException('Tanggal lembur tidak valid.');
    }
    if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $endTime)) {
        throw new InvalidArgumentException('Jam mulai dan jam selesai lembur wajib diisi.');
    }
    $start = new DateTimeImmutable($startDate . ' ' . $startTime);
    $end = new DateTimeImmutable($endDate . ' ' . $endTime);
    if ($end <= $start && $startDate === $endDate) {
        $end = $end->modify('+1 day');
        $endDate = $end->format('Y-m-d');
    }
    if ($end <= $start) throw new InvalidArgumentException('Jam selesai lembur harus setelah jam mulai.');
    $hours = round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
    if ($hours <= 0 || $hours > 24) throw new InvalidArgumentException('Durasi lembur harus lebih dari 0 dan maksimal 24 jam per pengajuan.');
    return ['hours'=>$hours,'end_date'=>$endDate];
}

function sigap_ensure_attendance_v110_columns(): void
{
    static $done=false; if($done)return; $done=true;
    $pdo=db();
    $columns=[
        'check_out_outside_geofence'=>"TINYINT(1) NOT NULL DEFAULT 0",
        'check_out_reason'=>"VARCHAR(500) NULL",
        'admin_note'=>"VARCHAR(1000) NULL",
        'updated_by_user_id'=>"BIGINT UNSIGNED NULL",
        'check_in_outside_geofence'=>"TINYINT(1) NOT NULL DEFAULT 0",
        'check_in_business_trip_id'=>"BIGINT UNSIGNED NULL"
    ];
    try{$pdo->exec("ALTER TABLE attendance MODIFY COLUMN status ENUM('present','late','late_permitted','incomplete','corrected') NOT NULL DEFAULT 'present'");}catch(Throwable $e){}
    try{$pdo->exec("ALTER TABLE employee_requests MODIFY COLUMN request_type ENUM('leave','permission','sick','overtime','late_permission') NOT NULL");}catch(Throwable $e){}
    foreach($columns as $name=>$definition){
        try{
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME=?");
            $stmt->execute([$name]);
            if(!(int)$stmt->fetchColumn())$pdo->exec("ALTER TABLE attendance ADD COLUMN {$name} {$definition}");
        }catch(Throwable $e){ /* migration file can be used when ALTER is restricted */ }
    }
}

function sigap_build_attendance_report(PDO $pdo, string $month, ?int $employeeId = null): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Format periode harus YYYY-MM.');
    }

    $periodStart = new DateTimeImmutable($month . '-01');
    if ($periodStart->format('Y-m') !== $month) {
        throw new InvalidArgumentException('Periode tidak valid.');
    }
    $periodEnd = $periodStart->modify('last day of this month');
    $today = new DateTimeImmutable('today');
    $effectiveEnd = $periodEnd < $today ? $periodEnd : $today;
    $hasElapsedDays = $effectiveEnd >= $periodStart;
    $defaultWorkingDays = sigap_report_working_days();
    $holidays = sigap_report_holidays();
    sigap_ensure_shift_tables();
    sigap_ensure_attendance_v110_columns();

    $employeeSql = "SELECT id,nik,full_name,position_name,department_name,employment_status,joined_date FROM employees WHERE employment_status='active' AND (joined_date IS NULL OR joined_date<=?)";
    $employeeParams = [$periodEnd->format('Y-m-d')];
    if ($employeeId !== null && $employeeId > 0) {
        $employeeSql .= ' AND id=?';
        $employeeParams[] = $employeeId;
    }
    $employeeSql .= ' ORDER BY full_name';
    $employeeStmt = $pdo->prepare($employeeSql);
    $employeeStmt->execute($employeeParams);
    $employees = $employeeStmt->fetchAll();

    if (!$employees) {
        return [
            'month'=>$month,
            'period_start'=>$periodStart->format('Y-m-d'),
            'period_end'=>$periodEnd->format('Y-m-d'),
            'effective_end'=>$hasElapsedDays ? $effectiveEnd->format('Y-m-d') : null,
            'working_days'=>$defaultWorkingDays,
            'schedule_mode'=>'per_employee',
            'holiday_dates'=>array_keys($holidays),
            'employees'=>[],
            'summary'=>[],
            'detail'=>[],
        ];
    }

    $shiftByEmployee = [];
    foreach ($employees as $employee) {
        $shiftByEmployee[(int)$employee['id']] = sigap_get_employee_shift($pdo,(int)$employee['id']);
    }

    $ids = array_map(static fn($row)=>(int)$row['id'], $employees);
    $employeeLookup = [];
    foreach ($employees as $employeeRow) $employeeLookup[(int)$employeeRow['id']] = $employeeRow;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rangeStart = $periodStart->format('Y-m-d');
    $rangeEnd = $hasElapsedDays ? $effectiveEnd->format('Y-m-d') : $periodStart->format('Y-m-d');

    $attendanceByEmployee = [];
    if ($hasElapsedDays) {
        $stmt = $pdo->prepare("SELECT a.*,w.location_name FROM attendance a LEFT JOIN work_locations w ON w.id=a.location_id WHERE a.employee_id IN ($placeholders) AND a.attendance_date BETWEEN ? AND ? ORDER BY a.attendance_date");
        $stmt->execute([...$ids,$rangeStart,$rangeEnd]);
        foreach ($stmt->fetchAll() as $row) {
            $attendanceByEmployee[(int)$row['employee_id']][(string)$row['attendance_date']] = $row;
        }
    }

    $requestsByEmployee = [];
    $overtimeByEmployee = [];
    $overtimeAgendaByEmployee = [];
    $overtimeDetail = [];
    if ($hasElapsedDays) {
        $stmt = $pdo->prepare("SELECT * FROM employee_requests WHERE employee_id IN ($placeholders) AND status='approved' AND start_date<=? AND end_date>=? ORDER BY start_date,id");
        $stmt->execute([...$ids,$rangeEnd,$rangeStart]);
        foreach ($stmt->fetchAll() as $row) {
            $eid = (int)$row['employee_id'];
            $workingDays = $shiftByEmployee[$eid]['work_days'] ?? $defaultWorkingDays;
            if ($row['request_type'] === 'overtime') {
                $requestStart = new DateTimeImmutable((string)$row['start_date']);
                $requestEnd = new DateTimeImmutable((string)$row['end_date']);
                $overlapStart = $requestStart > $periodStart ? $requestStart : $periodStart;
                $overlapEnd = $requestEnd < $effectiveEnd ? $requestEnd : $effectiveEnd;
                if ($overlapEnd >= $overlapStart) {
                    $totalDays = max(1, (int)$requestStart->diff($requestEnd)->days + 1);
                    $overlapDays = max(1, (int)$overlapStart->diff($overlapEnd)->days + 1);
                    $allocatedHours = sigap_report_round((float)$row['quantity'] * ($overlapDays / $totalDays));
                    $overtimeByEmployee[$eid] = ($overtimeByEmployee[$eid] ?? 0.0) + $allocatedHours;
                    $agenda = trim((string)$row['reason']);
                    if ($agenda !== '') $overtimeAgendaByEmployee[$eid][] = $agenda;
                    $employeeInfo = $employeeLookup[$eid] ?? [];
                    $overtimeDetail[] = [
                        'request_id'=>(int)$row['id'], 'employee_id'=>$eid,
                        'nik'=>(string)($employeeInfo['nik'] ?? ''), 'full_name'=>(string)($employeeInfo['full_name'] ?? ''),
                        'position_name'=>(string)($employeeInfo['position_name'] ?? ''), 'department_name'=>(string)($employeeInfo['department_name'] ?? ''),
                        'start_date'=>(string)$row['start_date'], 'end_date'=>(string)$row['end_date'],
                        'start_time'=>(string)($row['start_time'] ?? ''), 'end_time'=>(string)($row['end_time'] ?? ''),
                        'hours'=>$allocatedHours, 'agenda'=>$agenda, 'decided_at'=>$row['decided_at'] ?? null,
                    ];
                }
                continue;
            }

            $requestStart = new DateTimeImmutable((string)$row['start_date']);
            $requestEnd = new DateTimeImmutable((string)$row['end_date']);
            $overlapStart = $requestStart > $periodStart ? $requestStart : $periodStart;
            $overlapEnd = $requestEnd < $effectiveEnd ? $requestEnd : $effectiveEnd;
            if ($row['request_type'] === 'late_permission') continue;
            $eligibleDates = [];
            for ($cursor=$overlapStart; $cursor<=$overlapEnd; $cursor=$cursor->modify('+1 day')) {
                if (sigap_report_is_workday($cursor,$workingDays,$holidays)) $eligibleDates[] = $cursor->format('Y-m-d');
            }
            if (!$eligibleDates) continue;
            $quantity = (float)$row['quantity'];
            // Approved leave/permission/sick exempts the employee from attendance for each approved workday.
            $perDay = 1.0;
            foreach ($eligibleDates as $dateKey) {
                $requestsByEmployee[$eid][$dateKey][] = [
                    'type'=>(string)$row['request_type'],
                    'unit'=>$perDay,
                    'reason'=>(string)$row['reason'],
                    'request_id'=>(int)$row['id'],
                ];
            }
        }
    }

    $tripsByEmployee = [];
    if ($hasElapsedDays) {
        $stmt = $pdo->prepare("SELECT id,employee_id,start_date,end_date,project_name,destination,purpose FROM business_trips WHERE employee_id IN ($placeholders) AND approval_status='approved' AND start_date<=? AND end_date>=?");
        $stmt->execute([...$ids,$rangeEnd,$rangeStart]);
        foreach ($stmt->fetchAll() as $row) {
            $eid = (int)$row['employee_id'];
            $workingDays = $shiftByEmployee[$eid]['work_days'] ?? $defaultWorkingDays;
            $start = new DateTimeImmutable((string)$row['start_date']);
            $end = new DateTimeImmutable((string)$row['end_date']);
            $overlapStart = $start > $periodStart ? $start : $periodStart;
            $overlapEnd = $end < $effectiveEnd ? $end : $effectiveEnd;
            for ($cursor=$overlapStart; $cursor<=$overlapEnd; $cursor=$cursor->modify('+1 day')) {
                if (!sigap_report_is_workday($cursor,$workingDays,$holidays)) continue;
                $tripsByEmployee[$eid][$cursor->format('Y-m-d')] = $row;
            }
        }
    }

    $summary = [];
    $detail = [];
    $priority = ['sick'=>3,'leave'=>2,'permission'=>1];
    $typeLabels = ['leave'=>'Cuti','permission'=>'Izin','sick'=>'Sakit'];

    foreach ($employees as $employee) {
        $eid = (int)$employee['id'];
        $shift = $shiftByEmployee[$eid] ?? sigap_get_employee_shift($pdo,$eid);
        $workingDays = $shift['work_days'] ?: $defaultWorkingDays;
        $joinedDate = !empty($employee['joined_date']) ? new DateTimeImmutable((string)$employee['joined_date']) : null;
        $row = [
            'employee_id'=>$eid,
            'nik'=>(string)$employee['nik'],
            'full_name'=>(string)$employee['full_name'],
            'position_name'=>(string)$employee['position_name'],
            'department_name'=>(string)$employee['department_name'],
            'shift_id'=>(int)$shift['id'],
            'shift_code'=>(string)$shift['shift_code'],
            'shift_name'=>(string)$shift['shift_name'],
            'shift_source'=>(string)$shift['source'],
            'work_start'=>(string)$shift['work_start'],
            'work_end'=>(string)$shift['work_end'],
            'daily_schedule'=>$shift['daily_schedule'] ?? [],
            'work_day_numbers'=>$workingDays,
            'work_days'=>0.0,
            'present_days'=>0.0,
            'check_out_days'=>0.0,
            'on_time_days'=>0.0,
            'late_days'=>0.0,
            'incomplete_days'=>0.0,
            'leave_days'=>0.0,
            'permission_days'=>0.0,
            'sick_days'=>0.0,
            'business_trip_days'=>0.0,
            'alpha_days'=>0.0,
            'overtime_hours'=>sigap_report_round((float)($overtimeByEmployee[$eid] ?? 0.0)),
            'overtime_agenda'=>implode('; ',array_values(array_unique($overtimeAgendaByEmployee[$eid] ?? []))),
            'late_minutes'=>0,
        ];

        if ($hasElapsedDays) {
            for ($cursor=$periodStart; $cursor<=$effectiveEnd; $cursor=$cursor->modify('+1 day')) {
                if ($joinedDate && $cursor < $joinedDate) continue;
                $resolvedSchedule = sigap_resolve_employee_schedule($pdo,$eid,$shift,$cursor,$holidays);
                if (!$resolvedSchedule['is_workday']) continue;
                $dateKey = $cursor->format('Y-m-d');
                $daySchedule = ['work_start'=>$resolvedSchedule['work_start'],'work_end'=>$resolvedSchedule['work_end']];
                $row['work_days'] += 1;
                $attendance = $attendanceByEmployee[$eid][$dateKey] ?? null;
                $requests = $requestsByEmployee[$eid][$dateKey] ?? [];
                $trip = $tripsByEmployee[$eid][$dateKey] ?? null;
                $status = 'Alfa';
                $unit = 1.0;
                $note = '';
                $checkIn = $attendance['check_in_at'] ?? null;
                $checkOut = $attendance['check_out_at'] ?? null;
                $lateMinutes = (int)($attendance['late_minutes'] ?? 0);
                $location = (string)($attendance['location_name'] ?? '');

                if ($attendance && !empty($attendance['check_in_at'])) {
                    $row['present_days'] += 1;
                    $row['late_minutes'] += $lateMinutes;
                    if (!empty($attendance['check_out_at'])) $row['check_out_days'] += 1;
                    else $row['incomplete_days'] += 1;
                    if ($lateMinutes > 0 || in_array(($attendance['status'] ?? ''),['late','late_permitted'],true)) {
                        $row['late_days'] += 1;
                        $status = (($attendance['status'] ?? '') === 'late_permitted') ? 'Izin Terlambat' : 'Terlambat';
                    } else {
                        $row['on_time_days'] += 1;
                        $status = 'Hadir';
                    }
                    if (!$checkOut) $note = 'Belum absen pulang';
                    if ($requests) $note = trim($note . ($note ? '; ' : '') . 'Ada pengajuan disetujui pada tanggal yang sama');
                } elseif ($trip) {
                    $row['business_trip_days'] += 1;
                    $status = 'Dinas';
                    $note = trim((string)$trip['project_name'] . ((string)$trip['destination'] !== '' ? ' - ' . (string)$trip['destination'] : ''));
                } elseif ($requests) {
                    usort($requests, static function(array $a,array $b) use ($priority): int {
                        $unitCompare = ($b['unit'] <=> $a['unit']);
                        return $unitCompare !== 0 ? $unitCompare : (($priority[$b['type']] ?? 0) <=> ($priority[$a['type']] ?? 0));
                    });
                    $request = $requests[0];
                    $requestUnit = min(1.0,max(0.0,(float)$request['unit']));
                    $unit = $requestUnit;
                    $status = $typeLabels[$request['type']] ?? ucfirst((string)$request['type']);
                    $note = (string)$request['reason'];
                    if ($request['type'] === 'leave') $row['leave_days'] += $requestUnit;
                    elseif ($request['type'] === 'permission') $row['permission_days'] += $requestUnit;
                    elseif ($request['type'] === 'sick') $row['sick_days'] += $requestUnit;
                    $remaining = max(0.0,1.0-$requestUnit);
                    if ($remaining > 0) $row['alpha_days'] += $remaining;
                } else {
                    $row['alpha_days'] += 1;
                }

                $detail[] = [
                    'employee_id'=>$eid,
                    'nik'=>$row['nik'],
                    'full_name'=>$row['full_name'],
                    'department_name'=>$row['department_name'],
                    'shift_name'=>$row['shift_name'],
                    'work_start'=>$daySchedule['work_start'],
                    'work_end'=>$daySchedule['work_end'],
                    'attendance_date'=>$dateKey,
                    'day_number'=>(int)$cursor->format('N'),
                    'status'=>$status,
                    'unit'=>sigap_report_round($unit),
                    'check_in_at'=>$checkIn,
                    'check_out_at'=>$checkOut,
                    'late_minutes'=>$lateMinutes,
                    'location_name'=>$location,
                    'note'=>$note,
                ];
            }
        }

        foreach (['work_days','present_days','check_out_days','on_time_days','late_days','incomplete_days','leave_days','permission_days','sick_days','business_trip_days','alpha_days'] as $key) {
            $row[$key] = sigap_report_round((float)$row[$key]);
        }
        $summary[] = $row;
    }

    return [
        'month'=>$month,
        'period_start'=>$periodStart->format('Y-m-d'),
        'period_end'=>$periodEnd->format('Y-m-d'),
        'effective_end'=>$hasElapsedDays ? $effectiveEnd->format('Y-m-d') : null,
        'working_days'=>$defaultWorkingDays,
        'schedule_mode'=>'per_employee',
        'holiday_dates'=>array_keys($holidays),
        'employees'=>array_map(static function($e) use ($shiftByEmployee): array {
            $shift = $shiftByEmployee[(int)$e['id']] ?? [];
            return [
                'id'=>(int)$e['id'],'nik'=>(string)$e['nik'],'full_name'=>(string)$e['full_name'],
                'position_name'=>(string)$e['position_name'],'department_name'=>(string)$e['department_name'],
                'shift_name'=>(string)($shift['shift_name'] ?? ''),'work_start'=>(string)($shift['work_start'] ?? ''),'work_end'=>(string)($shift['work_end'] ?? '')
            ];
        },$employees),
        'summary'=>$summary,
        'detail'=>$detail,
        'overtime_detail'=>$overtimeDetail,
    ];
}


function sigap_position_source_rows(): array
{
    sigap_ensure_position_access_table();
    $sql = "\n        SELECT\n            src.position_name,\n            src.department_name,\n            COALESCE(j.default_role, p.role, src.inferred_role, 'employee') AS role,\n            COALESCE(j.description,'') AS description,\n            COALESCE(j.is_active,1) AS is_active,\n            COALESCE(src.active_count,0) AS active_count,\n            src.current_roles,\n            p.updated_at AS access_updated_at,
            p.permissions_json AS permissions_json\n        FROM (\n            SELECT e.position_name, COALESCE(e.department_name,'') AS department_name, COUNT(*) AS active_count,\n                   GROUP_CONCAT(DISTINCT COALESCE(u.role,'employee') ORDER BY COALESCE(u.role,'employee') SEPARATOR ', ') AS current_roles,\n                   NULL AS inferred_role\n            FROM employees e\n            LEFT JOIN users u ON u.employee_id=e.id\n            WHERE e.employment_status='active'\n            GROUP BY e.position_name, COALESCE(e.department_name,'')\n            UNION\n            SELECT jp.position_name, jp.department_name, 0 AS active_count, '' AS current_roles, jp.default_role AS inferred_role\n            FROM job_positions jp\n            WHERE jp.is_active=1\n        ) src\n        LEFT JOIN job_positions j ON j.position_name=src.position_name AND j.department_name=src.department_name\n        LEFT JOIN position_access_profiles p ON p.position_name=src.position_name AND p.department_name=src.department_name\n        ORDER BY src.department_name, src.position_name\n    ";
    return db()->query($sql)->fetchAll();
}

try {
    // Public endpoints
    if ($path === '/health' && $httpMethod === 'GET') {
        $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        global $config;
        json_response(['ok' => true, 'app' => (string)($config['app']['name'] ?? 'SIGAP MPA'), 'version' => '1.11.3', 'database' => true, 'installed' => $count > 0, 'server_time' => now_sql()]);
    }

    if ($path === '/auth/login' && $httpMethod === 'POST') {
        $data = request_json();
        require_fields($data, ['username', 'password']);
        $username = clean_string($data['username'], 100);
        $password = (string)$data['password'];
        global $config;
        $window = (int)($config['security']['login_window_minutes'] ?? 15);
        $max = (int)($config['security']['login_max_attempts'] ?? 5);
        $cutoff = (new DateTimeImmutable("-{$window} minutes"))->format('Y-m-d H:i:s');
        $rate = db()->prepare("SELECT COUNT(*) FROM security_events WHERE event_type='login_failed' AND ip_address=? AND created_at>=?");
        $rate->execute([client_ip(), $cutoff]);
        if ((int)$rate->fetchColumn() >= $max) {
            json_response(['ok' => false, 'error' => 'rate_limited', 'message' => 'Terlalu banyak percobaan login. Coba kembali beberapa menit lagi.'], 429);
        }

        $stmt = db()->prepare('SELECT u.*, e.nik, e.full_name, e.position_name, e.department_name FROM users u LEFT JOIN employees e ON e.id=u.employee_id WHERE u.username=? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $locked = $user && $user['locked_until'] && strtotime((string)$user['locked_until']) > time();
        if (!$user || $user['status'] !== 'active' || $locked || !password_verify($password, (string)$user['password_hash'])) {
            if ($user) {
                $failed = (int)$user['failed_login_count'] + 1;
                $lockedUntil = $failed >= $max ? (new DateTimeImmutable("+{$window} minutes"))->format('Y-m-d H:i:s') : null;
                $update = db()->prepare('UPDATE users SET failed_login_count=?, locked_until=? WHERE id=?');
                $update->execute([$failed, $lockedUntil, (int)$user['id']]);
                security_event((int)$user['id'], $user['employee_id'] ? (int)$user['employee_id'] : null, 'login_failed', 'warning', 'Login gagal untuk username ' . $username);
            } else {
                security_event(null, null, 'login_failed', 'warning', 'Login gagal untuk username ' . $username);
            }
            json_response(['ok' => false, 'error' => 'invalid_credentials', 'message' => 'Username atau password tidak sesuai.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET failed_login_count=0, locked_until=NULL, last_login_at=NOW(6) WHERE id=?')->execute([(int)$user['id']]);
        audit((int)$user['id'], 'login', 'user', (string)$user['id'], null, ['username' => $username]);
        json_response(['ok' => true, 'user' => public_user($user), 'csrf' => csrf_token()]);
    }

    if ($path === '/auth/me' && $httpMethod === 'GET') {
        $user = require_auth();
        json_response(['ok' => true, 'user' => public_user($user), 'csrf' => csrf_token(), 'server_time' => now_sql()]);
    }

    if ($path === '/auth/logout' && $httpMethod === 'POST') {
        $user = require_auth();
        audit((int)$user['id'], 'logout', 'user', (string)$user['id']);
        session_unset();
        session_destroy();
        json_response(['ok' => true]);
    }


    // WebAuthn / passkey status, registration, and verification
    if ($path === '/webauthn/status' && $httpMethod === 'GET') {
        $user = require_auth();
        if (!$user['employee_id']) {
            json_response(['ok'=>true,'supported'=>true,'device'=>null,'registered'=>false,'pin_registered'=>false,'approved'=>false]);
        }
        $uuid = clean_string($_GET['device_uuid'] ?? '', 36);
        if ($uuid === '') {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Identitas perangkat wajib dikirim.'],422);
        }
        $stmt = db()->prepare('SELECT id,device_uuid,device_label,status,approved_at,last_used_at,webauthn_credential_id IS NOT NULL AS registered,attendance_pin_hash IS NOT NULL AS pin_registered FROM employee_devices WHERE employee_id=? AND device_uuid=? LIMIT 1');
        $stmt->execute([(int)$user['employee_id'], $uuid]);
        $device = $stmt->fetch() ?: null;
        json_response([
            'ok'=>true,
            'supported'=>true,
            'approved'=>$device && $device['status']==='approved',
            'registered'=>$device && (bool)$device['registered'],
            'pin_registered'=>$device && (bool)$device['pin_registered'],
            'device'=>$device ? [
                'id'=>(int)$device['id'],
                'label'=>$device['device_label'],
                'status'=>$device['status'],
                'approved_at'=>$device['approved_at'],
                'last_used_at'=>$device['last_used_at'],
            ] : null,
        ]);
    }

    if ($path === '/webauthn/register/options' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json(); require_fields($data,['device_uuid']);
        $stmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $stmt->execute([(int)$user['employee_id'], clean_string($data['device_uuid'],36)]);
        $device = $stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'device_not_approved','message'=>'HP harus disetujui Admin/HR sebelum sidik jari diaktifkan.'],403);
        $challenge = webauthn_new_challenge('register',(int)$user['id'],(int)$device['id']);
        $exclude = [];
        if (!empty($device['webauthn_credential_id'])) {
            $exclude[] = ['type'=>'public-key','id'=>webauthn_b64url_encode((string)$device['webauthn_credential_id']),'transports'=>['internal']];
        }
        json_response(['ok'=>true,'publicKey'=>[
            'challenge'=>$challenge,
            'rp'=>['name'=>'PT Multi Power Abadi','id'=>webauthn_rp_id()],
            'user'=>[
                'id'=>webauthn_b64url_encode(substr(hash('sha256','mpa-user:'.(int)$user['id'],true),0,32)),
                'name'=>$user['username'],
                'displayName'=>$user['full_name'] ?: $user['username'],
            ],
            'pubKeyCredParams'=>[['type'=>'public-key','alg'=>-7]],
            'timeout'=>60000,
            'attestation'=>'none',
            'authenticatorSelection'=>[
                'authenticatorAttachment'=>'platform',
                'residentKey'=>'discouraged',
                'requireResidentKey'=>false,
                'userVerification'=>'required',
            ],
            'excludeCredentials'=>$exclude,
        ]]);
    }

    if ($path === '/webauthn/register/verify' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json(); require_fields($data,['device_uuid','rawId','response']);
        $stmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $stmt->execute([(int)$user['employee_id'], clean_string($data['device_uuid'],36)]);
        $device = $stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'device_not_approved','message'=>'Perangkat belum disetujui.'],403);
        try {
            $response = is_array($data['response']) ? $data['response'] : [];
            $clientDataJson = webauthn_b64url_decode((string)($response['clientDataJSON'] ?? ''));
            webauthn_verify_client_data($clientDataJson,'webauthn.create','register',(int)$user['id'],(int)$device['id']);
            $attestationObject = webauthn_b64url_decode((string)($response['attestationObject'] ?? ''));
            $attestation = webauthn_cbor_decode($attestationObject);
            if (!is_array($attestation) || !isset($attestation['authData']) || !is_string($attestation['authData'])) {
                throw new RuntimeException('Attestation object tidak valid.');
            }
            $parsed = webauthn_parse_authenticator_data($attestation['authData'],true);
            webauthn_assert_rp_and_uv($parsed);
            $rawId = webauthn_b64url_decode((string)$data['rawId']);
            if (!hash_equals($rawId,(string)$parsed['credential_id'])) {
                throw new RuntimeException('Credential ID tidak sesuai.');
            }
            $pem = webauthn_cose_ec2_to_pem((array)$parsed['credential_public_key']);
            db()->prepare('UPDATE employee_devices SET webauthn_credential_id=?,webauthn_public_key=?,signature_counter=?,last_used_at=NOW(6) WHERE id=?')
                ->execute([$rawId,$pem,(int)$parsed['sign_count'],(int)$device['id']]);
            audit((int)$user['id'],'webauthn_registered','employee_device',(string)$device['id'],null,['device_uuid'=>$device['device_uuid']]);
            security_event((int)$user['id'],(int)$user['employee_id'],'webauthn_registered','info','Sidik jari/passkey berhasil diaktifkan pada perangkat yang disetujui.');
            json_response(['ok'=>true,'registered'=>true,'message'=>'Sidik jari atau kunci layar berhasil diaktifkan.']);
        } catch (Throwable $e) {
            security_event((int)$user['id'],(int)$user['employee_id'],'webauthn_registration_failed','warning',$e->getMessage());
            json_response(['ok'=>false,'error'=>'webauthn_registration_failed','message'=>$e->getMessage()],422);
        }
    }

    if ($path === '/webauthn/auth/options' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json(); require_fields($data,['device_uuid']);
        $stmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $stmt->execute([(int)$user['employee_id'], clean_string($data['device_uuid'],36)]);
        $device = $stmt->fetch();
        if (!$device || empty($device['webauthn_credential_id']) || empty($device['webauthn_public_key'])) {
            json_response(['ok'=>false,'error'=>'passkey_not_registered','message'=>'Aktifkan sidik jari/kunci layar pada HP ini terlebih dahulu.'],409);
        }
        $challenge = webauthn_new_challenge('authenticate',(int)$user['id'],(int)$device['id']);
        json_response(['ok'=>true,'publicKey'=>[
            'challenge'=>$challenge,
            'rpId'=>webauthn_rp_id(),
            'timeout'=>60000,
            'userVerification'=>'required',
            'allowCredentials'=>[ ['type'=>'public-key','id'=>webauthn_b64url_encode((string)$device['webauthn_credential_id']),'transports'=>['internal']] ],
        ]]);
    }

    if ($path === '/webauthn/auth/verify' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json(); require_fields($data,['device_uuid','rawId','response']);
        $stmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $stmt->execute([(int)$user['employee_id'], clean_string($data['device_uuid'],36)]);
        $device = $stmt->fetch();
        if (!$device || empty($device['webauthn_credential_id']) || empty($device['webauthn_public_key'])) {
            json_response(['ok'=>false,'error'=>'passkey_not_registered','message'=>'Sidik jari/kunci layar belum aktif pada perangkat ini.'],409);
        }
        try {
            $rawId = webauthn_b64url_decode((string)$data['rawId']);
            if (!hash_equals((string)$device['webauthn_credential_id'],$rawId)) {
                throw new RuntimeException('Credential bukan milik perangkat ini.');
            }
            $response = is_array($data['response']) ? $data['response'] : [];
            $clientDataJson = webauthn_b64url_decode((string)($response['clientDataJSON'] ?? ''));
            webauthn_verify_client_data($clientDataJson,'webauthn.get','authenticate',(int)$user['id'],(int)$device['id']);
            $authenticatorData = webauthn_b64url_decode((string)($response['authenticatorData'] ?? ''));
            $parsed = webauthn_parse_authenticator_data($authenticatorData,false);
            webauthn_assert_rp_and_uv($parsed);
            $signature = webauthn_b64url_decode((string)($response['signature'] ?? ''));
            $signedData = $authenticatorData . hash('sha256',$clientDataJson,true);
            $verified = openssl_verify($signedData,$signature,(string)$device['webauthn_public_key'],OPENSSL_ALGO_SHA256);
            if ($verified !== 1) {
                throw new RuntimeException('Tanda tangan passkey tidak valid.');
            }
            $storedCounter = (int)$device['signature_counter'];
            $newCounter = (int)$parsed['sign_count'];
            if ($storedCounter > 0 && $newCounter > 0 && $newCounter <= $storedCounter) {
                security_event((int)$user['id'],(int)$user['employee_id'],'webauthn_counter_anomaly','danger','Signature counter passkey tidak meningkat.');
                throw new RuntimeException('Passkey terindikasi diduplikasi atau diputar ulang.');
            }
            db()->prepare('UPDATE employee_devices SET signature_counter=?,last_used_at=NOW(6) WHERE id=?')->execute([$newCounter,(int)$device['id']]);
            $proof = webauthn_create_proof((int)$user['id'],(int)$device['id']);
            audit((int)$user['id'],'webauthn_verified','employee_device',(string)$device['id']);
            json_response(['ok'=>true,'verified'=>true,'biometric_token'=>$proof,'expires_in'=>90]);
        } catch (Throwable $e) {
            security_event((int)$user['id'],(int)$user['employee_id'],'webauthn_verification_failed','warning',$e->getMessage());
            json_response(['ok'=>false,'error'=>'webauthn_verification_failed','message'=>$e->getMessage()],401);
        }
    }

    // Dashboard summary
    if ($path === '/dashboard' && $httpMethod === 'GET') {
        $user = require_auth();
        $today = (new DateTimeImmutable())->format('Y-m-d');
        $summary = [
            'today' => null,
            'pending_requests' => 0,
            'pending_devices' => 0,
            'employees_active' => 0,
            'owner_attendance' => [],
            'owner_counts' => ['present' => 0, 'late' => 0, 'not_checked_in' => 0, 'active' => 0],
        ];
        if ($user['employee_id']) {
            $stmt = db()->prepare('SELECT a.*, w.location_name FROM attendance a LEFT JOIN work_locations w ON w.id=a.location_id WHERE a.employee_id=? AND a.attendance_date=?');
            $stmt->execute([(int)$user['employee_id'], $today]);
            $summary['today'] = $stmt->fetch() ?: null;
        }
        if ($user['role'] === 'owner') {
            $summary['pending_requests'] = (int)db()->query("SELECT COUNT(*) FROM employee_requests WHERE status='pending' AND request_type='overtime'")->fetchColumn();
        } elseif (in_array($user['role'], ['supervisor','hr_admin','super_admin'], true)) {
            $summary['pending_requests'] = (int)db()->query("SELECT COUNT(*) FROM employee_requests WHERE status='pending' AND request_type<>'overtime'")->fetchColumn();
        } elseif ($user['employee_id']) {
            $stmtPending = db()->prepare("SELECT COUNT(*) FROM employee_requests WHERE status='pending' AND employee_id=?");
            $stmtPending->execute([(int)$user['employee_id']]);
            $summary['pending_requests'] = (int)$stmtPending->fetchColumn();
        }
        if (in_array($user['role'], ['hr_admin','super_admin'], true)) {
            $summary['pending_devices'] = (int)db()->query("SELECT COUNT(*) FROM employee_devices WHERE status='pending'")->fetchColumn();
            $summary['employees_active'] = (int)db()->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
        }
        if (in_array($user['role'], ['owner','super_admin'], true)) {
            $rows = db()->query("
                SELECT e.id,e.nik,e.full_name,e.position_name,e.department_name,
                       a.check_in_at,a.check_out_at,a.late_minutes,a.status,w.location_name
                FROM employees e
                LEFT JOIN users u ON u.employee_id=e.id
                LEFT JOIN attendance a ON a.employee_id=e.id AND a.attendance_date=CURDATE()
                LEFT JOIN work_locations w ON w.id=a.location_id
                WHERE e.employment_status='active' AND COALESCE(u.role,'employee')<>'owner'
                ORDER BY CASE WHEN a.check_in_at IS NULL THEN 1 ELSE 0 END, a.check_in_at ASC, e.full_name ASC
            ")->fetchAll();
            $summary['owner_attendance'] = $rows;
            $summary['owner_counts']['active'] = count($rows);
            foreach ($rows as $row) {
                if (empty($row['check_in_at'])) {
                    $summary['owner_counts']['not_checked_in']++;
                } elseif ((int)$row['late_minutes'] > 0 || $row['status'] === 'late') {
                    $summary['owner_counts']['late']++;
                } else {
                    $summary['owner_counts']['present']++;
                }
            }
        }
        json_response(['ok' => true, 'summary' => $summary]);
    }



    // Announcements: dashboard notices for all roles, managed by HR/Owner/Super Admin
    if ($path === '/announcements' && $httpMethod === 'GET') {
        $user = require_auth();
        sigap_ensure_announcements_table();
        $activeOnly = isset($_GET['active']) ? (int)$_GET['active'] : 1;
        $params = [$user['role']];
        $where = "WHERE (audience='all' OR audience=?)";
        if ($activeOnly) {
            $where .= " AND is_active=1 AND (published_at IS NULL OR published_at<=NOW(6)) AND (expires_at IS NULL OR expires_at>=NOW(6))";
        }
        if (in_array($user['role'], ['hr_admin','owner','super_admin'], true) && !$activeOnly) {
            $params = [];
            $where = "WHERE 1=1";
        }
        $stmt = db()->prepare("SELECT id,title,body,audience,priority,is_active,published_at,expires_at,created_at,updated_at FROM announcements {$where} ORDER BY FIELD(priority,'urgent','important','normal'), COALESCE(published_at,created_at) DESC, id DESC LIMIT 100");
        $stmt->execute($params);
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }

    if ($path === '/announcements' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','owner','super_admin']);
        sigap_ensure_announcements_table();
        $data = request_json();
        require_fields($data, ['title','body']);
        $audiences = ['all','employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
        $priorities = ['normal','important','urgent'];
        $audience = clean_string($data['audience'] ?? 'all', 40);
        $priority = clean_string($data['priority'] ?? 'normal', 20);
        if (!in_array($audience,$audiences,true)) $audience='all';
        if (!in_array($priority,$priorities,true)) $priority='normal';
        $publishedAt = !empty($data['published_at']) ? str_replace('T',' ', clean_string($data['published_at'], 30)) : now_sql();
        $expiresAt = !empty($data['expires_at']) ? str_replace('T',' ', clean_string($data['expires_at'], 30)) : null;
        $stmt = db()->prepare('INSERT INTO announcements (title,body,audience,priority,is_active,published_at,expires_at,created_by_user_id) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([clean_string($data['title'],180), clean_string($data['body'],5000), $audience, $priority, !empty($data['is_active']) ? 1 : 1, $publishedAt, $expiresAt, (int)$actor['id']]);
        $id = (int)db()->lastInsertId();
        audit((int)$actor['id'],'announcement_created','announcement',(string)$id,null,['title'=>$data['title']]);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Pengumuman berhasil dibuat.']);
    }

    if (count($segments)===2 && $segments[0]==='announcements' && $httpMethod === 'PUT') {
        $actor = require_roles(['hr_admin','owner','super_admin']);
        sigap_ensure_announcements_table();
        $id=(int)$segments[1];
        $data=request_json();
        require_fields($data,['title','body']);
        $audiences = ['all','employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
        $priorities = ['normal','important','urgent'];
        $audience = in_array(($data['audience'] ?? 'all'),$audiences,true) ? $data['audience'] : 'all';
        $priority = in_array(($data['priority'] ?? 'normal'),$priorities,true) ? $data['priority'] : 'normal';
        $publishedAt = !empty($data['published_at']) ? str_replace('T',' ', clean_string($data['published_at'], 30)) : null;
        $expiresAt = !empty($data['expires_at']) ? str_replace('T',' ', clean_string($data['expires_at'], 30)) : null;
        $stmt=db()->prepare('UPDATE announcements SET title=?,body=?,audience=?,priority=?,is_active=?,published_at=?,expires_at=? WHERE id=?');
        $stmt->execute([clean_string($data['title'],180),clean_string($data['body'],5000),$audience,$priority,!empty($data['is_active'])?1:0,$publishedAt,$expiresAt,$id]);
        audit((int)$actor['id'],'announcement_updated','announcement',(string)$id);
        json_response(['ok'=>true,'message'=>'Pengumuman diperbarui.']);
    }

    if (count($segments)===2 && $segments[0]==='announcements' && $httpMethod === 'DELETE') {
        $actor = require_roles(['hr_admin','owner','super_admin']);
        sigap_ensure_announcements_table();
        $id=(int)$segments[1];
        db()->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
        audit((int)$actor['id'],'announcement_deleted','announcement',(string)$id);
        json_response(['ok'=>true,'message'=>'Pengumuman dihapus.']);
    }

    // Master jabatan and access setup for Super Admin
    if ($path === '/positions' && $httpMethod === 'GET') {
        require_roles(['hr_admin','payroll_finance','super_admin']);
        sigap_ensure_position_access_table();
        $roles = sigap_role_permissions();
        $rows = sigap_position_source_rows();
        foreach ($rows as &$row) {
            $role = $row['role'] ?: sigap_infer_role((string)($row['current_roles'] ?? ''));
            $row['role'] = $role;
            $row['role_label'] = $roles[$role]['label'] ?? $role;
            $row['permissions'] = sigap_normalize_permissions_json($row['permissions_json'] ?? null, $role);
            $row['permission_labels'] = sigap_permission_labels($row['permissions']);
            $row['active_count'] = (int)$row['active_count'];
            $row['is_active'] = (int)$row['is_active'];
        }
        unset($row);
        json_response(['ok'=>true,'data'=>$rows,'roles'=>$roles,'permission_catalog'=>sigap_permission_catalog()]);
    }

    if ($path === '/positions' && $httpMethod === 'POST') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_position_access_table();
        $data = request_json();
        require_fields($data, ['position_name','default_role']);
        $roles = sigap_role_permissions();
        $role = clean_string($data['default_role'], 40);
        if (!isset($roles[$role])) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Role default tidak valid.'],422);
        $position = clean_string($data['position_name'],120);
        $department = clean_string($data['department_name'] ?? '',120);
        $description = clean_string($data['description'] ?? '',1000);
        $stmt=db()->prepare("INSERT INTO job_positions (position_name,department_name,default_role,description,is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE default_role=VALUES(default_role), description=VALUES(description), is_active=1, updated_at=CURRENT_TIMESTAMP(6)");
        $stmt->execute([$position,$department,$role,$description]);
        audit((int)$actor['id'],'position_saved','job_position',$position.'|'.$department,null,['role'=>$role]);
        json_response(['ok'=>true,'message'=>'Master jabatan berhasil disimpan.']);
    }

    if (count($segments)===2 && $segments[0]==='positions' && $httpMethod === 'DELETE') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_position_access_table();
        $idKey = base64_decode((string)$segments[1], true) ?: (string)$segments[1];
        [$position,$department] = array_pad(explode('|',$idKey,2),2,'');
        db()->prepare('UPDATE job_positions SET is_active=0 WHERE position_name=? AND department_name=?')->execute([$position,$department]);
        audit((int)$actor['id'],'position_deleted','job_position',$position.'|'.$department);
        json_response(['ok'=>true,'message'=>'Master jabatan dinonaktifkan.']);
    }

    if ($path === '/access/positions' && $httpMethod === 'GET') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_position_access_table();
        $roles = sigap_role_permissions();
        $rows = sigap_position_source_rows();
        foreach ($rows as &$row) {
            $role = $row['role'] ?: sigap_infer_role((string)($row['current_roles'] ?? ''));
            $row['role'] = $role;
            $row['permissions'] = sigap_normalize_permissions_json($row['permissions_json'] ?? null, $role);
            $row['permission_labels'] = sigap_permission_labels($row['permissions']);
            $row['role_label'] = $roles[$role]['label'] ?? $role;
            $row['active_count'] = (int)$row['active_count'];
        }
        unset($row);
        json_response(['ok'=>true,'data'=>$rows,'roles'=>$roles,'permission_catalog'=>sigap_permission_catalog(),'message'=>'Hak akses jabatan dapat dikustom per fitur/menu sesuai master jabatan.']);
    }

    if ($path === '/access/positions' && $httpMethod === 'POST') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_position_access_table();
        $data = request_json();
        require_fields($data, ['position_name','role']);
        $position = clean_string($data['position_name'], 120);
        $department = clean_string($data['department_name'] ?? '', 120);
        $role = clean_string($data['role'], 40);
        $roles = sigap_role_permissions();
        if (!isset($roles[$role])) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Role tidak valid.'],422);
        $catalog = sigap_permission_catalog();
        $rawPermissions = $data['permissions'] ?? ($roles[$role]['permissions'] ?? []);
        if (!is_array($rawPermissions)) $rawPermissions = [];
        $permissions = [];
        foreach ($rawPermissions as $perm) {
            $perm = clean_string((string)$perm, 80);
            if (isset($catalog[$perm])) $permissions[] = $perm;
        }
        if (!$permissions) $permissions = $roles[$role]['permissions'] ?? ['dashboard','security'];
        if (!in_array('dashboard', $permissions, true)) $permissions[] = 'dashboard';
        if (!in_array('security', $permissions, true)) $permissions[] = 'security';
        if ($role === 'super_admin') $permissions = array_keys($catalog);
        $permissions = array_values(array_unique($permissions));
        $json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        db()->prepare("INSERT INTO job_positions (position_name,department_name,default_role,is_active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE default_role=VALUES(default_role), is_active=1, updated_at=CURRENT_TIMESTAMP(6)")->execute([$position,$department,$role]);
        db()->prepare("INSERT INTO position_access_profiles (position_name,department_name,role,permissions_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role), permissions_json=VALUES(permissions_json), updated_at=CURRENT_TIMESTAMP(6)")->execute([$position,$department,$role,$json]);
        $updatedUsers = 0;
        if (!empty($data['apply_to_users'])) {
            $sql = "UPDATE users u JOIN employees e ON e.id=u.employee_id SET u.role=? WHERE e.position_name=? AND COALESCE(e.department_name,'')=? AND e.employment_status='active' AND u.id<>?";
            $params = [$role,$position,$department,(int)$actor['id']];
            if ($role !== 'super_admin') $sql .= " AND u.role<>'super_admin'";
            $upd = db()->prepare($sql);
            $upd->execute($params);
            $updatedUsers = $upd->rowCount();
        }
        audit((int)$actor['id'], 'position_access_updated', 'position_access', $position . '|' . $department, null, ['role'=>$role,'apply_to_users'=>!empty($data['apply_to_users']),'updated_users'=>$updatedUsers,'permissions'=>$permissions]);
        json_response(['ok'=>true,'message'=>$updatedUsers>0?"Hak akses disimpan dan {$updatedUsers} akun diperbarui.":'Hak akses jabatan disimpan.','updated_users'=>$updatedUsers,'role'=>$role,'permissions'=>$permissions,'permission_labels'=>sigap_permission_labels($permissions)]);
    }

    // Work shifts and schedule assignments
    if ($path === '/shifts' && $httpMethod === 'GET') {
        require_roles(['hr_admin','payroll_finance','super_admin']);
        sigap_ensure_shift_tables();
        $shiftRows = db()->query('SELECT * FROM work_shifts ORDER BY is_active DESC,shift_name')->fetchAll();
        $shifts = array_map(static fn($row)=>sigap_shift_public($row,'master'),$shiftRows);

        $assignmentRows = db()->query("SELECT p.position_name,p.department_name,p.shift_id,s.shift_code,s.shift_name,s.work_start,s.work_end,s.work_days_json,s.late_tolerance_minutes FROM position_shift_settings p JOIN work_shifts s ON s.id=p.shift_id ORDER BY p.department_name,p.position_name")->fetchAll();
        $positionAssignments = [];
        foreach ($assignmentRows as $assignment) {
            $key = (string)$assignment['position_name'].'|'.(string)$assignment['department_name'];
            $positionAssignments[$key] = [
                'shift_id'=>(int)$assignment['shift_id'],
                'shift'=>sigap_shift_public(array_merge($assignment,['id'=>$assignment['shift_id'],'is_active'=>1]),'position')
            ];
        }
        $positionMap = [];
        foreach (sigap_position_source_rows() as $position) {
            $key = (string)$position['position_name'].'|'.(string)$position['department_name'];
            $assigned = $positionAssignments[$key] ?? null;
            if (!isset($positionMap[$key])) {
                $positionMap[$key] = [
                    'position_name'=>(string)$position['position_name'],
                    'department_name'=>(string)$position['department_name'],
                    'active_count'=>(int)$position['active_count'],
                    'shift_id'=>$assigned['shift_id'] ?? null,
                    'shift'=>$assigned['shift'] ?? null,
                ];
            } else {
                $positionMap[$key]['active_count'] = max((int)$positionMap[$key]['active_count'],(int)$position['active_count']);
            }
        }
        $positions = array_values($positionMap);

        $employeeRows = db()->query("SELECT e.id,e.nik,e.full_name,e.position_name,e.department_name,es.shift_id AS override_shift_id FROM employees e LEFT JOIN employee_shift_settings es ON es.employee_id=e.id WHERE e.employment_status='active' ORDER BY e.full_name")->fetchAll();
        $employees = [];
        foreach ($employeeRows as $employee) {
            $resolved = sigap_get_employee_shift(db(),(int)$employee['id']);
            $employees[] = [
                'id'=>(int)$employee['id'],
                'nik'=>(string)$employee['nik'],
                'full_name'=>(string)$employee['full_name'],
                'position_name'=>(string)$employee['position_name'],
                'department_name'=>(string)$employee['department_name'],
                'override_shift_id'=>!empty($employee['override_shift_id']) ? (int)$employee['override_shift_id'] : null,
                'shift'=>$resolved,
            ];
        }
        $exceptionRows = db()->query("SELECT x.*,e.nik,e.full_name FROM employee_schedule_exceptions x JOIN employees e ON e.id=x.employee_id WHERE x.work_date>=DATE_SUB(CURDATE(),INTERVAL 60 DAY) ORDER BY x.work_date DESC,e.full_name")->fetchAll();
        $exceptions = array_map(static fn($x)=>[
            'id'=>(int)$x['id'],'employee_id'=>(int)$x['employee_id'],'nik'=>(string)$x['nik'],'full_name'=>(string)$x['full_name'],
            'work_date'=>(string)$x['work_date'],'schedule_type'=>(string)$x['schedule_type'],
            'work_start'=>$x['work_start']?substr((string)$x['work_start'],0,5):null,'work_end'=>$x['work_end']?substr((string)$x['work_end'],0,5):null,
            'replacement_date'=>$x['replacement_date']?:null,'reason'=>(string)$x['reason']
        ],$exceptionRows);
        json_response(['ok'=>true,'data'=>['shifts'=>$shifts,'positions'=>$positions,'employees'=>$employees,'exceptions'=>$exceptions]]);
    }

    if ($path === '/shifts' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $data = request_json();
        [$code,$name,$start,$end,$tolerance,$days,$dailySchedule] = sigap_validate_shift_payload($data);
        try {
            $stmt = db()->prepare('INSERT INTO work_shifts (shift_code,shift_name,work_start,work_end,late_tolerance_minutes,work_days_json,daily_schedule_json,is_active,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,1,?,?)');
            $stmt->execute([$code,$name,$start,$end,$tolerance,json_encode($days,JSON_UNESCAPED_UNICODE),json_encode($dailySchedule,JSON_UNESCAPED_UNICODE),(int)$actor['id'],(int)$actor['id']]);
            $id = (int)db()->lastInsertId();
            audit((int)$actor['id'],'shift_created','work_shift',(string)$id,null,['shift_code'=>$code,'shift_name'=>$name,'work_start'=>$start,'work_end'=>$end,'work_days'=>$days,'daily_schedule'=>$dailySchedule]);
            json_response(['ok'=>true,'id'=>$id,'message'=>'Shift kerja berhasil dibuat.'],201);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(),'Duplicate')) json_response(['ok'=>false,'error'=>'duplicate','message'=>'Kode shift sudah digunakan.'],409);
            throw $e;
        }
    }

    if (count($segments) === 2 && $segments[0] === 'shifts' && in_array($httpMethod,['PUT','PATCH'],true)) {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $id = (int)$segments[1];
        $data = request_json();
        [$code,$name,$start,$end,$tolerance,$days,$dailySchedule] = sigap_validate_shift_payload($data);
        $beforeStmt = db()->prepare('SELECT * FROM work_shifts WHERE id=?');
        $beforeStmt->execute([$id]);
        $before = $beforeStmt->fetch();
        if (!$before) json_response(['ok'=>false,'error'=>'not_found','message'=>'Shift tidak ditemukan.'],404);
        $newActive = !empty($data['is_active']) ? 1 : 0;
        if ($newActive === 0) {
            $assignedStmt = db()->prepare('SELECT (SELECT COUNT(*) FROM position_shift_settings WHERE shift_id=?)+(SELECT COUNT(*) FROM employee_shift_settings WHERE shift_id=?)');
            $assignedStmt->execute([$id,$id]);
            if ((int)$assignedStmt->fetchColumn() > 0) json_response(['ok'=>false,'error'=>'shift_in_use','message'=>'Shift masih digunakan oleh jabatan atau karyawan. Alihkan jadwal terlebih dahulu.'],409);
            $activeCount = (int)db()->query('SELECT COUNT(*) FROM work_shifts WHERE is_active=1')->fetchColumn();
            if ($activeCount <= 1 && (int)$before['is_active'] === 1) json_response(['ok'=>false,'error'=>'last_shift','message'=>'Minimal satu shift aktif harus tersedia.'],409);
        }
        try {
            $stmt = db()->prepare('UPDATE work_shifts SET shift_code=?,shift_name=?,work_start=?,work_end=?,late_tolerance_minutes=?,work_days_json=?,daily_schedule_json=?,is_active=?,updated_by_user_id=? WHERE id=?');
            $stmt->execute([$code,$name,$start,$end,$tolerance,json_encode($days,JSON_UNESCAPED_UNICODE),json_encode($dailySchedule,JSON_UNESCAPED_UNICODE),$newActive,(int)$actor['id'],$id]);
            audit((int)$actor['id'],'shift_updated','work_shift',(string)$id,$before,['shift_code'=>$code,'shift_name'=>$name,'work_start'=>$start,'work_end'=>$end,'work_days'=>$days,'daily_schedule'=>$dailySchedule]);
            json_response(['ok'=>true,'message'=>'Shift kerja berhasil diperbarui.']);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(),'Duplicate')) json_response(['ok'=>false,'error'=>'duplicate','message'=>'Kode shift sudah digunakan.'],409);
            throw $e;
        }
    }

    if (count($segments) === 2 && $segments[0] === 'shifts' && $httpMethod === 'DELETE') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT * FROM work_shifts WHERE id=?');
        $stmt->execute([$id]);
        $before = $stmt->fetch();
        if (!$before) json_response(['ok'=>false,'error'=>'not_found','message'=>'Shift tidak ditemukan.'],404);
        $assignedStmt = db()->prepare('SELECT (SELECT COUNT(*) FROM position_shift_settings WHERE shift_id=?)+(SELECT COUNT(*) FROM employee_shift_settings WHERE shift_id=?)');
        $assignedStmt->execute([$id,$id]);
        if ((int)$assignedStmt->fetchColumn() > 0) json_response(['ok'=>false,'error'=>'shift_in_use','message'=>'Shift masih digunakan oleh jabatan atau karyawan. Alihkan jadwal terlebih dahulu.'],409);
        $activeCount = (int)db()->query('SELECT COUNT(*) FROM work_shifts WHERE is_active=1')->fetchColumn();
        if ($activeCount <= 1 && (int)$before['is_active'] === 1) json_response(['ok'=>false,'error'=>'last_shift','message'=>'Minimal satu shift aktif harus tersedia.'],409);
        db()->prepare('UPDATE work_shifts SET is_active=0,updated_by_user_id=? WHERE id=?')->execute([(int)$actor['id'],$id]);
        audit((int)$actor['id'],'shift_deactivated','work_shift',(string)$id,$before);
        json_response(['ok'=>true,'message'=>'Shift dinonaktifkan.']);
    }

    if ($path === '/shifts/special-schedule' && $httpMethod === 'POST') {
        $actor=sigap_require_permission('shifts');
        sigap_ensure_shift_tables();
        $data=request_json(); require_fields($data,['employee_id','work_date','schedule_type','reason']);
        $employeeId=(int)$data['employee_id']; $date=trim((string)$data['work_date']); $type=(string)$data['schedule_type'];
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!in_array($type,['work','off'],true)) json_response(['ok'=>false,'message'=>'Data jadwal khusus tidak valid.'],422);
        $reason=clean_string($data['reason'],500); if($reason==='') json_response(['ok'=>false,'message'=>'Alasan wajib diisi.'],422);
        $start=null;$end=null;
        if($type==='work'){
            $start=trim((string)($data['work_start']??''));$end=trim((string)($data['work_end']??''));
            if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end)||$end<=$start) json_response(['ok'=>false,'message'=>'Jam masuk/pulang tidak valid.'],422);
        }
        $replacement=trim((string)($data['replacement_date']??'')); if($replacement!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$replacement)) json_response(['ok'=>false,'message'=>'Tanggal pengganti tidak valid.'],422);
        $check=db()->prepare("SELECT id FROM employees WHERE id=? AND employment_status='active'");$check->execute([$employeeId]);if(!$check->fetchColumn())json_response(['ok'=>false,'message'=>'Karyawan aktif tidak ditemukan.'],404);
        $stmt=db()->prepare('INSERT INTO employee_schedule_exceptions (employee_id,work_date,schedule_type,work_start,work_end,replacement_date,reason,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE schedule_type=VALUES(schedule_type),work_start=VALUES(work_start),work_end=VALUES(work_end),replacement_date=VALUES(replacement_date),reason=VALUES(reason),updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP(6)');
        $stmt->execute([$employeeId,$date,$type,$start,$end,$replacement?:null,$reason,(int)$actor['id']]);
        audit((int)$actor['id'],'special_schedule_saved','employee_schedule',(string)$employeeId.'|'.$date,null,['schedule_type'=>$type,'work_start'=>$start,'work_end'=>$end,'replacement_date'=>$replacement?:null,'reason'=>$reason]);
        json_response(['ok'=>true,'message'=>'Jadwal khusus berhasil disimpan.']);
    }

    if (count($segments)===3 && $segments[0]==='shifts' && $segments[1]==='special-schedule' && $httpMethod==='DELETE') {
        $actor=sigap_require_permission('shifts'); sigap_ensure_shift_tables(); $id=(int)$segments[2];
        $stmt=db()->prepare('SELECT * FROM employee_schedule_exceptions WHERE id=?');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)json_response(['ok'=>false,'message'=>'Jadwal khusus tidak ditemukan.'],404);
        db()->prepare('DELETE FROM employee_schedule_exceptions WHERE id=?')->execute([$id]);
        audit((int)$actor['id'],'special_schedule_deleted','employee_schedule',(string)$id,$before);
        json_response(['ok'=>true,'message'=>'Jadwal khusus dihapus.']);
    }

    if ($path === '/shifts/assign-position' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $data = request_json();
        require_fields($data,['position_name','shift_id']);
        $position = clean_string($data['position_name'],120);
        $department = clean_string($data['department_name'] ?? '',120);
        $shiftId = (int)$data['shift_id'];
        $check = db()->prepare('SELECT id FROM work_shifts WHERE id=? AND is_active=1');
        $check->execute([$shiftId]);
        if (!$check->fetchColumn()) json_response(['ok'=>false,'error'=>'shift_not_found','message'=>'Shift aktif tidak ditemukan.'],404);
        $stmt = db()->prepare('INSERT INTO position_shift_settings (position_name,department_name,shift_id,updated_by_user_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP(6)');
        $stmt->execute([$position,$department,$shiftId,(int)$actor['id']]);
        audit((int)$actor['id'],'position_shift_assigned','position_shift',$position.'|'.$department,null,['shift_id'=>$shiftId]);
        json_response(['ok'=>true,'message'=>'Shift jabatan berhasil ditetapkan.']);
    }

    if ($path === '/shifts/unassign-position' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $data = request_json();
        require_fields($data,['position_name']);
        $position = clean_string($data['position_name'],120);
        $department = clean_string($data['department_name'] ?? '',120);
        db()->prepare('DELETE FROM position_shift_settings WHERE position_name=? AND department_name=?')->execute([$position,$department]);
        audit((int)$actor['id'],'position_shift_removed','position_shift',$position.'|'.$department);
        json_response(['ok'=>true,'message'=>'Pengaturan shift jabatan dihapus. Karyawan kembali memakai shift default kecuali memiliki override.']);
    }

    if ($path === '/shifts/assign-employee' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $data = request_json();
        require_fields($data,['employee_id','shift_id']);
        $employeeId = (int)$data['employee_id'];
        $shiftId = (int)$data['shift_id'];
        $employeeCheck = db()->prepare("SELECT id FROM employees WHERE id=? AND employment_status='active'");
        $employeeCheck->execute([$employeeId]);
        if (!$employeeCheck->fetchColumn()) json_response(['ok'=>false,'error'=>'employee_not_found','message'=>'Karyawan aktif tidak ditemukan.'],404);
        $shiftCheck = db()->prepare('SELECT id FROM work_shifts WHERE id=? AND is_active=1');
        $shiftCheck->execute([$shiftId]);
        if (!$shiftCheck->fetchColumn()) json_response(['ok'=>false,'error'=>'shift_not_found','message'=>'Shift aktif tidak ditemukan.'],404);
        $stmt = db()->prepare('INSERT INTO employee_shift_settings (employee_id,shift_id,updated_by_user_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP(6)');
        $stmt->execute([$employeeId,$shiftId,(int)$actor['id']]);
        audit((int)$actor['id'],'employee_shift_assigned','employee_shift',(string)$employeeId,null,['shift_id'=>$shiftId]);
        json_response(['ok'=>true,'message'=>'Shift khusus karyawan berhasil ditetapkan.']);
    }

    if ($path === '/shifts/unassign-employee' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_shift_tables();
        $data = request_json();
        require_fields($data,['employee_id']);
        $employeeId = (int)$data['employee_id'];
        db()->prepare('DELETE FROM employee_shift_settings WHERE employee_id=?')->execute([$employeeId]);
        audit((int)$actor['id'],'employee_shift_removed','employee_shift',(string)$employeeId);
        json_response(['ok'=>true,'message'=>'Override shift dihapus. Karyawan kembali mengikuti jadwal jabatan atau shift default.']);
    }

    // Impor data karyawan existing dari Excel
    if ($path === '/employees/import-preview' && $httpMethod === 'POST') {
        require_roles(['hr_admin','super_admin']);
        if (empty($_FILES['file'])) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'File tidak ditemukan.'],422);
        try {
            $raw = sigap_read_payroll_file($_FILES['file']['tmp_name'], $_FILES['file']['name']);
        } catch (RuntimeException $e) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>$e->getMessage()],422);
        }
        if (count($raw) < 2) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Template tidak berisi data karyawan.'],422);
        $norm = fn($x) => strtolower(trim(preg_replace('/\s+/',' ',(string)$x)));
        $headers = array_map($norm, $raw[0]);
        $aliases = ['nik'=>['nik'],'nama'=>['nama lengkap','nama'],'jabatan'=>['jabatan'],'departemen'=>['departemen','divisi'],'tanggal_masuk'=>['tanggal bergabung','tanggal masuk'],'kuota_cuti'=>['kuota cuti'],'username'=>['username'],'password'=>['password awal','password'],'role'=>['role']];
        $idx = [];
        foreach ($aliases as $k=>$names) { foreach ($names as $n) { $i=array_search($n,$headers,true); if ($i!==false) { $idx[$k]=$i; break; } } }
        foreach (['nik','nama','jabatan','departemen'] as $r) if (!isset($idx[$r])) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Kolom wajib tidak ditemukan: '.$r],422);
        $pdo = db();
        $nikCheck = $pdo->prepare('SELECT id FROM employees WHERE nik=? LIMIT 1');
        $userCheck = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $allowedRoles = ['employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
        $out = []; $seenNik = [];
        foreach (array_slice($raw,1) as $lineNo => $r) {
            if (!array_filter($r, fn($v)=>trim((string)$v)!=='')) continue;
            $get = fn($k) => trim((string)($r[$idx[$k] ?? -1] ?? ''));
            $nik = $get('nik'); $errors = []; $warnings = [];
            if ($nik === '') $errors[] = 'NIK wajib diisi';
            elseif (isset($seenNik[$nik])) $errors[] = 'NIK duplikat di file ini (baris '.$seenNik[$nik].')';
            else { $nikCheck->execute([$nik]); if ($nikCheck->fetchColumn()) $errors[] = 'NIK sudah terdaftar di sistem'; }
            if ($get('nama') === '') $errors[] = 'Nama wajib diisi';
            if ($get('jabatan') === '') $errors[] = 'Jabatan wajib diisi';
            if ($get('departemen') === '') $errors[] = 'Departemen wajib diisi';
            $joinedDate = $get('tanggal_masuk');
            if ($joinedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinedDate)) { $errors[] = 'Format tanggal bergabung harus YYYY-MM-DD'; }
            $username = $get('username'); $password = $get('password'); $role = $get('role') ?: 'employee';
            if ($username !== '' && $password === '') $errors[] = 'Password wajib diisi jika username diisi';
            if ($password !== '' && $username === '') $errors[] = 'Username wajib diisi jika password diisi';
            if ($username !== '') { $userCheck->execute([$username]); if ($userCheck->fetchColumn()) $errors[] = 'Username sudah digunakan'; }
            if ($password !== '' && (strlen($password) < 12 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/\d/',$password))) $errors[] = 'Password minimal 12 karakter dengan huruf besar, kecil, dan angka';
            if ($role !== '' && !in_array($role, $allowedRoles, true)) $errors[] = 'Role tidak valid';
            if ($nik !== '') $seenNik[$nik] = $lineNo + 2;
            $out[] = ['line'=>$lineNo+2,'nik'=>$nik,'full_name'=>$get('nama'),'position_name'=>$get('jabatan'),'department_name'=>$get('departemen'),'joined_date'=>$joinedDate ?: null,'annual_leave_quota'=>$get('kuota_cuti') ?: 12,'username'=>$username ?: null,'password'=>$password ?: null,'role'=>$username ? $role : null,'errors'=>$errors,'warnings'=>$warnings,'valid'=>!$errors];
        }
        $summary = ['total'=>count($out),'valid'=>count(array_filter($out,fn($r)=>$r['valid'])),'invalid'=>count(array_filter($out,fn($r)=>!$r['valid']))];
        json_response(['ok'=>true,'rows'=>$out,'summary'=>$summary]);
    }

    if ($path === '/employees/import-commit' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $data = request_json();
        require_fields($data, ['rows']);
        $rows = array_filter((array)$data['rows'], fn($r)=>!empty($r['valid']));
        if (!$rows) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tidak ada baris valid untuk diimpor.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $created = []; $batchErrors = [];
        $pdo->prepare('INSERT INTO employee_import_batches (imported_by_user_id,filename,total_rows) VALUES (?,?,?)')->execute([(int)$actor['id'], 'import_karyawan.xlsx', count($rows)]);
        $batchId = (int)$pdo->lastInsertId();
        foreach ($rows as $row) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO employees (nik,full_name,position_name,department_name,annual_leave_quota,joined_date) VALUES (?,?,?,?,?,?)');
                $stmt->execute([clean_string($row['nik'],40), clean_string($row['full_name'],160), clean_string($row['position_name'],120), clean_string($row['department_name'],120), (float)($row['annual_leave_quota'] ?? 12), $row['joined_date'] ?: null]);
                $employeeId = (int)$pdo->lastInsertId();
                if (!empty($row['username']) && !empty($row['password'])) {
                    $pdo->prepare('INSERT INTO users (employee_id,username,password_hash,role,status,password_changed_at) VALUES (?,?,?,?,\'active\',NOW(6))')
                        ->execute([$employeeId, clean_string($row['username'],100), password_hash((string)$row['password'], PASSWORD_DEFAULT), $row['role'] ?: 'employee']);
                }
                sigap_employee_position_history_open($pdo, $employeeId, clean_string($row['position_name'],120), clean_string($row['department_name'],120), $row['joined_date'] ?: (new DateTimeImmutable('now'))->format('Y-m-d'), 'pengangkatan_awal', (int)$actor['id']);
                $pdo->commit();
                $pdo->prepare('INSERT INTO employee_import_rows (batch_id,row_no,employee_id,raw_json,status) VALUES (?,?,?,?,\'success\')')->execute([$batchId, (int)$row['line'], $employeeId, json_encode($row, JSON_UNESCAPED_UNICODE)]);
                $created[] = ['line'=>$row['line'],'employee_id'=>$employeeId,'nik'=>$row['nik']];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pdo->prepare('INSERT INTO employee_import_rows (batch_id,row_no,raw_json,status,error_message) VALUES (?,?,?,\'failed\',?)')->execute([$batchId, (int)$row['line'], json_encode($row, JSON_UNESCAPED_UNICODE), $e->getMessage()]);
                $batchErrors[] = ['line'=>$row['line'],'message'=>'Gagal menyimpan baris ini.'];
            }
        }
        $pdo->prepare('UPDATE employee_import_batches SET success_rows=?,failed_rows=? WHERE id=?')->execute([count($created), count($batchErrors), $batchId]);
        audit((int)$actor['id'], 'employees_imported', 'employee_import_batch', (string)$batchId, null, ['created'=>count($created),'failed'=>count($batchErrors)]);
        json_response(['ok'=>true,'created'=>$created,'errors'=>$batchErrors,'message'=>count($created).' karyawan berhasil diimpor.'.($batchErrors?' '.count($batchErrors).' baris gagal.':'')]);
    }

    // Employees
    if ($path === '/employees' && $httpMethod === 'GET') {
        require_roles(['hr_admin','payroll_finance','super_admin']);
        $rows = db()->query('SELECT e.id,e.nik,e.full_name,e.position_name,e.department_name,e.annual_leave_quota,e.employment_status,e.joined_date,e.supervisor_employee_id,u.id AS user_id,u.username,u.role,u.status AS user_status FROM employees e LEFT JOIN users u ON u.employee_id=e.id ORDER BY FIELD(e.employment_status,\'active\',\'inactive\',\'terminated\'),e.full_name')->fetchAll();
        json_response(['ok' => true, 'data' => $rows]);
    }

    if ($path === '/employees' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $data = request_json();
        require_fields($data, ['nik','full_name','position_name','department_name','username','password','role']);
        $allowedRoles = ['employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
        if (!in_array($data['role'], $allowedRoles, true)) {
            json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Role tidak valid.'], 422);
        }
        $newPassword = (string)$data['password'];
        if (strlen($newPassword) < 12 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            json_response(['ok' => false, 'error' => 'weak_password', 'message' => 'Password minimal 12 karakter dan harus mengandung huruf besar, huruf kecil, serta angka.'], 422);
        }
        $pdo = db();
        sigap_ensure_employee_master_tables(); // DDL: harus di luar transaksi (CREATE TABLE auto-commit di MySQL/MariaDB)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO employees (nik,full_name,position_name,department_name,supervisor_employee_id,annual_leave_quota,joined_date) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([
                clean_string($data['nik'], 40), clean_string($data['full_name'], 160), clean_string($data['position_name'], 120), clean_string($data['department_name'], 120),
                !empty($data['supervisor_employee_id']) ? (int)$data['supervisor_employee_id'] : null,
                isset($data['annual_leave_quota']) ? decimal_value($data['annual_leave_quota']) : 12,
                !empty($data['joined_date']) ? $data['joined_date'] : null,
            ]);
            $employeeId = (int)$pdo->lastInsertId();
            $userStmt = $pdo->prepare('INSERT INTO users (employee_id,username,password_hash,role,status,password_changed_at) VALUES (?,?,?,?,\'active\',NOW(6))');
            $userStmt->execute([$employeeId, clean_string($data['username'],100), password_hash($newPassword, PASSWORD_DEFAULT), $data['role']]);
            $userId = (int)$pdo->lastInsertId();
            sigap_employee_position_history_open(
                $pdo, $employeeId, clean_string($data['position_name'],120), clean_string($data['department_name'],120),
                !empty($data['joined_date']) ? (string)$data['joined_date'] : (new DateTimeImmutable('now'))->format('Y-m-d'),
                'pengangkatan_awal', (int)$actor['id']
            );
            $pdo->commit();
            audit((int)$actor['id'], 'employee_created', 'employee', (string)$employeeId, null, ['nik' => $data['nik'], 'user_id' => $userId, 'role' => $data['role']]);
            json_response(['ok' => true, 'id' => $employeeId], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (str_contains($e->getMessage(), 'Duplicate')) {
                json_response(['ok' => false, 'error' => 'duplicate', 'message' => 'NIK atau username sudah digunakan.'], 409);
            }
            throw $e;
        }
    }

    if (count($segments) === 2 && $segments[0] === 'employees' && in_array($httpMethod, ['PUT','PATCH'], true)) {
        $actor = require_roles(['hr_admin','super_admin']);
        $employeeId = (int)$segments[1];
        $data = request_json();
        require_fields($data, ['nik','full_name','position_name','department_name','username','role','employment_status','user_status']);
        $allowedRoles = ['employee','supervisor','hr_admin','payroll_finance','owner','super_admin'];
        $allowedEmployment = ['active','inactive','terminated'];
        $allowedUserStatus = ['active','locked','disabled'];
        if (!in_array($data['role'], $allowedRoles, true) || !in_array($data['employment_status'], $allowedEmployment, true) || !in_array($data['user_status'], $allowedUserStatus, true)) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Role atau status tidak valid.'],422);
        }
        $pdo = db();
        sigap_ensure_employee_master_tables(); // DDL: harus di luar transaksi (CREATE TABLE auto-commit di MySQL/MariaDB)
        $pdo->beginTransaction();
        try {
            $beforeStmt = $pdo->prepare('SELECT e.*,u.id AS user_id,u.username,u.role,u.status AS user_status FROM employees e LEFT JOIN users u ON u.employee_id=e.id WHERE e.id=? FOR UPDATE');
            $beforeStmt->execute([$employeeId]);
            $before = $beforeStmt->fetch();
            if (!$before) {
                $pdo->rollBack();
                json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
            }
            $pdo->prepare('UPDATE employees SET nik=?,full_name=?,position_name=?,department_name=?,supervisor_employee_id=?,annual_leave_quota=?,joined_date=?,employment_status=? WHERE id=?')
                ->execute([
                    clean_string($data['nik'],40),clean_string($data['full_name'],160),clean_string($data['position_name'],120),clean_string($data['department_name'],120),
                    !empty($data['supervisor_employee_id'])?(int)$data['supervisor_employee_id']:null,
                    isset($data['annual_leave_quota'])?decimal_value($data['annual_leave_quota']):12,
                    !empty($data['joined_date'])?$data['joined_date']:null,
                    $data['employment_status'],$employeeId
                ]);
            if (!empty($before['user_id'])) {
                $params=[clean_string($data['username'],100),$data['role'],$data['user_status']];
                // Reset percobaan login gagal & lockout setiap kali HR/Super Admin mengedit user secara eksplisit.
                // Tanpa ini, akun yang terkunci karena percobaan login gagal (locked_until) tetap tidak bisa
                // login walau password sudah diganti admin, sampai timer lockout habis sendiri.
                $sql='UPDATE users SET username=?,role=?,status=?,failed_login_count=0,locked_until=NULL';
                if (!empty($data['password'])) {
                    $newPassword=(string)$data['password'];
                    if (strlen($newPassword)<12 || !preg_match('/[A-Z]/',$newPassword) || !preg_match('/[a-z]/',$newPassword) || !preg_match('/\d/',$newPassword)) {
                        $pdo->rollBack();
                        json_response(['ok'=>false,'error'=>'weak_password','message'=>'Password baru minimal 12 karakter dan mengandung huruf besar, huruf kecil, serta angka.'],422);
                    }
                    $sql.=',password_hash=?,password_changed_at=NOW(6)';
                    $params[]=password_hash($newPassword,PASSWORD_DEFAULT);
                }
                $sql.=' WHERE employee_id=?';
                $params[]=$employeeId;
                $pdo->prepare($sql)->execute($params);
            }
            if ($data['employment_status'] !== 'active') {
                $pdo->prepare("UPDATE employee_devices SET status='revoked',attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL,webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0 WHERE employee_id=?")->execute([$employeeId]);
            }
            sigap_employee_position_history_sync($pdo, $employeeId, clean_string($data['position_name'],120), clean_string($data['department_name'],120), (int)$actor['id']);
            $pdo->commit();
            audit((int)$actor['id'],'employee_updated','employee',(string)$employeeId,$before,$data);
            json_response(['ok'=>true,'message'=>'Data karyawan berhasil diperbarui.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (str_contains($e->getMessage(),'Duplicate')) json_response(['ok'=>false,'error'=>'duplicate','message'=>'NIK atau username sudah digunakan.'],409);
            throw $e;
        }
    }

    if (count($segments) === 2 && $segments[0] === 'employees' && $httpMethod === 'DELETE') {
        $actor = require_roles(['hr_admin','super_admin']);
        $employeeId = (int)$segments[1];
        if ((int)($actor['employee_id'] ?? 0) === $employeeId) {
            json_response(['ok'=>false,'error'=>'self_delete_denied','message'=>'Akun yang sedang digunakan tidak dapat dihapus.'],409);
        }
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT e.*,u.id AS user_id,u.username,u.role,u.status AS user_status FROM employees e LEFT JOIN users u ON u.employee_id=e.id WHERE e.id=? FOR UPDATE');
        $stmt->execute([$employeeId]);
        $before = $stmt->fetch();
        if (!$before) {
            $pdo->rollBack();
            json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
        }
        $pdo->prepare("UPDATE employees SET employment_status='inactive' WHERE id=?")->execute([$employeeId]);
        $pdo->prepare("UPDATE users SET status='disabled' WHERE employee_id=?")->execute([$employeeId]);
        $pdo->prepare("UPDATE employee_devices SET status='revoked',attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL,webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0 WHERE employee_id=?")->execute([$employeeId]);
        $pdo->commit();
        audit((int)$actor['id'],'employee_deleted_soft','employee',(string)$employeeId,$before,['employment_status'=>'inactive','user_status'=>'disabled']);
        json_response(['ok'=>true,'message'=>'Karyawan dihapus dari daftar aktif. Riwayat absensi dan payroll tetap disimpan untuk audit.']);
    }

    // ========== Database Karyawan Lengkap: Profil Saya (self-service) ==========

    if ($path === '/profile/me' && $httpMethod === 'GET') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        sigap_ensure_employee_master_tables();
        $bundle = sigap_profile_bundle(db(), (int)$user['employee_id'], true, (int)$user['id']);
        json_response(['ok'=>true,'data'=>$bundle]);
    }

    if ($path === '/profile/me/consent' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        sigap_ensure_employee_master_tables();
        $employeeId = (int)$user['employee_id'];
        $pdo = db();
        sigap_profile_get_or_create($pdo, $employeeId);
        $version = 'v1.0';
        $pdo->prepare('UPDATE employee_profiles SET consent_accepted_at=NOW(6), consent_version=? WHERE employee_id=?')->execute([$version, $employeeId]);
        $pdo->prepare('INSERT INTO employee_profile_consents (employee_id, consent_version, ip_address, user_agent) VALUES (?,?,?,?)')
            ->execute([$employeeId, $version, client_ip(), user_agent()]);
        audit((int)$user['id'], 'profile_consent_accepted', 'employee_profile', (string)$employeeId);
        json_response(['ok'=>true,'message'=>'Persetujuan pemrosesan data pribadi tersimpan.']);
    }

    if (count($segments) === 3 && $segments[0] === 'profile' && $segments[1] === 'me' && $httpMethod === 'PUT' && in_array($segments[2], ['identitas','alamat','kontak','administrasi','pendukung'], true)) {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $code = $segments[2];
        $employeeId = (int)$user['employee_id'];
        sigap_ensure_employee_master_tables();
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $data = request_json();
        $fields = sigap_profile_validate_section_fields($code, $data);
        sigap_profile_get_or_create($pdo, $employeeId);
        $warnings = [];
        $sets = []; $params = [];

        if ($code === 'identitas') {
            if (isset($fields['nickname'])) { $sets[]='nickname=?'; $params[]=clean_string($fields['nickname'],80); }
            if (isset($fields['nik_ktp']) && trim((string)$fields['nik_ktp']) !== '') {
                $nik = preg_replace('/\D/','',(string)$fields['nik_ktp']);
                if (strlen($nik) !== 16) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'NIK KTP harus 16 digit angka.'],422);
                $hash = sigap_pii_hash($nik);
                $dupStmt = $pdo->prepare('SELECT employee_id FROM employee_profiles WHERE nik_ktp_hash=? AND employee_id<>? LIMIT 1');
                $dupStmt->execute([$hash, $employeeId]);
                if ($dupStmt->fetchColumn()) json_response(['ok'=>false,'error'=>'duplicate_nik','message'=>'NIK KTP ini sudah terdaftar pada karyawan lain.'],409);
                $warn = sigap_nik_birth_date_warning($nik, isset($fields['birth_date']) ? (string)$fields['birth_date'] : null);
                if ($warn) $warnings[] = $warn;
                $sets[]='nik_ktp_encrypted=?'; $params[]=sigap_pii_encrypt($nik);
                $sets[]='nik_ktp_masked=?'; $params[]=sigap_mask_middle($nik,4,4);
                $sets[]='nik_ktp_hash=?'; $params[]=$hash;
            }
            if (isset($fields['kk_number']) && trim((string)$fields['kk_number']) !== '') {
                $kk = preg_replace('/\D/','',(string)$fields['kk_number']);
                if (strlen($kk) !== 16) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Nomor KK harus 16 digit angka.'],422);
                $sets[]='kk_number_encrypted=?'; $params[]=sigap_pii_encrypt($kk);
                $sets[]='kk_number_masked=?'; $params[]=sigap_mask_middle($kk,4,4);
            }
            foreach (['birth_place'=>160,'gender'=>1] as $f=>$max) { if(isset($fields[$f])){ $sets[]="$f=?"; $params[]=clean_string($fields[$f],$max); } }
            if (isset($fields['birth_date'])) { $sets[]='birth_date=?'; $params[]=$fields['birth_date']?:null; }
            foreach (['religion','blood_type','marital_status','family_card_role'] as $f) { if(isset($fields[$f])){ $sets[]="$f=?"; $params[]=$fields[$f]?:null; } }
            if (isset($fields['nationality'])) { $sets[]='nationality=?'; $params[]=clean_string($fields['nationality'],60); }
        } elseif ($code === 'alamat') {
            if (isset($fields['ktp_address'])) { $sets[]='ktp_address_json=?'; $params[]=json_encode($fields['ktp_address'],JSON_UNESCAPED_UNICODE); }
            if (isset($fields['domicile_same_as_ktp'])) { $sets[]='domicile_same_as_ktp=?'; $params[]=$fields['domicile_same_as_ktp']?1:0; }
            if (isset($fields['domicile_address'])) { $sets[]='domicile_address_json=?'; $params[]=json_encode($fields['domicile_address'],JSON_UNESCAPED_UNICODE); }
            if (isset($fields['residence_status'])) { $sets[]='residence_status=?'; $params[]=$fields['residence_status']?:null; }
        } elseif ($code === 'kontak') {
            $primary = isset($fields['phone_primary']) ? sigap_normalize_phone_id((string)$fields['phone_primary']) : null;
            $secondary = isset($fields['phone_secondary']) ? sigap_normalize_phone_id((string)$fields['phone_secondary']) : null;
            if ($primary !== null) {
                if ($primary === '') json_response(['ok'=>false,'error'=>'validation_failed','message'=>'No. HP utama wajib diisi.'],422);
                $sets[]='phone_primary=?'; $params[]=$primary;
            }
            if ($secondary !== null) {
                if ($secondary === '') json_response(['ok'=>false,'error'=>'validation_failed','message'=>'No. HP alternatif wajib diisi.'],422);
                if ($primary === null) {
                    $primaryStmt = $pdo->prepare('SELECT phone_primary FROM employee_profiles WHERE employee_id=?');
                    $primaryStmt->execute([$employeeId]);
                    $comparePrimary = (string)$primaryStmt->fetchColumn();
                } else {
                    $comparePrimary = $primary;
                }
                if ($secondary === $comparePrimary) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'No. HP alternatif tidak boleh sama dengan No. HP utama.'],422);
                $sets[]='phone_secondary=?'; $params[]=$secondary;
            }
            if (isset($fields['email_personal'])) { $sets[]='email_personal=?'; $params[]=clean_string($fields['email_personal'],160); }
            if (isset($fields['email_office'])) { $sets[]='email_office=?'; $params[]=clean_string($fields['email_office'],160); }
        } elseif ($code === 'administrasi') {
            if (isset($fields['npwp']) && trim((string)$fields['npwp']) !== '') {
                $npwp = preg_replace('/\D/','',(string)$fields['npwp']);
                $sets[]='npwp_encrypted=?'; $params[]=sigap_pii_encrypt($npwp);
                $sets[]='npwp_masked=?'; $params[]=sigap_mask_middle($npwp,2,4);
            }
            if (isset($fields['uses_nik_as_npwp'])) { $sets[]='uses_nik_as_npwp=?'; $params[]=$fields['uses_nik_as_npwp']?1:0; }
            if (isset($fields['ptkp_override'])) { $sets[]='ptkp_override=?'; $params[]=$fields['ptkp_override']?1:0; }
            if (isset($fields['ptkp_override_note'])) { $sets[]='ptkp_override_note=?'; $params[]=clean_string($fields['ptkp_override_note'],255); }
            foreach (['bpjs_health_no'=>30,'bpjs_health_faskes'=>160,'bpjs_tk_no'=>30,'bank_name'=>80,'bank_account_holder_name'=>160,'bank_account_holder_mismatch_note'=>255] as $f=>$max) {
                if (isset($fields[$f])) { $sets[]="$f=?"; $params[]=clean_string($fields[$f],$max); }
            }
            if (isset($fields['bank_account']) && trim((string)$fields['bank_account']) !== '') {
                $acc = preg_replace('/\D/','',(string)$fields['bank_account']);
                $sets[]='bank_account_encrypted=?'; $params[]=sigap_pii_encrypt($acc);
                $sets[]='bank_account_masked=?'; $params[]=sigap_mask_middle($acc,0,4);
            }
        } elseif ($code === 'pendukung') {
            foreach (['uniform_size'=>10,'shoe_size'=>10,'helmet_size'=>10] as $f=>$max) { if(isset($fields[$f])){ $sets[]="$f=?"; $params[]=clean_string($fields[$f],$max); } }
            if (isset($fields['medical_notes'])) { $sets[]='medical_notes_encrypted=?'; $params[]=sigap_pii_encrypt((string)$fields['medical_notes']); }
        }

        if ($sets) {
            $params[] = $employeeId;
            $pdo->prepare('UPDATE employee_profiles SET '.implode(',',$sets).' WHERE employee_id=?')->execute($params);
        }
        sigap_profile_mark_draft($pdo, $employeeId);
        sigap_profile_completion_recalculate($pdo, $employeeId);
        audit((int)$user['id'], 'profile_section_saved', 'employee_profile', (string)$employeeId, null, ['section'=>$code]);
        json_response(['ok'=>true,'message'=>'Draf tersimpan.','warnings'=>$warnings]);
    }

    // Kirim seluruh profil (apa pun yang sudah terisi) untuk diverifikasi HRD sekaligus --
    // tidak lagi per-bagian. Isi sebagian tidak masalah; HRD yang menilai & memutuskan.
    if ($path === '/profile/me/submit' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $pdo = db();
        sigap_ensure_employee_master_tables();
        $current = sigap_profile_status($pdo, $employeeId)['profile_status'];
        if (in_array($current, ['disetujui','menunggu_verifikasi'], true)) {
            json_response(['ok'=>false,'error'=>'profile_locked','message'=>'Profil sudah terverifikasi atau sedang menunggu verifikasi.'],409);
        }
        sigap_profile_get_or_create($pdo, $employeeId);
        $pdo->prepare("UPDATE employee_profiles SET profile_status='menunggu_verifikasi', profile_submitted_at=NOW(6), profile_reviewed_by_user_id=NULL, profile_reviewed_at=NULL, profile_review_note=NULL WHERE employee_id=?")->execute([$employeeId]);
        audit((int)$user['id'], 'profile_submitted', 'employee_profile', (string)$employeeId);
        json_response(['ok'=>true,'message'=>'Profil berhasil dikirim untuk verifikasi HRD.']);
    }

    // ---- Keluarga (family members) ----
    if ($path === '/profile/me/family' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $data = request_json();
        require_fields($data, ['member_type','full_name']);
        if (!in_array($data['member_type'], ['spouse','child','father','mother'], true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis anggota keluarga tidak valid.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        if ($data['member_type'] === 'child' && !empty($data['birth_date'])) {
            $empStmt = $pdo->prepare('SELECT birth_date FROM employee_profiles WHERE employee_id=?'); $empStmt->execute([$employeeId]);
            $empBirth = $empStmt->fetchColumn();
            if ($empBirth) {
                $minChildDate = (new DateTimeImmutable($empBirth))->modify('+15 years');
                if (new DateTimeImmutable((string)$data['birth_date']) < $minChildDate) {
                    json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tanggal lahir anak tidak wajar dibandingkan tanggal lahir karyawan.'],422);
                }
            }
        }
        $nik = !empty($data['nik']) ? preg_replace('/\D/','',(string)$data['nik']) : '';
        $pdo->prepare('INSERT INTO employee_family_members (employee_id,member_type,full_name,nik_encrypted,nik_masked,birth_place,birth_date,gender,marriage_date,occupation,phone,education_level,is_dependent,bpjs_status,is_alive,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $employeeId, $data['member_type'], clean_string($data['full_name'],160),
                $nik !== '' ? sigap_pii_encrypt($nik) : null, $nik !== '' ? sigap_mask_middle($nik,4,4) : null,
                clean_string($data['birth_place'] ?? '',100) ?: null, $data['birth_date'] ?? null, $data['gender'] ?? null,
                $data['marriage_date'] ?? null, clean_string($data['occupation'] ?? '',120) ?: null, isset($data['phone']) ? sigap_normalize_phone_id((string)$data['phone']) : null,
                clean_string($data['education_level'] ?? '',40) ?: null, !empty($data['is_dependent']) ? 1 : 0, clean_string($data['bpjs_status'] ?? '',40) ?: null,
                array_key_exists('is_alive',$data) ? (int)!!$data['is_alive'] : 1, array_key_exists('is_active',$data) ? (int)!!$data['is_active'] : 1,
            ]);
        $id = (int)$pdo->lastInsertId();
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_family_added', 'employee_family_member', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Data keluarga tersimpan.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'me' && $segments[2] === 'family' && in_array($httpMethod, ['PUT','DELETE'], true)) {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id']; $id = (int)$segments[3];
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $own = $pdo->prepare('SELECT id FROM employee_family_members WHERE id=? AND employee_id=?'); $own->execute([$id,$employeeId]);
        if (!$own->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Data tidak ditemukan.'],404);
        if ($httpMethod === 'DELETE') {
            $pdo->prepare('DELETE FROM employee_family_members WHERE id=?')->execute([$id]);
            sigap_profile_mark_draft($pdo, $employeeId);
            audit((int)$user['id'], 'profile_family_deleted', 'employee_family_member', (string)$id);
            json_response(['ok'=>true,'message'=>'Data keluarga dihapus.']);
        }
        $data = request_json();
        $nik = array_key_exists('nik',$data) && $data['nik'] !== '' ? preg_replace('/\D/','',(string)$data['nik']) : null;
        $pdo->prepare('UPDATE employee_family_members SET full_name=COALESCE(?,full_name),birth_place=?,birth_date=?,gender=?,marriage_date=?,occupation=?,phone=?,education_level=?,is_dependent=?,bpjs_status=?,is_alive=?,is_active=? WHERE id=?')
            ->execute([
                isset($data['full_name']) ? clean_string($data['full_name'],160) : null,
                clean_string($data['birth_place'] ?? '',100) ?: null, $data['birth_date'] ?? null, $data['gender'] ?? null,
                $data['marriage_date'] ?? null, clean_string($data['occupation'] ?? '',120) ?: null, isset($data['phone']) ? sigap_normalize_phone_id((string)$data['phone']) : null,
                clean_string($data['education_level'] ?? '',40) ?: null, !empty($data['is_dependent']) ? 1 : 0, clean_string($data['bpjs_status'] ?? '',40) ?: null,
                array_key_exists('is_alive',$data) ? (int)!!$data['is_alive'] : 1, array_key_exists('is_active',$data) ? (int)!!$data['is_active'] : 1,
                $id,
            ]);
        if ($nik !== null) {
            $pdo->prepare('UPDATE employee_family_members SET nik_encrypted=?, nik_masked=? WHERE id=?')->execute([sigap_pii_encrypt($nik), sigap_mask_middle($nik,4,4), $id]);
        }
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_family_updated', 'employee_family_member', (string)$id);
        json_response(['ok'=>true,'message'=>'Data keluarga diperbarui.']);
    }

    // ---- Kontak darurat ----
    if ($path === '/profile/me/emergency-contacts' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $data = request_json();
        require_fields($data, ['full_name','relationship','phone']);
        $phone = sigap_normalize_phone_id((string)$data['phone']);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $ownPhones = $pdo->prepare('SELECT phone_primary, phone_secondary FROM employee_profiles WHERE employee_id=?'); $ownPhones->execute([$employeeId]);
        $own = $ownPhones->fetch();
        if ($own && ($phone === $own['phone_primary'] || $phone === $own['phone_secondary'])) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Nomor kontak darurat tidak boleh sama dengan nomor HP Anda sendiri.'],422);
        }
        $pdo->prepare('INSERT INTO employee_emergency_contacts (employee_id,full_name,relationship,phone,address,sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$employeeId, clean_string($data['full_name'],160), clean_string($data['relationship'],60), $phone, clean_string($data['address'] ?? '',255) ?: null, (int)($data['sort_order'] ?? 0)]);
        $id = (int)$pdo->lastInsertId();
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_emergency_contact_added', 'employee_emergency_contact', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Kontak darurat tersimpan.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'me' && $segments[2] === 'emergency-contacts' && in_array($httpMethod, ['PUT','DELETE'], true)) {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id']; $id = (int)$segments[3];
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $own = $pdo->prepare('SELECT id FROM employee_emergency_contacts WHERE id=? AND employee_id=?'); $own->execute([$id,$employeeId]);
        if (!$own->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Data tidak ditemukan.'],404);
        if ($httpMethod === 'DELETE') {
            $pdo->prepare('DELETE FROM employee_emergency_contacts WHERE id=?')->execute([$id]);
            sigap_profile_mark_draft($pdo, $employeeId);
            audit((int)$user['id'], 'profile_emergency_contact_deleted', 'employee_emergency_contact', (string)$id);
            json_response(['ok'=>true,'message'=>'Kontak darurat dihapus.']);
        }
        $data = request_json();
        require_fields($data, ['full_name','relationship','phone']);
        $pdo->prepare('UPDATE employee_emergency_contacts SET full_name=?,relationship=?,phone=?,address=?,sort_order=? WHERE id=?')
            ->execute([clean_string($data['full_name'],160), clean_string($data['relationship'],60), sigap_normalize_phone_id((string)$data['phone']), clean_string($data['address'] ?? '',255) ?: null, (int)($data['sort_order'] ?? 0), $id]);
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_emergency_contact_updated', 'employee_emergency_contact', (string)$id);
        json_response(['ok'=>true,'message'=>'Kontak darurat diperbarui.']);
    }

    // ---- Pendidikan ----
    if ($path === '/profile/me/education' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $data = request_json();
        require_fields($data, ['education_level','institution_name']);
        $levels = ['SD','SMP','SMA_SMK','D1','D2','D3','D4','S1','S2','S3'];
        if (!in_array($data['education_level'], $levels, true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenjang pendidikan tidak valid.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $pdo->prepare('INSERT INTO employee_education_history (employee_id,education_level,institution_name,major,graduation_year) VALUES (?,?,?,?,?)')
            ->execute([$employeeId, $data['education_level'], clean_string($data['institution_name'],160), clean_string($data['major'] ?? '',120) ?: null, !empty($data['graduation_year']) ? (int)$data['graduation_year'] : null]);
        $id = (int)$pdo->lastInsertId();
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_education_added', 'employee_education_history', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Riwayat pendidikan tersimpan.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'me' && $segments[2] === 'education' && $httpMethod === 'DELETE') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id']; $id = (int)$segments[3];
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $own = $pdo->prepare('SELECT id FROM employee_education_history WHERE id=? AND employee_id=?'); $own->execute([$id,$employeeId]);
        if (!$own->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Data tidak ditemukan.'],404);
        $pdo->prepare('DELETE FROM employee_education_history WHERE id=?')->execute([$id]);
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_education_deleted', 'employee_education_history', (string)$id);
        json_response(['ok'=>true,'message'=>'Riwayat pendidikan dihapus.']);
    }

    // ---- Sertifikasi / SIM ----
    if ($path === '/profile/me/certifications' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $data = request_json();
        require_fields($data, ['cert_type','cert_name']);
        $types = ['skk_ska','k3_umum','ahli_k3','operator_alat_berat','sim_a','sim_b1','sim_b2','sim_c','sim_d','lainnya'];
        if (!in_array($data['cert_type'], $types, true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis sertifikasi tidak valid.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $pdo->prepare('INSERT INTO employee_certifications (employee_id,cert_type,cert_name,cert_number,issuer,issued_date,expiry_date) VALUES (?,?,?,?,?,?,?)')
            ->execute([$employeeId, $data['cert_type'], clean_string($data['cert_name'],160), clean_string($data['cert_number'] ?? '',80) ?: null, clean_string($data['issuer'] ?? '',160) ?: null, $data['issued_date'] ?? null, $data['expiry_date'] ?? null]);
        $id = (int)$pdo->lastInsertId();
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_certification_added', 'employee_certification', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Sertifikasi tersimpan.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'me' && $segments[2] === 'certifications' && $httpMethod === 'DELETE') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id']; $id = (int)$segments[3];
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $own = $pdo->prepare('SELECT id FROM employee_certifications WHERE id=? AND employee_id=?'); $own->execute([$id,$employeeId]);
        if (!$own->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Data tidak ditemukan.'],404);
        $pdo->prepare('DELETE FROM employee_certifications WHERE id=?')->execute([$id]);
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_certification_deleted', 'employee_certification', (string)$id);
        json_response(['ok'=>true,'message'=>'Sertifikasi dihapus.']);
    }

    // ---- Upload dokumen ----
    if ($path === '/profile/me/documents' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $docType = clean_string($_POST['document_type'] ?? '',40);
        $allowedTypes = ['ktp','kk','npwp','ijazah','buku_nikah','akta_lahir_anak','kartu_bpjs','buku_tabungan','sertifikat_kompetensi','sim','lainnya'];
        if (!in_array($docType, $allowedTypes, true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis dokumen tidak valid.'],422);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'File tidak ditemukan atau gagal diupload.'],422);
        $file = $_FILES['file'];
        if ($file['size'] > 2 * 1024 * 1024) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Ukuran file maksimal 2 MB.'],422);
        $allowedMime = ['application/pdf','image/jpeg','image/png'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($mime, $allowedMime, true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Format file harus PDF, JPG, atau PNG.'],422);
        sigap_ensure_employee_master_tables();
        $pdoGuard = db();
        sigap_profile_require_editable($pdoGuard, $employeeId);
        $dir = sigap_employee_documents_directory() . '/' . $employeeId;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            json_response(['ok'=>false,'error'=>'server_error','message'=>'Gagal menyiapkan folder penyimpanan dokumen.'],500);
        }
        $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg');
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            json_response(['ok'=>false,'error'=>'server_error','message'=>'Gagal menyimpan file.'],500);
        }
        $familyId = !empty($_POST['related_family_member_id']) ? (int)$_POST['related_family_member_id'] : null;
        $pdo = db();
        $pdo->prepare('INSERT INTO employee_documents (employee_id,document_type,related_family_member_id,file_path,original_filename,mime_type,file_size_bytes,status) VALUES (?,?,?,?,?,?,?,\'menunggu_verifikasi\')')
            ->execute([$employeeId, $docType, $familyId, $employeeId.'/'.$storedName, clean_string($file['name'],255), $mime, (int)$file['size']]);
        $id = (int)$pdo->lastInsertId();
        sigap_profile_mark_draft($pdo, $employeeId);
        audit((int)$user['id'], 'profile_document_uploaded', 'employee_document', (string)$id, null, ['document_type'=>$docType]);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Dokumen berhasil diupload, menunggu verifikasi HRD.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'me' && $segments[2] === 'documents' && $httpMethod === 'DELETE') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id']; $id = (int)$segments[3];
        $pdo = db();
        sigap_profile_require_editable($pdo, $employeeId);
        $doc = $pdo->prepare('SELECT * FROM employee_documents WHERE id=? AND employee_id=?'); $doc->execute([$id,$employeeId]);
        $row = $doc->fetch();
        if (!$row) json_response(['ok'=>false,'error'=>'not_found','message'=>'Dokumen tidak ditemukan.'],404);
        if ($row['status'] === 'valid') json_response(['ok'=>false,'error'=>'document_locked','message'=>'Dokumen yang sudah tervalidasi tidak dapat dihapus sendiri.'],409);
        @unlink(sigap_employee_documents_directory() . '/' . $row['file_path']);
        $pdo->prepare('DELETE FROM employee_documents WHERE id=?')->execute([$id]);
        audit((int)$user['id'], 'profile_document_deleted', 'employee_document', (string)$id);
        json_response(['ok'=>true,'message'=>'Dokumen dihapus.']);
    }

    // ---- Ajukan Perubahan Data (setelah bagian terkunci/disetujui) ----
    if ($path === '/profile/me/change-requests' && $httpMethod === 'POST') {
        $user = sigap_require_permission('employee_profile_self');
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $employeeId = (int)$user['employee_id'];
        $data = request_json();
        require_fields($data, ['section_code','after_json']);
        if (!in_array($data['section_code'], sigap_profile_section_codes(), true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Bagian tidak valid.'],422);
        $events = ['menikah','punya_anak','pindah_alamat','ganti_hp','ganti_rekening','lainnya'];
        $trigger = in_array($data['trigger_event'] ?? '', $events, true) ? $data['trigger_event'] : 'lainnya';
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $pdo->prepare('INSERT INTO employee_profile_change_requests (employee_id,section_code,trigger_event,before_json,after_json) VALUES (?,?,?,?,?)')
            ->execute([$employeeId, $data['section_code'], $trigger, json_encode($data['before_json'] ?? null, JSON_UNESCAPED_UNICODE), json_encode($data['after_json'], JSON_UNESCAPED_UNICODE)]);
        $id = (int)$pdo->lastInsertId();
        audit((int)$user['id'], 'profile_change_request_created', 'employee_profile_change_request', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Pengajuan perubahan data terkirim, menunggu verifikasi HRD.'],201);
    }

    // ========== Database Karyawan Lengkap: Verifikasi & Manajemen HRD ==========

    if ($path === '/profile/queue' && $httpMethod === 'GET') {
        $actor = sigap_require_permission('employee_profile_manage');
        sigap_ensure_employee_master_tables();
        $rows = db()->query("SELECT p.employee_id,p.profile_submitted_at AS submitted_at,p.profile_completion_percent,e.full_name,e.nik,e.position_name,e.department_name FROM employee_profiles p JOIN employees e ON e.id=p.employee_id WHERE p.profile_status='menunggu_verifikasi' ORDER BY p.profile_submitted_at")->fetchAll();
        $changeRows = db()->query("SELECT c.id,c.employee_id,c.section_code,c.trigger_event,c.requested_at,e.full_name,e.nik FROM employee_profile_change_requests c JOIN employees e ON e.id=c.employee_id WHERE c.status='menunggu_verifikasi' ORDER BY c.requested_at")->fetchAll();
        $docRows = db()->query("SELECT d.id,d.employee_id,d.document_type,d.uploaded_at,e.full_name,e.nik FROM employee_documents d JOIN employees e ON e.id=d.employee_id WHERE d.status='menunggu_verifikasi' ORDER BY d.uploaded_at")->fetchAll();
        json_response(['ok'=>true,'data'=>['profiles'=>$rows,'change_requests'=>$changeRows,'documents'=>$docRows]]);
    }

    if ($path === '/profile/overview' && $httpMethod === 'GET') {
        $actor = sigap_require_permission('employee_profile_manage');
        sigap_ensure_employee_master_tables();
        $rows = db()->query("SELECT e.id,e.nik,e.full_name,e.position_name,e.department_name,COALESCE(p.profile_completion_percent,0) AS profile_completion_percent FROM employees e LEFT JOIN employee_profiles p ON p.employee_id=e.id WHERE e.employment_status='active' ORDER BY e.full_name")->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
    }

    // Lihat/download dokumen (KTP/KK/ijazah/dll) — HRD, atau karyawan pemilik dokumen sendiri.
    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'documents' && is_numeric($segments[2]) && $segments[3] === 'file' && $httpMethod === 'GET') {
        $actor = require_auth();
        $docId = (int)$segments[2];
        $docStmt = db()->prepare('SELECT * FROM employee_documents WHERE id=? LIMIT 1');
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch();
        if (!$doc) json_response(['ok'=>false,'error'=>'not_found','message'=>'Dokumen tidak ditemukan.'],404);
        $perms = sigap_get_user_permissions($actor);
        $isOwner = $actor['employee_id'] && (int)$actor['employee_id'] === (int)$doc['employee_id'];
        $isHr = in_array('employee_profile_manage', $perms, true);
        if (!$isOwner && !$isHr) json_response(['ok'=>false,'error'=>'forbidden','message'=>'Anda tidak memiliki hak akses ke dokumen ini.'],403);
        $fullPath = sigap_employee_documents_directory() . '/' . $doc['file_path'];
        if (!is_file($fullPath)) json_response(['ok'=>false,'error'=>'not_found','message'=>'File dokumen tidak ditemukan di server.'],404);
        if ($isHr) sigap_log_pii_access((int)$doc['employee_id'], 'document:'.$doc['document_type'], (int)$actor['id']);
        header('Content-Type: '.$doc['mime_type']);
        header('Content-Disposition: inline; filename="'.basename((string)$doc['original_filename']).'"');
        header('Content-Length: '.filesize($fullPath));
        header('Cache-Control: private, max-age=0, no-store');
        readfile($fullPath);
        exit;
    }

    // Export Excel data lengkap karyawan — HRD, wajib isi alasan (tercatat di audit log).
    if ($path === '/profile/export' && $httpMethod === 'GET') {
        $actor = sigap_require_permission('employee_profile_manage');
        $reason = trim((string)($_GET['reason'] ?? ''));
        if ($reason === '') json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Alasan ekspor wajib diisi.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $employees = $pdo->query("SELECT e.* FROM employees e WHERE e.employment_status='active' ORDER BY e.full_name")->fetchAll();
        $rows = [];
        foreach ($employees as $emp) {
            $employeeId = (int)$emp['id'];
            $profile = sigap_profile_get_or_create($pdo, $employeeId);
            $employment = sigap_employment_details_get_or_create($pdo, $employeeId);
            $spouseStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_family_members WHERE employee_id=? AND member_type='spouse' AND is_active=1"); $spouseStmt->execute([$employeeId]);
            $childStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_family_members WHERE employee_id=? AND member_type='child'"); $childStmt->execute([$employeeId]);
            foreach (['nik_ktp_encrypted','kk_number_encrypted','npwp_encrypted','bank_account_encrypted'] as $field) {
                if (!empty($profile[$field])) sigap_log_pii_access($employeeId, str_replace('_encrypted','',$field), (int)$actor['id']);
            }
            $rows[] = [
                $emp['nik'], $emp['full_name'], $emp['position_name'], $emp['department_name'], $emp['employment_status'],
                $employment['nip_internal'] ?: '', $employment['contract_type'] ?: '', $employment['contract_end_date'] ?: '',
                $emp['joined_date'] ?: '', $profile['profile_completion_percent'] ?: 0,
                sigap_pii_decrypt($profile['nik_ktp_encrypted']) ?: '', sigap_pii_decrypt($profile['kk_number_encrypted']) ?: '',
                $profile['birth_place'] ?: '', $profile['birth_date'] ?: '', $profile['gender'] ?: '', $profile['religion'] ?: '',
                $profile['marital_status'] ?: '', (int)$spouseStmt->fetchColumn(), (int)$childStmt->fetchColumn(),
                $profile['phone_primary'] ?: '', $profile['phone_secondary'] ?: '', $profile['email_personal'] ?: '',
                sigap_pii_decrypt($profile['npwp_encrypted']) ?: '', $profile['ptkp_status'] ?: '',
                $profile['bank_name'] ?: '', sigap_pii_decrypt($profile['bank_account_encrypted']) ?: '', $profile['bank_account_holder_name'] ?: '',
            ];
        }
        audit((int)$actor['id'], 'employee_master_data_exported', 'employee_profile', null, null, ['reason'=>$reason, 'total_rows'=>count($rows)]);
        json_response(['ok'=>true,'headers'=>['NIK','Nama','Jabatan','Departemen','Status Karyawan','NIP Internal','Jenis Kontrak','Akhir Kontrak','Tanggal Masuk','Kelengkapan %','NIK KTP','No KK','Tempat Lahir','Tanggal Lahir','Jenis Kelamin','Agama','Status Kawin','Jml Pasangan Aktif','Jml Anak','No HP Utama','No HP Alternatif','Email Pribadi','NPWP','Status PTKP','Bank','No Rekening','Nama Pemilik Rekening'],'rows'=>$rows]);
    }

    if (count($segments) === 2 && $segments[0] === 'profile' && is_numeric($segments[1]) && $httpMethod === 'GET') {
        $actor = require_auth();
        $employeeId = (int)$segments[1];
        $allowFull = sigap_get_user_permissions($actor);
        $canManage = in_array('employee_profile_manage', $allowFull, true);
        $canPayroll = in_array('employee_profile_payroll', $allowFull, true);
        $canTeam = in_array('employee_profile_team', $allowFull, true);
        if (!$canManage && !$canPayroll && !$canTeam) json_response(['ok'=>false,'error'=>'forbidden','message'=>'Anda tidak memiliki hak akses.'],403);
        sigap_ensure_employee_master_tables();
        $bundle = sigap_profile_bundle(db(), $employeeId, $canManage || $canPayroll, (int)$actor['id']);
        if (!$canManage && $canTeam && !$canPayroll) {
            $bundle = ['employee_id'=>$employeeId, 'phone_primary'=>$bundle['phone_primary'],'phone_secondary'=>$bundle['phone_secondary'],'emergency_contacts'=>$bundle['emergency_contacts'],'position_history'=>$bundle['position_history']];
        } elseif (!$canManage && $canPayroll) {
            $bundle = ['employee_id'=>$employeeId,'npwp'=>$bundle['npwp'],'ptkp_status'=>$bundle['ptkp_status'],'bpjs_health_no'=>$bundle['bpjs_health_no'],'bpjs_tk_no'=>$bundle['bpjs_tk_no'],'bank_name'=>$bundle['bank_name'],'bank_account'=>$bundle['bank_account'],'bank_account_holder_name'=>$bundle['bank_account_holder_name']];
        }
        json_response(['ok'=>true,'data'=>$bundle]);
    }

    if (count($segments) === 3 && $segments[0] === 'profile' && is_numeric($segments[1]) && $segments[2] === 'employment' && $httpMethod === 'PUT') {
        $actor = sigap_require_permission('employee_profile_manage');
        $employeeId = (int)$segments[1];
        $empCheck = db()->prepare('SELECT id, joined_date FROM employees WHERE id=? LIMIT 1'); $empCheck->execute([$employeeId]);
        $emp = $empCheck->fetch();
        if (!$emp) json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
        $data = request_json();
        require_fields($data, ['contract_type']);
        if (!in_array($data['contract_type'], ['pkwt','pkwtt','harian','magang'], true)) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis kontrak tidak valid.'],422);
        }
        if ($data['contract_type'] === 'pkwt' && empty($data['contract_end_date'])) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tanggal akhir kontrak wajib diisi untuk status PKWT.'],422);
        }
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $current = sigap_employment_details_get_or_create($pdo, $employeeId);
        $nip = $current['nip_internal'];
        if (!$nip) {
            $year = $emp['joined_date'] ? substr($emp['joined_date'],0,4) : (new DateTimeImmutable('now'))->format('Y');
            $nip = 'MPA'.$year.'-'.str_pad((string)$employeeId, 5, '0', STR_PAD_LEFT);
        }
        $pdo->prepare('UPDATE employee_employment_details SET nip_internal=?,contract_type=?,contract_end_date=?,resign_date=?,resign_reason=?,updated_by_user_id=? WHERE employee_id=?')
            ->execute([
                $nip, $data['contract_type'], $data['contract_type']==='pkwt' ? $data['contract_end_date'] : null,
                $data['resign_date'] ?? null, clean_string($data['resign_reason'] ?? '',255) ?: null, (int)$actor['id'], $employeeId,
            ]);
        audit((int)$actor['id'], 'employment_details_updated', 'employee_employment_details', (string)$employeeId, $current, $data);
        json_response(['ok'=>true,'message'=>'Data kepegawaian tersimpan.','nip_internal'=>$nip]);
    }

    if (count($segments) === 3 && $segments[0] === 'profile' && is_numeric($segments[1]) && $segments[2] === 'training' && $httpMethod === 'POST') {
        $actor = sigap_require_permission('employee_profile_manage');
        $employeeId = (int)$segments[1];
        $empCheck = db()->prepare('SELECT id FROM employees WHERE id=? LIMIT 1'); $empCheck->execute([$employeeId]);
        if (!$empCheck->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
        $data = request_json();
        require_fields($data, ['training_name']);
        if (!empty($data['category']) && !in_array($data['category'], ['internal','eksternal'], true)) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Kategori training tidak valid.'],422);
        }
        if (!empty($data['status']) && !in_array($data['status'], ['selesai','berjalan','dibatalkan'], true)) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Status training tidak valid.'],422);
        }
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $pdo->prepare('INSERT INTO employee_training_history (employee_id,training_name,provider,category,start_date,end_date,duration_hours,location,result_note,status,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $employeeId, clean_string($data['training_name'],200), clean_string($data['provider'] ?? '',160) ?: null,
                $data['category'] ?? 'internal', $data['start_date'] ?? null, $data['end_date'] ?? null,
                isset($data['duration_hours']) && $data['duration_hours'] !== '' ? (float)$data['duration_hours'] : null,
                clean_string($data['location'] ?? '',160) ?: null, clean_string($data['result_note'] ?? '',255) ?: null,
                $data['status'] ?? 'selesai', (int)$actor['id'],
            ]);
        $id = (int)$pdo->lastInsertId();
        audit((int)$actor['id'], 'training_added', 'employee_training_history', (string)$id);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Riwayat training tersimpan.'],201);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && is_numeric($segments[1]) && $segments[2] === 'training' && $httpMethod === 'DELETE') {
        $actor = sigap_require_permission('employee_profile_manage');
        $employeeId = (int)$segments[1]; $id = (int)$segments[3];
        $pdo = db();
        $own = $pdo->prepare('SELECT id FROM employee_training_history WHERE id=? AND employee_id=?'); $own->execute([$id,$employeeId]);
        if (!$own->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Data training tidak ditemukan.'],404);
        $pdo->prepare('DELETE FROM employee_training_history WHERE id=?')->execute([$id]);
        audit((int)$actor['id'], 'training_deleted', 'employee_training_history', (string)$id);
        json_response(['ok'=>true,'message'=>'Riwayat training dihapus.']);
    }

    if (count($segments) === 3 && $segments[0] === 'profile' && is_numeric($segments[1]) && $segments[2] === 'remind' && $httpMethod === 'POST') {
        $actor = sigap_require_permission('employee_profile_manage');
        $employeeId = (int)$segments[1];
        $data = request_json();
        $channel = in_array($data['channel'] ?? '', ['in_app','whatsapp'], true) ? $data['channel'] : 'in_app';
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $empStmt = $pdo->prepare('SELECT e.full_name, p.phone_primary FROM employees e LEFT JOIN employee_profiles p ON p.employee_id=e.id WHERE e.id=? LIMIT 1');
        $empStmt->execute([$employeeId]);
        $emp = $empStmt->fetch();
        if (!$emp) json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
        $pdo->prepare('INSERT INTO employee_completion_reminders (employee_id,sent_by_user_id,channel) VALUES (?,?,?)')->execute([$employeeId, (int)$actor['id'], $channel]);
        audit((int)$actor['id'], 'profile_reminder_sent', 'employee_profile', (string)$employeeId, null, ['channel'=>$channel]);
        json_response(['ok'=>true,'message'=>'Reminder tercatat.','phone_primary'=>$emp['phone_primary'],'full_name'=>$emp['full_name']]);
    }

    if ($path === '/profile/dashboard' && $httpMethod === 'GET') {
        $actor = sigap_require_permission('employee_profile_manage');
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $today = (new DateTimeImmutable('now'))->format('Y-m-d');
        $in60 = (new DateTimeImmutable('+60 days'))->format('Y-m-d');

        $contracts = $pdo->prepare("SELECT e.id,e.full_name,e.nik,e.position_name,d.contract_end_date FROM employee_employment_details d JOIN employees e ON e.id=d.employee_id WHERE d.contract_type='pkwt' AND d.contract_end_date IS NOT NULL AND d.contract_end_date BETWEEN ? AND ? AND e.employment_status='active' ORDER BY d.contract_end_date");
        $contracts->execute([$today, $in60]);
        $contractAlerts = $contracts->fetchAll();

        $certs = $pdo->prepare("SELECT c.id,c.cert_name,c.cert_type,c.expiry_date,e.id AS employee_id,e.full_name,e.nik FROM employee_certifications c JOIN employees e ON e.id=c.employee_id WHERE c.expiry_date IS NOT NULL AND c.expiry_date BETWEEN ? AND ? AND e.employment_status='active' ORDER BY c.expiry_date");
        $certs->execute([$today, $in60]);
        $certAlerts = $certs->fetchAll();

        $birthdayMonth = (new DateTimeImmutable('now'))->format('m');
        $birthdays = $pdo->prepare("SELECT e.id,e.full_name,e.nik,p.birth_date FROM employee_profiles p JOIN employees e ON e.id=p.employee_id WHERE p.birth_date IS NOT NULL AND MONTH(p.birth_date)=? AND e.employment_status='active' ORDER BY DAY(p.birth_date)");
        $birthdays->execute([$birthdayMonth]);
        $birthdayAlerts = $birthdays->fetchAll();

        $incomplete = $pdo->query("SELECT e.id,e.full_name,e.nik,COALESCE(p.profile_completion_percent,0) AS pct FROM employees e LEFT JOIN employee_profiles p ON p.employee_id=e.id WHERE e.employment_status='active' AND COALESCE(p.profile_completion_percent,0)<100 ORDER BY pct, e.full_name")->fetchAll();

        $statusComposition = $pdo->query("SELECT employment_status, COUNT(*) AS total FROM employees GROUP BY employment_status")->fetchAll();
        $maritalComposition = $pdo->query("SELECT COALESCE(marital_status,'belum_diisi') AS marital_status, COUNT(*) AS total FROM employee_profiles GROUP BY marital_status")->fetchAll();
        $contractComposition = $pdo->query("SELECT contract_type, COUNT(*) AS total FROM employee_employment_details GROUP BY contract_type")->fetchAll();
        $dependentsTotal = (int)$pdo->query("SELECT COUNT(*) FROM employee_family_members WHERE member_type='child' AND is_dependent=1")->fetchColumn();
        $avgTenureRow = $pdo->query("SELECT AVG(DATEDIFF(CURDATE(), joined_date))/365 AS avg_years FROM employees WHERE employment_status='active' AND joined_date IS NOT NULL")->fetch();
        $domicileTop = $pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(ktp_address_json,'$.kota')) AS kota, COUNT(*) AS total FROM employee_profiles WHERE ktp_address_json IS NOT NULL GROUP BY kota HAVING kota IS NOT NULL AND kota<>'' ORDER BY total DESC LIMIT 10")->fetchAll();

        json_response(['ok'=>true,'data'=>[
            'alerts'=>['contracts'=>$contractAlerts,'certifications'=>$certAlerts,'birthdays'=>$birthdayAlerts,'incomplete_profiles'=>$incomplete],
            'stats'=>[
                'employment_status'=>$statusComposition,'marital_status'=>$maritalComposition,'contract_type'=>$contractComposition,
                'dependents_total'=>$dependentsTotal,'average_tenure_years'=>round((float)($avgTenureRow['avg_years'] ?? 0),1),'domicile_top'=>$domicileTop,
            ],
        ]]);
    }

    if (count($segments) === 3 && $segments[0] === 'profile' && is_numeric($segments[1]) && $segments[2] === 'decision' && $httpMethod === 'POST') {
        $actor = sigap_require_permission('employee_profile_manage');
        $employeeId = (int)$segments[1];
        $data = request_json();
        require_fields($data, ['status']);
        if (!in_array($data['status'], ['disetujui','ditolak'], true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Keputusan tidak valid.'],422);
        sigap_ensure_employee_master_tables();
        $pdo = db();
        $current = sigap_profile_status($pdo, $employeeId);
        if ($current['profile_status'] !== 'menunggu_verifikasi') json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Profil tidak ditemukan atau sudah diproses.'],409);
        $pdo->prepare('UPDATE employee_profiles SET profile_status=?,profile_reviewed_by_user_id=?,profile_reviewed_at=NOW(6),profile_review_note=? WHERE employee_id=?')
            ->execute([$data['status'], (int)$actor['id'], clean_string($data['review_note'] ?? '',255), $employeeId]);
        sigap_profile_completion_recalculate($pdo, $employeeId);
        audit((int)$actor['id'], 'profile_decided', 'employee_profile', (string)$employeeId, $current, ['status'=>$data['status'],'note'=>$data['review_note']??null]);
        json_response(['ok'=>true,'message'=>'Keputusan verifikasi tersimpan.']);
    }

    if (count($segments) === 4 && $segments[0] === 'profile' && $segments[1] === 'documents' && is_numeric($segments[2]) && $segments[3] === 'decision' && $httpMethod === 'POST') {
        $actor = sigap_require_permission('employee_profile_manage');
        $docId = (int)$segments[2];
        $data = request_json();
        require_fields($data, ['status']);
        if (!in_array($data['status'], ['valid','ditolak'], true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Keputusan tidak valid.'],422);
        $pdo = db();
        $docStmt = $pdo->prepare("SELECT * FROM employee_documents WHERE id=? AND status='menunggu_verifikasi'"); $docStmt->execute([$docId]);
        $doc = $docStmt->fetch();
        if (!$doc) json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Dokumen tidak ditemukan atau sudah diproses.'],409);
        $pdo->prepare('UPDATE employee_documents SET status=?,rejection_note=?,verified_by_user_id=?,verified_at=NOW(6) WHERE id=?')
            ->execute([$data['status'], $data['status']==='ditolak' ? clean_string($data['rejection_note'] ?? '',255) : null, (int)$actor['id'], $docId]);
        audit((int)$actor['id'], 'profile_document_decided', 'employee_document', (string)$docId, null, $data);
        json_response(['ok'=>true,'message'=>'Keputusan dokumen tersimpan.']);
    }

    if ($path === '/profile/change-requests' && $httpMethod === 'GET') {
        $actor = sigap_require_permission('employee_profile_manage');
        $rows = db()->query("SELECT c.*,e.full_name,e.nik FROM employee_profile_change_requests c JOIN employees e ON e.id=c.employee_id WHERE c.status='menunggu_verifikasi' ORDER BY c.requested_at")->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
    }

    if (preg_match('#^profile/change-requests/(\d+)/decision$#', trim($path,'/'), $m) && $httpMethod === 'POST') {
        $actor = sigap_require_permission('employee_profile_manage');
        $id = (int)$m[1];
        $data = request_json();
        require_fields($data, ['status']);
        if (!in_array($data['status'], ['disetujui','ditolak'], true)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Keputusan tidak valid.'],422);
        $pdo = db();
        $crStmt = $pdo->prepare("SELECT * FROM employee_profile_change_requests WHERE id=? AND status='menunggu_verifikasi'"); $crStmt->execute([$id]);
        $cr = $crStmt->fetch();
        if (!$cr) json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Pengajuan tidak ditemukan atau sudah diproses.'],409);
        if ($data['status'] === 'disetujui') {
            $after = json_decode((string)$cr['after_json'], true) ?: [];
            $fields = sigap_profile_validate_section_fields($cr['section_code'], $after);
            $sets = []; $params = [];
            foreach ($fields as $f => $v) {
                if (in_array($f, ['ktp_address','domicile_address'], true)) { $sets[] = ($f==='ktp_address'?'ktp_address_json':'domicile_address_json').'=?'; $params[] = json_encode($v, JSON_UNESCAPED_UNICODE); }
                elseif ($f === 'phone_primary') { $sets[]='phone_primary=?'; $params[]=sigap_normalize_phone_id((string)$v); }
                elseif ($f === 'phone_secondary') { $sets[]='phone_secondary=?'; $params[]=sigap_normalize_phone_id((string)$v); }
                elseif ($f === 'bank_account') { $sets[]='bank_account_encrypted=?'; $params[]=sigap_pii_encrypt((string)$v); $sets[]='bank_account_masked=?'; $params[]=sigap_mask_middle(preg_replace('/\D/','',(string)$v),0,4); }
                elseif ($f === 'npwp') { $sets[]='npwp_encrypted=?'; $params[]=sigap_pii_encrypt((string)$v); $sets[]='npwp_masked=?'; $params[]=sigap_mask_middle(preg_replace('/\D/','',(string)$v),2,4); }
                elseif ($f === 'medical_notes') { $sets[]='medical_notes_encrypted=?'; $params[]=sigap_pii_encrypt((string)$v); }
                else { $sets[]="$f=?"; $params[]=is_bool($v)?(int)$v:$v; }
            }
            if ($sets) { $params[] = (int)$cr['employee_id']; $pdo->prepare('UPDATE employee_profiles SET '.implode(',',$sets).' WHERE employee_id=?')->execute($params); }
        }
        $pdo->prepare('UPDATE employee_profile_change_requests SET status=?,reviewed_by_user_id=?,reviewed_at=NOW(6),review_note=? WHERE id=?')
            ->execute([$data['status'], (int)$actor['id'], clean_string($data['review_note'] ?? '',255), $id]);
        audit((int)$actor['id'], 'profile_change_request_decided', 'employee_profile_change_request', (string)$id, $cr, $data);
        json_response(['ok'=>true,'message'=>'Keputusan pengajuan perubahan tersimpan.']);
    }

    // Locations
    if ($path === '/locations' && $httpMethod === 'GET') {
        require_auth();
        $rows = db()->query('SELECT * FROM work_locations WHERE active=1 ORDER BY location_name')->fetchAll();
        json_response(['ok' => true, 'data' => $rows]);
    }

    if ($path === '/locations' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $data = request_json();
        require_fields($data, ['location_code','location_name','latitude','longitude']);
        $stmt = db()->prepare('INSERT INTO work_locations (location_code,location_name,latitude,longitude,radius_meters,max_accuracy_meters,active) VALUES (?,?,?,?,?,?,1)');
        $stmt->execute([
            clean_string($data['location_code'],50), clean_string($data['location_name'],160), (float)$data['latitude'], (float)$data['longitude'],
            (int)($data['radius_meters'] ?? 150), (int)($data['max_accuracy_meters'] ?? 100),
        ]);
        $id = (int)db()->lastInsertId();
        audit((int)$actor['id'], 'location_created', 'work_location', (string)$id, null, $data);
        json_response(['ok' => true, 'id' => $id], 201);
    }

    // Device registration/approval
    if ($path === '/devices/register' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke karyawan.'],422);
        $data = request_json();
        require_fields($data, ['device_uuid']);
        $uuid = clean_string($data['device_uuid'],36);
        $stmt = db()->prepare('SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=?');
        $stmt->execute([(int)$user['employee_id'], $uuid]);
        $device = $stmt->fetch();
        if (!$device) {
            $insert = db()->prepare("INSERT INTO employee_devices (employee_id,device_uuid,device_label,status) VALUES (?,?,?,'pending')");
            $insert->execute([(int)$user['employee_id'], $uuid, clean_string($data['device_label'] ?? 'Perangkat HP',160)]);
            $id = (int)db()->lastInsertId();
            audit((int)$user['id'], 'device_registered', 'employee_device', (string)$id, null, ['device_uuid'=>$uuid]);
            json_response(['ok'=>true,'status'=>'pending','message'=>'Perangkat menunggu persetujuan admin.'],201);
        }
        json_response(['ok'=>true,'status'=>$device['status'],'device_id'=>(int)$device['id']]);
    }

    if ($path === '/devices' && $httpMethod === 'GET') {
        require_roles(['hr_admin','super_admin']);
        $rows = db()->query("SELECT d.id,d.employee_id,d.device_uuid,d.device_label,d.status,d.approved_by_user_id,d.approved_at,d.last_used_at,d.created_at,(d.webauthn_credential_id IS NOT NULL) AS passkey_registered,(d.attendance_pin_hash IS NOT NULL) AS pin_registered,e.nik,e.full_name FROM employee_devices d JOIN employees e ON e.id=d.employee_id ORDER BY FIELD(d.status,'pending','approved','revoked'),d.created_at DESC")->fetchAll();
        foreach ($rows as &$row) {
            $row['passkey_registered'] = (bool)$row['passkey_registered'];
            $row['pin_registered'] = (bool)$row['pin_registered'];
        }
        unset($row);
        json_response(['ok'=>true,'data'=>$rows]);
    }

    if (count($segments) === 3 && $segments[0] === 'devices' && $segments[2] === 'approve' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM employee_devices WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $device = $stmt->fetch();
        if (!$device) { $pdo->rollBack(); json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan.'],404); }
        $pdo->prepare("UPDATE employee_devices SET status='revoked',webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0,attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL WHERE employee_id=? AND id<>? AND status='approved'")->execute([(int)$device['employee_id'],$id]);
        $pdo->prepare("UPDATE employee_devices SET status='approved',approved_by_user_id=?,approved_at=NOW(6),webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0,attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL WHERE id=?")->execute([(int)$actor['id'],$id]);
        $pdo->commit();
        audit((int)$actor['id'],'device_approved','employee_device',(string)$id,$device,['status'=>'approved']);
        json_response(['ok'=>true]);
    }

    if (count($segments) === 3 && $segments[0] === 'devices' && $segments[2] === 'reset-passkey' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT * FROM employee_devices WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $device = $stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan.'],404);
        db()->prepare('UPDATE employee_devices SET webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0,last_used_at=NULL WHERE id=?')->execute([$id]);
        audit((int)$actor['id'],'webauthn_reset','employee_device',(string)$id,$device,['passkey_registered'=>false]);
        security_event((int)$actor['id'],(int)$device['employee_id'],'webauthn_reset','warning','Sidik jari/passkey perangkat direset oleh admin untuk pendaftaran ulang.');
        json_response(['ok'=>true,'message'=>'Sidik jari/passkey berhasil direset.']);
    }

    if (count($segments) === 3 && $segments[0] === 'devices' && $segments[2] === 'reset-approval' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT * FROM employee_devices WHERE id=? LIMIT 1');$stmt->execute([$id]);$device=$stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan.'],404);
        db()->prepare("UPDATE employee_devices SET status='pending',approved_by_user_id=NULL,approved_at=NULL,webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0,attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL,last_used_at=NULL WHERE id=?")->execute([$id]);
        audit((int)$actor['id'],'device_approval_reset','employee_device',(string)$id,$device,['status'=>'pending']);
        security_event((int)$actor['id'],(int)$device['employee_id'],'device_approval_reset','warning','Persetujuan HP direset oleh admin.');
        json_response(['ok'=>true,'message'=>'Persetujuan HP direset dan sidik jari dihapus.']);
    }

    if (count($segments) === 3 && $segments[0] === 'devices' && $segments[2] === 'revoke' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT * FROM employee_devices WHERE id=? LIMIT 1');$stmt->execute([$id]);$device=$stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan.'],404);
        db()->prepare("UPDATE employee_devices SET status='revoked',webauthn_credential_id=NULL,webauthn_public_key=NULL,signature_counter=0,attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL,last_used_at=NULL WHERE id=?")->execute([$id]);
        audit((int)$actor['id'],'device_revoked','employee_device',(string)$id,$device,['status'=>'revoked']);
        security_event((int)$actor['id'],(int)$device['employee_id'],'device_revoked','danger','Akses HP dicabut oleh admin.');
        json_response(['ok'=>true,'message'=>'Akses HP berhasil dicabut.']);
    }



    if (count($segments) === 2 && $segments[0] === 'devices' && $httpMethod === 'DELETE') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT d.*, e.full_name, e.nik FROM employee_devices d JOIN employees e ON e.id=d.employee_id WHERE d.id=? LIMIT 1');
        $stmt->execute([$id]);
        $device = $stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan atau sudah dihapus.'],404);

        audit((int)$actor['id'],'device_deleted','employee_device',(string)$id,$device,null);
        security_event((int)$actor['id'],(int)$device['employee_id'],'device_deleted','warning','Data HP dihapus permanen oleh admin.');
        db()->prepare('DELETE FROM employee_devices WHERE id=?')->execute([$id]);

        json_response(['ok'=>true,'message'=>'Data HP berhasil dihapus dari daftar. Jika karyawan masih memakai HP ini, ia wajib daftar ulang dan menunggu persetujuan admin.']);
    }

    if (count($segments) === 3 && $segments[0] === 'devices' && $segments[2] === 'reset-pin' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        $id = (int)$segments[1];
        $stmt = db()->prepare('SELECT * FROM employee_devices WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $device = $stmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'not_found','message'=>'Perangkat tidak ditemukan.'],404);
        db()->prepare('UPDATE employee_devices SET attendance_pin_hash=NULL,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NULL WHERE id=?')->execute([$id]);
        audit((int)$actor['id'],'attendance_pin_reset','employee_device',(string)$id,$device,['pin_registered'=>false]);
        security_event((int)$actor['id'],(int)$device['employee_id'],'attendance_pin_reset','warning','PIN absensi direset oleh admin.');
        json_response(['ok'=>true,'message'=>'PIN absensi berhasil direset.']);
    }

    // Employee attendance PIN (fallback when fingerprint is unavailable)
    if ($path === '/attendance/pin/set' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json();
        require_fields($data,['device_uuid','current_password','pin','pin_confirm']);
        $pin = trim((string)$data['pin']);
        if ($pin !== trim((string)$data['pin_confirm'])) json_response(['ok'=>false,'error'=>'pin_mismatch','message'=>'Konfirmasi PIN tidak sama.'],422);
        if (!preg_match('/^\d{6}$/',$pin)) json_response(['ok'=>false,'error'=>'pin_format','message'=>'PIN SIGAP harus tepat 6 angka.'],422);
        $weakPins = ['000000','111111','222222','333333','444444','555555','666666','777777','888888','999999','123456','654321','121212','112233'];
        if (in_array($pin,$weakPins,true)) json_response(['ok'=>false,'error'=>'weak_pin','message'=>'PIN terlalu mudah ditebak. Gunakan kombinasi 6 angka lain.'],422);
        $userStmt = db()->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $userStmt->execute([(int)$user['id']]);
        $passwordHash = (string)$userStmt->fetchColumn();
        if (!password_verify((string)$data['current_password'],$passwordHash)) {
            security_event((int)$user['id'],(int)$user['employee_id'],'attendance_pin_set_failed','warning','Password akun salah saat mengatur PIN absensi.');
            json_response(['ok'=>false,'error'=>'invalid_password','message'=>'Password akun tidak sesuai.'],401);
        }
        $deviceStmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $deviceStmt->execute([(int)$user['employee_id'],clean_string($data['device_uuid'],36)]);
        $device = $deviceStmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'device_not_approved','message'=>'HP harus disetujui admin sebelum PIN dibuat.'],403);
        db()->prepare('UPDATE employee_devices SET attendance_pin_hash=?,pin_failed_count=0,pin_locked_until=NULL,pin_updated_at=NOW(6) WHERE id=?')
            ->execute([password_hash($pin,PASSWORD_DEFAULT),(int)$device['id']]);
        audit((int)$user['id'],'attendance_pin_set','employee_device',(string)$device['id']);
        security_event((int)$user['id'],(int)$user['employee_id'],'attendance_pin_set','info','PIN absensi berhasil dibuat pada perangkat yang disetujui.');
        json_response(['ok'=>true,'message'=>'PIN SIGAP berhasil disimpan.']);
    }

    if ($path === '/attendance/pin/verify' && $httpMethod === 'POST') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data = request_json();
        require_fields($data,['device_uuid','pin']);
        $deviceStmt = db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $deviceStmt->execute([(int)$user['employee_id'],clean_string($data['device_uuid'],36)]);
        $device = $deviceStmt->fetch();
        if (!$device) json_response(['ok'=>false,'error'=>'device_not_approved','message'=>'HP belum disetujui admin.'],403);
        if (empty($device['attendance_pin_hash'])) json_response(['ok'=>false,'error'=>'pin_not_registered','message'=>'PIN SIGAP belum dibuat pada HP ini.'],409);
        if (!empty($device['pin_locked_until']) && strtotime((string)$device['pin_locked_until']) > time()) {
            json_response(['ok'=>false,'error'=>'pin_locked','message'=>'PIN dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi beberapa menit.'],429);
        }
        $pin = trim((string)$data['pin']);
        if (!preg_match('/^\d{6}$/',$pin) || !password_verify($pin,(string)$device['attendance_pin_hash'])) {
            $failed = (int)$device['pin_failed_count'] + 1;
            $lockedUntil = $failed >= 5 ? (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s') : null;
            db()->prepare('UPDATE employee_devices SET pin_failed_count=?,pin_locked_until=? WHERE id=?')->execute([$failed,$lockedUntil,(int)$device['id']]);
            security_event((int)$user['id'],(int)$user['employee_id'],'attendance_pin_failed','warning','PIN absensi salah.');
            json_response(['ok'=>false,'error'=>'invalid_pin','message'=>$lockedUntil?'PIN salah dan dikunci 15 menit.':'PIN SIGAP tidak sesuai.'],401);
        }
        db()->prepare('UPDATE employee_devices SET pin_failed_count=0,pin_locked_until=NULL,last_used_at=NOW(6) WHERE id=?')->execute([(int)$device['id']]);
        $proof = webauthn_create_proof((int)$user['id'],(int)$device['id']);
        audit((int)$user['id'],'attendance_pin_verified','employee_device',(string)$device['id']);
        json_response(['ok'=>true,'verified'=>true,'auth_token'=>$proof,'expires_in'=>90]);
    }

    // Attendance readiness diagnostic (does not write attendance)
    if ($path === '/attendance/preflight' && $httpMethod === 'GET') {
        $user = require_auth();
        global $config;
        $uuid = clean_string($_GET['device_uuid'] ?? '', 36);
        $device = null;
        if ($user['employee_id'] && $uuid !== '') {
            $stmt = db()->prepare('SELECT id,status,webauthn_credential_id IS NOT NULL AS passkey_registered,attendance_pin_hash IS NOT NULL AS pin_registered FROM employee_devices WHERE employee_id=? AND device_uuid=? LIMIT 1');
            $stmt->execute([(int)$user['employee_id'],$uuid]);
            $device = $stmt->fetch() ?: null;
        }
        $locationInfo = db()->query('SELECT COUNT(*) AS total,COALESCE(MAX(max_accuracy_meters),100) AS max_accuracy FROM work_locations WHERE active=1')->fetch() ?: ['total'=>0,'max_accuracy'=>100];
        $shift = $user['employee_id'] ? sigap_get_employee_shift(db(),(int)$user['employee_id']) : null;
        $requireAuth = false;
        $passkeyRegistered = $device ? (bool)$device['passkey_registered'] : false;
        $pinRegistered = $device ? (bool)$device['pin_registered'] : false;
        $blockers = [];
        if (!$user['employee_id']) $blockers[] = 'Akun belum terhubung ke data karyawan.';
        if (!$device) $blockers[] = 'HP belum didaftarkan pada akun ini.';
        elseif ($device['status'] !== 'approved') $blockers[] = $device['status']==='revoked' ? 'Akses HP telah dicabut admin.' : 'HP masih menunggu persetujuan admin.';
        
        if ((int)$locationInfo['total'] < 1) $blockers[] = 'Lokasi kerja aktif belum dibuat admin.';
        json_response([
            'ok'=>true,
            'employee_linked'=>(bool)$user['employee_id'],
            'device_status'=>$device['status'] ?? null,
            'passkey_registered'=>$passkeyRegistered,
            'pin_registered'=>$pinRegistered,
            'require_auth'=>$requireAuth,
            'require_passkey'=>false,
            'available_methods'=>[],
            'shift'=>$shift,
            'active_locations'=>(int)$locationInfo['total'],
            'max_accuracy_meters'=>(float)$locationInfo['max_accuracy'],
            'expected_origin'=>rtrim((string)($config['security']['webauthn_origin'] ?? $config['app']['url'] ?? ''),'/'),
            'rp_id'=>(string)($config['security']['webauthn_rp_id'] ?? ''),
            'blockers'=>$blockers,
            'server_time'=>now_sql()
        ]);
    }



    if ($path === '/attendance/report' && $httpMethod === 'GET') {
        sigap_require_permission('owner_monitor');
        $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? ''))
            ? (string)$_GET['month']
            : (new DateTimeImmutable())->format('Y-m');
        $employeeId = isset($_GET['employee_id']) && (int)$_GET['employee_id'] > 0 ? (int)$_GET['employee_id'] : null;
        try {
            $report = sigap_build_attendance_report(db(),$month,$employeeId);
        } catch (InvalidArgumentException $e) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>$e->getMessage()],422);
        }
        json_response(['ok'=>true,'data'=>$report,'server_time'=>now_sql()]);
    }

    // Attendance
    if ($path === '/attendance/today' && $httpMethod === 'GET') {
        $user = require_auth();
        if (!$user['employee_id']) json_response(['ok'=>true,'data'=>null]);
        $stmt = db()->prepare('SELECT a.*,w.location_name FROM attendance a LEFT JOIN work_locations w ON w.id=a.location_id WHERE a.employee_id=? AND a.attendance_date=CURDATE()');
        $stmt->execute([(int)$user['employee_id']]);
        json_response(['ok'=>true,'data'=>$stmt->fetch() ?: null,'shift'=>sigap_get_employee_shift(db(),(int)$user['employee_id']),'server_time'=>now_sql()]);
    }

    if (($path === '/attendance/check-in' || $path === '/attendance/check-out') && $httpMethod === 'POST') {
        $user = require_roles(['employee','supervisor','hr_admin','payroll_finance','owner','super_admin']);
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        sigap_ensure_attendance_v110_columns();
        $data = request_json();
        require_fields($data,['latitude','longitude','accuracy','device_uuid']);
        $lat=(float)$data['latitude']; $lon=(float)$data['longitude']; $accuracy=(float)$data['accuracy'];
        if ($accuracy <= 0 || $accuracy > 5000) json_response(['ok'=>false,'error'=>'gps_invalid','message'=>'Akurasi GPS tidak valid.'],422);
        $deviceStmt=db()->prepare("SELECT * FROM employee_devices WHERE employee_id=? AND device_uuid=? AND status='approved' LIMIT 1");
        $deviceStmt->execute([(int)$user['employee_id'],clean_string($data['device_uuid'],36)]);
        $device=$deviceStmt->fetch();
        if(!$device){
            security_event((int)$user['id'],(int)$user['employee_id'],'attendance_rejected','danger','Perangkat belum disetujui.');
            json_response(['ok'=>false,'error'=>'device_not_approved','message'=>'Perangkat ini belum disetujui admin.'],403);
        }
        $locations=db()->query('SELECT * FROM work_locations WHERE active=1')->fetchAll();
        if(!$locations) json_response(['ok'=>false,'error'=>'location_not_configured','message'=>'Lokasi kerja belum diatur admin.'],409);
        $nearest=null; $nearestDistance=INF;
        foreach($locations as $location){
            $distance=haversine_m($lat,$lon,(float)$location['latitude'],(float)$location['longitude']);
            if($distance<$nearestDistance){$nearestDistance=$distance;$nearest=$location;}
        }
        if($accuracy>(float)$nearest['max_accuracy_meters']){
            security_event((int)$user['id'],(int)$user['employee_id'],'attendance_rejected','warning','Akurasi GPS terlalu rendah: '.$accuracy.' meter');
            json_response(['ok'=>false,'error'=>'gps_accuracy','message'=>'Akurasi GPS belum cukup. Pindah ke area terbuka dan coba lagi.','accuracy'=>$accuracy,'max'=>(float)$nearest['max_accuracy_meters']],422);
        }
        $outsideGeofence=$nearestDistance>(float)$nearest['radius_meters'];
        $activeTrip=null;
        if($outsideGeofence && $path==='/attendance/check-in'){
            // Izinkan check-in di luar radius HANYA jika ada Perjalanan Dinas yang sudah
            // disetujui atasan untuk hari ini -- persetujuan sebelumnya ini yang menjaga
            // dari potensi "titip absen", bukan alasan yang diketik sendiri saat absen.
            $tripStmt=db()->prepare("SELECT id,project_name,destination FROM business_trips WHERE employee_id=? AND approval_status='approved' AND start_date<=CURDATE() AND end_date>=CURDATE() LIMIT 1");
            $tripStmt->execute([(int)$user['employee_id']]);
            $activeTrip=$tripStmt->fetch();
            if(!$activeTrip){
                security_event((int)$user['id'],(int)$user['employee_id'],'attendance_rejected','danger','Check-in di luar radius: '.round($nearestDistance).' meter');
                json_response(['ok'=>false,'error'=>'outside_geofence','message'=>'Absen masuk tetap harus berada di radius lokasi kerja. Jika sedang dinas luar, ajukan Perjalanan Dinas dan tunggu persetujuan atasan terlebih dahulu.','distance'=>round($nearestDistance,1),'radius'=>(int)$nearest['radius_meters']],403);
            }
        }
        $outsideReason=clean_string($data['outside_reason'] ?? '',500);
        if($outsideGeofence && $path==='/attendance/check-out' && $outsideReason===''){
            json_response(['ok'=>false,'error'=>'outside_reason_required','message'=>'Anda berada di luar lokasi kerja. Isi alasan check-out di luar kantor.','distance'=>round($nearestDistance,1),'radius'=>(int)$nearest['radius_meters']],422);
        }
        global $config;
        $requireAuth = false;
        $authMethod = clean_string($data['auth_method'] ?? '',20);
        if ($requireAuth) {
            if (!in_array($authMethod,['passkey','pin'],true)) {
                json_response(['ok'=>false,'error'=>'auth_method_required','message'=>'Pilih verifikasi Sidik Jari atau PIN SIGAP.'],428);
            }
            if ($authMethod === 'passkey' && (empty($device['webauthn_credential_id']) || empty($device['webauthn_public_key']))) {
                json_response(['ok'=>false,'error'=>'passkey_not_registered','message'=>'Sidik Jari belum diaktifkan pada HP ini. Gunakan PIN SIGAP atau aktifkan Sidik Jari.'],409);
            }
            if ($authMethod === 'pin' && empty($device['attendance_pin_hash'])) {
                json_response(['ok'=>false,'error'=>'pin_not_registered','message'=>'PIN SIGAP belum dibuat pada HP ini.'],409);
            }
            $authToken = (string)($data['auth_token'] ?? $data['biometric_token'] ?? '');
            if ($authToken === '') {
                json_response(['ok'=>false,'error'=>'attendance_auth_required','message'=>'Verifikasi keamanan wajib dilakukan sebelum absensi.'],428);
            }
            try {
                webauthn_consume_proof($authToken,(int)$user['id'],(int)$device['id']);
            } catch (Throwable $e) {
                security_event((int)$user['id'],(int)$user['employee_id'],'attendance_rejected','danger','Bukti verifikasi ditolak: '.$e->getMessage());
                json_response(['ok'=>false,'error'=>'attendance_auth_invalid','message'=>'Bukti verifikasi tidak valid atau sudah kedaluwarsa. Silakan verifikasi ulang.'],403);
            }
        } else {
            $authMethod = 'none';
        }
        sigap_ensure_shift_tables();
        $pdo=db(); $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT * FROM attendance WHERE employee_id=? AND attendance_date=CURDATE() FOR UPDATE');
        $stmt->execute([(int)$user['employee_id']]);
        $record=$stmt->fetch();
        if($path==='/attendance/check-in'){
            if($record && $record['check_in_at']){$pdo->rollBack();json_response(['ok'=>false,'error'=>'already_checked_in','message'=>'Anda sudah absen masuk hari ini.'],409);}
            $now=new DateTimeImmutable();
            $shift=sigap_get_employee_shift($pdo,(int)$user['employee_id']);
            $resolvedSchedule=sigap_resolve_employee_schedule($pdo,(int)$user['employee_id'],$shift,$now,sigap_report_holidays());
            if(!$resolvedSchedule['is_workday']){$pdo->rollBack();json_response(['ok'=>false,'error'=>'non_workday','message'=>'Hari ini bukan jadwal kerja Anda. Hubungi admin jika ada perubahan jadwal.'],422);}
            $daySchedule=['work_start'=>$resolvedSchedule['work_start'],'work_end'=>$resolvedSchedule['work_end']];
            $start=new DateTimeImmutable($now->format('Y-m-d').' '.$daySchedule['work_start']);
            $deadline=$start->modify('+'.(int)$shift['late_tolerance_minutes'].' minutes');
            $late=max(0,(int)floor(($now->getTimestamp()-$deadline->getTimestamp())/60));
            $latePermitStmt=$pdo->prepare("SELECT id,reason FROM employee_requests WHERE employee_id=? AND request_type='late_permission' AND start_date=CURDATE() AND status='approved' ORDER BY id DESC LIMIT 1");
            $latePermitStmt->execute([(int)$user['employee_id']]);
            $latePermit=$latePermitStmt->fetch();
            $status=$late>0?($latePermit?'late_permitted':'late'):'present';
            if($record){
                $upd=$pdo->prepare('UPDATE attendance SET check_in_at=NOW(6),check_in_latitude=?,check_in_longitude=?,check_in_accuracy_meters=?,check_in_distance_meters=?,location_id=?,device_id=?,check_in_auth_method=?,late_minutes=?,status=?,check_in_outside_geofence=?,check_in_business_trip_id=? WHERE id=?');
                $upd->execute([$lat,$lon,$accuracy,$nearestDistance,(int)$nearest['id'],(int)$device['id'],$authMethod,$late,$status,$outsideGeofence?1:0,$activeTrip?(int)$activeTrip['id']:null,(int)$record['id']]);
                $attendanceId=(int)$record['id'];
            }else{
                $ins=$pdo->prepare('INSERT INTO attendance (employee_id,attendance_date,check_in_at,check_in_latitude,check_in_longitude,check_in_accuracy_meters,check_in_distance_meters,location_id,device_id,check_in_auth_method,late_minutes,status,check_in_outside_geofence,check_in_business_trip_id) VALUES (?,CURDATE(),NOW(6),?,?,?,?,?,?,?,?,?,?,?)');
                $ins->execute([(int)$user['employee_id'],$lat,$lon,$accuracy,$nearestDistance,(int)$nearest['id'],(int)$device['id'],$authMethod,$late,$status,$outsideGeofence?1:0,$activeTrip?(int)$activeTrip['id']:null]);
                $attendanceId=(int)$pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE employee_devices SET last_used_at=NOW(6) WHERE id=?')->execute([(int)$device['id']]);
            $pdo->commit();
            audit((int)$user['id'],'attendance_check_in','attendance',(string)$attendanceId,null,['location'=>$nearest['location_name'],'distance'=>round($nearestDistance,1),'late_minutes'=>$late,'auth_method'=>$authMethod,'shift_id'=>$shift['id'],'shift_name'=>$shift['shift_name'],'outside_geofence'=>$outsideGeofence,'business_trip_id'=>$activeTrip['id']??null]);
            try{telegram_notify_checkin($user,$late,$nearest['location_name'],$now,(bool)$latePermit);}catch(Throwable $e){error_log('SIGAP MPA Telegram notify error: '.$e->getMessage());}
            $message=$activeTrip?('Absensi masuk berhasil (dinas luar: '.$activeTrip['project_name'].' — '.$activeTrip['destination'].').'):'Absensi masuk berhasil.';
            json_response(['ok'=>true,'message'=>$message,'late_minutes'=>$late,'location'=>$nearest['location_name'],'distance'=>round($nearestDistance,1),'shift'=>$shift,'server_time'=>now_sql(),'outside_geofence'=>$outsideGeofence,'pantun'=>sigap_pantun_checkin($late>0)]);
        }
        if(!$record || !$record['check_in_at']){$pdo->rollBack();json_response(['ok'=>false,'error'=>'not_checked_in','message'=>'Anda belum absen masuk hari ini.'],409);}
        if($record['check_out_at']){$pdo->rollBack();json_response(['ok'=>false,'error'=>'already_checked_out','message'=>'Anda sudah absen pulang hari ini.'],409);}
        $upd=$pdo->prepare('UPDATE attendance SET check_out_at=NOW(6),check_out_latitude=?,check_out_longitude=?,check_out_accuracy_meters=?,check_out_distance_meters=?,location_id=?,device_id=?,check_out_auth_method=?,check_out_outside_geofence=?,check_out_reason=? WHERE id=?');
        $upd->execute([$lat,$lon,$accuracy,$nearestDistance,(int)$nearest['id'],(int)$device['id'],$authMethod,$outsideGeofence?1:0,$outsideReason!==''?$outsideReason:null,(int)$record['id']]);
        $pdo->prepare('UPDATE employee_devices SET last_used_at=NOW(6) WHERE id=?')->execute([(int)$device['id']]);
        $pdo->commit();
        audit((int)$user['id'],'attendance_check_out','attendance',(string)$record['id'],$record,['location'=>$nearest['location_name'],'distance'=>round($nearestDistance,1),'auth_method'=>$authMethod,'outside_geofence'=>$outsideGeofence,'outside_reason'=>$outsideReason]);
        json_response(['ok'=>true,'message'=>$outsideGeofence?'Absensi pulang di luar lokasi berhasil dan tercatat untuk audit.':'Absensi pulang berhasil.','location'=>$nearest['location_name'],'distance'=>round($nearestDistance,1),'server_time'=>now_sql(),'pantun'=>sigap_pantun_checkout()]);
    }

    if ($path === '/attendance/admin' && $httpMethod === 'GET') {
        $actor=require_roles(['super_admin']);
        sigap_ensure_attendance_v110_columns();
        $month=preg_match('/^\d{4}-\d{2}$/',(string)($_GET['month']??''))?(string)$_GET['month']:(new DateTimeImmutable())->format('Y-m');
        $employeeId=(int)($_GET['employee_id']??0);
        $sql="SELECT a.*,e.nik,e.full_name,e.position_name,e.department_name,w.location_name FROM attendance a JOIN employees e ON e.id=a.employee_id LEFT JOIN work_locations w ON w.id=a.location_id WHERE DATE_FORMAT(a.attendance_date,'%Y-%m')=?";
        $params=[$month];
        if($employeeId>0){$sql.=' AND a.employee_id=?';$params[]=$employeeId;}
        $sql.=' ORDER BY a.attendance_date DESC,e.full_name';
        $stmt=db()->prepare($sql);$stmt->execute($params);
        $employees=db()->query("SELECT id,nik,full_name,position_name,department_name FROM employees WHERE employment_status='active' ORDER BY full_name")->fetchAll();
        json_response(['ok'=>true,'data'=>$stmt->fetchAll(),'employees'=>$employees,'server_time'=>now_sql()]);
    }

    if ($path === '/attendance/admin/save' && $httpMethod === 'POST') {
        $actor=require_roles(['super_admin']);
        sigap_ensure_attendance_v110_columns();
        $data=request_json();require_fields($data,['employee_id','attendance_date']);
        $employeeId=(int)$data['employee_id'];$date=(string)$data['attendance_date'];
        if($employeeId<1||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Karyawan atau tanggal tidak valid.'],422);
        $checkIn=trim((string)($data['check_in_time']??''));$checkOut=trim((string)($data['check_out_time']??''));
        foreach([$checkIn,$checkOut] as $time){if($time!==''&&!preg_match('/^\d{2}:\d{2}$/',$time))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Format jam harus HH:MM.'],422);}
        $status=clean_string($data['status']??'present',30);$allowed=['present','late','late_permitted','incomplete','corrected'];
        if(!in_array($status,$allowed,true))$status='present';
        $note=clean_string($data['admin_note']??'',1000);
        $pdo=db();$pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT * FROM attendance WHERE employee_id=? AND attendance_date=? FOR UPDATE');$stmt->execute([$employeeId,$date]);$before=$stmt->fetch();
        $inSql=$checkIn!==''?$date.' '.$checkIn.':00':null;$outSql=$checkOut!==''?$date.' '.$checkOut.':00':null;
        $shift=sigap_get_employee_shift($pdo,$employeeId);$late=0;
        if($inSql){$resolvedSchedule=sigap_resolve_employee_schedule($pdo,$employeeId,$shift,$date,sigap_report_holidays());$daySchedule=['work_start'=>$resolvedSchedule['work_start']??$shift['work_start'],'work_end'=>$resolvedSchedule['work_end']??$shift['work_end']];$deadline=new DateTimeImmutable($date.' '.$daySchedule['work_start']);$deadline=$deadline->modify('+'.(int)$shift['late_tolerance_minutes'].' minutes');$actual=new DateTimeImmutable($inSql);$late=max(0,(int)floor(($actual->getTimestamp()-$deadline->getTimestamp())/60));if($late>0&&$status==='present')$status='late';}
        if($before){
            $upd=$pdo->prepare('UPDATE attendance SET check_in_at=?,check_out_at=?,late_minutes=?,status=?,admin_note=?,updated_by_user_id=? WHERE id=?');
            $upd->execute([$inSql,$outSql,$late,$status,$note!==''?$note:null,(int)$actor['id'],(int)$before['id']]);$id=(int)$before['id'];
        }else{
            $ins=$pdo->prepare("INSERT INTO attendance (employee_id,attendance_date,check_in_at,check_out_at,late_minutes,status,check_in_auth_method,check_out_auth_method,admin_note,updated_by_user_id) VALUES (?,?,?,?,?,?,'admin','admin',?,?)");
            $ins->execute([$employeeId,$date,$inSql,$outSql,$late,$status,$note!==''?$note:null,(int)$actor['id']]);$id=(int)$pdo->lastInsertId();
        }
        $pdo->commit();audit((int)$actor['id'],'attendance_admin_saved','attendance',(string)$id,$before,['employee_id'=>$employeeId,'attendance_date'=>$date,'check_in_at'=>$inSql,'check_out_at'=>$outSql,'status'=>$status,'admin_note'=>$note]);
        json_response(['ok'=>true,'id'=>$id,'message'=>'Absensi karyawan berhasil disimpan.']);
    }

    if ($path === '/attendance/history' && $httpMethod === 'GET') {
        $user=require_auth();
        $month=preg_match('/^\d{4}-\d{2}$/',$_GET['month']??'')?$_GET['month']:(new DateTimeImmutable())->format('Y-m');
        $employeeId=$user['employee_id']?(int)$user['employee_id']:0;
        if(isset($_GET['employee_id']) && in_array($user['role'],['hr_admin','payroll_finance','super_admin'],true))$employeeId=(int)$_GET['employee_id'];
        if(!$employeeId) json_response(['ok'=>true,'data'=>[]]);
        $stmt=db()->prepare("SELECT a.*,w.location_name FROM attendance a LEFT JOIN work_locations w ON w.id=a.location_id WHERE a.employee_id=? AND DATE_FORMAT(a.attendance_date,'%Y-%m')=? ORDER BY a.attendance_date DESC");
        $stmt->execute([$employeeId,$month]);
        json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }

    // Requests
    if ($path === '/requests' && $httpMethod === 'GET') {
        $user=require_auth();
        if ($user['role'] === 'owner') {
            $rows=db()->query("SELECT r.*,e.nik,e.full_name,e.position_name,e.department_name,u.username AS decider_name FROM employee_requests r JOIN employees e ON e.id=r.employee_id LEFT JOIN users u ON u.id=r.decided_by_user_id WHERE r.request_type='overtime' ORDER BY FIELD(r.status,'pending','approved','rejected','cancelled'),r.created_at DESC LIMIT 500")->fetchAll();
        } elseif (in_array($user['role'],['supervisor','hr_admin','super_admin'],true)) {
            $where = $user['role']==='super_admin' ? '' : "WHERE r.request_type<>'overtime'";
            $rows=db()->query("SELECT r.*,e.nik,e.full_name,e.position_name,e.department_name,u.username AS decider_name FROM employee_requests r JOIN employees e ON e.id=r.employee_id LEFT JOIN users u ON u.id=r.decided_by_user_id {$where} ORDER BY FIELD(r.status,'pending','approved','rejected','cancelled'),r.created_at DESC LIMIT 500")->fetchAll();
        } else {
            if (!$user['employee_id']) json_response(['ok'=>true,'data'=>[]]);
            $stmt=db()->prepare('SELECT r.*,e.nik,e.full_name,e.position_name,e.department_name,u.username AS decider_name FROM employee_requests r JOIN employees e ON e.id=r.employee_id LEFT JOIN users u ON u.id=r.decided_by_user_id WHERE r.employee_id=? ORDER BY r.created_at DESC');
            $stmt->execute([(int)$user['employee_id']]); $rows=$stmt->fetchAll();
        }
        json_response(['ok'=>true,'data'=>$rows]);
    }

    if ($path === '/requests' && $httpMethod === 'POST') {
        $user=require_auth();
        sigap_ensure_attendance_v110_columns();
        if(!$user['employee_id'])json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        $data=request_json(); require_fields($data,['request_type','start_date','reason']);
        $types=['leave','permission','sick','overtime','late_permission'];
        if(!in_array($data['request_type'],$types,true))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis pengajuan tidak valid.'],422);
        $requestType=(string)$data['request_type'];
        $startDate=(string)$data['start_date'];
        $endDate=(string)($data['end_date'] ?? $startDate);
        $startTime=$data['start_time'] ?? null; $endTime=$data['end_time'] ?? null;
        $quantity=isset($data['quantity'])?decimal_value($data['quantity']):0;
        $reason=clean_string($data['reason'],2000);
        if ($requestType === 'overtime') {
            if ($reason === '') json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Agenda lembur wajib diisi.'],422);
            $overtimeMaxDate = (new DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
            if ($startDate > $overtimeMaxDate) {
                json_response(['ok'=>false,'error'=>'validation_failed','message'=>"Pengajuan lembur maksimal H+3 (paling lambat tanggal {$overtimeMaxDate})."],422);
            }
            try { $calculated=sigap_calculate_overtime_hours($startDate,$endDate,(string)$startTime,(string)$endTime); }
            catch (InvalidArgumentException $e) { json_response(['ok'=>false,'error'=>'validation_failed','message'=>$e->getMessage()],422); }
            $quantity=(float)$calculated['hours']; $endDate=(string)$calculated['end_date'];
        } elseif ($requestType === 'late_permission') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$startDate)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tanggal izin terlambat tidak valid.'],422);
            if (!preg_match('/^\d{2}:\d{2}$/',(string)$startTime)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Perkiraan jam masuk wajib diisi.'],422);
            if ($reason === '') json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Alasan izin terlambat wajib diisi.'],422);
            $endDate=$startDate; $endTime=null; $quantity=0;
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$endDate)) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tanggal pengajuan tidak valid.'],422);
            if ($endDate < $startDate) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Tanggal selesai tidak boleh sebelum tanggal mulai.'],422);
            if ($quantity <= 0) json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jumlah hari harus lebih dari 0.'],422);
            $startTime=null; $endTime=null;
        }
        if ($requestType === 'leave') {
            sigap_ensure_leave_balance_tables();
            $leaveYear = (int)substr($startDate, 0, 4);
            $leaveSummary = sigap_leave_balance_summary(db(), (int)$user['employee_id'], $leaveYear);
            if ($quantity > $leaveSummary['remaining_days']) {
                json_response(['ok'=>false,'error'=>'leave_quota_exceeded','message'=>"Sisa kuota cuti tahun {$leaveYear} hanya {$leaveSummary['remaining_days']} hari, pengajuan ini {$quantity} hari."],422);
            }
        }
        $stmt=db()->prepare('INSERT INTO employee_requests (employee_id,request_type,start_date,end_date,start_time,end_time,quantity,reason) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([(int)$user['employee_id'],$requestType,$startDate,$endDate,$startTime,$endTime,$quantity,$reason]);
        $id=(int)db()->lastInsertId();
        audit((int)$user['id'],'request_created','employee_request',(string)$id,null,['request_type'=>$requestType,'start_date'=>$startDate,'end_date'=>$endDate,'start_time'=>$startTime,'end_time'=>$endTime,'quantity'=>$quantity,'reason'=>$reason]);
        try{telegram_notify_request_submitted($user,$id,$requestType,$startDate,$endDate,$startTime,$endTime,$quantity,$reason);}catch(Throwable $e){error_log('SIGAP MPA Telegram notify error: '.$e->getMessage());}
        json_response(['ok'=>true,'id'=>$id,'quantity'=>$quantity,'message'=>$requestType==='overtime'?"Pengajuan lembur {$quantity} jam dikirim ke Owner.":($requestType==='late_permission'?'Izin terlambat berhasil dikirim. Anda tetap dapat absen masuk dan pulang.':'Pengajuan berhasil dikirim.')],201);
    }

    if(count($segments)===3 && $segments[0]==='requests' && $segments[2]==='decision' && $httpMethod==='POST'){
        $actor=require_auth(); $id=(int)$segments[1]; $data=request_json(); require_fields($data,['status']);
        if(!in_array($data['status'],['approved','rejected'],true))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Keputusan tidak valid.'],422);
        $requestStmt=db()->prepare("SELECT r.*,e.full_name FROM employee_requests r JOIN employees e ON e.id=r.employee_id WHERE r.id=? LIMIT 1");
        $requestStmt->execute([$id]); $request=$requestStmt->fetch();
        if(!$request || $request['status']!=='pending')json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Pengajuan tidak ditemukan atau sudah diproses.'],409);
        if ($request['request_type']==='overtime') {
            if ($actor['role']!=='owner') json_response(['ok'=>false,'error'=>'forbidden','message'=>'Pengajuan lembur hanya dapat diputuskan oleh Owner.'],403);
        } elseif (!in_array($actor['role'],['supervisor','hr_admin','super_admin'],true)) {
            json_response(['ok'=>false,'error'=>'forbidden','message'=>'Anda tidak memiliki hak approval untuk pengajuan ini.'],403);
        }
        if ($data['status']==='approved' && $request['request_type']==='leave') {
            sigap_ensure_leave_balance_tables();
            $leaveYear = (int)substr((string)$request['start_date'], 0, 4);
            $leaveSummary = sigap_leave_balance_summary(db(), (int)$request['employee_id'], $leaveYear);
            if ((float)$request['quantity'] > $leaveSummary['remaining_days']) {
                json_response(['ok'=>false,'error'=>'leave_quota_exceeded','message'=>"Sisa kuota cuti karyawan ini tahun {$leaveYear} hanya {$leaveSummary['remaining_days']} hari, pengajuan {$request['quantity']} hari."],422);
            }
        }
        $stmt=db()->prepare("UPDATE employee_requests SET status=?,decided_by_user_id=?,decided_at=NOW(6),decision_note=? WHERE id=? AND status='pending'");
        $stmt->execute([$data['status'],(int)$actor['id'],clean_string($data['decision_note']??'',1000),$id]);
        if($stmt->rowCount()===0)json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Pengajuan tidak ditemukan atau sudah diproses.'],409);
        if ($data['status']==='approved' && $request['request_type']==='leave') {
            sigap_leave_balance_deduct(db(), (int)$request['employee_id'], (int)substr((string)$request['start_date'],0,4), (float)$request['quantity']);
        }
        audit((int)$actor['id'],'request_decided','employee_request',(string)$id,$request,$data);
        try{telegram_notify_request_decided($request,$data['status'],clean_string($data['decision_note']??'',1000)?:null);}catch(Throwable $e){error_log('SIGAP MPA Telegram notify error: '.$e->getMessage());}
        json_response(['ok'=>true,'message'=>$request['request_type']==='overtime'?'Keputusan lembur tersimpan dan rekap payroll otomatis diperbarui.':'Keputusan tersimpan.']);
    }

    if ($path === '/requests/quick-decision' && $httpMethod === 'GET') {
        sigap_ensure_quick_token_table();
        $id=(int)($_GET['id']??0);
        $decision=(string)($_GET['decision']??'');
        $token=(string)($_GET['token']??'');
        if($id<1||!in_array($decision,['approved','rejected'],true)||$token===''){
            sigap_quick_decision_page('Link Tidak Valid','Parameter link tidak lengkap atau tidak valid.',false);
        }
        $tokenHash=hash('sha256',$token);
        $tokenStmt=db()->prepare('SELECT * FROM request_quick_tokens WHERE request_id=? AND decision=? AND token_hash=? LIMIT 1');
        $tokenStmt->execute([$id,$decision,$tokenHash]);
        $tokenRow=$tokenStmt->fetch();
        if(!$tokenRow){
            sigap_quick_decision_page('Link Tidak Valid','Link ini tidak dikenali atau salah.',false);
        }
        if($tokenRow['used_at']){
            sigap_quick_decision_page('Sudah Diproses','Pengajuan ini sudah diputuskan sebelumnya lewat link ini atau lewat aplikasi.',false);
        }
        if(strtotime((string)$tokenRow['expires_at'])<time()){
            sigap_quick_decision_page('Link Kedaluwarsa','Link persetujuan ini sudah kedaluwarsa (lebih dari 7 hari). Silakan putuskan lewat aplikasi SIGAP MPA.',false);
        }
        $pdo=db();$pdo->beginTransaction();
        $requestStmt=$pdo->prepare('SELECT r.*,e.full_name FROM employee_requests r JOIN employees e ON e.id=r.employee_id WHERE r.id=? LIMIT 1 FOR UPDATE');
        $requestStmt->execute([$id]);$request=$requestStmt->fetch();
        if(!$request||$request['status']!=='pending'){
            $pdo->rollBack();
            sigap_quick_decision_page('Sudah Diproses','Pengajuan ini sudah tidak berstatus menunggu (sudah diputuskan lewat aplikasi atau link lain).',false);
        }
        if($decision==='approved'&&$request['request_type']==='leave'){
            sigap_ensure_leave_balance_tables();
            $leaveYear=(int)substr((string)$request['start_date'],0,4);
            $leaveSummary=sigap_leave_balance_summary($pdo,(int)$request['employee_id'],$leaveYear);
            if((float)$request['quantity']>$leaveSummary['remaining_days']){
                $pdo->rollBack();
                sigap_quick_decision_page('Gagal Disetujui',"Sisa kuota cuti karyawan ini tahun {$leaveYear} hanya {$leaveSummary['remaining_days']} hari, pengajuan {$request['quantity']} hari. Putuskan lewat aplikasi untuk detail lebih lanjut.",false);
            }
        }
        $deciderUserId=(int)$tokenRow['decider_user_id'];
        $pdo->prepare("UPDATE employee_requests SET status=?,decided_by_user_id=?,decided_at=NOW(6),decision_note=? WHERE id=? AND status='pending'")
            ->execute([$decision,$deciderUserId,'Diputuskan via tombol Telegram',$id]);
        if($decision==='approved'&&$request['request_type']==='leave'){
            sigap_leave_balance_deduct($pdo,(int)$request['employee_id'],(int)substr((string)$request['start_date'],0,4),(float)$request['quantity']);
        }
        $pdo->prepare('UPDATE request_quick_tokens SET used_at=NOW(6) WHERE request_id=?')->execute([$id]);
        $pdo->commit();
        audit($deciderUserId,'request_decided','employee_request',(string)$id,$request,['status'=>$decision,'via'=>'telegram']);
        try{telegram_notify_request_decided($request,$decision,'Diputuskan via tombol Telegram');}catch(Throwable $e){error_log('SIGAP MPA Telegram notify error: '.$e->getMessage());}
        $typeLabel=['leave'=>'Cuti','permission'=>'Izin','sick'=>'Sakit','overtime'=>'Lembur','late_permission'=>'Izin Terlambat'][$request['request_type']]??'Pengajuan';
        $decisionLabel=$decision==='approved'?'disetujui':'ditolak';
        sigap_quick_decision_page($decision==='approved'?'Berhasil Disetujui':'Berhasil Ditolak',"Pengajuan {$typeLabel} atas nama {$request['full_name']} berhasil {$decisionLabel}.",true);
    }

    if ($path === '/telegram/link/start' && $httpMethod === 'POST') {
        $user=require_auth();
        if(!$user['employee_id'])json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        global $config;
        $botUsername=trim((string)($config['telegram']['bot_username']??''));
        if($botUsername==='')json_response(['ok'=>false,'error'=>'not_configured','message'=>'Bot Telegram belum dikonfigurasi admin.'],409);
        sigap_ensure_telegram_link_tables();
        $token=bin2hex(random_bytes(24));
        $hash=hash('sha256',$token);
        $expiresAt=(new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s.u');
        db()->prepare('INSERT INTO employee_telegram_link_tokens (employee_id,token_hash,expires_at) VALUES (?,?,?)')
            ->execute([(int)$user['employee_id'],$hash,$expiresAt]);
        json_response(['ok'=>true,'url'=>'https://t.me/'.$botUsername.'?start='.$token]);
    }

    if ($path === '/telegram/link/status' && $httpMethod === 'GET') {
        $user=require_auth();
        if(!$user['employee_id'])json_response(['ok'=>true,'linked'=>false]);
        sigap_ensure_telegram_link_tables();
        $stmt=db()->prepare('SELECT linked_at FROM employee_telegram_links WHERE employee_id=? LIMIT 1');
        $stmt->execute([(int)$user['employee_id']]);
        $row=$stmt->fetch();
        json_response(['ok'=>true,'linked'=>(bool)$row,'linked_at'=>$row['linked_at']??null]);
    }

    if ($path === '/telegram/webhook' && $httpMethod === 'POST') {
        $update=request_json();
        $message=$update['message']??null;
        $text=trim((string)($message['text']??''));
        $chatId=$message['chat']['id']??null;
        global $config;
        $botToken=(string)($config['telegram']['bot_token']??'');
        if($chatId!==null && preg_match('/^\/start\s+([a-f0-9]{48})$/',$text,$m)){
            sigap_ensure_telegram_link_tables();
            $hash=hash('sha256',$m[1]);
            $tokenStmt=db()->prepare('SELECT * FROM employee_telegram_link_tokens WHERE token_hash=? LIMIT 1');
            $tokenStmt->execute([$hash]);
            $tokenRow=$tokenStmt->fetch();
            if($tokenRow && !$tokenRow['used_at'] && strtotime((string)$tokenRow['expires_at'])>=time()){
                db()->prepare('INSERT INTO employee_telegram_links (employee_id,chat_id) VALUES (?,?) ON DUPLICATE KEY UPDATE chat_id=VALUES(chat_id),linked_at=NOW(6)')
                    ->execute([(int)$tokenRow['employee_id'],(string)$chatId]);
                db()->prepare('UPDATE employee_telegram_link_tokens SET used_at=NOW(6) WHERE id=?')->execute([(int)$tokenRow['id']]);
                if($botToken!=='')telegram_send_message($botToken,(string)$chatId,'✅ Berhasil! Akun Telegram Anda sudah terhubung ke SIGAP MPA. Anda akan menerima notifikasi pribadi (hasil approval, pengingat absen, dll) di sini.');
            } else {
                if($botToken!=='')telegram_send_message($botToken,(string)$chatId,'Link sudah kedaluwarsa atau tidak valid. Silakan klik tombol "Hubungkan Telegram" lagi dari aplikasi SIGAP MPA.');
            }
        }
        json_response(['ok'=>true]);
    }

    if ($path === '/cron/attendance-reminder' && $httpMethod === 'GET') {
        global $config;
        $secret=(string)($config['cron']['secret']??'');
        if($secret===''||($_GET['key']??'')!==$secret){
            json_response(['ok'=>false,'error'=>'forbidden'],403);
        }
        sigap_ensure_shift_tables();
        sigap_ensure_telegram_link_tables();
        $pdo=db();
        $now=new DateTimeImmutable();
        $today=$now->format('Y-m-d');
        $employees=$pdo->query("SELECT id,full_name FROM employees WHERE employment_status='active'")->fetchAll();
        $sent=0;$checked=0;
        foreach($employees as $emp){
            $empId=(int)$emp['id'];
            $shift=sigap_get_employee_shift($pdo,$empId);
            $resolved=sigap_resolve_employee_schedule($pdo,$empId,$shift,$now,sigap_report_holidays());
            if(!$resolved['is_workday'])continue;
            $workStart=$resolved['work_start']??$shift['work_start'];
            $deadline=new DateTimeImmutable($today.' '.$workStart);
            if($now<$deadline)continue;
            $checked++;
            $attStmt=$pdo->prepare('SELECT check_in_at FROM attendance WHERE employee_id=? AND attendance_date=? LIMIT 1');
            $attStmt->execute([$empId,$today]);
            if($attStmt->fetchColumn())continue;
            $minutesLate=(int)floor(($now->getTimestamp()-$deadline->getTimestamp())/60);
            $text="⏰ *Pengingat Absen*\n\nHalo {$emp['full_name']}, Anda belum melakukan Absen Masuk hari ini. Jadwal kerja dimulai {$workStart} dan sekarang sudah lewat {$minutesLate} menit. Segera lakukan Absen Masuk di aplikasi SIGAP MPA.";
            if(telegram_notify_employee($empId,$text))$sent++;
        }
        json_response(['ok'=>true,'checked'=>$checked,'sent'=>$sent,'server_time'=>now_sql()]);
    }

    if ($path === '/leave-balances' && $httpMethod === 'GET') {
        $user = require_auth();
        sigap_ensure_leave_balance_tables();
        $year = preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? (int)$_GET['year'] : (int)(new DateTimeImmutable('now'))->format('Y');
        if (in_array($user['role'], ['hr_admin','payroll_finance','super_admin'], true)) {
            if (isset($_GET['employee_id']) && (int)$_GET['employee_id'] > 0) {
                $empId = (int)$_GET['employee_id'];
                $empCheck = db()->prepare('SELECT id,full_name,nik FROM employees WHERE id=? LIMIT 1');
                $empCheck->execute([$empId]);
                $emp = $empCheck->fetch();
                if (!$emp) json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
                $summary = sigap_leave_balance_summary(db(), $empId, $year);
                $summary['full_name'] = $emp['full_name'];
                $summary['nik'] = $emp['nik'];
                json_response(['ok'=>true,'data'=>[$summary]]);
            }
            $rows = db()->query("SELECT id, full_name, nik FROM employees WHERE employment_status='active' ORDER BY full_name")->fetchAll();
            $data = [];
            foreach ($rows as $r) {
                $summary = sigap_leave_balance_summary(db(), (int)$r['id'], $year);
                $summary['full_name'] = $r['full_name'];
                $summary['nik'] = $r['nik'];
                $data[] = $summary;
            }
            json_response(['ok'=>true,'data'=>$data]);
        }
        if (!$user['employee_id']) json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke data karyawan.'],422);
        json_response(['ok'=>true,'data'=>[sigap_leave_balance_summary(db(), (int)$user['employee_id'], $year)]]);
    }

    if (count($segments)===3 && $segments[0]==='leave-balances' && $segments[2]==='adjust' && $httpMethod==='POST') {
        $actor = require_roles(['hr_admin','super_admin']);
        sigap_ensure_leave_balance_tables();
        $employeeId = (int)$segments[1];
        $data = request_json();
        require_fields($data, ['year','adjustment_type','amount_days','reason']);
        $allowedTypes = ['carry_over','bonus','koreksi','potongan'];
        if (!in_array($data['adjustment_type'], $allowedTypes, true)) {
            json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Jenis penyesuaian tidak valid.'],422);
        }
        $empCheck = db()->prepare('SELECT id FROM employees WHERE id=? LIMIT 1');
        $empCheck->execute([$employeeId]);
        if (!$empCheck->fetchColumn()) json_response(['ok'=>false,'error'=>'not_found','message'=>'Karyawan tidak ditemukan.'],404);
        $year = (int)$data['year'];
        $amount = decimal_value($data['amount_days']);
        if ($data['adjustment_type'] === 'potongan' && $amount > 0) $amount = -$amount;
        sigap_leave_balance_get_or_create(db(), $employeeId, $year);
        db()->prepare('INSERT INTO employee_leave_balance_adjustments (employee_id,year,adjustment_type,amount_days,reason,created_by_user_id) VALUES (?,?,?,?,?,?)')
            ->execute([$employeeId, $year, $data['adjustment_type'], $amount, clean_string($data['reason'],255), (int)$actor['id']]);
        audit((int)$actor['id'], 'leave_balance_adjusted', 'employee_leave_balance', (string)$employeeId, null, ['year'=>$year,'type'=>$data['adjustment_type'],'amount_days'=>$amount,'reason'=>$data['reason']]);
        json_response(['ok'=>true,'message'=>'Penyesuaian kuota cuti tersimpan.','data'=>sigap_leave_balance_summary(db(), $employeeId, $year)]);
    }

    // Business trips
    if($path==='/trips' && $httpMethod==='GET'){
        $user=require_auth();
        if(in_array($user['role'],['supervisor','hr_admin','payroll_finance','super_admin'],true)){
            $rows=db()->query('SELECT t.*,e.nik,e.full_name,e.department_name FROM business_trips t JOIN employees e ON e.id=t.employee_id ORDER BY t.created_at DESC LIMIT 500')->fetchAll();
        }else{
            $stmt=db()->prepare('SELECT t.*,e.nik,e.full_name,e.department_name FROM business_trips t JOIN employees e ON e.id=t.employee_id WHERE t.employee_id=? ORDER BY t.created_at DESC');$stmt->execute([(int)$user['employee_id']]);$rows=$stmt->fetchAll();
        }
        json_response(['ok'=>true,'data'=>$rows]);
    }
    if($path==='/trips' && $httpMethod==='POST'){
        $user=require_auth(); if(!$user['employee_id'])json_response(['ok'=>false,'error'=>'employee_required','message'=>'Akun tidak terhubung ke karyawan.'],422);
        $data=request_json(); require_fields($data,['project_name','destination','start_date','end_date','purpose']);
        $stmt=db()->prepare('INSERT INTO business_trips (employee_id,project_code,project_name,destination,start_date,end_date,purpose,requested_advance,estimated_expense,estimated_allowance) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([(int)$user['employee_id'],clean_string($data['project_code']??'',100)?:null,clean_string($data['project_name'],200),clean_string($data['destination'],200),$data['start_date'],$data['end_date'],clean_string($data['purpose'],2000),decimal_value($data['requested_advance']??0),decimal_value($data['estimated_expense']??0),decimal_value($data['estimated_allowance']??0)]);
        $id=(int)db()->lastInsertId();audit((int)$user['id'],'trip_created','business_trip',(string)$id,null,$data);json_response(['ok'=>true,'id'=>$id],201);
    }
    if(count($segments)===3 && $segments[0]==='trips' && $segments[2]==='decision' && $httpMethod==='POST'){
        $actor=require_roles(['supervisor','hr_admin','super_admin']);$id=(int)$segments[1];$data=request_json();require_fields($data,['status']);
        if(!in_array($data['status'],['approved','rejected'],true))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Keputusan tidak valid.'],422);
        $stmt=db()->prepare("UPDATE business_trips SET approval_status=?,approved_by_user_id=?,approved_at=NOW(6),approval_note=? WHERE id=? AND approval_status='pending'");
        $stmt->execute([$data['status'],(int)$actor['id'],clean_string($data['approval_note']??'',1000),$id]);
        if(!$stmt->rowCount())json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Perjalanan dinas tidak ditemukan atau sudah diproses.'],409);
        audit((int)$actor['id'],'trip_decided','business_trip',(string)$id,null,$data);json_response(['ok'=>true]);
    }
    if(count($segments)===3 && $segments[0]==='trips' && $segments[2]==='settle' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$id=(int)$segments[1];$data=request_json();require_fields($data,['payroll_period']);
        $pdo=db();$pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT * FROM business_trips WHERE id=? AND approval_status='approved' FOR UPDATE");$stmt->execute([$id]);$trip=$stmt->fetch();
        if(!$trip){$pdo->rollBack();json_response(['ok'=>false,'error'=>'not_found','message'=>'Perjalanan dinas belum disetujui atau tidak ditemukan.'],404);}
        $advance=decimal_value($data['advance_paid']??0);$actual=decimal_value($data['actual_expense']??0);$allowance=decimal_value($data['final_allowance']??0);
        $reimbursement=max(0,$actual-$advance);$refund=max(0,$advance-$actual);
        $period=(string)$data['payroll_period'];
        $pdo->prepare("INSERT INTO payroll_periods (period,status) VALUES (?,'open') ON DUPLICATE KEY UPDATE period=VALUES(period)")->execute([$period]);
        $pid=(int)$pdo->query("SELECT id FROM payroll_periods WHERE period=".$pdo->quote($period))->fetchColumn();
        $upd=$pdo->prepare("UPDATE business_trips SET settlement_status='settled',payroll_period=?,advance_paid=?,actual_expense=?,final_allowance=?,reimbursement_payable=?,refund_due=?,deduct_refund_from_payroll=?,settlement_note=?,settled_by_user_id=?,settled_at=NOW(6) WHERE id=?");
        $deduct=!empty($data['deduct_refund_from_payroll'])?1:0;
        $upd->execute([$period,$advance,$actual,$allowance,$reimbursement,$refund,$deduct,clean_string($data['settlement_note']??'',2000),(int)$actor['id'],$id]);
        $component=$pdo->prepare("INSERT INTO payroll_components (payroll_period_id,employee_id,business_trip_id,project_code,project_name,category,label,treatment,amount,description,source,status,created_by_user_id,approved_by_user_id,approved_at) VALUES (?,?,?,?,?,?,?,?,?,?,'business_trip','approved',?,?,NOW(6))");
        if($allowance>0)$component->execute([$pid,(int)$trip['employee_id'],$id,$trip['project_code'],$trip['project_name'],'travel_allowance','Tunjangan Perjalanan Dinas','earning',$allowance,'Realisasi perjalanan dinas #'.$id,(int)$actor['id'],(int)$actor['id']]);
        if($reimbursement>0)$component->execute([$pid,(int)$trip['employee_id'],$id,$trip['project_code'],$trip['project_name'],'reimbursement','Reimbursement Perjalanan Dinas','reimbursement',$reimbursement,'Realisasi perjalanan dinas #'.$id,(int)$actor['id'],(int)$actor['id']]);
        if($deduct && $refund>0)$component->execute([$pid,(int)$trip['employee_id'],$id,$trip['project_code'],$trip['project_name'],'other_deduction','Pengembalian Uang Muka Dinas','deduction',$refund,'Realisasi perjalanan dinas #'.$id,(int)$actor['id'],(int)$actor['id']]);
        $pdo->commit();audit((int)$actor['id'],'trip_settled','business_trip',(string)$id,$trip,['advance'=>$advance,'actual'=>$actual,'allowance'=>$allowance,'reimbursement'=>$reimbursement,'refund'=>$refund]);json_response(['ok'=>true]);
    }

    // Payroll periods/components
    if($path==='/payroll/periods' && $httpMethod==='GET'){
        require_roles(['payroll_finance','super_admin']);$rows=db()->query('SELECT * FROM payroll_periods ORDER BY period DESC')->fetchAll();json_response(['ok'=>true,'data'=>$rows]);
    }
    if($path==='/payroll/periods' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$data=request_json();require_fields($data,['period']);
        db()->prepare("INSERT INTO payroll_periods (period,status) VALUES (?,'open') ON DUPLICATE KEY UPDATE period=VALUES(period)")->execute([$data['period']]);audit((int)$actor['id'],'payroll_period_opened','payroll_period',$data['period']);json_response(['ok'=>true],201);
    }
    if($path==='/payroll/components' && $httpMethod==='GET'){
        require_roles(['payroll_finance','super_admin']);$period=$_GET['period']??(new DateTimeImmutable())->format('Y-m');
        $stmt=db()->prepare('SELECT c.*,e.nik,e.full_name,p.period FROM payroll_components c JOIN employees e ON e.id=c.employee_id JOIN payroll_periods p ON p.id=c.payroll_period_id WHERE p.period=? ORDER BY c.created_at DESC');$stmt->execute([$period]);json_response(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    if($path==='/payroll/components' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$data=request_json();require_fields($data,['period','employee_id','category','label','treatment','amount','description']);
        $categories=['project_bonus','project_incentive','travel_allowance','salary_adjustment','other_income','other_deduction','reimbursement'];$treatments=['earning','deduction','reimbursement'];
        if(!in_array($data['category'],$categories,true)||!in_array($data['treatment'],$treatments,true))json_response(['ok'=>false,'error'=>'validation_failed','message'=>'Kategori atau perlakuan tidak valid.'],422);
        $pdo=db();$pdo->beginTransaction();$pdo->prepare("INSERT INTO payroll_periods (period,status) VALUES (?,'open') ON DUPLICATE KEY UPDATE period=VALUES(period)")->execute([$data['period']]);
        $stmt=$pdo->prepare('SELECT id,status FROM payroll_periods WHERE period=? FOR UPDATE');$stmt->execute([$data['period']]);$period=$stmt->fetch();if($period['status']==='closed'){$pdo->rollBack();json_response(['ok'=>false,'error'=>'period_closed','message'=>'Periode payroll sudah ditutup.'],409);}
        $ins=$pdo->prepare("INSERT INTO payroll_components (payroll_period_id,employee_id,project_code,project_name,category,label,treatment,amount,description,source,status,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,'manual','draft',?)");
        $ins->execute([(int)$period['id'],(int)$data['employee_id'],clean_string($data['project_code']??'',100)?:null,clean_string($data['project_name']??'',200)?:null,$data['category'],clean_string($data['label'],200),$data['treatment'],decimal_value($data['amount']),clean_string($data['description'],2000),(int)$actor['id']]);$id=(int)$pdo->lastInsertId();$pdo->commit();audit((int)$actor['id'],'payroll_component_created','payroll_component',(string)$id,null,$data);json_response(['ok'=>true,'id'=>$id],201);
    }
    if(count($segments)===4 && $segments[0]==='payroll' && $segments[1]==='components' && $segments[3]==='approve' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$id=(int)$segments[2];$stmt=db()->prepare("UPDATE payroll_components SET status='approved',approved_by_user_id=?,approved_at=NOW(6) WHERE id=? AND status='draft'");$stmt->execute([(int)$actor['id'],$id]);if(!$stmt->rowCount())json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Komponen tidak ditemukan atau sudah diproses.'],409);audit((int)$actor['id'],'payroll_component_approved','payroll_component',(string)$id);json_response(['ok'=>true]);
    }

    // Salary structure
    if($path==='/payroll/salaries' && $httpMethod==='GET'){
        require_roles(['payroll_finance','super_admin']);$rows=db()->query('SELECT s.*,e.nik,e.full_name FROM salary_structures s JOIN employees e ON e.id=s.employee_id ORDER BY e.full_name,s.effective_from DESC')->fetchAll();json_response(['ok'=>true,'data'=>$rows]);
    }
    if($path==='/payroll/salaries' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$d=request_json();require_fields($d,['employee_id','effective_from','base_salary']);
        $stmt=db()->prepare("INSERT INTO salary_structures (employee_id,effective_from,effective_to,base_salary,position_allowance,fixed_allowance,meal_transport,other_income,bpjs_health,bpjs_employment,pph21,loan_deduction,other_deduction,bank_name,status,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?)");
        $stmt->execute([(int)$d['employee_id'],$d['effective_from'],$d['effective_to']??null,decimal_value($d['base_salary']),decimal_value($d['position_allowance']??0),decimal_value($d['fixed_allowance']??0),decimal_value($d['meal_transport']??0),decimal_value($d['other_income']??0),decimal_value($d['bpjs_health']??0),decimal_value($d['bpjs_employment']??0),decimal_value($d['pph21']??0),decimal_value($d['loan_deduction']??0),decimal_value($d['other_deduction']??0),clean_string($d['bank_name']??'',100)?:null,(int)$actor['id']]);$id=(int)db()->lastInsertId();audit((int)$actor['id'],'salary_structure_created','salary_structure',(string)$id,null,['employee_id'=>$d['employee_id'],'effective_from'=>$d['effective_from']]);json_response(['ok'=>true,'id'=>$id],201);
    }
    if(count($segments)===4 && $segments[0]==='payroll' && $segments[1]==='salaries' && $segments[3]==='approve' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$id=(int)$segments[2];$stmt=db()->prepare("UPDATE salary_structures SET status='approved',approved_by_user_id=?,approved_at=NOW(6) WHERE id=? AND status='draft'");$stmt->execute([(int)$actor['id'],$id]);if(!$stmt->rowCount())json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Struktur gaji tidak ditemukan atau sudah diproses.'],409);audit((int)$actor['id'],'salary_structure_approved','salary_structure',(string)$id);json_response(['ok'=>true]);
    }


    if($path==='/payroll/import-preview' && $httpMethod==='POST'){
        require_roles(['payroll_finance','super_admin']);
        if(empty($_FILES['file']['tmp_name']))json_response(['ok'=>false,'message'=>'Pilih file Excel terlebih dahulu.'],422);
        try{$raw=sigap_read_payroll_file($_FILES['file']['tmp_name'],$_FILES['file']['name']);$rows=sigap_payroll_import_rows(db(),$raw);$valid=count(array_filter($rows,fn($r)=>$r['valid']));json_response(['ok'=>true,'rows'=>$rows,'summary'=>['total'=>count($rows),'valid'=>$valid,'invalid'=>count($rows)-$valid,'total_transfer'=>array_sum(array_map(fn($r)=>$r['valid']?$r['total_transfer']:0,$rows))]]);}catch(Throwable $e){json_response(['ok'=>false,'message'=>$e->getMessage()],422);}
    }
    if($path==='/payroll/import-publish' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$d=request_json();$rows=$d['rows']??[];$publish=!empty($d['publish']);if(!$rows)json_response(['ok'=>false,'message'=>'Data preview kosong.'],422);
        foreach($rows as $r)if(empty($r['valid'])||empty($r['employee_id']))json_response(['ok'=>false,'message'=>'Masih ada baris tidak valid.'],422);
        $pdo=db();$pdo->beginTransaction();$created=[];try{foreach($rows as $r){$period=(string)$r['period'];$pdo->prepare("INSERT INTO payroll_periods (period,status) VALUES (?,'open') ON DUPLICATE KEY UPDATE period=VALUES(period)")->execute([$period]);$q=$pdo->prepare('SELECT id,status FROM payroll_periods WHERE period=? FOR UPDATE');$q->execute([$period]);$pp=$q->fetch();if($pp['status']==='closed')throw new RuntimeException('Periode '.$period.' sudah ditutup.');
          $v=$pdo->prepare('SELECT COALESCE(MAX(run_version),0)+1 FROM payroll_runs WHERE payroll_period_id=? AND employee_id=?');$v->execute([(int)$pp['id'],(int)$r['employee_id']]);$version=(int)$v->fetchColumn();
          $gross=(float)$r['base_salary']+(float)$r['overtime_total']+(float)$r['allowance']+(float)$r['bonus']+(float)$r['other_earning'];$ded=(float)$r['late_deduction']+(float)$r['absence_deduction']+(float)$r['other_deduction'];$net=$gross-$ded;$snapshot=['employee'=>['id'=>(int)$r['employee_id'],'nik'=>$r['nik'],'full_name'=>$r['full_name']],'period'=>$period,'salary'=>['base_salary'=>(float)$r['base_salary'],'position_allowance'=>(float)$r['allowance'],'fixed_allowance'=>(float)$r['bonus'],'meal_transport'=>(float)$r['other_earning']],'attendance'=>['overtime_hours'=>(float)$r['overtime_hours'],'overtime_rate'=>(float)$r['overtime_rate'],'overtime_value'=>(float)$r['overtime_total'],'late_penalty'=>(float)$r['late_deduction'],'absence_deduction'=>(float)$r['absence_deduction']],'components'=>['earning'=>0,'deduction'=>(float)$r['other_deduction'],'reimbursement'=>(float)$r['reimbursement']],'gross_income'=>$gross,'total_deductions'=>$ded,'net_salary'=>$net,'reimbursement_total'=>(float)$r['reimbursement'],'total_transfer'=>(float)$r['total_transfer'],'note'=>$r['note'],'source'=>'excel_import'];
          $ins=$pdo->prepare('INSERT INTO payroll_runs (payroll_period_id,employee_id,run_version,attendance_days,late_minutes,overtime_hours,gross_income,total_deductions,net_salary,reimbursement_total,total_transfer,calculation_snapshot,generated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');$ins->execute([(int)$pp['id'],(int)$r['employee_id'],$version,0,0,(float)$r['overtime_hours'],$gross,$ded,$net,(float)$r['reimbursement'],(float)$r['total_transfer'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$actor['id']]);$runId=(int)$pdo->lastInsertId();$doc='MPA/SLIP/'.str_replace('-','/',$period).'/'.$r['nik'].'/V'.$version;$status=$publish?'published':'draft';$pi=$pdo->prepare("INSERT INTO payslips (payroll_run_id,document_number,status,snapshot_json,published_by_user_id,published_at) VALUES (?,?,?,?,?,?)");$pi->execute([$runId,$doc,$status,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$publish?(int)$actor['id']:null,$publish?date('Y-m-d H:i:s.u'):null]);$created[]=['nik'=>$r['nik'],'payslip_id'=>(int)$pdo->lastInsertId()];}
          $pdo->commit();audit((int)$actor['id'],$publish?'payroll_excel_published':'payroll_excel_imported','payroll_import',null,null,['count'=>count($created)]);json_response(['ok'=>true,'created'=>$created,'published'=>$publish]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['ok'=>false,'message'=>$e->getMessage()],422);}
    }

    // Generate payroll and payslips
    if($path==='/payroll/generate' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$d=request_json();require_fields($d,['period']);$period=(string)$d['period'];
        sigap_ensure_shift_tables();
        $pdo=db();$pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payroll_periods (period,status) VALUES (?,'open') ON DUPLICATE KEY UPDATE period=VALUES(period)")->execute([$period]);
        $ps=$pdo->prepare('SELECT * FROM payroll_periods WHERE period=? FOR UPDATE');$ps->execute([$period]);$periodRow=$ps->fetch();if($periodRow['status']==='closed'){$pdo->rollBack();json_response(['ok'=>false,'error'=>'period_closed','message'=>'Periode payroll sudah ditutup.'],409);}
        $employees=$pdo->query("SELECT id,nik,full_name FROM employees WHERE employment_status='active'")->fetchAll();
        $attendanceReport=sigap_build_attendance_report($pdo,$period);
        $attendanceByEmployee=[];
        foreach($attendanceReport['summary'] as $attendanceRow){$attendanceByEmployee[(int)$attendanceRow['employee_id']]=$attendanceRow;}
        $overtimeDetailsByEmployee=[];
        foreach(($attendanceReport['overtime_detail'] ?? []) as $overtimeRow){$overtimeDetailsByEmployee[(int)$overtimeRow['employee_id']][]=$overtimeRow;}
        global $config;$generated=[];
        foreach($employees as $emp){
            $sal=$pdo->prepare("SELECT * FROM salary_structures WHERE employee_id=? AND status='approved' AND effective_from<=LAST_DAY(?) AND (effective_to IS NULL OR effective_to>=CONCAT(?,'-01')) ORDER BY effective_from DESC LIMIT 1");$sal->execute([(int)$emp['id'],$period.'-01',$period]);$salary=$sal->fetch();if(!$salary)continue;
            $a=$attendanceByEmployee[(int)$emp['id']]??['present_days'=>0,'late_minutes'=>0,'overtime_hours'=>0,'work_days'=>0,'on_time_days'=>0,'late_days'=>0,'incomplete_days'=>0,'leave_days'=>0,'permission_days'=>0,'sick_days'=>0,'business_trip_days'=>0,'alpha_days'=>0];
            $overtimeHours=(float)$a['overtime_hours'];
            $comp=$pdo->prepare("SELECT treatment,COALESCE(SUM(amount),0) amount FROM payroll_components WHERE payroll_period_id=? AND employee_id=? AND status='approved' GROUP BY treatment");$comp->execute([(int)$periodRow['id'],(int)$emp['id']]);$components=['earning'=>0.0,'deduction'=>0.0,'reimbursement'=>0.0];foreach($comp->fetchAll() as $c)$components[$c['treatment']]=(float)$c['amount'];
            $overtimeValue=$overtimeHours*(float)($config['attendance']['overtime_rate_per_hour']??0);$latePenalty=(int)$a['late_minutes']*(float)($config['attendance']['penalty_per_minute']??0);
            $fixedEarnings=(float)$salary['base_salary']+(float)$salary['position_allowance']+(float)$salary['fixed_allowance']+(float)$salary['meal_transport']+(float)$salary['other_income'];
            $fixedDeductions=(float)$salary['bpjs_health']+(float)$salary['bpjs_employment']+(float)$salary['pph21']+(float)$salary['loan_deduction']+(float)$salary['other_deduction'];
            $gross=$fixedEarnings+$overtimeValue+$components['earning'];$deductions=$fixedDeductions+$latePenalty+$components['deduction'];$net=$gross-$deductions;$total=$net+$components['reimbursement'];
            $v=$pdo->prepare('SELECT COALESCE(MAX(run_version),0)+1 FROM payroll_runs WHERE payroll_period_id=? AND employee_id=?');$v->execute([(int)$periodRow['id'],(int)$emp['id']]);$version=(int)$v->fetchColumn();
            $snapshot=['employee'=>$emp,'period'=>$period,'salary'=>$salary,'attendance'=>['days'=>(float)$a['present_days'],'work_days'=>(float)$a['work_days'],'on_time_days'=>(float)$a['on_time_days'],'late_days'=>(float)$a['late_days'],'incomplete_days'=>(float)$a['incomplete_days'],'leave_days'=>(float)$a['leave_days'],'permission_days'=>(float)$a['permission_days'],'sick_days'=>(float)$a['sick_days'],'business_trip_days'=>(float)$a['business_trip_days'],'alpha_days'=>(float)$a['alpha_days'],'late_minutes'=>(int)$a['late_minutes'],'overtime_hours'=>$overtimeHours,'overtime_value'=>$overtimeValue,'overtime_details'=>($overtimeDetailsByEmployee[(int)$emp['id']] ?? []),'late_penalty'=>$latePenalty],'components'=>$components,'gross_income'=>$gross,'total_deductions'=>$deductions,'net_salary'=>$net,'reimbursement_total'=>$components['reimbursement'],'total_transfer'=>$total];
            $run=$pdo->prepare('INSERT INTO payroll_runs (payroll_period_id,employee_id,run_version,attendance_days,late_minutes,overtime_hours,gross_income,total_deductions,net_salary,reimbursement_total,total_transfer,calculation_snapshot,generated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $run->execute([(int)$periodRow['id'],(int)$emp['id'],$version,(float)$a['present_days'],(int)$a['late_minutes'],$overtimeHours,$gross,$deductions,$net,$components['reimbursement'],$total,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$actor['id']]);$runId=(int)$pdo->lastInsertId();
            $doc='MPA/SLIP/'.str_replace('-','/',$period).'/'.$emp['nik'].'/V'.$version;
            $pdo->prepare("INSERT INTO payslips (payroll_run_id,document_number,status,snapshot_json) VALUES (?,?,'draft',?)")->execute([$runId,$doc,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $generated[]=['employee_id'=>(int)$emp['id'],'nik'=>$emp['nik'],'run_id'=>$runId,'version'=>$version];
        }
        $pdo->commit();audit((int)$actor['id'],'payroll_generated','payroll_period',$period,null,['count'=>count($generated)]);json_response(['ok'=>true,'generated'=>$generated]);
    }

    if($path==='/payslips' && $httpMethod==='GET'){
        $user=require_auth();$period=$_GET['period']??null;
        if(in_array($user['role'],['payroll_finance','super_admin'],true)){
            $sql="SELECT p.id,p.document_number,p.status,p.published_at,p.revoked_at,r.run_version,r.gross_income,r.total_deductions,r.net_salary,r.reimbursement_total,r.total_transfer,pp.period,e.nik,e.full_name FROM payslips p JOIN payroll_runs r ON r.id=p.payroll_run_id JOIN payroll_periods pp ON pp.id=r.payroll_period_id JOIN employees e ON e.id=r.employee_id";$params=[];
            if($period){$sql.=' WHERE pp.period=?';$params[]=$period;}$sql.=' ORDER BY pp.period DESC,e.full_name,p.created_at DESC';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
        }else{
            $sql="SELECT p.id,p.document_number,p.status,p.published_at,r.run_version,r.gross_income,r.total_deductions,r.net_salary,r.reimbursement_total,r.total_transfer,pp.period,e.nik,e.full_name,p.snapshot_json FROM payslips p JOIN payroll_runs r ON r.id=p.payroll_run_id JOIN payroll_periods pp ON pp.id=r.payroll_period_id JOIN employees e ON e.id=r.employee_id WHERE r.employee_id=? AND p.status='published'";$params=[(int)$user['employee_id']];if($period){$sql.=' AND pp.period=?';$params[]=$period;}$sql.=' ORDER BY pp.period DESC,p.published_at DESC';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
        }
        json_response(['ok'=>true,'data'=>$rows]);
    }
    if(count($segments)===3 && $segments[0]==='payslips' && $segments[2]==='view' && $httpMethod==='GET'){
        $user=require_auth();$id=(int)$segments[1];$sql='SELECT p.*,r.employee_id,pp.period,e.nik,e.full_name FROM payslips p JOIN payroll_runs r ON r.id=p.payroll_run_id JOIN payroll_periods pp ON pp.id=r.payroll_period_id JOIN employees e ON e.id=r.employee_id WHERE p.id=?';$stmt=db()->prepare($sql);$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)json_response(['ok'=>false,'error'=>'not_found','message'=>'Slip tidak ditemukan.'],404);
        $canAdmin=in_array($user['role'],['payroll_finance','super_admin'],true);if(!$canAdmin && ((int)$row['employee_id']!==(int)$user['employee_id'] || $row['status']!=='published'))json_response(['ok'=>false,'error'=>'forbidden','message'=>'Slip tidak dapat diakses.'],403);
        audit((int)$user['id'],'payslip_viewed','payslip',(string)$id);$row['snapshot']=json_decode((string)$row['snapshot_json'],true);unset($row['snapshot_json']);json_response(['ok'=>true,'data'=>$row]);
    }
    if(count($segments)===3 && $segments[0]==='payslips' && $segments[2]==='publish' && $httpMethod==='POST'){
        $actor=require_roles(['payroll_finance','super_admin']);$id=(int)$segments[1];$stmt=db()->prepare("UPDATE payslips SET status='published',published_by_user_id=?,published_at=NOW(6) WHERE id=? AND status='draft'");$stmt->execute([(int)$actor['id'],$id]);if(!$stmt->rowCount())json_response(['ok'=>false,'error'=>'not_found_or_processed','message'=>'Slip tidak ditemukan atau bukan draft.'],409);audit((int)$actor['id'],'payslip_published','payslip',(string)$id);json_response(['ok'=>true]);
    }

    if ($path === '/audit' && $httpMethod === 'GET') {
        require_roles(['super_admin']);
        $rows=db()->query('SELECT a.*,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_user_id ORDER BY a.created_at DESC LIMIT 500')->fetchAll();json_response(['ok'=>true,'data'=>$rows]);
    }


    // ---- Rekrutmen: HR/Admin (login + role) ----
    if ($path === '/recruitment/candidates' && $httpMethod === 'GET') {
        require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        sigap_recruitment_seed_if_empty();
        $rows = db()->query("SELECT c.*,
            (SELECT s.id FROM test_sessions s WHERE s.candidate_id=c.id ORDER BY s.created_at DESC LIMIT 1) AS latest_session_id
            FROM test_candidates c ORDER BY c.created_at DESC")->fetchAll();
        json_response(['ok' => true, 'data' => $rows]);
    }

    if ($path === '/recruitment/candidates' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        sigap_recruitment_seed_if_empty();
        $data = request_json();
        require_fields($data, ['full_name', 'applied_position']);
        $stmt = db()->prepare('INSERT INTO test_candidates (full_name,applied_position,phone,email,notes,created_by_user_id) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            clean_string($data['full_name'], 160),
            clean_string($data['applied_position'], 120),
            !empty($data['phone']) ? clean_string($data['phone'], 30) : null,
            !empty($data['email']) ? clean_string($data['email'], 160) : null,
            !empty($data['notes']) ? clean_string($data['notes'], 2000) : null,
            (int)$actor['id'],
        ]);
        $id = (int)db()->lastInsertId();
        audit((int)$actor['id'], 'candidate_created', 'test_candidate', (string)$id, null, ['full_name' => $data['full_name']]);
        json_response(['ok' => true, 'id' => $id, 'message' => 'Kandidat berhasil ditambahkan.'], 201);
    }

    if (count($segments) === 4 && $segments[0] === 'recruitment' && $segments[1] === 'candidates' && $segments[3] === 'sessions' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        sigap_recruitment_seed_if_empty();
        $candidateId = (int)$segments[2];
        $check = db()->prepare('SELECT id,full_name,applied_position,phone FROM test_candidates WHERE id=?');
        $check->execute([$candidateId]);
        $candidate = $check->fetch();
        if (!$candidate) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Kandidat tidak ditemukan.'], 404);
        $iqBank = db()->query("SELECT id FROM test_question_banks WHERE test_type='iq' AND is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
        $discBank = db()->query("SELECT id FROM test_question_banks WHERE test_type='disc' AND is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$iqBank || !$discBank) json_response(['ok' => false, 'error' => 'no_bank', 'message' => 'Bank soal belum tersedia.'], 422);
        $data = request_json();
        $hours = isset($data['expires_hours']) ? max(1, (int)$data['expires_hours']) : 48;
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable("+{$hours} hours"))->format('Y-m-d H:i:s');
        $stmt = db()->prepare("INSERT INTO test_sessions (candidate_id,iq_bank_id,disc_bank_id,access_token,status,invited_by_user_id,expires_at) VALUES (?,?,?,?,'not_started',?,?)");
        $stmt->execute([$candidateId, (int)$iqBank['id'], (int)$discBank['id'], $token, (int)$actor['id'], $expiresAt]);
        $sessionId = (int)db()->lastInsertId();
        audit((int)$actor['id'], 'recruitment_session_created', 'test_session', (string)$sessionId);
        $testUrl = rtrim((string)($config['app']['url'] ?? ''), '/') . '/test.html?t=' . $token;
        json_response(['ok' => true, 'id' => $sessionId, 'access_token' => $token, 'test_url' => $testUrl, 'expires_at' => $expiresAt,
            'candidate_name' => $candidate['full_name'], 'applied_position' => $candidate['applied_position'], 'phone' => $candidate['phone']], 201);
    }

    if ($path === '/recruitment/sessions' && $httpMethod === 'GET') {
        require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        sigap_recruitment_seed_if_empty();
        $rows = db()->query("SELECT s.id,s.status,s.iq_raw_score,s.iq_scaled_score,s.iq_category,s.iq_recommendation,s.access_token,
            s.expires_at,s.created_at,c.id AS candidate_id,c.full_name,c.applied_position,c.phone
            FROM test_sessions s JOIN test_candidates c ON c.id=s.candidate_id
            ORDER BY s.created_at DESC")->fetchAll();
        json_response(['ok' => true, 'data' => $rows]);
    }

    if (count($segments) === 3 && $segments[0] === 'recruitment' && $segments[1] === 'sessions' && $httpMethod === 'GET') {
        require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        $id = (int)$segments[2];
        $stmt = db()->prepare('SELECT s.*, c.full_name, c.applied_position, c.phone, c.email FROM test_sessions s JOIN test_candidates c ON c.id=s.candidate_id WHERE s.id=?');
        $stmt->execute([$id]);
        $session = $stmt->fetch();
        if (!$session) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Sesi tidak ditemukan.'], 404);
        $iqRows = db()->prepare('SELECT q.question_number, q.question_text, a.selected_option, a.is_correct,
            (SELECT option_label FROM test_question_options WHERE question_id=q.id AND is_correct=1 LIMIT 1) AS correct_option
            FROM test_questions q LEFT JOIN test_iq_answers a ON a.question_id=q.id AND a.session_id=?
            WHERE q.bank_id=? ORDER BY q.question_number');
        $iqRows->execute([$id, $session['iq_bank_id']]);
        $discRows = db()->prepare('SELECT q.question_number, a.most_option, a.least_option
            FROM test_questions q LEFT JOIN test_disc_answers a ON a.question_id=q.id AND a.session_id=?
            WHERE q.bank_id=? ORDER BY q.question_number');
        $discRows->execute([$id, $session['disc_bank_id']]);
        json_response(['ok' => true, 'data' => [
            'session' => $session,
            'iq_answers' => $iqRows->fetchAll(),
            'disc_answers' => $discRows->fetchAll(),
            'disc_summary' => $session['disc_result_json'] ? json_decode((string)$session['disc_result_json'], true) : null,
        ]]);
    }

    if (count($segments) === 4 && $segments[0] === 'recruitment' && $segments[1] === 'sessions' && $segments[3] === 'resend' && $httpMethod === 'POST') {
        $actor = require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        $id = (int)$segments[2];
        $check = db()->prepare('SELECT s.id,s.status,c.full_name,c.applied_position,c.phone FROM test_sessions s JOIN test_candidates c ON c.id=s.candidate_id WHERE s.id=?');
        $check->execute([$id]);
        $row = $check->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Sesi tidak ditemukan.'], 404);
        if ($row['status'] === 'completed') json_response(['ok' => false, 'error' => 'already_completed', 'message' => 'Tes sudah selesai, tidak bisa generate ulang link.'], 422);
        $data = request_json();
        $hours = isset($data['expires_hours']) ? max(1, (int)$data['expires_hours']) : 48;
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable("+{$hours} hours"))->format('Y-m-d H:i:s');
        db()->prepare("UPDATE test_sessions SET access_token=?, expires_at=?, status='not_started' WHERE id=?")->execute([$token, $expiresAt, $id]);
        audit((int)$actor['id'], 'recruitment_session_resent', 'test_session', (string)$id);
        $testUrl = rtrim((string)($config['app']['url'] ?? ''), '/') . '/test.html?t=' . $token;
        json_response(['ok' => true, 'access_token' => $token, 'test_url' => $testUrl, 'expires_at' => $expiresAt,
            'candidate_name' => $row['full_name'], 'applied_position' => $row['applied_position'], 'phone' => $row['phone']]);
    }

    // ---- Rekrutmen: kandidat publik (token-based, tanpa login) ----
    if (count($segments) === 3 && $segments[0] === 'public' && $segments[1] === 'test' && $httpMethod === 'GET') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        sigap_recruitment_log_event((int)$session['id'], 'link_opened');
        $iqBank = sigap_recruitment_bank((int)$session['iq_bank_id']);
        $discBank = sigap_recruitment_bank((int)$session['disc_bank_id']);
        $payload = [
            'candidate_name' => $session['candidate_name'],
            'applied_position' => $session['applied_position'],
            'status' => $session['status'],
            'iq_duration_minutes' => (int)($iqBank['duration_minutes'] ?? 30),
            'iq_total_questions' => (int)($iqBank['total_questions'] ?? 60),
            'disc_duration_minutes' => (int)($discBank['duration_minutes'] ?? 15),
            'disc_total_questions' => (int)($discBank['total_questions'] ?? 24),
            'iq_started_at' => $session['iq_started_at'],
            'disc_started_at' => $session['disc_started_at'],
        ];
        if ($session['status'] === 'iq_in_progress') {
            $payload['questions'] = sigap_recruitment_bank_questions((int)$session['iq_bank_id']);
            $ansStmt = db()->prepare('SELECT question_id,selected_option FROM test_iq_answers WHERE session_id=?');
            $ansStmt->execute([$session['id']]);
            $payload['answers'] = $ansStmt->fetchAll();
        } elseif ($session['status'] === 'disc_in_progress') {
            $payload['questions'] = sigap_recruitment_bank_questions((int)$session['disc_bank_id']);
            $ansStmt = db()->prepare('SELECT question_id,most_option,least_option FROM test_disc_answers WHERE session_id=?');
            $ansStmt->execute([$session['id']]);
            $payload['answers'] = $ansStmt->fetchAll();
        }
        json_response(['ok' => true, 'data' => $payload, 'server_time' => now_sql(), 'csrf' => csrf_token()]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'start-iq' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] === 'expired') json_response(['ok' => false, 'error' => 'expired', 'message' => 'Link tes sudah kedaluwarsa.'], 410);
        if ($session['status'] !== 'not_started') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Tes IQ sudah dimulai atau sudah selesai.'], 422);
        $startedAt = now_sql();
        db()->prepare("UPDATE test_sessions SET status='iq_in_progress', iq_started_at=? WHERE id=?")->execute([$startedAt, $session['id']]);
        sigap_recruitment_log_event((int)$session['id'], 'iq_started');
        $bank = sigap_recruitment_bank((int)$session['iq_bank_id']);
        json_response(['ok' => true, 'data' => sigap_recruitment_bank_questions((int)$session['iq_bank_id']), 'server_time' => now_sql(), 'iq_started_at' => $startedAt, 'duration_minutes' => (int)($bank['duration_minutes'] ?? 30)]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'answer-iq' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] !== 'iq_in_progress') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Sesi tes IQ tidak aktif.'], 422);
        $data = request_json();
        require_fields($data, ['question_id', 'selected_option']);
        $questionId = (int)$data['question_id'];
        $label = strtoupper(clean_string($data['selected_option'], 1));
        $optStmt = db()->prepare('SELECT o.is_correct FROM test_question_options o JOIN test_questions q ON q.id=o.question_id WHERE o.question_id=? AND o.option_label=? AND q.bank_id=?');
        $optStmt->execute([$questionId, $label, $session['iq_bank_id']]);
        $opt = $optStmt->fetch();
        if (!$opt) json_response(['ok' => false, 'error' => 'invalid_option', 'message' => 'Pilihan jawaban tidak valid.'], 422);
        $stmt = db()->prepare('INSERT INTO test_iq_answers (session_id,question_id,selected_option,is_correct,answered_at) VALUES (?,?,?,?,NOW(6))
            ON DUPLICATE KEY UPDATE selected_option=VALUES(selected_option), is_correct=VALUES(is_correct), answered_at=VALUES(answered_at)');
        $stmt->execute([$session['id'], $questionId, $label, (int)$opt['is_correct']]);
        json_response(['ok' => true]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'submit-iq' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] !== 'iq_in_progress') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Sesi tes IQ tidak aktif.'], 422);
        $result = sigap_recruitment_finalize_iq($session);
        json_response(['ok' => true, 'data' => $result]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'start-disc' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] !== 'iq_completed') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Tes IQ belum diselesaikan.'], 422);
        $startedAt = now_sql();
        db()->prepare("UPDATE test_sessions SET status='disc_in_progress', disc_started_at=? WHERE id=?")->execute([$startedAt, $session['id']]);
        sigap_recruitment_log_event((int)$session['id'], 'disc_started');
        $bank = sigap_recruitment_bank((int)$session['disc_bank_id']);
        json_response(['ok' => true, 'data' => sigap_recruitment_bank_questions((int)$session['disc_bank_id']), 'server_time' => now_sql(), 'disc_started_at' => $startedAt, 'duration_minutes' => (int)($bank['duration_minutes'] ?? 15)]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'answer-disc' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] !== 'disc_in_progress') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Sesi tes DISC tidak aktif.'], 422);
        $data = request_json();
        require_fields($data, ['question_id', 'most_option', 'least_option']);
        $questionId = (int)$data['question_id'];
        $most = strtoupper(clean_string($data['most_option'], 1));
        $least = strtoupper(clean_string($data['least_option'], 1));
        if ($most === $least) json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Pilihan Mirip dan Tidak Mirip tidak boleh sama.'], 422);
        $checkStmt = db()->prepare('SELECT COUNT(DISTINCT o.option_label) FROM test_question_options o JOIN test_questions q ON q.id=o.question_id WHERE o.question_id=? AND o.option_label IN (?,?) AND q.bank_id=?');
        $checkStmt->execute([$questionId, $most, $least, $session['disc_bank_id']]);
        if ((int)$checkStmt->fetchColumn() !== 2) json_response(['ok' => false, 'error' => 'invalid_option', 'message' => 'Pilihan jawaban tidak valid.'], 422);
        $stmt = db()->prepare('INSERT INTO test_disc_answers (session_id,question_id,most_option,least_option,answered_at) VALUES (?,?,?,?,NOW(6))
            ON DUPLICATE KEY UPDATE most_option=VALUES(most_option), least_option=VALUES(least_option), answered_at=VALUES(answered_at)');
        $stmt->execute([$session['id'], $questionId, $most, $least]);
        json_response(['ok' => true]);
    }

    if (count($segments) === 4 && $segments[0] === 'public' && $segments[1] === 'test' && $segments[3] === 'submit-disc' && $httpMethod === 'POST') {
        sigap_ensure_recruitment_tables();
        $session = sigap_recruitment_session_by_token(clean_string($segments[2], 64));
        if ($session['status'] !== 'disc_in_progress') json_response(['ok' => false, 'error' => 'invalid_state', 'message' => 'Sesi tes DISC tidak aktif.'], 422);
        $result = sigap_recruitment_finalize_disc($session);
        json_response(['ok' => true, 'data' => $result]);
    }


    // ---- Rekrutmen: kelola bank soal (super_admin) ----
    if ($path === '/recruitment/question-banks' && $httpMethod === 'GET') {
        require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        sigap_recruitment_seed_if_empty();
        $rows = db()->query("SELECT b.*, (SELECT COUNT(*) FROM test_questions q WHERE q.bank_id=b.id) AS question_count FROM test_question_banks b ORDER BY b.test_type, b.id DESC")->fetchAll();
        json_response(['ok' => true, 'data' => $rows]);
    }

    if (count($segments) === 3 && $segments[0] === 'recruitment' && $segments[1] === 'question-banks' && $httpMethod === 'GET') {
        require_roles(['hr_admin', 'super_admin']);
        sigap_ensure_recruitment_tables();
        $bankId = (int)$segments[2];
        $bank = sigap_recruitment_bank($bankId);
        if (!$bank) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Bank soal tidak ditemukan.'], 404);
        json_response(['ok' => true, 'data' => ['bank' => $bank, 'questions' => sigap_recruitment_bank_questions($bankId)]]);
    }

    if (count($segments) === 3 && $segments[0] === 'recruitment' && $segments[1] === 'question-banks' && $httpMethod === 'PUT') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_recruitment_tables();
        $bankId = (int)$segments[2];
        $bank = sigap_recruitment_bank($bankId);
        if (!$bank) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Bank soal tidak ditemukan.'], 404);
        $data = request_json();
        require_fields($data, ['title', 'duration_minutes']);
        $duration = max(1, (int)$data['duration_minutes']);
        $threshold = ($bank['test_type'] === 'iq' && $data['pass_threshold'] !== '' && isset($data['pass_threshold'])) ? (int)$data['pass_threshold'] : null;
        $isActive = !empty($data['is_active']) ? 1 : 0;
        db()->prepare('UPDATE test_question_banks SET title=?, duration_minutes=?, pass_threshold=?, is_active=? WHERE id=?')
            ->execute([clean_string($data['title'], 160), $duration, $threshold, $isActive, $bankId]);
        audit((int)$actor['id'], 'recruitment_bank_updated', 'test_question_bank', (string)$bankId, $bank, $data);
        json_response(['ok' => true, 'message' => 'Pengaturan bank soal diperbarui.']);
    }

    if (count($segments) === 5 && $segments[0] === 'recruitment' && $segments[1] === 'question-banks' && $segments[3] === 'questions' && $httpMethod === 'PUT') {
        $actor = require_roles(['super_admin']);
        sigap_ensure_recruitment_tables();
        $bankId = (int)$segments[2];
        $questionId = (int)$segments[4];
        $bank = sigap_recruitment_bank($bankId);
        if (!$bank) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Bank soal tidak ditemukan.'], 404);
        $qCheck = db()->prepare('SELECT id FROM test_questions WHERE id=? AND bank_id=?');
        $qCheck->execute([$questionId, $bankId]);
        if (!$qCheck->fetchColumn()) json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Soal tidak ditemukan pada bank ini.'], 404);
        $data = request_json();
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        if (!$options) json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Opsi jawaban wajib diisi.'], 422);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($bank['test_type'] === 'iq') {
                require_fields($data, ['question_text']);
                $pdo->prepare('UPDATE test_questions SET question_text=? WHERE id=?')->execute([clean_string($data['question_text'], 2000), $questionId]);
                $correctCount = 0;
                foreach ($options as $opt) {
                    $label = strtoupper(clean_string($opt['label'] ?? '', 1));
                    $text = clean_string($opt['text'] ?? '', 255);
                    $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
                    if ($isCorrect) $correctCount++;
                    if ($label === '' || $text === '') { $pdo->rollBack(); json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Setiap opsi wajib punya label dan teks.'], 422); }
                    $pdo->prepare('UPDATE test_question_options SET option_text=?, is_correct=? WHERE question_id=? AND option_label=?')
                        ->execute([$text, $isCorrect, $questionId, $label]);
                }
                if ($correctCount !== 1) { $pdo->rollBack(); json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Harus tepat 1 opsi yang ditandai benar.'], 422); }
            } else {
                $seen = [];
                foreach ($options as $opt) {
                    $label = strtoupper(clean_string($opt['label'] ?? '', 1));
                    $text = clean_string($opt['text'] ?? '', 255);
                    $trait = strtoupper(clean_string($opt['trait'] ?? '', 1));
                    if ($label === '' || $text === '' || !in_array($trait, ['D', 'I', 'S', 'C'], true)) { $pdo->rollBack(); json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Setiap opsi DISC wajib punya teks dan trait D/I/S/C.'], 422); }
                    $seen[] = $trait;
                    $pdo->prepare('UPDATE test_question_options SET option_text=?, disc_trait=? WHERE question_id=? AND option_label=?')
                        ->execute([$text, $trait, $questionId, $label]);
                }
                if (count(array_unique($seen)) !== 4) { $pdo->rollBack(); json_response(['ok' => false, 'error' => 'validation_failed', 'message' => 'Satu blok DISC harus punya tepat 1 opsi untuk masing-masing trait D, I, S, C.'], 422); }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        audit((int)$actor['id'], 'recruitment_question_updated', 'test_question', (string)$questionId);
        json_response(['ok' => true, 'message' => 'Soal berhasil diperbarui.']);
    }

    json_response(['ok' => false, 'error' => 'not_found', 'message' => 'Endpoint tidak ditemukan.'], 404);
} catch (PDOException $e) {
    error_log('SIGAP MPA PDO exception: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        json_response(['ok'=>false,'error'=>'constraint_failed','message'=>'Data tidak dapat disimpan karena duplikat atau masih dipakai data lain.'],409);
    }
    json_response(['ok'=>false,'error'=>'database_error','message'=>'Terjadi kesalahan pada database.'],500);


} catch (Throwable $e) {
    error_log('SIGAP MPA exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_response(['ok'=>false,'error'=>'server_error','message'=>'Terjadi kesalahan pada server.'],500);
}
